<?php

namespace App\Services\Serpro\Catalog;

use App\Enums\SerproFunctionalRoute;
use App\Enums\SerproPlatformSupport;
use App\Models\SerproServiceCatalogEntry;
use RuntimeException;

/**
 * Resolve operation_key → coordenadas SERPRO oficiais (fonte: projeção DB ou manifesto).
 * Jobs entregam operation_key; nunca montam idSistema/idServico a partir do frontend.
 */
final class OperationCoordinateResolver
{
    public function __construct(
        private readonly OfficialServiceCatalogManifest $manifest,
    ) {}

    /**
     * @return array{
     *   operation_key: string,
     *   id_sistema: string,
     *   id_servico: string,
     *   versao_sistema: string,
     *   route: SerproFunctionalRoute,
     *   required_proxy_power: string|null,
     *   platform_support: SerproPlatformSupport,
     *   dados_mode: string,
     *   billable_class: string,
     *   is_mutating: bool,
     *   label: string
     * }
     */
    public function resolve(string $operationKey): array
    {
        try {
            $row = SerproServiceCatalogEntry::query()
                ->where('operation_key', $operationKey)
                ->orderByDesc('catalog_version')
                ->first();
        } catch (\Throwable) {
            $row = null;
        }

        if ($row !== null && $row->operation_key) {
            $support = $row->platform_support instanceof SerproPlatformSupport
                ? $row->platform_support
                : (SerproPlatformSupport::tryFrom((string) $row->platform_support)
                    ?? SerproPlatformSupport::Inventoried);
            $route = $row->functional_route instanceof SerproFunctionalRoute
                ? $row->functional_route
                : (SerproFunctionalRoute::tryFrom((string) ($row->functional_route ?? ''))
                    ?? SerproFunctionalRoute::Consultar);

            return [
                'operation_key' => (string) $row->operation_key,
                'id_sistema' => (string) ($row->id_sistema ?: $row->solution_code),
                'id_servico' => (string) ($row->id_servico ?: $row->operation_code),
                'versao_sistema' => (string) ($row->versao_sistema ?: '1.0'),
                'route' => $route,
                'required_proxy_power' => $row->required_proxy_power,
                'platform_support' => $support,
                'dados_mode' => (string) ($row->dados_mode ?: 'JSON_STRING'),
                'billable_class' => $row->billable_class?->value ?? 'DESCONHECIDA',
                'is_mutating' => (bool) $row->is_mutating,
                'label' => (string) $row->label,
            ];
        }

        // Autoridade canônica serpro_operations + versions (plano de controle)
        $canonical = $this->resolveFromCanonicalOperations($operationKey);
        if ($canonical !== null) {
            return $canonical;
        }

        // Fallback manifesto (antes da migration / import / SQLite sem tabela)
        $m = $this->manifest->load();
        $entry = $this->manifest->findByOperationKey($m, $operationKey);
        $support = SerproPlatformSupport::from($entry['platform_support']);
        $route = SerproFunctionalRoute::from($entry['route']);

        return [
            'operation_key' => $entry['operation_key'],
            'id_sistema' => $entry['id_sistema'],
            'id_servico' => $entry['id_servico'],
            'versao_sistema' => $entry['versao_sistema'],
            'route' => $route,
            'required_proxy_power' => $entry['required_proxy_power'],
            'platform_support' => $support,
            'dados_mode' => $entry['dados_mode'],
            'billable_class' => $entry['billable_class'],
            'is_mutating' => (bool) $entry['is_mutating'],
            'label' => $entry['label'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function resolveExecutable(string $operationKey): array
    {
        $coords = $this->resolve($operationKey);
        if (! $coords['platform_support']->isExecutable()) {
            throw new RuntimeException(
                'CAPABILITY_NOT_IMPLEMENTED: operação inventariada sem adapter ('.$operationKey.').'
            );
        }

        return $coords;
    }

    /**
     * @return array{
     *   operation_key: string,
     *   id_sistema: string,
     *   id_servico: string,
     *   versao_sistema: string,
     *   route: SerproFunctionalRoute,
     *   required_proxy_power: string|null,
     *   platform_support: SerproPlatformSupport,
     *   dados_mode: string,
     *   billable_class: string,
     *   is_mutating: bool,
     *   label: string
     * }|null
     */
    private function resolveFromCanonicalOperations(string $operationKey): ?array
    {
        try {
            $op = \Illuminate\Support\Facades\DB::table('serpro_operations')
                ->where('operation_key', $operationKey)
                ->first();
        } catch (\Throwable) {
            return null;
        }

        if ($op === null) {
            return null;
        }

        try {
            $version = \Illuminate\Support\Facades\DB::table('serpro_operation_versions')
                ->where('serpro_operation_id', $op->id)
                ->orderByDesc('id')
                ->first();
        } catch (\Throwable) {
            $version = null;
        }

        // Sem versão efetiva não há coordenadas executáveis — cair no manifesto.
        if ($version === null) {
            return null;
        }

        $route = SerproFunctionalRoute::tryFrom((string) ($version->functional_route ?? ''))
            ?? SerproFunctionalRoute::Consultar;

        // Preferir flags da versão/metadata quando existirem; desconhecido → fail-closed (mutante).
        $isMutating = filter_var($version->is_mutating ?? $op->is_mutating ?? true, FILTER_VALIDATE_BOOL);
        $proxyPower = $version->required_proxy_power ?? $op->required_proxy_power ?? null;
        $supportRaw = (string) ($version->platform_support ?? $op->platform_support ?? '');
        $platformSupport = SerproPlatformSupport::tryFrom($supportRaw)
            ?? SerproPlatformSupport::Inventoried;

        return [
            'operation_key' => (string) $op->operation_key,
            'id_sistema' => (string) ($version->id_sistema ?? $version->system_code ?? ''),
            'id_servico' => (string) ($version->id_servico ?? $version->service_code ?? ''),
            'versao_sistema' => (string) ($version->versao_sistema ?? '1.0'),
            'route' => $route,
            'required_proxy_power' => $proxyPower !== null ? (string) $proxyPower : null,
            'platform_support' => $platformSupport,
            'dados_mode' => (string) ($version->dados_mode ?? 'JSON_STRING'),
            'billable_class' => (string) ($op->consumption_class ?? 'DESCONHECIDA'),
            'is_mutating' => $isMutating,
            'label' => (string) ($op->label ?? $op->operation_key),
        ];
    }
}
