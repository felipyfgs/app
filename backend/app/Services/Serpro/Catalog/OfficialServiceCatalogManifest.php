<?php

namespace App\Services\Serpro\Catalog;

use App\Enums\SerproBillableClass;
use App\Enums\SerproFunctionalRoute;
use App\Enums\SerproOfficialState;
use App\Enums\SerproPlatformSupport;
use InvalidArgumentException;
use RuntimeException;

/**
 * Leitor/validador do manifesto versionado de serviços oficiais Integra Contador.
 *
 * @phpstan-type ManifestEntry array{
 *   operation_key: string,
 *   id_sistema: string,
 *   id_servico: string,
 *   versao_sistema: string,
 *   route: string,
 *   required_proxy_power: string|null,
 *   official_state: string,
 *   platform_support: string,
 *   label: string,
 *   is_mutating: bool,
 *   billable_class: string,
 *   dados_mode: string
 * }
 * @phpstan-type Manifest array{
 *   manifest_version: string,
 *   source: string,
 *   verified_at: string,
 *   expected_counts: array{total: int, PRODUCTION: int, PROSPECTION: int, UNDER_CONSTRUCTION: int, CANCELED: int},
 *   notes?: string,
 *   entries: list<ManifestEntry>
 * }
 */
final class OfficialServiceCatalogManifest
{
    public const DEFAULT_RELATIVE_PATH = 'resources/serpro/official-service-catalog.v2026-07-15.json';

    /**
     * @return Manifest
     */
    public function load(?string $absolutePath = null): array
    {
        $path = $absolutePath ?? base_path(self::DEFAULT_RELATIVE_PATH);
        if (! is_file($path)) {
            throw new RuntimeException("Manifesto SERPRO não encontrado: {$path}");
        }

        $raw = file_get_contents($path);
        if ($raw === false || $raw === '') {
            throw new RuntimeException("Manifesto SERPRO ilegível: {$path}");
        }

        /** @var mixed $decoded */
        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            throw new RuntimeException('Manifesto SERPRO não é JSON válido.');
        }

        $this->assertStructure($decoded);

