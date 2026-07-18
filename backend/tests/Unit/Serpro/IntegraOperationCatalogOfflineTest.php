<?php

namespace Tests\Unit\Serpro;

use App\DTO\Serpro\IntegraRequest;
use App\Enums\SerproOfficialState;
use App\Enums\SerproPlatformSupport;
use App\Services\Serpro\Catalog\OfficialServiceCatalogManifest;
use App\Services\Serpro\Catalog\OperationCoordinateResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\Fakes\FakeIntegraContadorClient;
use Tests\Support\Fakes\SimulatedIntegraContadorClient;
use Tests\TestCase;

/**
 * Catálogo local Integra Contador (offline): PROD/IMPLEMENTED resolvem coordenadas
 * em fake/simulated sem HTTP; operações não produtivas são recusadas como não executáveis.
 *
 * Não lê dados/, .env de aplicação, segredos nem cria artefato fiscal.
 */
final class IntegraOperationCatalogOfflineTest extends TestCase
{
    /**
     * @return iterable<string, array{0: string, 1: array<string, mixed>}>
     */
    public static function productiveImplementedOperationsProvider(): iterable
    {
        $fixtures = self::contractFixturesByOperation();

        foreach (self::catalogEntries() as $entry) {
            if (
                ($entry['official_state'] ?? '') === SerproOfficialState::Production->value
                && ($entry['platform_support'] ?? '') === SerproPlatformSupport::Implemented->value
            ) {
                $key = (string) $entry['operation_key'];
                yield $key => [$key, $fixtures[$key] ?? []];
            }
        }
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function nonProductiveOperationsProvider(): iterable
    {
        foreach (self::catalogEntries() as $entry) {
            $state = (string) ($entry['official_state'] ?? '');
            if ($state !== SerproOfficialState::Production->value) {
                $key = (string) $entry['operation_key'];
                yield $key => [$key, $state];
            }
        }
    }

    #[DataProvider('productiveImplementedOperationsProvider')]
    public function test_prod_implemented_coordinates_and_contract_fixture_resolve_offline(
        string $operationKey,
        array $fixture,
    ): void {
        // Manifesto versionado em non-prod (sem projeção DB obrigatória).
        config(['serpro.fail_closed_catalog' => false]);

        // O fixture é a entrada sintética equivalente ao contrato oficial, não dado fiscal.
        $this->assertSame($operationKey, $fixture['operation_key'] ?? null);
        $this->assertTrue($fixture['synthetic'] ?? false);
        $this->assertIsArray($fixture['request']['business_data'] ?? null);
        $this->assertIsArray($fixture['response']['dados'] ?? null);
        $this->assertIsArray($fixture['sources'] ?? null);
        $this->assertNotEmpty($fixture['sources']);

        $resolver = app(OperationCoordinateResolver::class);
        $coords = $resolver->resolveExecutable($operationKey);

        $this->assertSame($operationKey, $coords['operation_key']);
        $this->assertSame(SerproOfficialState::Production, $coords['official_state']);
        $this->assertSame(SerproPlatformSupport::Implemented, $coords['platform_support']);
        $this->assertNotSame('', (string) $coords['id_sistema']);
        $this->assertNotSame('', (string) $coords['id_servico']);
        $this->assertNotSame('', (string) $coords['versao_sistema']);
        $this->assertNotNull($coords['route']);
        $this->assertTrue($coords['platform_support']->isExecutable());
        $this->assertSame($coords['route']->value, $fixture['route'] ?? null);

        $request = new IntegraRequest(
            officeId: 1,
            clientId: 1,
            environment: 'TRIAL',
            contractorCnpj: '11222333000181',
            authorIdentity: '11222333000181',
            contributorCnpj: '11222333000181',
            operationKey: $operationKey,
            solutionCode: (string) $coords['id_sistema'],
            serviceCode: (string) $coords['id_sistema'],
            operationCode: (string) $coords['id_servico'],
            businessData: $fixture['request']['business_data'],
        );

        $fake = new FakeIntegraContadorClient;
        $fakeResponse = $fake->execute($request);
        $this->assertTrue($fakeResponse->simulated);
        $this->assertSame($operationKey, $fakeResponse->operationKey);
        $this->assertFalse($fakeResponse->isProductiveEvidence());

        $simulated = app(SimulatedIntegraContadorClient::class);
        $simResponse = $simulated->execute($request);
        $this->assertTrue($simResponse->simulated);
        $this->assertSame($operationKey, $simResponse->operationKey);
        $this->assertSame($coords['route']->value, $simResponse->functionalRoute);
        $this->assertFalse($simResponse->isProductiveEvidence());
    }

    #[DataProvider('nonProductiveOperationsProvider')]
    public function test_non_productive_operations_are_refused_as_not_executable(string $operationKey, string $officialState): void
    {
        config(['serpro.fail_closed_catalog' => false]);

        $resolver = app(OperationCoordinateResolver::class);

        // Inventário permanece legível, mas não executável.
        $coords = $resolver->resolve($operationKey);
        $this->assertSame($operationKey, $coords['operation_key']);
        $this->assertInstanceOf(SerproOfficialState::class, $coords['official_state']);
        $this->assertNotSame(SerproOfficialState::Production, $coords['official_state']);
        $this->assertSame($officialState, $coords['official_state']->value);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/CAPABILITY_NOT_EXECUTABLE/');
        $resolver->resolveExecutable($operationKey);
    }

    /**
     * Carrega entradas do manifesto versionado sob resources/serpro (não dados/, não .env).
     *
     * @return list<array<string, mixed>>
     */
    private static function catalogEntries(): array
    {
        // Data providers rodam antes do bootstrap Laravel — path absoluto via __DIR__.
        $path = dirname(__DIR__, 3).'/resources/serpro/official-service-catalog.v2026-07-16.json';

        return (new OfficialServiceCatalogManifest)->load($path)['entries'];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function contractFixturesByOperation(): array
    {
        $path = dirname(__DIR__, 3).'/resources/serpro/contract-fixtures.v2026-07-16.json';
        $raw = file_get_contents($path);

        if (! is_string($raw)) {
            return [];
        }

        /** @var array{fixtures?: list<array<string, mixed>>} $document */
        $document = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);

        $fixtures = [];
        foreach ($document['fixtures'] ?? [] as $fixture) {
            $operationKey = (string) ($fixture['operation_key'] ?? '');
            if ($operationKey !== '') {
                $fixtures[$operationKey] = $fixture;
            }
        }

        return $fixtures;
    }
}
