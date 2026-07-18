<?php

namespace Tests\Unit\Serpro;

use App\Services\Integra\ProxyPowerMatrixService;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Tests\TestCase;

final class ProxyPowerMatrixServiceTest extends TestCase
{
    private ?string $temporaryDirectory = null;

    protected function tearDown(): void
    {
        if ($this->temporaryDirectory !== null) {
            File::deleteDirectory($this->temporaryDirectory);
        }

        parent::tearDown();
    }

    public function test_valid_matrix_is_usable_only_with_matching_observed_source_hash(): void
    {
        $service = app(ProxyPowerMatrixService::class);
        $summary = $service->summary();

        $matching = $service->evaluateUsability($summary['source_content_sha256']);
        $this->assertTrue($matching['usable']);
        $this->assertSame(ProxyPowerMatrixService::REVIEW_APPROVED, $matching['review_status']);

        $divergent = $service->evaluateUsability(str_repeat('c1', 32));
        $this->assertFalse($divergent['usable']);
        $this->assertSame(ProxyPowerMatrixService::REVIEW_REQUIRED, $divergent['review_status']);
    }

    public function test_corrupted_matrix_fails_closed_without_operational_promotion(): void
    {
        $this->temporaryDirectory = sys_get_temp_dir().'/serpro-matrix-'.bin2hex(random_bytes(8));
        File::makeDirectory($this->temporaryDirectory);

        $manifestPath = $this->temporaryDirectory.'/official-sources.v2026-07-18.json';
        $catalogPath = $this->temporaryDirectory.'/official-service-catalog.v2026-07-16.json';
        $matrixPath = $this->temporaryDirectory.'/power-matrix.v2026-07-18.json';
        File::copy(resource_path('serpro/official-sources.v2026-07-18.json'), $manifestPath);
        File::copy(resource_path('serpro/official-service-catalog.v2026-07-16.json'), $catalogPath);
        File::copy(resource_path('serpro/power-matrix.v2026-07-18.json'), $matrixPath);

        $matrix = json_decode((string) File::get($matrixPath), true, 512, JSON_THROW_ON_ERROR);
        $matrix['entries'][0]['required_proxy_powers'] = ['99999'];
        File::put($matrixPath, json_encode(
            $matrix,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        ));

        config([
            'serpro.official_sources_manifest' => $manifestPath,
            'serpro.official_service_catalog_manifest' => $catalogPath,
            'serpro.power_matrix_manifest' => $matrixPath,
        ]);

        $service = app(ProxyPowerMatrixService::class);
        $evaluation = $service->evaluateUsability();
        $this->assertFalse($evaluation['usable']);
        $this->assertSame(ProxyPowerMatrixService::REVIEW_REQUIRED, $evaluation['review_status']);
        $this->assertSame('unknown', $evaluation['matrix_version']);

        $this->expectException(RuntimeException::class);
        $service->load();
    }
}
