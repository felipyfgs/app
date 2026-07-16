<?php

namespace Tests\Unit\Serpro;

use App\Services\Serpro\Catalog\OfficialServiceCatalogManifest;
use Tests\TestCase;

final class OfficialOperationContractFixturesTest extends TestCase
{
    public function test_every_productive_operation_has_a_source_backed_synthetic_contract_fixture(): void
    {
        $manifestPath = base_path(OfficialServiceCatalogManifest::DEFAULT_RELATIVE_PATH);
        $fixturePath = base_path('resources/serpro/contract-fixtures.v2026-07-16.json');
        $manifestRaw = file_get_contents($manifestPath);
        $fixtureRaw = file_get_contents($fixturePath);

        $this->assertIsString($manifestRaw);
        $this->assertIsString($fixtureRaw);
        $manifest = json_decode($manifestRaw, true, flags: JSON_THROW_ON_ERROR);
        $document = json_decode($fixtureRaw, true, flags: JSON_THROW_ON_ERROR);

        $this->assertTrue($document['synthetic']);
        $this->assertSame(hash('sha256', $manifestRaw), $document['manifest_sha256']);
        $this->assertCount(98, $document['fixtures']);

        $productive = collect($manifest['entries'])
            ->where('official_state', 'PRODUCTION')
            ->keyBy('operation_key');
        $fixtures = collect($document['fixtures'])->keyBy('operation_key');
        $this->assertSame($productive->keys()->sort()->values()->all(), $fixtures->keys()->sort()->values()->all());

        foreach ($document['fixtures'] as $fixture) {
            $entry = $productive->get($fixture['operation_key']);
            $this->assertNotNull($entry);
            $this->assertTrue($fixture['synthetic']);
            $this->assertSame($entry['route'], $fixture['route']);
            $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $fixture['schema_sha256']);
            $this->assertIsArray($fixture['request']['business_data']);
            $this->assertIsArray($fixture['response']['dados']);
            $this->assertNotEmpty($fixture['sources']);
            foreach ($fixture['sources'] as $source) {
                $this->assertStringStartsWith('https://apicenter.estaleiro.serpro.gov.br/', $source['url']);
                $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $source['sha256']);
            }
        }
    }
}
