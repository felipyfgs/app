<?php

namespace Tests\Unit\Serpro\Usage;

use App\Enums\SerproReconciliationStatus;
use App\Enums\SerproUsageResult;
use App\Models\Office;
use App\Models\SerproApiUsageEntry;
use App\Models\SerproUsageMonthlyAggregate;
use App\Services\Serpro\Usage\UsageAggregationService;
use App\Services\Serpro\Usage\UsageLedgerService;
use App\Services\Serpro\Usage\UsageReconciliationService;
use App\Services\Serpro\Usage\UsageReserveRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UsageAggregationAndReconciliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_agregacao_mensal_global_e_por_tenant_recomputavel(): void
    {
        $officeA = Office::factory()->create();
        $officeB = Office::factory()->create();
        $ledger = app(UsageLedgerService::class);
        $agg = app(UsageAggregationService::class);

        foreach ([['a1', $officeA], ['a2', $officeA], ['b1', $officeB]] as [$key, $office]) {
            $o = $ledger->reserve(new UsageReserveRequest(
                officeId: $office->id,
                idempotencyKey: $key,
                systemCode: 'INTEGRA_CONTADOR',
                serviceCode: 'SITFIS',
                operationCode: 'CONSULTAR_SITUACAO',
            ));
            $ledger->finalize($o->reservation, SerproUsageResult::Success);
        }

        $year = (int) now()->year;
        $month = (int) now()->month;

        $result = $agg->recomputeMonth($year, $month);
        $this->assertGreaterThanOrEqual(2, $result['tenant_rows']);
        $this->assertGreaterThanOrEqual(1, $result['global_rows']);

        $tenantAQty = SerproUsageMonthlyAggregate::query()
            ->where('scope', 'TENANT')
            ->where('office_id', $officeA->id)
            ->where('period_year', $year)
            ->where('period_month', $month)
            ->sum('total_quantity');
        $this->assertSame(2, (int) $tenantAQty);

        $globalTotal = SerproUsageMonthlyAggregate::query()
            ->where('scope', 'GLOBAL')
            ->whereNull('office_id')
            ->whereNull('service_code')
            ->where('period_year', $year)
            ->where('period_month', $month)
            ->first();
        $this->assertNotNull($globalTotal);
        $this->assertSame(3, $globalTotal->total_quantity);

        // Recomputação é idempotente em totais
        $agg->recomputeMonth($year, $month);
        $this->assertSame(3, (int) SerproUsageMonthlyAggregate::query()
            ->where('scope', 'GLOBAL')
            ->whereNull('service_code')
            ->where('period_year', $year)
            ->where('period_month', $month)
            ->value('total_quantity'));
    }

    public function test_conciliacao_registra_divergencia_sem_reescrever_ledger(): void
    {
        $office = Office::factory()->create();
        $ledger = app(UsageLedgerService::class);
        $recon = app(UsageReconciliationService::class);

        $o = $ledger->reserve(new UsageReserveRequest(
            officeId: $office->id,
            idempotencyKey: 'recon-1',
            systemCode: 'INTEGRA_CONTADOR',
            serviceCode: 'SITFIS',
            operationCode: 'CONSULTAR_SITUACAO',
        ));
        $entry = $ledger->finalize($o->reservation, SerproUsageResult::Success);
        $originalCost = $entry->estimated_cost_micros;

        $year = (int) now()->year;
        $month = (int) now()->month;

        $official = ($originalCost ?? 0) + 50_000;
        $record = $recon->registerOfficialInvoice(
            year: $year,
            month: $month,
            officialTotalCostMicros: $official,
            officialReference: 'FAT-2026-07-001',
            officialSource: 'SERPRO_INVOICE',
            adjustments: [[
                'office_id' => $office->id,
                'service_code' => 'SITFIS',
                'amount_micros' => 50_000,
                'reason' => 'ARREDONDAMENTO_OFICIAL',
            ]],
            differenceCause: 'PRICE_TABLE_DRIFT',
        );

        $this->assertSame(SerproReconciliationStatus::Adjusted, $record->status);
        $this->assertSame(50_000, $record->difference_micros);
        $this->assertCount(1, $record->adjustments);

        // Ledger original intacto
        $entry->refresh();
        $this->assertSame($originalCost, $entry->estimated_cost_micros);
        $this->assertSame(1, SerproApiUsageEntry::query()->withoutGlobalScopes()->count());

        $this->assertDatabaseHas('serpro_usage_reconciliations', [
            'official_reference' => 'FAT-2026-07-001',
            'status' => SerproReconciliationStatus::Adjusted->value,
        ]);
        $this->assertDatabaseCount('serpro_usage_reconciliation_adjustments', 1);
    }

    public function test_conciliacao_matched_quando_igual(): void
    {
        $office = Office::factory()->create();
        $ledger = app(UsageLedgerService::class);
        $recon = app(UsageReconciliationService::class);

        $o = $ledger->reserve(new UsageReserveRequest(
            officeId: $office->id,
            idempotencyKey: 'recon-match',
            systemCode: 'INTEGRA_CONTADOR',
            serviceCode: 'SITFIS',
            operationCode: 'CONSULTAR_SITUACAO',
        ));
        $entry = $ledger->finalize($o->reservation, SerproUsageResult::Success);

        $record = $recon->registerOfficialInvoice(
            year: (int) now()->year,
            month: (int) now()->month,
            officialTotalCostMicros: (int) $entry->estimated_cost_micros,
            officialReference: 'FAT-MATCH',
        );

        $this->assertSame(SerproReconciliationStatus::Matched, $record->status);
        $this->assertSame(0, $record->difference_micros);
    }
}
