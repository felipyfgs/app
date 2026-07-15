<?php

namespace Tests\Unit\Fiscal\SimplesMei;

use App\DTO\Fiscal\SimplesMei\CcmeiDto;
use App\DTO\Fiscal\SimplesMei\DasGuideDto;
use App\DTO\Fiscal\SimplesMei\DasnSimeiDto;
use App\DTO\Fiscal\SimplesMei\DefisDto;
use App\DTO\Fiscal\SimplesMei\PgdasdDeclarationDto;
use App\DTO\Fiscal\SimplesMei\PgmeiDto;
use App\DTO\Fiscal\SimplesMei\RegimeApuracaoDto;
use App\Enums\FiscalGuidePaymentStatus;
use App\Enums\FiscalSituation;
use App\Enums\TaxRegimeCode;
use App\Services\Fiscal\SimplesMei\SimplesMeiCatalog;
use App\Services\Fiscal\SimplesMei\SimplesMeiResponseMapper;
use App\DTO\Serpro\IntegraResponse;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Contract tests por versão de DTO (tasks 8.1–8.2).
 */
class SimplesMeiDtoContractTest extends TestCase
{
    public function test_catalogo_contem_operacoes_sn_e_mei(): void
    {
        $keys = array_map(fn ($d) => $d->catalogKey(), SimplesMeiCatalog::all());

        $this->assertContains('INTEGRA_SN/PGDASD/CONSULTAR_DECLARACAO', $keys);
        $this->assertContains('INTEGRA_SN/DEFIS/CONSULTAR', $keys);
        $this->assertContains('INTEGRA_SN/REGIME_APURACAO/CONSULTAR', $keys);
        $this->assertContains('INTEGRA_MEI/PGMEI/CONSULTAR', $keys);
        $this->assertContains('INTEGRA_MEI/CCMEI/CONSULTAR', $keys);
        $this->assertContains('INTEGRA_MEI/DASN_SIMEI/CONSULTAR', $keys);
        $this->assertContains('INTEGRA_SN/PGDASD/TRANSMITIR', $keys);
        $this->assertTrue(
            SimplesMeiCatalog::find('INTEGRA_SN', 'PGDASD', 'TRANSMITIR')->mutability->isMutating()
        );
    }

    public function test_pgdasd_v1_entregue_com_recibo(): void
    {
        $dto = PgdasdDeclarationDto::fromIntegraBody([
            'dto_version' => '1',
            'data' => [
                'competence' => '2026-03',
                'status' => 'ENTREGUE',
                'receipt_number' => 'REC-1',
                'declaration_id' => 'D1',
            ],
        ]);

        $this->assertSame(PgdasdDeclarationDto::VERSION, $dto->version);
        $this->assertSame(FiscalSituation::UpToDate, $dto->situation);
        $this->assertSame('REC-1', $dto->receiptNumber);
        $this->assertSame('SIMPLES_NACIONAL', $dto->toNormalized()['regime_family']);
    }

    public function test_pgdasd_v1_sem_recibo_nao_presume_em_dia(): void
    {
        $dto = PgdasdDeclarationDto::fromIntegraBody([
            'dto_version' => '1',
            'data' => ['competence' => '2026-03', 'status' => 'ENTREGUE'],
        ]);

        $this->assertSame(FiscalSituation::Unknown, $dto->situation);
    }

    public function test_pgdasd_versao_desconhecida_falha(): void
    {
        $this->expectException(InvalidArgumentException::class);
        PgdasdDeclarationDto::fromIntegraBody(['dto_version' => '99', 'data' => []]);
    }

    public function test_defis_v1(): void
    {
        $dto = DefisDto::fromIntegraBody([
            'dto_version' => '1',
            'data' => ['year' => '2025', 'status' => 'PENDENTE'],
        ]);
        $this->assertSame(FiscalSituation::Pending, $dto->situation);
        $this->assertSame('2025', $dto->year);
    }

    public function test_regime_apuracao_v1_normaliza_sn_mei(): void
    {
        $dto = RegimeApuracaoDto::fromIntegraBody([
            'dto_version' => '1',
            'data' => [
                'current_regime' => 'SIMEI',
                'periods' => [
                    ['regime' => 'MEI', 'effective_from' => '2023-01-01', 'effective_to' => '2023-12-31'],
                    ['regime' => 'SN', 'effective_from' => '2024-01-01', 'effective_to' => null],
                ],
            ],
        ]);

        $this->assertSame(TaxRegimeCode::Mei, $dto->currentRegime);
        $this->assertCount(2, $dto->periods);
        $this->assertSame(TaxRegimeCode::SimplesNacional->value, $dto->periods[1]['regime']);
    }

    public function test_pgmei_ccmei_dasn_v1(): void
    {
        $pgmei = PgmeiDto::fromIntegraBody([
            'dto_version' => '1',
            'data' => ['competence' => '2026-01', 'status' => 'EMITIDO', 'das_number' => 'X'],
        ]);
        $this->assertSame(FiscalSituation::UpToDate, $pgmei->situation);
        $this->assertFalse($pgmei->toNormalized()['payment_inferred']);

        $ccmei = CcmeiDto::fromIntegraBody([
            'dto_version' => '1',
            'data' => ['status' => 'ATIVO', 'certificate_number' => 'C1'],
        ]);
        $this->assertSame(FiscalSituation::UpToDate, $ccmei->situation);

        $dasn = DasnSimeiDto::fromIntegraBody([
            'dto_version' => '1',
            'data' => ['year' => '2025', 'status' => 'ENTREGUE', 'receipt_number' => 'R'],
        ]);
        $this->assertSame(FiscalSituation::UpToDate, $dasn->situation);
        $this->assertSame('MEI', $dasn->toNormalized()['regime_family']);
    }

    public function test_das_guide_nunca_infere_pagamento(): void
    {
        $dto = DasGuideDto::fromIntegraBody([
            'dto_version' => '1',
            'data' => [
                'competence' => '2026-02',
                'document_number' => 'DAS-1',
                'amount' => 100,
                'payment_status' => 'PAID', // ignorado
            ],
        ]);

        $this->assertSame(FiscalGuidePaymentStatus::Unknown, $dto->paymentStatus);
        $this->assertFalse($dto->toNormalized()['payment_inferred']);
    }

    public function test_mapper_inconclusivo_pgdasd(): void
    {
        $def = SimplesMeiCatalog::find('INTEGRA_SN', 'PGDASD', 'CONSULTAR_DECLARACAO');
        $mapper = new SimplesMeiResponseMapper;
        $result = $mapper->map($def, new IntegraResponse(
            success: true,
            httpStatus: 200,
            body: [
                'dto_version' => '1',
                'data' => ['competence' => '2026-04', 'status' => 'INCONCLUSIVO'],
            ],
            simulated: true,
        ), '2026-04');

        $this->assertSame(FiscalSituation::Unknown, $result->situation);
        $this->assertNotNull($result->evidenceBytes);
        $this->assertNotEmpty($result->findings);
        $this->assertSame('PGDASD_INCONCLUSIVE', $result->findings[0]['code']);
    }

    public function test_mapper_versao_dto_invalida(): void
    {
        $def = SimplesMeiCatalog::find('INTEGRA_MEI', 'PGMEI', 'CONSULTAR');
        $mapper = new SimplesMeiResponseMapper;
        $result = $mapper->map($def, new IntegraResponse(
            success: true,
            httpStatus: 200,
            body: ['dto_version' => '9', 'data' => []],
        ));

        $this->assertSame('DTO_VERSION_UNSUPPORTED', $result->errorCode);
    }
}
