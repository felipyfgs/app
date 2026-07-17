<?php

namespace App\Services\Fiscal\SimplesMei\Pgdasd;

use App\Enums\PgdasdDeclarationState;
use App\Models\PgdasdOperation;
use App\Models\TaxDeadlineCalendarVersion;
use App\Models\TaxObligationProjection;
use Carbon\CarbonImmutable;

/**
 * Estado fail-closed da declaração PGDAS-D para o PA esperado.
 */
final class PgdasdDeclarationStateResolver
{
    /**
     * @return array{
     *   state: PgdasdDeclarationState,
     *   calendar_verified: bool,
     *   calendar_version_code: ?string,
     *   due_at: ?CarbonImmutable
     * }
     */
    public function resolve(
        ?PgdasdOperation $declarationForExpectedPa,
        ?CarbonImmutable $lastProductiveConsultedAt,
        ?TaxObligationProjection $projection,
        bool $responseIncomplete = false,
        bool $simulated = false,
    ): array {
        $dueAt = $projection?->due_at instanceof CarbonImmutable
            ? $projection->due_at
            : ($projection?->due_at !== null ? CarbonImmutable::parse($projection->due_at) : null);

        $calendar = null;
        if ($projection?->calendar_version_id !== null) {
            $calendar = TaxDeadlineCalendarVersion::query()->find($projection->calendar_version_id);
        }

        $calendarVerified = $this->isCalendarVerified($calendar);
        $calendarCode = $calendar?->code;

        if ($simulated || $responseIncomplete || $lastProductiveConsultedAt === null) {
            return $this->pack(PgdasdDeclarationState::Unverified, $calendarVerified, $calendarCode, $dueAt);
        }

        if ($declarationForExpectedPa !== null) {
            return $this->pack(PgdasdDeclarationState::Current, $calendarVerified, $calendarCode, $dueAt);
        }

        // Ausência confirmada por consulta produtiva
        if ($dueAt === null) {
            return $this->pack(PgdasdDeclarationState::Unverified, $calendarVerified, $calendarCode, $dueAt);
        }

        $now = CarbonImmutable::now();
        if ($now->lessThanOrEqualTo($dueAt)) {
            return $this->pack(PgdasdDeclarationState::DueWithinDeadline, $calendarVerified, $calendarCode, $dueAt);
        }

        // Atraso só se consulta produtiva posterior ao vencimento E calendário verificado
        if ($calendarVerified && $lastProductiveConsultedAt->greaterThan($dueAt)) {
            return $this->pack(PgdasdDeclarationState::OverdueNotFound, $calendarVerified, $calendarCode, $dueAt);
        }

        return $this->pack(PgdasdDeclarationState::Unverified, $calendarVerified, $calendarCode, $dueAt);
    }

    private function isCalendarVerified(?TaxDeadlineCalendarVersion $calendar): bool
    {
        if ($calendar === null) {
            return false;
        }

        $meta = is_array($calendar->metadata) ? $calendar->metadata : [];
        $verification = strtoupper((string) ($meta['verification'] ?? $meta['status'] ?? ''));
        if ($verification === 'VERIFIED') {
            return true;
        }

        // Flag explícita em coluna metadata.verified
        if (($meta['verified'] ?? false) === true) {
            return true;
        }

        return false;
    }

    /**
     * @return array{
     *   state: PgdasdDeclarationState,
     *   calendar_verified: bool,
     *   calendar_version_code: ?string,
     *   due_at: ?CarbonImmutable
     * }
     */
    private function pack(
        PgdasdDeclarationState $state,
        bool $calendarVerified,
        ?string $calendarCode,
        ?CarbonImmutable $dueAt,
    ): array {
        return [
            'state' => $state,
            'calendar_verified' => $calendarVerified,
            'calendar_version_code' => $calendarCode,
            'due_at' => $dueAt,
        ];
    }
}
