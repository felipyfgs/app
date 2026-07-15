<?php

namespace App\Services\Fiscal\SimplesMei;

use App\DTO\Fiscal\FiscalAdapterResult;
use App\DTO\Fiscal\SimplesMei\SimplesMeiOperationDef;
use App\Enums\FiscalCoverage;
use App\Enums\FiscalFindingSeverity;
use App\Enums\FiscalRunResult;
use App\Enums\FiscalSituation;
use App\Enums\TaxRegimeCode;
use App\Models\Client;
use App\Models\ClientTaxRegimePeriod;
use App\Models\FiscalCategory;
use App\Models\FiscalCompetence;
use App\Models\Office;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Projeta regime por vigência e impede misturar competências SN ↔ MEI.
 */
final class RegimeApplicabilityService
{
    /**
     * Bloqueia operação se regime da competência for incompatível com o serviço.
     */
    public function assertOperationApplicable(
        Office $office,
        Client $client,
        SimplesMeiOperationDef $def,
        ?string $periodKey,
    ): ?FiscalAdapterResult {
        // REGIME_APURACAO sempre aplicável (é a fonte do regime)
        if (strtoupper($def->serviceCode) === 'REGIME_APURACAO') {
            return null;
        }

        if ($periodKey === null || $periodKey === '') {
            // Sem competência: permite consulta pontual (CCMEI etc.)
            return null;
        }

        $regimeAt = $this->regimeForPeriod($office, $client, $periodKey);
        if ($regimeAt === null || $regimeAt === TaxRegimeCode::Unknown) {
            // Sem projeção: não inventa — permite consulta (fonte dirá); não marca NOT_APPLICABLE
            return null;
        }

        if (! $regimeAt->compatibleWith($def->regimeFamily)) {
            return new FiscalAdapterResult(
                result: FiscalRunResult::Success,
                situation: FiscalSituation::NotApplicable,
                coverage: FiscalCoverage::NotApplicable,
                evidenceBytes: json_encode([
                    'not_applicable' => true,
                    'reason' => 'REGIME_MISMATCH',
                    'regime_at_period' => $regimeAt->value,
                    'operation_regime_family' => $def->regimeFamily->value,
                    'period_key' => $periodKey,
                ], JSON_THROW_ON_ERROR),
                normalized: [
                    'situation' => FiscalSituation::NotApplicable->value,
                    'regime_at_period' => $regimeAt->value,
                    'operation_regime_family' => $def->regimeFamily->value,
                    'reason' => 'REGIME_MISMATCH',
                ],
                findings: [[
                    'code' => 'REGIME_MISMATCH',
                    'severity' => FiscalFindingSeverity::Info->value,
                    'title' => 'Operação não aplicável ao regime da competência',
                    'detail' => "Regime {$regimeAt->value} ≠ família {$def->regimeFamily->value}",
                    'situation' => FiscalSituation::NotApplicable->value,
                    'creates_pending' => false,
                ]],
            );
        }

        return null;
    }

    public function regimeForPeriod(Office $office, Client $client, string $periodKey): ?TaxRegimeCode
    {
        $anchor = $this->periodAnchorDate($periodKey);
        if ($anchor === null) {
            return null;
        }

        $row = ClientTaxRegimePeriod::query()
            ->withoutGlobalScopes()
            ->where('office_id', $office->id)
            ->where('client_id', $client->id)
            ->where('effective_from', '<=', $anchor)
            ->where(function ($q) use ($anchor): void {
                $q->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', $anchor);
            })
            ->orderByDesc('effective_from')
            ->first();

        return $row?->regime_code;
    }

