<?php

namespace App\Services\FiscalMonitoring\Surfaces;

use App\Enums\FiscalModuleKey;
use App\Enums\MonitoringChannel;
use App\Enums\MonitoringDocumentPolicy;
use App\Enums\MonitoringOfficialStateSummary;
use App\Enums\MonitoringResultKind;
use App\Enums\SerproOfficialState;
use App\Services\Serpro\Catalog\OfficialServiceCatalogManifest;
use InvalidArgumentException;

/**
 * Registro tipado de todas as superfícies de page-payload-matrix.md.
 */
final class MonitoringSurfaceRegistry
{
    /** @var array<string, MonitoringSurfaceContract>|null */
    private ?array $contracts = null;

    public function __construct(
        private readonly OfficialServiceCatalogManifest $catalog,
        private readonly MonitoringSurfaceCatalogValidator $validator,
    ) {}

    /**
     * @return array<string, MonitoringSurfaceContract>
     */
    public function all(): array
    {
        return $this->ensureLoaded();
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_keys($this->ensureLoaded());
    }

    public function get(string $surfaceKey): MonitoringSurfaceContract
    {
        $all = $this->ensureLoaded();
        if (! isset($all[$surfaceKey])) {
            throw new InvalidArgumentException("surface_key desconhecida: {$surfaceKey}");
        }

        return $all[$surfaceKey];
    }

    public function has(string $surfaceKey): bool
    {
        return isset($this->ensureLoaded()[$surfaceKey]);
    }

    /**
     * Resolve a superfície a partir do módulo de portfolio e submodule opcional.
     */
    public function resolveForModule(FiscalModuleKey $module, ?string $submodule = null): MonitoringSurfaceContract
    {
        $sub = $submodule !== null ? strtoupper(trim($submodule)) : null;

        $key = match ($module) {
            FiscalModuleKey::Dashboard => 'monitoring_dashboard',
            FiscalModuleKey::SimplesMei => match ($sub) {
                null, 'PGDASD' => 'simples_mei_pgdasd',
                'PGMEI' => 'simples_mei_pgmei',
                default => throw new InvalidArgumentException("Submódulo Simples / MEI não disponível: {$sub}"),
            },
            FiscalModuleKey::Dctfweb => match ($sub) {
                'MIT' => 'mit',
                'DCTFWEB', 'DCTF' => 'dctfweb',
                default => 'dctfweb',
            },
            FiscalModuleKey::Fgts => 'fgts',
            FiscalModuleKey::Installments => 'installments',
            FiscalModuleKey::Sitfis => 'sitfis',
            FiscalModuleKey::Mailbox => 'mailbox_list',
            FiscalModuleKey::Declarations => 'declarations',
            FiscalModuleKey::Guides => 'guides',
            FiscalModuleKey::Registrations => 'registrations',
            FiscalModuleKey::TaxProcesses => 'tax_processes',
        };

        return $this->get($key);
    }

    /**
     * @return array<string, MonitoringSurfaceContract>
     */
    private function ensureLoaded(): array
    {
        if ($this->contracts !== null) {
            return $this->contracts;
        }

        $manifest = $this->catalog->load();
        $index = $this->validator->indexByOperationKey($manifest);
        $contracts = $this->buildContracts($index);
        $this->validator->assertValid($contracts, $manifest);
        $this->contracts = $contracts;

        return $this->contracts;
    }

