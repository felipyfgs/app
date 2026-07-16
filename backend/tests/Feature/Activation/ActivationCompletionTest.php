<?php

namespace Tests\Feature\Activation;

use App\Enums\ActivationMethod;
use App\Enums\ActivationPurpose;
use App\Enums\OfficeLifecycleStatus;
use App\Enums\OfficeRole;
use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionStatus;
use App\Models\AccountActivation;
use App\Models\Office;
use App\Models\OfficeMembership;
use App\Models\User;
use App\Services\Activation\ActivationCompletionService;
use App\Services\Activation\ActivationCredentialService;
use App\Services\Activation\CreatePendingOfficeService;
use App\Services\Auth\RecentPasswordConfirmationGate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\InteractsWithSpaAuth;
use Tests\TestCase;

class ActivationCompletionTest extends TestCase
{
    use InteractsWithSpaAuth;
    use RefreshDatabase;

    private const VALID_CNPJ = '11222333000181';

    private function platformAdmin(): User
    {
        Office::factory()->create();

        return User::factory()->asPlatformAdmin()->create([
            'password' => bcrypt('admin-secret-12'),
        ]);
    }

    /**
     * @return array{office: Office, token: string, user: User, activation: AccountActivation}
     */
    private function createPendingOfficeWithLink(): array
    {
        $admin = $this->platformAdmin();
        $this->actingAs($admin);
        app(RecentPasswordConfirmationGate::class)->markConfirmed($admin);

        $creds = app(ActivationCredentialService::class);
        $token = $creds->generateLinkToken();
        $hash = $creds->hashToken($token);

        $service = app(CreatePendingOfficeService::class);
        // Cria via serviço e depois substitui o hash pelo token conhecido
        $result = $service->create([
            'name' => 'Beta Office',
            'profile' => [
                'cnpj' => self::VALID_CNPJ,
                'legal_name' => 'Beta LTDA',
                'institutional_email' => 'c@beta.example',
                'institutional_phone' => '11988887777',
            ],
            'plan' => SubscriptionPlan::Professional,
            'admin_name' => 'João Beta',
            'admin_email' => 'joao@beta.example',
            'method' => ActivationMethod::ManualLink,
            'idempotency_key' => 'complete-link-'.uniqid(),
        ], $admin);

        $office = Office::query()->findOrFail($result['office']['id']);
        $activation = AccountActivation::query()->where('office_id', $office->id)->firstOrFail();
        $activation->forceFill(['secret_hash' => $hash])->save();

        return [
            'office' => $office,
            'token' => $token,
            'user' => $activation->user,
            'activation' => $activation->fresh(),
        ];
    }

    public function test_inspect_link_valido_e_invalido(): void
    {
        $ctx = $this->createPendingOfficeWithLink();

        $this->postJson('/api/v1/activations/inspect', ['token' => $ctx['token']])
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('data.valid', true)
            ->assertJsonPath('data.invite_name', 'João Beta');

        $this->assertStringContainsString('***', (string) $this->postJson('/api/v1/activations/inspect', [
            'token' => $ctx['token'],
        ])->json('data.email_masked'));

        $this->postJson('/api/v1/activations/inspect', ['token' => str_repeat('a', 64)])
            ->assertOk()
            ->assertJsonPath('data.valid', false);
    }

    public function test_complete_link_ativa_office_e_periodo(): void
    {
        $ctx = $this->createPendingOfficeWithLink();

        $this->asSpa()
            ->postJson('/api/v1/activations/complete', [
                'token' => $ctx['token'],
                'password' => 'SenhaPermanente12!',
                'password_confirmation' => 'SenhaPermanente12!',
            ])
            ->assertOk()
            ->assertJsonPath('data.authenticated', true)
            ->assertJsonPath('data.purpose', ActivationPurpose::OfficeFirstAdmin->value);

        $office = $ctx['office']->fresh(['subscription']);
        $this->assertTrue($office->is_active);
        $this->assertSame(OfficeLifecycleStatus::Active, $office->lifecycle_status);
        $this->assertSame(SubscriptionStatus::Active, $office->subscription->status);
        $this->assertNotNull($office->subscription->starts_at);
        $this->assertNotNull($office->subscription->current_period_starts_at);
        $this->assertNotNull($office->subscription->current_period_ends_at);

        $user = $ctx['user']->fresh();
        $this->assertTrue($user->is_active);
        $this->assertFalse($user->password_change_required);
        $this->assertTrue(Hash::check('SenhaPermanente12!', $user->password));

        $this->assertNotNull($ctx['activation']->fresh()->consumed_at);
        $this->assertAuthenticatedAs($user);
    }

    public function test_link_expirado_rejeita(): void
    {
        $ctx = $this->createPendingOfficeWithLink();
        $ctx['activation']->forceFill(['expires_at' => now()->subMinute()])->save();

        $this->postJson('/api/v1/activations/complete', [
            'token' => $ctx['token'],
            'password' => 'SenhaPermanente12!',
            'password_confirmation' => 'SenhaPermanente12!',
        ])->assertStatus(422);

        $this->assertFalse($ctx['office']->fresh()->is_active);
        $this->assertNull($ctx['activation']->fresh()->consumed_at);
    }

