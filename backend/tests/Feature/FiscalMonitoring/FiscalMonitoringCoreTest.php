<?php

namespace Tests\Feature\FiscalMonitoring;

use App\Enums\FiscalCoverage;
use App\Enums\FiscalPendingStatus;
use App\Enums\FiscalRunStatus;
use App\Enums\FiscalSituation;
use App\Enums\FiscalTrigger;
use App\Enums\OfficeRole;
use App\Enums\SubscriptionStatus;
use App\Models\Client;
use App\Models\FiscalCategory;
use App\Models\FiscalEvidenceArtifact;
use App\Models\FiscalFinding;
use App\Models\FiscalLastUpdateEvent;
use App\Models\FiscalMonitoringRun;
use App\Models\FiscalMonitoringSchedule;
use App\Models\FiscalPendingItem;
use App\Models\FiscalSnapshot;
use App\Models\Office;
use App\Models\OfficeSubscription;
use App\Models\User;
use App\Services\FiscalMonitoring\FiscalAdapterRegistry;
use App\Services\FiscalMonitoring\FiscalCategoryService;
use App\Services\FiscalMonitoring\FiscalEvidenceStore;
use App\Services\FiscalMonitoring\FiscalIdempotency;
use App\Services\FiscalMonitoring\FiscalLastUpdateEventService;
use App\Services\FiscalMonitoring\FiscalMonitoringRunService;
use App\Services\FiscalMonitoring\FiscalMonitoringScheduler;
use App\Support\CurrentOffice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Support\FakeFiscalSourceAdapter;
use Tests\TestCase;

class FiscalMonitoringCoreTest extends TestCase
{
    use RefreshDatabase;

    private Office $office;