        /** @var Manifest $decoded */
        return $decoded;
    }

    /**
     * Valida contagens, unicidade e campos obrigatórios.
     *
     * @param  Manifest|null  $manifest
     * @return array{
     *   valid: bool,
     *   total: int,
     *   counts: array<string, int>,
     *   expected: array<string, int>,
     *   unique_operation_keys: bool,
     *   unique_coordinates: bool,
     *   errors: list<string>,
     *   manifest_version: string|null,
     *   sha256: string|null
     * }
     */
    public function validate(?array $manifest = null, ?string $absolutePath = null): array
    {
        $path = $absolutePath ?? base_path(self::DEFAULT_RELATIVE_PATH);
        $errors = [];
        $manifest ??= $this->load($path);

        $entries = $manifest['entries'] ?? [];
        if (! is_array($entries)) {
            return $this->result(false, 0, [], [], false, false, ['entries ausente'], null, null);
        }

        $counts = [
            'PRODUCTION' => 0,
            'PROSPECTION' => 0,
            'UNDER_CONSTRUCTION' => 0,
            'CANCELED' => 0,
        ];
        $keys = [];
        $coords = [];

        foreach ($entries as $i => $entry) {
            if (! is_array($entry)) {
                $errors[] = "entrada #{$i} não é objeto";

                continue;
            }
            foreach ([
                'operation_key', 'id_sistema', 'id_servico', 'versao_sistema', 'route',
                'official_state', 'platform_support', 'label', 'is_mutating', 'billable_class', 'dados_mode',
            ] as $field) {
                if (! array_key_exists($field, $entry)) {
                    $errors[] = "entrada #{$i} sem campo {$field}";
                }
            }

            $key = (string) ($entry['operation_key'] ?? '');
            if ($key === '') {
                $errors[] = "entrada #{$i} operation_key vazia";
            } elseif (isset($keys[$key])) {
                $errors[] = "operation_key duplicada: {$key}";
            } else {
                $keys[$key] = true;
            }

            $coord = sprintf(
                '%s|%s|%s',
                (string) ($entry['id_sistema'] ?? ''),
                (string) ($entry['id_servico'] ?? ''),
                (string) ($entry['versao_sistema'] ?? ''),
            );
            if (isset($coords[$coord])) {
                $errors[] = "coordenadas duplicadas: {$coord}";
            } else {
                $coords[$coord] = true;
            }

            $state = (string) ($entry['official_state'] ?? '');
            if (isset($counts[$state])) {
                $counts[$state]++;
            } else {
                $errors[] = "estado oficial inválido em {$key}: {$state}";
            }

            $route = (string) ($entry['route'] ?? '');
            if (SerproFunctionalRoute::tryFrom($route) === null) {
                $errors[] = "rota inválida em {$key}: {$route}";
            }
            if (SerproOfficialState::tryFrom($state) === null && $state !== '') {
                $errors[] = "SerproOfficialState inválido: {$state}";
            }
            $support = (string) ($entry['platform_support'] ?? '');
            if (SerproPlatformSupport::tryFrom($support) === null) {
                $errors[] = "platform_support inválido em {$key}: {$support}";
            }
            $billable = (string) ($entry['billable_class'] ?? '');
            if (SerproBillableClass::tryFrom($billable) === null) {
                $errors[] = "billable_class inválido em {$key}: {$billable}";
            }
        }

        $expected = $manifest['expected_counts'] ?? [
            'total' => 119,
            'PRODUCTION' => 98,
            'PROSPECTION' => 19,
            'UNDER_CONSTRUCTION' => 1,
            'CANCELED' => 1,
        ];

        $total = count($entries);
        if ($total !== (int) ($expected['total'] ?? 119)) {
            $errors[] = "total {$total} ≠ esperado {$expected['total']}";
        }
        foreach (['PRODUCTION', 'PROSPECTION', 'UNDER_CONSTRUCTION', 'CANCELED'] as $st) {
            if (($counts[$st] ?? 0) !== (int) ($expected[$st] ?? -1)) {
                $errors[] = "{$st}: {$counts[$st]} ≠ esperado {$expected[$st]}";
            }
        }

        $sha = is_file($path) ? hash_file('sha256', $path) : null;

        return $this->result(
            $errors === [],
            $total,
            $counts,
            $expected,
            count($keys) === $total,
            count($coords) === $total,
            $errors,
            isset($manifest['manifest_version']) ? (string) $manifest['manifest_version'] : null,
            $sha,
        );
    }

    /**
     * Resolve entrada por operation_key.
     *
     * @param  Manifest  $manifest
     * @return ManifestEntry
     */
    public function findByOperationKey(array $manifest, string $operationKey): array
    {
        foreach ($manifest['entries'] as $entry) {
            if (($entry['operation_key'] ?? '') === $operationKey) {
                return $entry;
            }
        }

        throw new InvalidArgumentException("operation_key desconhecida: {$operationKey}");
    }

    /**
     * @param  array<string, mixed>  $decoded
     */
    private function assertStructure(array $decoded): void
    {
        foreach (['manifest_version', 'source', 'verified_at', 'expected_counts', 'entries'] as $field) {
            if (! array_key_exists($field, $decoded)) {
                throw new RuntimeException("Manifesto SERPRO sem campo obrigatório: {$field}");
            }
        }
        if (! is_array($decoded['entries'])) {
            throw new RuntimeException('Manifesto SERPRO: entries deve ser lista.');
        }
    }

    /**
     * @param  array<string, int>  $counts
     * @param  array<string, int>  $expected
     * @param  list<string>  $errors
     * @return array{
     *   valid: bool,
     *   total: int,
     *   counts: array<string, int>,
     *   expected: array<string, int>,
     *   unique_operation_keys: bool,
     *   unique_coordinates: bool,
     *   errors: list<string>,
     *   manifest_version: string|null,
     *   sha256: string|null
     * }
     */
    private function result(
        bool $valid,
        int $total,
        array $counts,
        array $expected,
        bool $uniqueKeys,
        bool $uniqueCoords,
        array $errors,
        ?string $version,
        ?string $sha256,
    ): array {
        return [
            'valid' => $valid,
            'total' => $total,
            'counts' => $counts,
            'expected' => $expected,
            'unique_operation_keys' => $uniqueKeys,
            'unique_coordinates' => $uniqueCoords,
            'errors' => $errors,
            'manifest_version' => $version,
            'sha256' => $sha256,
        ];
    }
}
