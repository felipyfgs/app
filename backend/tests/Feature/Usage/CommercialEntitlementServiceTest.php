<?php

namespace Tests\Feature\Usage;

use App\Enums\SubscriptionPlan;
use App\Models\Client;
use App\Models\Office;
use App\Models\OfficeSubscription;
use App\Services\Platform\OfficeSubscriptionService;
use App\Services\Usage\CommercialEntitlementService;
use App\Services\Usage\SubscriptionPeriodService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

/**
 * Task 5.1 — período da assinatura, entitlements 5/7/10, max clients, negociado platform-only.
 */
class CommercialEntitlementServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_entitlements_por_plano_5_7_10_e_max_clientes_100_150_200(): void
    {
        $this->assertSame(5, SubscriptionPlan::Starter->commercialMonitorUnits());
        $this->assertSame(100, SubscriptionPlan::Starter->commercialMaxClients());
        $this->assertSame(7, SubscriptionPlan::Professional->commercialMonitorUnits());
        $this->assertSame(150, SubscriptionPlan::Professional->commercialMaxClients());
        $this->assertSame(10, SubscriptionPlan::Enterprise->commercialMonitorUnits());
        $this->assertSame(200, SubscriptionPlan::Enterprise->commercialMaxClients());

        $svc = app(CommercialEntitlementService::class);

        $cases = [
            [SubscriptionPlan::Starter, 5, 100],
            [SubscriptionPlan::Professional, 7, 150],
            [SubscriptionPlan::Enterprise, 10, 200],
        ];
        foreach ($cases as [$plan, $units, $max]) {
            $office = Office::factory()->withoutSubscription()->create();
            $sub = app(OfficeSubscriptionService::class)->create($office, $plan);
            $snap = $svc->snapshot($sub);

            $this->assertSame($units, $snap['commercial_monitor_units']);
            $this->assertSame($max, $snap['effective_max_clients']);
            $this->assertNull($snap['negotiated_client_limit']);
            // monthly_api_quota técnico preservado (não é franquia comercial).
            $this->assertSame(
                $plan->defaultLimits()['monthly_api_quota'],
                $snap['monthly_api_quota'],
            );
        }
    }

    public function test_periodo_alinha_aniversario_assinatura_nao_mes_calendario(): void
    {
        $office = Office::factory()->withoutSubscription()->create();
        $start = CarbonImmutable::parse('2026-01-15 10:00:00');
        CarbonImmutable::setTestNow($start);
        try {
            $sub = app(OfficeSubscriptionService::class)->create(
                $office,
                SubscriptionPlan::Professional,
            );

            $periods = app(SubscriptionPeriodService::class);
            $resolved = $periods->resolve($sub, $start);

            $this->assertSame('2026-01-15', $resolved['period_key']);
            $this->assertSame('2026-01-15', $resolved['starts_at']->toDateString());
            // Fim = +1 mês noOverflow − 1s (não endOfMonth calendário).
            $this->assertSame('2026-02-15', $resolved['ends_at']->toDateString());
            $this->assertNotSame(
                $start->endOfMonth()->toDateString(),
                $resolved['ends_at']->toDateString(),
            );

            // Renovação fora do mês-calendário (dia 15 → 15).
            $after = CarbonImmutable::parse('2026-02-15 10:00:01');
            $next = $periods->resolve($sub->fresh(), $after);
            $this->assertSame('2026-02-15', $next['period_key']);
            $this->assertNotSame($resolved['period_key'], $next['period_key']);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_sem_rollover_ao_renovar_periodo(): void
    {
        $office = Office::factory()->withoutSubscription()->create();
        $start = CarbonImmutable::parse('2026-03-10 08:00:00');
        $sub = OfficeSubscription::factory()->forOffice($office)->create([
            'plan' => SubscriptionPlan::Starter,
            'commercial_monitor_units' => 5,
            'current_period_starts_at' => $start,
            'current_period_ends_at' => $start->addMonthNoOverflow()->subSecond(),
        ]);

        $periods = app(SubscriptionPeriodService::class);
        $nextAt = $start->addMonthNoOverflow()->addDay();
        $renewed = $periods->ensureCurrent($sub, $nextAt);

        $this->assertTrue(
            CarbonImmutable::parse($renewed->current_period_starts_at)->greaterThan($start),
        );
        $this->assertNotSame($start->toDateString(), CarbonImmutable::parse($renewed->current_period_starts_at)->toDateString());
        // Unidades comerciais permanecem as do plano — sem crédito residual.
        $this->assertSame(5, $renewed->resolvedCommercialMonitorUnits());
        $this->assertNull($renewed->negotiated_client_limit);
    }

    public function test_limite_negociado_somente_acima_de_200_preserva_plano_e_unidades(): void
    {
        $office = Office::factory()->withoutSubscription()->create();
        $sub = app(OfficeSubscriptionService::class)->create(
            $office,
            SubscriptionPlan::Enterprise,
        );
        $svc = app(CommercialEntitlementService::class);

        try {
            $svc->setNegotiatedClientLimit($sub, 200);
            $this->fail('Esperava rejeitar limite <= 200');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('200', $e->getMessage());
        }

        $updated = $svc->setNegotiatedClientLimit($sub->fresh(), 260);
        $this->assertSame(260, $updated->negotiated_client_limit);
        $this->assertSame(SubscriptionPlan::Enterprise, $updated->plan);
        $this->assertSame(10, $svc->monitorUnits($updated));
        $this->assertSame(260, $svc->effectiveMaxClients($updated));
        $this->assertSame(
            SubscriptionPlan::Enterprise->defaultLimits()['monthly_api_quota'],
            $updated->monthly_api_quota,
        );
    }

    public function test_bloqueia_cadastro_de_cliente_acima_do_maximo(): void
    {
        $office = Office::factory()->withoutSubscription()->create();
        $sub = OfficeSubscription::factory()->forOffice($office)->create([
            'plan' => SubscriptionPlan::Starter,
            'max_clients' => 100,
            'commercial_monitor_units' => 5,
            'negotiated_client_limit' => null,
        ]);
        // Força teto efetivo artificialmente baixo para o teste (override negociado não se aplica ≤200).
        // Usamos count via mock de clientes: setamos max via reflection do plan defaults
        // criando exatamente N clientes e setando negotiated? Melhor: max_clients column
        // e effectiveCommercialMaxClients usa negotiated OU plan commercialMax — não max_clients.
        // Então usamos negotiated_client_limit com valor alto e contamos... não.
        // effectiveCommercialMaxClients: negotiated se set, senão plan->commercialMaxClients().
        // Para testar bloqueio com poucos clientes, setamos negotiated para um valor >200
        // e criamos 201... pesado. Alternativa: criar service evaluate com stub.
        // Mais simples: setar plan Starter (100) e criar 100 clientes-raiz.

        $svc = app(CommercialEntitlementService::class);
        $this->assertTrue($svc->evaluateClientCreate($office)['allowed']);

        Client::factory()->forOffice($office)->count(100)->create();

        $eval = $svc->evaluateClientCreate($office);
        $this->assertFalse($eval['allowed']);
        $this->assertSame('MAX_CLIENTS_REACHED', $eval['reason']);
        $this->assertSame(100, $eval['max']);
        $this->assertSame(100, $eval['current']);

        $this->expectException(RuntimeException::class);
        $svc->assertCanCreateClient($office);
    }

    public function test_negociado_eleva_teto_sem_ampliar_franquia_de_consultas(): void
    {
        $office = Office::factory()->withoutSubscription()->create();
        $sub = app(OfficeSubscriptionService::class)->create(
            $office,
            SubscriptionPlan::Enterprise,
        );
        $svc = app(CommercialEntitlementService::class);
        $svc->setNegotiatedClientLimit($sub, 260);

        Client::factory()->forOffice($office)->count(200)->create();
        $eval = $svc->evaluateClientCreate($office->fresh());
        $this->assertTrue($eval['allowed']);
        $this->assertSame(260, $eval['max']);
        $this->assertSame(10, $svc->monitorUnits($sub->fresh()));
    }

    public function test_change_plan_preserva_monthly_api_quota_tecnico_do_novo_plano(): void
    {
        $office = Office::factory()->withoutSubscription()->create();
        $svc = app(OfficeSubscriptionService::class);
        $sub = $svc->create($office, SubscriptionPlan::Starter);
        $this->assertSame(1_000, $sub->monthly_api_quota);

        $sub = $svc->changePlan($sub, SubscriptionPlan::Enterprise);
        $this->assertSame(100_000, $sub->monthly_api_quota);
        $this->assertSame(10, $sub->commercial_monitor_units);
        $this->assertSame(200, $sub->max_clients);
    }

    public function test_sem_assinatura_bloqueia_create(): void
    {
        $office = Office::factory()->withoutSubscription()->create();
        $svc = app(CommercialEntitlementService::class);
        $eval = $svc->evaluateClientCreate($office);
        $this->assertFalse($eval['allowed']);
        $this->assertSame('SUBSCRIPTION_MISSING', $eval['reason']);
    }

    public function test_clear_negotiated_volta_ao_max_do_plano(): void
    {
        $office = Office::factory()->withoutSubscription()->create();
        $sub = app(OfficeSubscriptionService::class)->create(
            $office,
            SubscriptionPlan::Enterprise,
        );
        $svc = app(CommercialEntitlementService::class);
        $svc->setNegotiatedClientLimit($sub, 300);
        $cleared = $svc->clearNegotiatedClientLimit($sub->fresh());
        $this->assertNull($cleared->negotiated_client_limit);
        $this->assertSame(200, $svc->effectiveMaxClients($cleared));
    }

    public function test_status_canceled_rejeita_negociado(): void
    {
        $office = Office::factory()->withoutSubscription()->create();
        $sub = app(OfficeSubscriptionService::class)->create(
            $office,
            SubscriptionPlan::Enterprise,
        );
        app(OfficeSubscriptionService::class)->cancel($sub);

        $this->expectException(InvalidArgumentException::class);
        app(CommercialEntitlementService::class)->setNegotiatedClientLimit($sub->fresh(), 250);
    }
}