    public function test_link_reutilizado_rejeita(): void
    {
        $ctx = $this->createPendingOfficeWithLink();

        $this->postJson('/api/v1/activations/complete', [
            'token' => $ctx['token'],
            'password' => 'SenhaPermanente12!',
            'password_confirmation' => 'SenhaPermanente12!',
        ])->assertOk();

        $startsAt = $ctx['office']->fresh()->subscription->starts_at?->toIso8601String();

        $this->postJson('/api/v1/activations/complete', [
            'token' => $ctx['token'],
            'password' => 'OutraSenhaPerman12!',
            'password_confirmation' => 'OutraSenhaPerman12!',
        ])->assertStatus(422);

        $this->assertSame(
            $startsAt,
            $ctx['office']->fresh()->subscription->starts_at?->toIso8601String(),
        );
    }

    public function test_first_access_com_senha_provisoria(): void
    {
        $admin = $this->platformAdmin();
        $this->actingAs($admin);
        app(RecentPasswordConfirmationGate::class)->markConfirmed($admin);

        $creds = app(ActivationCredentialService::class);
        $temp = $creds->generateTemporaryPassword();

        $result = app(CreatePendingOfficeService::class)->create([
            'name' => 'Gama Office',
            'profile' => [
                'cnpj' => self::VALID_CNPJ,
                'legal_name' => 'Gama LTDA',
                'institutional_email' => 'c@gama.example',
                'institutional_phone' => '11977776666',
            ],
            'plan' => SubscriptionPlan::Starter,
            'admin_name' => 'Ana Gama',
            'admin_email' => 'ana@gama.example',
            'method' => ActivationMethod::TemporaryPassword,
            'idempotency_key' => 'first-access-'.uniqid(),
        ], $admin);

        $activation = AccountActivation::query()
            ->where('office_id', $result['office']['id'])
            ->firstOrFail();
        $activation->forceFill([
            'secret_hash' => $creds->hashPassword($temp),
            'method' => ActivationMethod::TemporaryPassword,
        ])->save();

        // Login comum com provisória deve falhar (usuário inativo / sentinela)
        auth()->logout();
        $this->asSpa()->postJson('/login', [
            'email' => 'ana@gama.example',
            'password' => $temp,
        ]);
        $this->assertGuest();

        $this->asSpa()
            ->postJson('/api/v1/first-access/complete', [
                'email' => 'ana@gama.example',
                'temporary_password' => $temp,
                'password' => 'SenhaPermanente12!',
                'password_confirmation' => 'SenhaPermanente12!',
            ])
            ->assertOk()
            ->assertJsonPath('data.authenticated', true);

        $user = User::query()->where('email', 'ana@gama.example')->firstOrFail();
        $this->assertTrue($user->is_active);
        $this->assertFalse($user->password_change_required);
        $this->assertAuthenticatedAs($user);
    }

    public function test_membro_nao_reinicia_periodo(): void
    {
        $office = Office::factory()->create();
        $periodStart = $office->subscription->current_period_starts_at->copy();
        $periodEnd = $office->subscription->current_period_ends_at->copy();

        $creds = app(ActivationCredentialService::class);
        $token = $creds->generateLinkToken();

        $user = User::factory()->create([
            'email' => 'op@office.example',
            'is_active' => false,
            'password_change_required' => true,
            'password' => $creds->makeSentinelPasswordHash(),
        ]);

        $membership = OfficeMembership::query()->create([
            'office_id' => $office->id,
            'user_id' => $user->id,
            'role' => OfficeRole::Operator,
            'is_active' => false,
        ]);

        AccountActivation::query()->create([
            'purpose' => ActivationPurpose::OfficeMember,
            'method' => ActivationMethod::ManualLink,
            'user_id' => $user->id,
            'office_id' => $office->id,
            'office_membership_id' => $membership->id,
            'email_normalized' => 'op@office.example',
            'secret_hash' => $creds->hashToken($token),
            'expires_at' => now()->addDays(7),
            'generation' => 1,
        ]);

        app(ActivationCompletionService::class)->completeLink($token, 'SenhaPermanente12!');

        $sub = $office->fresh()->subscription;
        $this->assertTrue($periodStart->equalTo($sub->current_period_starts_at));
        $this->assertTrue($periodEnd->equalTo($sub->current_period_ends_at));
        $this->assertTrue($membership->fresh()->is_active);
    }

    public function test_concorrencia_apenas_uma_ativacao_primeiro_admin(): void
    {
        $ctx = $this->createPendingOfficeWithLink();
        $service = app(ActivationCompletionService::class);

        $ok = 0;
        $fail = 0;

        // Simula duas tentativas sequenciais sob a mesma condição de corrida (sqlite).
        try {
            $service->completeLink($ctx['token'], 'SenhaPermanente12!');
            $ok++;
        } catch (\Throwable) {
            $fail++;
        }

        try {
            $service->completeLink($ctx['token'], 'SenhaPermanente12!');
            $ok++;
        } catch (\Throwable) {
            $fail++;
        }

        $this->assertSame(1, $ok);
        $this->assertSame(1, $fail);
        $this->assertSame(1, AccountActivation::query()->whereNotNull('consumed_at')->count());
        $this->assertSame(SubscriptionStatus::Active, $ctx['office']->fresh()->subscription->status);
    }
}
