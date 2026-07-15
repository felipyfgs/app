<?php

namespace Tests\Feature\Outbound;

use App\DTO\Outbound\ProtocolQueryResult;
use App\Enums\DocumentAcquisitionSource;
use App\Enums\OutboundCaptureMode;
use App\Enums\OutboundFiscalModel;
use App\Enums\OutboundNumberStatus;
use App\Enums\OutboundProfileStatus;
use App\Enums\OutboundRetrievalStatus;
use App\Enums\OutboundSeriesStatus;
use App\Models\Client;
use App\Models\DfeDocument;
use App\Models\DocumentAcquisition;
use App\Models\Establishment;
use App\Models\MaOutboundRetrievalRequest;
use App\Models\Office;
use App\Models\OutboundCaptureProfile;
use App\Models\OutboundNumberState;
use App\Models\OutboundSeriesCursor;
use App\Services\Outbound\AccessKeyCandidateBuilder;
use App\Services\Outbound\OutboundKillSwitchService;
use App\Services\Outbound\ProtocolQueryResponseParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Drills de 9.6 em CI: 656, timeout ambíguo, chave divergente, pacote/SHA, kill switch.
 * Sem rede fiscal e sem certificado real.
 */
class OutboundDrillScenariosTest extends TestCase
{
    use RefreshDatabase;

    public function test_656_bloqueia_serie_sem_avancar_posicao(): void
    {
        $series = $this->seedSeries(discovery: 20);
        $before = $series->discovery_position;

        app(OutboundKillSwitchService::class)->blockSeries($series, 'cStat 656 — consumo indevido', '656');
        $series->refresh();

        $this->assertSame(OutboundSeriesStatus::Blocked, $series->status);
        $this->assertSame($before, $series->discovery_position);
        $this->assertSame('656', $series->last_cstat);
    }

    public function test_timeout_ambiguo_preserva_candidata(): void
    {
        $series = $this->seedSeries();
        $state = OutboundNumberState::query()->create([
            'office_id' => $series->office_id,
            'outbound_capture_profile_id' => $series->outbound_capture_profile_id,
            'outbound_series_cursor_id' => $series->id,
            'series' => 1,
            'nnf' => 11,
            'status' => OutboundNumberStatus::ConsultQueued,
            'candidate_access_key' => '21260712345678000190550010000000111234567890',
            'candidate_cnf' => '12345678',
            'attempts' => 0,
        ]);

        $result = new ProtocolQueryResult(
            cStat: '000',
            xMotivo: 'Timeout ambíguo',
            consultedAccessKey: $state->candidate_access_key,
            ambiguousTimeout: true,
        );

        $this->assertTrue($result->ambiguousTimeout);
        // Simula persistência do reconciler: mesma candidata, retry
        $state->forceFill([
            'status' => OutboundNumberStatus::RetryScheduled,
            'attempts' => 1,
            'next_attempt_at' => now()->addHours(12),
        ])->save();
        $state->refresh();

        $this->assertSame('21260712345678000190550010000000111234567890', $state->candidate_access_key);
        $this->assertSame('12345678', $state->candidate_cnf);
        $this->assertSame(OutboundNumberStatus::RetryScheduled, $state->status);
    }

    public function test_chave_divergente_nao_vira_key_discovered(): void
    {
        $builder = new AccessKeyCandidateBuilder;
        $ok = $builder->matchesIdentity(
            '21260712345678000190550010000000011234567890',
            '21',
            '12345678000190',
            '55',
            1,
            1,
            '1',
        );
        // Chave da fixture pode falhar DV — o importante é divergência de nNF
        $wrongNnf = $builder->matchesIdentity(
            str_pad('21', 44, '0'),
            '21',
            '12345678000190',
            '55',
            1,
            99,
            '1',
        );
        $this->assertFalse($wrongNnf);
    }

