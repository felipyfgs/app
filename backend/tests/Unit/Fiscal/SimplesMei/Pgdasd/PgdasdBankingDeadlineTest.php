<?php

namespace Tests\Unit\Fiscal\SimplesMei\Pgdasd;

use App\Models\TaxDeadlineCalendarVersion;
use App\Models\TaxDeadlineRule;
use App\Models\TaxObligationDefinition;
use App\Services\Fiscal\Declarations\TaxDeadlineCalendarService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PgdasdBankingDeadlineTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function applies_weekend_and_versioned_holiday_to_pgdasd_deadline(): void
    {
        $obligation = TaxObligationDefinition::query()->where('code', 'PGDAS_D')->firstOrFail();
        $calendar = TaxDeadlineCalendarVersion::query()->create([
            'code' => 'PGDASD_TEST_OFFICIAL',
            'version' => 1,
            'label' => 'Calendário oficial de teste',
            'timezone' => 'America/Sao_Paulo',
            'effective_from' => CarbonImmutable::parse('2026-01-01'),
            'is_current' => true,
            'source_ref' => 'https://www.gov.br/fonte-oficial',
            'metadata' => [
                'verification' => 'VERIFIED',
                'official_source' => true,
                'non_business_dates' => ['2026-06-22'],
            ],
        ]);
        TaxDeadlineRule::query()->create([
            'calendar_version_id' => $calendar->id,
            'obligation_definition_id' => $obligation->id,
            'period_granularity' => 'MONTHLY',
            'due_day' => 20,
            'due_month_offset' => 1,
            'business_day_adjustment' => 'NEXT_BUSINESS_DAY',
            'timezone' => 'America/Sao_Paulo',
        ]);

        // 20/06/2026 é sábado; 22/06 foi marcado feriado, logo vence terça 23/06.
        $calculation = app(TaxDeadlineCalendarService::class)->calculateDue(
            $obligation,
            '2026-05',
            2026,
            5,
            $calendar,
        );

        $this->assertSame('2026-06-23', $calculation['due_at']?->format('Y-m-d'));
        $this->assertSame('2026-06-20', substr((string) $calculation['snapshot']['raw_due_at'], 0, 10));
        $this->assertTrue($calculation['snapshot']['calendar_verified']);
        $this->assertSame('VERSIONED_BANKING_CALENDAR', $calculation['snapshot']['business_day_adjustment_reason']);
    }

    #[Test]
    public function default_cloned_calendar_adjusts_weekend_but_remains_unverified(): void
    {
        $obligation = TaxObligationDefinition::query()->where('code', 'PGDAS_D')->firstOrFail();
        $calculation = app(TaxDeadlineCalendarService::class)->calculateDue(
            $obligation,
            '2026-05',
            2026,
            5,
        );

        $this->assertSame('2026-06-22', $calculation['due_at']?->format('Y-m-d'));
        $this->assertFalse($calculation['snapshot']['calendar_verified']);
        $this->assertSame('WEEKEND_ONLY_UNVERIFIED_HOLIDAYS', $calculation['snapshot']['business_day_adjustment_reason']);
    }
}
