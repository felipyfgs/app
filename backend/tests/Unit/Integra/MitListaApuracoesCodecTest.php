<?php

namespace Tests\Unit\Integra;

use App\DTO\Integra\MitListaApuracoesRequest;
use App\Services\Integra\Dctfweb\MitListaApuracoesCodec;
use App\Services\Serpro\Catalog\OperationKeyMap;
use InvalidArgumentException;
use Tests\TestCase;

class MitListaApuracoesCodecTest extends TestCase
{
    public function test_contrato_oficial_e_coordenada_sao_normalizados_sem_rede(): void
    {
        $fixture = json_decode(
            (string) file_get_contents(base_path('tests/fixtures/serpro/mit/listaapuracoes317.json')),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $filters = new MitListaApuracoesRequest(anoApuracao: 2026, mesApuracao: 5, situacaoApuracao: 2);
        $items = app(MitListaApuracoesCodec::class)->decode($fixture);

        $this->assertSame([
            'anoApuracao' => 2026,
            'mesApuracao' => 5,
            'situacaoApuracao' => 2,
        ], $filters->toPayload());
        $this->assertSame('mit.listaapuracoes', OperationKeyMap::require(
            null,
            'MIT',
            'MIT',
            'LISTAAPURACOES317',
        ));
        $this->assertSame('2026-05', $items[0]['period_key']);
        $this->assertSame(71001, $items[0]['id_apuracao']);
        $this->assertSame(1250.75, $items[0]['valor_total_apurado']);
    }

    public function test_filtros_e_resposta_malformados_falham_fechado(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new MitListaApuracoesRequest(mesApuracao: 5);
    }

    public function test_codec_rejeita_periodo_invalido_sem_projetar(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(MitListaApuracoesCodec::class)->decode([
            'Apuracoes' => [[
                'periodoApuracao' => '202613',
                'idApuracao' => 1,
                'situacao' => 1,
                'eventoEspecial' => false,
                'valorTotalApurado' => 0,
            ]],
        ]);
    }
}