    /**
     * @param  array<string, array<string, mixed>>  $index
     * @return array<string, MonitoringSurfaceContract>
     */
    private function buildContracts(array $index): array
    {
        $list = [
            new MonitoringSurfaceContract(
                surfaceKey: 'monitoring_dashboard',
                routePattern: '/monitoring',
                responsibility: 'Priorizar problemas acionáveis da carteira, sem criar conclusão fiscal nova',
                channel: MonitoringChannel::Aggregate,
                operationKeys: [],
                officialState: MonitoringOfficialStateSummary::NotApplicable,
                resultKind: MonitoringResultKind::Aggregate,
                allowsDocument: false,
                documentPolicy: MonitoringDocumentPolicy::Never,
                sourceLabel: 'Dashboard de monitoramento',
            ),
            new MonitoringSurfaceContract(
                surfaceKey: 'simples_mei_pgdasd',
                routePattern: '/monitoring/simples-mei/pgdasd',
                responsibility: 'Declarações PGDAS-D e seus documentos',
                channel: MonitoringChannel::Integra,
                operationKeys: [
                    // Superfície de monitoramento: apenas consultas oficiais 13–16 (sem emissão)
                    'pgdasd.consdeclaracao',
                    'pgdasd.consultimadecrec',
                    'pgdasd.consdecrec',
                    'pgdasd.consextrato',
                ],
                officialState: MonitoringOfficialStateSummary::Production,
                resultKind: MonitoringResultKind::Pdf,
                allowsDocument: true,
                documentPolicy: MonitoringDocumentPolicy::WhenArtifact,
                sourceLabel: 'PGDAS-D',
            ),
            new MonitoringSurfaceContract(
                surfaceKey: 'simples_mei_pgmei',
                routePattern: '/monitoring/simples-mei/pgmei',
                responsibility: 'Dívida ativa do MEI por ano-calendário, sem emitir DAS',
                channel: MonitoringChannel::Integra,
                operationKeys: [
                    'pgmei.dividaativa',
                ],
                officialState: MonitoringOfficialStateSummary::Production,
                resultKind: MonitoringResultKind::Structured,
                allowsDocument: false,
                documentPolicy: MonitoringDocumentPolicy::Never,
                sourceLabel: 'PGMEI',
            ),
            new MonitoringSurfaceContract(
                surfaceKey: 'dctfweb',
                routePattern: '/monitoring/dctfweb/dctfweb',
                responsibility: 'Declaração DCTFWeb e artefatos oficiais, sem misturar MIT',
                channel: MonitoringChannel::Integra,
                operationKeys: [
                    'dctfweb.consrecibo',
                    'dctfweb.consdeccompleta',
                    'dctfweb.consxmldeclaracao',
                    'dctfweb.gerarguia',
                    'dctfweb.gerarguiaandamento',
                ],
                officialState: MonitoringOfficialStateSummary::Production,
                resultKind: MonitoringResultKind::Pdf,
                allowsDocument: true,
                documentPolicy: MonitoringDocumentPolicy::WhenArtifact,
                sourceLabel: 'DCTFWeb',
            ),
            new MonitoringSurfaceContract(
                surfaceKey: 'mit',
                routePattern: '/monitoring/dctfweb/mit',
                responsibility: 'Apurações e encerramento MIT, sem reutilizar colunas da DCTFWeb',
                channel: MonitoringChannel::Integra,
                operationKeys: [
                    'mit.listaapuracoes',
                    'mit.consapuracao',
                    'mit.situacaoenc',
                ],
                officialState: MonitoringOfficialStateSummary::Production,
                resultKind: MonitoringResultKind::Structured,
                allowsDocument: false,
                documentPolicy: MonitoringDocumentPolicy::Never,
                sourceLabel: 'MIT',
            ),
            new MonitoringSurfaceContract(
                surfaceKey: 'fgts',
                routePattern: '/monitoring/fgts',
                responsibility: 'Fechamento e totalizações FGTS/eSocial',
                channel: MonitoringChannel::Esocial,
                operationKeys: [],
                officialState: MonitoringOfficialStateSummary::NotApplicable,
                resultKind: MonitoringResultKind::Structured,
                allowsDocument: false,
                documentPolicy: MonitoringDocumentPolicy::Never,
                sourceLabel: 'FGTS / eSocial',
            ),
            new MonitoringSurfaceContract(
                surfaceKey: 'installments',
                routePattern: '/monitoring/installments',
                responsibility: 'Pedidos, saldo, parcelas, pagamentos e documento da parcela por modalidade',
                channel: MonitoringChannel::Integra,
                operationKeys: $this->installmentProductionKeys($index),
                officialState: MonitoringOfficialStateSummary::Production,
                resultKind: MonitoringResultKind::Pdf,
                allowsDocument: true,
                documentPolicy: MonitoringDocumentPolicy::WhenArtifact,
                sourceLabel: 'Parcelamentos',
            ),
            new MonitoringSurfaceContract(
                surfaceKey: 'sitfis',
                routePattern: '/monitoring/sitfis',
                responsibility: 'Estado da consulta assíncrona e relatório oficial',
                channel: MonitoringChannel::Integra,
                operationKeys: [
                    'sitfis.solicitar_protocolo',
                    'sitfis.emitir_relatorio',
                ],
                officialState: MonitoringOfficialStateSummary::Production,
                resultKind: MonitoringResultKind::AsyncPdf,
                allowsDocument: true,
                documentPolicy: MonitoringDocumentPolicy::AsyncWhenReady,
                sourceLabel: 'SITFIS — Relatório de Situação Fiscal',
            ),
            new MonitoringSurfaceContract(
                surfaceKey: 'mailbox_list',
                routePattern: '/monitoring/mailbox',
                responsibility: 'Carteira de mensagens e prazos por cliente',
                channel: MonitoringChannel::Integra,
                operationKeys: [
                    'caixa_postal.lista',
                    'caixa_postal.indicador',
                    'dte.consultar',
                ],
                officialState: MonitoringOfficialStateSummary::Production,
                resultKind: MonitoringResultKind::Structured,
                allowsDocument: false,
                documentPolicy: MonitoringDocumentPolicy::Never,
                sourceLabel: 'Caixa Postal',
            ),
            new MonitoringSurfaceContract(
                surfaceKey: 'mailbox_detail',
                routePattern: '/monitoring/mailbox/:id',
                responsibility: 'Conteúdo oficial de uma mensagem específica',
                channel: MonitoringChannel::Integra,
                operationKeys: [
                    'caixa_postal.detalhe',
                ],
                officialState: MonitoringOfficialStateSummary::Production,
                resultKind: MonitoringResultKind::Structured,
                allowsDocument: false,
                documentPolicy: MonitoringDocumentPolicy::Never,
                sourceLabel: 'Caixa Postal — detalhe',
            ),
            new MonitoringSurfaceContract(
                surfaceKey: 'declarations',
                routePattern: '/monitoring/declarations',
                responsibility: 'Agenda e entrega consolidadas, delegando evidência à obrigação de origem',
                channel: MonitoringChannel::Aggregate,
                operationKeys: [],
                officialState: MonitoringOfficialStateSummary::NotApplicable,
                resultKind: MonitoringResultKind::Aggregate,
                allowsDocument: true,
                documentPolicy: MonitoringDocumentPolicy::WhenArtifact,
                sourceLabel: 'Integra Contador + agenda fiscal',
            ),
            new MonitoringSurfaceContract(
                surfaceKey: 'guides',
                routePattern: '/monitoring/guides',
                responsibility: 'Guias/documentos de arrecadação e confirmação oficial de pagamento',
                channel: MonitoringChannel::Aggregate,
                operationKeys: [
                    'pagtoweb.pagamentos',
                    'pagtoweb.comparrecadacao',
                    'sicalc.consolidargerardarf',
                    'sicalc.gerardarfcodbarra',
                ],
                officialState: MonitoringOfficialStateSummary::Production,
                resultKind: MonitoringResultKind::Pdf,
                allowsDocument: true,
                documentPolicy: MonitoringDocumentPolicy::WhenArtifact,
                sourceLabel: 'Guias',
            ),
            new MonitoringSurfaceContract(
                surfaceKey: 'registrations',
                routePattern: '/monitoring/registrations',
                responsibility: 'Vínculos cadastrais PNR/Redesim',
                channel: MonitoringChannel::Integra,
                operationKeys: [
                    'pnr_contador.consultar_vinculos',
                ],
                officialState: MonitoringOfficialStateSummary::Production,
                resultKind: MonitoringResultKind::Structured,
                allowsDocument: false,
                documentPolicy: MonitoringDocumentPolicy::Never,
                sourceLabel: 'Cadastro e vínculos',
            ),
            new MonitoringSurfaceContract(
                surfaceKey: 'tax_processes',
                routePattern: '/monitoring/tax-processes',
                responsibility: 'Processos do contribuinte',
                channel: MonitoringChannel::Integra,
                operationKeys: [
                    'eprocesso.consultar_por_interessado',
                ],
                officialState: MonitoringOfficialStateSummary::Production,
                resultKind: MonitoringResultKind::Structured,
                allowsDocument: false,
                documentPolicy: MonitoringDocumentPolicy::Never,
                sourceLabel: 'Processos fiscais',
            ),
            new MonitoringSurfaceContract(
                surfaceKey: 'client_detail',
                routePattern: '/monitoring/clients/:clientId',
                responsibility: 'Consolidar módulos de um cliente sem duplicar payload',
                channel: MonitoringChannel::Aggregate,
                operationKeys: [],
                officialState: MonitoringOfficialStateSummary::NotApplicable,
                resultKind: MonitoringResultKind::Aggregate,
                allowsDocument: false,
                documentPolicy: MonitoringDocumentPolicy::Never,
                sourceLabel: 'Detalhe do cliente',
            ),
        ];

        $map = [];
        foreach ($list as $contract) {
            $map[$contract->surfaceKey] = $contract;
        }

        return $map;
    }

