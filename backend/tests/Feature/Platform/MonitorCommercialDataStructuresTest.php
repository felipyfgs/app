<?php

namespace Tests\Feature\Platform;

use App\Enums\ClientProcuracaoSyncStatus;
use App\Enums\MonitorCommercialDispatchState;
use App\Enums\MonitorCommercialOrigin;
use App\Enums\SubscriptionPlan;
use App\Models\Client;
use App\Models\ClientProcuracaoSync;
use App\Models\MonitorCommercialLedgerEntry;
use App\Models\Office;
use App\Models\OfficeMonitorSchedulePolicy;
use App\Models\OfficeSubscription;
use App\Models\SerproApiUsageEntry;
use App\Services\FiscalMonitoring\MonitorScheduleDayHasher;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Task 1.3 — estruturas de franquia comercial / procurações / política mensal.
 * Unicidades, idempotência e preservação do ledger técnico.
 */
class MonitorCommercialDataStructuresTest extends TestCase
{
    use RefreshDatabase;

    public function test_tabelas_e_colunas_existem(): void
    {
        $this->assertTrue(Schema::hasTable('client_procuracao_syncs'));
        $this->assertTrue(Schema::hasTable('monitor_commercial_ledger_entries'));
        $this->assertTrue(Schema::hasTable('office_monitor_schedule_policies'));
        $this->assertTrue(Schema::hasColumns('office_subscriptions', [
            'commercial_monitor_units',
            'negotiated_client_limit',
            'monthly_api_quota',
        ]));
        // Ledger técnico intacto.
        $this->assertTrue(Schema::hasTable('serpro_api_usage_entries'));
        $this->assertTrue(Schema::hasTable('serpro_api_usage_reservations'));
    }

    public function test_entitlements_comerciais_por_plano_preservam_quota_tecnica(): void
    {
        $this->assertSame(5, SubscriptionPlan::Starter->commercialMonitorUnits());
        $this->assertSame(100, SubscriptionPlan::Starter->commercialMaxClients());
        $this->assertSame(7, SubscriptionPlan::Professional->commercialMonitorUnits());
        $this->assertSame(150, SubscriptionPlan::Professional->commercialMaxClients());
        $this->assertSame(10, SubscriptionPlan::Enterprise->commercialMonitorUnits());
        $this->assertSame(200, SubscriptionPlan::Enterprise->commercialMaxClients());

        // Orçamento técnico legado permanece nos defaultLimits.
        $this->assertSame(1_000, SubscriptionPlan::Starter->defaultLimits()['monthly_api_quota']);
        $this->assertSame(10_000, SubscriptionPlan::Professional->defaultLimits()['monthly_api_quota']);
        $this->assertSame(100_000, SubscriptionPlan::Enterprise->defaultLimits()['monthly_api_quota']);

        $office = Office::factory()->create();
        $sub = OfficeSubscription::query()->where('office_id', $office->id)->firstOrFail();

        $this->assertSame(7, $sub->commercial_monitor_units);
        $this->assertSame(7, $sub->resolvedCommercialMonitorUnits());
        $this->assertSame(150, $sub->effectiveCommercialMaxClients());
        $this->assertNull($sub->negotiated_client_limit);
        $this->assertSame(10_000, $sub->monthly_api_quota);

        $sub->negotiated_client_limit = 260;
        $sub->save();
        $this->assertSame(260, $sub->fresh()->effectiveCommercialMaxClients());
        // Override de clientes não altera franquia de consultas do plano.
        $this->assertSame(SubscriptionPlan::Professional, $sub->fresh()->plan);
        $this->assertSame(7, $sub->fresh()->resolvedCommercialMonitorUnits());
    }

    public function test_client_procuracao_sync_unica_por_office_cliente(): void
    {
        $office = Office::factory()->create();
        $client = Client::factory()->forOffice($office)->create();

        $sync = ClientProcuracaoSync::factory()->forClient($client)->authorized()->create();
        $this->assertSame(ClientProcuracaoSyncStatus::Authorized, $sync->status);
        $this->assertTrue($sync->isAuthorized());

        $this->expectException(QueryException::class);
        ClientProcuracaoSync::factory()->forClient($client)->missing()->create();
    }

    public function test_ledger_comercial_idempotency_key_unica(): void
    {
        $office = Office::factory()->create();
        $client = Client::factory()->forOffice($office)->create();

        MonitorCommercialLedgerEntry::factory()->forClient($client)->create([
            'idempotency_key' => 'same-key-once',
        ]);

        $this->expectException(QueryException::class);
        MonitorCommercialLedgerEntry::factory()->forClient($client)->create([
            'idempotency_key' => 'same-key-once',
            'monitor_key' => 'dctfweb',
        ]);
    }

    public function test_inaugural_unica_por_cliente_e_monitor(): void
    {
        $office = Office::factory()->create();
        $client = Client::factory()->forOffice($office)->create();

        MonitorCommercialLedgerEntry::factory()
            ->forClient($client)
            ->inaugural()
            ->monitor('sitfis')
            ->create();

        // Outro monitor no mesmo cliente: ok.
        MonitorCommercialLedgerEntry::factory()
            ->forClient($client)
            ->inaugural()
            ->monitor('dctfweb')
            ->create();

        // Mesmo cliente+monitor inaugural: bloqueado.
        $this->expectException(QueryException::class);
        MonitorCommercialLedgerEntry::factory()
            ->forClient($client)
            ->inaugural()
            ->monitor('sitfis')
            ->create([
                'period_key' => '2099-01-01',
            ]);
    }

