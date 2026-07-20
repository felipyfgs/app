<?php

namespace Tests\Feature;

use App\DTO\Fiscal\Module\ModulePortfolioFilters;
use App\Enums\FiscalModuleKey;
use App\Enums\TaxRegimeCode;
use App\Models\Client;
use App\Models\Office;
use App\Services\FiscalMonitoring\ModulePortfolio\ModulePortfolioQueryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModulePortfolioSimplesMeiSubmoduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_pgdasd_submodule_lists_only_simples_nacional_clients(): void
    {
        [$office, $sn, $mei, $other] = $this->seedMixedRegimeClients();
        $portfolio = app(ModulePortfolioQueryService::class);

        $page = $portfolio->clients(
            $office,
            FiscalModuleKey::SimplesMei,
            ModulePortfolioFilters::fromRequest(['submodule' => 'PGDASD', 'per_page' => 50]),
        );

        $ids = collect($page->items())->map(fn ($row) => $row->clientId)->all();
        $this->assertContains($sn->id, $ids);
        $this->assertNotContains($mei->id, $ids);
        $this->assertNotContains($other->id, $ids);
        $this->assertSame(1, $page->total());
    }

    public function test_pgmei_submodule_lists_only_mei_clients(): void
    {
        [$office, $sn, $mei, $other] = $this->seedMixedRegimeClients();
        $portfolio = app(ModulePortfolioQueryService::class);

        $page = $portfolio->clients(
            $office,
            FiscalModuleKey::SimplesMei,
            ModulePortfolioFilters::fromRequest(['submodule' => 'PGMEI', 'per_page' => 50]),
        );

        $ids = collect($page->items())->map(fn ($row) => $row->clientId)->all();
        $this->assertContains($mei->id, $ids);
        $this->assertNotContains($sn->id, $ids);
        $this->assertNotContains($other->id, $ids);
        $this->assertSame(1, $page->total());
    }

    public function test_overview_counters_follow_regime_scope(): void
    {
        [$office] = $this->seedMixedRegimeClients();
        $portfolio = app(ModulePortfolioQueryService::class);

        $pgdasd = $portfolio->overview(
            $office,
            FiscalModuleKey::SimplesMei,
            ModulePortfolioFilters::fromRequest(['submodule' => 'PGDASD']),
        );
        $pgmei = $portfolio->overview(
            $office,
            FiscalModuleKey::SimplesMei,
            ModulePortfolioFilters::fromRequest(['submodule' => 'PGMEI']),
        );

        $this->assertSame(1, $pgdasd->totalClients);
        $this->assertSame(1, $pgmei->totalClients);
    }

    public function test_does_not_include_matching_regime_from_other_office(): void
    {
        [$office] = $this->seedMixedRegimeClients();
        $otherOffice = Office::factory()->create();
        Client::factory()->for($otherOffice)->create([
            'is_active' => true,
            'matrix_client_id' => null,
            'tax_regime' => TaxRegimeCode::SimplesNacional->value,
        ]);

        $portfolio = app(ModulePortfolioQueryService::class);
        $page = $portfolio->clients(
            $office,
            FiscalModuleKey::SimplesMei,
            ModulePortfolioFilters::fromRequest(['submodule' => 'PGDASD', 'per_page' => 50]),
        );

        $this->assertSame(1, $page->total());
    }

    public function test_legacy_simples_alias_matches_pgdasd_scope(): void
    {
        $office = Office::factory()->create();
        $legacy = Client::factory()->for($office)->create([
            'is_active' => true,
            'matrix_client_id' => null,
            'tax_regime' => 'SIMPLES',
        ]);

        $portfolio = app(ModulePortfolioQueryService::class);
        $page = $portfolio->clients(
            $office,
            FiscalModuleKey::SimplesMei,
            ModulePortfolioFilters::fromRequest(['submodule' => 'PGDASD', 'per_page' => 50]),
        );

        $ids = collect($page->items())->map(fn ($row) => $row->clientId)->all();
        $this->assertContains($legacy->id, $ids);
    }

    /**
     * @return array{0: Office, 1: Client, 2: Client, 3: Client}
     */
    private function seedMixedRegimeClients(): array
    {
        $office = Office::factory()->create();

        $sn = Client::factory()->for($office)->create([
            'is_active' => true,
            'matrix_client_id' => null,
            'tax_regime' => TaxRegimeCode::SimplesNacional->value,
        ]);
        $mei = Client::factory()->for($office)->create([
            'is_active' => true,
            'matrix_client_id' => null,
            'tax_regime' => TaxRegimeCode::Mei->value,
        ]);
        $other = Client::factory()->for($office)->create([
            'is_active' => true,
            'matrix_client_id' => null,
            'tax_regime' => TaxRegimeCode::LucroPresumido->value,
        ]);

        return [$office, $sn, $mei, $other];
    }
}
