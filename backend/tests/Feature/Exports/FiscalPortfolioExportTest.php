<?php

namespace Tests\Feature\Exports;

use App\Contracts\SecureObjectStore;
use App\Enums\FiscalCoverage;
use App\Enums\FiscalLinkStatus;
use App\Enums\FiscalRunStatus;
use App\Enums\FiscalSituation;
use App\Enums\FiscalTrigger;
use App\Enums\OfficeRole;
use App\Jobs\BuildExportZipJob;
use App\Models\Client;
use App\Models\Establishment;
use App\Models\Export;
use App\Models\FiscalCategory;
use App\Models\FiscalCompetence;
use App\Models\FiscalMonitoringRun;
use App\Models\FiscalSnapshot;
use App\Models\Office;
use App\Models\OfficeFiscalCategoryLink;
use App\Models\User;
use App\Support\CurrentOffice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;
use ZipArchive;

/**
 * Task 8.3 — export assíncrono de carteira fiscal (export_scope=fiscal_portfolio).
 */
class FiscalPortfolioExportTest extends TestCase
{
    use RefreshDatabase;

    private Office $office;

    private Office $otherOffice;

    private User $operator;

    private User $viewer;

    private FiscalCategory $sitfisCategory;

    private Client $clientA;

    private Client $clientB;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'features.global_enabled' => true,
            'features.kill_switch' => false,
            'features.modules.sitfis.enabled' => true,
            'features.modules.sitfis.allow_all_offices' => true,
            'fiscal_monitoring.enabled' => true,
            'fiscal_monitoring.kill_switch' => false,
            'fiscal_monitoring.demo.office_slug' => 'demo',
        ]);

        $this->office = Office::factory()->create(['slug' => 'export-office', 'name' => 'Export Co']);
        $this->otherOffice = Office::factory()->create(['slug' => 'other-export', 'name' => 'Other Co']);
        $this->operator = User::factory()->forOffice($this->office, OfficeRole::Operator)->withTwoFactorConfirmed()->create();
        $this->viewer = User::factory()->forOffice($this->office, OfficeRole::Viewer)->withTwoFactorConfirmed()->create();
        $this->sitfisCategory = FiscalCategory::query()->where('code', 'SITFIS')->firstOrFail();

        $this->clientA = $this->seedClient($this->office, 'Alpha Export LTDA', FiscalSituation::Pending, '11222333');
        $this->clientB = $this->seedClient($this->office, 'Beta Export SA', FiscalSituation::UpToDate, '44555666');
        // Cliente no outro office com mesmo root — isolamento
        $this->seedClient($this->otherOffice, 'SEGREDO OUTRO OFFICE', FiscalSituation::Error, '11222333');
    }

    public function test_export_fiscal_portfolio_cria_job_e_zip_sanitizado(): void
    {
        Queue::fake();
        $this->actingAsOffice($this->operator);

        $response = $this->postJson('/api/v1/exports', [
            'filters' => [
                'export_scope' => 'fiscal_portfolio',
                'module_key' => 'sitfis',
                'situation' => 'PENDING',
                'q' => 'Alpha',
            ],
        ])->assertStatus(202)
            ->assertJsonPath('data.status', 'PENDING')
            ->assertJsonPath('data.filters.export_scope', 'fiscal_portfolio')
            ->assertJsonPath('data.filters.module_key', 'sitfis')
            ->assertJsonPath('data.filters.situation', 'PENDING');

        Queue::assertPushed(BuildExportZipJob::class);
        $this->assertDatabaseHas('audit_logs', ['action' => 'export.create']);

        $export = Export::query()->findOrFail($response->json('data.id'));
        $this->assertSame($this->office->id, $export->office_id);
        $this->assertArrayNotHasKey('office_id', $export->filters ?? []);

        (new BuildExportZipJob($export->id))->handle(
            app(SecureObjectStore::class),
            app(\App\Services\FiscalMonitoring\ModulePortfolio\ModulePortfolioQueryService::class),
        );

        $export->refresh();
        $this->assertSame('READY', $export->status);
        $this->assertGreaterThanOrEqual(3, $export->files_count);
        $this->assertNotNull($export->storage_path);
        $this->assertFileExists($export->storage_path);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($export->storage_path) === true);

        $names = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $names[] = $zip->getNameIndex($i);
        }

        $this->assertContains('manifest.json', $names);
        $this->assertContains('portfolio.csv', $names);
        $this->assertContains('portfolio.json', $names);

        $manifest = json_decode((string) $zip->getFromName('manifest.json'), true);
        $this->assertIsArray($manifest);
        $this->assertSame('fiscal_portfolio', $manifest['export_scope']);
        $this->assertSame('sitfis', $manifest['module_key']);
        $this->assertSame('LIVE', $manifest['data_origin']);
        $this->assertFalse($manifest['is_demonstration']);
        $this->assertArrayNotHasKey('office_id', $manifest);

        $portfolio = json_decode((string) $zip->getFromName('portfolio.json'), true);
        $this->assertIsArray($portfolio);
        $this->assertCount(1, $portfolio['data']);
        $row = $portfolio['data'][0];
        $this->assertSame($this->clientA->id, $row['client_id']);
        $this->assertSame('Alpha Export LTDA', $row['legal_name']);
        $this->assertStringContainsString('*', $row['cnpj_masked']);
        $this->assertSame('PENDING', $row['situation']);
        $this->assertSame('LIVE', $row['data_origin']);

        $blob = strtolower(json_encode($portfolio) ?: '');
        $this->assertStringNotContainsString('pfx', $blob);
        $this->assertStringNotContainsString('private_key', $blob);
        $this->assertStringNotContainsString('vault', $blob);
        $this->assertStringNotContainsString('consumer_secret', $blob);
        $this->assertStringNotContainsString('segreto outro office', $blob);
        // CNPJ completo do establishment não deve vazar (só mascarado)
        $this->assertStringNotContainsString('11222333000181', $blob);

        $csv = (string) $zip->getFromName('portfolio.csv');
        $this->assertStringContainsString('cnpj_masked', $csv);
        $this->assertStringContainsString('Alpha Export LTDA', $csv);
        $this->assertStringNotContainsString('Beta Export SA', $csv);

        $zip->close();
    }

    public function test_isolamento_entre_offices_no_export_fiscal(): void
    {
        $this->actingAsOffice($this->operator);

        $export = Export::query()->create([
            'office_id' => $this->office->id,
            'user_id' => $this->operator->id,
            'status' => 'PENDING',
            'filters' => [
                'export_scope' => 'fiscal_portfolio',
                'module_key' => 'sitfis',
            ],
            'include_events' => false,
        ]);

        (new BuildExportZipJob($export->id))->handle(
            app(SecureObjectStore::class),
            app(\App\Services\FiscalMonitoring\ModulePortfolio\ModulePortfolioQueryService::class),
        );

        $export->refresh();
        $this->assertSame('READY', $export->status);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($export->storage_path) === true);
        $portfolio = json_decode((string) $zip->getFromName('portfolio.json'), true);
        $zip->close();

        $names = collect($portfolio['data'] ?? [])->pluck('legal_name')->all();
        $this->assertContains('Alpha Export LTDA', $names);
        $this->assertContains('Beta Export SA', $names);
        $this->assertNotContains('SEGREDO OUTRO OFFICE', $names);

        $ids = collect($portfolio['data'] ?? [])->pluck('client_id')->all();
        $this->assertContains($this->clientA->id, $ids);
        $this->assertContains($this->clientB->id, $ids);
    }

    public function test_viewer_recebe_403_em_export_fiscal(): void
    {
        $this->actingAsOffice($this->viewer);

        $this->postJson('/api/v1/exports', [
            'filters' => [
                'export_scope' => 'fiscal_portfolio',
                'module_key' => 'sitfis',
            ],
        ])->assertForbidden();

        $this->assertSame(0, Export::query()->count());
    }

    public function test_office_id_no_body_e_em_filters_e_ignorado(): void
    {
        Queue::fake();
        $this->actingAsOffice($this->operator);

        $response = $this->postJson('/api/v1/exports', [
            'office_id' => $this->otherOffice->id,
            'filters' => [
                'export_scope' => 'fiscal_portfolio',
                'module_key' => 'sitfis',
                'office_id' => $this->otherOffice->id,
            ],
        ])->assertStatus(202);

        $export = Export::query()->findOrFail($response->json('data.id'));
        $this->assertSame($this->office->id, $export->office_id);
        $this->assertArrayNotHasKey('office_id', $export->filters ?? []);
        $this->assertSame('fiscal_portfolio', $export->filters['export_scope'] ?? null);
    }

    public function test_module_key_obrigatorio_e_invalido_rejeitado(): void
    {
        $this->actingAsOffice($this->operator);

        $this->postJson('/api/v1/exports', [
            'filters' => [
                'export_scope' => 'fiscal_portfolio',
            ],
        ])->assertStatus(422);

        $this->postJson('/api/v1/exports', [
            'filters' => [
                'export_scope' => 'fiscal_portfolio',
                'module_key' => 'dashboard',
            ],
        ])->assertStatus(422);

        $this->postJson('/api/v1/exports', [
            'filters' => [
                'export_scope' => 'fiscal_portfolio',
                'module_key' => 'nao_existe',
            ],
        ])->assertStatus(422);
    }

    public function test_export_demo_marca_demonstracao_no_artefato(): void
    {
        $demo = Office::factory()->create(['slug' => 'demo', 'name' => 'Demo']);
        $demoOp = User::factory()->forOffice($demo, OfficeRole::Operator)->withTwoFactorConfirmed()->create();
        $this->seedClient($demo, 'Cliente Demo', FiscalSituation::Attention, '99888777');

        $this->actingAsOffice($demoOp);

        $export = Export::query()->create([
            'office_id' => $demo->id,
            'user_id' => $demoOp->id,
            'status' => 'PENDING',
            'filters' => [
                'export_scope' => 'fiscal_portfolio',
                'module_key' => 'sitfis',
            ],
            'include_events' => false,
        ]);

        (new BuildExportZipJob($export->id))->handle(
            app(SecureObjectStore::class),
            app(\App\Services\FiscalMonitoring\ModulePortfolio\ModulePortfolioQueryService::class),
        );

        $export->refresh();
        $this->assertSame('READY', $export->status);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($export->storage_path) === true);

        $names = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $names[] = $zip->getNameIndex($i);
        }
        $this->assertContains('DEMONSTRACAO.txt', $names);

        $manifest = json_decode((string) $zip->getFromName('manifest.json'), true);
        $this->assertSame('DEMO', $manifest['data_origin']);
        $this->assertTrue($manifest['is_demonstration']);
        $this->assertStringContainsString('DEMONSTRAÇÃO', (string) $manifest['demonstration_banner']);

        $demoTxt = (string) $zip->getFromName('DEMONSTRACAO.txt');
        $this->assertStringContainsString('DEMONSTRAÇÃO', $demoTxt);
        $this->assertStringContainsString('DEMO', $demoTxt);

        $zip->close();
    }

    public function test_client_id_restringe_carteira_exportada(): void
    {
        $this->actingAsOffice($this->operator);

        $export = Export::query()->create([
            'office_id' => $this->office->id,
            'user_id' => $this->operator->id,
            'status' => 'PENDING',
            'filters' => [
                'export_scope' => 'fiscal_portfolio',
                'module_key' => 'sitfis',
                'client_id' => $this->clientB->id,
            ],
            'include_events' => false,
        ]);

        (new BuildExportZipJob($export->id))->handle(
            app(SecureObjectStore::class),
            app(\App\Services\FiscalMonitoring\ModulePortfolio\ModulePortfolioQueryService::class),
        );

        $export->refresh();
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($export->storage_path) === true);
        $portfolio = json_decode((string) $zip->getFromName('portfolio.json'), true);
        $zip->close();

        $this->assertCount(1, $portfolio['data']);
        $this->assertSame($this->clientB->id, $portfolio['data'][0]['client_id']);
    }

    private function actingAsOffice(User $user): void
    {
        $this->actingAs($user);
        app(CurrentOffice::class)->resolve($user);
    }

    private function seedClient(
        Office $office,
        string $legalName,
        FiscalSituation $situation,
        string $rootPrefix,
    ): Client {
        $client = Client::factory()->forOffice($office)->create([
            'legal_name' => $legalName,
            'root_cnpj' => $rootPrefix,
        ]);
        Establishment::factory()->forClient($client)->create([
            'cnpj' => $rootPrefix.'000181',
            'is_matrix' => true,
        ]);

        OfficeFiscalCategoryLink::query()->create([
            'office_id' => $office->id,
            'client_id' => $client->id,
            'fiscal_category_id' => $this->sitfisCategory->id,
            'status' => FiscalLinkStatus::Active,
            'coverage' => FiscalCoverage::Full,
            'activated_at' => now(),
        ]);

        FiscalCompetence::query()->create([
            'office_id' => $office->id,
            'client_id' => $client->id,
            'fiscal_category_id' => $this->sitfisCategory->id,
            'period_key' => '2026-06',
            'period_year' => 2026,
            'period_month' => 6,
            'situation' => $situation,
            'coverage' => FiscalCoverage::Full,
            'due_at' => now()->addDays(5),
        ]);

        $run = FiscalMonitoringRun::query()->create([
            'office_id' => $office->id,
            'client_id' => $client->id,
            'system_code' => 'INTEGRA_SITFIS',
            'service_code' => 'SITFIS',
            'operation_code' => 'MONITOR',
            'trigger' => FiscalTrigger::Manual,
            'idempotency_key' => 'export-run-'.$office->id.'-'.$client->id.'-'.uniqid('', true),
            'status' => FiscalRunStatus::Completed,
            'situation' => $situation,
            'coverage' => FiscalCoverage::Full,
        ]);

        FiscalSnapshot::query()->create([
            'office_id' => $office->id,
            'run_id' => $run->id,
            'client_id' => $client->id,
            'system_code' => 'INTEGRA_SITFIS',
            'service_code' => 'SITFIS',
            'operation_code' => 'MONITOR',
            'situation' => $situation,
            'coverage' => FiscalCoverage::Full,
            'version' => 1,
            'is_current' => true,
            'normalized' => ['ok' => true, 'is_negative_certificate' => false],
            'observed_at' => now(),
            'created_at' => now(),
        ]);

        return $client;
    }
}
