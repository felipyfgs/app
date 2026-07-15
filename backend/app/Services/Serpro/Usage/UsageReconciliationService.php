<?php

namespace App\Services\Serpro\Usage;

use App\Enums\SerproReconciliationStatus;
use App\Models\SerproUsageReconciliation;
use App\Models\SerproUsageReconciliationAdjustment;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Importa/registra fatura oficial SERPRO e mantém diferenças em registros próprios.
 * Nunca reescreve serpro_api_usage_entries.
 */
final class UsageReconciliationService
{
    public function __construct(
        private readonly UsageAggregationService $aggregates,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  list<array{
     *     office_id?: int|null,
     *     service_code?: string|null,
     *     consumption_class?: string|null,
     *     amount_micros: int,
     *     reason: string,
     *     notes?: string|null
     * }>  $adjustments
     */
    public function registerOfficialInvoice(
        int $year,
        int $month,
        int $officialTotalCostMicros,
        ?string $officialReference = null,
        ?string $officialSource = null,
        ?string $notes = null,
        ?int $importedByUserId = null,
        array $adjustments = [],
        ?string $differenceCause = null,
        bool $recomputeAggregates = true,
    ): SerproUsageReconciliation {
        if ($recomputeAggregates) {
            $this->aggregates->recomputeMonth($year, $month);
        }

        $internal = $this->aggregates->internalEstimatedTotalMicros($year, $month);
        $difference = $officialTotalCostMicros - $internal;

        $status = match (true) {
            $difference === 0 && $adjustments === [] => SerproReconciliationStatus::Matched,
            $adjustments !== [] => SerproReconciliationStatus::Adjusted,
            default => SerproReconciliationStatus::Divergent,
        };

        return DB::transaction(function () use (
            $year,
            $month,
            $officialTotalCostMicros,
            $officialReference,
            $officialSource,
            $notes,
            $importedByUserId,
            $adjustments,
            $differenceCause,
            $internal,
            $difference,
            $status,
        ): SerproUsageReconciliation {
            $recon = SerproUsageReconciliation::query()->create([
                'period_year' => $year,
                'period_month' => $month,
                'official_reference' => $officialReference,
                'official_source' => $officialSource ?? 'SERPRO_INVOICE',
                'official_total_cost_micros' => $officialTotalCostMicros,
                'internal_total_estimated_cost_micros' => $internal,
                'difference_micros' => $difference,
                'status' => $status,
                'difference_cause' => $differenceCause
                    ?? ($difference === 0 ? null : 'PENDING_REVIEW'),
                'notes' => $notes,
                'imported_by_user_id' => $importedByUserId,
                'imported_at' => now(),
            ]);

            foreach ($adjustments as $adj) {
                SerproUsageReconciliationAdjustment::query()->create([
                    'reconciliation_id' => $recon->id,
                    'office_id' => $adj['office_id'] ?? null,
                    'service_code' => $adj['service_code'] ?? null,
                    'consumption_class' => $adj['consumption_class'] ?? null,
                    'amount_micros' => (int) $adj['amount_micros'],
                    'reason' => $adj['reason'],
                    'notes' => $adj['notes'] ?? null,
                    'created_at' => now(),
                ]);
            }

            $this->audit->record(
                action: 'serpro.usage.reconciliation_registered',
                result: $status->value,
                subject: $recon,
                context: [
                    'period_year' => $year,
                    'period_month' => $month,
                    'difference_micros' => $difference,
                    'official_reference' => $officialReference,
                    'adjustments_count' => count($adjustments),
                ],
                userId: $importedByUserId,
            );

            return $recon->load('adjustments');
        });
    }
}
