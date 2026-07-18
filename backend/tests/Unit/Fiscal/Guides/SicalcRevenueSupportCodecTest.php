<?php

namespace Tests\Unit\Fiscal\Guides;

use App\Services\Fiscal\Guides\SicalcRevenueSupportCodec;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SicalcRevenueSupportCodecTest extends TestCase
{
    #[Test]
    public function it_accepts_only_the_documented_safe_revenue_metadata(): void
    {
        $decoded = (new SicalcRevenueSupportCodec)->decode(json_encode([
            'receita' => [
                'codigoReceita' => '1082',
                'descricaoReceita' => 'IRRF - Trabalho assalariado',
                'extensoes' => [[
                    'obrigatorios' => ['codigoReceita' => true, 'dataPA' => true, 'segredo' => 'não entra'],
                    'opcionais' => ['observacao' => false, 'cnpjPrestador' => true],
                    'informacoes' => ['calculado' => true, 'descricaoReferencia' => 'Referência', 'cpf' => 'nunca entra'],
                ]],
            ],
        ], JSON_THROW_ON_ERROR));

        $this->assertSame('1082', $decoded['revenue_code']);
        $this->assertSame(['codigoReceita' => true, 'dataPA' => true], $decoded['extensions'][0]['obrigatorios']);
        $this->assertSame(['cnpjPrestador' => true, 'observacao' => false], $decoded['extensions'][0]['opcionais']);
        $this->assertSame(['calculado' => true, 'descricaoReferencia' => 'Referência'], $decoded['extensions'][0]['informacoes']);
    }

    #[Test]
    public function it_rejects_missing_or_mismatched_revenue_shapes(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new SicalcRevenueSupportCodec)->decode(['receita' => ['codigoReceita' => '1082', 'descricaoReceita' => 'IRRF']]);
    }
}