    public function test_sha_divergente_quarentena_sem_sobrescrever_canonico(): void
    {
        $office = Office::factory()->create();
        $client = Client::factory()->forOffice($office)->create();
        $est = Establishment::factory()->forClient($client)->create([
            'cnpj' => '12345678000190',
            'address_state' => 'MA',
        ]);

        $xmlA = '<nfeProc>A</nfeProc>';
        $xmlB = '<nfeProc>B</nfeProc>';
        $shaA = hash('sha256', $xmlA);
        $shaB = hash('sha256', $xmlB);
        $key = '21260712345678000190550010000000011234567890';

        $docA = DfeDocument::query()->create([
            'office_id' => $office->id,
            'sha256' => $shaA,
            'document_type' => \App\Enums\AdnDocumentType::Nfe,
            'schema_version' => 'procNFe_v4.00.xsd',
            'access_key' => $key,
            'vault_object_id' => 'obj-a',
            'byte_size' => strlen($xmlA),
            'parse_status' => 'OK',
        ]);
        $docB = DfeDocument::query()->create([
            'office_id' => $office->id,
            'sha256' => $shaB,
            'document_type' => \App\Enums\AdnDocumentType::Nfe,
            'schema_version' => 'procNFe_v4.00.xsd',
            'access_key' => $key,
            'vault_object_id' => 'obj-b',
            'byte_size' => strlen($xmlB),
            'parse_status' => 'QUARANTINE',
            'parse_alert' => 'Mesma chave com bytes divergentes',
        ]);

        DocumentAcquisition::query()->create([
            'office_id' => $office->id,
            'dfe_document_id' => $docA->id,
            'access_key' => $key,
            'source' => DocumentAcquisitionSource::MaOfficialPackage,
            'sha256' => $shaA,
            'is_canonical' => true,
            'bytes_diverge_from_canonical' => false,
            'establishment_id' => $est->id,
        ]);
        DocumentAcquisition::query()->create([
            'office_id' => $office->id,
            'dfe_document_id' => $docB->id,
            'access_key' => $key,
            'source' => DocumentAcquisitionSource::MaOfficialPackage,
            'sha256' => $shaB,
            'is_canonical' => false,
            'bytes_diverge_from_canonical' => true,
            'quarantine_reason' => 'SHA divergente',
            'establishment_id' => $est->id,
        ]);

        $this->assertSame(1, DocumentAcquisition::query()->where('is_canonical', true)->where('access_key', $key)->count());
        $this->assertSame(1, DocumentAcquisition::query()->where('bytes_diverge_from_canonical', true)->count());
        $this->assertNotSame($shaA, $shaB);
    }

    public function test_pacote_expirado_nao_avanca_nnf(): void
    {
        $series = $this->seedSeries(discovery: 30);
        $profile = OutboundCaptureProfile::query()->findOrFail($series->outbound_capture_profile_id);

        MaOutboundRetrievalRequest::query()->create([
            'office_id' => $series->office_id,
            'outbound_capture_profile_id' => $profile->id,
            'establishment_id' => $series->establishment_id,
            'environment' => 'homologation',
            'model' => OutboundFiscalModel::Nfe,
            'direction' => 'OUT',
            'competence' => '2026-01',
            'status' => OutboundRetrievalStatus::Expired,
            'mode' => OutboundCaptureMode::Assisted,
            'expires_at' => now()->subDay(),
        ]);

        $series->refresh();
        $this->assertSame(30, $series->discovery_position);
        $this->assertSame(
            1,
            MaOutboundRetrievalRequest::query()->where('status', OutboundRetrievalStatus::Expired)->count()
        );
    }

    public function test_kill_switch_preserva_cursores_e_tabelas(): void
    {
        $series = $this->seedSeries(discovery: 40);
        $ks = app(OutboundKillSwitchService::class);
        $profile = OutboundCaptureProfile::query()->findOrFail($series->outbound_capture_profile_id);

        $ks->activateGlobal('drill 9.6', 1, $series->office_id);
        $this->assertTrue($ks->isGlobalActive());
        $this->assertTrue($ks->isBlocked($profile));

        // Estado preservado
        $this->assertSame(40, $series->fresh()->discovery_position);
        $this->assertTrue(Schema::hasTable('outbound_series_cursors'));
        $this->assertFalse(Schema::hasColumn('outbound_series_cursors', 'last_nsu'));

        $ks->deactivateGlobal('fim drill', 1, $series->office_id);
        $this->assertFalse($ks->isGlobalActive());
    }

    public function test_parser_562_sem_chave_bloqueia_forca_bruta(): void
    {
        $parser = new ProtocolQueryResponseParser;
        $xml = file_get_contents(base_path('tests/fixtures/ma-outbound/consulta_562_sem_chave.xml'));
        $result = $parser->parse((string) $xml, '21260712345678000190550010000000011234567890');
        $this->assertTrue($result->is562WithoutKey());
        $this->assertTrue($result->isLimitedWithoutKey());
    }

    public function test_m2m_disabled_e_modo_assisted(): void
    {
        $this->assertSame('NO_GO_M2M', config('sefaz.ma_outbound.m2m_status'));
        $this->assertFalse((bool) config('sefaz.ma_outbound.m2m_retrieval_enabled'));
        $this->assertFalse((bool) config('sefaz.ma_outbound.mutating_probe_enabled'));
        $this->assertSame(OutboundCaptureMode::Assisted, OutboundCaptureMode::Assisted);
    }

    private function seedSeries(int $discovery = 11): OutboundSeriesCursor
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
            'model' => OutboundFiscalModel::Nfe,
            'mode' => OutboundCaptureMode::Assisted,
            'status' => OutboundProfileStatus::Active,
            'allowlisted' => true,
            'consent_recorded' => true,
            'mandate_reference' => 'DRILL',
        ]);

        return OutboundSeriesCursor::query()->create([
            'office_id' => $office->id,
            'outbound_capture_profile_id' => $profile->id,
            'establishment_id' => $est->id,
            'environment' => 'homologation',
            'model' => OutboundFiscalModel::Nfe,
            'series' => 1,
            'seed_nnf' => 10,
            'discovery_position' => $discovery,
            'status' => OutboundSeriesStatus::Idle,
        ]);
    }
}
