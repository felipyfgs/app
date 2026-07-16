<?php

namespace App\Services\Activation;

use App\Enums\ActivationMethod;
use App\Enums\ActivationPurpose;
use App\Enums\PlatformRole;
use App\Models\AccountActivation;
use App\Models\Office;
use App\Models\OfficeMembership;
use App\Models\PlatformMembership;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Cria PLATFORM_ADMIN pendente sem OfficeMembership.
 */
final class CreatePendingPlatformAdminService
{
    public function __construct(
        private readonly ActivationCredentialService $credentials,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array{
     *   name: string,
     *   email: string,
     *   method: ActivationMethod|string,
     *   default_office_id?: int|null
     * }  $input
     * @return array<string, mixed>
     */
    public function create(array $input, User $actor): array
    {
        $method = $input['method'] instanceof ActivationMethod
            ? $input['method']
            : ActivationMethod::from((string) $input['method']);

        $name = trim((string) $input['name']);
        $email = $this->credentials->normalizeEmail((string) $input['email']);

        $defaultOfficeId = isset($input['default_office_id']) && $input['default_office_id'] !== null
            ? (int) $input['default_office_id']
            : $this->resolveDefaultOfficeId($actor);

        if ($defaultOfficeId === null) {
            throw ActivationException::invalid('Office padrão obrigatório para administrador global.');
        }

        $office = Office::query()
            ->whereKey($defaultOfficeId)
            ->where('is_active', true)
            ->first();

        if ($office === null) {
            throw ActivationException::invalid('Office padrão inválido ou inativo.');
        }

        if (User::query()->where('email', $email)->exists()) {
            throw ActivationException::emailTaken();
        }

        $issued = $this->credentials->issueSecret($method);
        $expiresAt = $this->credentials->expiresAtFor();

        $result = DB::transaction(function () use ($name, $email, $method, $issued, $expiresAt, $actor, $defaultOfficeId) {
            if (User::query()->where('email', $email)->lockForUpdate()->exists()) {
                throw ActivationException::emailTaken();
            }

            // Qualquer grant existente (mesmo e-mail) já foi bloqueado pelo unique email.
            // Dupla checagem de memberships se e-mail fosse reutilizado (não deve ocorrer).
            $user = User::query()->create([
                'name' => $name,
                'email' => $email,
                'password' => $this->credentials->makeSentinelPasswordHash(),
                'is_active' => false,
                'password_change_required' => true,
            ]);

            // Garantia explícita: zero OfficeMembership.
            if (OfficeMembership::query()->where('user_id', $user->id)->exists()) {
                throw ActivationException::conflict('Estado inconsistente ao criar administrador global.');
            }

            $pm = PlatformMembership::query()->create([
                'user_id' => $user->id,
                'role' => PlatformRole::PlatformAdmin,
                'is_active' => false,
                'default_office_id' => $defaultOfficeId,
            ]);

            $activation = AccountActivation::query()->create([
                'purpose' => ActivationPurpose::PlatformAdmin,
                'method' => $method,
                'user_id' => $user->id,
                'office_id' => null,
                'office_membership_id' => null,
                'platform_membership_id' => $pm->id,
                'email_normalized' => $email,
                'secret_hash' => $issued['hash'],
                'expires_at' => $expiresAt,
                'generation' => 1,
                'created_by_user_id' => $actor->id,
            ]);

            $this->audit->record(
                action: 'platform_admin.pending_created',
                result: 'SUCCESS',
                subject: $user,
                context: [
                    'method' => $method->value,
                    'email_masked' => AccountActivation::maskEmail($email),
                    'default_office_id' => $defaultOfficeId,
                ],
                userId: $actor->id,
            );

            return [$user, $pm, $activation];
        });

        /** @var User $user */
        /** @var PlatformMembership $pm */
        /** @var AccountActivation $activation */
        [$user, $pm, $activation] = $result;

        $payload = [
            'admin' => $this->sanitizeAdmin($user, $pm, $activation),
            'credential_delivery' => 'delivered',
            'method' => $method->value,
            'expires_at' => $expiresAt->toIso8601String(),
        ];

        if (isset($issued['activation_url'])) {
            $payload['activation_url'] = $issued['activation_url'];
        }
        if (isset($issued['temporary_password'])) {
            $payload['temporary_password'] = $issued['temporary_password'];
        }

        return $payload;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listAdmins(): array
    {
        return PlatformMembership::query()
            ->with(['user', 'defaultOffice'])
            ->where('role', PlatformRole::PlatformAdmin)
            ->orderBy('id')
            ->get()
            ->map(function (PlatformMembership $pm) {
                $activation = AccountActivation::query()
                    ->where('platform_membership_id', $pm->id)
                    ->where('purpose', ActivationPurpose::PlatformAdmin)
                    ->orderByDesc('generation')
                    ->orderByDesc('id')
                    ->first();

                return $this->sanitizeAdmin($pm->user, $pm, $activation);
            })
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function showAdmin(User $user): array
    {
        $pm = PlatformMembership::query()
            ->where('user_id', $user->id)
            ->where('role', PlatformRole::PlatformAdmin)
            ->first();

        if ($pm === null) {
            throw ActivationException::notFound('Administrador global não encontrado.');
        }

        $activation = AccountActivation::query()
            ->where('platform_membership_id', $pm->id)
            ->where('purpose', ActivationPurpose::PlatformAdmin)
            ->orderByDesc('generation')
            ->orderByDesc('id')
            ->first();

        return $this->sanitizeAdmin($user, $pm, $activation);
    }

    /**
     * @return array<string, mixed>
     */
    public function sanitizeAdmin(?User $user, PlatformMembership $pm, ?AccountActivation $activation): array
    {
        return [
            'user_id' => $user?->id ?? $pm->user_id,
            'name' => $user?->name,
            'email' => $user?->email,
            'is_active' => (bool) ($user?->is_active && $pm->is_active),
            'membership_active' => $pm->is_active,
            'password_change_required' => (bool) $user?->password_change_required,
            'default_office_id' => $pm->default_office_id,
            'default_office' => $pm->defaultOffice === null ? null : [
                'id' => $pm->defaultOffice->id,
                'name' => $pm->defaultOffice->name,
                'slug' => $pm->defaultOffice->slug,
            ],
            'activation' => $activation?->toSanitizedArray(),
            'created_at' => $pm->created_at?->toIso8601String(),
        ];
    }

    private function resolveDefaultOfficeId(User $actor): ?int
    {
        $pm = PlatformMembership::query()
            ->where('user_id', $actor->id)
            ->where('role', PlatformRole::PlatformAdmin)
            ->where('is_active', true)
            ->first();

        if ($pm?->default_office_id !== null) {
            return (int) $pm->default_office_id;
        }

        $id = Office::query()->where('is_active', true)->orderBy('id')->value('id');

        return $id !== null ? (int) $id : null;
    }
}
