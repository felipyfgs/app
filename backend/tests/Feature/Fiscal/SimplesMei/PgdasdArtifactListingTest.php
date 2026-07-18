<?php

namespace Tests\Feature\Fiscal\SimplesMei;

use App\Enums\FiscalCoverage;
use App\Enums\FiscalRunStatus;
use App\Enums\FiscalSituation;
use App\Enums\FiscalTrigger;
use App\Enums\OfficeRole;
use App\Models\Client;
use App\Models\FiscalMonitoringRun;
use App\Models\Office;
use App\Models\PgdasdArtifact;
use App\Models\User;
use App\Services\Fiscal\Declarations\TaxObligationCatalogService;
use App\Services\Fiscal\Declarations\TaxObligationProjectionService;
use App\Services\FiscalMonitoring\FiscalEvidenceStore;
use App\Support\CurrentOffice;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Contrato offline dos documentos PGDAS-D já persistidos: não cria jobs nem
 * instancia clientes SERPRO; usa somente cofre de teste e fixtures curtas.
 */
class PgdasdArtifactListingTest extends TestCase
{
    use RefreshDatabase;

    private const BASE = '/api/v1/fiscal/simples-mei/pgdasd';

    private Office $office;

    private User $admin;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'features.global_enabled' => true,
            'features.kill_switch' => false,
            'features.modules.simples_mei.enabled' => true,
            'features.modules.simples_mei.allow_all_offices' => true,
            'fiscal_monitoring.enabled' => true,
            'fiscal_monitoring.kill_switch' => false,
            'serpro.trial.use_fake_clients' => true,
            'serpro.capabilities.simples_mei' => 'real',
        ]);

        $this->office = Office::factory()->create();
        $this->client = Client::factory()->forOffice($this->office)->create();
        $this->admin = User::factory()
            ->forOffice($this->office, OfficeRole::Admin)
            ->withTwoFactorConfirmed()
            ->create();

        $this->actingAsOffice($this->admin);
    }

    #[Test]
    public function lista_cinco_documentos_persistidos_sem_vazar_dados_do_cofre_e_baixa_apenas_no_office_atual(): void
    {
        $artifacts = $this->createPersistedDocuments($this->office, $this->client);

        $portfolio = $this->getJson('/api/v1/fiscal/modules/simples_mei/clients?submodule=PGDASD&per_page=10')
            ->assertOk()
            ->json('data.0.detail.pgdasd.documents');
        $this->assertIsArray($portfolio);
        $this->assertCount(5, $portfolio);
        $this->assertSame(
            '/api/v1/fiscal/simples-mei/pgdasd/artifacts/'.$artifacts[0]->id.'/download',
            collect($portfolio)->firstWhere('id', $artifacts[0]->id)['download_path'] ?? null,
        );

        $history = $this->getJson(self::BASE."/clients/{$this->client->id}/history")
            ->assertOk()
            ->json('data');

        $documents = $history['periods'][0]['documents'] ?? [];
        $this->assertCount(5, $documents);
        $kinds = array_column($documents, 'kind');
        sort($kinds);
        $this->assertSame(
            ['DARF_MAED', 'DECLARACAO', 'EXTRATO', 'NOTIFICACAO_MAED', 'RECIBO'],
            $kinds,
        );
        foreach ($documents as $document) {
            $this->assertArrayHasKey('id', $document);
            $this->assertArrayHasKey('download_path', $document);
            $this->assertStringStartsWith(self::BASE.'/artifacts/', (string) $document['download_path']);
            $this->assertArrayNotHasKey('vault_object_id', $document);
            $this->assertArrayNotHasKey('content_sha256', $document);
            $this->assertArrayNotHasKey('operation_key', $document);
        }
        $historyJson = json_encode($history, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('vault_object_id', $historyJson);
        $this->assertStringNotContainsString('content_sha256', $historyJson);
        $this->assertStringNotContainsString('offline-pgdasd-fixture-', $historyJson);

        $download = $this->get($documents[0]['download_path'])->assertOk();
        $this->assertSame('text/plain; charset=utf-8', $download->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment;', (string) $download->headers->get('Content-Disposition'));
        $this->assertStringContainsString('no-store', (string) $download->headers->get('Cache-Control'));

        $otherOffice = Office::factory()->create();
        $otherAdmin = User::factory()
            ->forOffice($otherOffice, OfficeRole::Admin)
            ->withTwoFactorConfirmed()
            ->create();
        $this->actingAsOffice($otherAdmin);

        $crossTenant = $this->getJson($documents[0]['download_path'])->assertNotFound();
        $this->assertStringNotContainsString((string) $artifacts[0]->fiscal_evidence_artifact_id, $crossTenant->getContent() ?: '');
    }

    /**
     * @return list<PgdasdArtifact>
     */
    private function createPersistedDocuments(Office $office, Client $client): array
    {
        $projection = app(TaxObligationProjectionService::class)->project(
            $office,
            $client,
            app(TaxObligationCatalogService::class)->findByCode('PGDAS_D'),
            '2026-06',
        );
        $run = FiscalMonitoringRun::query()->create([
            'office_id' => $office->id,
            'client_id' => $client->id,
            'system_code' => 'INTEGRA_SN',
            'service_code' => 'PGDASD',
            'operation_code' => 'OFFLINE_FIXTURE',
            'operation_key' => 'pgdasd.offline_fixture',
            'trigger' => FiscalTrigger::Manual,
            'idempotency_key' => 'pgdasd-artifact-list-'.$client->id,
            'status' => FiscalRunStatus::Completed,
            'situation' => FiscalSituation::Unknown,
            'coverage' => FiscalCoverage::Full,
        ]);

        $created = [];
        foreach (['DECLARACAO', 'RECIBO', 'NOTIFICACAO_MAED', 'DARF_MAED', 'EXTRATO'] as $index => $kind) {
            $evidence = app(FiscalEvidenceStore::class)->store(
                $run,
                'offline-pgdasd-fixture-'.$kind,
                'text/plain',
                'test-fixture',
                null,
                CarbonImmutable::parse('2026-06-20 12:00:00')->addSeconds($index),
            );
            $created[] = PgdasdArtifact::query()->create([
                'office_id' => $office->id,
                'client_id' => $client->id,
                'projection_id' => $projection->id,
                'fiscal_evidence_artifact_id' => $evidence->id,
                'kind' => $kind,
                'filename' => strtolower($kind).'.txt',
                'content_type' => 'text/plain',
                'observed_at' => CarbonImmutable::parse('2026-06-20 12:00:00')->addSeconds($index),
                'metadata' => ['period_key' => '2026-06'],
            ]);
        }

        return $created;
    }

    private function actingAsOffice(User $user): void
    {
        $this->actingAs($user);
        app(CurrentOffice::class)->clear();
        app(CurrentOffice::class)->resolve($user);
    }
}
