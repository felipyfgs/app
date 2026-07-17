<?php

namespace Tests\Unit\Fiscal\Dctfweb;

use App\Services\Fiscal\Dctfweb\DctfwebPeriod;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DctfwebPeriodTest extends TestCase
{
    #[Test]
    public function expected_pa_is_previous_month_in_office_timezone(): void
    {
        $now = CarbonImmutable::parse('2026-03-15 10:00:00', 'America/Sao_Paulo');
        $pa = DctfwebPeriod::expectedPa($now, 'America/Sao_Paulo');

        $this->assertSame('2026-02', DctfwebPeriod::toPeriodKey($pa));
        $this->assertSame('2026', DctfwebPeriod::toAnoPa($pa));
        $this->assertSame('02', DctfwebPeriod::toMesPa($pa));
    }

    #[Test]
    public function january_rolls_to_previous_year_december(): void
    {
        $now = CarbonImmutable::parse('2026-01-05 08:00:00', 'America/Sao_Paulo');
        $pa = DctfwebPeriod::expectedPa($now, 'America/Sao_Paulo');

        $this->assertSame('2025-12', DctfwebPeriod::toPeriodKey($pa));
    }

    #[Test]
    public function raw_due_date_is_last_day_of_following_month(): void
    {
        $pa = DctfwebPeriod::parse('2026-01');
        $due = DctfwebPeriod::rawDueDate($pa);

        $this->assertSame('2026-02-28', $due->format('Y-m-d'));
    }
}