    public function test_scheduled_unica_por_cliente_monitor_e_periodo(): void
    {
        $office = Office::factory()->create();
        $client = Client::factory()->forOffice($office)->create();

        MonitorCommercialLedgerEntry::factory()
            ->forClient($client)
            ->scheduled()
            ->monitor('sitfis')
            ->forPeriod('2026-07-01')
            ->create();

        // Outro período: ok.
        MonitorCommercialLedgerEntry::factory()
            ->forClient($client)
            ->scheduled()
            ->monitor('sitfis')
            ->forPeriod('2026-08-01')
            ->create();

        // Mesmo período scheduled: bloqueado (idempotência do item automático).
        $this->expectException(QueryException::class);
        MonitorCommercialLedgerEntry::factory()
            ->forClient($client)
            ->scheduled()
            ->monitor('sitfis')
            ->forPeriod('2026-07-01')
            ->create();
    }

    public function test_manual_pode_repetir_no_mesmo_periodo_com_chaves_diferentes(): void
    {
        $office = Office::factory()->create();
        $client = Client::factory()->forOffice($office)->create();

        $a = MonitorCommercialLedgerEntry::factory()
            ->forClient($client)
            ->manualDispatched()
            ->forPeriod('2026-07-01')
            ->create();

        $b = MonitorCommercialLedgerEntry::factory()
            ->forClient($client)
            ->manualDispatched()
            ->forPeriod('2026-07-01')
            ->create();

        $this->assertNotSame($a->idempotency_key, $b->idempotency_key);
        $this->assertSame(1, $a->quota_units);
        $this->assertSame(MonitorCommercialOrigin::Manual, $b->origin);
    }

    public function test_identidade_do_ledger_comercial_e_imutavel(): void
    {
        $entry = MonitorCommercialLedgerEntry::factory()->create();

        $this->expectException(\LogicException::class);
        $entry->monitor_key = 'dctfweb';
        $entry->save();
    }

    public function test_dispatch_state_pode_avancar(): void
    {
        $entry = MonitorCommercialLedgerEntry::factory()->create([
            'dispatch_state' => MonitorCommercialDispatchState::Pending,
            'quota_units' => 0,
        ]);

        $entry->dispatch_state = MonitorCommercialDispatchState::Dispatched;
        $entry->quota_units = 1;
        $entry->dispatched_at = now();
        $entry->save();

        $this->assertSame(MonitorCommercialDispatchState::Dispatched, $entry->fresh()->dispatch_state);
        $this->assertSame(1, $entry->fresh()->quota_units);
    }

    public function test_politica_mensal_unica_por_office_monitor_e_dia_1_a_28(): void
    {
        $office = Office::factory()->create();

        $policy = OfficeMonitorSchedulePolicy::ensureDefault($office->id, 'sitfis');
        $this->assertFalse($policy->is_custom);
        $this->assertSame(
            MonitorScheduleDayHasher::defaultDay($office->id, 'sitfis'),
            $policy->day_of_month
        );

        $custom = OfficeMonitorSchedulePolicy::setCustomDay($office->id, 'sitfis', 12);
        $this->assertTrue($custom->is_custom);
        $this->assertSame(12, $custom->day_of_month);
        $this->assertSame(1, OfficeMonitorSchedulePolicy::query()->where('office_id', $office->id)->count());

        $this->expectException(InvalidArgumentException::class);
        OfficeMonitorSchedulePolicy::setCustomDay($office->id, 'sitfis', 29);
    }

    public function test_politica_rejeita_dia_zero_no_save(): void
    {
        $office = Office::factory()->create();

        $this->expectException(InvalidArgumentException::class);
        OfficeMonitorSchedulePolicy::factory()->forOffice($office)->create([
            'day_of_month' => 0,
            'is_custom' => true,
        ]);
    }

    public function test_hash_de_dia_estavel_e_entre_1_e_28(): void
    {
        $a = MonitorScheduleDayHasher::defaultDay(42, 'sitfis');
        $b = MonitorScheduleDayHasher::defaultDay(42, 'sitfis');
        $c = MonitorScheduleDayHasher::defaultDay(42, 'dctfweb');

        $this->assertSame($a, $b);
        $this->assertGreaterThanOrEqual(1, $a);
        $this->assertLessThanOrEqual(28, $a);
        // Monitores distintos tipicamente divergem (não garantido matematicamente; checamos range).
        $this->assertGreaterThanOrEqual(1, $c);
        $this->assertLessThanOrEqual(28, $c);

        // Distribuição grosseira: várias offices cobrem >1 dia distinto.
        $days = [];
        for ($i = 1; $i <= 80; $i++) {
            $days[MonitorScheduleDayHasher::defaultDay($i, 'sitfis')] = true;
        }
        $this->assertGreaterThan(5, count($days));
    }

    public function test_ledger_tecnico_serpro_nao_foi_substituido(): void
    {
        $this->assertTrue(class_exists(SerproApiUsageEntry::class));
        $this->assertNotSame(
            MonitorCommercialLedgerEntry::class,
            SerproApiUsageEntry::class
        );
        $this->assertTrue(Schema::hasColumn('serpro_api_usage_entries', 'idempotency_key'));
        $this->assertTrue(Schema::hasColumn('monitor_commercial_ledger_entries', 'quota_units'));
    }
}
