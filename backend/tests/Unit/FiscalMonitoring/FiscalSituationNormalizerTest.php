<?php

namespace Tests\Unit\FiscalMonitoring;

use App\Enums\FiscalCoverage;
use App\Enums\FiscalSituation;
use App\Services\FiscalMonitoring\FiscalSituationNormalizer;
use Tests\TestCase;

class FiscalSituationNormalizerTest extends TestCase
{
    private FiscalSituationNormalizer $normalizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->normalizer = new FiscalSituationNormalizer;
    }

    public function test_nao_infere_em_dia_sem_evidencia(): void
    {
        $situation = $this->normalizer->guardSituation(
            FiscalSituation::UpToDate,
            FiscalCoverage::Full,
            hasEvidence: false,
        );

        $this->assertSame(FiscalSituation::Unknown, $situation);
    }

    public function test_em_dia_exige_cobertura_full(): void
    {
        $situation = $this->normalizer->guardSituation(
            FiscalSituation::UpToDate,
            FiscalCoverage::Partial,
            hasEvidence: true,
        );

        $this->assertSame(FiscalSituation::Attention, $situation);
    }

    public function test_preserva_unsupported_e_not_applicable(): void
    {
        $this->assertSame(
            FiscalSituation::Unsupported,
            $this->normalizer->guardSituation(
                FiscalSituation::UpToDate,
                FiscalCoverage::Unsupported,
                hasEvidence: true,
            ),
        );

        $this->assertSame(
            FiscalSituation::NotApplicable,
            $this->normalizer->guardSituation(
                FiscalSituation::Pending,
                FiscalCoverage::NotApplicable,
                hasEvidence: true,
            ),
        );
    }

    public function test_em_dia_com_evidencia_e_full(): void
    {
        $out = $this->normalizer->normalize(
            FiscalSituation::UpToDate,
            FiscalCoverage::Full,
            hasEvidence: true,
            normalized: ['regular' => true],
        );

        $this->assertSame(FiscalSituation::UpToDate, $out['situation']);
        $this->assertTrue($out['normalized']['has_evidence']);
    }

    public function test_rejeita_inferred_flag_quando_nao_up_to_date(): void
    {
        $out = $this->normalizer->normalize(
            FiscalSituation::UpToDate,
            FiscalCoverage::Partial,
            hasEvidence: true,
            normalized: ['inferred_up_to_date' => true],
        );

        $this->assertSame(FiscalSituation::Attention, $out['situation']);
        $this->assertArrayNotHasKey('inferred_up_to_date', $out['normalized']);
        $this->assertSame('UP_TO_DATE_WITHOUT_EVIDENCE', $out['normalized']['inference_rejected']);
    }
}
