<?php

namespace App\Services\Serpro;

use App\Models\SerproDocumentSnapshot;
use Illuminate\Support\Facades\File;
use RuntimeException;

/**
 * Registro versionado das fontes oficiais SERPRO (URL, hash, revisão, capacidades).
 */
final class SerproDocumentRegistry
{
    public function manifestPath(): string
    {
        return (string) config(
            'serpro.official_sources_manifest',
            resource_path('serpro/official-sources.v2026-07-16.json')
        );
    }

    /**
     * @return array{
     *   version: string,
     *   retrieved_on: string,
     *   sources: list<array<string, mixed>>
     * }
     */
    public function loadManifest(): array
    {
        $path = $this->manifestPath();
        if (! is_file($path)) {
            throw new RuntimeException('Manifesto de fontes oficiais SERPRO ausente.');
        }

        /** @var array{version?: string, retrieved_on?: string, sources?: list<array<string, mixed>>} $data */
        $data = json_decode((string) File::get($path), true, 512, JSON_THROW_ON_ERROR);

        return [
            'version' => (string) ($data['version'] ?? 'unknown'),
            'retrieved_on' => (string) ($data['retrieved_on'] ?? ''),
            'sources' => is_array($data['sources'] ?? null) ? $data['sources'] : [],
        ];
    }

    /**
     * Importa o manifesto para a tabela de snapshots (idempotente por source_key+hash).
     *
     * @return array{created: int, existing: int, total: int}
     */
    public function syncFromManifest(): array
    {
        $manifest = $this->loadManifest();
        $created = 0;
        $existing = 0;

        foreach ($manifest['sources'] as $source) {
            $key = (string) ($source['source_key'] ?? '');
            $hash = (string) ($source['content_sha256'] ?? '');
            if ($key === '' || $hash === '') {
                continue;
            }

            $row = SerproDocumentSnapshot::query()->firstOrCreate(
                [
                    'source_key' => $key,
                    'content_sha256' => $hash,
                ],
                [
                    'title' => (string) ($source['title'] ?? $key),
                    'url' => isset($source['url']) ? (string) $source['url'] : null,
                    'document_type' => (string) ($source['document_type'] ?? 'REFERENCE'),
                    'revision' => isset($source['revision']) ? (string) $source['revision'] : null,
                    'retrieved_on' => $source['retrieved_on'] ?? $manifest['retrieved_on'] ?: null,
                    'affected_capabilities' => $source['affected_capabilities'] ?? [],
                    'segregation_class' => (string) ($source['segregation_class'] ?? 'PRODUCTION'),
                    'notes' => isset($source['notes']) ? (string) $source['notes'] : null,
                    'metadata' => [
                        'manifest_version' => $manifest['version'],
                        'canonical' => (bool) ($source['canonical'] ?? true),
                    ],
                ]
            );

            if ($row->wasRecentlyCreated) {
                $created++;
            } else {
                $existing++;
            }
        }

        return [
            'created' => $created,
            'existing' => $existing,
            'total' => $created + $existing,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listSanitized(): array
    {
        return SerproDocumentSnapshot::query()
            ->orderBy('source_key')
            ->orderByDesc('id')
            ->get()
            ->map->toSanitizedArray()
            ->all();
    }
}
