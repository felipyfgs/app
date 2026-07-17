<?php

namespace App\Services\FiscalMonitoring\ModulePortfolio;

use App\Domain\Cnpj;
use App\DTO\Fiscal\FiscalDocumentDescriptorDto;
use App\DTO\Fiscal\Module\ModuleAgendaItemDto;
use App\DTO\Fiscal\Module\ModuleCategorySummaryDto;
use App\DTO\Fiscal\Module\ModuleClientRowDto;
use App\DTO\Fiscal\Module\ModuleCountersDto;
use App\DTO\Fiscal\Module\ModuleOverviewDto;
use App\DTO\Fiscal\Module\ModulePortfolioFilters;
use App\Enums\DocumentUnavailableReason;
use App\Enums\FiscalCoverage;
use App\Enums\FiscalDataOrigin;
use App\Enums\FiscalLinkStatus;
use App\Enums\FiscalModuleKey;
use App\Enums\FiscalSituation;
use App\Enums\MonitoringDocumentPolicy;
use App\Enums\MonitoringResultKind;
use App\Enums\TaxInstallmentParcelStatus;
use App\Models\Client;
use App\Models\DctfwebDeclaration;
use App\Models\FgtsCompetenceStatus;
use App\Models\FiscalCategory;
use App\Models\FiscalCompetence;
use App\Models\FiscalEvidenceArtifact;
use App\Models\FiscalFinding;
use App\Models\FiscalPendingItem;
use App\Models\FiscalSnapshot;
use App\Models\MailboxContributorState;
use App\Models\MailboxMessage;
use App\Models\MitApuracao;
use App\Models\Office;
use App\Models\OfficeFiscalCategoryLink;
use App\Models\TaxGuide;
use App\Models\TaxInstallmentOrder;
use App\Models\TaxInstallmentParcel;
use App\Models\TaxObligationProjection;
use App\Services\Fiscal\SimplesMei\Pgdasd\PgdasdMonitoringQueryService;
use App\Services\FiscalMonitoring\Surfaces\MonitoringSurfaceContract;
use App\Services\FiscalMonitoring\Surfaces\MonitoringSurfaceRegistry;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Pagination\LengthAwarePaginator as ConcretePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Read model tenant-scoped de overview + carteira por módulo.
 * Contadores e lista compartilham o mesmo escopo normalizado (filtros SQL).
 */
final class ModulePortfolioQueryService
{
    public function __construct(
        private readonly DataOriginResolver $dataOrigin,
        private readonly MonitoringSurfaceRegistry $surfaces,
    ) {}

    public function overview(
        Office $office,
        FiscalModuleKey $module,
        ModulePortfolioFilters $filters,
    ): ModuleOverviewDto {
        $origin = $this->dataOrigin->resolve($office);
        $categories = $this->moduleCategories($module);
        $surface = $this->surfaces->resolveForModule($module, $filters->submodule);

        // Contadores + total no mesmo agrupamento (sem filtro de situation).
        $counters = $this->aggregateCounters($office, $module, $filters);
        $total = $counters->total();
        $agenda = $this->buildAgenda($office, $module, $filters);
        $categorySummaries = $this->categorySummaries($office, $categories, $filters);
        $asOf = $this->latestObservedAt($office, $module, $filters);
        $coverage = $this->moduleDefaultCoverage($categories);

        return new ModuleOverviewDto(
            moduleKey: $module,
            dataOrigin: $origin,
            coverage: $coverage,
            sourceLabel: $module->label(),
            asOf: $asOf,
            totalClients: $total,
            counters: $counters,
            agenda: $agenda,
            categories: $categorySummaries,
            metrics: $this->moduleMetrics($office, $module, $filters, $total),
            surface: $surface->toPublicArray(),
        );
    }

    /**
     * @return LengthAwarePaginator<int, ModuleClientRowDto>
     */
    public function clients(
        Office $office,
        FiscalModuleKey $module,
        ModulePortfolioFilters $filters,
    ): LengthAwarePaginator {
        $origin = $this->dataOrigin->resolve($office);
        $idsQuery = $this->scopedClientIdsQuery($office, $module, $filters);

        $sortColumn = match ($filters->sort) {
            'display_name' => 'clients.display_name',
            'id' => 'clients.id',
            'situation' => 'portfolio_situation',
            'last_consulted_at' => 'portfolio_last_consulted_at',
            'competence' => 'portfolio_competence',
            default => 'clients.legal_name',
        };

        $pageQuery = Client::query()
            ->withoutGlobalScopes()
            ->from('clients')
            ->whereIn('clients.id', $idsQuery)
            ->where('clients.office_id', $office->id)
            ->select('clients.*')
            ->selectSub(
                $this->situationSubquery($office, $module, $filters),
                'portfolio_situation',
            )
            ->selectSub(
                $this->lastConsultedSubquery($office, $module, $filters),
                'portfolio_last_consulted_at',
            )
            ->selectSub(
                $this->competenceSubquery($office, $module, $filters),
                'portfolio_competence',
            )
            ->orderBy($sortColumn, $filters->sortDirection)
            ->orderBy('clients.id');

        /** @var LengthAwarePaginator<int, Client> $paginator */
        $paginator = $pageQuery->paginate($filters->perPage, ['*'], 'page', $filters->page);

        $clients = $paginator->getCollection();
        $clientIds = $clients->pluck('id')->map(fn ($id) => (int) $id)->all();

        $matrixCnpjs = $this->loadMatrixCnpjs($office, $clientIds);
        $coverages = $this->loadCoverages($office, $module, $clientIds, $filters);
        $details = $this->loadModuleDetails($office, $module, $clientIds, $filters);
        $deadlines = $this->loadNextDeadlines($office, $module, $clientIds, $filters);
        $documents = $this->loadDocuments($office, $module, $filters, $clientIds);

        $rows = $clients->map(function (Client $client) use (
            $module,
            $origin,
            $matrixCnpjs,
            $coverages,
            $details,
            $deadlines,
            $documents,
        ): ModuleClientRowDto {
            $id = (int) $client->id;
            $situation = (string) ($client->getAttribute('portfolio_situation') ?? FiscalSituation::Unknown->value);
            $competence = $client->getAttribute('portfolio_competence');
            $lastConsulted = $client->getAttribute('portfolio_last_consulted_at');
            $cnpj = $matrixCnpjs[$id] ?? null;
            $coverage = $coverages[$id] ?? FiscalCoverage::Unknown->value;
            $detail = $details[$id] ?? ['module_key' => $module->value];
            $deadline = $deadlines[$id] ?? null;

            return new ModuleClientRowDto(
                moduleKey: $module,
                clientId: $id,
                legalName: (string) $client->legal_name,
                displayName: $client->display_name,
                cnpjMasked: $this->maskCnpj($cnpj ?? $client->root_cnpj),
                rootCnpjMasked: $this->maskRootCnpj((string) $client->root_cnpj),
                competence: is_string($competence) && $competence !== '' ? $competence : null,
                situation: $situation,
                coverage: $coverage,
                dataOrigin: $origin,
                lastConsultedAt: is_string($lastConsulted) && $lastConsulted !== ''
                    ? CarbonImmutable::parse($lastConsulted)->toIso8601String()
                    : null,
                nextDeadlineAt: $deadline['due_at'] ?? null,
                nextAction: $this->nextActionFor($module, $situation, $detail),
                detail: $detail,
                links: $this->rowLinks($module, $id, $detail),
                document: $documents[$id] ?? null,
            );
        });

        return new ConcretePaginator(
            $rows->values(),
            $paginator->total(),
            $paginator->perPage(),
            $paginator->currentPage(),
            [
                'path' => $paginator->path(),
                'pageName' => $paginator->getPageName(),
            ],
        );
    }

