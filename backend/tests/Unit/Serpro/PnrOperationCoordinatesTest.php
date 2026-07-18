<?php

namespace Tests\Unit\Serpro;

use App\Services\Serpro\Catalog\OfficialServiceCatalogManifest;
use App\Services\Serpro\Catalog\OperationCoordinateResolver;
use App\Services\Serpro\Catalog\OperationKeyMap;
use Tests\TestCase;

final class PnrOperationCoordinatesTest extends TestCase
{
    public function test_leitura_pnr_resolve_coordenadas_oficiais_sem_fixture_sintetico(): void
    {
        config(['serpro.fail_closed_catalog' => false]);

        $expected = [
            'pnr_contador.consultar_renuncias' => ['CONSRENUNCIA263', 'Consultar'],
            'pnr_contador.emitir_comprovante' => ['COMPRENUNCIA264', 'Emitir'],
            'pnr_contador.situacao_renuncia' => ['SITSOLICRENUNCIA265', 'Consultar'],
        ];

        $resolver = app(OperationCoordinateResolver::class);
        foreach ($expected as $operationKey => [$service, $route]) {
            $coordinates = $resolver->resolveExecutable($operationKey);

            $this->assertSame('PNRCONTADOR', $coordinates['id_sistema']);
            $this->assertSame($service, $coordinates['id_servico']);
            $this->assertSame($route, $coordinates['route']->value);
        }
    }

    public function test_mapa_legado_nunca_resolve_solicitacao_de_renuncia(): void
    {
        $this->assertSame(
            'pnr_contador.consultar_renuncias',
            OperationKeyMap::require(null, 'PNRCONTADOR', 'PNRCONTADOR', 'CONSRENUNCIA263'),
        );
        $this->assertNull(OperationKeyMap::resolve(null, 'PNRCONTADOR', 'PNRCONTADOR', 'SOLICRENUNCIA262'));
    }

    public function test_snapshot_documenta_os_campos_das_respostas_pnr(): void
    {
        $catalog = app(OfficialServiceCatalogManifest::class)->load();
        $entries = collect($catalog['entries'])->keyBy('operation_key');

        $history = $entries->get('pnr_contador.consultar_renuncias');
        $receipt = $entries->get('pnr_contador.emitir_comprovante');
        $status = $entries->get('pnr_contador.situacao_renuncia');

        $this->assertContains('content', array_column($history['response_schema']['fields'], 'field'));
        $this->assertContains('dados', array_column($receipt['response_schema']['fields'], 'field'));
        $this->assertContains('resultado', array_column($status['response_schema']['fields'], 'field'));
    }
}
