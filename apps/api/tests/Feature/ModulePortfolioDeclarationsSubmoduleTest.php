<?php

namespace Tests\Feature;

use App\DTO\Fiscal\Module\ModulePortfolioFilters;
use App\Enums\FiscalCoverage;
use App\Enums\FiscalModuleKey;
use App\Enums\FiscalMutability;
use App\Enums\FiscalSituation;
use App\Enums\TaxObligationApplicability;
use App\Models\Client;
use App\Models\FiscalCategory;
use App\Models\Office;
use App\Models\TaxObligationDefinition;
use App\Models\TaxObligationProjection;
use App\Services\FiscalMonitoring\ModulePortfolio\ModulePortfolioQueryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModulePortfolioDeclarationsSubmoduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_declarations_known_submodules_include_obligation_tabs(): void
    {
        $this->assertSame(
            ['PGDAS', 'DCTFWEB', 'FGTS', 'DEFIS', 'DIRF', 'DECLARACOES'],
            FiscalModuleKey::Declarations->knownSubmodules(),
        );
    }

    public function test_pgdas_submodule_filters_to_pgdas_d_projections(): void
    {
        [$office, $pgdasClient, $defisClient] = $this->seedObligationClients();
        $portfolio = app(ModulePortfolioQueryService::class);

        $page = $portfolio->clients(
            $office,
            FiscalModuleKey::Declarations,
            ModulePortfolioFilters::fromRequest(['submodule' => 'PGDAS', 'per_page' => 50]),
        );

        $ids = collect($page->items())->map(fn ($row) => $row->clientId)->all();
        $this->assertContains($pgdasClient->id, $ids);
        $this->assertNotContains($defisClient->id, $ids);

        $row = collect($page->items())->first(fn ($r) => $r->clientId === $pgdasClient->id);
        $this->assertNotNull($row);
        $this->assertSame('PGDAS_D', $row->detail['next_obligation_code'] ?? null);
        $this->assertSame('PGDAS', $row->detail['submodule'] ?? null);
    }

    public function test_dirf_submodule_returns_empty_unsupported_coverage(): void
    {
        [$office] = $this->seedObligationClients();
        $portfolio = app(ModulePortfolioQueryService::class);

        $overview = $portfolio->overview(
            $office,
            FiscalModuleKey::Declarations,
            ModulePortfolioFilters::fromRequest(['submodule' => 'DIRF']),
        );
        $this->assertSame(FiscalCoverage::Unsupported->value, $overview->coverage);
        $this->assertSame(0, $overview->totalClients);

        $page = $portfolio->clients(
            $office,
            FiscalModuleKey::Declarations,
            ModulePortfolioFilters::fromRequest(['submodule' => 'DIRF']),
        );
        $this->assertSame(0, $page->total());
    }

    /**
     * @return array{0: Office, 1: Client, 2: Client}
     */
    private function seedObligationClients(): array
    {
        $office = Office::factory()->create();
        $this->ensureDeclarationsCategory();

        $pgdasDef = TaxObligationDefinition::query()->firstOrCreate(
            ['code' => 'PGDAS_D'],
            [
                'name' => 'PGDAS-D',
                'system_code' => 'INTEGRA_SN',
                'service_code' => 'PGDASD',
                'is_active' => true,
                'sort_order' => 10,
            ],
        );
        $defisDef = TaxObligationDefinition::query()->firstOrCreate(
            ['code' => 'DEFIS'],
            [
                'name' => 'DEFIS',
                'system_code' => 'INTEGRA_SN',
                'service_code' => 'DEFIS',
                'is_active' => true,
                'sort_order' => 20,
            ],
        );

        $pgdasClient = Client::factory()->for($office)->create([
            'is_active' => true,
            'matrix_client_id' => null,
        ]);
        $defisClient = Client::factory()->for($office)->create([
            'is_active' => true,
            'matrix_client_id' => null,
        ]);

        TaxObligationProjection::query()->withoutGlobalScopes()->create([
            'office_id' => $office->id,
            'client_id' => $pgdasClient->id,
            'obligation_definition_id' => $pgdasDef->id,
            'period_key' => '2026-06',
            'period_year' => 2026,
            'period_month' => 6,
            'is_open' => true,
            'situation' => FiscalSituation::Pending,
            'delivery_status' => FiscalSituation::Pending,
            'applicability' => TaxObligationApplicability::Applicable,
        ]);
        TaxObligationProjection::query()->withoutGlobalScopes()->create([
            'office_id' => $office->id,
            'client_id' => $defisClient->id,
            'obligation_definition_id' => $defisDef->id,
            'period_key' => '2025',
            'period_year' => 2025,
            'period_month' => null,
            'is_open' => true,
            'situation' => FiscalSituation::Pending,
            'delivery_status' => FiscalSituation::Pending,
            'applicability' => TaxObligationApplicability::Applicable,
        ]);

        return [$office, $pgdasClient, $defisClient];
    }

    private function ensureDeclarationsCategory(): void
    {
        FiscalCategory::query()->firstOrCreate(
            ['code' => 'DECLARACOES'],
            [
                'name' => 'Declarações',
                'module_key' => 'declaracoes',
                'default_coverage' => FiscalCoverage::Partial,
                'default_mutability' => FiscalMutability::ReadOnly,
                'system_code' => 'INTEGRA_CONTADOR',
                'service_code' => 'DECLARACAO',
                'is_active' => true,
                'sort_order' => 80,
            ],
        );
    }
}
