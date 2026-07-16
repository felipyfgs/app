<?php

namespace App\Services\Serpro\Usage;

use App\Models\SerproApiUsageEntry;
use App\Models\SerproUsageMonthlyAggregate;
use App\Models\SerproUsageReconciliation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

/**
 * Consultas de consumo para tenant e plataforma.
 * Controllers tenant usam este service (sem importar models Serpro*).
 */
final class UsageReportService
{
    public function __construct(
        private readonly UsageBudgetGate $budget,
        private readonly UsageShadowPolicy $shadow,
        private readonly UsageAggregationService $aggregates,
    ) {}

    /**
     * Painel de uso/franquia do escritório ativo.
     *
     * @return array<string, mixed>
     */
    public function tenantUsageSummary(int $officeId, ?int $year = null, ?int $month = null): array
    {
        $at = $this->periodMoment($year, $month);
        $snapshot = $this->budget->tenantSnapshot($officeId, $at);

        $aggregates = SerproUsageMonthlyAggregate::query()
            ->where('scope', SerproUsageMonthlyAggregate::SCOPE_TENANT)
            ->where('office_id', $officeId)
            ->where('period_year', $snapshot['period_year'])
            ->where('period_month', $snapshot['period_month'])
            ->orderBy('service_code')
            ->get()
            ->map(fn (SerproUsageMonthlyAggregate $a) => $a->toPublicArray(includeOfficeId: false))
            ->all();

        // Se ainda não houve recompute, monta on-the-fly a partir do ledger.
        if ($aggregates === []) {
            $this->aggregates->recomputeMonth(
                $snapshot['period_year'],
                $snapshot['period_month'],
                $officeId,
            );
            $aggregates = SerproUsageMonthlyAggregate::query()
                ->where('scope', SerproUsageMonthlyAggregate::SCOPE_TENANT)
                ->where('office_id', $officeId)
                ->where('period_year', $snapshot['period_year'])
                ->where('period_month', $snapshot['period_month'])
                ->orderBy('service_code')
                ->get()
                ->map(fn (SerproUsageMonthlyAggregate $a) => $a->toPublicArray(includeOfficeId: false))
                ->all();
        }

        return [
            'summary' => $snapshot,
            'by_service' => $aggregates,
            // Tenant NÃO recebe global_budget / custo de outros offices.
        ];
    }

    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function tenantEntries(
        int $officeId,
        int $perPage = 50,
        ?int $year = null,
        ?int $month = null,
        string $sort = '',
        string $direction = '',
    ): LengthAwarePaginator {
        $sortColumn = match ($sort) {
            'quantity' => 'quantity',
            'result' => 'result',
            'client_id' => 'client_id',
            'id' => 'id',
            default => 'occurred_at',
        };
        $sortDirection = strtolower($direction) === 'asc' ? 'asc' : 'desc';
        $query = SerproApiUsageEntry::query()
            ->withoutGlobalScopes()
            ->where('office_id', $officeId)
            ->orderBy($sortColumn, $sortDirection);
        if ($sortColumn !== 'id') {
            $query->orderBy('id', $sortDirection);
        }

        if ($year !== null && $month !== null) {
            $start = Carbon::create($year, $month, 1)->startOfMonth();
            $end = $start->copy()->endOfMonth();
            $query->whereBetween('occurred_at', [$start, $end]);
        }

        return $query->paginate($perPage)->through(
            fn (SerproApiUsageEntry $e) => $e->toTenantArray()
        );
    }

    /**
     * Consolidação global (PLATFORM_ADMIN).
     *
     * @return array<string, mixed>
     */
    public function platformConsolidation(?int $year = null, ?int $month = null, bool $recompute = false): array
    {
        $at = $this->periodMoment($year, $month);
        $y = (int) $at->year;
        $m = (int) $at->month;

        if ($recompute) {
            $this->aggregates->recomputeMonth($y, $m);
        }

        $global = SerproUsageMonthlyAggregate::query()
            ->where('scope', SerproUsageMonthlyAggregate::SCOPE_GLOBAL)
            ->where('period_year', $y)
            ->where('period_month', $m)
            ->orderBy('service_code')
            ->get()
            ->map(fn (SerproUsageMonthlyAggregate $a) => $a->toPublicArray(includeOfficeId: false))
            ->all();

        $byTenant = SerproUsageMonthlyAggregate::query()
            ->where('scope', SerproUsageMonthlyAggregate::SCOPE_TENANT)
            ->where('period_year', $y)
            ->where('period_month', $m)
            ->orderBy('office_id')
            ->get()
            ->groupBy('office_id')
            ->map(function ($rows, $officeId) {
                $qty = $rows->sum('total_quantity');
                $cost = $rows->sum('total_estimated_cost_micros');
                $entries = $rows->sum('entry_count');

                return [
                    'office_id' => (int) $officeId,
                    'entry_count' => $entries,
                    'total_quantity' => $qty,
                    'total_estimated_cost_micros' => $cost,
                ];
            })
            ->values()
            ->all();

        $reconciliations = SerproUsageReconciliation::query()
            ->where('period_year', $y)
            ->where('period_month', $m)
            ->with('adjustments')
            ->orderByDesc('id')
            ->get()
            ->map(fn (SerproUsageReconciliation $r) => $r->toPlatformArray())
            ->all();

        return [
            'period_year' => $y,
            'period_month' => $m,
            'policy' => $this->shadow->snapshot(),
            'global_aggregates' => $global,
            'by_tenant' => $byTenant,
            'internal_estimated_total_micros' => $this->aggregates->internalEstimatedTotalMicros($y, $m),
            'global_monthly_budget' => config('serpro_usage.global_monthly_budget'),
            'reconciliations' => $reconciliations,
        ];
    }

    private function periodMoment(?int $year, ?int $month): Carbon
    {
        if ($year !== null && $month !== null) {
            return Carbon::create($year, $month, 15)->startOfDay();
        }

        return now();
    }
}
