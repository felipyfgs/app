<?php

namespace Tests\Unit\Fiscal\SimplesMei\Pgdasd;

use App\Enums\PgdasdDeclarationState;
use App\Enums\PgdasdOperationKind;
use App\Models\PgdasdOperation;
use App\Models\TaxDeadlineCalendarVersion;
use App\Models\TaxObligationProjection;
use App\Services\Fiscal\SimplesMei\Pgdasd\PgdasdDeclarationStateResolver;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PgdasdDeclarationStateResolverTest extends TestCase
{
    use RefreshDatabase;

    private PgdasdDeclarationStateResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new PgdasdDeclarationStateResolver;
    }

    #[Test]
    public function current_when_declaration_for_pa(): void
    {
        $decl = new PgdasdOperation([
            'kind' => PgdasdOperationKind::Declaration,
            'declaration_number' => '1',
        ]);
        $pack = $this->resolver->resolve(
            $decl,
            CarbonImmutable::now(),
            null,
            false,
            false,
        );
        $this->assertSame(PgdasdDeclarationState::Current, $pack['state']);
    }

    #[Test]
    public function unverified_when_simulated(): void
    {
        $pack = $this->resolver->resolve(null, CarbonImmutable::now(), null, false, true);
        $this->assertSame(PgdasdDeclarationState::Unverified, $pack['state']);
    }

    #[Test]
    public function due_within_deadline_when_absent_before_due(): void
    {
        $proj = new TaxObligationProjection([
            'due_at' => CarbonImmutable::now()->addDays(5),
        ]);
        $pack = $this->resolver->resolve(
            null,
            CarbonImmutable::now(),
            $proj,
            false,
            false,
        );
        $this->assertSame(PgdasdDeclarationState::DueWithinDeadline, $pack['state']);
    }

    #[Test]
    public function overdue_requires_verified_calendar(): void
    {
        $cal = TaxDeadlineCalendarVersion::query()->create([
            'code' => 'TEST_PGDASD',
            'version' => 1,
            'label' => 'Test',
            'timezone' => 'America/Sao_Paulo',
            'effective_from' => CarbonImmutable::now()->subYear(),
            'is_current' => true,
            'source_ref' => 'https://www.gov.br/receitafederal/calendario-oficial',
            'metadata' => ['verification' => 'VERIFIED'],
        ]);

        $proj = new TaxObligationProjection([
            'due_at' => CarbonImmutable::now()->subDays(3),
            'calendar_version_id' => $cal->id,
        ]);

        $pack = $this->resolver->resolve(
            null,
            CarbonImmutable::now(),
            $proj,
            false,
            false,
        );
        $this->assertSame(PgdasdDeclarationState::OverdueNotFound, $pack['state']);
        $this->assertTrue($pack['calendar_verified']);
    }

    #[Test]
    public function overdue_without_verified_calendar_is_unverified(): void
    {
        $proj = new TaxObligationProjection([
            'due_at' => CarbonImmutable::now()->subDays(3),
        ]);
        $pack = $this->resolver->resolve(
            null,
            CarbonImmutable::now(),
            $proj,
            false,
            false,
        );
        $this->assertSame(PgdasdDeclarationState::Unverified, $pack['state']);
    }

    #[Test]
    public function overdue_with_unverified_banking_adjustment_remains_unverified(): void
    {
        $calendar = TaxDeadlineCalendarVersion::query()->create([
            'code' => 'TEST_PGDASD_ADJUSTMENT',
            'version' => 1,
            'label' => 'Test',
            'timezone' => 'America/Sao_Paulo',
            'effective_from' => CarbonImmutable::now()->subYear(),
            'is_current' => true,
            'source_ref' => 'https://www.gov.br/fonte-oficial',
            'metadata' => ['verification' => 'VERIFIED'],
        ]);
        $projection = new TaxObligationProjection([
            'due_at' => CarbonImmutable::now()->subDays(3),
            'calendar_version_id' => $calendar->id,
            'due_rule_snapshot' => [
                'business_day_adjustment' => 'NEXT_BUSINESS_DAY',
                'calendar_verified' => false,
                'business_day_adjustment_reason' => 'WEEKEND_ONLY_UNVERIFIED_HOLIDAYS',
            ],
        ]);

        $pack = $this->resolver->resolve(null, CarbonImmutable::now(), $projection);

        $this->assertSame(PgdasdDeclarationState::Unverified, $pack['state']);
        $this->assertFalse($pack['calendar_verified']);
    }
}
