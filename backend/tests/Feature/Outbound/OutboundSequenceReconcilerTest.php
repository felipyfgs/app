<?php

namespace Tests\Feature\Outbound;

use App\Contracts\SecureObjectStore;
use App\Contracts\SefazOutboundProtocolQueryClient;
use App\DTO\Outbound\ProtocolQueryResult;
use App\Enums\CredentialStatus;
use App\Enums\OutboundCaptureMode;
use App\Enums\OutboundFiscalModel;
use App\Enums\OutboundNumberStatus;
use App\Enums\OutboundProfileStatus;
use App\Enums\OutboundRetrievalOrigin;
use App\Enums\OutboundRetrievalStatus;
use App\Enums\OutboundSeriesStatus;
use App\Enums\SvrsNfceRecoveryStatus;
use App\Jobs\RecoverSvrsNfceXmlJob;
use App\Models\Client;
use App\Models\ClientCredential;
use App\Models\Establishment;
use App\Models\MaOutboundRetrievalRequest;
use App\Models\Office;
use App\Models\OutboundCaptureProfile;
use App\Models\OutboundNumberState;
use App\Models\OutboundSeriesCursor;
use App\Services\Outbound\AccessKeyCandidateBuilder;
use App\Services\Outbound\OutboundSequenceReconciler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Reconciliação de sequência: lease, recovery SVRS vs assistido, unicidade por chave.
 */
class OutboundSequenceReconcilerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config([
            'sefaz.ma_outbound.enabled' => true,
            'sefaz.ma_outbound.protocol_query_enabled' => true,
            'sefaz.ma_outbound.global_rps' => 1000,
            'sefaz.ma_outbound.max_numbers_per_run' => 5,
            'sefaz.ma_outbound.retry_interval_hours' => 12,
            'sefaz.svrs_nfce_xml.retrieval_enabled' => false,
            'sefaz.svrs_nfce_xml.auto_queue_enabled' => false,
            'sefaz.svrs_nfe55_xml.retrieval_enabled' => false,
            'sefaz.svrs_nfe55_xml.auto_queue_enabled' => false,
        ]);
    }

    public function test_descoberta_cria_pendencia_assistida_por_access_key_com_competence_do_aamm(): void
    {
        [$series, $establishment, $profile] = $this->seedSeriesContext(
            model: OutboundFiscalModel::Nfe,
            discovery: 11,
            seedIssuedAt: '2025-01-15 12:00:00', // seed antigo — competence NÃO deve usar isso
        );
        $this->seedVaultCredential($profile);

        $builder = app(AccessKeyCandidateBuilder::class);
        $built = $builder->build([
            'cuf' => '21',
            'aamm' => '2607',
            'cnpj' => $establishment->cnpj,
            'model' => '55',
            'series' => 1,
            'nnf' => 11,
            'tp_emis' => '1',
        ]);
        $key = $built['access_key'];

        $this->bindQueryClient(fn (string $accessKey) => new ProtocolQueryResult(
            cStat: '100',
            xMotivo: 'Autorizado o uso da NF-e',
            consultedAccessKey: $accessKey,
            returnedAccessKey: $key,
            protocol: '121260000000001',
        ));

        // Pré-cria state com candidata = chave real (aamm 2607 → competence 2026-07)
        OutboundNumberState::query()->create([
            'office_id' => $series->office_id,
            'outbound_capture_profile_id' => $profile->id,
            'outbound_series_cursor_id' => $series->id,
            'series' => 1,
            'nnf' => 11,
            'status' => OutboundNumberStatus::ConsultQueued,
            'candidate_access_key' => $key,
            'candidate_cnf' => $built['cnf'],
            'attempts' => 0,
        ]);

        $result = app(OutboundSequenceReconciler::class)->reconcileSeries($series->fresh(), maxNumbers: 1);

        $this->assertFalse($result['blocked']);
        $this->assertSame(1, $result['discovered']);

        $state = OutboundNumberState::query()->where('nnf', 11)->firstOrFail();
        $this->assertSame(OutboundNumberStatus::XmlPending, $state->status);
        $this->assertSame($key, $state->discovered_access_key);

        $req = MaOutboundRetrievalRequest::withoutGlobalScopes()
            ->where('office_id', $series->office_id)
            ->where('access_key', $key)
            ->first();

        $this->assertNotNull($req);
        $this->assertSame('2026-07', $req->competence, 'competence deve vir do AAMM da chave, não do seed_issued_at');
        $this->assertSame(OutboundRetrievalStatus::Pending, $req->status);
        $this->assertSame(OutboundCaptureMode::Assisted, $req->mode);
        $this->assertSame(OutboundRetrievalOrigin::MaAssistedUpload, $req->origin);
        $this->assertSame($state->id, $req->outbound_number_state_id);
        $this->assertSame(substr((string) $establishment->cnpj, 0, 8), $req->root_cnpj);
        $this->assertSame($key, $req->access_key);

        // Idempotência: segunda descoberta/reconciliação não duplica por office+access_key
        $countBefore = MaOutboundRetrievalRequest::withoutGlobalScopes()
            ->where('office_id', $series->office_id)
            ->where('access_key', $key)
            ->count();
        $this->assertSame(1, $countBefore);

        // Chama novamente open path via ensure: firstOrCreate não cria segundo
        MaOutboundRetrievalRequest::withoutGlobalScopes()->firstOrCreate(
            ['office_id' => $series->office_id, 'access_key' => $key],
            ['competence' => '1999-01']
        );
        $this->assertSame(1, MaOutboundRetrievalRequest::withoutGlobalScopes()
            ->where('office_id', $series->office_id)
            ->where('access_key', $key)
            ->count());
        $this->assertSame('2026-07', $req->fresh()->competence);
    }

    public function test_auto_queue_svrs_chama_ensure_recovery_em_vez_de_assistido(): void
    {
        config([
            'sefaz.svrs_nfce_xml.retrieval_enabled' => true,
            'sefaz.svrs_nfce_xml.auto_queue_enabled' => true,
            'sefaz.svrs_nfce_xml.pilot_allowlist_only' => false,
            'sefaz.svrs_nfce_xml.require_signature' => false,
        ]);
        Queue::fake();

        [$series, $establishment, $profile] = $this->seedSeriesContext(
            model: OutboundFiscalModel::Nfce,
            discovery: 5,
        );
        $this->seedVaultCredential($profile);

        $builder = app(AccessKeyCandidateBuilder::class);
        $built = $builder->build([
            'cuf' => '21',
            'aamm' => '2607',
            'cnpj' => $establishment->cnpj,
            'model' => '65',
            'series' => 1,
            'nnf' => 5,
            'tp_emis' => '1',
        ]);
        $key = $built['access_key'];

        $this->bindQueryClient(fn (string $accessKey) => new ProtocolQueryResult(
            cStat: '100',
            xMotivo: 'Autorizado o uso da NF-e',
            consultedAccessKey: $accessKey,
            returnedAccessKey: $key,
        ));

        OutboundNumberState::query()->create([
            'office_id' => $series->office_id,
            'outbound_capture_profile_id' => $profile->id,
            'outbound_series_cursor_id' => $series->id,
            'series' => 1,
            'nnf' => 5,
            'status' => OutboundNumberStatus::ConsultQueued,
            'candidate_access_key' => $key,
            'candidate_cnf' => $built['cnf'],
            'attempts' => 0,
        ]);

        $result = app(OutboundSequenceReconciler::class)->reconcileSeries($series->fresh(), maxNumbers: 1);

        $this->assertSame(1, $result['discovered']);

        $svrs = MaOutboundRetrievalRequest::withoutGlobalScopes()
            ->where('access_key', $key)
            ->where('origin', OutboundRetrievalOrigin::SvrsPortalByKey)
            ->first();
        $this->assertNotNull($svrs, 'deve abrir recovery SVRS via ensureRecovery');
        $this->assertSame(SvrsNfceRecoveryStatus::Queued, $svrs->recovery_status);
        $this->assertSame(OutboundCaptureMode::Automatic, $svrs->mode);

        $assisted = MaOutboundRetrievalRequest::withoutGlobalScopes()
            ->where('access_key', $key)
            ->where('origin', OutboundRetrievalOrigin::MaAssistedUpload)
            ->count();
        $this->assertSame(0, $assisted, 'não cria assistido quando SVRS auto-queue sucede');

        Queue::assertPushed(RecoverSvrsNfceXmlJob::class);
    }

    public function test_bloqueio_656_libera_lease_locked_at(): void
    {
        [$series, $establishment, $profile] = $this->seedSeriesContext(
            model: OutboundFiscalModel::Nfe,
            discovery: 20,
        );
        $this->seedVaultCredential($profile);

        $builder = app(AccessKeyCandidateBuilder::class);
        $built = $builder->build([
            'cuf' => '21',
            'aamm' => '2607',
            'cnpj' => $establishment->cnpj,
            'model' => '55',
            'series' => 1,
            'nnf' => 20,
            'tp_emis' => '1',
        ]);

        $this->bindQueryClient(fn (string $accessKey) => new ProtocolQueryResult(
            cStat: '656',
            xMotivo: 'Rejeicao: Consumo Indevido',
            consultedAccessKey: $accessKey,
        ));

        OutboundNumberState::query()->create([
            'office_id' => $series->office_id,
            'outbound_capture_profile_id' => $profile->id,
            'outbound_series_cursor_id' => $series->id,
            'series' => 1,
            'nnf' => 20,
            'status' => OutboundNumberStatus::ConsultQueued,
            'candidate_access_key' => $built['access_key'],
            'candidate_cnf' => $built['cnf'],
            'attempts' => 0,
        ]);

        $beforePos = $series->discovery_position;
        $result = app(OutboundSequenceReconciler::class)->reconcileSeries($series->fresh(), maxNumbers: 1);

        $this->assertTrue($result['blocked']);
        $series->refresh();
        $this->assertSame(OutboundSeriesStatus::Blocked, $series->status);
        $this->assertNull($series->locked_at, 'lease DB deve ser limpo no block mid-run');
        $this->assertNull($series->lock_owner);
        $this->assertSame($beforePos, $series->discovery_position, 'posição nNF não avança em 656');
    }

    public function test_chave_divergente_bloqueia_e_libera_lease(): void
    {
        [$series, $establishment, $profile] = $this->seedSeriesContext(
            model: OutboundFiscalModel::Nfe,
            discovery: 3,
        );
        $this->seedVaultCredential($profile);

        $builder = app(AccessKeyCandidateBuilder::class);
        $candidate = $builder->build([
            'cuf' => '21',
            'aamm' => '2607',
            'cnpj' => $establishment->cnpj,
            'model' => '55',
            'series' => 1,
            'nnf' => 3,
            'tp_emis' => '1',
        ]);
        // Chave válida de outro nNF (divergente)
        $other = $builder->build([
            'cuf' => '21',
            'aamm' => '2607',
            'cnpj' => $establishment->cnpj,
            'model' => '55',
            'series' => 1,
            'nnf' => 99,
            'tp_emis' => '1',
        ]);

        $this->bindQueryClient(fn (string $accessKey) => new ProtocolQueryResult(
            cStat: '562',
            xMotivo: 'Rejeicao com chNFe:'.$other['access_key'],
            consultedAccessKey: $accessKey,
            returnedAccessKey: $other['access_key'],
        ));

        OutboundNumberState::query()->create([
            'office_id' => $series->office_id,
            'outbound_capture_profile_id' => $profile->id,
            'outbound_series_cursor_id' => $series->id,
            'series' => 1,
            'nnf' => 3,
            'status' => OutboundNumberStatus::ConsultQueued,
            'candidate_access_key' => $candidate['access_key'],
            'candidate_cnf' => $candidate['cnf'],
            'attempts' => 0,
        ]);

        $result = app(OutboundSequenceReconciler::class)->reconcileSeries($series->fresh(), maxNumbers: 1);

        $this->assertTrue($result['blocked']);
        $series->refresh();
        $this->assertSame(OutboundSeriesStatus::Blocked, $series->status);
        $this->assertNull($series->locked_at);
        $this->assertNull($series->lock_owner);

        $state = OutboundNumberState::query()->where('nnf', 3)->firstOrFail();
        $this->assertSame(OutboundNumberStatus::Blocked, $state->status);
        $this->assertNull($state->discovered_access_key);
    }

    /**
     * @return array{0: OutboundSeriesCursor, 1: Establishment, 2: OutboundCaptureProfile}
     */
    private function seedSeriesContext(
        OutboundFiscalModel $model,
        int $discovery,
        ?string $seedIssuedAt = null,
    ): array {
        $office = Office::factory()->create();
        $client = Client::factory()->forOffice($office)->create(['root_cnpj' => '12345678']);
        $est = Establishment::factory()->forClient($client)->create([
            'cnpj' => '12345678000190',
            'address_state' => 'MA',
            'capture_enabled' => true,
            'is_active' => true,
        ]);
        $profile = OutboundCaptureProfile::query()->create([
            'office_id' => $office->id,
            'client_id' => $client->id,
            'establishment_id' => $est->id,
            'uf' => 'MA',
            'environment' => 'homologation',
            'model' => $model,
            'mode' => OutboundCaptureMode::Assisted,
            'status' => OutboundProfileStatus::Active,
            'allowlisted' => true,
            'consent_recorded' => true,
            'mandate_reference' => 'mandate-test-001',
        ]);
        $series = OutboundSeriesCursor::query()->create([
            'office_id' => $office->id,
            'outbound_capture_profile_id' => $profile->id,
            'establishment_id' => $est->id,
            'environment' => 'homologation',
            'model' => $model,
            'series' => 1,
            'seed_nnf' => max(1, $discovery - 1),
            'discovery_position' => $discovery,
            'status' => OutboundSeriesStatus::Idle,
            'tp_emis' => '1',
            'seed_issued_at' => $seedIssuedAt,
        ]);

        return [$series, $est, $profile];
    }

    private function seedVaultCredential(OutboundCaptureProfile $profile): void
    {
        $fp = str_repeat('c', 64);
        $store = app(SecureObjectStore::class);
        $payload = json_encode([
            'pfx' => base64_encode('fake-pfx-bytes-for-sequence-reconciler'),
            'password' => 'test-only',
        ], JSON_THROW_ON_ERROR);
        $objectId = $store->put($payload, [
            'office_id' => $profile->office_id,
            'client_id' => $profile->client_id,
            'fingerprint' => $fp,
        ]);

        ClientCredential::query()->create([
            'office_id' => $profile->office_id,
            'client_id' => $profile->client_id,
            'status' => CredentialStatus::Active,
            'subject_name' => 'Test Fixture Sequence',
            'holder_cnpj' => '12345678000190',
            'fingerprint_sha256' => $fp,
            'valid_from' => now()->subDay(),
            'valid_to' => now()->addYear(),
            'vault_object_id' => $objectId,
            'activated_at' => now(),
        ]);
    }

    /**
     * @param  callable(string): ProtocolQueryResult  $responder
     */
    private function bindQueryClient(callable $responder): void
    {
        $this->app->instance(SefazOutboundProtocolQueryClient::class, new class($responder) implements SefazOutboundProtocolQueryClient
        {
            /** @param  callable(string): ProtocolQueryResult  $responder */
            public function __construct(private $responder) {}

            public function consult(
                string $accessKey,
                string $model,
                string $environment,
                array $certificate,
            ): ProtocolQueryResult {
                return ($this->responder)(strtoupper($accessKey));
            }
        });
    }
}
