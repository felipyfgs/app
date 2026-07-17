<?php

namespace App\Services\Fiscal\SimplesMei\Pgdasd;

use Carbon\CarbonImmutable;

/**
 * Ajuste de próximo dia bancário (NEXT_BANKING_BUSINESS_DAY).
 * Sem feriados oficiais verificados: só fim de semana → NÃO marca calendário como VERIFIED.
 */
final class PgdasdBankingCalendar
{
    public const ADJUSTMENT = 'NEXT_BANKING_BUSINESS_DAY';

    /**
     * Avança para o próximo dia útil (seg–sex). Não conhece feriados nacionais.
     */
    public function nextBankingBusinessDay(CarbonImmutable $date): CarbonImmutable
    {
        $cursor = $date->startOfDay();
        while ($cursor->isWeekend()) {
            $cursor = $cursor->addDay();
        }

        return $cursor;
    }

    /**
     * Aplica ajuste se configurado; retorna se o resultado é confiável para OVERDUE.
     *
     * @return array{date: CarbonImmutable, verified: bool, reason: string}
     */
    public function applyAdjustment(CarbonImmutable $rawDue, string $adjustment): array
    {
        $adj = strtoupper(trim($adjustment));
        if ($adj === 'NONE' || $adj === '') {
            return [
                'date' => $rawDue,
                'verified' => false,
                'reason' => 'NO_OFFICIAL_HOLIDAY_SOURCE',
            ];
        }

        if ($adj === self::ADJUSTMENT || $adj === 'NEXT_BUSINESS_DAY') {
            $adjusted = $this->nextBankingBusinessDay($rawDue);

            return [
                'date' => $adjusted,
                'verified' => false, // feriados oficiais não verificados
                'reason' => 'WEEKEND_ONLY_NO_HOLIDAY_CALENDAR',
            ];
        }

        return [
            'date' => $rawDue,
            'verified' => false,
            'reason' => 'UNKNOWN_ADJUSTMENT',
        ];
    }
}
