<?php

namespace Tests\Feature\Outbound;

use App\Enums\OfficeRole;
use App\Enums\OutboundCaptureMode;
use App\Enums\OutboundFiscalModel;
use App\Enums\OutboundNumberStatus;
use App\Enums\OutboundProfileStatus;
use App\Enums\OutboundRetrievalOrigin;
use App\Enums\OutboundSeriesStatus;
use App\Enums\SvrsNfceFailureReason;
use App\Enums\SvrsNfceRecoveryStatus;
use App\Enums\SvrsNfceTransportOutcome;
use App\Jobs\RecoverSvrsNfceXmlJob;
use App\Models\Client;
use App\Models\Establishment;
use App\Models\MaOutboundRetrievalRequest;
use App\Models\Office;
use App\Models\OutboundCaptureProfile;
use App\Models\OutboundNumberState;
use App\Models\OutboundSeriesCursor;
use App\Models\OutboundXmlRecoveryAttempt;
use App\Models\User;
use App\Services\Outbound\OutboundXmlRecoveryOrchestrator;
use App\Support\CurrentOffice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 13.6 varredura anti-segredo + 13.7 drill kill switch com backlog.
 */
class SvrsNfceAntiSecretAndKillDrillTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private const FORBIDDEN = [
        'BEGIN PRIVATE KEY',
        'BEGIN RSA PRIVATE KEY',
        'BEGIN CERTIFICATE',
        '-----BEGIN',
        'password',
        'vault_object_id',
        'CURLOPT_SSLCERT_BLOB value',
        '<nfeProc',
        'downloadXml(',
        'cookie=',
        'Set-Cookie',
    ];

    public function test_api_responses_sem_marcadores_sensiveis(): void
    {
        $office = Office::factory()->create();
        $admin = User::factory()->forOffice($office, OfficeRole::Admin)->withTwoFactorConfirmed()->create();
        $client = Client::factory()->forOffice($office)->create();
        $est = Establishment::factory()->forClient($client)->create(['address_state' => 'MA']);
        $profile = OutboundCaptureProfile::query()->create([
            'office_id' => $office->id,
            'client_id' => $client->id,
            'establishment_id' => $est->id,
            'uf' => 'MA',
            'environment' => 'homologation',
            'model' => OutboundFiscalModel::Nfce,
            'mode' => OutboundCaptureMode::Assisted,
            'status' => OutboundProfileStatus::Active,
        ]);
        $req = MaOutboundRetrievalRequest::query()->create([
            'office_id' => $office->id,
            'outbound_capture_profile_id' => $profile->id,
            'establishment_id' => $est->id,
            'environment' => 'homologation',
            'model' => OutboundFiscalModel::Nfce,
            'direction' => 'OUT',
            'competence' => '2026-07',
            'origin' => OutboundRetrievalOrigin::SvrsPortalByKey,
            'access_key' => '21260712345678000190650010000000011234567892',
            'recovery_status' => SvrsNfceRecoveryStatus::Blocked,
            'failure_reason' => SvrsNfceFailureReason::ResponseContractChanged,
            'correlation_id' => 'corr-secret-scan',
            'last_error' => 'Contrato alterado',
        ]);
        OutboundXmlRecoveryAttempt::query()->create([
            'office_id' => $office->id,
            'ma_outbound_retrieval_request_id' => $req->id,
            'outbound_capture_profile_id' => $profile->id,
            'access_key' => '21260712345678000190650010000000011234567892',
            'correlation_id' => 'corr-secret-scan',
            'attempt_number' => 1,
            'result' => SvrsNfceRecoveryStatus::Blocked,
            'failure_reason' => SvrsNfceFailureReason::ResponseContractChanged,
            'transport_outcome' => SvrsNfceTransportOutcome::ResponseContractChanged,
            'sanitized_detail' => 'wrapper divergente',
            'started_at' => now(),
            'finished_at' => now(),
        ]);

        $this->actingAs($admin);
        app(CurrentOffice::class)->resolve($admin);

        $payloads = [
            $this->getJson('/api/v1/outbound/svrs-nfce/summary')->assertOk()->getContent(),
            $this->getJson('/api/v1/outbound/svrs-nfce/recoveries')->assertOk()->getContent(),
            $this->getJson('/api/v1/outbound/svrs-nfce/recoveries/'.$req->id.'/attempts')->assertOk()->getContent(),
            $this->getJson('/api/v1/operations/summary')->assertOk()->getContent(),
            $this->getJson('/api/v1/operations/inbox')->assertOk()->getContent(),
        ];

        foreach ($payloads as $json) {
            $this->assertNotFalse($json);
            $lower = strtolower((string) $json);
            foreach (self::FORBIDDEN as $marker) {
                if (in_array(strtolower($marker), ['password'], true)) {
                    // palavra "password" não deve aparecer como campo
                    $this->assertStringNotContainsString('"password"', $lower);

                    continue;
                }
                $this->assertStringNotContainsString(
                    strtolower($marker),
                    $lower,
                    "Marcador proibido em response: {$marker}"
                );
            }
            // chave completa de 44 não deve vazar se mascarada
            $this->assertStringNotContainsString(
                '21260712345678000190650010000000011234567892',
                (string) $json
            );
        }

        // Job serializado só com id
        $job = new RecoverSvrsNfceXmlJob($req->id);
        $serialized = serialize($job);
        $this->assertStringNotContainsString('BEGIN ', $serialized);
        $this->assertStringNotContainsString('nfeProc', $serialized);
        $this->assertStringContainsString((string) $req->id, $serialized);
    }

    public function test_kill_switch_drill_preserva_documentos_tentativas_nnf(): void
    {
        $office = Office::factory()->create();
        $admin = User::factory()->forOffice($office, OfficeRole::Admin)->withTwoFactorConfirmed()->create();
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
            'seed_nnf' => 10,
            'discovery_position' => 15,
            'status' => OutboundSeriesStatus::Idle,
        ]);
        $number = OutboundNumberState::query()->create([
            'office_id' => $office->id,
            'outbound_capture_profile_id' => $profile->id,
            'outbound_series_cursor_id' => $series->id,
            'series' => 1,
            'nnf' => 12,
            'status' => OutboundNumberStatus::XmlPending,
            'discovered_access_key' => '21260712345678000190650010000000011234567892',
        ]);
        $req = MaOutboundRetrievalRequest::query()->create([
            'office_id' => $office->id,
            'outbound_capture_profile_id' => $profile->id,
            'establishment_id' => $est->id,
            'environment' => 'homologation',
            'model' => OutboundFiscalModel::Nfce,
            'direction' => 'OUT',
            'competence' => '2026-07',
            'origin' => OutboundRetrievalOrigin::SvrsPortalByKey,
            'access_key' => $number->discovered_access_key,
            'outbound_number_state_id' => $number->id,
            'recovery_status' => SvrsNfceRecoveryStatus::Queued,
            'attempt_count' => 1,
            'correlation_id' => 'corr-drill',
        ]);
        OutboundXmlRecoveryAttempt::query()->create([
            'office_id' => $office->id,
            'ma_outbound_retrieval_request_id' => $req->id,
            'outbound_capture_profile_id' => $profile->id,
            'outbound_number_state_id' => $number->id,
            'access_key' => $number->discovered_access_key,
            'correlation_id' => 'corr-drill',
            'attempt_number' => 1,
            'result' => SvrsNfceRecoveryStatus::RetryScheduled,
            'started_at' => now()->subHour(),
            'finished_at' => now()->subHour(),
        ]);

        $posBefore = $series->discovery_position;
        $nnfBefore = $number->nnf;
        $attemptsBefore = OutboundXmlRecoveryAttempt::withoutGlobalScopes()->count();
        $reqId = $req->id;

        $this->actingAs($admin);
        app(CurrentOffice::class)->resolve($admin);

        $this->postJson('/api/v1/outbound/svrs-nfce/kill-switch', [
            'active' => true,
            'reason' => 'drill rollback com backlog',
        ])->assertOk()->assertJsonPath('data.active', true);

        // Job com kill switch não captura
        config(['sefaz.svrs_nfce_xml.retrieval_enabled' => true]);
        app(OutboundXmlRecoveryOrchestrator::class)->runAttempt($reqId);

        $series->refresh();
        $number->refresh();
        $req->refresh();

        $this->assertSame($posBefore, $series->discovery_position, 'posição nNF preservada');
        $this->assertSame($nnfBefore, $number->nnf);
        $this->assertSame(OutboundNumberStatus::XmlPending, $number->status);
        $this->assertTrue(
            OutboundXmlRecoveryAttempt::withoutGlobalScopes()->count() >= $attemptsBefore
        );
        $this->assertTrue(
            MaOutboundRetrievalRequest::withoutGlobalScopes()->whereKey($reqId)->exists()
        );

        // Desligar kill switch não apaga
        $this->postJson('/api/v1/outbound/svrs-nfce/kill-switch', [
            'active' => false,
            'reason' => 'fim do drill',
        ])->assertOk()->assertJsonPath('data.active', false);

        $this->assertSame($posBefore, $series->fresh()->discovery_position);
        $this->assertSame(1, MaOutboundRetrievalRequest::withoutGlobalScopes()->whereKey($reqId)->count());
    }

    public function test_dto_publico_recovery_e_attempt_sanitizados(): void
    {
        $office = Office::factory()->create();
        $client = Client::factory()->forOffice($office)->create();
        $est = Establishment::factory()->forClient($client)->create(['address_state' => 'MA']);
        $profile = OutboundCaptureProfile::query()->create([
            'office_id' => $office->id,
            'client_id' => $client->id,
            'establishment_id' => $est->id,
            'uf' => 'MA',
            'environment' => 'homologation',
            'model' => OutboundFiscalModel::Nfce,
            'mode' => OutboundCaptureMode::Assisted,
            'status' => OutboundProfileStatus::Active,
        ]);
        $req = MaOutboundRetrievalRequest::query()->create([
            'office_id' => $office->id,
            'outbound_capture_profile_id' => $profile->id,
            'establishment_id' => $est->id,
            'environment' => 'homologation',
            'model' => OutboundFiscalModel::Nfce,
            'direction' => 'OUT',
            'competence' => '2026-07',
            'origin' => OutboundRetrievalOrigin::SvrsPortalByKey,
            'access_key' => '21260712345678000190650010000000011234567892',
            'recovery_status' => SvrsNfceRecoveryStatus::Running,
            'correlation_id' => 'c1',
        ]);
        $pub = $req->toPublicArray();
        $json = json_encode($pub, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('21260712345678000190650010000000011234567892', $json);
        $this->assertArrayNotHasKey('vault_object_id', $pub);
        $this->assertArrayNotHasKey('pfx', $pub);
        $this->assertArrayHasKey('access_key_masked', $pub);
    }
}
