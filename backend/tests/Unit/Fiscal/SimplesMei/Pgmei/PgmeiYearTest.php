<?php

namespace Tests\Unit\Fiscal\SimplesMei\Pgmei;

use App\Services\Fiscal\SimplesMei\Pgmei\PgmeiYear;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class PgmeiYearTest extends TestCase
{
    public function test_recent_years_are_five_descending(): void
    {
        $now = CarbonImmutable::parse('2026-07-17 12:00:00', 'America/Sao_Paulo');
        $this->assertSame([2026, 2025, 2024, 2023, 2022], PgmeiYear::recentYears($now));
    }

    public function test_daily_cycle_covers_five_years_in_five_days(): void
    {
        $base = CarbonImmutable::parse('2026-01-01 12:00:00', 'America/Sao_Paulo');
        $years = [];
        for ($i = 0; $i < 5; $i++) {
            $years[] = PgmeiYear::yearForDailyCycle($base->addDays($i));
        }
        sort($years);
        $this->assertSame([2022, 2023, 2024, 2025, 2026], $years);
    }
}
