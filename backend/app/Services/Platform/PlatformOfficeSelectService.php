<?php

namespace App\Services\Platform;

use App\Enums\OfficeAccessMode;
use App\Enums\OfficeRole;
use App\Models\Office;
use App\Models\PlatformPrivilegedAuditEvent;
use App\Models\User;
use App\Support\CurrentOffice;
use App\Support\FeatureFlags;
use App\Support\PlatformPrivilegedContext;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Seletor global de office para PLATFORM_ADMIN (modo privilegiado).
 *
 * Não cria OfficeMembership, não altera users.selected_office_id.
 * Sessão: {@see PlatformPrivilegedContext::SESSION_KEY}.
 */
final class PlatformOfficeSelectService
{
    public function __construct(
        private readonly CurrentOffice $currentOffice,
    ) {}

    /**
     * Lista offices ativos (metadados não-fiscais) para o seletor global.
     *
     * @return list<array{id: int, name: string|null, slug: string|null, is_active: bool}>
     */
    public function listOffices(): array
    {
        return Office::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->orderBy('id')
            ->get(['id', 'name', 'slug', 'is_active'])
            ->map(fn (Office $o) => [
                'id' => $o->id,
                'name' => $o->name,
                'slug' => $o->slug,
                'is_active' => (bool) $o->is_active,
            ])
            ->values()
            ->all();
    }

    /**
     * @throws HttpException
     */
    public function select(User $user, int $targetOfficeId, Request $request): Office
    {
        if (! $user->is_active || ! $user->isPlatformAdmin()) {
            abort(403, 'Ação restrita a administradores da plataforma.');
        }

        if (! FeatureFlags::isPlatformPrivilegedContextEnabled()) {
            $this->auditDenied($user, $targetOfficeId, 'privileged_context_disabled');

            throw new HttpException(
                403,
                'Contexto privilegiado da plataforma indisponível.',
                null,
                ['X-Error-Code' => 'privileged_context_disabled'],
            );
        }

        $fromOfficeId = $this->currentOffice->isPlatformPrivileged()
            ? $this->currentOffice->id()
            : null;

        $office = Office::query()
            ->whereKey($targetOfficeId)
            ->where('is_active', true)
            ->first();

        if ($office === null) {
            $this->auditDenied($user, $targetOfficeId, 'office_not_found_or_inactive');

            abort(404, 'Escritório não encontrado.');
        }

        // Não altera membership nem selected_office_id do usuário.
        // Sessão SPA + cache por usuário (token clients / suite de testes).
        $this->currentOffice->rememberPlatformSelection($user, $office->id);
        if ($request->hasSession()) {
            // Rotaciona id de sessão sem destroy (mantém atributos no driver array).
            $request->session()->regenerate();
            // Regrava após regenerate (garantia em drivers de teste).
            $request->session()->put(PlatformPrivilegedContext::SESSION_KEY, $office->id);
        }

        $this->currentOffice->clear();
        $this->currentOffice->bindPlatformPrivileged($user, $office);

        PlatformPrivilegedAuditEvent::record(
            actorUserId: $user->id,
            officeId: $office->id,
            action: PlatformPrivilegedAuditEvent::ACTION_SELECT_OFFICE,
            result: PlatformPrivilegedAuditEvent::RESULT_SUCCESS,
            targetType: Office::class,
            targetId: $office->id,
            requestId: $this->requestId($request),
            metadata: [
                'access_mode' => OfficeAccessMode::PlatformPrivileged->value,
                'from_office_id' => $fromOfficeId,
                'to_office_id' => $office->id,
                'membership_created' => false,
                'selected_office_id_unchanged' => $user->selected_office_id,
            ],
        );

        return $office;
    }

    /**
     * Remove a seleção privilegiada (não mexe em memberships).
     */
    public function clear(User $user, Request $request): void
    {
        if (! $user->is_active || ! $user->isPlatformAdmin()) {
            abort(403, 'Ação restrita a administradores da plataforma.');
        }

        $previousOfficeId = $this->currentOffice->platformSelectedOfficeId($user);
        $this->currentOffice->forgetPlatformSelection($user);
        $this->currentOffice->clear();

        if ($previousOfficeId !== null) {
            PlatformPrivilegedAuditEvent::record(
                actorUserId: $user->id,
                officeId: $previousOfficeId,
                action: PlatformPrivilegedAuditEvent::ACTION_CLEAR_OFFICE,
                result: PlatformPrivilegedAuditEvent::RESULT_SUCCESS,
                targetType: Office::class,
                targetId: $previousOfficeId,
                requestId: $this->requestId($request),
                metadata: [
                    'access_mode' => OfficeAccessMode::PlatformPrivileged->value,
                    'cleared_office_id' => $previousOfficeId,
                ],
            );
        }
    }

    /**
     * Snapshot do contexto privilegiado atual (sem conteúdo fiscal).
     *
     * @return array{
     *     enabled: bool,
     *     selected: bool,
     *     access_mode: string|null,
     *     office: array{id: int, name: string|null, slug: string|null}|null,
     *     role: string|null,
     *     actor_user_id: int|null
     * }
     */
    public function current(User $user): array
    {
        $enabled = FeatureFlags::isPlatformPrivilegedContextEnabled();
        $this->currentOffice->clear();
        $office = $enabled && $user->isPlatformAdmin()
            ? $this->currentOffice->resolve($user)
            : null;

        $privileged = $office !== null && $this->currentOffice->isPlatformPrivileged();

        return [
            'enabled' => $enabled,
            'selected' => $privileged,
            'access_mode' => $privileged
                ? OfficeAccessMode::PlatformPrivileged->value
                : $this->currentOffice->accessMode()?->value,
            'office' => $privileged && $office !== null ? [
                'id' => $office->id,
                'name' => $office->name,
                'slug' => $office->slug,
            ] : null,
            'role' => $privileged ? OfficeRole::Admin->value : null,
            'actor_user_id' => $privileged ? $user->id : null,
        ];
    }

    private function auditDenied(User $user, int $targetOfficeId, string $reason): void
    {
        // Só grava se o office existir (FK); senão só reason em log estruturado via try/skip.
        $officeExists = Office::query()->whereKey($targetOfficeId)->exists();
        if (! $officeExists) {
            return;
        }

        PlatformPrivilegedAuditEvent::record(
            actorUserId: $user->id,
            officeId: $targetOfficeId,
            action: PlatformPrivilegedAuditEvent::ACTION_SELECT_OFFICE,
            result: PlatformPrivilegedAuditEvent::RESULT_DENIED,
            targetType: Office::class,
            targetId: $targetOfficeId,
            requestId: $this->requestId(request()),
            metadata: [
                'reason' => $reason,
                'access_mode' => OfficeAccessMode::PlatformPrivileged->value,
            ],
        );
    }

    private function requestId(?Request $request): string
    {
        $existing = $request?->attributes->get('correlation_id');
        if (is_string($existing) && $existing !== '') {
            return $existing;
        }

        $id = (string) Str::uuid();
        $request?->attributes->set('correlation_id', $id);

        return $id;
    }
}
