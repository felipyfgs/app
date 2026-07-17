<?php

namespace Tests\Unit\Fiscal\SimplesMei\Pgdasd;

use App\Services\Fiscal\SimplesMei\Pgdasd\PgdasdConsDeclaracao13Codec;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
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
}
