<?php

namespace Tests\Unit\Fiscal\Declarations;

use App\Enums\TaxDeliveryEvidenceKind;
use App\Enums\TaxRegimeCode;
use App\Services\Fiscal\Declarations\TaxDeliveryEvidenceService;
use Tests\TestCase;

class TaxRegimeAndDeliveryRulesTest extends TestCase
{
    public function test_normaliza_regimes_comuns(): void
    {
        $this->assertSame(TaxRegimeCode::SimplesNacional, TaxRegimeCode::normalize('simples'));
        $this->assertSame(TaxRegimeCode::SimplesNacional, TaxRegimeCode::normalize('SN'));
        $this->assertSame(TaxRegimeCode::Mei, TaxRegimeCode::normalize('simei'));
        $this->assertSame(TaxRegimeCode::LucroPresumido, TaxRegimeCode::normalize('Lucro Presumido'));
        $this->assertSame(TaxRegimeCode::LucroReal, TaxRegimeCode::normalize('LUCRO_REAL'));
        $this->assertSame(TaxRegimeCode::Unknown, TaxRegimeCode::normalize(null));
        $this->assertSame(TaxRegimeCode::Unknown, TaxRegimeCode::normalize(''));
        $this->assertSame(TaxRegimeCode::Unknown, TaxRegimeCode::normalize('QUALQUER_COISA'));
    }

    public function test_conclusividade_de_evidencia(): void
    {
        $svc = app(TaxDeliveryEvidenceService::class);

        $this->assertFalse($svc->isConclusive(
            TaxDeliveryEvidenceKind::InternalArtifact,
            'PROTO-1',
            null,
        ));

        $this->assertFalse($svc->isConclusive(
            TaxDeliveryEvidenceKind::OfficialResponse,
            null,
            null,
        ));

        $this->assertTrue($svc->isConclusive(
            TaxDeliveryEvidenceKind::OfficialReceipt,
            null,
            'REC-1',
        ));

        $this->assertTrue($svc->isConclusive(
            TaxDeliveryEvidenceKind::OfficialProtocol,
            'P-99',
            null,
        ));
    }
}
