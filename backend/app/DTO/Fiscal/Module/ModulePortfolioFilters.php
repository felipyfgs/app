<?php

namespace App\DTO\Fiscal\Module;

use App\Enums\FiscalCoverage;
use App\Enums\FiscalSituation;
use App\Enums\TaxInstallmentModality;

/**
 * Filtros normalizados da carteira/overview (mesmo escopo para contadores e lista).
 */
final readonly class ModulePortfolioFilters
{
    public function __construct(
        public int $page = 1,
        public int $perPage = 15,
        public ?string $q = null,
        public ?string $situation = null,
        public ?string $competence = null,
        public ?string $submodule = null,
        public ?string $deliveryStatus = null,
        public string $sort = 'legal_name',
        public string $sortDirection = 'asc',
        public ?int $clientId = null,
        public ?string $coverage = null,
        public ?string $modality = null,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public static function fromRequest(array $input): self
    {
        $page = max(1, (int) ($input['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($input['per_page'] ?? 15)));

        $q = isset($input['q']) && is_string($input['q']) ? trim($input['q']) : null;
        if ($q === '') {
            $q = null;
        }

        $situation = isset($input['situation']) && is_string($input['situation'])
            ? strtoupper(trim($input['situation']))
            : null;
        if ($situation === '') {
            $situation = null;
        }

        $competence = isset($input['competence']) && is_string($input['competence'])
            ? trim($input['competence'])
            : null;
        if ($competence === '') {
            $competence = null;
        }

        $submodule = isset($input['submodule']) && is_string($input['submodule'])
            ? strtoupper(trim($input['submodule']))
            : null;
        if ($submodule === '') {
            $submodule = null;
        }

        $delivery = isset($input['delivery_status']) && is_string($input['delivery_status'])
            ? strtoupper(trim($input['delivery_status']))
            : null;
        if ($delivery === '') {
            $delivery = null;
        }

        $sort = isset($input['sort']) && is_string($input['sort'])
            ? strtolower(trim($input['sort']))
            : 'legal_name';
        $allowedSort = ['legal_name', 'display_name', 'situation', 'last_consulted_at', 'competence', 'id'];
        if (! in_array($sort, $allowedSort, true)) {
            $sort = 'legal_name';
        }

        $dir = isset($input['sort_direction']) && is_string($input['sort_direction'])
            ? strtolower(trim($input['sort_direction']))
            : 'asc';
        if (! in_array($dir, ['asc', 'desc'], true)) {
            $dir = 'asc';
        }

        $clientId = isset($input['client_id']) && is_numeric($input['client_id'])
            ? (int) $input['client_id']
            : null;
        if ($clientId !== null && $clientId < 1) {
            $clientId = null;
        }

        $coverage = isset($input['coverage']) && is_string($input['coverage'])
            ? strtoupper(trim($input['coverage']))
            : null;
        if ($coverage === '' || FiscalCoverage::tryFrom((string) $coverage) === null) {
            $coverage = null;
        }

        $modality = isset($input['modality']) && is_string($input['modality'])
            ? strtoupper(trim($input['modality']))
            : null;
        if ($modality === '' || TaxInstallmentModality::tryFrom((string) $modality) === null) {
            $modality = null;
        }

        return new self(
            page: $page,
            perPage: $perPage,
            q: $q,
            situation: $situation,
            competence: $competence,
            submodule: $submodule,
            deliveryStatus: $delivery,
            sort: $sort,
            sortDirection: $dir,
            clientId: $clientId,
            coverage: $coverage,
            modality: $modality,
        );
    }

    /**
     * Cópia com paginação (export assíncrono itera páginas).
     */
    public function withPage(int $page, int $perPage = 100): self
    {
        return new self(
            page: max(1, $page),
            perPage: min(100, max(1, $perPage)),
            q: $this->q,
            situation: $this->situation,
            competence: $this->competence,
            submodule: $this->submodule,
            deliveryStatus: $this->deliveryStatus,
            sort: $this->sort,
            sortDirection: $this->sortDirection,
            clientId: $this->clientId,
            coverage: $this->coverage,
            modality: $this->modality,
        );
    }

    public function situationEnum(): ?FiscalSituation
    {
        if ($this->situation === null) {
            return null;
        }

        return FiscalSituation::tryFrom($this->situation);
    }
}