    /**
     * Linhas sanitizadas da carteira para export assíncrono (sem paginação UI).
     * Nunca inclui PFX, PEM, vault, tokens, XML ou CNPJ completo.
     *
     * @return array{
     *     module_key: string,
     *     data_origin: FiscalDataOrigin,
     *     is_demonstration: bool,
     *     rows: list<array<string, mixed>>,
     *     total: int
     * }
     */
    public function exportSanitizedRows(
        Office $office,
        FiscalModuleKey $module,
        ModulePortfolioFilters $filters,
    ): array {
        $origin = $this->dataOrigin->resolve($office);
        $rows = [];
        $page = 1;
        $total = 0;
        $maxPages = 100; // teto 10k linhas (100 × 100)

        do {
            $pageFilters = $filters->withPage($page, 100);
            $paginator = $this->clients($office, $module, $pageFilters);
            $total = (int) $paginator->total();

            /** @var ModuleClientRowDto $dto */
            foreach ($paginator->items() as $dto) {
                $rows[] = $this->sanitizeExportRow($dto);
            }

            $page++;
            $lastPage = max(1, (int) $paginator->lastPage());
        } while ($page <= $lastPage && $page <= $maxPages);

        return [
            'module_key' => $module->value,
            'data_origin' => $origin,
            'is_demonstration' => $origin->isSynthetic(),
            'rows' => $rows,
            'total' => $total,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function sanitizeExportRow(ModuleClientRowDto $dto): array
    {
        $detail = $this->sanitizeExportDetail($dto->detail);

        return [
            'module_key' => $dto->moduleKey->value,
            'client_id' => $dto->clientId,
            'legal_name' => $dto->legalName,
            'display_name' => $dto->displayName,
            'cnpj_masked' => $dto->cnpjMasked,
            'root_cnpj_masked' => $dto->rootCnpjMasked,
            'competence' => $dto->competence,
            'situation' => $dto->situation,
            'coverage' => $dto->coverage,
            'data_origin' => $dto->dataOrigin->value,
            'last_consulted_at' => $dto->lastConsultedAt,
            'next_deadline_at' => $dto->nextDeadlineAt,
            'next_action' => $dto->nextAction,
            'metrics' => $detail,
        ];
    }

    /**
     * Remove chaves sensíveis e links internos do bloco discriminado.
     *
     * @param  array<string, mixed>  $detail
     * @return array<string, mixed>
     */
    private function sanitizeExportDetail(array $detail): array
    {
        unset($detail['links']);

        $blockedFragments = [
            'pfx', 'pem', 'password', 'passwd', 'secret', 'token', 'vault',
            'private_key', 'privatekey', 'certificate', 'consumer', 'xml',
            'raw_payload', 'payload_raw', 'mtls', 'keystore',
        ];

        $walk = function (mixed $value) use (&$walk, $blockedFragments): mixed {
            if (! is_array($value)) {
                if (is_string($value) && strlen($value) > 2000) {
                    return null;
                }

                return $value;
            }

            $out = [];
            foreach ($value as $key => $item) {
                if (! is_string($key) && ! is_int($key)) {
                    continue;
                }
                $keyStr = strtolower((string) $key);
                $blocked = false;
                foreach ($blockedFragments as $frag) {
                    if (str_contains($keyStr, $frag)) {
                        $blocked = true;
                        break;
                    }
                }
                if ($blocked || $keyStr === 'links') {
                    continue;
                }
                $out[$key] = $walk($item);
            }

            return $out;
        };

        /** @var array<string, mixed> $sanitized */
        $sanitized = $walk($detail);

        return $sanitized;
    }

    /**
     * IDs de clientes no escopo filtrado (SQL, reutilizável por overview/lista).
     *
     * @return Builder<Client>|QueryBuilder
     */
    public function scopedClientIdsQuery(
        Office $office,
        FiscalModuleKey $module,
        ModulePortfolioFilters $filters,
    ): Builder|QueryBuilder {
        $categoryIds = $this->categoryIdsForModule($module, $filters->submodule);

        $q = Client::query()
            ->withoutGlobalScopes()
            ->select('clients.id')
            ->where('clients.office_id', $office->id)
            ->where('clients.is_active', true)
            ->whereExists(function (QueryBuilder $exists) use ($office, $categoryIds): void {
                $exists->select(DB::raw('1'))
                    ->from('office_fiscal_category_links as ofcl')
                    ->whereColumn('ofcl.client_id', 'clients.id')
                    ->where('ofcl.office_id', $office->id)
                    ->where('ofcl.status', FiscalLinkStatus::Active->value)
                    ->whereIn('ofcl.fiscal_category_id', $categoryIds);
            });

        $clientIds = $filters->clientIdList();
        if ($clientIds !== []) {
            if (count($clientIds) === 1) {
                $q->where('clients.id', $clientIds[0]);
            } else {
                $q->whereIn('clients.id', $clientIds);
            }
        }

        if ($filters->q !== null) {
            $this->applySearch($q, $filters->q);
        }

        $situations = $filters->situationList();
        if ($situations !== []) {
            $expr = '('.$this->situationSqlExpression($office, $module, $filters).')';
            if (count($situations) === 1) {
                $q->whereRaw($expr.' = ?', [$situations[0]]);
            } else {
                $placeholders = implode(',', array_fill(0, count($situations), '?'));
                $q->whereRaw($expr.' IN ('.$placeholders.')', $situations);
            }
        }

        if ($filters->competence !== null) {
            $comp = $filters->competence;
            $q->whereExists(function (QueryBuilder $exists) use ($office, $module, $filters, $comp): void {
                $exists->select(DB::raw('1'))
                    ->from('fiscal_competences as fc')
                    ->whereColumn('fc.client_id', 'clients.id')
                    ->where('fc.office_id', $office->id)
                    ->where('fc.period_key', $comp);

                $catIds = $this->categoryIdsForModule($module, $filters->submodule);
                if ($catIds !== []) {
                    $exists->where(function (QueryBuilder $inner) use ($catIds): void {
                        $inner->whereIn('fc.fiscal_category_id', $catIds)
                            ->orWhereNull('fc.fiscal_category_id');
                    });
                }
            });
        }

        $deliveries = $filters->deliveryStatusList();
        if ($deliveries !== [] && $module === FiscalModuleKey::Declarations) {
            $q->whereExists(function (QueryBuilder $exists) use ($office, $deliveries): void {
                $exists->select(DB::raw('1'))
                    ->from('tax_obligation_projections as top')
                    ->whereColumn('top.client_id', 'clients.id')
                    ->where('top.office_id', $office->id)
                    ->whereIn('top.delivery_status', $deliveries);
            });
        }

        if ($filters->coverageList() !== []) {
            $this->applyCoverageFilter($q, $office, $module, $filters);
        }

        $modalities = $filters->modalityList();
        if ($modalities !== [] && $module === FiscalModuleKey::Installments) {
            $q->whereExists(function (QueryBuilder $exists) use ($office, $modalities): void {
                $exists->select(DB::raw('1'))
                    ->from('tax_installment_orders as tio')
                    ->whereColumn('tio.client_id', 'clients.id')
                    ->where('tio.office_id', $office->id)
                    ->whereIn('tio.modality', $modalities);
            });
        }

        return $q;
    }

    /**
     * Filtra pela cobertura efetiva (snapshot corrente do módulo, senão vínculo de categoria).
     * Alinhado a loadCoverages() — não inventa FULL.
     */
    private function applyCoverageFilter(
        Builder|QueryBuilder $q,
        Office $office,
        FiscalModuleKey $module,
        ModulePortfolioFilters $filters,
    ): void {
        $coverages = $filters->coverageList();
        if ($coverages === []) {
            return;
        }

        $systemCodes = $this->systemCodesForModule($module, $filters->submodule);
        $categoryIds = $this->categoryIdsForModule($module, $filters->submodule);
        $sysList = $this->quoteList($systemCodes);

        // COALESCE(snapshot.coverage, link.coverage, 'UNKNOWN') IN (…)
        $snapshotCoverage = <<<SQL
(
    SELECT fs.coverage
    FROM fiscal_snapshots fs
    WHERE fs.office_id = {$office->id}
      AND fs.client_id = clients.id
      AND fs.is_current = true
      AND fs.system_code IN ({$sysList})
    ORDER BY fs.observed_at DESC, fs.id DESC
    LIMIT 1
)
SQL;

        $catList = $categoryIds === []
            ? 'NULL'
            : implode(',', array_map(static fn (int $id): string => (string) $id, $categoryIds));

        $linkCoverage = <<<SQL
(
    SELECT ofcl.coverage
    FROM office_fiscal_category_links ofcl
    WHERE ofcl.office_id = {$office->id}
      AND ofcl.client_id = clients.id
      AND ofcl.status = 'ACTIVE'
      AND ofcl.fiscal_category_id IN ({$catList})
    ORDER BY ofcl.id DESC
    LIMIT 1
)
SQL;

        $expr = "COALESCE({$snapshotCoverage}, {$linkCoverage}, 'UNKNOWN')";
        if (count($coverages) === 1) {
            $q->whereRaw("{$expr} = ?", [$coverages[0]]);
        } else {
            $placeholders = implode(',', array_fill(0, count($coverages), '?'));
            $q->whereRaw("{$expr} IN ({$placeholders})", $coverages);
        }
    }

    private function aggregateCounters(
        Office $office,
        FiscalModuleKey $module,
        ModulePortfolioFilters $filters,
    ): ModuleCountersDto {
        // Contadores no escopo completo — situation filter removed so KPI strip stays useful;
        // other filters (q, competence, submodule) still apply via a clone without situation.
        // total_clients do overview usa a soma deste mesmo mapa (partição exaustiva).
        $scopeFilters = new ModulePortfolioFilters(
            page: 1,
            perPage: 1,
            q: $filters->q,
            situation: null,
            competence: $filters->competence,
            submodule: $filters->submodule,
            deliveryStatus: $filters->deliveryStatus,
            sort: $filters->sort,
            sortDirection: $filters->sortDirection,
            clientId: $filters->clientId,
            coverage: $filters->coverage,
            modality: $filters->modality,
        );

        $ids = $this->scopedClientIdsQuery($office, $module, $scopeFilters);
        $expr = $this->situationSqlExpression($office, $module, $scopeFilters);

        $rows = Client::query()
            ->withoutGlobalScopes()
            ->from('clients')
            ->whereIn('clients.id', $ids)
            ->where('clients.office_id', $office->id)
            ->selectRaw("({$expr}) as sit")
            ->selectRaw('COUNT(*) as cnt')
            ->groupBy(DB::raw("({$expr})"))
            ->get();

        $map = [];
        foreach (FiscalSituation::cases() as $case) {
            $map[$case->value] = 0;
        }

        foreach ($rows as $row) {
            $sit = (string) $row->sit;
            // Valor fora do enum canônico → UNKNOWN (fail-closed).
            if (! array_key_exists($sit, $map)) {
                $sit = FiscalSituation::Unknown->value;
            }
            $map[$sit] += (int) $row->cnt;
        }

        return ModuleCountersDto::fromSituationMap($map);
    }

    /**
     * @return list<ModuleAgendaItemDto>
     */
    private function buildAgenda(
        Office $office,
        FiscalModuleKey $module,
        ModulePortfolioFilters $filters,
    ): array {
        $catIds = $this->categoryIdsForModule($module, $filters->submodule);
        $clientIds = $this->scopedClientIdsQuery($office, $module, $filters);

        $items = FiscalCompetence::query()
            ->withoutGlobalScopes()
            ->where('office_id', $office->id)
            ->whereIn('client_id', $clientIds)
            ->whereNotNull('due_at')
            ->whereNull('closed_at')
            ->where('due_at', '>=', now()->subDays(1))
            ->when($catIds !== [], fn ($q) => $q->where(function ($inner) use ($catIds): void {
                $inner->whereIn('fiscal_category_id', $catIds)
                    ->orWhereNull('fiscal_category_id');
            }))
            ->orderBy('due_at')
            ->limit(8)
            ->get(['client_id', 'period_key', 'due_at', 'situation']);

        return $items->map(fn (FiscalCompetence $c) => new ModuleAgendaItemDto(
            clientId: (int) $c->client_id,
            label: 'Competência '.$c->period_key,
            dueAt: $c->due_at?->toIso8601String(),
            situation: $c->situation?->value,
            href: '/api/v1/clients/'.$c->client_id,
        ))->all();
    }

    /**
     * @param  Collection<int, FiscalCategory>  $categories
     * @return list<ModuleCategorySummaryDto>
     */
    private function categorySummaries(
        Office $office,
        Collection $categories,
        ModulePortfolioFilters $filters,
    ): array {
        if ($categories->isEmpty()) {
            return [];
        }

        $counts = OfficeFiscalCategoryLink::query()
            ->withoutGlobalScopes()
            ->where('office_id', $office->id)
            ->where('status', FiscalLinkStatus::Active->value)
            ->whereIn('fiscal_category_id', $categories->pluck('id'))
            ->selectRaw('fiscal_category_id, COUNT(DISTINCT client_id) as cnt')
            ->groupBy('fiscal_category_id')
            ->pluck('cnt', 'fiscal_category_id');

        return $categories->map(fn (FiscalCategory $cat) => new ModuleCategorySummaryDto(
            id: (int) $cat->id,
            code: (string) $cat->code,
            name: (string) $cat->name,
            defaultCoverage: $cat->default_coverage?->value,
            linkedClients: (int) ($counts[$cat->id] ?? 0),
        ))->values()->all();
    }

    private function latestObservedAt(
        Office $office,
        FiscalModuleKey $module,
        ModulePortfolioFilters $filters,
    ): ?string {
        $systemCodes = $this->systemCodesForModule($module, $filters->submodule);
        if ($systemCodes === []) {
            return null;
        }

        $at = FiscalSnapshot::query()
            ->withoutGlobalScopes()
            ->where('office_id', $office->id)
            ->where('is_current', true)
            ->whereIn('system_code', $systemCodes)
            ->whereIn('client_id', $this->scopedClientIdsQuery($office, $module, $filters))
            ->max('observed_at');

        return $at !== null ? CarbonImmutable::parse((string) $at)->toIso8601String() : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function moduleMetrics(
        Office $office,
        FiscalModuleKey $module,
        ModulePortfolioFilters $filters,
        int $totalClients,
    ): array {
        return match ($module) {
            FiscalModuleKey::Fgts => [
                'partial_coverage' => true,
                'guide_payment_supported' => false,
            ],
            FiscalModuleKey::Mailbox => [
                'open_messages' => MailboxMessage::query()
                    ->withoutGlobalScopes()
                    ->where('office_id', $office->id)
                    ->whereIn('client_id', $this->scopedClientIdsQuery($office, $module, $filters))
                    ->whereIn('triage_status', ['NEW', 'IN_REVIEW'])
                    ->count(),
            ],
            FiscalModuleKey::Guides => [
                'unconfirmed_payment_guides' => TaxGuide::query()
                    ->withoutGlobalScopes()
                    ->where('office_id', $office->id)
                    ->whereIn('client_id', $this->scopedClientIdsQuery($office, $module, $filters))
                    ->whereIn('payment_status', ['UNKNOWN', 'NOT_CONFIRMED'])
                    ->count(),
            ],
            default => [
                'total_clients' => $totalClients,
            ],
        };
    }

    /**
     * Expressão SQL escalar de situação por cliente (mesmo critério de contadores e lista).
     */
    private function situationSqlExpression(
        Office $office,
        FiscalModuleKey $module,
        ModulePortfolioFilters $filters,
    ): string {
        $systemCodes = $this->systemCodesForModule($module, $filters->submodule);
        $serviceCodes = $this->serviceCodesForModule($module, $filters->submodule);

        $sysList = $this->quoteList($systemCodes);
        $svcList = $this->quoteList($serviceCodes);

        // Snapshot corrente mais recente do(s) system/service do módulo.
        // COALESCE com UNKNOWN quando não há projeção.
        $snapshotSql = <<<SQL
(
    SELECT fs.situation
    FROM fiscal_snapshots fs
    WHERE fs.office_id = {$office->id}
      AND fs.client_id = clients.id
      AND fs.is_current = true
      AND fs.system_code IN ({$sysList})
SQL;
        if ($serviceCodes !== []) {
            $snapshotSql .= " AND (fs.service_code IN ({$svcList}) OR fs.service_code IS NULL)";
        }
        $snapshotSql .= ' ORDER BY fs.observed_at DESC, fs.id DESC LIMIT 1)';

        // Complementos por módulo (projeções específicas têm prioridade se existirem)
        $moduleSql = match ($module) {
            FiscalModuleKey::Dctfweb => $this->dctfwebSituationSql($office, $filters),
            FiscalModuleKey::Installments => $this->parcelamentosSituationSql($office),
            FiscalModuleKey::Mailbox => $this->mailboxSituationSql($office),
            FiscalModuleKey::Declarations => $this->declaracoesSituationSql($office),
            FiscalModuleKey::Guides => $this->guiasSituationSql($office),
            FiscalModuleKey::Fgts => $this->fgtsSituationSql($office),
            default => null,
        };

        if ($moduleSql !== null) {
            return "COALESCE({$moduleSql}, {$snapshotSql}, 'UNKNOWN')";
        }

        return "COALESCE({$snapshotSql}, 'UNKNOWN')";
    }

    private function dctfwebSituationSql(Office $office, ModulePortfolioFilters $filters): string
    {
        $sub = $filters->submodule;
        if ($sub === 'MIT') {
            return "(
                SELECT ma.situation FROM mit_apuracoes ma
                WHERE ma.office_id = {$office->id} AND ma.client_id = clients.id
                ORDER BY ma.observed_at DESC, ma.id DESC LIMIT 1
            )";
        }

        if ($sub === 'DCTFWEB') {
            return "(
                SELECT dd.situation FROM dctfweb_declarations dd
                WHERE dd.office_id = {$office->id} AND dd.client_id = clients.id
                ORDER BY dd.official_at DESC, dd.id DESC LIMIT 1
            )";
        }

        // Ambos: pior situação entre as duas fontes (prioridade fixa via CASE)
        return "(
            SELECT sit FROM (
                SELECT dd.situation AS sit, 1 AS src_ord, COALESCE(dd.official_at, dd.updated_at) AS ord_at, dd.id AS ord_id
                FROM dctfweb_declarations dd
                WHERE dd.office_id = {$office->id} AND dd.client_id = clients.id
                UNION ALL
                SELECT ma.situation, 2, COALESCE(ma.observed_at, ma.updated_at), ma.id
                FROM mit_apuracoes ma
                WHERE ma.office_id = {$office->id} AND ma.client_id = clients.id
            ) u
            ORDER BY
                CASE u.sit
                    WHEN 'ERROR' THEN 1 WHEN 'BLOCKED' THEN 2 WHEN 'ATTENTION' THEN 3
                    WHEN 'PENDING' THEN 4 WHEN 'PROCESSING' THEN 5 WHEN 'UNKNOWN' THEN 6
                    WHEN 'UNSUPPORTED' THEN 7 WHEN 'NOT_APPLICABLE' THEN 8 WHEN 'UP_TO_DATE' THEN 9
                    ELSE 10
                END ASC,
                u.ord_at DESC, u.ord_id DESC
            LIMIT 1
        )";
    }

    private function parcelamentosSituationSql(Office $office): string
    {
        // Mapeia situação textual do pedido + atraso de parcela
        return "(
            SELECT CASE
                WHEN EXISTS (
                    SELECT 1 FROM tax_installment_parcels tip
                    WHERE tip.office_id = {$office->id} AND tip.client_id = clients.id
                      AND tip.status IN ('ATTENTION','PENDING')
                ) THEN 'ATTENTION'
                WHEN EXISTS (
                    SELECT 1 FROM tax_installment_orders tio
                    WHERE tio.office_id = {$office->id} AND tio.client_id = clients.id
                      AND UPPER(COALESCE(tio.situation,'')) IN ('ATTENTION','PENDING','ERROR','BLOCKED')
                ) THEN (
                    SELECT UPPER(tio.situation) FROM tax_installment_orders tio
                    WHERE tio.office_id = {$office->id} AND tio.client_id = clients.id
                    ORDER BY tio.observed_at DESC, tio.id DESC LIMIT 1
                )
                WHEN EXISTS (
                    SELECT 1 FROM tax_installment_orders tio
                    WHERE tio.office_id = {$office->id} AND tio.client_id = clients.id
                ) THEN COALESCE((
                    SELECT UPPER(tio.situation) FROM tax_installment_orders tio
                    WHERE tio.office_id = {$office->id} AND tio.client_id = clients.id
                    ORDER BY tio.observed_at DESC, tio.id DESC LIMIT 1
                ), 'UNKNOWN')
                ELSE NULL
            END
        )";
    }

