<?php

namespace Tests\Unit\Serpro;

use App\Services\Serpro\SerproOfficialSourceIntegrity;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Tests\TestCase;

final class SerproOfficialSourceIntegrityTest extends TestCase
{
    /** @var list<string> */
    private array $temporaryDirectories = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryDirectories as $directory) {
            File::deleteDirectory($directory);
        }

        parent::tearDown();
    }

    public function test_current_resources_are_coherent_and_hash_all_119_matrix_entries(): void
    {
        $result = $this->integrity()->loadAndValidate(
            resource_path('serpro/official-sources.v2026-07-18.json'),
            resource_path('serpro/official-service-catalog.v2026-07-16.json'),
            resource_path('serpro/power-matrix.v2026-07-18.json'),
        );

        $httpSources = array_filter(
            $result['manifest']['sources'],
            static fn (array $source): bool => $source['verification_kind'] === 'HTTP_CONTENT',
        );
        $this->assertCount(8, $httpSources);
        $this->assertCount(119, $result['catalog']['entries']);
        $this->assertCount(119, $result['matrix']['entries']);
        $this->assertSame(
            $result['matrix']['matrix_content_sha256'],
            hash('sha256', $this->integrity()->canonicalJson($result['matrix']['entries'])),
        );
    }

    public function test_manifest_rejects_legacy_and_obviously_repetitive_hashes(): void
    {
        $manifest = $this->currentManifest();
        $manifest['sources'][0]['content_sha256'] =
            'a1b2c3d4e5f60718293a4b5c6d7e8f90112233445566778899aabbccddeeff00';
        $this->assertRejected(
            fn () => $this->integrity()->assertManifest($manifest),
            'HASH_PLACEHOLDER',
        );

        $manifest = $this->currentManifest();
        $manifest['sources'][0]['content_sha256'] = str_repeat('ab', 32);
        $this->assertRejected(
            fn () => $this->integrity()->assertManifest($manifest),
            'HASH_ARTIFICIAL',
        );
    }

    public function test_manifest_rejects_invalid_kind_canonical_url_status_hash_and_date_combinations(): void
    {
        $mutations = [
            'HTTP_NOT_CANONICAL' => static function (array &$source): void {
                $source['canonical'] = false;
            },
            'HTTP_URL' => static function (array &$source): void {
                $source['url'] = 'http://apicenter.estaleiro.serpro.gov.br/documentacao/';
            },
            'HTTP_STATUS' => static function (array &$source): void {
                $source['expected_http_status'] = 304;
            },
            'HASH_FORMAT' => static function (array &$source): void {
                $source['content_sha256'] = strtoupper((string) $source['content_sha256']);
            },
            'DATE' => static function (array &$source): void {
                $source['retrieved_on'] = '2026-02-30';
            },
        ];

        foreach ($mutations as $expected => $mutation) {
            $manifest = $this->currentManifest();
            $mutation($manifest['sources'][0]);
            $this->assertRejected(
                fn () => $this->integrity()->assertManifest($manifest),
                $expected,
            );
        }

        $manifest = $this->currentManifest();
        $dynamicIndex = $this->sourceIndex($manifest, 'cnpj_alphanumeric_rfb');
        $manifest['sources'][$dynamicIndex]['content_sha256'] = str_repeat('1a', 32);
        $this->assertRejected(
            fn () => $this->integrity()->assertManifest($manifest),
            'NON_CONTENT_HASH',
        );
    }

    public function test_manifest_rejects_duplicate_and_missing_required_source_keys(): void
    {
        $manifest = $this->currentManifest();
        $manifest['sources'][1]['source_key'] = $manifest['sources'][0]['source_key'];
        $this->assertRejected(
            fn () => $this->integrity()->assertManifest($manifest),
            'SOURCE_DUPLICATE',
        );

        $manifest = $this->currentManifest();
        $index = $this->sourceIndex($manifest, 'services_vs_proxies');
        $manifest['sources'][$index]['source_key'] = 'services_vs_proxies_missing';
        $this->assertRejected(
            fn () => $this->integrity()->assertManifest($manifest),
            'SOURCE_MISSING:services_vs_proxies',
        );
    }

    public function test_transversal_validation_rejects_catalog_source_hash_divergence(): void
    {
        $paths = $this->copyResources();
        $catalog = $this->decode($paths['catalog']);
        $catalog['source_snapshots'][0]['sha256'] = str_repeat('c3', 32);
        $this->encode($paths['catalog'], $catalog);

        $this->assertRejected(
            fn () => $this->integrity()->loadAndValidate(...array_values($paths)),
            'CATALOG_SOURCE_MISMATCH:service_catalog',
        );
    }

    public function test_transversal_validation_rejects_matrix_content_hash_divergence(): void
    {
        $paths = $this->copyResources();
        $matrix = $this->decode($paths['matrix']);
        $matrix['entries'][0]['route'] = 'Consultar';
        $this->encode($paths['matrix'], $matrix);

        $this->assertRejected(
            fn () => $this->integrity()->loadAndValidate(...array_values($paths)),
            'MATRIX_CONTENT_HASH',
        );
    }

    public function test_transversal_validation_rejects_matrix_catalog_mismatch_even_with_recomputed_hash(): void
    {
        $paths = $this->copyResources();
        $matrix = $this->decode($paths['matrix']);
        $matrix['entries'][0]['route'] = 'Consultar';
        $matrix['matrix_content_sha256'] = hash(
            'sha256',
            $this->integrity()->canonicalJson($matrix['entries']),
        );
        $this->encode($paths['matrix'], $matrix);

        $this->assertRejected(
            fn () => $this->integrity()->loadAndValidate(...array_values($paths)),
            'MATRIX_ENTRY_MISMATCH',
        );
    }

    private function integrity(): SerproOfficialSourceIntegrity
    {
        return app(SerproOfficialSourceIntegrity::class);
    }

    /**
     * @return array<string, mixed>
     */
    private function currentManifest(): array
    {
        return $this->decode(resource_path('serpro/official-sources.v2026-07-18.json'));
    }

    /**
     * @param  array<string, mixed>  $manifest
     */
    private function sourceIndex(array $manifest, string $sourceKey): int
    {
        foreach ($manifest['sources'] as $index => $source) {
            if (($source['source_key'] ?? null) === $sourceKey) {
                return $index;
            }
        }

        self::fail("Fonte {$sourceKey} não encontrada no fixture.");
    }

    /**
     * @return array{manifest: string, catalog: string, matrix: string}
     */
    private function copyResources(): array
    {
        $directory = sys_get_temp_dir().'/serpro-integrity-'.bin2hex(random_bytes(8));
        File::makeDirectory($directory);
        $this->temporaryDirectories[] = $directory;

        $paths = [
            'manifest' => $directory.'/official-sources.v2026-07-18.json',
            'catalog' => $directory.'/official-service-catalog.v2026-07-16.json',
            'matrix' => $directory.'/power-matrix.v2026-07-18.json',
        ];
        File::copy(resource_path('serpro/official-sources.v2026-07-18.json'), $paths['manifest']);
        File::copy(resource_path('serpro/official-service-catalog.v2026-07-16.json'), $paths['catalog']);
        File::copy(resource_path('serpro/power-matrix.v2026-07-18.json'), $paths['matrix']);

        return $paths;
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(string $path): array
    {
        return json_decode((string) File::get($path), true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private function encode(string $path, array $value): void
    {
        File::put($path, json_encode(
            $value,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        ));
    }

    private function assertRejected(callable $callback, string $expectedMessage): void
    {
        try {
            $callback();
            self::fail("Validação deveria rejeitar com {$expectedMessage}.");
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString($expectedMessage, $exception->getMessage());
        }
    }
}
