<?php

namespace Tests\Feature\Outbound;

use App\Enums\DocumentAcquisitionSource;
use App\Enums\OutboundCaptureMode;
use App\Enums\OutboundFiscalModel;
use App\Enums\OutboundNumberStatus;
use App\Enums\OutboundProfileStatus;
use App\Enums\OutboundRetrievalOrigin;
use App\Enums\OutboundSeriesStatus;
use App\Enums\SvrsNfceRecoveryStatus;
use App\Models\Client;
use App\Models\Establishment;
use App\Models\MaOutboundRetrievalRequest;
use App\Models\Office;
use App\Models\OutboundCaptureProfile;
use App\Models\OutboundNumberState;
use App\Models\OutboundSeriesCursor;
use App\Models\OutboundXmlRecoveryAttempt;
use App\Models\User;
use App\Support\CurrentOffice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SvrsNfceSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_tabelas_e_colunas_svrs(): void
    {
        $this->assertTrue(Schema::hasTable('outbound_xml_recovery_attempts'));
        $this->assertTrue(Schema::hasColumns('ma_outbound_retrieval_requests', [
            'origin', 'access_key', 'outbound_number_state_id', 'recovery_status',
            'failure_reason', 'attempt_count', 'next_attempt_at', 'correlation_id', 'sha256',
        ]));
        $this->assertSame(
            'SVRS_NFCE_DOWNLOAD_XML_DFE',
            DocumentAcquisitionSource::SvrsNfceDownloadXmlDfe->value
        );
        $this->assertSame(
            'SVRS_PORTAL_BY_KEY',
            OutboundRetrievalOrigin::SvrsPortalByKey->value
        );
    }

    public function test_access_key_normalizada_maiuscula(): void
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
            'recovery_status' => SvrsNfceRecoveryStatus::Eligible,
        ]);

        $this->assertSame(
            '21260712345678000190650010000000011234567892',
            $req->fresh()->access_key
        );
        $public = $req->toPublicArray();
        $this->assertArrayNotHasKey('vault_object_id', $public);
        $this->assertStringContainsString('*', (string) $public['access_key_masked']);
    }

    public function test_tenancy_scope_office(): void
    {
        $officeA = Office::factory()->create();
        $officeB = Office::factory()->create();
        $clientA = Client::factory()->forOffice($officeA)->create();
        $clientB = Client::factory()->forOffice($officeB)->create();
        $estA = Establishment::factory()->forClient($clientA)->create(['address_state' => 'MA']);
        $estB = Establishment::factory()->forClient($clientB)->create(['address_state' => 'MA']);

        $profileA = OutboundCaptureProfile::query()->create([
            'office_id' => $officeA->id,
            'client_id' => $clientA->id,
            'establishment_id' => $estA->id,
            'uf' => 'MA',
            'environment' => 'homologation',
            'model' => OutboundFiscalModel::Nfce,
            'mode' => OutboundCaptureMode::Assisted,
            'status' => OutboundProfileStatus::Active,
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
        ]);

        MaOutboundRetrievalRequest::query()->create([
            'office_id' => $officeA->id,
            'outbound_capture_profile_id' => $profileA->id,
            'establishment_id' => $estA->id,
            'environment' => 'homologation',
            'model' => OutboundFiscalModel::Nfce,
            'direction' => 'OUT',
            'competence' => '2026-07',
            'origin' => OutboundRetrievalOrigin::SvrsPortalByKey,
            'access_key' => '21260712345678000190650010000000011234567892',
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
            'access_key' => '21260712345678000190650010000000011234567892',
            'recovery_status' => SvrsNfceRecoveryStatus::Queued,
        ]);

        $userA = User::factory()->forOffice($officeA)->withTwoFactorConfirmed()->create();
        $this->actingAs($userA);
        app(CurrentOffice::class)->resolve($userA);

        $this->assertSame(1, MaOutboundRetrievalRequest::query()->count());
        $this->assertSame(2, MaOutboundRetrievalRequest::withoutGlobalScopes()->count());
    }

    public function test_attempt_public_array_sanitized(): void
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
            'outbound_number_state_id' => $number->id,
            'recovery_status' => SvrsNfceRecoveryStatus::Running,
            'correlation_id' => 'corr-test',
        ]);
        $attempt = OutboundXmlRecoveryAttempt::query()->create([
            'office_id' => $office->id,
            'ma_outbound_retrieval_request_id' => $req->id,
            'outbound_capture_profile_id' => $profile->id,
            'outbound_number_state_id' => $number->id,
            'access_key' => '21260712345678000190650010000000011234567892',
            'correlation_id' => 'corr-test',
            'attempt_number' => 1,
            'result' => SvrsNfceRecoveryStatus::Captured,
            'started_at' => now(),
            'finished_at' => now(),
        ]);

        $pub = $attempt->toPublicArray();
        $this->assertArrayNotHasKey('xml', $pub);
        $this->assertArrayNotHasKey('html', $pub);
        $this->assertArrayNotHasKey('pfx', $pub);
        $this->assertArrayNotHasKey('password', $pub);
        $this->assertArrayNotHasKey('vault_object_id', $pub);
    }
}
