<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class SurfaceInventoryApiTest extends TestCase
{
    public function test_live_route_total_matches_inventory_summary(): void
    {
        $summary = $this->summary();

        Artisan::call('route:list', ['--json' => true]);
        $live = json_decode(Artisan::output(), true);
        $this->assertIsArray($live);

        $this->assertSame(
            (int) $summary['apiTotal'],
            count($live),
            'Totais do inventário e de artisan route:list --json divergem.',
        );
    }

    public function test_sample_inventory_uris_exist_in_live_routes(): void
    {
        $inventory = json_decode(
            (string) file_get_contents(base_path('tests/fixtures/surface-inventory/api-routes.json')),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        $this->assertIsArray($inventory);
        $this->assertNotEmpty($inventory);

        Artisan::call('route:list', ['--json' => true]);
        $live = json_decode(Artisan::output(), true);
        $this->assertIsArray($live);

        $liveUris = collect($live)
            ->map(fn (array $row): string => (string) ($row['uri'] ?? ''))
            ->filter()
            ->unique()
            ->all();

        $sample = array_slice($inventory, 0, 5);
        foreach ($sample as $row) {
            $uri = (string) ($row['uri'] ?? '');
            $this->assertNotSame('', $uri);
            $this->assertContains(
                $uri,
                $liveUris,
                "URI inventariada ausente nas rotas live: {$uri}",
            );
        }
    }

    /** @return array<string, mixed> */
    private function summary(): array
    {
        $path = base_path('tests/fixtures/surface-inventory/summary.json');
        $this->assertFileExists($path);

        $summary = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($summary);
        $this->assertArrayHasKey('apiTotal', $summary);

        return $summary;
    }
}
