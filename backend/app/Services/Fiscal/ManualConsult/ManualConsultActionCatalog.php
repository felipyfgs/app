<?php

namespace App\Services\Fiscal\ManualConsult;

use App\Enums\SerproOfficialState;
use App\Enums\SerproPlatformSupport;
use App\Services\FiscalMonitoring\Surfaces\MonitoringSurfaceRegistry;
use App\Services\Serpro\Catalog\OfficialServiceCatalogManifest;
use InvalidArgumentException;

/**
 * Inventário canônico de ações de consulta manual (onda 1).
 *
 * Fonte: MonitoringSurfaceRegistry ∩ catálogo (PRODUCTION + IMPLEMENTED + !mutating)
 * + POSTs de consult já existentes (DEFIS/CCMEI/REGIME/SICALC 52/PAGTOWEB count).
 * Handlers ausentes ficam com hasHandler=false → elegibilidade adapter_missing.
 */
final class ManualConsultActionCatalog
{
    /** @var array<string, ManualConsultActionDefinition>|null */
    private ?array $byActionId = null;

    public function __construct(
        private readonly MonitoringSurfaceRegistry $surfaces,
        private readonly OfficialServiceCatalogManifest $catalog,
    ) {}

    /**
     * @return list<ManualConsultActionDefinition>
     */
    public function all(): array
    {
        return array_values($this->ensureLoaded());
    }

    public function get(string $actionId): ManualConsultActionDefinition
    {
        $all = $this->ensureLoaded();
        if (! isset($all[$actionId])) {
            throw new InvalidArgumentException("action_id desconhecida: {$actionId}");
        }

        return $all[$actionId];
    }

    public function has(string $actionId): bool
    {
        return isset($this->ensureLoaded()[$actionId]);
    }

    public function findByOperationKey(string $operationKey): ?ManualConsultActionDefinition
    {
        foreach ($this->ensureLoaded() as $def) {
            if ($def->operationKey === $operationKey) {
                return $def;
            }
        }

        return null;
    }

    /**
     * @return array<string, ManualConsultActionDefinition>
     */
    private function ensureLoaded(): array
    {
        if ($this->byActionId !== null) {
            return $this->byActionId;
        }

        $manifest = $this->catalog->load();
        $entries = [];
        foreach ($manifest['entries'] as $entry) {
            $key = (string) ($entry['operation_key'] ?? '');
            if ($key !== '') {
                $entries[$key] = $entry;
            }
        }

        $handlers = $this->handlerMap();
        $params = $this->paramsMap();
        $runCodes = $this->runCodesMap();
        $asyncKeys = [
            'sitfis.solicitar_protocolo' => true,
            'sitfis.emitir_relatorio' => true,
        ];

        /** @var array<string, ManualConsultActionDefinition> $map */
        $map = [];

        // 1) Superfícies do registry (apenas ops de leitura elegíveis no catálogo).
        foreach ($this->surfaces->all() as $surface) {
            foreach ($surface->operationKeys as $opKey) {
                $entry = $entries[$opKey] ?? null;
                if ($entry === null || ! $this->isReadableProduction($entry)) {
                    continue;
                }
                $actionId = $surface->surfaceKey.':'.$opKey;
                $map[$actionId] = $this->buildDefinition(
                    actionId: $actionId,
                    opKey: $opKey,
                    entry: $entry,
                    surfaceKey: $surface->surfaceKey,
                    moduleRoute: $surface->routePattern,
                    handlers: $handlers,
                    params: $params,
                    runCodes: $runCodes,
                    asyncKeys: $asyncKeys,
                );
            }
        }

        // 2) Ações com POST de consult fora do registry (DEFIS/CCMEI/REGIME/SICALC 52/count).
        foreach ($this->extraPostBackedKeys() as $meta) {
            $opKey = $meta['operation_key'];
            $entry = $entries[$opKey] ?? null;
            if ($entry === null || ! $this->isReadableProduction($entry)) {
                continue;
            }
            $actionId = $meta['surface_key'].':'.$opKey;
            if (isset($map[$actionId])) {
                continue;
            }
            $map[$actionId] = $this->buildDefinition(
                actionId: $actionId,
                opKey: $opKey,
                entry: $entry,
                surfaceKey: $meta['surface_key'],
                moduleRoute: $meta['route'],
                handlers: $handlers,
                params: $params,
                runCodes: $runCodes,
                asyncKeys: $asyncKeys,
            );
        }

        ksort($map);
        $this->byActionId = $map;

        return $this->byActionId;
    }

