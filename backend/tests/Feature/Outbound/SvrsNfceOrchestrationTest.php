<?php

namespace Tests\Feature\Outbound;

use App\Contracts\SecureObjectStore;
use App\Contracts\SvrsNfceOutboundXmlRetrievalClient;
use App\DTO\Outbound\SvrsNfceRetrievalResult;
use App\Enums\CredentialStatus;
use App\Enums\OutboundCaptureMode;
use App\Enums\OutboundFiscalModel;
use App\Enums\OutboundNumberStatus;
use App\Enums\OutboundProfileStatus;
use App\Enums\OutboundRetrievalOrigin;
use App\Enums\OutboundSeriesStatus;
use App\Enums\SvrsNfceRecoveryStatus;
use App\Enums\SvrsNfceTransportOutcome;
use App\Jobs\RecoverSvrsNfceXmlJob;
use App\Models\Client;
use App\Models\ClientCredential;
use App\Models\Establishment;
use App\Models\MaOutboundRetrievalRequest;
use App\Models\Office;
use App\Models\OutboundCaptureProfile;
use App\Models\OutboundNumberState;
use App\Models\OutboundSeriesCursor;
use App\Models\User;
use App\Services\Outbound\FakeSvrsNfceOutboundXmlRetrievalClient;
use App\Services\Outbound\OutboundXmlRecoveryOrchestrator;
use App\Services\Outbound\SvrsNfceKillSwitchService;
use App\Support\CurrentOffice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * 8.12 — concorrência, retry, duplicata e resolução por fallback (sem rede SVRS).
 */
class SvrsNfceOrchestrationTest extends TestCase
{
    use RefreshDatabase;

