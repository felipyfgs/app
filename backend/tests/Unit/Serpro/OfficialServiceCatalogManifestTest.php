<?php

namespace Tests\Unit\Serpro;

use App\Enums\SerproOfficialState;
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
        $this->assertSame(5, $result['route_counts']['Apoiar']);
        $this->assertSame(72, $result['route_counts']['Consultar']);
        $this->assertSame(7, $result['route_counts']['Declarar']);
        $this->assertSame(30, $result['route_counts']['Emitir']);
        $this->assertSame(5, $result['route_counts']['Monitorar']);
        $this->assertNotNull($result['sha256']);
        $this->assertSame(64, strlen((string) $result['sha256']));
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
        $this->assertSame('PROTOCOL_POLLING', $solicit['async_policy']);
        $this->assertSame(SerproOfficialState::Production, $solicit['official_state']);

        $emit = $resolver->resolve('sitfis.emitir_relatorio');
        $this->assertSame('RELATORIOSITFIS92', $emit['id_servico']);
        $this->assertSame('Emitir', $emit['route']->value);
        $this->assertSame('00002', $emit['required_proxy_power']);
    }

    public function test_official_sources_and_auth_metadata_are_present(): void
    {
        $manifest = new OfficialServiceCatalogManifest;
        $data = $manifest->load();
        $entry = $manifest->findByOperationKey($data, 'caixa_postal.lista');

        $this->assertSame('CAIXAPOSTAL', $entry['id_sistema']);
        $this->assertSame('MSGCONTRIBUINTE61', $entry['id_servico']);
        $this->assertSame('Consultar', $entry['route']);
        $this->assertSame('mailbox', $entry['monitoring_module']);
        $this->assertSame('PROCURATOR_WHEN_REPRESENTING', $entry['auth_mode']);
        $this->assertNotEmpty($entry['sources']);
        $this->assertArrayHasKey('url', $entry['sources'][0]);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $entry['sources'][0]['sha256']);
        $this->assertIsArray($entry['request_schema']);
        $this->assertIsArray($entry['response_schema']);
    }

    public function test_all_productive_capabilities_are_executable_by_generic_gateway(): void
    {
        $resolver = app(OperationCoordinateResolver::class);
        $coords = $resolver->resolve('procuracoes.obter');
        $this->assertTrue($coords['platform_support']->isExecutable());
        $this->assertSame('OBTERPROCURACAO41', $resolver->resolveExecutable('procuracoes.obter')['id_servico']);
    }

    public function test_non_productive_operation_is_blocked(): void
    {
        $resolver = app(OperationCoordinateResolver::class);
        $coords = $resolver->resolve('sicalc.consolidar');
        $this->assertSame(SerproOfficialState::UnderConstruction, $coords['official_state']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('CAPABILITY_NOT_EXECUTABLE');
        $resolver->resolveExecutable('sicalc.consolidar');
    }

    public function test_rejects_placeholder_manifest(): void
    {
        $path = sys_get_temp_dir().'/serpro-placeholder-manifest-'.uniqid('', true).'.json';
        $payload = [
            'manifest_version' => 'test.placeholder',
            'source' => 'test',
            'verified_at' => '2026-07-16',
            'source_snapshots' => [[
                'url' => 'https://example.invalid/catalog',
                'sha256' => str_repeat('a', 64),
            ]],
            'expected_counts' => [
                'total' => 1,
                'PRODUCTION' => 1,
                'PROSPECTION' => 0,
                'UNDER_CONSTRUCTION' => 0,
                'CANCELED' => 0,
            ],
            'entries' => [[
                'operation_key' => 'inventory.prospection_1',
                'id_sistema' => 'INTEGRA_PROSPECTION',
                'id_servico' => 'SERVPROSP501',
                'versao_sistema' => '1.0',
                'route' => 'Consultar',
                'auth_mode' => 'CONTRACT_ONLY',
                'proxy_rule' => 'NOT_APPLICABLE',
                'required_proxy_power' => null,
                'official_state' => 'PRODUCTION',
                'platform_support' => 'INVENTORIED',
                'monitoring_module' => 'inventory',
                'label' => 'placeholder',
                'is_mutating' => false,
                'billable_class' => 'CONSULTA',
                'dados_mode' => 'JSON_STRING',
                'async_policy' => 'HTTP_STATUS',
                'request_schema' => ['type' => 'object', 'fields' => []],
                'response_schema' => ['type' => 'object', 'fields' => []],
                'sources' => [[
                    'url' => 'https://example.invalid/catalog',
                    'sha256' => str_repeat('a', 64),
                ]],
            ]],
        ];
        file_put_contents($path, json_encode($payload, JSON_THROW_ON_ERROR));

        try {
            $result = (new OfficialServiceCatalogManifest)->validate(null, $path);
            $this->assertFalse($result['valid']);
            $this->assertNotEmpty($result['errors']);
            $joined = strtolower(implode(' ', $result['errors']));
            $this->assertTrue(
                str_contains($joined, 'placeholder')
                || str_contains($joined, 'inventário')
                || str_contains($joined, 'inventory')
                || str_contains($joined, 'integra_prospection'),
                'esperava erro de placeholder, obtido: '.$joined,
            );
        } finally {
            @unlink($path);
        }
    }

    public function test_simulated_driver_blocked_in_production_preflight(): void
    {
        config([
            'serpro.capabilities.sitfis' => 'simulated',
            'serpro.capabilities.autentica_procurador' => 'disabled',
        ]);

        $resolver = new CapabilityDriverResolver;
        $this->app['env'] = 'production';

        $problems = $resolver->preflightProduction();
        $this->assertNotEmpty($problems);

        $this->expectException(\RuntimeException::class);
        $resolver->forCapability('sitfis');
    }
}
