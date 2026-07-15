<?php

namespace Tests\Feature\Outbound;

use App\Contracts\SecureObjectStore;
use App\Contracts\SvrsNfceOutboundXmlRetrievalClient;
use App\DTO\Outbound\SvrsNfceRetrievalResult;
use App\Enums\AdnDocumentType;
use App\Enums\CredentialStatus;
use App\Enums\OutboundCaptureMode;
use App\Enums\OutboundFiscalModel;
use App\Enums\OutboundNumberStatus;
use App\Enums\OutboundProfileStatus;
use App\Enums\OutboundRetrievalOrigin;
use App\Enums\OutboundSeriesStatus;
use App\Enums\OutboundUrgencyBand;
use App\Enums\SvrsNfceRecoveryStatus;
use App\Enums\SvrsNfceTransportOutcome;
use App\Jobs\PlanOutboundDeadlineScheduleJob;
use App\Jobs\RecoverSvrsNfceXmlJob;
use App\Models\Client;
use App\Models\ClientCredential;
use App\Models\DfeDocument;
use App\Models\Establishment;
use App\Models\MaOutboundRetrievalRequest;
use App\Models\Office;
use App\Models\OutboundCaptureProfile;
use App\Models\OutboundNumberState;
use App\Models\OutboundSeriesCursor;
use App\Services\Outbound\FakeSvrsNfceOutboundXmlRetrievalClient;
use App\Services\Outbound\OutboundDeadlinePlannerService;
use App\Services\Outbound\OutboundDeadlineSatisfactionService;
use App\Services\Outbound\OutboundXmlRecoveryOrchestrator;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * 8.9 — crash/idempotência entre planejamento, dispatch, reserva, mTLS, vault e commit.
 */
class OutboundDeadlineCrashRecoveryTest extends TestCase
{
    use RefreshDatabase;

