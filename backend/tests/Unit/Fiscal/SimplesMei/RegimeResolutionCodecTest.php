<?php

namespace Tests\Unit\Fiscal\SimplesMei;

use App\Services\Fiscal\SimplesMei\RegimeResolutionCodec;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class RegimeResolutionCodecTest extends TestCase
{
    public function test_decodifica_fixture_oficial_e_nao_retorna_base64(): void
    {
        $fixture = json_decode(
            (string) file_get_contents(dirname(__DIR__, 3).'/fixtures/serpro/regime-apuracao/consultar-resolucao104.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $decoded = (new RegimeResolutionCodec)->decode($fixture, 2025);

        $this->assertSame(2025, $decoded['calendar_year']);
        $this->assertSame('Resolução CGSN - Regime de Caixa (2025)', $decoded['text_bytes']);
        $this->assertSame(41, $decoded['byte_size']);
        $this->assertSame('text/plain; charset=UTF-8', $decoded['content_type']);
    }

    public function test_rejeita_base64_invalido_ou_ano_fora_do_contrato(): void
    {
        $codec = new RegimeResolutionCodec;

        try {
            $codec->decode(['textoResolucao' => 'not base64!'], 2025);
            $this->fail('O Base64 inválido deveria falhar fechado.');
        } catch (RuntimeException) {
            $this->addToAssertionCount(1);
        }

        $this->expectException(InvalidArgumentException::class);
        $codec->buildPayload(1999);
    }

    public function test_rejeita_ano_com_caracteres_extras(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new RegimeResolutionCodec)->buildPayload('2025texto');
    }

    public function test_sanitiza_corpo_publico_sem_texto_da_resolucao(): void
    {
        $safe = (new RegimeResolutionCodec)->sanitizePublic([
            'dados' => ['textoResolucao' => base64_encode('conteúdo sigiloso')],
        ], ['sanitized' => true, 'omitted' => true]);

        $this->assertSame(['sanitized' => true, 'omitted' => true], $safe['dados']['textoResolucao']);
        $this->assertStringNotContainsString('conteúdo sigiloso', json_encode($safe, JSON_THROW_ON_ERROR));
    }
}
