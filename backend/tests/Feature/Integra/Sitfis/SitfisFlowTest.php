<?php

namespace Tests\Feature\Integra\Sitfis;

use App\Contracts\IntegraContadorClient;
use App\Enums\FiscalRunStatus;
use App\Enums\FiscalSituation;
use App\Enums\OfficeRole;
use App\Enums\SerproAuthorizationStatus;
use App\Enums\SerproContractStatus;
use App\Enums\SerproEnvironment;
use App\Enums\TaxProxyPowerSource;
use App\Enums\TaxProxyPowerStatus;
use App\Models\Client;
use App\Models\Establishment;
use App\Models\FiscalEvidenceArtifact;
use App\Models\FiscalFinding;
use App\Models\FiscalMonitoringRun;
use App\Models\FiscalSnapshot;
use App\Models\Office;
use App\Models\OfficeSerproAuthorization;
use App\Models\SerproApiUsageReservation;
use App\Models\SerproContract;
use App\Models\TaxProxyPower;
use App\Models\User;
use App\Services\FiscalMonitoring\FiscalAdapterRegistry;
use App\Services\FiscalMonitoring\FiscalEvidenceStore;
use App\Services\FiscalMonitoring\FiscalMonitoringRunService;
use App\Services\Integra\Sitfis\SitfisFlowService;
use App\Services\Integra\Sitfis\SitfisSnapshotService;
use App\Services\Integra\Sitfis\SitfisSourceAdapter;
use App\Support\CurrentOffice;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Support\ProgrammableIntegraContadorClient;
use Tests\TestCase;

/**
 * Cobertura SITFIS (tasks 10.1–10.4 / 10.9 parte SITFIS):
 * processando, layout novo, cache/TTL, ausência ≠ certidão.
 */
class SitfisFlowTest extends TestCase
{
    use RefreshDatabase;

    private Office $office;

    private Client $client;

    private User $admin;

