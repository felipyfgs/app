<?php

namespace Tests\Unit\Fiscal\SimplesMei\Pgdasd;

use App\Services\Fiscal\SimplesMei\Pgdasd\PgdasdConsDeclaracao13Codec;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class PgdasdConsDeclaracao13CodecTest extends TestCase
{
    private PgdasdConsDeclaracao13Codec $codec;

    protected function setUp(): void
    {
        parent::setUp();
        $this->codec = new PgdasdConsDeclaracao13Codec;
    }

    #[Test]
    public function payload_xor_rejects_both(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->codec->buildPayload('2026', '202605');
    }

    #[Test]
    public function payload_xor_rejects_neither(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->codec->buildPayload(null, null);
    }

    #[Test]
    public function payload_accepts_year_only(): void
    {
        $this->assertSame(['anoCalendario' => '2026'], $this->codec->buildPayload('2026', null));
    }

    #[Test]
    public function payload_accepts_pa_only(): void
    {
        $this->assertSame(['periodoApuracao' => '202605'], $this->codec->buildPayload(null, '202605'));
    }

    #[Test]
    public function decode_preserves_out_of_order_declarations(): void
    {
        $dados = [
            'anoCalendario' => '2026',
            'periodos' => [[
                'periodoApuracao' => 202605,
                'operacoes' => [
                    [
                        'tipoOperacao' => 'Declaração Retificadora',
                        'numeroDeclaracao' => '22222222222222222',
                        'dataHoraTransmissao' => 20260615120000,
                    ],
                    [
                        'tipoOperacao' => 'Declaração Original',
                        'numeroDeclaracao' => '11111111111111111',
                        'dataHoraTransmissao' => 20260601100000,
                    ],
                    [
                        'tipoOperacao' => 'Geração de DAS',
                        'numeroDas' => '33333333333333333',
                        'dataHoraEmissaoDas' => 20260602090000,
                        'dasPago' => false,
                    ],
                ],
            ]],
        ];

        $decoded = $this->codec->decodeDados($dados);
        $this->assertFalse($decoded['incomplete']);
        $this->assertCount(1, $decoded['periods']);
        $this->assertCount(3, $decoded['periods'][0]['operations']);
        $kinds = array_column($decoded['periods'][0]['operations'], 'kind');
        $normalized = array_column($decoded['periods'][0]['operations'], 'normalized_operation_type');
        $this->assertContains('DECLARATION', $kinds);
        $this->assertContains('DAS', $kinds);
        $this->assertContains('RECTIFIER', $normalized);
        $this->assertContains('ORIGINAL', $normalized);
        $this->assertContains('DAS', $normalized);
    }

    #[Test]
    public function null_or_empty_dados_never_confirms_absence(): void
    {
        foreach ([null, ''] as $dados) {
            try {
                $this->codec->decodeDados($dados);
                $this->fail('Dados ausentes deveriam falhar.');
            } catch (RuntimeException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    #[Test]
    public function annual_empty_period_list_is_a_valid_covered_absence(): void
    {
        $decoded = $this->codec->decodeDados([
            'anoCalendario' => 2026,
            'periodos' => [],
        ]);

        $this->assertFalse($decoded['incomplete']);
        $this->assertTrue($this->codec->coversPeriodo($decoded, '202606'));
    }

    #[Test]
    public function official_nested_fixture_maps_declaration_and_das(): void
    {
        $path = dirname(__DIR__, 4).'/fixtures/serpro/pgdasd/13.json';
        $fixture = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        $decoded = $this->codec->decodeDados($fixture['response_dados']);

        $this->assertFalse($decoded['incomplete']);
        $this->assertCount(2, $decoded['periods'][0]['operations']);
        $this->assertSame('20260600000000001', $decoded['periods'][0]['operations'][0]['declaration_number']);
        $this->assertSame('20260600000000002', $decoded['periods'][0]['operations'][1]['das_number']);
    }

    #[Test]
    public function accepts_the_production_casing_variant_for_das_issuance_timestamp(): void
    {
        $decoded = $this->codec->decodeDados([
            'periodoApuracao' => 202606,
            'operacoes' => [[
                'tipoOperacao' => 'Geração de DAS',
                'indiceDas' => [
                    'numeroDas' => '20260600000000002',
                    'datahoraEmissaoDas' => 20260602090000,
                ],
            ]],
        ]);

        $this->assertFalse($decoded['incomplete']);
        $this->assertSame('DAS', $decoded['periods'][0]['operations'][0]['kind']);
    }

    #[Test]
    public function official_identifier_keeps_logical_key_stable_when_timestamp_changes(): void
    {
        $base = [
            'periodoApuracao' => 202606,
            'operacoes' => [[
                'tipoOperacao' => 'DECLARACAO ORIGINAL',
                'numeroDeclaracao' => '20260600000000001',
                'dataHoraTransmissao' => 20260715123045,
            ]],
        ];
        $first = $this->codec->decodeDados($base);
        $base['operacoes'][0]['dataHoraTransmissao'] = 20260715123046;
        $second = $this->codec->decodeDados($base);

        $this->assertSame(
            $first['periods'][0]['operations'][0]['logical_key'],
            $second['periods'][0]['operations'][0]['logical_key'],
        );
    }
}