    private function mailboxSituationSql(Office $office): string
    {
        return "(
            SELECT CASE
                WHEN EXISTS (
                    SELECT 1 FROM mailbox_messages mm
                    WHERE mm.office_id = {$office->id} AND mm.client_id = clients.id
                      AND mm.due_at IS NOT NULL AND mm.due_at < CURRENT_TIMESTAMP
                      AND mm.triage_status IN ('NEW','IN_REVIEW')
                ) THEN 'ATTENTION'
                WHEN EXISTS (
                    SELECT 1 FROM mailbox_messages mm
                    WHERE mm.office_id = {$office->id} AND mm.client_id = clients.id
                      AND mm.triage_status = 'NEW'
                ) THEN 'PENDING'
                WHEN EXISTS (
                    SELECT 1 FROM mailbox_contributor_states mcs
                    WHERE mcs.office_id = {$office->id} AND mcs.client_id = clients.id
                      AND COALESCE(mcs.official_unread_count, 0) > 0
                ) THEN 'PENDING'
                WHEN EXISTS (
                    SELECT 1 FROM mailbox_contributor_states mcs
                    WHERE mcs.office_id = {$office->id} AND mcs.client_id = clients.id
                ) THEN 'UP_TO_DATE'
                ELSE NULL
            END
        )";
    }

    private function declaracoesSituationSql(Office $office): string
    {
        return "(
            SELECT top.situation FROM tax_obligation_projections top
            WHERE top.office_id = {$office->id} AND top.client_id = clients.id
              AND top.is_open = true
            ORDER BY
                CASE top.situation
                    WHEN 'ERROR' THEN 1 WHEN 'BLOCKED' THEN 2 WHEN 'ATTENTION' THEN 3
                    WHEN 'PENDING' THEN 4 WHEN 'PROCESSING' THEN 5 ELSE 9
                END ASC,
                CASE WHEN top.due_at IS NULL THEN 1 ELSE 0 END ASC,
                top.due_at ASC, top.id DESC
            LIMIT 1
        )";
    }

    private function guiasSituationSql(Office $office): string
    {
        return "(
            SELECT CASE
                WHEN EXISTS (
                    SELECT 1 FROM tax_guides tg
                    WHERE tg.office_id = {$office->id} AND tg.client_id = clients.id
                      AND tg.due_at IS NOT NULL AND tg.due_at < CURRENT_TIMESTAMP
                      AND tg.payment_status IN ('UNKNOWN','NOT_CONFIRMED')
                ) THEN 'ATTENTION'
                WHEN EXISTS (
                    SELECT 1 FROM tax_guides tg
                    WHERE tg.office_id = {$office->id} AND tg.client_id = clients.id
                      AND tg.payment_status IN ('UNKNOWN','NOT_CONFIRMED')
                ) THEN 'PENDING'
                WHEN EXISTS (
                    SELECT 1 FROM tax_guides tg
                    WHERE tg.office_id = {$office->id} AND tg.client_id = clients.id
                      AND tg.payment_status IN ('CONFIRMED','PARTIAL')
                ) THEN 'UP_TO_DATE'
                ELSE NULL
            END
        )";
    }

    private function fgtsSituationSql(Office $office): string
    {
        return "(
            SELECT fcs.situation FROM fgts_competence_statuses fcs
            WHERE fcs.office_id = {$office->id} AND fcs.client_id = clients.id
            ORDER BY fcs.last_synced_at DESC, fcs.id DESC LIMIT 1
        )";
    }

    private function situationSubquery(
        Office $office,
        FiscalModuleKey $module,
        ModulePortfolioFilters $filters,
    ): QueryBuilder {
        return DB::query()->selectRaw($this->situationSqlExpression($office, $module, $filters));
    }

    private function lastConsultedSubquery(
        Office $office,
        FiscalModuleKey $module,
        ModulePortfolioFilters $filters,
    ): QueryBuilder {
        $systemCodes = $this->systemCodesForModule($module, $filters->submodule);
        $sysList = $this->quoteList($systemCodes);

        return DB::query()->selectRaw(<<<SQL
(
    SELECT fs.observed_at FROM fiscal_snapshots fs
    WHERE fs.office_id = {$office->id}
      AND fs.client_id = clients.id
      AND fs.is_current = true
      AND fs.system_code IN ({$sysList})
    ORDER BY fs.observed_at DESC, fs.id DESC LIMIT 1
)
SQL);
    }

    private function competenceSubquery(
        Office $office,
        FiscalModuleKey $module,
        ModulePortfolioFilters $filters,
    ): QueryBuilder {
        $catIds = $this->categoryIdsForModule($module, $filters->submodule);
        $catList = $catIds === [] ? 'NULL' : implode(',', array_map('intval', $catIds));

        return DB::query()->selectRaw(<<<SQL
(
    SELECT fc.period_key FROM fiscal_competences fc
    WHERE fc.office_id = {$office->id}
      AND fc.client_id = clients.id
      AND (fc.fiscal_category_id IN ({$catList}) OR fc.fiscal_category_id IS NULL)
    ORDER BY fc.period_year DESC,
             CASE WHEN fc.period_month IS NULL THEN 1 ELSE 0 END ASC,
             fc.period_month DESC, fc.id DESC
    LIMIT 1
)
SQL);
    }

    /**
     * @param  Builder<Client>  $q
     */
    private function applySearch(Builder $q, string $search): void
    {
        $needle = '%'.mb_strtolower($search).'%';
        $cnpjNeedle = '%'.strtoupper(Cnpj::normalize($search)).'%';

        $q->where(function (Builder $inner) use ($needle, $cnpjNeedle): void {
            $inner->whereRaw('LOWER(clients.legal_name) LIKE ?', [$needle])
                ->orWhereRaw('LOWER(COALESCE(clients.display_name, \'\')) LIKE ?', [$needle])
                ->orWhere('clients.root_cnpj', 'like', $cnpjNeedle)
                ->orWhereExists(function (QueryBuilder $est) use ($cnpjNeedle): void {
                    $est->select(DB::raw('1'))
                        ->from('establishments')
                        ->whereColumn('establishments.client_id', 'clients.id')
                        ->where('establishments.cnpj', 'like', $cnpjNeedle);
                });
        });
    }

    /**
     * @return Collection<int, FiscalCategory>
     */
    private function moduleCategories(FiscalModuleKey $module): Collection
    {
        $flag = $module->featureFlagKey();
        if ($flag === null) {
            return collect();
        }

        return FiscalCategory::query()
            ->where('module_key', $flag)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * @return list<int>
     */
    private function categoryIdsForModule(FiscalModuleKey $module, ?string $submodule): array
    {
        $flag = $module->featureFlagKey();
        if ($flag === null) {
            return [];
        }

        $q = FiscalCategory::query()
            ->where('module_key', $flag)
            ->where('is_active', true);

        if ($submodule !== null) {
            $code = $this->normalizeSubmoduleToCategoryCode($module, $submodule);
            $service = $this->normalizeSubmoduleToServiceCode($module, $submodule);
            $q->where(function ($inner) use ($code, $service, $submodule): void {
                $inner->where('code', $code)
                    ->orWhere('code', $submodule)
                    ->orWhere('service_code', $service)
                    ->orWhere('service_code', $submodule);
            });
        }

        return $q->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * @return list<string>
     */
    private function systemCodesForModule(FiscalModuleKey $module, ?string $submodule): array
    {
        $flag = $module->featureFlagKey();
        if ($flag === null) {
            return ['__none__'];
        }

        $q = FiscalCategory::query()
            ->where('module_key', $flag)
            ->where('is_active', true)
            ->whereNotNull('system_code');

        if ($submodule !== null) {
            $ids = $this->categoryIdsForModule($module, $submodule);
            if ($ids !== []) {
                $q->whereIn('id', $ids);
            }
        }

        $codes = $q->pluck('system_code')->filter()->unique()->values()->all();

        return $codes !== [] ? $codes : ['__none__'];
    }

    /**
     * @return list<string>
     */
    private function serviceCodesForModule(FiscalModuleKey $module, ?string $submodule): array
    {
        $flag = $module->featureFlagKey();
        if ($flag === null) {
            return [];
        }

        $q = FiscalCategory::query()
            ->where('module_key', $flag)
            ->where('is_active', true)
            ->whereNotNull('service_code');

        if ($submodule !== null) {
            $ids = $this->categoryIdsForModule($module, $submodule);
            if ($ids !== []) {
                $q->whereIn('id', $ids);
            }
        }

        return $q->pluck('service_code')->filter()->unique()->values()->all();
    }

    private function normalizeSubmoduleToCategoryCode(FiscalModuleKey $module, string $submodule): string
    {
        return match ($module) {
            FiscalModuleKey::SimplesMei => match ($submodule) {
                'PGDASD', 'PGDAS-D', 'SIMPLES' => 'SIMPLES_NACIONAL',
                'PGMEI', 'MEI' => 'MEI',
                'DASN_SIMEI', 'DASN-SIMEI' => 'MEI',
                'REGIME' => 'SIMPLES_NACIONAL',
                default => $submodule,
            },
            default => $submodule,
        };
    }

    private function normalizeSubmoduleToServiceCode(FiscalModuleKey $module, string $submodule): string
    {
        return match ($module) {
            FiscalModuleKey::SimplesMei => match ($submodule) {
                'PGDAS-D', 'PGDASD' => 'PGDASD',
                'PGMEI' => 'PGMEI',
                'DASN_SIMEI', 'DASN-SIMEI' => 'DASN',
                'REGIME' => 'REGIME',
                default => $submodule,
            },
            default => $submodule,
        };
    }

    /**
     * @param  Collection<int, FiscalCategory>  $categories
     */
    private function moduleDefaultCoverage(Collection $categories): ?string
    {
        if ($categories->isEmpty()) {
            return FiscalCoverage::Unknown->value;
        }

        // Se qualquer categoria for PARTIAL/UNSUPPORTED, expõe o pior
        $values = $categories->map(fn (FiscalCategory $c) => $c->default_coverage?->value)->filter();
        foreach ([
            FiscalCoverage::Unsupported->value,
            FiscalCoverage::Partial->value,
            FiscalCoverage::Unknown->value,
            FiscalCoverage::Full->value,
        ] as $candidate) {
            if ($values->contains($candidate)) {
                return $candidate;
            }
        }

        return FiscalCoverage::Unknown->value;
    }

    /**
     * Descritores de documento por cliente — href só com artefato real do CurrentOffice.
     *
     * @param  list<int>  $clientIds
     * @return array<int, FiscalDocumentDescriptorDto>
     */
    private function loadDocuments(
        Office $office,
        FiscalModuleKey $module,
        ModulePortfolioFilters $filters,
        array $clientIds,
    ): array {
        if ($clientIds === []) {
            return [];
        }

        $surface = $this->surfaces->resolveForModule($module, $filters->submodule);
        $unavailable = $this->unavailableReasonForSurface($surface);

        if (! $surface->allowsDocument || $unavailable !== null) {
            $reason = $unavailable ?? DocumentUnavailableReason::NotSupported;
            $map = [];
            foreach ($clientIds as $cid) {
                $map[$cid] = FiscalDocumentDescriptorDto::unavailable($reason, $surface);
            }

            return $map;
        }

        $artifactsByClient = $this->latestArtifactsByClient($office, $clientIds, $surface);
        $map = [];
        foreach ($clientIds as $cid) {
            $artifact = $artifactsByClient[$cid] ?? null;
            if ($artifact === null) {
                $map[$cid] = FiscalDocumentDescriptorDto::unavailable(
                    DocumentUnavailableReason::NotCollected,
                    $surface,
                );

                continue;
            }

            $map[$cid] = FiscalDocumentDescriptorDto::fromArtifact($artifact, $surface, $office);
        }

        return $map;
    }

    private function unavailableReasonForSurface(MonitoringSurfaceContract $surface): ?DocumentUnavailableReason
    {
        if ($surface->resultKind === MonitoringResultKind::Unavailable) {
            return DocumentUnavailableReason::NotProduction;
        }

        if (! $surface->allowsDocument
            || $surface->documentPolicy === MonitoringDocumentPolicy::Never
        ) {
            return match ($surface->resultKind) {
                MonitoringResultKind::Structured => DocumentUnavailableReason::StructuredOnly,
                MonitoringResultKind::Unavailable => DocumentUnavailableReason::NotProduction,
                default => DocumentUnavailableReason::NotSupported,
            };
        }

        return null;
    }

    /**
     * @param  list<int>  $clientIds
     * @return array<int, FiscalEvidenceArtifact>
     */
    private function latestArtifactsByClient(
        Office $office,
        array $clientIds,
        MonitoringSurfaceContract $surface,
    ): array {
        $opKeys = $surface->operationKeys;

        $rows = FiscalEvidenceArtifact::query()
            ->withoutGlobalScopes()
            ->from('fiscal_evidence_artifacts as fea')
            ->join('fiscal_monitoring_runs as fmr', 'fmr.id', '=', 'fea.run_id')
            ->where('fea.office_id', $office->id)
            ->whereIn('fmr.client_id', $clientIds)
            ->when(
                $opKeys !== [],
                function ($q) use ($opKeys): void {
                    $q->where(function ($inner) use ($opKeys): void {
                        $inner->whereIn('fea.operation_key', $opKeys)
                            ->orWhereIn('fmr.operation_key', $opKeys);
                    });
                },
            )
            ->orderByDesc('fea.observed_at')
            ->orderByDesc('fea.id')
            ->select(['fea.*', 'fmr.client_id as portfolio_client_id'])
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $cid = (int) $row->getAttribute('portfolio_client_id');
            if ($cid < 1 || isset($map[$cid])) {
                continue;
            }
            if ((int) $row->office_id !== (int) $office->id) {
                continue;
            }
            $map[$cid] = $row;
        }

        return $map;
    }

    /**
     * @param  list<int>  $clientIds
     * @return array<int, string>
     */
    private function loadMatrixCnpjs(Office $office, array $clientIds): array
    {
        if ($clientIds === []) {
            return [];
        }

        $rows = DB::table('establishments')
            ->where('office_id', $office->id)
            ->whereIn('client_id', $clientIds)
            ->whereNull('deleted_at')
            ->orderByDesc('is_matrix')
            ->orderBy('id')
            ->get(['client_id', 'cnpj', 'is_matrix']);

        $map = [];
        foreach ($rows as $row) {
            $cid = (int) $row->client_id;
            if (! isset($map[$cid])) {
                $map[$cid] = (string) $row->cnpj;
            }
        }

        return $map;
    }

    /**
     * @param  list<int>  $clientIds
     * @return array<int, string>
     */
    private function loadCoverages(
        Office $office,
        FiscalModuleKey $module,
        array $clientIds,
        ModulePortfolioFilters $filters,
    ): array {
        if ($clientIds === []) {
            return [];
        }

        $systemCodes = $this->systemCodesForModule($module, $filters->submodule);
        $rows = FiscalSnapshot::query()
            ->withoutGlobalScopes()
            ->where('office_id', $office->id)
            ->where('is_current', true)
            ->whereIn('client_id', $clientIds)
            ->whereIn('system_code', $systemCodes)
            ->orderByDesc('observed_at')
            ->get(['client_id', 'coverage']);

        $map = [];
        foreach ($rows as $row) {
            $cid = (int) $row->client_id;
            if (! isset($map[$cid])) {
                $map[$cid] = $row->coverage?->value ?? FiscalCoverage::Unknown->value;
            }
        }

        // Cobertura do vínculo quando não há snapshot
        $linkCoverages = OfficeFiscalCategoryLink::query()
            ->withoutGlobalScopes()
            ->where('office_id', $office->id)
            ->whereIn('client_id', $clientIds)
            ->where('status', FiscalLinkStatus::Active->value)
            ->whereIn('fiscal_category_id', $this->categoryIdsForModule($module, $filters->submodule))
            ->get(['client_id', 'coverage']);

        foreach ($linkCoverages as $link) {
            $cid = (int) $link->client_id;
            if (! isset($map[$cid])) {
                $map[$cid] = $link->coverage?->value ?? FiscalCoverage::Unknown->value;
            }
        }

        return $map;
    }

    /**
     * @param  list<int>  $clientIds
     * @return array<int, array<string, mixed>>
     */
    private function loadModuleDetails(
        Office $office,
        FiscalModuleKey $module,
        array $clientIds,
        ModulePortfolioFilters $filters,
    ): array {
        if ($clientIds === []) {
            return [];
        }

        return match ($module) {
            FiscalModuleKey::SimplesMei => $this->detailSimplesMei($office, $clientIds, $filters),
            FiscalModuleKey::Dctfweb => $this->detailDctfwebMit($office, $clientIds, $filters),
            FiscalModuleKey::Installments => $this->detailParcelamentos($office, $clientIds),
            FiscalModuleKey::Sitfis => $this->detailSitfis($office, $clientIds),
            FiscalModuleKey::Mailbox => $this->detailMailbox($office, $clientIds),
            FiscalModuleKey::Declarations => $this->detailDeclaracoes($office, $clientIds),
            FiscalModuleKey::Guides => $this->detailGuias($office, $clientIds),
            FiscalModuleKey::Fgts => $this->detailFgts($office, $clientIds),
        };
    }

    /**
     * @param  list<int>  $clientIds
     * @return array<int, array{due_at: ?string}>
     */
    private function loadNextDeadlines(
        Office $office,
        FiscalModuleKey $module,
        array $clientIds,
        ModulePortfolioFilters $filters,
    ): array {
        if ($clientIds === []) {
            return [];
        }

        $catIds = $this->categoryIdsForModule($module, $filters->submodule);
        $rows = FiscalCompetence::query()
            ->withoutGlobalScopes()
            ->where('office_id', $office->id)
            ->whereIn('client_id', $clientIds)
            ->whereNotNull('due_at')
            ->whereNull('closed_at')
            ->when($catIds !== [], fn ($q) => $q->where(function ($inner) use ($catIds): void {
                $inner->whereIn('fiscal_category_id', $catIds)->orWhereNull('fiscal_category_id');
            }))
            ->orderBy('due_at')
            ->get(['client_id', 'due_at']);

        $map = [];
        foreach ($rows as $row) {
            $cid = (int) $row->client_id;
            if (! isset($map[$cid])) {
                $map[$cid] = ['due_at' => $row->due_at?->toIso8601String()];
            }
        }

        return $map;
    }

    /**
     * @param  list<int>  $clientIds
     * @return array<int, array<string, mixed>>
     */
    private function detailSimplesMei(Office $office, array $clientIds, ModulePortfolioFilters $filters): array
    {
        $comps = FiscalCompetence::query()
            ->withoutGlobalScopes()
            ->where('office_id', $office->id)
            ->whereIn('client_id', $clientIds)
            ->orderByDesc('period_year')
            ->orderByDesc('period_month')
            ->get();

        $sub = strtoupper((string) ($filters->submodule ?? 'PGDASD'));
        $pgdasdDetails = [];
        if (in_array($sub, ['PGDASD', 'PGDAS-D', 'SIMPLES', 'SIMPLES_NACIONAL', ''], true)) {
            $pgdasdDetails = app(PgdasdMonitoringQueryService::class)
                ->portfolioDetails($office, $clientIds);
        }

        $map = [];
        foreach ($clientIds as $cid) {
            $comp = $comps->firstWhere('client_id', $cid);
            $base = [
                'module_key' => FiscalModuleKey::SimplesMei->value,
                'submodule' => $filters->submodule,
                'period_key' => $comp?->period_key,
                'competence_id' => $comp?->id,
                'links' => [
                    'regimes' => "/api/v1/fiscal/simples-mei/clients/{$cid}/regimes",
                    'competences' => "/api/v1/fiscal/simples-mei/clients/{$cid}/competences",
                    'snapshots' => "/api/v1/fiscal/simples-mei/clients/{$cid}/snapshots",
                    'guide_stubs' => "/api/v1/fiscal/simples-mei/clients/{$cid}/guide-stubs",
                ],
            ];

            if (isset($pgdasdDetails[$cid])) {
                $pg = $pgdasdDetails[$cid];
                $base['pgdasd'] = [
                    'expected_period_key' => $pg['expected_period_key'] ?? $pg['period_key'] ?? null,
                    'latest_declaration' => $pg['latest_declaration'] ?? null,
                    'declaration_state' => $pg['declaration_state'] ?? null,
                    'last_valid_query_at' => $pg['last_valid_query_at'] ?? null,
                    'rbt12' => $pg['rbt12'] ?? null,
                    'communication' => $pg['communication'] ?? null,
                ];
                $base['declaration_state'] = $pg['declaration_state'];
                $base['last_declaration'] = $pg['last_declaration'];
                $base['rbt12'] = $pg['rbt12'];
                $base['last_productive_consulted_at'] = $pg['last_productive_consulted_at'];
                $base['communication'] = $pg['communication'];
                $base['links'] = array_merge($base['links'], $pg['links'] ?? []);
                if (($pg['period_key'] ?? null) !== null) {
                    $base['period_key'] = $pg['period_key'];
                }
            }

            $map[$cid] = $base;
        }

        return $map;
    }

    /**
     * @param  list<int>  $clientIds
     * @return array<int, array<string, mixed>>
     */
    private function detailDctfwebMit(Office $office, array $clientIds, ModulePortfolioFilters $filters): array
    {
        $declarations = DctfwebDeclaration::query()
            ->withoutGlobalScopes()
            ->where('office_id', $office->id)
            ->whereIn('client_id', $clientIds)
            ->orderByDesc('id')
            ->get();

        $mits = MitApuracao::query()
            ->withoutGlobalScopes()
            ->where('office_id', $office->id)
            ->whereIn('client_id', $clientIds)
            ->orderByDesc('id')
            ->get();

        $map = [];
        foreach ($clientIds as $cid) {
            $decl = $declarations->firstWhere('client_id', $cid);
            $mit = $mits->firstWhere('client_id', $cid);
            $map[$cid] = [
                'module_key' => FiscalModuleKey::Dctfweb->value,
                'submodule' => $filters->submodule,
                'dctfweb' => $decl === null ? null : [
                    'id' => $decl->id,
                    'period_key' => $decl->period_key,
                    'transmission_status' => $decl->transmission_status?->value,
                    'payment_status' => $decl->payment_status?->value,
                    'receipt_number' => $decl->receipt_number,
                    'situation' => $decl->situation?->value,
                ],
                'mit' => $mit === null ? null : [
                    'id' => $mit->id,
                    'period_key' => $mit->period_key,
                    'encerramento_status' => $mit->encerramento_status?->value,
                    'dctfweb_transmission_status' => $mit->dctfweb_transmission_status?->value,
                    'situation' => $mit->situation?->value,
                ],
                'links' => [
                    'declarations' => '/api/v1/fiscal/dctfweb/declarations?client_id='.$cid,
                    'mit' => '/api/v1/fiscal/mit/apuracoes?client_id='.$cid,
                ],
            ];
        }

        return $map;
    }

    /**
     * @param  list<int>  $clientIds
     * @return array<int, array<string, mixed>>
     */
    private function detailParcelamentos(Office $office, array $clientIds): array
    {
        $orders = TaxInstallmentOrder::query()
            ->withoutGlobalScopes()
            ->where('office_id', $office->id)
            ->whereIn('client_id', $clientIds)
            ->orderByDesc('observed_at')
            ->get();

        $parcels = TaxInstallmentParcel::query()
            ->withoutGlobalScopes()
            ->where('office_id', $office->id)
            ->whereIn('client_id', $clientIds)
            ->whereNotIn('status', [TaxInstallmentParcelStatus::Paid->value, TaxInstallmentParcelStatus::Cancelled->value])
            ->orderBy('due_at')
            ->get();

        $map = [];
        foreach ($clientIds as $cid) {
            $order = $orders->firstWhere('client_id', $cid);
            $nextParcel = $parcels->firstWhere('client_id', $cid);
            $overdue = $parcels->where('client_id', $cid)
                ->filter(fn (TaxInstallmentParcel $p) => in_array(
                    $p->status,
                    [TaxInstallmentParcelStatus::Attention, TaxInstallmentParcelStatus::Pending],
                    true,
                ))
                ->count();

            $map[$cid] = [
                'module_key' => FiscalModuleKey::Installments->value,
                'order_id' => $order?->id,
                'modality' => $order?->modality?->value ?? $order?->getAttribute('modality'),
                'external_order_id' => $order?->external_order_id,
                'total_amount_cents' => $order?->total_amount_cents,
                'parcel_count' => $order?->parcel_count,
                'order_situation' => $order?->situation,
                'next_parcel_id' => $nextParcel?->id,
                'next_parcel_due_at' => $nextParcel?->due_at?->toIso8601String(),
                'next_parcel_amount_cents' => $nextParcel?->amount_cents,
                'overdue_parcels' => $overdue,
                'links' => [
                    'orders' => '/api/v1/fiscal/installments/orders?client_id='.$cid,
                    'parcels' => '/api/v1/fiscal/installments/parcels?client_id='.$cid,
                    'order' => $order !== null
                        ? '/api/v1/fiscal/installments/orders/'.$order->id
                        : null,
                ],
            ];
        }

        return $map;
    }

    /**
     * @param  list<int>  $clientIds
     * @return array<int, array<string, mixed>>
     */
    private function detailSitfis(Office $office, array $clientIds): array
    {
        $system = (string) config('fiscal_monitoring.sitfis.system_code', 'INTEGRA_SITFIS');
        $ttl = (int) config('fiscal_monitoring.sitfis.snapshot_ttl_seconds', 86400);

        $snapshots = FiscalSnapshot::query()
            ->withoutGlobalScopes()
            ->where('office_id', $office->id)
            ->where('is_current', true)
            ->where('system_code', $system)
            ->whereIn('client_id', $clientIds)
            ->orderByDesc('observed_at')
            ->get();

        $findings = FiscalFinding::query()
            ->withoutGlobalScopes()
            ->where('office_id', $office->id)
            ->whereIn('client_id', $clientIds)
            ->where('is_active', true)
            ->selectRaw('client_id, COUNT(*) as cnt')
            ->groupBy('client_id')
            ->pluck('cnt', 'client_id');

        $pending = FiscalPendingItem::query()
            ->withoutGlobalScopes()
            ->where('office_id', $office->id)
            ->whereIn('client_id', $clientIds)
            ->where('status', 'OPEN')
            ->selectRaw('client_id, COUNT(*) as cnt')
            ->groupBy('client_id')
            ->pluck('cnt', 'client_id');

        $map = [];
        foreach ($clientIds as $cid) {
            $snap = $snapshots->firstWhere('client_id', $cid);
            $observed = $snap?->observed_at;
            $ageSeconds = $observed !== null ? $observed->diffInSeconds(now()) : null;
            $expired = $observed !== null && $ageSeconds !== null && $ageSeconds > $ttl;

            $map[$cid] = [
                'module_key' => FiscalModuleKey::Sitfis->value,
                'snapshot_id' => $snap?->id,
                'observed_at' => $observed?->toIso8601String(),
                'age_seconds' => $ageSeconds,
                'ttl_seconds' => $ttl,
                'is_expired' => $expired,
                'findings_count' => (int) ($findings[$cid] ?? 0),
                'pending_count' => (int) ($pending[$cid] ?? 0),
                'links' => [
                    'sitfis' => '/api/v1/fiscal/sitfis?client_id='.$cid,
                    'findings' => '/api/v1/fiscal/findings?client_id='.$cid,
                    'pending_items' => '/api/v1/fiscal/pending-items?client_id='.$cid,
                    'snapshot' => $snap !== null ? '/api/v1/fiscal/snapshots/'.$snap->id : null,
                ],
            ];
        }

        return $map;
    }

    /**
     * @param  list<int>  $clientIds
     * @return array<int, array<string, mixed>>
     */
    private function detailMailbox(Office $office, array $clientIds): array
    {
        $states = MailboxContributorState::query()
            ->withoutGlobalScopes()
            ->where('office_id', $office->id)
            ->whereIn('client_id', $clientIds)
            ->get()
            ->keyBy('client_id');

        $latest = MailboxMessage::query()
            ->withoutGlobalScopes()
            ->where('office_id', $office->id)
            ->whereIn('client_id', $clientIds)
            ->orderByDesc('received_at_official')
            ->get()
            ->groupBy('client_id');

        $openCounts = MailboxMessage::query()
            ->withoutGlobalScopes()
            ->where('office_id', $office->id)
            ->whereIn('client_id', $clientIds)
            ->whereIn('triage_status', ['NEW', 'IN_REVIEW'])
            ->selectRaw('client_id, COUNT(*) as cnt')
            ->groupBy('client_id')
            ->pluck('cnt', 'client_id');

        $map = [];
        foreach ($clientIds as $cid) {
            /** @var MailboxContributorState|null $state */
            $state = $states->get($cid);
            /** @var MailboxMessage|null $msg */
            $msg = $latest->get($cid)?->first();

            $map[$cid] = [
                'module_key' => FiscalModuleKey::Mailbox->value,
                'official_unread_count' => $state?->official_unread_count ?? 0,
                'stored_message_count' => $state?->stored_message_count ?? 0,
                'open_triage_count' => (int) ($openCounts[$cid] ?? 0),
                'dte_status' => $state?->dte_status?->value,
                'latest_message_id' => $msg?->id,
                'latest_subject_preview' => $msg?->subject_preview,
                'latest_received_at' => $msg?->received_at_official?->toIso8601String(),
                'latest_due_at' => $msg?->due_at?->toIso8601String(),
                'links' => [
                    'messages' => '/api/v1/fiscal/mailbox/messages?client_id='.$cid,
                    'state' => '/api/v1/fiscal/mailbox/state?client_id='.$cid,
                    'message' => $msg !== null
                        ? '/api/v1/fiscal/mailbox/messages/'.$msg->id
                        : null,
                ],
            ];
        }

        return $map;
    }

    /**
     * @param  list<int>  $clientIds
     * @return array<int, array<string, mixed>>
     */
    private function detailDeclaracoes(Office $office, array $clientIds): array
    {
        $projections = TaxObligationProjection::query()
            ->withoutGlobalScopes()
            ->with('obligation')
            ->where('office_id', $office->id)
            ->whereIn('client_id', $clientIds)
            ->where('is_open', true)
            ->orderBy('due_at')
            ->get()
            ->groupBy('client_id');

        $map = [];
        foreach ($clientIds as $cid) {
            /** @var Collection<int, TaxObligationProjection> $rows */
            $rows = $projections->get($cid) ?? collect();
            $next = $rows->first();
            $map[$cid] = [
                'module_key' => FiscalModuleKey::Declarations->value,
                'open_count' => $rows->count(),
                'next_projection_id' => $next?->id,
                'next_obligation_code' => $next?->obligation?->code,
                'next_period_key' => $next?->period_key,
                'next_due_at' => $next?->due_at?->toIso8601String(),
                'next_delivery_status' => $next?->delivery_status?->value,
                'next_situation' => $next?->situation?->value,
                'links' => [
                    'declarations' => '/api/v1/fiscal/declarations?client_id='.$cid,
                    'summary' => '/api/v1/fiscal/declarations/summary?client_id='.$cid,
                    'projection' => $next !== null
                        ? '/api/v1/fiscal/declarations/'.$next->id
                        : null,
                ],
            ];
        }

        return $map;
    }

    /**
     * @param  list<int>  $clientIds
     * @return array<int, array<string, mixed>>
     */
    private function detailGuias(Office $office, array $clientIds): array
    {
        $guides = TaxGuide::query()
            ->withoutGlobalScopes()
            ->where('office_id', $office->id)
            ->whereIn('client_id', $clientIds)
            ->orderBy('due_at')
            ->get()
            ->groupBy('client_id');

        $map = [];
        foreach ($clientIds as $cid) {
            /** @var Collection<int, TaxGuide> $rows */
            $rows = $guides->get($cid) ?? collect();
            $open = $rows->filter(fn (TaxGuide $g) => ! in_array(
                $g->payment_status?->value,
                ['CONFIRMED'],
                true,
            ));
            $next = $open->sortBy(fn (TaxGuide $g) => $g->due_at?->getTimestamp() ?? PHP_INT_MAX)->first();
            $unpaidCents = $open->sum(fn (TaxGuide $g) => (int) ($g->amount_cents ?? 0));

            $map[$cid] = [
                'module_key' => FiscalModuleKey::Guides->value,
                'guides_count' => $rows->count(),
                'open_count' => $open->count(),
                'unpaid_amount_cents' => $unpaidCents,
                'next_guide_id' => $next?->id,
                'next_due_at' => $next?->due_at?->toIso8601String(),
                'next_amount_cents' => $next?->amount_cents,
                'next_payment_status' => $next?->payment_status?->value,
                'links' => [
                    'guides' => '/api/v1/fiscal/guides?client_id='.$cid,
                    'guide' => $next !== null ? '/api/v1/fiscal/guides/'.$next->id : null,
                ],
            ];
        }

        return $map;
    }

    /**
     * @param  list<int>  $clientIds
     * @return array<int, array<string, mixed>>
     */
    private function detailFgts(Office $office, array $clientIds): array
    {
        $rows = FgtsCompetenceStatus::query()
            ->withoutGlobalScopes()
            ->where('office_id', $office->id)
            ->whereIn('client_id', $clientIds)
            ->orderByDesc('last_synced_at')
            ->get()
            ->groupBy('client_id');

        $map = [];
        foreach ($clientIds as $cid) {
            /** @var FgtsCompetenceStatus|null $row */
            $row = $rows->get($cid)?->first();
            $map[$cid] = [
                'module_key' => FiscalModuleKey::Fgts->value,
                'competence_period_key' => $row?->competence_period_key,
                'closure_status' => $row?->closure_status?->value,
                'totalization_status' => $row?->totalization_status?->value,
                'guide_status' => $row?->guide_status?->value ?? 'UNSUPPORTED',
                'payment_status' => $row?->payment_status?->value ?? 'UNSUPPORTED',
                'coverage' => $row?->coverage?->value ?? FiscalCoverage::Partial->value,
                'last_synced_at' => $row?->last_synced_at?->toIso8601String(),
                'partial_coverage_notice' => 'Guia e pagamento FGTS Digital permanecem UNSUPPORTED sem API pública M2M.',
                'links' => [
                    'coverage' => '/api/v1/fiscal/fgts/coverage',
                    'competences' => '/api/v1/fiscal/fgts/competences?client_id='.$cid,
                    'events' => '/api/v1/fiscal/fgts/events?client_id='.$cid,
                    'status' => $row !== null ? '/api/v1/fiscal/fgts/competences/'.$row->id : null,
                ],
            ];
        }

        return $map;
    }

    /**
     * @param  array<string, mixed>  $detail
     */
    private function nextActionFor(FiscalModuleKey $module, string $situation, array $detail): ?string
    {
        return match ($situation) {
            FiscalSituation::Error->value => 'Investigar erro da última consulta',
            FiscalSituation::Blocked->value => 'Resolver bloqueio operacional',
            FiscalSituation::Attention->value => match ($module) {
                FiscalModuleKey::Installments => 'Revisar parcelas em atraso',
                FiscalModuleKey::Mailbox => 'Tratar mensagens com prazo',
                FiscalModuleKey::Sitfis => 'Revisar pendências SITFIS',
                default => 'Revisar itens em atenção',
            },
            FiscalSituation::Pending->value => 'Concluir pendências',
            FiscalSituation::Processing->value => 'Aguardar processamento',
            FiscalSituation::Unsupported->value => 'Cobertura parcial / sem fonte M2M',
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $detail
     * @return array<string, string|null>
     */
    private function rowLinks(FiscalModuleKey $module, int $clientId, array $detail): array
    {
        $base = [
            'client' => '/api/v1/clients/'.$clientId,
        ];

        $detailLinks = is_array($detail['links'] ?? null) ? $detail['links'] : [];

        return array_merge($base, $detailLinks);
    }

    private function maskCnpj(?string $cnpj): string
    {
        if ($cnpj === null || $cnpj === '') {
            return '****';
        }

        $clean = strtoupper(Cnpj::normalize($cnpj));
        $len = strlen($clean);
        if ($len < 8) {
            return '****';
        }

        return substr($clean, 0, 4).str_repeat('*', max(0, $len - 8)).substr($clean, -4);
    }

    private function maskRootCnpj(string $root): string
    {
        $clean = strtoupper(Cnpj::normalize($root));
        if (strlen($clean) < 4) {
            return '****';
        }

        return substr($clean, 0, 4).str_repeat('*', max(0, strlen($clean) - 4));
    }

    /**
     * @param  list<string>  $values
     */
    private function quoteList(array $values): string
    {
        if ($values === []) {
            return "'__none__'";
        }

        return implode(',', array_map(
            static fn (string $v) => "'".str_replace("'", "''", $v)."'",
            $values,
        ));
    }
}
