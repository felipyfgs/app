<?php

namespace Tests\Unit\Fiscal\Dctfweb;

use App\Enums\DctfwebDeclarationState;
use App\Enums\TaxObligationApplicability;
use App\Models\DctfwebDeclaration;
use App\Models\TaxDeadlineCalendarVersion;
use App\Models\TaxObligationProjection;
use App\Services\Fiscal\Dctfweb\DctfwebDeclarationStateResolver;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DctfwebDeclarationStateResolverTest extends TestCase
{
    use RefreshDatabase;

    private DctfwebDeclarationStateResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new DctfwebDeclarationStateResolver;
    }

    #[Test]
    public function current_when_document_valid(): void
    {
        $decl = new DctfwebDeclaration(['receipt_number' => 'R1']);
        $pack = $this->resolver->resolve(
            declarationForExpectedPa: $decl,
            lastProductiveConsultedAt: CarbonImmutable::now(),
            projection: null,
            documentValid: true,
        );
        $this->assertSame(DctfwebDeclarationState::Current, $pack['state']);
    }

    #[Test]
    public function no_movement_when_parser_confirms(): void
    {
        $decl = new DctfwebDeclaration(['receipt_number' => 'R1', 'no_movement' => true]);
        $pack = $this->resolver->resolve(
            declarationForExpectedPa: $decl,
            lastProductiveConsultedAt: CarbonImmutable::now(),
            projection: null,
            noMovement: true,
            documentValid: true,
        );
        $this->assertSame(DctfwebDeclarationState::NoMovementValid, $pack['state']);
    }

    #[Test]
    public function unverified_when_simulated(): void
    {
        $pack = $this->resolver->resolve(null, CarbonImmutable::now(), null, false, true);
        $this->assertSame(DctfwebDeclarationState::Unverified, $pack['state']);
    }

    #[Test]
    public function due_within_deadline_requires_applicability(): void
    {
        $proj = new TaxObligationProjection([
            'due_at' => CarbonImmutable::now()->addDays(5),
            'applicability' => TaxObligationApplicability::Applicable,
        ]);
        $pack = $this->resolver->resolve(
            null,
            CarbonImmutable::now(),
            $proj,
            false,
            false,
        );
        $this->assertSame(DctfwebDeclarationState::DueWithinDeadline, $pack['state']);
    }

    #[Test]
    public function overdue_requires_verified_calendar_and_productive_after_due(): void
    {
        $cal = TaxDeadlineCalendarVersion::query()->create([
            'code' => 'TEST_DCTFWEB',
            'version' => 1,
            'label' => 'Test',
            'timezone' => 'America/Sao_Paulo',
            'effective_from' => CarbonImmutable::now()->subYear(),
            'is_current' => true,
            'source_ref' => 'https://www.gov.br/receitafederal/calendario-oficial',
            'metadata' => ['verification' => 'VERIFIED'],
        ]);

        $due = CarbonImmutable::now()->subDays(3);
        $proj = new TaxObligationProjection([
            'due_at' => $due,
            'applicability' => TaxObligationApplicability::Applicable,
            'calendar_version_id' => $cal->id,
            'due_rule_snapshot' => [
                'business_day_adjustment' => 'NEXT_BANKING_BUSINESS_DAY',
                'calendar_verified' => true,
            ],
        ]);
        // Ensure calendar relation lookup works
        $proj->setRelation('calendarVersion', $cal);
        $proj->calendar_version_id = $cal->id;

        $pack = $this->resolver->resolve(
            null,
            $due->addDay(),
            $proj,
            false,
            false,
        );
        $this->assertSame(DctfwebDeclarationState::OverdueNotFound, $pack['state']);
    }

    #[Test]
    public function absence_without_verified_calendar_stays_unverified(): void
    {
        $proj = new TaxObligationProjection([
            'due_at' => CarbonImmutable::now()->subDays(3),
            'applicability' => TaxObligationApplicability::Applicable,
        ]);
        $pack = $this->resolver->resolve(
            null,
            CarbonImmutable::now(),
            $proj,
            false,
            false,
        );
        $this->assertSame(DctfwebDeclarationState::Unverified, $pack['state']);
    }
}
