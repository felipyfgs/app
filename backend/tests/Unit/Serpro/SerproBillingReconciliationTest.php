<?php

namespace Tests\Unit\Serpro;

use App\Enums\SerproReconciliationStatus;
use App\Enums\SerproUsageResult;
use App\Models\Office;
use App\Models\SerproBillingInvoiceLine;
use App\Models\SerproUsageIncident;
use App\Models\SerproUsageMonthlyAggregate;
use App\Services\Serpro\Usage\BillingCycleResolver;
use App\Services\Serpro\Usage\UsageAggregationService;
use App\Services\Serpro\Usage\UsageLedgerService;
use App\Services\Serpro\Usage\UsageReconciliationService;
use App\Services\Serpro\Usage\UsageReserveRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

final class SerproBillingReconciliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_agregacao_ciclo_21_20_distinta_do_calendario(): void
    {
        $office = Office::factory()->create();
        $ledger = app(UsageLedgerService::class);
        $agg = app(UsageAggregationService::class);
        $cycles = app(BillingCycleResolver::class);

        // Entrada no dia 22 (pertence ao ciclo 21→20 do mês seguinte, não “só” calendário)
        Carbon::setTestNow(Carbon::parse('2026-07-22 12:00:00', 'America/Sao_Paulo'));

        $o = $ledger->reserve(new UsageReserveRequest(
            officeId: $office->id,
            idempotencyKey: 'cycle-entry-1',
            systemCode: 'INTEGRA_CONTADOR',
            serviceCode: 'SITFIS',
            operationCode: 'CONSULTAR_SITUACAO',
            functionalRoute: 'Consultar',
            requestTag: 'tag-cycle-entry-1-cccccccccc',
        ));
        $ledger->finalize($o->reservation, SerproUsageResult::Success, httpStatus: 200);

        $cycle = $cycles->resolve();
        $this->assertSame('2026-07-21', $cycle['period_start']->toDateString());

        $billing = $agg->recomputeBillingCycle();
        $this->assertSame($cycle['cycle_code'], $billing['cycle_code']);
        $this->assertGreaterThanOrEqual(1, $billing['tenant_rows']);

        $calendar = $agg->recomputeMonth(2026, 7);
        $this->assertGreaterThanOrEqual(1, $calendar['tenant_rows']);

        $cycleRows = SerproUsageMonthlyAggregate::query()
            ->where('period_kind', UsageAggregationService::PERIOD_BILLING_CYCLE)
            ->where('cycle_code', $cycle['cycle_code'])
            ->count();
        $this->assertGreaterThanOrEqual(1, $cycleRows);

        Carbon::setTestNow();
    }

    public function test_import_detalhamento_e_incidente_de_divergencia(): void
    {
        $office = Office::factory()->create();
        $ledger = app(UsageLedgerService::class);
        $recon = app(UsageReconciliationService::class);

        $o = $ledger->reserve(new UsageReserveRequest(
            officeId: $office->id,
            idempotencyKey: 'recon-detail-1',
            systemCode: 'INTEGRA_CONTADOR',
            serviceCode: 'SITFIS',
            operationCode: 'CONSULTAR_SITUACAO',
            functionalRoute: 'Consultar',
            requestTag: 'tag-recon-detail-1-dddddddd',
        ));
        $entry = $ledger->finalize($o->reservation, SerproUsageResult::Success, httpStatus: 200);

        $year = (int) now()->year;
        $month = (int) now()->month;
        $official = ((int) $entry->estimated_cost_micros) + 10_000;

        $record = $recon->registerOfficialInvoice(
            year: $year,
            month: $month,
            officialTotalCostMicros: $official,
            officialReference: 'FAT-DETAIL-001',
            detailLines: [[
                'office_id' => $office->id,
                'functional_route' => 'Consultar',
                'http_status' => 200,
                'request_tag' => 'tag-recon-detail-1-dddddddd',
                'service_code' => 'SITFIS',
                'official_cost_micros' => $official,
            ]],
            officeIdScope: $office->id,
        );

        $this->assertSame(SerproReconciliationStatus::Divergent, $record->status);
        $this->assertSame(1, SerproBillingInvoiceLine::query()->count());
        $this->assertSame(1, SerproUsageIncident::query()
            ->where('kind', SerproUsageIncident::KIND_RECONCILIATION_DIVERGENCE)
            ->count());

        // Ledger original intacto
        $originalCost = $entry->estimated_cost_micros;
        $entry->refresh();
        $this->assertSame($originalCost, $entry->estimated_cost_micros);
    }
}