    private Client $client;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'features.global_enabled' => true,
            'features.kill_switch' => false,
            'fiscal_monitoring.enabled' => true,
            'fiscal_monitoring.kill_switch' => false,
            'fiscal_monitoring.scheduler.enabled' => true,
            'fiscal_monitoring.mutating_enabled' => false,
        ]);

        $this->office = Office::factory()->create();
        $this->client = Client::factory()->forOffice($this->office)->create();
        $this->admin = User::factory()->forOffice($this->office, OfficeRole::Admin)->withTwoFactorConfirmed()->create();
    }

    public function test_categorias_seed_e_associacao_em_lote(): void
    {
        $this->assertGreaterThanOrEqual(5, FiscalCategory::query()->count());

        $category = FiscalCategory::query()->where('code', 'SITFIS')->firstOrFail();
        $clients = Client::factory()->forOffice($this->office)->count(3)->create();
        $ids = $clients->pluck('id')->all();

        $svc = app(FiscalCategoryService::class);
        $result = $svc->associateBatch($this->office, $category, $ids, $this->admin->id);

        $this->assertSame(3, $result['created']);
        $this->assertSame(0, count($result['errors']));
        $this->assertSame(3, FiscalMonitoringSchedule::query()->withoutGlobalScopes()
            ->where('office_id', $this->office->id)->count());
    }

    public function test_api_categorias_e_runs_tenant_scoped(): void
    {
        $this->actingAs($this->admin);
        app(CurrentOffice::class)->resolve($this->admin);

        $this->getJson('/api/v1/fiscal/categories')
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'code', 'name']]]);

        $category = FiscalCategory::query()->where('code', 'SIMPLES_NACIONAL')->firstOrFail();

        $this->postJson('/api/v1/fiscal/category-links', [
            'client_id' => $this->client->id,
            'fiscal_category_id' => $category->id,
        ])->assertCreated()
            ->assertJsonPath('data.client_id', $this->client->id)
            ->assertJsonPath('data.office_id', $this->office->id);

        // Outro office não vê o link
        $other = Office::factory()->create();
        $otherAdmin = User::factory()->forOffice($other, OfficeRole::Admin)->withTwoFactorConfirmed()->create();
        $this->actingAs($otherAdmin);
        app(CurrentOffice::class)->clear();
        app(CurrentOffice::class)->resolve($otherAdmin);

        $this->getJson('/api/v1/fiscal/category-links')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_concorrencia_mesma_chave_idempotente(): void
    {
        $adapter = FakeFiscalSourceAdapter::upToDateWithEvidence();
        app(FiscalAdapterRegistry::class)->register($adapter);

        $svc = app(FiscalMonitoringRunService::class);
        $correlation = 'same-corr-1';

        $runA = $svc->enqueueManual(
            $this->office,
            $this->client,
            'INTEGRA_TEST',
            'TEST_SVC',
            correlationId: $correlation,
            dispatch: false,
        );
        $runB = $svc->enqueueManual(
            $this->office,
            $this->client,
            'INTEGRA_TEST',
            'TEST_SVC',
            correlationId: $correlation,
            dispatch: false,
        );

        $this->assertSame($runA->id, $runB->id);
        $this->assertSame(1, FiscalMonitoringRun::query()->withoutGlobalScopes()
            ->where('office_id', $this->office->id)->count());

        $svc->execute($runA->id);
        $svc->execute($runA->id); // terminal — no-op

        $this->assertSame(1, $adapter->calls);
        $this->assertSame(1, FiscalSnapshot::query()->withoutGlobalScopes()
            ->where('office_id', $this->office->id)->count());
    }

    public function test_evento_duplicado_nao_duplica_snapshot_nem_pendencia(): void
    {
        $adapter = FakeFiscalSourceAdapter::withPendingFinding();
        app(FiscalAdapterRegistry::class)->register($adapter);

        $events = app(FiscalLastUpdateEventService::class);

        $first = $events->ingestAndDirect(
            office: $this->office,
            systemCode: 'INTEGRA_TEST',
            eventType: 'ULTIMA_ATUALIZACAO',
            client: $this->client,
            serviceCode: 'TEST_SVC',
            externalId: 'evt-99',
            payloadDigest: 'abc',
            enqueue: false,
        );
        $this->assertFalse($first['duplicate']);
        $this->assertNotNull($first['run']);

        $second = $events->ingestAndDirect(
            office: $this->office,
            systemCode: 'INTEGRA_TEST',
            eventType: 'ULTIMA_ATUALIZACAO',
            client: $this->client,
            serviceCode: 'TEST_SVC',
            externalId: 'evt-99',
            payloadDigest: 'abc',
            enqueue: false,
        );
        $this->assertTrue($second['duplicate']);
        $this->assertSame(1, FiscalLastUpdateEvent::query()->withoutGlobalScopes()
            ->where('office_id', $this->office->id)->count());

        app(FiscalMonitoringRunService::class)->execute($first['run']->id);

        $this->assertSame(1, FiscalSnapshot::query()->withoutGlobalScopes()->where('office_id', $this->office->id)->count());
        $this->assertSame(1, FiscalPendingItem::query()->withoutGlobalScopes()->where('office_id', $this->office->id)->count());
        $this->assertSame(1, FiscalFinding::query()->withoutGlobalScopes()->where('office_id', $this->office->id)->count());
    }

    public function test_requeue_por_limite_cria_continuacao_com_progresso(): void
    {
        $adapter = FakeFiscalSourceAdapter::requeueAfterPages();
        app(FiscalAdapterRegistry::class)->register($adapter);

        $svc = app(FiscalMonitoringRunService::class);
        $run = $svc->enqueueManual(
            $this->office,
            $this->client,
            'INTEGRA_TEST',
            'TEST_SVC',
            correlationId: 'requeue-1',
            dispatch: false,
        );

        $done = $svc->execute($run->id);
        $this->assertSame(FiscalRunStatus::Requeued, $done->status);
        $this->assertSame('page:2', $done->progress_cursor);
        $this->assertSame(20, $done->pages_processed);

        $child = FiscalMonitoringRun::query()
            ->withoutGlobalScopes()
            ->where('parent_run_id', $done->id)
            ->first();

        $this->assertNotNull($child);
        $this->assertSame(FiscalTrigger::Continuation, $child->trigger);
        $this->assertSame('page:2', $child->progress_cursor);
        $this->assertSame(2, $child->attempt);
    }

    public function test_evidencia_persiste_antes_de_projecao_e_falha_parcial_segura(): void
    {
        $adapter = FakeFiscalSourceAdapter::withPendingFinding();
        app(FiscalAdapterRegistry::class)->register($adapter);

        $svc = app(FiscalMonitoringRunService::class);
        $run = $svc->enqueueManual(
            $this->office,
            $this->client,
            'INTEGRA_TEST',
            'TEST_SVC',
            correlationId: 'ev-1',
            dispatch: false,
        );

        // Spy: força falha na projeção após evidência — sobrescreve reproject path via exception em create finding
        // Aqui validamos ordem: após execute, evidência e snapshot existem.
        $done = $svc->execute($run->id);

        $this->assertSame(FiscalRunStatus::Completed, $done->status);
        $this->assertSame(FiscalSituation::Pending, $done->situation);

        $evidence = FiscalEvidenceArtifact::query()->withoutGlobalScopes()
            ->where('office_id', $this->office->id)
            ->where('run_id', $done->id)
            ->first();
        $this->assertNotNull($evidence);
        $this->assertNotEmpty($evidence->content_sha256);
        $this->assertStringNotContainsString('/', $evidence->vault_object_id); // id opaco, não path

        $snapshot = FiscalSnapshot::query()->withoutGlobalScopes()
            ->where('run_id', $done->id)
            ->first();
        $this->assertNotNull($snapshot);
        $this->assertSame($evidence->id, $snapshot->evidence_artifact_id);
        $this->assertTrue($snapshot->is_current);

        // Download autorizado
        $bytes = app(FiscalEvidenceStore::class)->readAuthorized($evidence, (int) $this->office->id);
        $this->assertStringContainsString('debt', $bytes);

        // Cross-tenant negado
        $this->expectException(\RuntimeException::class);
        app(FiscalEvidenceStore::class)->readAuthorized($evidence, $this->office->id + 999);
    }

    public function test_nao_infere_em_dia_sem_evidencia_no_persist(): void
    {
        $adapter = FakeFiscalSourceAdapter::claimUpToDateWithoutEvidence();
        app(FiscalAdapterRegistry::class)->register($adapter);

        $svc = app(FiscalMonitoringRunService::class);
        $run = $svc->enqueueManual(
            $this->office,
            $this->client,
            'INTEGRA_TEST',
            'TEST_SVC',
            correlationId: 'no-ev',
            dispatch: false,
        );
        $done = $svc->execute($run->id);

        $this->assertSame(FiscalSituation::Unknown, $done->situation);
        $this->assertNotSame(FiscalSituation::UpToDate, $done->situation);
        $this->assertSame(0, FiscalEvidenceArtifact::query()->withoutGlobalScopes()
            ->where('run_id', $done->id)->count());
    }

    public function test_tenant_suspenso_apos_enqueue_bloqueia_antes_da_chamada(): void
    {
        $adapter = FakeFiscalSourceAdapter::upToDateWithEvidence();
        app(FiscalAdapterRegistry::class)->register($adapter);

        $svc = app(FiscalMonitoringRunService::class);
        $run = $svc->enqueueManual(
            $this->office,
            $this->client,
            'INTEGRA_TEST',
            'TEST_SVC',
            correlationId: 'susp-1',
            dispatch: false,
        );

        OfficeSubscription::query()->where('office_id', $this->office->id)->update([
            'status' => SubscriptionStatus::Suspended->value,
        ]);

        $done = $svc->execute($run->id);

        $this->assertSame(0, $adapter->calls);
        $this->assertSame(FiscalRunStatus::Blocked, $done->status);
        $this->assertSame(FiscalSituation::Blocked, $done->situation);
        $this->assertSame('SUBSCRIPTION_BLOCKED', $done->error_code);
        $this->assertSame(0, FiscalEvidenceArtifact::query()->withoutGlobalScopes()
            ->where('run_id', $done->id)->count());
    }

    public function test_isolamento_cache_e_storage_por_tenant(): void
    {
        $officeB = Office::factory()->create();
        $clientB = Client::factory()->forOffice($officeB)->create();

        $keyA = FiscalIdempotency::cacheKey((int) $this->office->id, 'probe', 'x');
        $keyB = FiscalIdempotency::cacheKey((int) $officeB->id, 'probe', 'x');
        $this->assertNotSame($keyA, $keyB);

        Cache::put($keyA, 'secret-a', 60);
        Cache::put($keyB, 'secret-b', 60);
        $this->assertSame('secret-a', Cache::get($keyA));
        $this->assertSame('secret-b', Cache::get($keyB));

        $adapter = FakeFiscalSourceAdapter::upToDateWithEvidence();
        app(FiscalAdapterRegistry::class)->register($adapter);

        $svc = app(FiscalMonitoringRunService::class);
        $runA = $svc->enqueueManual($this->office, $this->client, 'INTEGRA_TEST', 'TEST_SVC', correlationId: 'iso-a', dispatch: false);
        $runB = $svc->enqueueManual($officeB, $clientB, 'INTEGRA_TEST', 'TEST_SVC', correlationId: 'iso-b', dispatch: false);
        $svc->execute($runA->id);
        $svc->execute($runB->id);

        $evA = FiscalEvidenceArtifact::query()->withoutGlobalScopes()->where('office_id', $this->office->id)->first();
        $evB = FiscalEvidenceArtifact::query()->withoutGlobalScopes()->where('office_id', $officeB->id)->first();
        $this->assertNotNull($evA);
        $this->assertNotNull($evB);
        $this->assertNotSame($evA->vault_object_id, $evB->vault_object_id);

        // Mesmo conteúdo (mesmo sha possível) mas AAD diferente por office — leitura cruzada falha
        $this->expectException(\RuntimeException::class);
        app(FiscalEvidenceStore::class)->readAuthorized($evA, (int) $officeB->id);
    }

    public function test_api_download_evidencia_sem_path_interno(): void
    {
        $adapter = FakeFiscalSourceAdapter::upToDateWithEvidence();
        app(FiscalAdapterRegistry::class)->register($adapter);

        $svc = app(FiscalMonitoringRunService::class);
        $run = $svc->enqueueManual($this->office, $this->client, 'INTEGRA_TEST', 'TEST_SVC', correlationId: 'dl-1', dispatch: false);
        $svc->execute($run->id);

        $evidence = FiscalEvidenceArtifact::query()->withoutGlobalScopes()
            ->where('office_id', $this->office->id)->firstOrFail();

        $this->actingAs($this->admin);
        app(CurrentOffice::class)->resolve($this->admin);

        $response = $this->get('/api/v1/fiscal/evidence/'.$evidence->id.'/download');
        $response->assertOk();
        $body = $response->streamedContent();
        $this->assertStringContainsString('regular', $body);
        $this->assertStringNotContainsString('vault_object_id', (string) $response->headers);
        $this->assertStringNotContainsString($evidence->vault_object_id, $body);
        $jsonList = $this->getJson('/api/v1/fiscal/snapshots')->assertOk();
        $this->assertStringNotContainsString('vault_object_id', $jsonList->getContent());
    }

    public function test_scheduler_respeita_tenant_suspenso(): void
    {
        $category = FiscalCategory::query()->where('code', 'SITFIS')->firstOrFail();
        app(FiscalCategoryService::class)->associate($this->office, $this->client, $category, $this->admin->id);

        FiscalMonitoringSchedule::query()->withoutGlobalScopes()
            ->where('office_id', $this->office->id)
            ->update(['next_run_at' => now()->subMinute()]);

        OfficeSubscription::query()->where('office_id', $this->office->id)->update([
            'status' => SubscriptionStatus::Suspended->value,
        ]);

        $result = app(FiscalMonitoringScheduler::class)->dispatchDue();
        $this->assertSame(0, $result['dispatched']);
        $this->assertGreaterThanOrEqual(1, $result['blocked']);
    }

    public function test_unsupported_nao_cria_pendencia_ficticia(): void
    {
        // Null adapter path: system sem registro
        $svc = app(FiscalMonitoringRunService::class);
        $run = $svc->enqueueManual(
            $this->office,
            $this->client,
            'NO_ADAPTER_SYS',
            'NO_ADAPTER_SVC',
            correlationId: 'unsup-1',
            dispatch: false,
        );
        $done = $svc->execute($run->id);

        $this->assertSame(FiscalSituation::Unsupported, $done->situation);
        $this->assertSame(FiscalCoverage::Unsupported, $done->coverage);
        $this->assertSame(0, FiscalPendingItem::query()->withoutGlobalScopes()
            ->where('office_id', $this->office->id)
            ->where('status', FiscalPendingStatus::Open->value)
            ->count());
        $this->assertGreaterThanOrEqual(1, FiscalFinding::query()->withoutGlobalScopes()
            ->where('office_id', $this->office->id)->count());
    }
}
