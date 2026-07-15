<?php

namespace Tests\Feature\Platform;

use App\Enums\OfficeRole;
use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionStatus;
use App\Models\AuditLog;
use App\Models\Office;
use App\Models\OfficeSubscription;
use App\Models\User;
use App\Services\Platform\OfficeSubscriptionGate;
use App\Services\Platform\OfficeSubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class OfficeSubscriptionLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_seed_cria_assinatura_active_para_office(): void
    {
        $office = Office::factory()->create();

        $this->assertDatabaseHas('office_subscriptions', [
            'office_id' => $office->id,
            'status' => SubscriptionStatus::Active->value,
        ]);
    }

    public function test_ciclo_trial_active_past_due_suspended_canceled(): void
    {
        $office = Office::factory()->withoutSubscription()->create();
        $service = app(OfficeSubscriptionService::class);

        $sub = $service->create($office, SubscriptionPlan::Starter, SubscriptionStatus::Trial);
        $this->assertSame(SubscriptionStatus::Trial, $sub->status);
        $this->assertTrue($sub->allowsMutations());

        $sub = $service->activate($sub);
        $this->assertSame(SubscriptionStatus::Active, $sub->status);

        $sub = $service->markPastDue($sub);
        $this->assertSame(SubscriptionStatus::PastDue, $sub->status);
        $this->assertTrue($sub->allowsMutations());

        $sub = $service->suspend($sub, 'inadimplência');
        $this->assertSame(SubscriptionStatus::Suspended, $sub->status);
        $this->assertFalse($sub->allowsMutations());
        $this->assertFalse($sub->allowsExternalCalls());
        $this->assertTrue($sub->allowsRead());

        $sub = $service->cancel($sub, 'encerramento');
        $this->assertSame(SubscriptionStatus::Canceled, $sub->status);
        $this->assertNotNull($sub->ends_at);
        $this->assertFalse($sub->allowsMutations());
    }

    public function test_resume_de_suspended_para_active(): void
    {
        $office = Office::factory()->create();
        $sub = $office->subscription;
        $service = app(OfficeSubscriptionService::class);

        $service->suspend($sub);
        $sub = $service->resume($sub->fresh());

        $this->assertSame(SubscriptionStatus::Active, $sub->status);
    }

    public function test_transicao_invalida_de_canceled_lanca(): void
    {
        $office = Office::factory()->create();
        $service = app(OfficeSubscriptionService::class);
        $sub = $service->cancel($office->subscription);

        $this->expectException(InvalidArgumentException::class);
        $service->activate($sub);
    }

    public function test_change_plan_atualiza_limites(): void
    {
        $office = Office::factory()->create();
        $service = app(OfficeSubscriptionService::class);

        $sub = $service->changePlan($office->subscription, SubscriptionPlan::Enterprise);

        $this->assertSame(SubscriptionPlan::Enterprise, $sub->plan);
        $this->assertSame(
            SubscriptionPlan::Enterprise->defaultLimits()['monthly_api_quota'],
            $sub->monthly_api_quota,
        );
    }

    public function test_gate_bloqueia_mutacao_quando_suspended(): void
    {
        $office = Office::factory()->create();
        $user = User::factory()->forOffice($office, OfficeRole::Admin)->withTwoFactorConfirmed()->create();
        $this->actingAs($user);

        app(OfficeSubscriptionService::class)->suspend($office->subscription);

        $gate = app(OfficeSubscriptionGate::class);
        $this->assertFalse($gate->allowsMutations($office));
        $this->assertFalse($gate->allowsExternalCalls($office));
        $this->assertTrue($gate->allowsRead($office));
    }

    public function test_endpoint_tenant_mostra_assinatura_e_limites(): void
    {
        $office = Office::factory()->create();
        $user = User::factory()->forOffice($office, OfficeRole::Viewer)->create();

        $this->actingAs($user)
            ->getJson('/api/v1/office/subscription')
            ->assertOk()
            ->assertJsonPath('data.office_id', $office->id)
            ->assertJsonPath('data.status', SubscriptionStatus::Active->value)
            ->assertJsonStructure([
                'data' => [
                    'plan',
                    'status',
                    'limits' => ['monthly_api_quota', 'max_clients', 'max_users'],
                    'allows_mutations',
                    'allows_external_calls',
                ],
            ]);
    }

    public function test_mutacao_http_bloqueada_quando_suspended_leitura_ok(): void
    {
        $office = Office::factory()->create();
        $user = User::factory()->forOffice($office, OfficeRole::Admin)->withTwoFactorConfirmed()->create();

        app(OfficeSubscriptionService::class)->suspend($office->subscription);

        $this->actingAs($user);

        $this->getJson('/api/v1/office/subscription')
            ->assertOk()
            ->assertJsonPath('data.status', 'SUSPENDED')
            ->assertJsonPath('data.allows_mutations', false);

        $this->getJson('/api/v1/clients')->assertOk();

        $this->postJson('/api/v1/clients', [
            'legal_name' => 'Cliente Teste',
            'root_cnpj' => '11222333',
        ])->assertForbidden()
            ->assertJsonPath('subscription_status', 'SUSPENDED');
    }

    public function test_auditoria_registra_mudanca_de_status(): void
    {
        $office = Office::factory()->create();
        $user = User::factory()->forOffice($office, OfficeRole::Admin)->withTwoFactorConfirmed()->create();
        $this->actingAs($user);

        app(OfficeSubscriptionService::class)->suspend($office->subscription, 'teste');

        $this->assertTrue(
            AuditLog::query()
                ->where('action', 'office_subscription.status_changed')
                ->where('office_id', $office->id)
                ->exists()
        );
    }

    public function test_ensure_for_office_e_idempotente(): void
    {
        $office = Office::factory()->withoutSubscription()->create();
        $service = app(OfficeSubscriptionService::class);

        $a = $service->ensureForOffice($office);
        $b = $service->ensureForOffice($office);

        $this->assertSame($a->id, $b->id);
        $this->assertSame(1, OfficeSubscription::query()->where('office_id', $office->id)->count());
    }
}
