<?php

namespace Tests\Feature\Fiscal\ModulePortfolio;

use App\DTO\Fiscal\FiscalDocumentDescriptorDto;
use App\Enums\DocumentUnavailableReason;
use App\Enums\FiscalCoverage;
use App\Enums\FiscalLinkStatus;
use App\Enums\FiscalRunStatus;
use App\Enums\FiscalSituation;
use App\Enums\FiscalTrigger;
use App\Enums\OfficeRole;
use App\Models\Client;
use App\Models\Establishment;
use App\Models\FiscalCategory;
use App\Models\FiscalCompetence;
use App\Models\FiscalEvidenceArtifact;
use App\Models\FiscalMonitoringRun;
use App\Models\FiscalRegistrationLink;
use App\Models\FiscalSnapshot;
use App\Models\FiscalTaxProcess;
use App\Models\Office;
use App\Models\OfficeFiscalCategoryLink;
use App\Models\User;
use App\Services\FiscalMonitoring\FiscalEvidenceStore;
use App\Services\FiscalMonitoring\Surfaces\MonitoringSurfaceRegistry;
use App\Support\CurrentOffice;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Task 5.2 — descritor de documento no portfolio + download endurecido.
 */
class ModulePortfolioDocumentDescriptorTest extends TestCase
{
    use RefreshDatabase;

    private Office $office;

    private Office $otherOffice;

    private User $admin;

    private User $otherAdmin;