    private FakeSvrsNfceOutboundXmlRetrievalClient $fake;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config([
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

    public function test_dois_disparos_idempotentes_uma_recovery_ativa(): void
    {
        Queue::fake();
        [$profile, $number] = $this->seedEligible();

        // Sem A1 real: eligibility com a1Available=true via force — materializeA1 retorna null no run
        // ensureRecovery chama hasA1 que verifica active credential — criar sem A1 ainda bloqueia eligibility
        // Forçamos avaliando com profile allowlisted + mock: sobrescrevemos hasA1 path
        // Criar recovery diretamente e chamar ensureRecovery duas vezes com A1 mockado via ClientCredential skip
        // Estratégia: criar recovery via ensureRecovery após stub de CredentialService não — usar API do orchestrator
        // com número/perfil e injetar credential check via creating active credential sem vault (hasA1 only checks activeFor)

        // hasA1 only checks activeFor existence, not materialize — create credential row without vault use
        $this->seedActiveCredentialStub($profile);

        $orch = app(OutboundXmlRecoveryOrchestrator::class);
        $a = $orch->ensureRecovery($number, $profile, queue: true, triggeredBy: 'scheduler');
        $b = $orch->ensureRecovery($number->fresh(), $profile->fresh(), queue: true, triggeredBy: 'operator');

        $this->assertNotNull($a);
        $this->assertNotNull($b);
        $this->assertSame($a->id, $b->id);

        $count = MaOutboundRetrievalRequest::withoutGlobalScopes()
            ->where('origin', OutboundRetrievalOrigin::SvrsPortalByKey)
            ->where('access_key', $number->discovered_access_key)
            ->count();
        $this->assertSame(1, $count);

        Queue::assertPushed(RecoverSvrsNfceXmlJob::class);
    }

    public function test_resolve_by_other_source_encerra_recovery_pendente(): void
    {
        [$profile, $number] = $this->seedEligible();
        $this->seedActiveCredentialStub($profile);

        $req = MaOutboundRetrievalRequest::query()->create([
            'office_id' => $profile->office_id,
            'outbound_capture_profile_id' => $profile->id,
            'establishment_id' => $profile->establishment_id,
            'environment' => 'homologation',
            'model' => OutboundFiscalModel::Nfce,
            'direction' => 'OUT',
            'competence' => '2026-07',
            'origin' => OutboundRetrievalOrigin::SvrsPortalByKey,
            'access_key' => $number->discovered_access_key,
            'outbound_number_state_id' => $number->id,
            'recovery_status' => SvrsNfceRecoveryStatus::RetryScheduled,
            'attempt_count' => 2,
            'correlation_id' => 'corr-fallback',
        ]);

        app(OutboundXmlRecoveryOrchestrator::class)
            ->resolveByOtherSource($profile->office_id, (string) $number->discovered_access_key, 'MANUAL_ZIP');

        $req->refresh();
        $this->assertSame(SvrsNfceRecoveryStatus::ResolvedByOtherSource, $req->recovery_status);
    }

    public function test_kill_switch_preserva_backlog_e_nnf(): void
    {
        [$profile, $number] = $this->seedEligible();
        $this->seedActiveCredentialStub($profile);
        $req = MaOutboundRetrievalRequest::query()->create([
            'office_id' => $profile->office_id,
            'outbound_capture_profile_id' => $profile->id,
            'establishment_id' => $profile->establishment_id,
            'environment' => 'homologation',
            'model' => OutboundFiscalModel::Nfce,
            'direction' => 'OUT',
            'competence' => '2026-07',
            'origin' => OutboundRetrievalOrigin::SvrsPortalByKey,
            'access_key' => $number->discovered_access_key,
            'outbound_number_state_id' => $number->id,
            'recovery_status' => SvrsNfceRecoveryStatus::Queued,
            'correlation_id' => 'corr-kill',
        ]);

        $ks = app(SvrsNfceKillSwitchService::class);
        // userId null evita FK em audit_logs
        $ks->activate('drill backlog', 0, $profile->office_id);
        // force cache even if audit fails with user 0
        Cache::forever('sefaz.svrs_nfce_xml.kill_switch.runtime', true);

        app(OutboundXmlRecoveryOrchestrator::class)->runAttempt($req->id);

        $req->refresh();
        $number->refresh();
        $this->assertNotSame(SvrsNfceRecoveryStatus::Captured, $req->recovery_status);
        $this->assertSame(OutboundNumberStatus::XmlPending, $number->status);
        $this->assertSame(1, OutboundNumberState::withoutGlobalScopes()->where('id', $number->id)->count());
        $this->assertTrue(MaOutboundRetrievalRequest::withoutGlobalScopes()->whereKey($req->id)->exists());

        Cache::forget('sefaz.svrs_nfce_xml.kill_switch.runtime');
    }

    public function test_run_attempt_remote_not_found_agenda_retry(): void
    {
        [$profile, $number] = $this->seedEligible();
        $this->seedActiveCredentialStub($profile);
        Queue::fake();

        $req = MaOutboundRetrievalRequest::query()->create([
            'office_id' => $profile->office_id,
            'outbound_capture_profile_id' => $profile->id,
            'establishment_id' => $profile->establishment_id,
            'environment' => 'homologation',
            'model' => OutboundFiscalModel::Nfce,
            'direction' => 'OUT',
            'competence' => '2026-07',
            'origin' => OutboundRetrievalOrigin::SvrsPortalByKey,
            'access_key' => $number->discovered_access_key,
            'outbound_number_state_id' => $number->id,
            'recovery_status' => SvrsNfceRecoveryStatus::Queued,
            'attempt_count' => 0,
            'correlation_id' => 'corr-retry',
        ]);

        $this->fake->enqueue(new SvrsNfceRetrievalResult(
            outcome: SvrsNfceTransportOutcome::RemoteNotFound,
            httpStatus: 200,
            sanitizedDetail: 'não disponível',
        ));

        // Coloca material A1 de teste no vault (sem certificado fiscal real)
        $this->seedVaultPfxForProfile($profile);

        app(OutboundXmlRecoveryOrchestrator::class)->runAttempt($req->id);

        $req->refresh();
        $this->assertSame(SvrsNfceRecoveryStatus::RetryScheduled, $req->recovery_status);
        $this->assertNotNull($req->next_attempt_at);
        $number->refresh();
        $this->assertNotSame(OutboundNumberStatus::XmlCaptured, $number->status);
        $this->assertGreaterThanOrEqual(1, count($this->fake->calls));
    }

    public function test_tenancy_recovery_de_outro_office_nao_aparece(): void
    {
        [$profileA, $numberA] = $this->seedEligible();
        $officeB = Office::factory()->create();
        $clientB = Client::factory()->forOffice($officeB)->create();
        $estB = Establishment::factory()->forClient($clientB)->create([
            'cnpj' => '98765432000199',
            'address_state' => 'MA',
        ]);
        $profileB = OutboundCaptureProfile::query()->create([
            'office_id' => $officeB->id,
            'client_id' => $clientB->id,
            'establishment_id' => $estB->id,
            'uf' => 'MA',
            'environment' => 'homologation',
            'model' => OutboundFiscalModel::Nfce,
            'mode' => OutboundCaptureMode::Assisted,
            'status' => OutboundProfileStatus::Active,
            'allowlisted' => true,
        ]);

        MaOutboundRetrievalRequest::query()->create([
            'office_id' => $profileA->office_id,
            'outbound_capture_profile_id' => $profileA->id,
            'establishment_id' => $profileA->establishment_id,
            'environment' => 'homologation',
            'model' => OutboundFiscalModel::Nfce,
            'direction' => 'OUT',
            'competence' => '2026-07',
            'origin' => OutboundRetrievalOrigin::SvrsPortalByKey,
            'access_key' => $numberA->discovered_access_key,
            'recovery_status' => SvrsNfceRecoveryStatus::Queued,
        ]);
        MaOutboundRetrievalRequest::query()->create([
            'office_id' => $officeB->id,
            'outbound_capture_profile_id' => $profileB->id,
            'establishment_id' => $estB->id,
            'environment' => 'homologation',
            'model' => OutboundFiscalModel::Nfce,
            'direction' => 'OUT',
            'competence' => '2026-07',
            'origin' => OutboundRetrievalOrigin::SvrsPortalByKey,
            'access_key' => '21260798765432000199650010000000011234567890',
            'recovery_status' => SvrsNfceRecoveryStatus::Queued,
        ]);

        $userA = User::factory()->forOffice(
            Office::withoutGlobalScopes()->find($profileA->office_id)
        )->withTwoFactorConfirmed()->create();
        $this->actingAs($userA);
        app(CurrentOffice::class)->resolve($userA);

        $this->assertSame(1, MaOutboundRetrievalRequest::query()->count());
        $this->getJson('/api/v1/outbound/svrs-nfce/recoveries')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    /**
     * @return array{0: OutboundCaptureProfile, 1: OutboundNumberState}
     */
    private function seedEligible(): array
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

    private function seedActiveCredentialStub(OutboundCaptureProfile $profile): void
    {
        // activeFor só precisa de linha ACTIVE (hasA1)
        $fp = str_repeat('a', 64);
        ClientCredential::query()->create([
            'office_id' => $profile->office_id,
            'client_id' => $profile->client_id,
            'status' => CredentialStatus::Active,
            'subject_name' => 'Test Fixture',
            'holder_cnpj' => '12345678000190',
            'fingerprint_sha256' => $fp,
            'valid_from' => now()->subDay(),
            'valid_to' => now()->addYear(),
            'vault_object_id' => '01HZZZZZZZZZZZZZZZZZZZZZZZ', // placeholder until seedVault
            'activated_at' => now(),
        ]);
    }

    private function seedVaultPfxForProfile(OutboundCaptureProfile $profile): void
    {
        $fp = str_repeat('a', 64);
        $store = app(SecureObjectStore::class);
        $payload = json_encode([
            'pfx' => base64_encode('fake-pfx-bytes-for-fake-client'),
            'password' => 'test-only',
        ], JSON_THROW_ON_ERROR);
        $objectId = $store->put($payload, [
            'office_id' => $profile->office_id,
            'client_id' => $profile->client_id,
            'fingerprint' => $fp,
        ]);
        ClientCredential::query()
            ->where('client_id', $profile->client_id)
            ->update(['vault_object_id' => $objectId, 'fingerprint_sha256' => $fp]);
    }
}
