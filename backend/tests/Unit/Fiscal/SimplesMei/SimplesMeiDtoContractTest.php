<?php

namespace Tests\Unit\Fiscal\SimplesMei;

use App\DTO\Fiscal\SimplesMei\CcmeiDto;
use App\DTO\Fiscal\SimplesMei\DasGuideDto;
use App\DTO\Fiscal\SimplesMei\DasnSimeiDto;
use App\DTO\Fiscal\SimplesMei\DefisDto;
use App\DTO\Fiscal\SimplesMei\PgdasdDeclarationDto;
use App\DTO\Fiscal\SimplesMei\PgmeiDto;
use App\DTO\Fiscal\SimplesMei\RegimeApuracaoDto;
use App\DTO\Serpro\IntegraResponse;
use App\Enums\FiscalGuidePaymentStatus;
use App\Enums\FiscalSituation;
use App\Enums\TaxRegimeCode;
use App\Services\Fiscal\SimplesMei\Pgmei\PgmeiDividaAtiva24Codec;
use App\Services\Fiscal\SimplesMei\SimplesMeiCatalog;
use App\Services\Fiscal\SimplesMei\SimplesMeiResponseMapper;
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
        $this->assertContains('INTEGRA_SN/REGIME_APURACAO/CONSULTAR_ANOS_CALENDARIOS', $keys);
        $this->assertContains('INTEGRA_SN/REGIME_APURACAO/CONSULTAR_RESOLUCAO', $keys);
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
        $this->assertArrayNotHasKey('raw', $ccmei->toNormalized());

        $dasn = DasnSimeiDto::fromIntegraBody([
            'dto_version' => '1',
            'data' => ['year' => '2025', 'status' => 'ENTREGUE', 'receipt_number' => 'R'],
        ]);
        $this->assertSame(FiscalSituation::UpToDate, $dasn->situation);
        $this->assertSame('MEI', $dasn->toNormalized()['regime_family']);
    }

    public function test_ccmei_descarta_campos_sensiveis_do_retorno_oficial(): void
    {
        $dto = CcmeiDto::fromIntegraBody([
            'situacaoCadastralVigente' => 'ATIVA',
            'cnpj' => '00000000000000',
            'empresario' => [
                'nomeCivil' => 'Pessoa protegida',
                'cpf' => '00000000000',
            ],
            'enderecoComercial' => ['logradouro' => 'Endereço protegido'],
            'qrcode' => base64_encode('conteudo-protegido'),
        ]);

        $normalized = $dto->toNormalized();

        $this->assertSame('ATIVA', $normalized['status']);
        $this->assertSame(FiscalSituation::UpToDate->value, $normalized['situation']);
        $this->assertStringNotContainsString('00000000000', json_encode($normalized, JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString('conteudo-protegido', json_encode($normalized, JSON_THROW_ON_ERROR));
        $this->assertArrayNotHasKey('raw', $normalized);
    }

    public function test_ccmei_rejeita_retorno_sem_situacao_confiavel(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Resposta CCMEI inválida ou ambígua.');

        CcmeiDto::fromIntegraBody([
            'qrcode' => base64_encode('conteudo-protegido'),
            'empresario' => ['cpf' => '00000000000'],
        ]);
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
        $mapper = new SimplesMeiResponseMapper(app(PgmeiDividaAtiva24Codec::class));
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
        $mapper = new SimplesMeiResponseMapper(app(PgmeiDividaAtiva24Codec::class));
        $result = $mapper->map($def, new IntegraResponse(
            success: true,
            httpStatus: 200,
            body: [
                'dados' => json_encode([
                    [
                        'periodoApuracao' => '202601',
                        'tributo' => 'INSS',
                        'valor' => '1,00',
                        'enteFederado' => 'União',
                        'situacaoDebito' => 'Ativa',
                    ],
                ], JSON_THROW_ON_ERROR),
            ],
        ), '2026');

        $this->assertSame(FiscalSituation::Pending, $result->situation);
        $this->assertSame('HAS_ACTIVE_DEBT', $result->normalized['debt_state'] ?? null);
        $this->assertSame(100, $result->normalized['total_cents'] ?? null);
    }

    public function test_mapper_ccmei_sanitiza_evidencia_oficial_e_falha_sem_dados_decodificaveis(): void
    {
        $def = SimplesMeiCatalog::find('INTEGRA_MEI', 'CCMEI', 'CONSULTAR');
        $mapper = new SimplesMeiResponseMapper(app(PgmeiDividaAtiva24Codec::class));
        $result = $mapper->map($def, new IntegraResponse(
            success: true,
            httpStatus: 200,
            body: ['status' => 200],
            dados: json_encode([
                'situacaoCadastralVigente' => 'ATIVA',
                'cnpj' => '00000000000000',
                'empresario' => ['cpf' => '00000000000'],
                'qrcode' => base64_encode('conteudo-protegido'),
            ], JSON_THROW_ON_ERROR),
            simulated: true,
        ));

        $this->assertSame('SUCCESS', $result->result->value);
        $this->assertSame(FiscalSituation::UpToDate, $result->situation);
        $this->assertStringNotContainsString('00000000000', (string) $result->evidenceBytes);
        $this->assertStringNotContainsString('conteudo-protegido', (string) $result->evidenceBytes);
        $this->assertStringNotContainsString('qrcode', (string) $result->evidenceBytes);

        $invalid = $mapper->map($def, new IntegraResponse(
            success: true,
            httpStatus: 200,
            body: ['status' => 200, 'dados' => '{invalid-json'],
            dados: '{invalid-json',
            simulated: true,
        ));

        $this->assertSame('FAILED', $invalid->result->value);
        $this->assertSame('INVALID_RESPONSE', $invalid->errorCode);
    }
}