    /**
     * @param  array<string, mixed>  $entry
     * @param  array<string, string>  $handlers
     * @param  array<string, list<array{name: string, type: string, required: bool, label: string, pattern?: string|null}>>  $params
     * @param  array<string, array{system: string, service: string, operation: string}>  $runCodes
     * @param  array<string, bool>  $asyncKeys
     */
    private function buildDefinition(
        string $actionId,
        string $opKey,
        array $entry,
        string $surfaceKey,
        string $moduleRoute,
        array $handlers,
        array $params,
        array $runCodes,
        array $asyncKeys,
    ): ManualConsultActionDefinition {
        $handler = $this->resolveHandler($opKey, $handlers);
        $powers = $this->normalizePowers($entry);

        return new ManualConsultActionDefinition(
            actionId: $actionId,
            operationKey: $opKey,
            label: (string) ($entry['label'] ?? $opKey),
            surfaceKey: $surfaceKey,
            moduleKey: (string) ($entry['monitoring_module'] ?? 'unknown'),
            featureModule: $this->featureModuleFor((string) ($entry['monitoring_module'] ?? '')),
            handler: $handler,
            hasHandler: $handler !== 'none',
            paramsSchema: $this->resolveParamsSchema($opKey, $params),
            requiredProxyPowers: $powers,
            runCodes: $runCodes[$opKey] ?? null,
            moduleRoute: $moduleRoute,
            async: (bool) ($asyncKeys[$opKey] ?? false),
        );
    }

    /**
     * @param  array<string, string>  $explicit
     */
    private function resolveHandler(string $opKey, array $explicit): string
    {
        if (isset($explicit[$opKey])) {
            return $explicit[$opKey];
        }

        if (preg_match(
            '/^(parcsn|parcsn_esp|parcmei|parcmei_esp|pertsn|pertmei|relpsn|relpmei)\.(pedidosparc|obterparc|parcelasparagerar|detpagtoparc)$/',
            $opKey,
        ) === 1) {
            return 'installment_read';
        }

        return 'none';
    }

