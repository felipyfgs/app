<?php

namespace Tests\Unit\Fiscal\SimplesMei\Pgmei;

use App\Services\Fiscal\SimplesMei\Pgmei\PgmeiDividaAtiva24Codec;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

class PgmeiDividaAtiva24CodecTest extends TestCase
{
    private PgmeiDividaAtiva24Codec $codec;

    protected function setUp(): void
    {
        parent::setUp();
        $this->codec = new PgmeiDividaAtiva24Codec;
    }

    public function test_build_payload_requires_four_digit_year(): void
    {
        $this->assertSame(['anoCalendario' => '2026'], $this->codec->buildPayload(2026));
        $this->expectException(InvalidArgumentException::class);
        $this->codec->buildPayload('26');
    }

    public function test_decode_empty_list_is_no_debt(): void
    {
        $decoded = $this->codec->decodeDados('[]', 2026);
        $this->assertSame(2026, $decoded['calendar_year']);
        $this->assertSame(0, $decoded['items_count']);
        $this->assertSame(0, $decoded['total_cents']);
        $this->assertSame([], $decoded['items']);
        $this->assertNotEmpty($decoded['digest']);
    }

    public function test_decode_items_with_br_decimal_and_exact_cents(): void
    {
        $dados = json_encode([
            [
                'periodoApuracao' => '202603',
                'tributo' => 'INSS',
                'valor' => '1.234,56',
                'enteFederado' => 'União',
                'situacaoDebito' => 'Enviado à PFN',
            ],
            [
                'periodoApuracao' => '202604',
                'tributo' => 'ISS',
                'valor' => '10.00',
                'enteFederado' => 'Município',
                'situacaoDebito' => 'Ativa',
            ],
        ], JSON_THROW_ON_ERROR);

        $decoded = $this->codec->decodeDados($dados, 2026);
        $this->assertSame(2, $decoded['items_count']);
        $this->assertSame(123456 + 1000, $decoded['total_cents']);
        $this->assertSame(123456, $decoded['items'][0]['amount_cents']);
        $this->assertSame('INSS', $decoded['items'][0]['tributo']);
        $this->assertSame('Enviado à PFN', $decoded['items'][0]['situacao_debito']);
    }

    public function test_decode_rejects_ambiguous_value(): void
    {
        $this->expectException(RuntimeException::class);
        $this->codec->decodeDados([
            ['periodoApuracao' => '202601', 'tributo' => 'INSS', 'valor' => '1.234.56,789'],
        ], 2026);
    }

    public function test_extract_dados_from_envelope(): void
    {
        $body = ['dados' => '[]', 'status' => 200];
        $this->assertSame('[]', $this->codec->extractDados($body));
    }
}