    private FakeSvrsNfceOutboundXmlRetrievalClient $fake;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config([
            'outbound_deadline.enabled' => true,
            'outbound_deadline.planner_enabled' => true,
            'outbound_deadline.dispatch_enabled' => true,
            'outbound_deadline.shadow_mode' => false,
            'outbound_deadline.deadline_retry_policy' => true,
            'outbound_deadline.accommodation_hours' => 0,
            'sefaz.svrs_nfce_xml.retrieval_enabled' => true,
            'sefaz.svrs_nfce_xml.auto_queue_enabled' => true,
            'sefaz.svrs_nfce_xml.pilot_allowlist_only' => false,
            'sefaz.svrs_nfce_xml.min_interval_global_seconds' => 0,
            'sefaz.svrs_nfce_xml.min_interval_root_seconds' => 0,
            'sefaz.svrs_nfce_xml.max_inflight_global' => 5,
            'sefaz.svrs_nfce_xml.require_signature' => false,
        ]);
        $this->fake = new FakeSvrsNfceOutboundXmlRetrievalClient;
        $this->app->instance(SvrsNfceOutboundXmlRetrievalClient::class, $this->fake);
    }

    public function test_replanejamento_idempotente_sem_duplicar_slot(): void
    {
        [$profile, $number] = $this->seedPending();
        $req = $this->makeRecovery($profile, $number, [
            'urgency_band' => OutboundUrgencyBand::Planned,
            'svrs_transaction_count' => 0,
            'competence' => '2026-07',
            'access_key' => $number->discovered_access_key,
            'created_at' => now()->subDays(2),
        ]);

        $now = CarbonImmutable::parse('2026-07-15 12:00:00', 'UTC');
        $planner = app(OutboundDeadlinePlannerService::class);
        $a = $planner->plan($profile->office_id, $now);
        $req->refresh();
        $firstNext = $req->next_attempt_at?->toIso8601String();
        $firstSlot = $req->slot_key;

        $b = $planner->plan($profile->office_id, $now);
        $req->refresh();

        $this->assertSame(1, $a['planned']);
        $this->assertSame(1, $b['planned']);
        $this->assertSame($firstNext, $req->next_attempt_at?->toIso8601String());
        $this->assertSame($firstSlot, $req->slot_key);
        $this->assertSame(1, MaOutboundRetrievalRequest::withoutGlobalScopes()
            ->where('access_key', $number->discovered_access_key)
            ->count());
    }

    public function test_shadow_mode_planeja_sem_enfileirar_remoto(): void
    {
        config(['outbound_deadline.shadow_mode' => true, 'outbound_deadline.dispatch_enabled' => true]);
        [$profile, $number] = $this->seedPending();
        $this->makeRecovery($profile, $number, [
            'urgency_band' => OutboundUrgencyBand::Planned,
            'next_attempt_at' => now()->subMinute(),
            'accommodation_until' => now()->subMinute(),
            'svrs_transaction_count' => 0,
            'access_key' => $number->discovered_access_key,
            'competence' => '2026-07',
        ]);

        Queue::fake();
        Artisan::call('outbound:deadline-plan', ['--dispatch' => true, '--office' => $profile->office_id]);

        Queue::assertNotPushed(RecoverSvrsNfceXmlJob::class);
    }

    public function test_dispatch_cancela_quando_vault_ja_tem_full_apos_crash_pre_mtls(): void
    {
        [$profile, $number] = $this->seedPending();
        $key = (string) $number->discovered_access_key;
        $req = $this->makeRecovery($profile, $number, [
            'urgency_band' => OutboundUrgencyBand::Planned,
            'recovery_status' => SvrsNfceRecoveryStatus::Eligible,
            'next_attempt_at' => now()->subMinute(),
            'accommodation_until' => now()->subMinute(),
            'svrs_transaction_count' => 0,
            'access_key' => $key,
            'competence' => '2026-07',
        ]);

        DfeDocument::query()->create([
            'office_id' => $profile->office_id,
            'sha256' => hash('sha256', 'full-xml'),
            'document_type' => AdnDocumentType::Nfe,
            'access_key' => $key,
            'vault_object_id' => '01TESTTESTTESTTESTTESTTESTTEST01',
            'byte_size' => 20,
            'parse_status' => 'OK',
        ]);

        Queue::fake();
        Artisan::call('outbound:deadline-plan', ['--dispatch' => true, '--office' => $profile->office_id]);

        $req->refresh();
        $this->assertSame(SvrsNfceRecoveryStatus::ResolvedByOtherSource, $req->recovery_status);
        $this->assertSame(OutboundUrgencyBand::Captured, $req->urgency_band);
        Queue::assertNotPushed(RecoverSvrsNfceXmlJob::class);
    }

    public function test_ingestao_concorrente_cancela_job_pendente_antes_do_commit(): void
    {
        [$profile, $number] = $this->seedPending();
        $key = (string) $number->discovered_access_key;
        $req = $this->makeRecovery($profile, $number, [
            'urgency_band' => OutboundUrgencyBand::Attention,
            'recovery_status' => SvrsNfceRecoveryStatus::Queued,
            'next_attempt_at' => now()->addHour(),
            'svrs_transaction_count' => 0,
            'access_key' => $key,
            'competence' => '2026-07',
        ]);

        app(OutboundDeadlineSatisfactionService::class)->markCapturedBySource(
            $profile->office_id,
            $key,
            'AUTXML',
            hash('sha256', 'autxml'),
        );

        $req->refresh();
        $this->assertSame(SvrsNfceRecoveryStatus::ResolvedByOtherSource, $req->recovery_status);
        $this->assertNull($req->next_attempt_at);

        // Job tardio (crash de worker que reprocessa id) não reabre captura
        app(OutboundXmlRecoveryOrchestrator::class)->runAttempt($req->id);
        $req->refresh();
        $this->assertSame(SvrsNfceRecoveryStatus::ResolvedByOtherSource, $req->recovery_status);
        $this->assertSame(0, count($this->fake->calls));
    }

    public function test_falha_sem_a1_fecha_sem_marcar_capturado_nem_consumir_tx(): void
    {
        [$profile, $number] = $this->seedPending();
        // Credential ACTIVE existe para eligibility, mas vault_object_id inválido → materialize falha
        ClientCredential::query()->create([
            'office_id' => $profile->office_id,
            'client_id' => $profile->client_id,
            'status' => CredentialStatus::Active,
            'subject_name' => 'Fixture',
            'holder_cnpj' => '12345678000190',
            'fingerprint_sha256' => str_repeat('b', 64),
            'valid_from' => now()->subDay(),
            'valid_to' => now()->addYear(),
            'vault_object_id' => '01MISSINGMISSINGMISSINGMISSIN',
            'activated_at' => now(),
        ]);

        $req = $this->makeRecovery($profile, $number, [
            'urgency_band' => OutboundUrgencyBand::Planned,
            'recovery_status' => SvrsNfceRecoveryStatus::Queued,
            'svrs_transaction_count' => 0,
            'access_key' => $number->discovered_access_key,
            'competence' => '2026-07',
        ]);

        app(OutboundXmlRecoveryOrchestrator::class)->runAttempt($req->id);

        $req->refresh();
        $this->assertNotSame(SvrsNfceRecoveryStatus::Captured, $req->recovery_status);
        $this->assertNotSame(SvrsNfceRecoveryStatus::ResolvedByOtherSource, $req->recovery_status);
        // Sem mTLS remoto: fake client não é chamado (PFX não materializado com sucesso)
        $this->assertSame(0, count($this->fake->calls));
        $number->refresh();
        $this->assertSame(OutboundNumberStatus::XmlPending, $number->status);
    }

    public function test_lock_impede_duplo_run_simultaneo(): void
    {
        [$profile, $number] = $this->seedPending();
        $this->seedVaultPfx($profile);
        $req = $this->makeRecovery($profile, $number, [
            'urgency_band' => OutboundUrgencyBand::Planned,
            'recovery_status' => SvrsNfceRecoveryStatus::Queued,
            'svrs_transaction_count' => 0,
            'access_key' => $number->discovered_access_key,
            'competence' => '2026-07',
        ]);

        $lock = Cache::lock('svrs_nfce.recovery.'.$req->id, 60);
        $this->assertTrue($lock->get());

        app(OutboundXmlRecoveryOrchestrator::class)->runAttempt($req->id);

        $req->refresh();
        $this->assertSame(SvrsNfceRecoveryStatus::Queued, $req->recovery_status);
        $this->assertSame(0, count($this->fake->calls));

        $lock->release();
    }

    public function test_planner_job_nao_materializa_certificado(): void
    {
        [$profile, $number] = $this->seedPending();
        $this->makeRecovery($profile, $number, [
            'urgency_band' => OutboundUrgencyBand::Planned,
            'access_key' => $number->discovered_access_key,
            'competence' => '2026-07',
            'created_at' => now()->subDays(2),
        ]);

        $store = $this->createMock(SecureObjectStore::class);
        $store->expects($this->never())->method('get');
        $this->app->instance(SecureObjectStore::class, $store);

        PlanOutboundDeadlineScheduleJob::dispatchSync($profile->office_id);

        $this->assertSame(0, count($this->fake->calls));
    }

    public function test_crash_apos_reserva_falha_transport_agenda_sem_capturar(): void
    {
        [$profile, $number] = $this->seedPending();
        $this->seedVaultPfx($profile);
        $req = $this->makeRecovery($profile, $number, [
            'urgency_band' => OutboundUrgencyBand::Planned,
            'recovery_status' => SvrsNfceRecoveryStatus::Queued,
            'svrs_transaction_count' => 0,
            'access_key' => $number->discovered_access_key,
            'competence' => '2026-07',
        ]);

        $this->fake->enqueue(new SvrsNfceRetrievalResult(
            outcome: SvrsNfceTransportOutcome::HttpTransient,
            httpStatus: 503,
            sanitizedDetail: 'timeout',
        ));

        app(OutboundXmlRecoveryOrchestrator::class)->runAttempt($req->id);

        $req->refresh();
        $this->assertNotSame(SvrsNfceRecoveryStatus::Captured, $req->recovery_status);
        $this->assertGreaterThanOrEqual(1, (int) $req->svrs_transaction_count);
        $number->refresh();
        $this->assertSame(OutboundNumberStatus::XmlPending, $number->status);
    }

    /**
     * @return array{0: OutboundCaptureProfile, 1: OutboundNumberState}
     */
    private function seedPending(): array
    {
        $office = Office::factory()->create();
        $client = Client::factory()->forOffice($office)->create();
        $est = Establishment::factory()->forClient($client)->create([
            'cnpj' => '12345678000190',
            'address_state' => 'MA',
        ]);
        $profile = OutboundCaptureProfile::query()->create([
            'office_id' => $office->id,
            'client_id' => $client->id,
            'establishment_id' => $est->id,
            'uf' => 'MA',
            'environment' => 'homologation',
            'model' => OutboundFiscalModel::Nfce,
            'mode' => OutboundCaptureMode::Assisted,
            'status' => OutboundProfileStatus::Active,
            'allowlisted' => true,
        ]);
        $series = OutboundSeriesCursor::query()->create([
            'office_id' => $office->id,
            'outbound_capture_profile_id' => $profile->id,
            'establishment_id' => $est->id,
            'environment' => 'homologation',
            'model' => OutboundFiscalModel::Nfce,
            'series' => 1,
            'seed_nnf' => 1,
            'discovery_position' => 2,
            'status' => OutboundSeriesStatus::Idle,
        ]);
        $number = OutboundNumberState::query()->create([
            'office_id' => $office->id,
            'outbound_capture_profile_id' => $profile->id,
            'outbound_series_cursor_id' => $series->id,
            'series' => 1,
            'nnf' => 1,
            'status' => OutboundNumberStatus::XmlPending,
            'discovered_access_key' => '21260712345678000190650010000000011234567892',
        ]);

        return [$profile, $number];
    }

    private function makeRecovery(OutboundCaptureProfile $profile, OutboundNumberState $number, array $extra = []): MaOutboundRetrievalRequest
    {
        return MaOutboundRetrievalRequest::query()->create(array_merge([
            'office_id' => $profile->office_id,
            'outbound_capture_profile_id' => $profile->id,
            'establishment_id' => $profile->establishment_id,
            'environment' => 'homologation',
            'model' => OutboundFiscalModel::Nfce,
            'direction' => 'OUT',
            'competence' => '2026-07',
            'status' => 'PENDING',
            'mode' => OutboundCaptureMode::Automatic,
            'origin' => OutboundRetrievalOrigin::SvrsPortalByKey,
            'access_key' => $number->discovered_access_key,
            'outbound_number_state_id' => $number->id,
            'recovery_status' => SvrsNfceRecoveryStatus::Eligible,
            'urgency_band' => OutboundUrgencyBand::Planned,
            'correlation_id' => 'crash-'.uniqid(),
        ], $extra));
    }

    private function seedVaultPfx(OutboundCaptureProfile $profile): void
    {
        $fp = str_repeat('c', 64);
        $store = app(SecureObjectStore::class);
        $payload = json_encode([
            'pfx' => base64_encode('fake-pfx-bytes'),
            'password' => 'test-only',
        ], JSON_THROW_ON_ERROR);
        $objectId = $store->put($payload, [
            'office_id' => $profile->office_id,
            'client_id' => $profile->client_id,
            'fingerprint' => $fp,
        ]);
        ClientCredential::query()->updateOrCreate(
            ['client_id' => $profile->client_id, 'office_id' => $profile->office_id],
            [
                'status' => CredentialStatus::Active,
                'subject_name' => 'Fixture',
                'holder_cnpj' => '12345678000190',
                'fingerprint_sha256' => $fp,
                'valid_from' => now()->subDay(),
                'valid_to' => now()->addYear(),
                'vault_object_id' => $objectId,
                'activated_at' => now(),
            ]
        );
    }
}