    private FiscalCategory $sitfisCategory;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'features.global_enabled' => true,
            'features.kill_switch' => false,
            'features.modules.sitfis.enabled' => true,
            'features.modules.sitfis.allow_all_offices' => true,
            'features.modules.dctfweb_mit.enabled' => true,
            'features.modules.dctfweb_mit.allow_all_offices' => true,
            'features.modules.mailbox.enabled' => true,
            'features.modules.mailbox.allow_all_offices' => true,
            'features.modules.simples_mei.enabled' => true,
            'features.modules.simples_mei.allow_all_offices' => true,
            'fiscal_monitoring.enabled' => true,
            'fiscal_monitoring.kill_switch' => false,
            'fiscal_monitoring.demo.office_slug' => 'demo',
        ]);

        $this->office = Office::factory()->create(['slug' => 'doc-acme', 'name' => 'Doc Acme']);
        $this->otherOffice = Office::factory()->create(['slug' => 'doc-other', 'name' => 'Doc Other']);
        $this->admin = User::factory()->forOffice($this->office, OfficeRole::Admin)->withTwoFactorConfirmed()->create();
        $this->otherAdmin = User::factory()->forOffice($this->otherOffice, OfficeRole::Admin)->withTwoFactorConfirmed()->create();
        $this->sitfisCategory = FiscalCategory::query()->where('code', 'SITFIS')->firstOrFail();

        $this->client = Client::factory()->forOffice($this->office)->create(['legal_name' => 'Cliente Documento']);
        Establishment::factory()->forClient($this->client)->create();
        OfficeFiscalCategoryLink::query()->create([
            'office_id' => $this->office->id,
            'client_id' => $this->client->id,
            'fiscal_category_id' => $this->sitfisCategory->id,
            'status' => FiscalLinkStatus::Active->value,
            'coverage' => FiscalCoverage::Full->value,
        ]);
        FiscalCompetence::query()->create([
            'office_id' => $this->office->id,
            'client_id' => $this->client->id,
            'fiscal_category_id' => $this->sitfisCategory->id,
            'period_key' => '2026-06',
            'period_year' => 2026,
            'period_month' => 6,
            'situation' => FiscalSituation::Pending,
            'coverage' => FiscalCoverage::Full,
            'due_at' => now()->addDays(10),
        ]);
        $this->createSnapshot($this->office, $this->client, 'INTEGRA_SITFIS', 'SITFIS', FiscalSituation::Pending, 'seed-sitfis');
    }

    public function test_document_absent_without_artifact_is_not_collected(): void
    {
        $this->actingAsOffice($this->admin);

        $row = $this->getJson('/api/v1/fiscal/modules/sitfis/clients?per_page=10')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->json('data.0');

        $this->assertIsArray($row['document'] ?? null);
        $this->assertFalse($row['document']['available']);
        $this->assertNull($row['document']['href']);
        $this->assertSame('NOT_COLLECTED', $row['document']['unavailable_reason']);
        $this->assertSame('sitfis', $row['document']['source_surface']);
        $this->assertStringNotContainsString('content_sha256', json_encode($row));
        $this->assertStringNotContainsString('vault_object_id', json_encode($row));
        $this->assertStringNotContainsString('operation_key', json_encode($row));
    }

    public function test_document_available_only_with_owned_artifact(): void
    {
        $artifact = $this->createEvidenceArtifact(
            $this->office,
            $this->client,
            'sitfis.emitir_relatorio',
            '%PDF-1.4 fake',
            'application/pdf',
        );

        $this->actingAsOffice($this->admin);

        $row = $this->getJson('/api/v1/fiscal/modules/sitfis/clients?per_page=10')
            ->assertOk()
            ->json('data.0');

        $this->assertTrue($row['document']['available']);
        $this->assertSame('PDF', $row['document']['kind']);
        $this->assertSame('application/pdf', $row['document']['content_type']);
        $this->assertSame('/api/v1/fiscal/evidence/'.$artifact->id.'/download', $row['document']['href']);
        $this->assertNull($row['document']['unavailable_reason']);
        $this->assertSame('Ver relatório oficial', $row['document']['label']);

        $json = json_encode($row);
        $this->assertStringNotContainsString('content_sha256', $json);
        $this->assertStringNotContainsString('vault_object_id', $json);
        $this->assertStringNotContainsString('"operation_key"', $json);
        $this->assertStringNotContainsString('run_id', $json);
    }

    public function test_mit_and_mailbox_never_expose_available_document(): void
    {
        $mitCat = FiscalCategory::query()->where('code', 'MIT')->first()
            ?? FiscalCategory::query()->where('module_key', 'dctfweb_mit')->where('code', 'like', '%MIT%')->first();
        $mailboxCat = FiscalCategory::query()->where('code', 'CAIXA_POSTAL')->first()
            ?? FiscalCategory::query()->where('module_key', 'mailbox')->first();

        $this->assertNotNull($mitCat, 'categoria MIT necessária no seed');
        $this->assertNotNull($mailboxCat, 'categoria mailbox necessária no seed');

        foreach ([$mitCat, $mailboxCat] as $cat) {
            OfficeFiscalCategoryLink::query()->firstOrCreate(
                [
                    'office_id' => $this->office->id,
                    'client_id' => $this->client->id,
                    'fiscal_category_id' => $cat->id,
                ],
                [
                    'status' => FiscalLinkStatus::Active->value,
                    'coverage' => FiscalCoverage::Full->value,
                ],
            );
        }

        // Mesmo com artefato no office, MIT/mailbox são STRUCTURED_ONLY
        $this->createEvidenceArtifact(
            $this->office,
            $this->client,
            'mit.consapuracao',
            '%PDF-1.4 should-not-surface',
            'application/pdf',
        );

        $this->actingAsOffice($this->admin);

        $mitRow = $this->getJson('/api/v1/fiscal/modules/dctfweb/clients?submodule=MIT&per_page=10')
            ->assertOk()
            ->json('data.0');

        $this->assertNotNull($mitRow);
        $this->assertFalse($mitRow['document']['available']);
        $this->assertNull($mitRow['document']['href']);
        $this->assertSame('STRUCTURED_ONLY', $mitRow['document']['unavailable_reason']);
        $this->assertSame('mit', $mitRow['document']['source_surface']);

        $mailboxRow = $this->getJson('/api/v1/fiscal/modules/mailbox/clients?per_page=10')
            ->assertOk()
            ->json('data.0');

        $this->assertNotNull($mailboxRow);
        $this->assertFalse($mailboxRow['document']['available']);
        $this->assertNull($mailboxRow['document']['href']);
        $this->assertSame('STRUCTURED_ONLY', $mailboxRow['document']['unavailable_reason']);
    }

    public function test_dasn_surface_is_not_production(): void
    {
        $this->actingAsOffice($this->admin);

        // Overview expõe o contrato mesmo sem linhas (submódulo em prospecção).
        $this->getJson('/api/v1/fiscal/modules/simples_mei/overview?submodule=DASN_SIMEI')
            ->assertOk()
            ->assertJsonPath('data.surface.surface_key', 'simples_mei_dasn')
            ->assertJsonPath('data.surface.result_kind', 'UNAVAILABLE')
            ->assertJsonPath('data.surface.allows_document', false);

        // Descritor fail-closed a partir do contrato da superfície.
        $surface = app(MonitoringSurfaceRegistry::class)
            ->get('simples_mei_dasn');
        $descriptor = FiscalDocumentDescriptorDto::unavailable(
            DocumentUnavailableReason::NotProduction,
            $surface,
        )->toArray();

        $this->assertFalse($descriptor['available']);
        $this->assertNull($descriptor['href']);
        $this->assertSame('NOT_PRODUCTION', $descriptor['unavailable_reason']);
        $this->assertSame('simples_mei_dasn', $descriptor['source_surface']);
    }

    public function test_overview_includes_surface_summary_without_serpro_coords(): void
    {
        $this->actingAsOffice($this->admin);

        $data = $this->getJson('/api/v1/fiscal/modules/sitfis/overview')
            ->assertOk()
            ->assertJsonPath('data.surface.surface_key', 'sitfis')
            ->assertJsonPath('data.surface.allows_document', true)
            ->assertJsonPath('data.surface.result_kind', 'ASYNC_PDF')
            ->json('data');

        $json = json_encode($data);
        $this->assertStringNotContainsString('operation_key', $json);
        $this->assertStringNotContainsString('idSistema', $json);
        $this->assertStringNotContainsString('id_servico', $json);
        $this->assertArrayHasKey('official_state_label', $data['surface']);
        $this->assertArrayHasKey('channel_label', $data['surface']);
    }

    public function test_download_owned_evidence_sanitized_headers(): void
    {
        $artifact = $this->createEvidenceArtifact(
            $this->office,
            $this->client,
            'sitfis.emitir_relatorio',
            '%PDF-1.4 content',
            'application/pdf',
        );

        $this->actingAsOffice($this->admin);

        $response = $this->get('/api/v1/fiscal/evidence/'.$artifact->id.'/download')
            ->assertOk();

        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertNull($response->headers->get('X-Content-SHA256'));
        $this->assertStringContainsString('.pdf', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringNotContainsString('vault', strtolower($response->getContent() ?: ''));
    }

    public function test_cross_office_download_returns_404_without_leakage(): void
    {
        $artifact = $this->createEvidenceArtifact(
            $this->office,
            $this->client,
            'sitfis.emitir_relatorio',
            '%PDF-1.4 secret',
            'application/pdf',
        );

        $this->actingAsOffice($this->otherAdmin);

        $response = $this->getJson('/api/v1/fiscal/evidence/'.$artifact->id.'/download')
            ->assertNotFound();

        $body = $response->getContent() ?: '';
        $this->assertStringNotContainsString((string) $artifact->vault_object_id, $body);
        $this->assertStringNotContainsString((string) $artifact->content_sha256, $body);
        $this->assertStringNotContainsString('vault', strtolower($body));
    }

    public function test_foreign_office_artifact_does_not_make_document_available_in_portfolio(): void
    {
        $otherClient = Client::factory()->forOffice($this->otherOffice)->create(['legal_name' => 'Outro']);
        Establishment::factory()->forClient($otherClient)->create();

        // Artefato no office B com operation_key de SITFIS — não deve vazar para office A.
        $this->createEvidenceArtifact(
            $this->otherOffice,
            $otherClient,
            'sitfis.emitir_relatorio',
            '%PDF-1.4 other-office',
            'application/pdf',
        );

        $this->actingAsOffice($this->admin);

        $row = $this->getJson('/api/v1/fiscal/modules/sitfis/clients?per_page=10')
            ->assertOk()
            ->json('data.0');

        $this->assertFalse($row['document']['available']);
        $this->assertNull($row['document']['href']);
        $this->assertSame('NOT_COLLECTED', $row['document']['unavailable_reason']);
    }

    public function test_portfolio_json_never_leaks_vault_or_serpro_coords_in_document(): void
    {
        $artifact = $this->createEvidenceArtifact(
            $this->office,
            $this->client,
            'sitfis.emitir_relatorio',
            '%PDF-1.4 secret-body',
            'application/pdf',
        );

        $this->actingAsOffice($this->admin);

        $clients = $this->getJson('/api/v1/fiscal/modules/sitfis/clients?per_page=10')
            ->assertOk()
            ->json();
        $overview = $this->getJson('/api/v1/fiscal/modules/sitfis/overview')
            ->assertOk()
            ->json();

        foreach ([$clients, $overview] as $payload) {
            $json = json_encode($payload, JSON_THROW_ON_ERROR);
            $this->assertStringNotContainsString('vault_object_id', $json);
            $this->assertStringNotContainsString('content_sha256', $json);
            $this->assertStringNotContainsString('"operation_key"', $json);
            $this->assertStringNotContainsString('idSistema', $json);
            $this->assertStringNotContainsString('idServico', $json);
            $this->assertStringNotContainsString('%PDF', $json);
            $this->assertStringNotContainsString(base64_encode('%PDF-1.4 secret-body'), $json);
            $this->assertDoesNotMatchRegularExpression('#/var/vault|/vault/|secure-object-store#i', $json);
            $this->assertStringNotContainsString((string) $artifact->vault_object_id, $json);
            $this->assertStringNotContainsString((string) $artifact->content_sha256, $json);
        }

        $doc = $clients['data'][0]['document'];
        $this->assertTrue($doc['available']);
        $this->assertArrayNotHasKey('vault_object_id', $doc);
        $this->assertArrayNotHasKey('content_sha256', $doc);
        $this->assertArrayNotHasKey('operation_key', $doc);
        $this->assertArrayNotHasKey('run_id', $doc);
    }

    public function test_registrations_and_tax_processes_descriptor_never_available(): void
    {
        $registry = app(MonitoringSurfaceRegistry::class);

        foreach (['registrations', 'tax_processes', 'mit', 'mailbox_list', 'mailbox_detail'] as $key) {
            $surface = $registry->get($key);
            $this->assertFalse($surface->allowsDocument, $key);

            $descriptor = FiscalDocumentDescriptorDto::unavailable(
                DocumentUnavailableReason::StructuredOnly,
                $surface,
            )->toArray();

            $this->assertFalse($descriptor['available'], $key);
            $this->assertNull($descriptor['href'], $key);
            $this->assertSame('STRUCTURED_ONLY', $descriptor['unavailable_reason'], $key);
            $this->assertSame($key, $descriptor['source_surface']);
            $this->assertArrayNotHasKey('vault_object_id', $descriptor);
            $this->assertArrayNotHasKey('operation_key', $descriptor);
        }
    }

    public function test_registrations_and_tax_processes_public_lists_have_no_document_action(): void
    {
        FiscalRegistrationLink::query()->create([
            'office_id' => $this->office->id,
            'client_id' => $this->client->id,
            'contributor_cnpj' => '11222333000181',
            'link_key' => 'link-no-doc',
            'status' => 'ACTIVE',
            'is_simulated' => true,
            'operation_key' => 'pnr_contador.consultar_vinculos',
        ]);
        FiscalTaxProcess::query()->create([
            'office_id' => $this->office->id,
            'client_id' => $this->client->id,
            'contributor_cnpj' => '11222333000181',
            'process_number' => 'PROC-1',
            'status' => 'OPEN',
            'is_simulated' => true,
            'operation_key' => 'eprocesso.consultar_por_interessado',
        ]);

        // Artefato no office não inventa download nessas APIs estruturadas.
        $this->createEvidenceArtifact(
            $this->office,
            $this->client,
            'pnr_contador.consultar_vinculos',
            '%PDF-1.4 should-not-surface',
            'application/pdf',
        );

        $this->actingAsOffice($this->admin);

        $reg = $this->getJson('/api/v1/fiscal/registrations')
            ->assertOk()
            ->json();
        $tp = $this->getJson('/api/v1/fiscal/tax-processes')
            ->assertOk()
            ->json();

        foreach ([$reg, $tp] as $payload) {
            $json = json_encode($payload, JSON_THROW_ON_ERROR);
            $this->assertStringNotContainsString('vault_object_id', $json);
            $this->assertStringNotContainsString('content_sha256', $json);
            $this->assertStringNotContainsString('/api/v1/fiscal/evidence/', $json);
            $this->assertStringNotContainsString('%PDF', $json);
            $this->assertDoesNotMatchRegularExpression('#/var/vault#i', $json);

            foreach ($payload['data'] as $row) {
                $this->assertIsArray($row);
                if (array_key_exists('document', $row)) {
                    $this->assertFalse($row['document']['available'] ?? true);
                    $this->assertNull($row['document']['href'] ?? 'x');
                }
            }
        }
    }

    private function actingAsOffice(User $user): void
    {
        $this->actingAs($user);
        app(CurrentOffice::class)->clear();
        app(CurrentOffice::class)->resolve($user);
    }

    private function createSnapshot(
        Office $office,
        Client $client,
        string $system,
        string $service,
        FiscalSituation $situation,
        string $suffix,
    ): void {
        $run = FiscalMonitoringRun::query()->create([
            'office_id' => $office->id,
            'client_id' => $client->id,
            'system_code' => $system,
            'service_code' => $service,
            'operation_code' => 'MONITOR',
            'trigger' => FiscalTrigger::Manual,
            'idempotency_key' => 'doc-desc-'.$suffix.'-'.$client->id,
            'status' => FiscalRunStatus::Completed,
            'situation' => $situation,
            'coverage' => FiscalCoverage::Full,
        ]);

        FiscalSnapshot::query()->create([
            'office_id' => $office->id,
            'run_id' => $run->id,
            'client_id' => $client->id,
            'system_code' => $system,
            'service_code' => $service,
            'situation' => $situation,
            'coverage' => FiscalCoverage::Full,
            'is_current' => true,
            'version' => 1,
            'observed_at' => CarbonImmutable::now(),
        ]);
    }

    private function createEvidenceArtifact(
        Office $office,
        Client $client,
        string $operationKey,
        string $bytes,
        string $contentType,
    ): FiscalEvidenceArtifact {
        $run = FiscalMonitoringRun::query()->create([
            'office_id' => $office->id,
            'client_id' => $client->id,
            'system_code' => 'INTEGRA_SITFIS',
            'service_code' => 'SITFIS',
            'operation_code' => 'EMITIR',
            'operation_key' => $operationKey,
            'trigger' => FiscalTrigger::Manual,
            'idempotency_key' => 'evidence-'.uniqid('', true),
            'status' => FiscalRunStatus::Completed,
            'situation' => FiscalSituation::UpToDate,
            'coverage' => FiscalCoverage::Full,
        ]);

        return app(FiscalEvidenceStore::class)->store(
            $run,
            $bytes,
            $contentType,
            'test',
            null,
            CarbonImmutable::now(),
        );
    }
}
