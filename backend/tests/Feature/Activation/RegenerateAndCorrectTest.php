<?php

namespace Tests\Feature\Activation;

use App\Enums\ActivationMethod;
use App\Enums\OfficeLifecycleStatus;
use App\Enums\SubscriptionPlan;
use App\Models\AccountActivation;
use App\Models\Office;
use App\Models\User;
use App\Services\Activation\ActivationCredentialService;
use App\Services\Activation\CreatePendingOfficeService;
use App\Services\Auth\RecentPasswordConfirmationGate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegenerateAndCorrectTest extends TestCase
{
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
     * @return array{office: Office, token: string, activation: AccountActivation, admin: User}
     */
    private function pendingOffice(): array
    {
        $admin = $this->platformAdmin();
        $this->actingAs($admin);
        app(RecentPasswordConfirmationGate::class)->markConfirmed($admin);

        $creds = app(ActivationCredentialService::class);
        $token = $creds->generateLinkToken();

        $result = app(CreatePendingOfficeService::class)->create([
            'name' => 'Delta Office',
            'profile' => [
                'cnpj' => self::VALID_CNPJ,
                'legal_name' => 'Delta LTDA',
                'institutional_email' => 'c@delta.example',
                'institutional_phone' => '11966665555',
            ],
            'plan' => SubscriptionPlan::Starter,
            'admin_name' => 'Pedro Delta',
            'admin_email' => 'pedro@delta.example',
            'method' => ActivationMethod::ManualLink,
            'idempotency_key' => 'regen-'.uniqid(),
        ], $admin);

        $office = Office::query()->findOrFail($result['office']['id']);
        $activation = AccountActivation::query()->where('office_id', $office->id)->firstOrFail();
        $activation->forceFill(['secret_hash' => $creds->hashToken($token)])->save();

        return compact('office', 'token', 'activation', 'admin');
    }

    public function test_regenerar_revoga_token_antigo(): void
    {
        $ctx = $this->pendingOffice();

        $response = $this->actingAs($ctx['admin'])
            ->postJson('/api/v1/platform/offices/'.$ctx['office']->id.'/activation/regenerate', [
                'method' => ActivationMethod::TemporaryPassword->value,
            ])
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private');

        $this->assertArrayHasKey('temporary_password', $response->json('data'));
        $this->assertNotNull($ctx['activation']->fresh()->revoked_at);

        $this->postJson('/api/v1/activations/complete', [
            'token' => $ctx['token'],
            'password' => 'SenhaPermanente12!',
            'password_confirmation' => 'SenhaPermanente12!',
        ])->assertStatus(422);

        $temp = $response->json('data.temporary_password');
        $this->postJson('/api/v1/first-access/complete', [
            'email' => 'pedro@delta.example',
            'temporary_password' => $temp,
            'password' => 'SenhaPermanente12!',
            'password_confirmation' => 'SenhaPermanente12!',
        ])->assertOk();
    }

    public function test_corrigir_email_enquanto_pendente(): void
    {
        $ctx = $this->pendingOffice();

        $response = $this->actingAs($ctx['admin'])
            ->patchJson('/api/v1/platform/offices/'.$ctx['office']->id.'/first-admin', [
                'name' => 'Pedro Correto',
                'email' => 'pedro.correto@delta.example',
                'method' => ActivationMethod::ManualLink->value,
            ])
            ->assertOk();

        $this->assertDatabaseMissing('users', ['email' => 'pedro@delta.example']);
        $this->assertDatabaseHas('users', ['email' => 'pedro.correto@delta.example']);
        // Ativação antiga foi revogada e removida junto com o usuário exclusivo
        $this->assertNull(AccountActivation::query()->find($ctx['activation']->id));

        $this->postJson('/api/v1/activations/complete', [
            'token' => $ctx['token'],
            'password' => 'SenhaPermanente12!',
            'password_confirmation' => 'SenhaPermanente12!',
        ])->assertStatus(422);

        $url = $response->json('data.activation_url');
        $this->assertNotEmpty($url);
        $newToken = substr((string) $url, strlen('/activate#token='));

        $this->postJson('/api/v1/activations/complete', [
            'token' => $newToken,
            'password' => 'SenhaPermanente12!',
            'password_confirmation' => 'SenhaPermanente12!',
        ])->assertOk();
    }

    public function test_corrigir_apos_ativacao_negado(): void
    {
        $ctx = $this->pendingOffice();

        $this->postJson('/api/v1/activations/complete', [
            'token' => $ctx['token'],
            'password' => 'SenhaPermanente12!',
            'password_confirmation' => 'SenhaPermanente12!',
        ])->assertOk();

        $this->assertSame(OfficeLifecycleStatus::Active, $ctx['office']->fresh()->lifecycle_status);

        $this->actingAs($ctx['admin']);
        app(RecentPasswordConfirmationGate::class)->markConfirmed($ctx['admin']);

        $this->actingAs($ctx['admin'])
            ->patchJson('/api/v1/platform/offices/'.$ctx['office']->id.'/first-admin', [
                'name' => 'X',
                'email' => 'x@delta.example',
                'method' => ActivationMethod::ManualLink->value,
            ])
            ->assertStatus(403);
    }
}
