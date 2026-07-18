<?php

namespace Tests\Feature\Usage;

use App\Enums\MonitorCommercialDispatchState;
use App\Enums\MonitorCommercialOrigin;
use App\Enums\SubscriptionPlan;
use App\Models\Client;
use App\Models\FiscalMonitoringRun;
use App\Models\FiscalMonitoringSchedule;
use App\Models\MonitorCommercialLedgerEntry;
use App\Models\Office;
use App\Models\OfficeMonitorSchedulePolicy;
use App\Models\OfficeSubscription;
use App\Services\FiscalMonitoring\FiscalMonitoringScheduler;
use App\Services\FiscalMonitoring\MonitorScheduleDayHasher;
use App\Services\Usage\CommercialMonitorCatalog;
use App\Services\Usage\MonitorCommercialLedgerService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Task 5.3 — política dia 1–28, um item/cliente+monitor+período, spillover, fora da franquia.
 */
class CommercialMonthlySchedulerTest extends TestCase
{
    use RefreshDatabase;

    private Office $office;

    private Client $client;

    private OfficeSubscription $subscription;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'features.global_enabled' => true,
            'features.kill_switch' => false,
            'fiscal_monitoring.enabled' => true,
            'fiscal_monitoring.kill_switch' => false,
            'fiscal_monitoring.scheduler.enabled' => true,
            'fiscal_monitoring.scheduler.commercial_monthly_enabled' => true,
            'fiscal_monitoring.commercial.enabled' => true,
            'fiscal_monitoring.scheduler.max_dispatch_per_tick' => 50,
            'serpro.capabilities.sitfis' => 'real',
        ]);

        $this->office = Office::factory()->create([
            'timezone' => 'America/Sao_Paulo',
            'deadline_timezone' => 'America/Sao_Paulo',
            'is_active' => true,
        ]);
        $this->client = Client::factory()->forOffice($this->office)->create(['is_active' => true]);
        $this->subscription = $this->office->subscription;
        $this->subscription->forceFill([
            'plan' => SubscriptionPlan::Starter,
            'commercial_monitor_units' => 5,
            'current_period_starts_at' => CarbonImmutable::parse('2026-06-01 00:00:00'),
            'current_period_ends_at' => CarbonImmutable::parse('2026-06-30 23:59:59'),
        ])->save();
    }

    public function test_default_day_hash_estavel_entre_1_e_28(): void
    {
        $d1 = MonitorScheduleDayHasher::defaultDay((int) $this->office->id, 'sitfis');
        $d2 = MonitorScheduleDayHasher::defaultDay((int) $this->office->id, 'sitfis');
        $this->assertSame($d1, $d2);
        $this->assertGreaterThanOrEqual(1, $d1);
        $this->assertLessThanOrEqual(28, $d1);

        $other = MonitorScheduleDayHasher::defaultDay((int) $this->office->id, 'dctfweb');
        // Pode coincidir por colisão, mas ensureDefault materializa.
        $policy = OfficeMonitorSchedulePolicy::ensureDefault((int) $this->office->id, 'sitfis');
        $this->assertSame($d1, $policy->day_of_month);
        $this->assertFalse($policy->is_custom);

        $again = OfficeMonitorSchedulePolicy::ensureDefault((int) $this->office->id, 'sitfis');
        $this->assertSame($policy->id, $again->id);
    }

    public function test_dia_invalido_rejeitado(): void
    {
        $this->expectException(InvalidArgumentException::class);
        OfficeMonitorSchedulePolicy::setCustomDay((int) $this->office->id, 'sitfis', 29);
    }

    public function test_dia_zero_rejeitado_preserva_anterior(): void
    {
        $policy = OfficeMonitorSchedulePolicy::setCustomDay((int) $this->office->id, 'sitfis', 12);
        try {
            OfficeMonitorSchedulePolicy::setCustomDay((int) $this->office->id, 'sitfis', 0);
            $this->fail('deveria rejeitar dia 0');
        } catch (InvalidArgumentException) {
            // ok
        }
        $this->assertSame(12, $policy->fresh()->day_of_month);
    }

    public function test_scheduler_cria_um_item_por_cliente_monitor_periodo_e_enfileira(): void
    {
        Bus::fake();

        OfficeMonitorSchedulePolicy::setCustomDay((int) $this->office->id, 'sitfis', 10);
        // Elegível via schedule habilitado
        FiscalMonitoringSchedule::query()->create([
            'office_id' => $this->office->id,
            'client_id' => $this->client->id,
            'system_code' => 'INTEGRA_SITFIS',
            'service_code' => 'SITFIS',
            'operation_code' => 'MONITOR',
            'is_enabled' => true,
            'interval_minutes' => 60,
            'preferred_minute' => 0,
        ]);

        $now = CarbonImmutable::parse('2026-06-10 15:00:00', 'America/Sao_Paulo')->utc();
        $scheduler = app(FiscalMonitoringScheduler::class);
        $result = $scheduler->dispatchCommercialMonthlyDue($now);

        $this->assertGreaterThanOrEqual(1, $result['commercial_created']);
        $this->assertGreaterThanOrEqual(1, $result['dispatched']);

        $entries = MonitorCommercialLedgerEntry::query()
            ->where('office_id', $this->office->id)
            ->where('client_id', $this->client->id)
            ->where('monitor_key', 'sitfis')
            ->get();
        $this->assertCount(1, $entries);

        // Reexecução idempotente
        $result2 = $scheduler->dispatchCommercialMonthlyDue($now);
        $this->assertSame(0, $result2['commercial_created']);
        $this->assertSame(1, MonitorCommercialLedgerEntry::query()
            ->where('client_id', $this->client->id)
            ->where('monitor_key', 'sitfis')
            ->count());

        $this->assertSame(1, FiscalMonitoringRun::query()
            ->withoutGlobalScopes()
            ->where('office_id', $this->office->id)
            ->where('client_id', $this->client->id)
            ->where('service_code', 'SITFIS')
            ->count());
    }

    public function test_saldo_esgotado_bloqueia_scheduled_sem_run(): void
    {
        Bus::fake();
        OfficeMonitorSchedulePolicy::setCustomDay((int) $this->office->id, 'sitfis', 5);
        FiscalMonitoringSchedule::query()->create([
            'office_id' => $this->office->id,
            'client_id' => $this->client->id,
            'system_code' => 'INTEGRA_SITFIS',
            'service_code' => 'SITFIS',
            'operation_code' => 'MONITOR',
            'is_enabled' => true,
            'interval_minutes' => 60,
            'preferred_minute' => 0,
        ]);

        $ledger = app(MonitorCommercialLedgerService::class);
        // inaugural + 5 consumos
        $ledger->authorizeAndDebitBeforeRemoteDispatch(
            officeId: (int) $this->office->id,
            clientId: (int) $this->client->id,
            monitorKey: 'sitfis',
            origin: MonitorCommercialOrigin::Manual,
            idempotencyKey: 'ex-0',
            technicalCorrelationId: 'ex-0',
            subscription: $this->subscription,
        );
        for ($i = 1; $i <= 5; $i++) {
            $ledger->authorizeAndDebitBeforeRemoteDispatch(
                officeId: (int) $this->office->id,
                clientId: (int) $this->client->id,
                monitorKey: 'sitfis',
                origin: MonitorCommercialOrigin::Manual,
                idempotencyKey: "ex-{$i}",
                technicalCorrelationId: "ex-c-{$i}",
                subscription: $this->subscription,
            );
        }

        $now = CarbonImmutable::parse('2026-06-05 12:00:00', 'America/Sao_Paulo')->utc();
        $result = app(FiscalMonitoringScheduler::class)->dispatchCommercialMonthlyDue($now);

        $this->assertGreaterThanOrEqual(1, $result['blocked']);
        $this->assertSame(0, FiscalMonitoringRun::query()
            ->withoutGlobalScopes()
            ->where('client_id', $this->client->id)
            ->where('service_code', 'SITFIS')
            ->count());

        $auto = MonitorCommercialLedgerEntry::query()
            ->where('client_id', $this->client->id)
            ->where('monitor_key', 'sitfis')
            ->whereIn('origin', [
                MonitorCommercialOrigin::Scheduled->value,
                MonitorCommercialOrigin::Inaugural->value,
            ])
            ->where('dispatch_state', MonitorCommercialDispatchState::BlockedQuota)
            ->exists();
        // inaugural já existia manual → scheduled blocked
        $this->assertTrue(
            MonitorCommercialLedgerEntry::query()
                ->where('client_id', $this->client->id)
                ->where('monitor_key', 'sitfis')
                ->where('origin', MonitorCommercialOrigin::Scheduled)
                ->where('dispatch_state', MonitorCommercialDispatchState::BlockedQuota)
                ->exists()
            || $auto
        );
    }

    public function test_spillover_processa_pending_apos_dia_configurado(): void
    {
        Bus::fake();
        OfficeMonitorSchedulePolicy::setCustomDay((int) $this->office->id, 'dctfweb', 8);

        // Item pending criado no dia 8, despacho no dia 12 (spillover).
        $entry = app(MonitorCommercialLedgerService::class)->ensureScheduledItem(
            (int) $this->office->id,
            (int) $this->client->id,
            'dctfweb',
            $this->subscription,
        );
        $this->assertSame(MonitorCommercialDispatchState::Pending, $entry->dispatch_state);

        FiscalMonitoringSchedule::query()->create([
            'office_id' => $this->office->id,
            'client_id' => $this->client->id,
            'system_code' => 'DCTFWEB',
            'service_code' => 'DCTFWEB',
            'operation_code' => 'MONITOR',
            'is_enabled' => true,
            'interval_minutes' => 60,
            'preferred_minute' => 0,
        ]);

        $now = CarbonImmutable::parse('2026-06-12 09:00:00', 'America/Sao_Paulo')->utc();
        $result = app(FiscalMonitoringScheduler::class)->dispatchCommercialMonthlyDue($now);

        $this->assertGreaterThanOrEqual(1, $result['dispatched']);
        $this->assertSame(1, FiscalMonitoringRun::query()
            ->withoutGlobalScopes()
            ->where('client_id', $this->client->id)
            ->where('service_code', 'DCTFWEB')
            ->count());
    }

    public function test_antes_do_dia_sem_pending_nao_cria(): void
    {
        Bus::fake();
        OfficeMonitorSchedulePolicy::setCustomDay((int) $this->office->id, 'mailbox', 20);
        FiscalMonitoringSchedule::query()->create([
            'office_id' => $this->office->id,
            'client_id' => $this->client->id,
            'system_code' => 'CAIXA_POSTAL',
            'service_code' => 'CAIXA_POSTAL',
            'operation_code' => 'MONITOR',
            'is_enabled' => true,
            'interval_minutes' => 60,
            'preferred_minute' => 0,
        ]);

        $now = CarbonImmutable::parse('2026-06-05 09:00:00', 'America/Sao_Paulo')->utc();
        $result = app(FiscalMonitoringScheduler::class)->dispatchCommercialMonthlyDue($now);

        $this->assertSame(0, MonitorCommercialLedgerEntry::query()
            ->where('office_id', $this->office->id)
            ->where('monitor_key', 'mailbox')
            ->count());
        $this->assertSame(0, $result['dispatched']);
    }

    public function test_nfse_sefaz_autxml_fora_do_catalogo_comercial(): void
    {
        $this->assertFalse(CommercialMonitorCatalog::isCommercialMonitor('nfse'));
        $this->assertFalse(CommercialMonitorCatalog::isCommercialMonitor('sefaz'));
        $this->assertFalse(CommercialMonitorCatalog::isCommercialMonitor('autxml'));
        $this->assertTrue(CommercialMonitorCatalog::isRealtimeNonFranchiseChannel(null, null, 'autXML'));
        $this->assertTrue(CommercialMonitorCatalog::isRealtimeNonFranchiseChannel('SEFAZ_SVRS', 'NFE'));
        $this->assertTrue(CommercialMonitorCatalog::isRealtimeNonFranchiseChannel('ADN_NFSE', 'EVENTO'));
    }

    public function test_monitor_simples_mei_usa_divida_ativa_para_cliente_mei(): void
    {
        Bus::fake();
        $this->client->forceFill(['tax_regime' => 'MEI'])->save();
        OfficeMonitorSchedulePolicy::setCustomDay((int) $this->office->id, 'simples_mei', 5);
        FiscalMonitoringSchedule::query()->create([
            'office_id' => $this->office->id,
            'client_id' => $this->client->id,
            'system_code' => 'INTEGRA_MEI',
            'service_code' => 'PGMEI',
            'operation_code' => 'MONITOR',
            'is_enabled' => true,
            'interval_minutes' => 1440,
            'preferred_minute' => 0,
        ]);

        $now = CarbonImmutable::parse('2026-06-05 09:00:00', 'America/Sao_Paulo')->utc();
        $result = app(FiscalMonitoringScheduler::class)->dispatchCommercialMonthlyDue($now);

        $this->assertGreaterThanOrEqual(1, $result['dispatched']);
        $run = FiscalMonitoringRun::query()
            ->withoutGlobalScopes()
            ->where('client_id', $this->client->id)
            ->where('service_code', 'PGMEI')
            ->sole();
        $this->assertSame('INTEGRA_MEI', $run->system_code);
        $this->assertSame('MONITOR', $run->operation_code);
        $this->assertArrayHasKey('ano_calendario', $run->progress);
    }

    public function test_legacy_interval_pausa_monitor_comercial_quando_mensal_ativo(): void
    {
        Bus::fake();
        $schedule = FiscalMonitoringSchedule::query()->create([
            'office_id' => $this->office->id,
            'client_id' => $this->client->id,
            'system_code' => 'INTEGRA_SITFIS',
            'service_code' => 'SITFIS',
            'operation_code' => 'MONITOR',
            'is_enabled' => true,
            'interval_minutes' => 60,
            'preferred_minute' => (int) now()->format('i'),
            'next_run_at' => now()->subMinute(),
        ]);

        $result = app(FiscalMonitoringScheduler::class)->dispatchLegacyIntervalDue(CarbonImmutable::now());
        $this->assertGreaterThanOrEqual(1, $result['skipped']);
        $this->assertSame(0, FiscalMonitoringRun::query()
            ->withoutGlobalScopes()
            ->where('schedule_id', $schedule->id)
            ->count());
    }

    public function test_commercial_monthly_disabled_nao_despacha(): void
    {
        config(['fiscal_monitoring.scheduler.commercial_monthly_enabled' => false]);
        Bus::fake();
        OfficeMonitorSchedulePolicy::setCustomDay((int) $this->office->id, 'sitfis', 1);

        $now = CarbonImmutable::parse('2026-06-01 10:00:00', 'America/Sao_Paulo')->utc();
        $result = app(FiscalMonitoringScheduler::class)->dispatchCommercialMonthlyDue($now);
        $this->assertSame(0, $result['dispatched']);
        $this->assertSame(0, $result['commercial_created']);
    }
}
