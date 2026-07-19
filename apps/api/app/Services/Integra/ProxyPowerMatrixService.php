<?php

namespace App\Services\Integra;

use App\Services\Serpro\SerproOfficialSourceIntegrity;

/**
 * Matriz versionada idSistema+idServico → poderes e-CAC.
 * Hash da fonte oficial divergente ⇒ REVIEW_REQUIRED (fail-closed em produção).
 */
final class ProxyPowerMatrixService
{
    public const REVIEW_APPROVED = 'APPROVED';

    public const REVIEW_REQUIRED = 'REVIEW_REQUIRED';

    private ?array $cached = null;

    public function __construct(
        private readonly SerproOfficialSourceIntegrity $integrity,
    ) {}

    public function matrixPath(): string
    {
        return (string) config(
            'serpro.power_matrix_manifest',
            resource_path('serpro/power-matrix.v2026-07-18.json')
        );
    }

    /**
     * @return array{
     *   matrix_version: string,
     *   source_key: string,
     *   source_content_sha256: string,
     *   matrix_content_sha256: string,
     *   review_status: string,
     *   entries: list<array<string, mixed>>
     * }
     */
    public function load(): array
    {
        if ($this->cached !== null) {
            return $this->cached;
        }

        $validated = $this->integrity->loadAndValidate(
            (string) config(
                'serpro.official_sources_manifest',
                resource_path('serpro/official-sources.v2026-07-18.json'),
            ),
            matrixPath: $this->matrixPath(),
        );
        $data = $validated['matrix'];

        $entries = is_array($data['entries'] ?? null) ? $data['entries'] : [];
        $this->cached = [
            'matrix_version' => (string) ($data['matrix_version'] ?? 'unknown'),
            'source_key' => (string) ($data['source_key'] ?? 'services_vs_proxies'),
            'source_url' => isset($data['source_url']) ? (string) $data['source_url'] : null,
            'source_revision' => isset($data['source_revision']) ? (string) $data['source_revision'] : null,
            'source_content_sha256' => (string) ($data['source_content_sha256'] ?? ''),
            'matrix_content_sha256' => (string) ($data['matrix_content_sha256'] ?? ''),
            'review_status' => strtoupper((string) ($data['review_status'] ?? self::REVIEW_REQUIRED)),
            'retrieved_on' => isset($data['retrieved_on']) ? (string) $data['retrieved_on'] : null,
            'entries' => $entries,
        ];

        return $this->cached;
    }

    /**
     * Avalia se a matriz pode liberar operações (produção fail-closed).
     *
     * @param  string|null  $observedSourceHash  hash atual da página oficial (se capturado)
     * @return array{
     *   usable: bool,
     *   review_status: string,
     *   reason: ?string,
     *   matrix_version: string,
     *   source_content_sha256: string
     * }
     */
    public function evaluateUsability(?string $observedSourceHash = null): array
    {
        try {
            $matrix = $this->load();
        } catch (\Throwable) {
            return [
                'usable' => false,
                'review_status' => self::REVIEW_REQUIRED,
                'reason' => 'Integridade da matriz de poderes não comprovada.',
                'matrix_version' => 'unknown',
                'source_content_sha256' => '',
            ];
        }
        $status = $matrix['review_status'];
        $approvedHash = $matrix['source_content_sha256'];

        if ($status === self::REVIEW_REQUIRED) {
            return [
                'usable' => false,
                'review_status' => self::REVIEW_REQUIRED,
                'reason' => 'Matriz de poderes em REVIEW_REQUIRED — aguarda revisão da fonte oficial.',
                'matrix_version' => $matrix['matrix_version'],
                'source_content_sha256' => $approvedHash,
            ];
        }

        if ($observedSourceHash !== null && $observedSourceHash !== '' && $approvedHash !== '') {
            if (! hash_equals(strtolower($approvedHash), strtolower($observedSourceHash))) {
                return [
                    'usable' => false,
                    'review_status' => self::REVIEW_REQUIRED,
                    'reason' => 'Hash da fonte oficial de procurações divergiu da matriz aprovada.',
                    'matrix_version' => $matrix['matrix_version'],
                    'source_content_sha256' => $approvedHash,
                ];
            }
        }

        // Integridade leve: matrix_content_sha256 deve existir quando APPROVED
        if ($status === self::REVIEW_APPROVED && $matrix['matrix_content_sha256'] === '') {
            return [
                'usable' => false,
                'review_status' => self::REVIEW_REQUIRED,
                'reason' => 'Matriz APPROVED sem content hash — revisão necessária.',
                'matrix_version' => $matrix['matrix_version'],
                'source_content_sha256' => $approvedHash,
            ];
        }

        return [
            'usable' => $status === self::REVIEW_APPROVED,
            'review_status' => $status,
            'reason' => $status === self::REVIEW_APPROVED ? null : 'Matriz não aprovada.',
            'matrix_version' => $matrix['matrix_version'],
            'source_content_sha256' => $approvedHash,
        ];
    }

