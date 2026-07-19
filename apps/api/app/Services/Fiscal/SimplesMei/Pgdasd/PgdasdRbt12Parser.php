<?php

namespace App\Services\Fiscal\SimplesMei\Pgdasd;

use App\Enums\PgdasdRbt12Status;

/** Parser versionado e fail-closed do RBT12 contido no extrato oficial. */
final class PgdasdRbt12Parser
{
    public const VERSION = 'pgdasd-rbt12-v1';

    /**
     * @return array{status:PgdasdRbt12Status,total_cents:?int,internal_market_cents:?int,external_market_cents:?int,parser_version:string,reason:?string}
     */
    public function parse(string $text, string $periodoApuracao): array
    {
        if (trim($text) === '') {
            return $this->result(PgdasdRbt12Status::NotFound, reason: 'EMPTY_TEXT');
        }
        if (preg_match('/^\d{6}$/', $periodoApuracao) !== 1) {
            return $this->result(PgdasdRbt12Status::Failed, reason: 'INVALID_EXPECTED_PERIOD');
        }
        if (! $this->containsExpectedPeriod($text, $periodoApuracao)) {
            return $this->result(PgdasdRbt12Status::Ambiguous, reason: 'PERIOD_MISMATCH');
        }

        $totalCandidates = [];
        $internalCandidates = [];
        $externalCandidates = [];

        foreach (preg_split('/\R/u', $text) ?: [] as $line) {
            if (preg_match('/\bRBT12\b/iu', $line) !== 1
                || preg_match('/proporcionalizad[oa]/iu', $line) === 1) {
                continue;
            }

            $internal = $this->moneyAfterLabel($line, '/mercado\s+interno/iu');
            $external = $this->moneyAfterLabel($line, '/mercado\s+externo/iu');
            $total = $this->moneyAfterLabel($line, '/\btotal\b/iu');
            if ($internal !== null) {
                $internalCandidates[] = $internal;
            }
            if ($external !== null) {
                $externalCandidates[] = $external;
            }
            if ($total !== null) {
                $totalCandidates[] = $total;

                continue;
            }

            $values = $this->moneyValues($line);
            if (count($values) === 1) {
                $totalCandidates[] = $values[0];
            } elseif (count($values) === 3 && $internal !== null && $external !== null) {
                $totalCandidates[] = $values[2];
            }
        }

        $totals = array_values(array_unique($totalCandidates));
        $internals = array_values(array_unique($internalCandidates));
        $externals = array_values(array_unique($externalCandidates));
        if (count($totals) > 1 || count($internals) > 1 || count($externals) > 1) {
            return $this->result(PgdasdRbt12Status::Ambiguous, reason: 'CONFLICTING_VALUES');
        }

        $internal = $internals[0] ?? null;
        $external = $externals[0] ?? null;
        $total = $totals[0] ?? null;
        if ($total === null && $internal !== null && $external !== null) {
            $total = $internal + $external;
        }
        if ($total === null) {
            return $this->result(PgdasdRbt12Status::NotFound, reason: 'EXACT_RBT12_VALUE_NOT_FOUND');
        }
        if ($internal !== null && $external !== null && $internal + $external !== $total) {
            return $this->result(PgdasdRbt12Status::Ambiguous, reason: 'COMPOSITION_MISMATCH');
        }

        return $this->result(PgdasdRbt12Status::Parsed, $total, $internal, $external);
    }

    private function containsExpectedPeriod(string $text, string $pa): bool
    {
        $year = substr($pa, 0, 4);
        $month = substr($pa, 4, 2);
        foreach ([$pa, "{$month}/{$year}", "{$year}/{$month}", "{$month}-{$year}", "{$year}-{$month}"] as $candidate) {
            if (str_contains($text, $candidate)) {
                return true;
            }
        }

        return false;
    }

    private function moneyAfterLabel(string $line, string $labelPattern): ?int
    {
        if (preg_match($labelPattern, $line, $label, PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }
        $tail = substr($line, $label[0][1] + strlen($label[0][0]));
        $values = $this->moneyValues($tail);

        return $values[0] ?? null;
    }

    /** @return list<int> */
    private function moneyValues(string $value): array
    {
        preg_match_all('/(?<!\d)(?:R\$\s*)?(\d{1,3}(?:\.\d{3})*,\d{2}|\d+,\d{2}|\d+\.\d{2})(?!\d)/u', $value, $matches);
        $out = [];
        foreach ($matches[1] ?? [] as $raw) {
            $cents = $this->toCents((string) $raw);
            if ($cents !== null) {
                $out[] = $cents;
            }
        }

        return $out;
    }

    private function toCents(string $value): ?int
    {
        $value = trim($value);
        if (str_contains($value, ',')) {
            [$integer, $decimal] = array_pad(explode(',', $value, 2), 2, '00');
            $integer = str_replace('.', '', $integer);
        } else {
            [$integer, $decimal] = array_pad(explode('.', $value, 2), 2, '00');
        }
        if (preg_match('/^\d+$/', $integer) !== 1 || preg_match('/^\d{2}$/', $decimal) !== 1) {
            return null;
        }
        $cents = ltrim($integer, '0').$decimal;
        $cents = ltrim($cents, '0');
        if ($cents === '') {
            return 0;
        }
        if (strlen($cents) > 18 || (strlen($cents) === 18 && strcmp($cents, (string) PHP_INT_MAX) > 0)) {
            return null;
        }

        return (int) $cents;
    }

    /**
     * @return array{status:PgdasdRbt12Status,total_cents:?int,internal_market_cents:?int,external_market_cents:?int,parser_version:string,reason:?string}
     */
    private function result(
        PgdasdRbt12Status $status,
        ?int $total = null,
        ?int $internal = null,
        ?int $external = null,
        ?string $reason = null,
    ): array {
        return [
            'status' => $status,
            'total_cents' => $total,
            'internal_market_cents' => $internal,
            'external_market_cents' => $external,
            'parser_version' => self::VERSION,
            'reason' => $reason,
        ];
    }
}
