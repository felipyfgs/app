<?php

namespace Tests\Unit\Serpro;

use App\Services\Serpro\CapabilityDriverResolver;
use App\Services\Serpro\Catalog\OfficialServiceCatalogManifest;
use App\Services\Serpro\Catalog\OperationCoordinateResolver;
use Tests\TestCase;

final class OfficialServiceCatalogManifestTest extends TestCase
{
    public function test_manifest_has_119_entries_and_expected_state_counts(): void
    {
        $manifest = new OfficialServiceCatalogManifest;
        $result = $manifest->validate();

        $this->assertTrue($result['valid'], implode('; ', $result['errors']));
        $this->assertSame(119, $result['total']);
        $this->assertSame(98, $result['counts']['PRODUCTION']);
        $this->assertSame(19, $result['counts']['PROSPECTION']);
        $this->assertSame(1, $result['counts']['UNDER_CONSTRUCTION']);
        $this->assertSame(1, $result['counts']['CANCELED']);
        $this->assertTrue($result['unique_operation_keys']);
        $this->assertTrue($result['unique_coordinates']);
    }

    public function test_sitfis_coordinates_are_official_v2(): void
    {
        $resolver = app(OperationCoordinateResolver::class);

        $solicit = $resolver->resolve('sitfis.solicitar_protocolo');
        $this->assertSame('SITFIS', $solicit['id_sistema']);
        $this->assertSame('SOLICITARPROTOCOLO91', $solicit['id_servico']);
        $this->assertSame('2.0', $solicit['versao_sistema']);
        $this->assertSame('Apoiar', $solicit['route']->value);
        $this->assertSame('00002', $solicit['required_proxy_power']);
        $this->assertSame('EMPTY', $solicit['dados_mode']);

        $emit = $resolver->resolve('sitfis.emitir_relatorio');
        $this->assertSame('RELATORIOSITFIS92', $emit['id_servico']);
        $this->assertSame('Emitir', $emit['route']->value);
        $this->assertSame('00002', $emit['required_proxy_power']);
    }

    public function test_inventoried_capability_is_not_executable(): void
    {
        $resolver = app(OperationCoordinateResolver::class);
        $coords = $resolver->resolve('procuracoes.obter');
        $this->assertFalse($coords['platform_support']->isExecutable());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('CAPABILITY_NOT_IMPLEMENTED');
        $resolver->resolveExecutable('procuracoes.obter');
    }

    public function test_simulated_driver_blocked_in_production_preflight(): void
    {
        config([
            'serpro.capabilities.sitfis' => 'simulated',
            'serpro.capabilities.autentica_procurador' => 'disabled',
        ]);

        $resolver = new CapabilityDriverResolver;
        // Força ambiente production via app
        $this->app['env'] = 'production';

        $problems = $resolver->preflightProduction();
        $this->assertNotEmpty($problems);

        $this->expectException(\RuntimeException::class);
        $resolver->forCapability('sitfis');
    }
}