    /**
     * @param  array<string, mixed>  $normalized  saída de RegimeApuracaoDto::toNormalized()
     */
    public function projectFromNormalized(
        Office $office,
        Client $client,
        array $normalized,
        ?int $sourceRunId = null,
    ): void {
        $periods = is_array($normalized['periods'] ?? null) ? $normalized['periods'] : [];
        if ($periods === []) {
            $current = TaxRegimeCode::tryFrom((string) ($normalized['current_regime'] ?? ''))
                ?? TaxRegimeCode::Unknown;
            if ($current !== TaxRegimeCode::Unknown) {
                $this->upsertPeriod(
                    $office,
                    $client,
                    $current,
                    CarbonImmutable::now()->startOfMonth(),
                    null,
                    $sourceRunId,
                );
            }

            return;
        }

        foreach ($periods as $p) {
            if (! is_array($p)) {
                continue;
            }
            $code = TaxRegimeCode::tryFrom((string) ($p['regime'] ?? '')) ?? TaxRegimeCode::Unknown;
            if ($code === TaxRegimeCode::Unknown) {
                continue;
            }
            $from = CarbonImmutable::parse((string) $p['effective_from'])->startOfDay();
            $to = ! empty($p['effective_to'])
                ? CarbonImmutable::parse((string) $p['effective_to'])->endOfDay()
                : null;
            $this->upsertPeriod($office, $client, $code, $from, $to, $sourceRunId);
        }

        // Atualiza tax_regime do cliente com o vigente atual (somente exibição; fonte de verdade = períodos)
        $current = $this->regimeForPeriod($office, $client, now()->format('Y-m'));
        if ($current !== null && $current !== TaxRegimeCode::Unknown) {
            $client->forceFill(['tax_regime' => $current->value])->save();
        }
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function projectCompetenceSituation(
        Office $office,
        Client $client,
        SimplesMeiOperationDef $def,
        string $periodKey,
        FiscalSituation $situation,
        FiscalCoverage $coverage,
        ?array $metadata = null,
    ): ?FiscalCompetence {
        if ($periodKey === '') {
            return null;
        }

        // Não projetar obrigação SN em categoria MEI e vice-versa
        $categoryCode = $def->regimeFamily->fiscalCategoryCode();
        if ($categoryCode === null) {
            return null;
        }

        $category = FiscalCategory::query()->where('code', $categoryCode)->first();
        if ($category === null) {
            return null;
        }

        [$year, $month] = $this->parsePeriodKey($periodKey);

        $competence = FiscalCompetence::query()
            ->withoutGlobalScopes()
            ->firstOrNew([
                'office_id' => $office->id,
                'client_id' => $client->id,
                'fiscal_category_id' => $category->id,
                'period_key' => $periodKey,
            ]);

        $competence->fill([
            'period_year' => $year,
            'period_month' => $month,
            'situation' => $situation,
            'coverage' => $coverage,
            'metadata' => array_merge($competence->metadata ?? [], [
                'service_code' => $def->serviceCode,
                'operation_code' => $def->operationCode,
                'regime_family' => $def->regimeFamily->value,
                'last_normalized' => $metadata,
            ]),
        ]);
        $competence->save();

        return $competence;
    }

    /**
     * @return Collection<int, ClientTaxRegimePeriod>
     */
    public function listPeriods(Office $office, Client $client): Collection
    {
        return ClientTaxRegimePeriod::query()
            ->withoutGlobalScopes()
            ->where('office_id', $office->id)
            ->where('client_id', $client->id)
            ->orderBy('effective_from')
            ->get();
    }

    private function upsertPeriod(
        Office $office,
        Client $client,
        TaxRegimeCode $regime,
        CarbonImmutable $from,
        ?CarbonImmutable $to,
        ?int $sourceRunId,
    ): ClientTaxRegimePeriod {
        return ClientTaxRegimePeriod::query()->updateOrCreate(
            [
                'office_id' => $office->id,
                'client_id' => $client->id,
                'regime_code' => $regime->value,
                'effective_from' => $from->toDateString(),
            ],
            [
                'effective_to' => $to?->toDateString(),
                'source_system' => 'INTEGRA_SN',
                'source_service' => 'REGIME_APURACAO',
                'source_run_id' => $sourceRunId,
                'observed_at' => CarbonImmutable::now(),
            ],
        );
    }

    private function periodAnchorDate(string $periodKey): ?CarbonImmutable
    {
        if (preg_match('/^(\d{4})-(\d{2})$/', $periodKey, $m)) {
            return CarbonImmutable::create((int) $m[1], (int) $m[2], 15);
        }
        if (preg_match('/^(\d{4})$/', $periodKey, $m)) {
            return CarbonImmutable::create((int) $m[1], 6, 30);
        }

        try {
            return CarbonImmutable::parse($periodKey);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array{0: int, 1: int|null}
     */
    private function parsePeriodKey(string $periodKey): array
    {
        if (preg_match('/^(\d{4})-(\d{2})$/', $periodKey, $m)) {
            return [(int) $m[1], (int) $m[2]];
        }
        if (preg_match('/^(\d{4})$/', $periodKey, $m)) {
            return [(int) $m[1], null];
        }

        return [(int) now()->format('Y'), null];
    }
}