    /**
     * @return list<string> códigos de poder exigidos
     */
    public function requiredPowers(string $idSistema, string $idServico): array
    {
        $matrix = $this->load();
        $sistema = strtoupper(trim($idSistema));
        $servico = strtoupper(trim($idServico));

        foreach ($matrix['entries'] as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            if (
                strtoupper((string) ($entry['id_sistema'] ?? '')) === $sistema
                && strtoupper((string) ($entry['id_servico'] ?? '')) === $servico
            ) {
                $powers = $entry['required_proxy_powers'] ?? [];
                if (! is_array($powers)) {
                    return [];
                }

                return array_values(array_filter(array_map(
                    static fn ($p) => strtoupper(trim((string) $p)),
                    $powers
                ), static fn (string $p) => $p !== ''));
            }
        }

        return [];
    }

    /**
     * União dos poderes PRODUCTION da matriz (semântica e-CAC "TODOS").
     * Usado por OBTERPROCURACAO41 quando sistemas[] contém "TODOS".
     *
     * @return list<array{power_code: string, system_code: string, service_code: null}>
     */
    public function hubTodosPowerGrants(): array
    {
        $matrix = $this->load();
        /** @var array<string, array{power_code: string, system_code: string, service_code: null}> $byCode */
        $byCode = [];

        foreach ($matrix['entries'] as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            if (strtoupper((string) ($entry['official_state'] ?? '')) !== 'PRODUCTION') {
                continue;
            }
            $sistema = strtoupper(trim((string) ($entry['id_sistema'] ?? '')));
            if ($sistema === '') {
                continue;
            }
            $powers = $entry['required_proxy_powers'] ?? [];
            if (! is_array($powers)) {
                continue;
            }
            foreach ($powers as $raw) {
                $code = strtoupper(trim((string) $raw));
                if ($code === '' || isset($byCode[$code])) {
                    continue;
                }
                $byCode[$code] = [
                    'power_code' => $code,
                    'system_code' => $sistema,
                    'service_code' => null,
                ];
            }
        }

        ksort($byCode);

        return array_values($byCode);
    }

    /**
     * Marca REVIEW_REQUIRED em memória quando a fonte observada muda
     * (persistência do status fica no manifesto / processo de revisão).
     */
    public function markReviewRequiredOnSourceChange(string $observedSourceHash): bool
    {
        $eval = $this->evaluateUsability($observedSourceHash);

        return $eval['review_status'] === self::REVIEW_REQUIRED && ! $eval['usable'];
    }

    /**
     * @return array{
     *   matrix_version: string,
     *   review_status: string,
     *   source_content_sha256: string,
     *   matrix_content_sha256: string,
     *   entry_count: int
     * }
     */
    public function summary(): array
    {
        $matrix = $this->load();

        return [
            'matrix_version' => $matrix['matrix_version'],
            'review_status' => $matrix['review_status'],
            'source_content_sha256' => $matrix['source_content_sha256'],
            'matrix_content_sha256' => $matrix['matrix_content_sha256'],
            'entry_count' => count($matrix['entries']),
        ];
    }
}