    /**
     * @param  array<string, list<array{name: string, type: string, required: bool, label: string, pattern?: string|null}>>  $explicit
     * @return list<array{name: string, type: string, required: bool, label: string, pattern?: string|null}>
     */
    private function resolveParamsSchema(string $opKey, array $explicit): array
    {
        if (isset($explicit[$opKey])) {
            return $explicit[$opKey];
        }

        if (preg_match(
            '/^(parcsn|parcsn_esp|parcmei|parcmei_esp|pertsn|pertmei|relpsn|relpmei)\.(pedidosparc|obterparc|parcelasparagerar|detpagtoparc)$/',
            $opKey,
        ) === 1) {
            return [[
                'name' => 'modality',
                'type' => 'string',
                'required' => false,
                'label' => 'Modalidade (opcional; deve bater com a ação)',
            ]];
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    private function isReadableProduction(array $entry): bool
    {
        if ((bool) ($entry['is_mutating'] ?? true)) {
            return false;
        }
        $state = (string) ($entry['official_state'] ?? '');
        if ($state !== SerproOfficialState::Production->value) {
            return false;
        }
        $support = (string) ($entry['platform_support'] ?? '');

        return in_array($support, [
            SerproPlatformSupport::Implemented->value,
            SerproPlatformSupport::ProductionValidated->value,
        ], true);
    }

    /**
     * @param  array<string, mixed>  $entry
     * @return list<string>
     */
    private function normalizePowers(array $entry): array
    {
        $list = $entry['required_proxy_powers'] ?? null;
        if (is_array($list) && $list !== []) {
            return array_values(array_filter(array_map(
                static fn ($p) => is_string($p) ? trim($p) : '',
                $list,
            )));
        }
        $single = $entry['required_proxy_power'] ?? null;
        if (is_string($single) && trim($single) !== '') {
            // catálogo pode usar "00076 00188"
            $parts = preg_split('/\s+/', trim($single)) ?: [];

            return array_values(array_filter($parts));
        }

        return [];
    }

    private function featureModuleFor(string $monitoringModule): ?string
    {
        return match ($monitoringModule) {
            'simples_mei' => 'simples_mei',
            'dctfweb' => 'dctfweb_mit',
            'installments' => 'parcelamentos',
            'sitfis' => 'sitfis',
            'mailbox' => 'mailbox',
            'guides' => 'guias',
            'declarations' => 'declaracoes',
            default => null,
        };
    }

    /**
     * Mapa operation_key → handler id (despacho).
     *
     * @return array<string, string>
     */
    private function handlerMap(): array
    {
        return [
            // PGDASD
            'pgdasd.consdeclaracao' => 'pgdasd_documents',
            'pgdasd.consultimadecrec' => 'pgdasd_documents',
            'pgdasd.consdecrec' => 'pgdasd_documents',
            'pgdasd.consextrato' => 'pgdasd_extract',
            // PGMEI
            'pgmei.dividaativa' => 'pgmei_debt',
            // DEFIS
            'defis.consdeclaracao' => 'defis_list',
            'defis.consultimadecrec' => 'defis_latest',
            'defis.consdecrec' => 'defis_specific',
            // CCMEI
            'ccmei.dadosccmei' => 'ccmei_data',
            'ccmei.ccmeisitcadastral' => 'ccmei_status',
            // REGIME
            'regimeapuracao.consultaranoscalendarios' => 'regime_calendar',
            'regimeapuracao.consultaropcaoregime' => 'regime_option',
            'regimeapuracao.consultarresolucao' => 'regime_resolution',
            // DCTFWEB leitura
            'dctfweb.consrecibo' => 'dctfweb_read',
            'dctfweb.consdeccompleta' => 'dctfweb_read',
            'dctfweb.consxmldeclaracao' => 'dctfweb_read',
            // MIT leitura
            'mit.listaapuracoes' => 'mit_lista',
            'mit.consapuracao' => 'mit_read',
            'mit.situacaoenc' => 'mit_read',
            // SITFIS (async)
            'sitfis.solicitar_protocolo' => 'sitfis_refresh',
            'sitfis.emitir_relatorio' => 'sitfis_refresh',
            // Mailbox / DTE
            'caixa_postal.lista' => 'mailbox_list',
            'caixa_postal.indicador' => 'mailbox_indicator',
            'caixa_postal.detalhe' => 'mailbox_detail',
            'dte.consultar' => 'dte_status',
            // Guias leitura
            'pagtoweb.pagamentos' => 'pagtoweb_list',
            'pagtoweb.contaconsdocarrpg' => 'pagtoweb_count',
            'pagtoweb.comparrecadacao' => 'none', // sem POST dedicado na onda 1
            'sicalc.consultaapoioreceitas' => 'sicalc_support',
            // Parcelamentos: resolveHandler() por prefixo/sufixo (sem listar 32 keys).
            // PNR / e-Processo
            'pnr_contador.consultar_vinculos' => 'registrations_refresh',
            'eprocesso.consultar_por_interessado' => 'tax_process_refresh',
        ];
    }

    /**
     * @return array<string, list<array{name: string, type: string, required: bool, label: string, pattern?: string|null}>>
     */
    private function paramsMap(): array
    {
        $year = [['name' => 'year', 'type' => 'integer', 'required' => true, 'label' => 'Ano-calendário']];
        $period = [['name' => 'period_key', 'type' => 'string', 'required' => true, 'label' => 'Competência (AAAA-MM)', 'pattern' => '^\d{4}-(0[1-9]|1[0-2])$']];
        $revenue = [['name' => 'codigo_receita', 'type' => 'string', 'required' => true, 'label' => 'Código da receita']];
        $filters = [['name' => 'filters', 'type' => 'object', 'required' => true, 'label' => 'Filtros de arrecadação']];
        $refId = [['name' => 'reference_id', 'type' => 'integer', 'required' => true, 'label' => 'Referência da declaração (lista DEFIS)']];
        $messageId = [['name' => 'message_id', 'type' => 'string', 'required' => true, 'label' => 'Identificador da mensagem']];
        $pgdasdDocs = [
            ['name' => 'period_key', 'type' => 'string', 'required' => true, 'label' => 'Competência (AAAA-MM)', 'pattern' => '^\d{4}-(0[1-9]|1[0-2])$'],
            ['name' => 'declaration_number', 'type' => 'string', 'required' => false, 'label' => 'Número da declaração'],
        ];
        $listaMit = [
            ['name' => 'anoApuracao', 'type' => 'integer', 'required' => false, 'label' => 'Ano de apuração'],
            ['name' => 'mesApuracao', 'type' => 'integer', 'required' => false, 'label' => 'Mês de apuração'],
        ];

        return [
            'pgdasd.consdeclaracao' => $pgdasdDocs,
            'pgdasd.consultimadecrec' => $pgdasdDocs,
            'pgdasd.consdecrec' => $pgdasdDocs,
            'pgdasd.consextrato' => $period,
            'pgmei.dividaativa' => $year,
            'defis.consultimadecrec' => $year,
            'defis.consdecrec' => $refId,
            'regimeapuracao.consultaropcaoregime' => $year,
            'regimeapuracao.consultarresolucao' => $year,
            'dctfweb.consrecibo' => $period,
            'dctfweb.consdeccompleta' => $period,
            'dctfweb.consxmldeclaracao' => $period,
            'mit.consapuracao' => $period,
            'mit.situacaoenc' => $period,
            'mit.listaapuracoes' => $listaMit,
            'caixa_postal.detalhe' => $messageId,
            'pagtoweb.pagamentos' => $filters,
            'pagtoweb.contaconsdocarrpg' => $filters,
            'sicalc.consultaapoioreceitas' => $revenue,
            // Parcelamentos: resolveParamsSchema() por regex.
        ];
    }

    /**
     * @return array<string, array{system: string, service: string, operation: string}>
     */
    private function runCodesMap(): array
    {
        return [
            'pgdasd.consdeclaracao' => ['system' => 'INTEGRA_SN', 'service' => 'PGDASD', 'operation' => 'CONSULTAR_DECLARACAO'],
            'pgdasd.consultimadecrec' => ['system' => 'INTEGRA_SN', 'service' => 'PGDASD', 'operation' => 'CONSULTAR_ULTIMA_DECLARACAO_RECIBO'],
            'pgdasd.consdecrec' => ['system' => 'INTEGRA_SN', 'service' => 'PGDASD', 'operation' => 'CONSULTAR_RECIBO'],
            'pgdasd.consextrato' => ['system' => 'INTEGRA_SN', 'service' => 'PGDASD', 'operation' => 'CONSULTAR_EXTRATO'],
            'pgmei.dividaativa' => ['system' => 'INTEGRA_MEI', 'service' => 'PGMEI', 'operation' => 'MONITOR'],
            'defis.consdeclaracao' => ['system' => 'INTEGRA_SN', 'service' => 'DEFIS', 'operation' => 'CONSULTAR'],
            'defis.consultimadecrec' => ['system' => 'INTEGRA_SN', 'service' => 'DEFIS', 'operation' => 'CONSULTAR_ULTIMA_DECLARACAO_RECIBO'],
            'defis.consdecrec' => ['system' => 'INTEGRA_SN', 'service' => 'DEFIS', 'operation' => 'CONSULTAR_DECLARACAO_RECIBO'],
            'ccmei.dadosccmei' => ['system' => 'INTEGRA_MEI', 'service' => 'CCMEI', 'operation' => 'MONITOR'],
            'ccmei.ccmeisitcadastral' => ['system' => 'INTEGRA_MEI', 'service' => 'CCMEI', 'operation' => 'CONSULTAR_SITUACAO_CADASTRAL'],
            'regimeapuracao.consultaranoscalendarios' => ['system' => 'INTEGRA_SN', 'service' => 'REGIME_APURACAO', 'operation' => 'CONSULTAR_ANOS_CALENDARIOS'],
            'regimeapuracao.consultaropcaoregime' => ['system' => 'INTEGRA_SN', 'service' => 'REGIME_APURACAO', 'operation' => 'CONSULTAR'],
            'regimeapuracao.consultarresolucao' => ['system' => 'INTEGRA_SN', 'service' => 'REGIME_APURACAO', 'operation' => 'CONSULTAR_RESOLUCAO'],
            'dctfweb.consrecibo' => ['system' => 'INTEGRA_DCTFWEB', 'service' => 'DCTFWEB', 'operation' => 'CONSULTAR_RECIBO'],
            'dctfweb.consdeccompleta' => ['system' => 'INTEGRA_DCTFWEB', 'service' => 'DCTFWEB', 'operation' => 'CONSULTAR_DECLARACAO'],
            'dctfweb.consxmldeclaracao' => ['system' => 'INTEGRA_DCTFWEB', 'service' => 'DCTFWEB', 'operation' => 'CONSULTAR_XML'],
            'mit.listaapuracoes' => ['system' => 'INTEGRA_MIT', 'service' => 'MIT', 'operation' => 'LISTAR_APURACOES'],
            'mit.consapuracao' => ['system' => 'INTEGRA_MIT', 'service' => 'MIT', 'operation' => 'CONSULTAR_APURACAO'],
            'mit.situacaoenc' => ['system' => 'INTEGRA_MIT', 'service' => 'MIT', 'operation' => 'CONSULTAR_SITUACAO'],
            'caixa_postal.lista' => ['system' => 'INTEGRA_CAIXAPOSTAL', 'service' => 'CAIXA_POSTAL', 'operation' => 'LISTAR'],
            'caixa_postal.indicador' => ['system' => 'INTEGRA_CAIXAPOSTAL', 'service' => 'CAIXA_POSTAL', 'operation' => 'INDICADOR'],
            'caixa_postal.detalhe' => ['system' => 'INTEGRA_CAIXAPOSTAL', 'service' => 'CAIXA_POSTAL', 'operation' => 'DETALHE'],
            'dte.consultar' => ['system' => 'INTEGRA_CAIXAPOSTAL', 'service' => 'DTE', 'operation' => 'CONSULTAR'],
        ];
    }

    /**
     * Ops com POST de consult que não estão (ou não só) no registry de superfície.
     *
     * @return list<array{operation_key: string, surface_key: string, route: string}>
     */
    private function extraPostBackedKeys(): array
    {
        return [
            ['operation_key' => 'defis.consdeclaracao', 'surface_key' => 'simples_mei_defis', 'route' => '/monitoring/simples-mei'],
            ['operation_key' => 'defis.consultimadecrec', 'surface_key' => 'simples_mei_defis', 'route' => '/monitoring/simples-mei'],
            ['operation_key' => 'defis.consdecrec', 'surface_key' => 'simples_mei_defis', 'route' => '/monitoring/simples-mei'],
            ['operation_key' => 'ccmei.dadosccmei', 'surface_key' => 'simples_mei_ccmei', 'route' => '/monitoring/simples-mei'],
            ['operation_key' => 'ccmei.ccmeisitcadastral', 'surface_key' => 'simples_mei_ccmei', 'route' => '/monitoring/simples-mei'],
            ['operation_key' => 'regimeapuracao.consultaranoscalendarios', 'surface_key' => 'simples_mei_regime', 'route' => '/monitoring/simples-mei'],
            ['operation_key' => 'regimeapuracao.consultaropcaoregime', 'surface_key' => 'simples_mei_regime', 'route' => '/monitoring/simples-mei'],
            ['operation_key' => 'regimeapuracao.consultarresolucao', 'surface_key' => 'simples_mei_regime', 'route' => '/monitoring/simples-mei'],
            ['operation_key' => 'sicalc.consultaapoioreceitas', 'surface_key' => 'guides', 'route' => '/monitoring/guides'],
            ['operation_key' => 'pagtoweb.contaconsdocarrpg', 'surface_key' => 'guides', 'route' => '/monitoring/guides'],
        ];
    }
}
