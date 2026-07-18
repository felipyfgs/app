<?php

namespace Tests\Unit\Fiscal\SimplesMei;

use App\Services\Fiscal\SimplesMei\RegimeCalendarOptionsCodec;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class RegimeCalendarOptionsCodecTest extends TestCase
{
    public function test_decodifica_dados_json_escapado_oficial(): void
    {
        $fixture = json_decode(
            (string) file_get_contents(dirname(__DIR__, 3).'/fixtures/serpro/regime-apuracao/consultar-anos-calendarios102.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $items = (new RegimeCalendarOptionsCodec)->decode($fixture);

        $this->assertSame([
            ['calendar_year' => 2025, 'regime_apuracao' => 'CAIXA'],
            ['calendar_year' => 2026, 'regime_apuracao' => 'COMPETENCIA'],
        ], $items);
    }

    public function test_aceita_lista_ja_decodificada_e_rejeita_anos_duplicados(): void
    {
        $codec = new RegimeCalendarOptionsCodec;
        $this->assertSame([['calendar_year' => 2026, 'regime_apuracao' => 'COMPETENCIA']], $codec->decode([
            ['anoCalendario' => 2026, 'regimeApurado' => 'COMPETENCIA'],
        ]));

        $this->expectException(InvalidArgumentException::class);
        $codec->decode([
            ['anoCalendario' => 2026, 'regimeApurado' => 'COMPETENCIA'],
            ['anoCalendario' => 2026, 'regimeApurado' => 'CAIXA'],
        ]);
    }
}
