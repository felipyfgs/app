<?php

namespace App\Services\Serpro\Usage;

use App\Enums\SerproConsumptionClass;
use App\Enums\SerproUsageReservationStatus;
use App\Models\OfficeSubscription;
use App\Models\SerproApiUsageEntry;
use App\Models\SerproApiUsageReservation;
use Illuminate\Support\Carbon;

/**
 * Franquia por tenant, limiares, saldo e proteção contra tenant ruidoso.
 *
 * Conta tentativas faturáveis do mês (entries + reservas abertas).
 * Não inclui CNPJ em métricas.
 */
final class UsageBudgetGate
{
    public const BLOCK_FRANCHISE = 'FRANCHISE_EXCEEDED';

    public const BLOCK_GLOBAL = 'GLOBAL_BUDGET_EXCEEDED';

    public const BLOCK_NOISY_TENANT = 'NOISY_TENANT_SHARE';

    public function __construct(
        private readonly UsageShadowPolicy $shadow,
    ) {}

    /**
     * @return array{
     *     allowed: bool,
     *     would_block: bool,
     *     block_reason: string|null,
     *     period_year: int,
     *     period_month: int,
     *     used_quantity: int,
     *     reserved_open_quantity: int,
     *     franchise_quota: int|null,
     *     remaining: int|null,
     *     franchise_ratio: float|null,
     *     alert_threshold_reached: bool,
     *     global_used: int,
     *     global_budget: int|null,
     *     tenant_share_ratio: float|null
     * }
     */
    public function evaluate(
        int $officeId,
        SerproConsumptionClass $class,
        int $quantity,
        bool $isEssential,
        Carbon|string|null $at = null,
    ): array {
        $at = $at instanceof Carbon ? $at : ($at ? Carbon::parse($at) : now());
        $year = (int) $at->year;
        $month = (int) $at->month;

        $used = $this->billableQuantityForOffice($officeId, $year, $month);
        $openReserved = $this->openReservedQuantityForOffice($officeId, $year, $month);
        $projected = $used + $openReserved + max(0, $quantity);

        $franchise = $this->franchiseQuota($officeId);
        $remaining = $franchise === null ? null : max(0, $franchise - $used - $openReserved);
        $ratio = ($franchise !== null && $franchise > 0)
            ? ($used + $openReserved) / $franchise
            : null;

        $alertThreshold = (float) config('serpro_usage.franchise_alert_threshold', 0.8);
        $alertReached = $ratio !== null && $ratio >= $alertThreshold;

        $globalBudget = config('serpro_usage.global_monthly_budget');
        $globalBudget = $globalBudget === null ? null : (int) $globalBudget;
        $globalUsed = $this->billableQuantityGlobal($year, $month) + $this->openReservedQuantityGlobal($year, $month);

        $maxShare = config('serpro_usage.max_tenant_share_of_global');
        $maxShare = $maxShare === null ? null : (float) $maxShare;
        $tenantShareRatio = ($globalBudget !== null && $globalBudget > 0)
            ? ($used + $openReserved) / $globalBudget
            : null;

        $blockReason = null;

        // NAO_FATURAVEL não consome franquia
        if ($class === SerproConsumptionClass::NaoFaturavel) {
            return $this->result(
                allowed: true,
                wouldBlock: false,
                blockReason: null,
                year: $year,
                month: $month,
                used: $used,
                openReserved: $openReserved,
                franchise: $franchise,
                remaining: $remaining,
                ratio: $ratio,
                alertReached: $alertReached,
                globalUsed: $globalUsed,
                globalBudget: $globalBudget,
                tenantShareRatio: $tenantShareRatio,
            );
        }

        if ($franchise !== null && $projected > $franchise) {
            $blockReason = self::BLOCK_FRANCHISE;
        }

        if ($blockReason === null && $globalBudget !== null && ($globalUsed + max(0, $quantity)) > $globalBudget) {
            $blockReason = self::BLOCK_GLOBAL;
        }

        if (
            $blockReason === null
            && $globalBudget !== null
            && $maxShare !== null
            && $maxShare > 0
            && ($used + $openReserved + max(0, $quantity)) > (int) floor($globalBudget * $maxShare)
        ) {
            $blockReason = self::BLOCK_NOISY_TENANT;
        }

        $wouldBlock = $blockReason !== null && ! $isEssential;
        // Essenciais não são bloqueadas por franquia (só alertadas).
        $allowed = ! $wouldBlock || ! $this->shadow->isCommercialBlockingEnabled();

        // Com blocking efetivo: não essenciais bloqueadas; essenciais passam.
        if ($this->shadow->isCommercialBlockingEnabled() && $wouldBlock) {
            $allowed = false;
        } elseif ($this->shadow->isCommercialBlockingEnabled() && $blockReason !== null && $isEssential) {
            $allowed = true;
            $wouldBlock = false;
        } else {
            // Shadow / blocking off: sempre allowed; would_block sinaliza o que teria acontecido.
            $allowed = true;
        }

        return $this->result(
            allowed: $allowed,
            wouldBlock: $blockReason !== null && ! $isEssential,
            blockReason: $blockReason,
            year: $year,
            month: $month,
            used: $used,
            openReserved: $openReserved,
            franchise: $franchise,
            remaining: $remaining,
            ratio: $ratio,
            alertReached: $alertReached || ($blockReason !== null),
            globalUsed: $globalUsed,
            globalBudget: $globalBudget,
            tenantShareRatio: $tenantShareRatio,
        );
    }

