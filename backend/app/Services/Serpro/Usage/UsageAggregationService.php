<?php

namespace App\Services\Serpro\Usage;

use App\Enums\SerproConsumptionClass;
use App\Models\SerproApiUsageEntry;
use App\Models\SerproUsageMonthlyAggregate;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Agregações mensais globais e por tenant, recomputáveis a partir do ledger.
 */
final class UsageAggregationService
{
    /**
     * Recomputa agregados do período a partir de serpro_api_usage_entries.
     *
     * @return array{tenant_rows: int, global_rows: int}
     */
    public function recomputeMonth(int $year, int $month, ?int $officeId = null): array
    {
        $start = Carbon::create($year, $month, 1)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $query = SerproApiUsageEntry::query()
            ->withoutGlobalScopes()
            ->whereBetween('occurred_at', [$start, $end]);

        if ($officeId !== null) {
            $query->where('office_id', $officeId);
        }

        /** @var Collection<int, object> $groups */
        $groups = $query
            ->select([
                'office_id',
                'system_code',
                'service_code',
                'consumption_class',
                DB::raw('COUNT(*) as entry_count'),
                DB::raw('COALESCE(SUM(quantity), 0) as total_quantity'),
                DB::raw('COALESCE(SUM(estimated_cost_micros), 0) as total_estimated_cost_micros'),
                DB::raw("SUM(CASE WHEN consumption_class = 'DESCONHECIDA' THEN 1 ELSE 0 END) as unknown_class_count"),
                DB::raw('SUM(CASE WHEN is_billable_attempt = 1 THEN 1 ELSE 0 END) as billable_attempt_count'),
            ])
            ->groupBy(['office_id', 'system_code', 'service_code', 'consumption_class'])
            ->get();

        $now = now();
        $tenantRows = 0;

        // Limpa agregados do escopo alvo no período antes de regravar
        $delete = SerproUsageMonthlyAggregate::query()
            ->where('period_year', $year)
            ->where('period_month', $month);

        if ($officeId !== null) {
            $delete->where('scope', SerproUsageMonthlyAggregate::SCOPE_TENANT)
                ->where('office_id', $officeId);
        } else {
            $delete->where(function ($q) use ($year, $month): void {
                $q->where('scope', SerproUsageMonthlyAggregate::SCOPE_TENANT)
                    ->orWhere('scope', SerproUsageMonthlyAggregate::SCOPE_GLOBAL);
            });
        }
        $delete->delete();

        foreach ($groups as $row) {
            $classValue = $this->classValue($row->consumption_class);
            $key = $this->aggregateKey(
                SerproUsageMonthlyAggregate::SCOPE_TENANT,
                (int) $row->office_id,
                $year,
                $month,
                $row->system_code,
                $row->service_code,
                $classValue,
            );

            SerproUsageMonthlyAggregate::query()->create([
                'scope' => SerproUsageMonthlyAggregate::SCOPE_TENANT,
                'office_id' => (int) $row->office_id,
                'period_year' => $year,
                'period_month' => $month,
                'system_code' => $row->system_code,
                'service_code' => $row->service_code,
                'consumption_class' => $classValue,
                'aggregate_key' => $key,
                'entry_count' => (int) $row->entry_count,
                'total_quantity' => (int) $row->total_quantity,
                'total_estimated_cost_micros' => (int) $row->total_estimated_cost_micros,
                'unknown_class_count' => (int) $row->unknown_class_count,
                'billable_attempt_count' => (int) $row->billable_attempt_count,
                'recomputed_at' => $now,
            ]);
            $tenantRows++;
        }

        $globalRows = 0;
        if ($officeId === null) {
            // Totais globais por service+class
            $globalGroups = SerproApiUsageEntry::query()
                ->withoutGlobalScopes()
                ->whereBetween('occurred_at', [$start, $end])
                ->select([
                    'system_code',
                    'service_code',
                    'consumption_class',
                    DB::raw('COUNT(*) as entry_count'),
                    DB::raw('COALESCE(SUM(quantity), 0) as total_quantity'),
                    DB::raw('COALESCE(SUM(estimated_cost_micros), 0) as total_estimated_cost_micros'),
                    DB::raw("SUM(CASE WHEN consumption_class = 'DESCONHECIDA' THEN 1 ELSE 0 END) as unknown_class_count"),
                    DB::raw('SUM(CASE WHEN is_billable_attempt = 1 THEN 1 ELSE 0 END) as billable_attempt_count'),
                ])
                ->groupBy(['system_code', 'service_code', 'consumption_class'])
                ->get();

            foreach ($globalGroups as $row) {
                $classValue = $this->classValue($row->consumption_class);
                $key = $this->aggregateKey(
                    SerproUsageMonthlyAggregate::SCOPE_GLOBAL,
                    null,
                    $year,
                    $month,
                    $row->system_code,
                    $row->service_code,
                    $classValue,
                );

                SerproUsageMonthlyAggregate::query()->create([
                    'scope' => SerproUsageMonthlyAggregate::SCOPE_GLOBAL,
                    'office_id' => null,
                    'period_year' => $year,
                    'period_month' => $month,
                    'system_code' => $row->system_code,
                    'service_code' => $row->service_code,
                    'consumption_class' => $classValue,
                    'aggregate_key' => $key,
                    'entry_count' => (int) $row->entry_count,
                    'total_quantity' => (int) $row->total_quantity,
                    'total_estimated_cost_micros' => (int) $row->total_estimated_cost_micros,
                    'unknown_class_count' => (int) $row->unknown_class_count,
                    'billable_attempt_count' => (int) $row->billable_attempt_count,
                    'recomputed_at' => $now,
                ]);
                $globalRows++;
            }

            // Total global sintético (todas as classes)
            $total = SerproApiUsageEntry::query()
                ->withoutGlobalScopes()
                ->whereBetween('occurred_at', [$start, $end])
                ->selectRaw('COUNT(*) as entry_count')
                ->selectRaw('COALESCE(SUM(quantity), 0) as total_quantity')
                ->selectRaw('COALESCE(SUM(estimated_cost_micros), 0) as total_estimated_cost_micros')
                ->selectRaw("SUM(CASE WHEN consumption_class = 'DESCONHECIDA' THEN 1 ELSE 0 END) as unknown_class_count")
                ->selectRaw('SUM(CASE WHEN is_billable_attempt = 1 THEN 1 ELSE 0 END) as billable_attempt_count')
                ->first();

            if ($total && (int) $total->entry_count > 0) {
                $key = $this->aggregateKey(
                    SerproUsageMonthlyAggregate::SCOPE_GLOBAL,
                    null,
                    $year,
                    $month,
                    null,
                    null,
                    null,
                );
                SerproUsageMonthlyAggregate::query()->create([
                    'scope' => SerproUsageMonthlyAggregate::SCOPE_GLOBAL,
                    'office_id' => null,
                    'period_year' => $year,
                    'period_month' => $month,
                    'system_code' => null,
                    'service_code' => null,
                    'consumption_class' => null,
                    'aggregate_key' => $key,
                    'entry_count' => (int) $total->entry_count,
                    'total_quantity' => (int) $total->total_quantity,
                    'total_estimated_cost_micros' => (int) $total->total_estimated_cost_micros,
                    'unknown_class_count' => (int) $total->unknown_class_count,
                    'billable_attempt_count' => (int) $total->billable_attempt_count,
                    'recomputed_at' => $now,
                ]);
                $globalRows++;
            }
        }

        return ['tenant_rows' => $tenantRows, 'global_rows' => $globalRows];
    }

    /**
     * Soma estimada interna do mês (todas as offices) a partir do ledger.
     */
    public function internalEstimatedTotalMicros(int $year, int $month): int
    {
        $start = Carbon::create($year, $month, 1)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        return (int) SerproApiUsageEntry::query()
            ->withoutGlobalScopes()
            ->whereBetween('occurred_at', [$start, $end])
            ->whereNotNull('estimated_cost_micros')
            ->sum('estimated_cost_micros');
    }

    public function aggregateKey(
        string $scope,
        ?int $officeId,
        int $year,
        int $month,
        ?string $system,
        ?string $service,
        ?string $class,
    ): string {
        return implode(':', [
            $scope,
            $officeId === null ? '-' : (string) $officeId,
            (string) $year,
            (string) $month,
            $system ?? '-',
            $service ?? '-',
            $class ?? '-',
        ]);
    }

    private function classValue(mixed $class): ?string
    {
        if ($class instanceof SerproConsumptionClass) {
            return $class->value;
        }
        if ($class instanceof \BackedEnum) {
            return (string) $class->value;
        }
        if ($class === null || $class === '') {
            return null;
        }

        return (string) $class;
    }
}
