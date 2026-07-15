<?php

namespace App\Services\Serpro\Catalog;

use App\Models\SerproServiceCatalogEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Importa o manifesto versionado para a projeção consultável (idempotente).
 */
final class OfficialServiceCatalogImporter
{
    public function __construct(
        private readonly OfficialServiceCatalogManifest $manifest,
    ) {}

    /**
     * @return array{
     *   valid: bool,
     *   imported: int,
     *   updated: int,
     *   skipped: int,
     *   errors: list<string>,
     *   manifest_version: string|null,
     *   validation: array<string, mixed>
     * }
     */
    public function import(?string $absolutePath = null, ?int $catalogVersion = null): array
    {
        $validation = $this->manifest->validate(null, $absolutePath);
        if (! $validation['valid']) {
            return [
                'valid' => false,
                'imported' => 0,
                'updated' => 0,
                'skipped' => 0,
                'errors' => $validation['errors'],
                'manifest_version' => $validation['manifest_version'],
                'validation' => $validation,
            ];
        }

        $data = $this->manifest->load($absolutePath);
        $version = $catalogVersion ?? $this->resolveCatalogVersion($data['manifest_version']);
        $imported = 0;
        $updated = 0;
        $skipped = 0;

        DB::transaction(function () use ($data, $version, &$imported, &$updated, &$skipped): void {
            foreach ($data['entries'] as $entry) {
                $payload = $this->mapEntry($entry, $version, $data);

                $existing = SerproServiceCatalogEntry::query()
                    ->where('operation_key', $entry['operation_key'])
                    ->where('catalog_version', $version)
                    ->first();

                if ($existing === null) {
                    // Compat: lookup por coordenadas legadas se operation_key ainda nulo no legado
                    $existing = SerproServiceCatalogEntry::query()
                        ->whereNull('operation_key')
                        ->where('catalog_version', $version)
                        ->where('solution_code', $entry['id_sistema'])
                        ->where('service_code', $entry['id_servico'])
                        ->where('operation_code', $entry['id_servico'])
                        ->first();
                }

                if ($existing === null) {
                    SerproServiceCatalogEntry::query()->create($payload);
                    $imported++;

                    continue;
                }

                $dirty = false;
                foreach ($payload as $col => $value) {
                    if ($existing->{$col} != $value) {
                        $existing->{$col} = $value;
                        $dirty = true;
                    }
                }
                if ($dirty) {
                    $existing->save();
                    $updated++;
                } else {
                    $skipped++;
                }
            }
        });

        return [
            'valid' => true,
            'imported' => $imported,
            'updated' => $updated,
            'skipped' => $skipped,
            'errors' => [],
            'manifest_version' => $data['manifest_version'],
            'validation' => $validation,
        ];
    }

    /**
     * @param  array<string, mixed>  $entry
     * @param  array<string, mixed>  $manifest
     * @return array<string, mixed>
     */
    private function mapEntry(array $entry, int $version, array $manifest): array
    {
        $hasNewColumns = Schema::hasColumn('serpro_service_catalog_entries', 'operation_key');

        $base = [
            'catalog_version' => $version,
            'environment' => 'PRODUCTION',
            'solution_code' => $entry['id_sistema'],
            'service_code' => $entry['id_sistema'],
            'operation_code' => $entry['id_servico'],
            'label' => $entry['label'],
            'is_mutating' => (bool) $entry['is_mutating'],
            'is_enabled' => $entry['platform_support'] !== 'INVENTORIED',
            'required_proxy_power' => $entry['required_proxy_power'],
            'billable_class' => $entry['billable_class'],
            'cache_ttl_seconds' => $entry['id_sistema'] === 'SITFIS' ? 86400 : 3600,
            'rate_limit_per_minute' => 30,
            'coverage' => $entry['platform_support'] === 'INVENTORIED' ? 'INVENTORIED' : 'KNOWN',
            'metadata' => [
                'manifest_version' => $manifest['manifest_version'],
                'source' => $manifest['source'],
                'verified_at' => $manifest['verified_at'],
                'dados_mode' => $entry['dados_mode'],
                'route' => $entry['route'],
                'versao_sistema' => $entry['versao_sistema'],
            ],
            'effective_from' => now(),
            'effective_to' => null,
        ];

        if ($hasNewColumns) {
            $base['operation_key'] = $entry['operation_key'];
            $base['id_sistema'] = $entry['id_sistema'];
            $base['id_servico'] = $entry['id_servico'];
            $base['versao_sistema'] = $entry['versao_sistema'];
            $base['functional_route'] = $entry['route'];
            $base['official_state'] = $entry['official_state'];
            $base['platform_support'] = $entry['platform_support'];
            $base['dados_mode'] = $entry['dados_mode'];
        }

        return $base;
    }

    private function resolveCatalogVersion(string $manifestVersion): int
    {
        // 2026.07.15.1 → hash estável em inteiro positivo pequeno
        $digits = preg_replace('/\D+/', '', $manifestVersion) ?: '1';

        return (int) substr($digits, 0, 8) ?: 1;
    }
}