    public function franchiseQuota(int $officeId): ?int
    {
        $sub = OfficeSubscription::query()->where('office_id', $officeId)->first();

        if ($sub === null) {
            return null;
        }

        return $sub->monthly_api_quota;
    }

    public function billableQuantityForOffice(int $officeId, int $year, int $month): int
    {
        $start = Carbon::create($year, $month, 1)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        return (int) SerproApiUsageEntry::query()
            ->withoutGlobalScopes()
            ->where('office_id', $officeId)
            ->where('is_billable_attempt', true)
            ->where('consumption_class', '!=', SerproConsumptionClass::NaoFaturavel->value)
            ->whereBetween('occurred_at', [$start, $end])
            ->sum('quantity');
    }

    public function openReservedQuantityForOffice(int $officeId, int $year, int $month): int
    {
        $start = Carbon::create($year, $month, 1)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        return (int) SerproApiUsageReservation::query()
            ->withoutGlobalScopes()
            ->where('office_id', $officeId)
            ->where('status', SerproUsageReservationStatus::Reserved->value)
            ->where('consumption_class', '!=', SerproConsumptionClass::NaoFaturavel->value)
            ->whereBetween('reserved_at', [$start, $end])
            ->sum('quantity');
    }

    public function billableQuantityGlobal(int $year, int $month): int
    {
        $start = Carbon::create($year, $month, 1)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        return (int) SerproApiUsageEntry::query()
            ->withoutGlobalScopes()
            ->where('is_billable_attempt', true)
            ->where('consumption_class', '!=', SerproConsumptionClass::NaoFaturavel->value)
            ->whereBetween('occurred_at', [$start, $end])
            ->sum('quantity');
    }

    public function openReservedQuantityGlobal(int $year, int $month): int
    {
        $start = Carbon::create($year, $month, 1)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        return (int) SerproApiUsageReservation::query()
            ->withoutGlobalScopes()
            ->where('status', SerproUsageReservationStatus::Reserved->value)
            ->where('consumption_class', '!=', SerproConsumptionClass::NaoFaturavel->value)
            ->whereBetween('reserved_at', [$start, $end])
            ->sum('quantity');
    }

    /**
     * Snapshot de consumo/franquia do tenant (sem dados de outros offices).
     *
     * @return array<string, mixed>
     */
    public function tenantSnapshot(int $officeId, Carbon|string|null $at = null): array
    {
        $at = $at instanceof Carbon ? $at : ($at ? Carbon::parse($at) : now());
        $eval = $this->evaluate(
            officeId: $officeId,
            class: SerproConsumptionClass::Consulta,
            quantity: 0,
            isEssential: true,
            at: $at,
        );

        $cost = (int) SerproApiUsageEntry::query()
            ->withoutGlobalScopes()
            ->where('office_id', $officeId)
            ->whereYear('occurred_at', $eval['period_year'])
            ->whereMonth('occurred_at', $eval['period_month'])
            ->whereNotNull('estimated_cost_micros')
            ->sum('estimated_cost_micros');

        return [
            'office_id' => $officeId,
            'period_year' => $eval['period_year'],
            'period_month' => $eval['period_month'],
            'used_quantity' => $eval['used_quantity'],
            'reserved_open_quantity' => $eval['reserved_open_quantity'],
            'franchise_quota' => $eval['franchise_quota'],
            'remaining' => $eval['remaining'],
            'franchise_ratio' => $eval['franchise_ratio'],
            'alert_threshold_reached' => $eval['alert_threshold_reached'],
            'estimated_cost_micros' => $cost,
            'policy' => $this->shadow->snapshot(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function result(
        bool $allowed,
        bool $wouldBlock,
        ?string $blockReason,
        int $year,
        int $month,
        int $used,
        int $openReserved,
        ?int $franchise,
        ?int $remaining,
        ?float $ratio,
        bool $alertReached,
        int $globalUsed,
        ?int $globalBudget,
        ?float $tenantShareRatio,
    ): array {
        return [
            'allowed' => $allowed,
            'would_block' => $wouldBlock,
            'block_reason' => $blockReason,
            'period_year' => $year,
            'period_month' => $month,
            'used_quantity' => $used,
            'reserved_open_quantity' => $openReserved,
            'franchise_quota' => $franchise,
            'remaining' => $remaining,
            'franchise_ratio' => $ratio,
            'alert_threshold_reached' => $alertReached,
            'global_used' => $globalUsed,
            'global_budget' => $globalBudget,
            'tenant_share_ratio' => $tenantShareRatio,
        ];
    }
}