    private ProgrammableIntegraContadorClient $integra;

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
            'fiscal_monitoring.sitfis.min_wait_seconds' => 30,
            'fiscal_monitoring.sitfis.poll_interval_seconds' => 60,
            'fiscal_monitoring.sitfis.max_polls' => 5,
            'fiscal_monitoring.sitfis.snapshot_ttl_seconds' => 3600,
            'serpro.default_environment' => 'TRIAL',
        ]);

        $this->office = Office::factory()->create();
        $this->client = Client::factory()->forOffice($this->office)->create();
        Establishment::factory()->create([
            'office_id' => $this->office->id,
            'client_id' => $this->client->id,
            'cnpj' => '11222333000181',
            'is_matrix' => true,
            'is_active' => true,
        ]);
        $this->admin = User::factory()->forOffice($this->office, OfficeRole::Admin)->withTwoFactorConfirmed()->create();

        SerproContract::query()->create([
            'environment' => SerproEnvironment::Trial,
            'status' => SerproContractStatus::Active,
            'contractor_cnpj' => '11222333000181',
            'contractor_name' => 'Software House Trial',
            'health_status' => 'OK',
            'activated_at' => now(),
        ]);

        $auth = OfficeSerproAuthorization::query()->create([
            'office_id' => $this->office->id,
            'environment' => SerproEnvironment::Trial,
            'status' => SerproAuthorizationStatus::TokenActive,
            'author_identity_type' => 'CNPJ',
            'author_identity' => '11222333000181',
            'certificate_mode' => 'EXTERNAL_SIGNATURE',
            'termo_vault_object_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            'termo_valid_to' => now()->addYear(),
            'procurador_token_vault_object_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            'procurador_token_expires_at' => now()->addHours(6),
        ]);

        // Poder SITFIS exigido pela elegibilidade (catálogo required_proxy_power).
        TaxProxyPower::query()->create([
            'office_id' => $this->office->id,
            'client_id' => $this->client->id,
            'office_serpro_authorization_id' => $auth->id,
            'author_identity' => '11222333000181',
            'contributor_cnpj' => '11222333000181',
            'system_code' => 'INTEGRA_SITFIS',
            'service_code' => 'SITFIS',
            'power_code' => '00002',
            'source' => TaxProxyPowerSource::ManualOfficialEvidence,
            'status' => TaxProxyPowerStatus::Active,
            'valid_from' => now()->subDay(),
            'valid_to' => now()->addYear(),
        ]);

        $this->integra = new ProgrammableIntegraContadorClient;
        $this->app->instance(IntegraContadorClient::class, $this->integra);

        // Rebind flow/adapter to pick up programmable client
        $this->app->forgetInstance(SitfisFlowService::class);
        $this->app->forgetInstance(SitfisSourceAdapter::class);

        // Re-register adapter with fresh dependencies
        $registry = $this->app->make(FiscalAdapterRegistry::class);
        // Clear and re-register only Sitfis (others may exist)
        $ref = new \ReflectionClass($registry);
        $prop = $ref->getProperty('adapters');
        $prop->setAccessible(true);
        $existing = $prop->getValue($registry);
        $filtered = array_values(array_filter(
            $existing,
            fn ($a) => ! $a instanceof SitfisSourceAdapter,
        ));
        $prop->setValue($registry, $filtered);
        $registry->register($this->app->make(SitfisSourceAdapter::class));
    }

    public function test_fluxo_processando_nao_faz_polling_antes_do_prazo_minimo(): void
    {
        Queue::fake();

        $this->integra->queueSolicit('PROT-WAIT-1');

        $svc = app(FiscalMonitoringRunService::class);
        $run = $svc->enqueueManual(
            $this->office,
            $this->client,
            'INTEGRA_SITFIS',
            'SITFIS',
            'MONITOR',
            correlationId: 'sitfis-wait-1',
            dispatch: false,
        );

        $done = $svc->execute($run->id);

        $this->assertSame(FiscalRunStatus::Requeued, $done->status);
        $this->assertSame(FiscalSituation::Processing, $done->situation);
        $this->assertSame('PROT-WAIT-1', $done->progress['protocol'] ?? null);
        $this->assertSame('WAITING_MIN_PERIOD', $done->progress['phase'] ?? null);
        $this->assertSame(['sitfis.solicitar_protocolo'], $this->integra->operations());

        // Simulação não cria reserva, franquia ou custo no ledger.
        $this->assertSame(
            0,
            SerproApiUsageReservation::query()
                ->withoutGlobalScopes()
                ->where('office_id', $this->office->id)
                ->where('service_code', 'SITFIS')
                ->count(),
        );

        // Continuação antes do not_before: sem nova chamada
        $child = FiscalMonitoringRun::query()
            ->withoutGlobalScopes()
            ->where('parent_run_id', $done->id)
            ->firstOrFail();

        // Progresso copiado; not_before no futuro
        $this->assertNotNull($child->progress['not_before'] ?? null);

        $done2 = $svc->execute($child->id);
        $this->assertSame(FiscalRunStatus::Requeued, $done2->status);
        $this->assertSame(FiscalSituation::Processing, $done2->situation);
        // Ainda só a solicitação — sem EMITIR durante a espera
        $this->assertSame(['sitfis.solicitar_protocolo'], $this->integra->operations());
        $this->assertSame(0, FiscalEvidenceArtifact::query()->withoutGlobalScopes()
            ->where('office_id', $this->office->id)->count());
    }

    public function test_resultado_disponivel_vincula_evidencia_ao_protocolo(): void
    {
        Queue::fake();

        $this->integra
            ->queueSolicit('PROT-OK-1')
            ->queueReport([
                'layoutVersion' => '1.0',
                'dataConsulta' => '2026-07-15',
                'pendencias' => [
                    ['codigo' => 'DEBITO_1', 'descricao' => 'Débito RFB', 'detalhe' => 'R$ 10'],
                ],
            ]);

        $svc = app(FiscalMonitoringRunService::class);
        $run = $svc->enqueueManual(
            $this->office,
            $this->client,
            'INTEGRA_SITFIS',
            'SITFIS',
            'MONITOR',
            correlationId: 'sitfis-ok-1',
            dispatch: false,
        );

        $afterSolicit = $svc->execute($run->id);
        $this->assertSame(FiscalRunStatus::Requeued, $afterSolicit->status);

        $child = FiscalMonitoringRun::query()
            ->withoutGlobalScopes()
            ->where('parent_run_id', $afterSolicit->id)
            ->firstOrFail();
        $this->assertSame(FiscalRunStatus::Queued, $child->status);

        // Garante not_before no passado no progress
        $progress = $child->progress ?? [];
        $progress['not_before'] = CarbonImmutable::now()->subMinute()->toIso8601String();
        $progress['phase'] = 'WAITING_MIN_PERIOD';
        $child->forceFill(['progress' => $progress])->save();

        $done = $svc->execute($child->id);

        $this->assertSame(FiscalRunStatus::Completed, $done->status);
        $this->assertSame(FiscalSituation::Pending, $done->situation);
        $this->assertContains('sitfis.emitir_relatorio', $this->integra->operations());

        $evidence = FiscalEvidenceArtifact::query()->withoutGlobalScopes()
            ->where('run_id', $done->id)->first();
        $this->assertNotNull($evidence);
        $this->assertStringContainsString('DEBITO_1', app(FiscalEvidenceStore::class)
            ->readAuthorized($evidence, (int) $this->office->id));

        $snapshot = FiscalSnapshot::query()->withoutGlobalScopes()
            ->where('run_id', $done->id)->first();
        $this->assertNotNull($snapshot);
        $this->assertSame($evidence->id, $snapshot->evidence_artifact_id);
        $this->assertSame('PROT-OK-1', $snapshot->normalized['protocol'] ?? null);

        $finding = FiscalFinding::query()->withoutGlobalScopes()
            ->where('snapshot_id', $snapshot->id)
            ->where('code', 'DEBITO_1')
            ->first();
        $this->assertNotNull($finding);
        $this->assertSame($snapshot->id, $finding->snapshot_id);
        $this->assertSame($done->id, $finding->run_id);
    }

    public function test_layout_novo_preserva_artefato_e_marca_attention(): void
    {
        Queue::fake();

        // Predecessor VERIFIED corrente — layout desconhecido não deve demovê-lo.
        $seedRun = FiscalMonitoringRun::query()->create([
            'office_id' => $this->office->id,
            'client_id' => $this->client->id,
            'system_code' => 'INTEGRA_SITFIS',
            'service_code' => 'SITFIS',
            'operation_code' => 'MONITOR',
            'trigger' => 'MANUAL',
            'idempotency_key' => 'seed-sitfis-layout-predecessor',
            'status' => FiscalRunStatus::Completed,
            'result' => 'SUCCESS',
            'situation' => FiscalSituation::Unknown,
            'coverage' => 'FULL',
            'mutability' => 'READ_ONLY',
            'correlation_id' => 'seed-layout-pred',
            'source_provenance' => 'SERPRO_REAL',
            'verification_state' => 'VERIFIED',
            'finished_at' => now()->subHour(),
        ]);
        $predecessor = FiscalSnapshot::query()->create([
            'office_id' => $this->office->id,
            'run_id' => $seedRun->id,
            'client_id' => $this->client->id,
            'system_code' => 'INTEGRA_SITFIS',
            'service_code' => 'SITFIS',
            'operation_code' => 'MONITOR',
            'source_provenance' => 'SERPRO_REAL',
            'verification_state' => 'VERIFIED',
            'situation' => FiscalSituation::Unknown,
            'coverage' => 'FULL',
            'version' => 1,
            'is_current' => true,
            'normalized' => [
                'is_negative_certificate' => false,
                'disclaimer' => 'Não é certidão negativa.',
                'protocol' => 'PROT-PRED',
            ],
            'observed_at' => now()->subHour(),
            'created_at' => now()->subHour(),
        ]);

        $this->integra
            ->queueSolicit('PROT-LAYOUT-1')
            ->queueReport([
                'layoutVersion' => '9.9',
                '__unknown_layout' => true,
                'secaoNovaOficial' => ['x' => 1],
            ]);

        $svc = app(FiscalMonitoringRunService::class);
        $run = $svc->enqueueManual(
            $this->office,
            $this->client,
            'INTEGRA_SITFIS',
            'SITFIS',
            'MONITOR',
            correlationId: 'sitfis-layout-1',
            dispatch: false,
        );

        $afterSolicit = $svc->execute($run->id);
        $child = FiscalMonitoringRun::query()
            ->withoutGlobalScopes()
            ->where('parent_run_id', $afterSolicit->id)
            ->firstOrFail();
        $this->assertSame(FiscalRunStatus::Queued, $child->status);
        $this->assertSame(
            'UNVERIFIED',
            $child->fresh()->verification_state?->value ?? $child->fresh()->verification_state,
            'Enqueue/execute não deve rotular VERIFIED antes do parse'
        );

        $progress = $child->progress ?? [];
        $progress['not_before'] = CarbonImmutable::now()->subMinute()->toIso8601String();
        $child->forceFill(['progress' => $progress])->save();

        $done = $svc->execute($child->id);

        $this->assertSame(FiscalRunStatus::Completed, $done->status);
        $this->assertSame(FiscalSituation::Attention, $done->situation);
        $this->assertSame(
            'PARSE_ALERT',
            $done->verification_state?->value ?? $done->verification_state
        );

        $evidence = FiscalEvidenceArtifact::query()->withoutGlobalScopes()
            ->where('run_id', $done->id)->first();
        $this->assertNotNull($evidence, 'Artefato deve ser preservado mesmo com layout desconhecido');

        $snapshot = FiscalSnapshot::query()->withoutGlobalScopes()
            ->where('run_id', $done->id)->firstOrFail();
        $this->assertTrue($snapshot->normalized['contract_changed'] ?? false);
        $this->assertFalse($snapshot->normalized['is_negative_certificate'] ?? true);
        $this->assertFalse(
            (bool) $snapshot->is_current,
            'Snapshot com PARSE_ALERT não vira corrente'
        );
        $this->assertTrue(
            (bool) $predecessor->fresh()->is_current,
            'Predecessor VERIFIED deve permanecer is_current após PARSE_ALERT'
        );

        $this->assertNotNull(
            FiscalFinding::query()->withoutGlobalScopes()
                ->where('snapshot_id', $snapshot->id)
                ->where('code', 'SITFIS_LAYOUT_UNKNOWN')
                ->first()
        );
    }

    public function test_cache_ttl_nao_cria_nova_chamada_ao_abrir_situacao(): void
    {
        // Snapshot fresco pré-existente
        $run = FiscalMonitoringRun::query()->create([
            'office_id' => $this->office->id,
            'client_id' => $this->client->id,
            'system_code' => 'INTEGRA_SITFIS',
            'service_code' => 'SITFIS',
            'operation_code' => 'MONITOR',
            'trigger' => 'MANUAL',
            'idempotency_key' => 'seed-sitfis-cache-1',
            'status' => FiscalRunStatus::Completed,
            'result' => 'SUCCESS',
            'situation' => FiscalSituation::Unknown,
            'coverage' => 'FULL',
            'mutability' => 'READ_ONLY',
            'correlation_id' => 'seed-cache',
            'finished_at' => now(),
        ]);

        $evidence = FiscalEvidenceArtifact::query()->create([
            'office_id' => $this->office->id,
            'run_id' => $run->id,
            'vault_object_id' => 'opaque-seed-1',
            'content_sha256' => hash('sha256', '{"pendencias":[]}'),
            'content_type' => 'application/json',
            'byte_size' => 16,
            'source' => 'test',
            'source_version' => '1.0',
            'observed_at' => now(),
            'retention_until' => now()->addYear(),
            'is_immutable' => true,
            'created_at' => now(),
        ]);

        FiscalSnapshot::query()->create([
            'office_id' => $this->office->id,
            'run_id' => $run->id,
            'client_id' => $this->client->id,
            'evidence_artifact_id' => $evidence->id,
            'system_code' => 'INTEGRA_SITFIS',
            'service_code' => 'SITFIS',
            'operation_code' => 'MONITOR',
            'source_provenance' => 'SERPRO_REAL',
            'verification_state' => 'VERIFIED',
            'situation' => FiscalSituation::Unknown,
            'coverage' => 'FULL',
            'version' => 1,
            'is_current' => true,
            'normalized' => [
                'is_negative_certificate' => false,
                'disclaimer' => 'Não é certidão negativa.',
                'protocol' => 'PROT-CACHED',
            ],
            'observed_at' => now()->subMinutes(10),
            'created_at' => now(),
        ]);

        $this->actingAs($this->admin);
        app(CurrentOffice::class)->resolve($this->admin);

        $response = $this->getJson('/api/v1/fiscal/sitfis?client_id='.$this->client->id)
            ->assertOk()
            ->assertJsonPath('data.is_within_ttl', true)
            ->assertJsonPath('data.is_negative_certificate', false);

        $this->assertNotNull($response->json('data.age_seconds'));
        $this->assertGreaterThanOrEqual(600, (int) $response->json('data.age_seconds'));
        $this->assertNotNull($response->json('data.expires_at'));
        $this->assertSame(0, $this->integra->callCount());

        // Refresh reutiliza snapshot dentro do TTL
        $refresh = $this->postJson('/api/v1/fiscal/sitfis/refresh', [
            'client_id' => $this->client->id,
        ])->assertOk();

        $this->assertFalse($refresh->json('data.enqueued'));
        $this->assertTrue($refresh->json('data.reused_snapshot'));
        $this->assertSame('WITHIN_TTL', $refresh->json('data.reason'));
        $this->assertSame(0, $this->integra->callCount());
    }

    public function test_sem_pendencia_api_nao_afirma_certidao(): void
    {
        Queue::fake();

        $this->integra
            ->queueSolicit('PROT-EMPTY-1')
            ->queueReport([
                'layoutVersion' => '1.0',
                'dataConsulta' => '2026-07-15',
                'pendencias' => [],
            ]);

        $svc = app(FiscalMonitoringRunService::class);
        $run = $svc->enqueueManual(
            $this->office,
            $this->client,
            'INTEGRA_SITFIS',
            'SITFIS',
            'MONITOR',
            correlationId: 'sitfis-empty-1',
            dispatch: false,
        );
        $afterSolicit = $svc->execute($run->id);
        $child = FiscalMonitoringRun::query()
            ->withoutGlobalScopes()
            ->where('parent_run_id', $afterSolicit->id)
            ->firstOrFail();
        $this->assertSame(FiscalRunStatus::Queued, $child->status);
        $progress = $child->progress ?? [];
        $progress['not_before'] = CarbonImmutable::now()->subMinute()->toIso8601String();
        $child->forceFill(['progress' => $progress])->save();

        $done = $svc->execute($child->id);
        $this->assertSame(FiscalSituation::Unknown, $done->situation);
        $this->assertNotSame(FiscalSituation::UpToDate, $done->situation);

        $snap = FiscalSnapshot::query()->withoutGlobalScopes()->where('run_id', $done->id)->firstOrFail();
        $this->assertFalse($snap->normalized['is_negative_certificate'] ?? true);

        $this->actingAs($this->admin);
        app(CurrentOffice::class)->resolve($this->admin);

        $this->getJson('/api/v1/fiscal/sitfis?client_id='.$this->client->id)
            ->assertOk()
            ->assertJsonPath('data.is_negative_certificate', false)
            ->assertJsonPath('data.snapshot.situation', FiscalSituation::Unknown->value);
    }

    public function test_emit_ainda_processando_requeue_respeitoso(): void
    {
        Queue::fake();

        $this->integra
            ->queueSolicit('PROT-POLL-1')
            ->queueProcessing();

        $svc = app(FiscalMonitoringRunService::class);
        $run = $svc->enqueueManual(
            $this->office,
            $this->client,
            'INTEGRA_SITFIS',
            'SITFIS',
            'MONITOR',
            correlationId: 'sitfis-poll-1',
            dispatch: false,
        );

        $afterSolicit = $svc->execute($run->id);
        $child = FiscalMonitoringRun::query()
            ->withoutGlobalScopes()
            ->where('parent_run_id', $afterSolicit->id)
            ->firstOrFail();
        $this->assertSame(FiscalRunStatus::Queued, $child->status);
        $progress = $child->progress ?? [];
        $progress['not_before'] = CarbonImmutable::now()->subMinute()->toIso8601String();
        $child->forceFill(['progress' => $progress])->save();

        $polling = $svc->execute($child->id);
        $this->assertSame(FiscalRunStatus::Requeued, $polling->status);
        $this->assertSame(FiscalSituation::Processing, $polling->situation);
        $this->assertSame('POLLING_EMIT', $polling->progress['phase'] ?? null);
        $this->assertSame(1, (int) ($polling->progress['poll_count'] ?? 0));
        $this->assertGreaterThanOrEqual(60, (int) ($polling->progress['requeue_after_seconds'] ?? 0));
        $this->assertSame(['sitfis.solicitar_protocolo', 'sitfis.emitir_relatorio'], $this->integra->operations());
    }

    public function test_snapshot_service_refresh_forca_quando_ttl_expirado(): void
    {
        $run = FiscalMonitoringRun::query()->create([
            'office_id' => $this->office->id,
            'client_id' => $this->client->id,
            'system_code' => 'INTEGRA_SITFIS',
            'service_code' => 'SITFIS',
            'operation_code' => 'MONITOR',
            'trigger' => 'MANUAL',
            'idempotency_key' => 'seed-sitfis-stale-1',
            'status' => FiscalRunStatus::Completed,
            'result' => 'SUCCESS',
            'situation' => FiscalSituation::Unknown,
            'coverage' => 'FULL',
            'mutability' => 'READ_ONLY',
            'correlation_id' => 'seed-stale',
            'finished_at' => now(),
        ]);

        $evidence = FiscalEvidenceArtifact::query()->create([
            'office_id' => $this->office->id,
            'run_id' => $run->id,
            'vault_object_id' => 'opaque-stale-1',
            'content_sha256' => hash('sha256', 'stale'),
            'content_type' => 'application/json',
            'byte_size' => 5,
            'source' => 'test',
            'observed_at' => now()->subDays(2),
            'retention_until' => now()->addYear(),
            'is_immutable' => true,
            'created_at' => now(),
        ]);

        FiscalSnapshot::query()->create([
            'office_id' => $this->office->id,
            'run_id' => $run->id,
            'client_id' => $this->client->id,
            'evidence_artifact_id' => $evidence->id,
            'system_code' => 'INTEGRA_SITFIS',
            'service_code' => 'SITFIS',
            'operation_code' => 'MONITOR',
            'situation' => FiscalSituation::Unknown,
            'coverage' => 'FULL',
            'version' => 1,
            'is_current' => true,
            'normalized' => ['is_negative_certificate' => false],
            'observed_at' => now()->subDays(2),
            'created_at' => now(),
        ]);

        $result = app(SitfisSnapshotService::class)->refresh(
            $this->office,
            $this->client,
            force: false,
            actorId: $this->admin->id,
            dispatch: false,
        );

        $this->assertTrue($result['enqueued']);
        $this->assertSame('TTL_EXPIRED_OR_MISSING', $result['reason']);
        $this->assertNotNull($result['run']);
        $this->assertFalse($result['view']['is_within_ttl']);
    }
}