    /**
     * Famílias produtivas de parcelamento (exclui PAEX/SIPADE em prospecção).
     *
     * @param  array<string, array<string, mixed>>  $index
     * @return list<string>
     */
    private function installmentProductionKeys(array $index): array
    {
        $suffixes = [
            'pedidosparc',
            'obterparc',
            'parcelasparagerar',
            'detpagtoparc',
            'gerardas',
        ];
        $prefixes = [
            'parcsn.',
            'parcsn_esp.',
            'pertsn.',
            'relpsn.',
            'parcmei.',
            'parcmei_esp.',
            'pertmei.',
            'relpmei.',
        ];

        $keys = [];
        foreach ($index as $opKey => $entry) {
            if (($entry['official_state'] ?? '') !== SerproOfficialState::Production->value) {
                continue;
            }
            $matchedPrefix = false;
            foreach ($prefixes as $prefix) {
                if (str_starts_with($opKey, $prefix)) {
                    $matchedPrefix = true;
                    break;
                }
            }
            if (! $matchedPrefix) {
                continue;
            }
            foreach ($suffixes as $suffix) {
                if (str_ends_with($opKey, '.'.$suffix)) {
                    $keys[] = $opKey;
                    break;
                }
            }
        }

        sort($keys);

        return $keys;
    }
}
