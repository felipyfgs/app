<?php

namespace Tests\Unit\Serpro;

use App\Models\SerproServiceCatalogEntry;
use App\Services\Serpro\Catalog\OfficialServiceCatalogManifest;
use App\Services\Serpro\Catalog\OperationCoordinateResolver;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class CatalogFailClosedTest extends TestCase
{
    public function test_producao_nao_inventa_fallback_quando_chave_ausente_com_projecao(): void
    {
        config(['serpro.fail_closed_catalog' => true]);

        // Garantir que existe alguma projeção (seed migration) ou criar uma entrada mínima
        if (Schema::hasTable('serpro_service_catalog_entries')) {
            SerproServiceCatalogEntry::query()->firstOrCreate(
                [
                    'operation_key' => 'catalog.seed.marker',
                    'catalog_version' => 999001,
                    'environment' => 'TRIAL',
                    'solution_code' => 'SEED',
                    'service_code' => 'SEED',
                    'operation_code' => 'SEED',
                ],
                [
                    'label' => 'seed marker',
                    'is_mutating' => false,
                    'is_enabled' => true,
                    'billable_class' => 'CONSULTA',
                    'platform_support' => 'INVENTORIED',
                ],
            );
        }

        $resolver = app(OperationCoordinateResolver::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/CAPABILITY_NOT_FOUND|CATALOG_SOURCE_UNAVAILABLE/');
        $resolver->resolve('operacao.totalmente.ausente.no.catalogo');
    }

    public function test_non_prod_pode_usar_manifesto(): void
    {
        config(['serpro.fail_closed_catalog' => false]);
        $resolver = app(OperationCoordinateResolver::class);
        $manifest = app(OfficialServiceCatalogManifest::class);
        $m = $manifest->load();
        $first = $m['entries'][0]['operation_key'] ?? null;
        $this->assertNotNull($first);
        $coords = $resolver->resolve($first);
        $this->assertSame($first, $coords['operation_key']);
        $this->assertNotEmpty($coords['id_sistema']);
        $this->assertNotEmpty($coords['id_servico']);
    }
}
