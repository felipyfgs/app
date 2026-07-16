<?php

namespace Tests\Feature\Activation;

use App\Enums\ActivationMethod;
use App\Enums\OfficeLifecycleStatus;
use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionStatus;
use App\Models\AccountActivation;
use App\Models\Office;
use App\Models\OfficeMembership;
use App\Models\PlatformMembership;
use App\Models\User;
use App\Services\Auth\RecentPasswordConfirmationGate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CreatePendingOfficeTest extends TestCase
{
    use RefreshDatabase;

    private const VALID_CNPJ = '11222333000181';

    private function platformAdmin(): User
    {
        // Precisa de um office ativo para default_office_id do factory
        Office::factory()->create();

        return User::factory()->asPlatformAdmin()->create([
            'password' => bcrypt('admin-secret-12'),
        ]);
    }

    private function confirmPassword(User $user): void
    {
        $this->actingAs($user);
        app(RecentPasswordConfirmationGate::class)->markConfirmed($user);
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(string $key = 'idem-key-001'): array
    {
        return [
            'name' => 'Escritório Alfa',
            'profile' => [
                'cnpj' => self::VALID_CNPJ,
                'legal_name' => 'Alfa Contabilidade LTDA',
                'institutional_email' => 'contato@alfa.example',
                'institutional_phone' => '11999999999',
            ],
            'plan' => SubscriptionPlan::Starter->value,
            'admin_name' => 'Maria Admin',
            'admin_email' => 'maria@alfa.example',
            'method' => ActivationMethod::ManualLink->value,
            'idempotency_key' => $key,
        ];
    }

    public function test_cria_office_agregado_pendente_atomico(): void
    {
        Queue::fake();
        $admin = $this->platformAdmin();
        $this->confirmPassword($admin);

        $response = $this->actingAs($admin)
            ->postJson('/api/v1/platform/offices', $this->validPayload())
            ->assertCreated()
            ->assertHeader('Cache-Control', 'no-store, private');

        $response->assertJsonPath('data.credential_delivery', 'delivered');
        $response->assertJsonPath('data.office.lifecycle_status', OfficeLifecycleStatus::PendingActivation->value);
        $response->assertJsonPath('data.office.is_active', false);
        $response->assertJsonPath('data.office.subscription.status', SubscriptionStatus::PendingActivation->value);
        $this->assertNotEmpty($response->json('data.activation_url'));
        $this->assertStringStartsWith('/activate#token=', $response->json('data.activation_url'));
        $this->assertNull($response->json('data.office.subscription.starts_at'));
        $this->assertNull($response->json('data.office.subscription.current_period_starts_at'));

        $office = Office::query()->where('slug', 'like', 'escritorio-alfa%')->first();
        $this->assertNotNull($office);
        $this->assertFalse($office->is_active);
        $this->assertSame(OfficeLifecycleStatus::PendingActivation, $office->lifecycle_status);

        $this->assertDatabaseHas('account_activations', [
            'office_id' => $office->id,
            'email_normalized' => 'maria@alfa.example',
        ]);

        // Nenhum job fiscal enfileirado
        Queue::assertNothingPushed();
    }

    public function test_primeiro_office_converge_padrao_do_platform_admin_sem_membership_tenant(): void
    {
        Queue::fake();
        $admin = User::factory()->asPlatformAdmin()->create([
            'password' => bcrypt('admin-secret-12'),
            'selected_office_id' => null,
        ]);
        $platformMembership = PlatformMembership::query()
            ->where('user_id', $admin->id)
            ->firstOrFail();
        $this->assertNull($platformMembership->default_office_id);

        $this->confirmPassword($admin);

        $response = $this->actingAs($admin)
            ->postJson('/api/v1/platform/offices', $this->validPayload('first-office-default'))
            ->assertCreated();

        $officeId = (int) $response->json('data.office.id');
        $this->assertGreaterThan(0, $officeId);
        $this->assertSame($officeId, (int) $platformMembership->fresh()->default_office_id);
        $this->assertNull($admin->fresh()->selected_office_id);
        $this->assertFalse(OfficeMembership::query()->where('user_id', $admin->id)->exists());
    }

    public function test_plano_obrigatorio(): void
    {
        $admin = $this->platformAdmin();
        $this->confirmPassword($admin);

        $payload = $this->validPayload();
        unset($payload['plan']);

        $this->actingAs($admin)
            ->postJson('/api/v1/platform/offices', $payload)
            ->assertStatus(422);

        $this->assertSame(1, Office::query()->count()); // só o do factory/bootstrap do admin
    }

    public function test_idempotency_replay_exige_regeneracao_sem_segredo(): void
    {
        $admin = $this->platformAdmin();
        $this->confirmPassword($admin);

        $payload = $this->validPayload('same-key-xyz');

        $first = $this->actingAs($admin)
            ->postJson('/api/v1/platform/offices', $payload)
            ->assertCreated();

        $this->assertNotEmpty($first->json('data.activation_url'));
        $officeId = $first->json('data.office.id');

        $second = $this->actingAs($admin)
            ->postJson('/api/v1/platform/offices', $payload)
            ->assertOk();

        $second->assertJsonPath('data.credential_delivery', 'regeneration_required');
        $second->assertJsonPath('data.office.id', $officeId);
        $this->assertArrayNotHasKey('activation_url', $second->json('data'));
        $this->assertArrayNotHasKey('temporary_password', $second->json('data'));

        $this->assertSame(1, Office::query()->where('lifecycle_status', OfficeLifecycleStatus::PendingActivation->value)->count());
    }

    public function test_idempotency_payload_divergente_409(): void
    {
        $admin = $this->platformAdmin();
        $this->confirmPassword($admin);

        $payload = $this->validPayload('conflict-key');
        $this->actingAs($admin)->postJson('/api/v1/platform/offices', $payload)->assertCreated();

        $payload['admin_email'] = 'outro@alfa.example';
        $this->actingAs($admin)
            ->postJson('/api/v1/platform/offices', $payload)
            ->assertStatus(409)
            ->assertJsonPath('code', 'idempotency_payload_mismatch');
    }

    public function test_segredo_nao_fica_em_claro_no_banco(): void
    {
        $admin = $this->platformAdmin();
        $this->confirmPassword($admin);

        $response = $this->actingAs($admin)
            ->postJson('/api/v1/platform/offices', $this->validPayload('secret-scan'))
            ->assertCreated();

        $url = $response->json('data.activation_url');
        $token = substr((string) $url, strlen('/activate#token='));

        $this->assertNotEmpty($token);
        $this->assertDatabaseMissing('account_activations', ['secret_hash' => $token]);

        $rows = DB::table('account_activations')->get();
        foreach ($rows as $row) {
            $this->assertStringNotContainsString($token, (string) $row->secret_hash);
            $this->assertStringNotContainsString($token, json_encode((array) $row) ?: '');
        }

        // Hash existe e é diferente do plaintext
        $activation = AccountActivation::query()->first();
        $this->assertNotNull($activation);
        $this->assertNotSame($token, $activation->secret_hash);
        $this->assertGreaterThan(40, strlen($activation->secret_hash));
    }

    public function test_exige_senha_recente(): void
    {
        $admin = $this->platformAdmin();

        $this->actingAs($admin)
            ->postJson('/api/v1/platform/offices', $this->validPayload())
            ->assertStatus(403)
            ->assertJsonPath('code', 'password_confirmation_required');
    }
}
