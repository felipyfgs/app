<?php

namespace Tests\Feature\Outbound;

use App\Enums\OutboundCaptureMode;
use App\Enums\OutboundFiscalModel;
use App\Enums\OutboundNumberStatus;
use App\Enums\OutboundProfileStatus;
use App\Enums\OutboundSeriesStatus;
use App\Models\Client;
use App\Models\Establishment;
use App\Models\Office;
use App\Models\OutboundCaptureProfile;
use App\Models\OutboundNumberState;
use App\Models\OutboundSeriesCursor;
use App\Models\User;
use App\Support\CurrentOffice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OutboundSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_tabelas_outbound_existem_sem_last_nsu(): void
    {
        $this->assertTrue(Schema::hasTable('outbound_capture_profiles'));
        $this->assertTrue(Schema::hasTable('outbound_series_cursors'));
        $this->assertTrue(Schema::hasTable('outbound_number_states'));
        $this->assertTrue(Schema::hasTable('ma_outbound_retrieval_requests'));
        $this->assertTrue(Schema::hasTable('outbound_capture_runs'));
        $this->assertTrue(Schema::hasTable('document_acquisitions'));
        $this->assertTrue(Schema::hasColumns('nfe_documents', ['purpose', 'acquisition_source']));

        $this->assertFalse(Schema::hasColumn('outbound_series_cursors', 'last_nsu'));
        $this->assertFalse(Schema::hasColumn('outbound_number_states', 'last_nsu'));
        $this->assertTrue(Schema::hasColumn('outbound_series_cursors', 'discovery_position'));
        $this->assertTrue(Schema::hasColumn('outbound_series_cursors', 'seed_nnf'));
    }

    public function test_unique_serie_e_isolamento_office(): void
    {
        $officeA = Office::factory()->create();
        $officeB = Office::factory()->create();
        $clientA = Client::factory()->forOffice($officeA)->create();
        $clientB = Client::factory()->forOffice($officeB)->create();
        $estA = Establishment::factory()->forClient($clientA)->create([
            'cnpj' => '12345678000190',
            'address_state' => 'MA',
        ]);
        $estB = Establishment::factory()->forClient($clientB)->create([
            'cnpj' => '12345678000190',
            'address_state' => 'MA',
        ]);

        $profileA = OutboundCaptureProfile::query()->create([
            'office_id' => $officeA->id,
            'client_id' => $clientA->id,
            'establishment_id' => $estA->id,
            'uf' => 'MA',
            'environment' => 'homologation',
            'model' => OutboundFiscalModel::Nfe,
            'mode' => OutboundCaptureMode::Assisted,
            'status' => OutboundProfileStatus::SeedReady,
        ]);

        $profileB = OutboundCaptureProfile::query()->create([
            'office_id' => $officeB->id,
            'client_id' => $clientB->id,
            'establishment_id' => $estB->id,
            'uf' => 'MA',
            'environment' => 'homologation',
            'model' => OutboundFiscalModel::Nfe,
            'mode' => OutboundCaptureMode::Assisted,
            'status' => OutboundProfileStatus::SeedReady,
        ]);

        OutboundSeriesCursor::query()->create([
            'office_id' => $officeA->id,
            'outbound_capture_profile_id' => $profileA->id,
            'establishment_id' => $estA->id,
            'environment' => 'homologation',
            'model' => OutboundFiscalModel::Nfe,
            'series' => 1,
            'seed_nnf' => 10,
            'discovery_position' => 11,
            'status' => OutboundSeriesStatus::SeedReady,
        ]);

        OutboundSeriesCursor::query()->create([
            'office_id' => $officeB->id,
            'outbound_capture_profile_id' => $profileB->id,
            'establishment_id' => $estB->id,
            'environment' => 'homologation',
            'model' => OutboundFiscalModel::Nfe,
            'series' => 1,
            'seed_nnf' => 10,
            'discovery_position' => 11,
            'status' => OutboundSeriesStatus::SeedReady,
        ]);

        $userA = User::factory()->forOffice($officeA)->withTwoFactorConfirmed()->create();
        $this->actingAs($userA);
        app(CurrentOffice::class)->resolve($userA);

        $this->assertSame(1, OutboundCaptureProfile::query()->count());
        $this->assertSame(1, OutboundSeriesCursor::query()->count());
        $this->assertSame('nNF', OutboundSeriesCursor::query()->first()->positionKind());

        // Sem escopo de office, ambos os registros existem (isolamento via BelongsToOffice)
        $this->assertSame(2, OutboundCaptureProfile::withoutGlobalScopes()->count());
    }

    public function test_unique_numero_por_perfil_serie_nnf(): void
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
            'model' => OutboundFiscalModel::Nfe,
            'mode' => OutboundCaptureMode::Assisted,
            'status' => OutboundProfileStatus::SeedReady,
        ]);
        $series = OutboundSeriesCursor::query()->create([
            'office_id' => $office->id,
            'outbound_capture_profile_id' => $profile->id,
            'establishment_id' => $est->id,
            'environment' => 'homologation',
            'model' => OutboundFiscalModel::Nfe,
            'series' => 1,
            'seed_nnf' => 1,
            'discovery_position' => 2,
            'status' => OutboundSeriesStatus::Idle,
        ]);

        OutboundNumberState::query()->create([
            'office_id' => $office->id,
            'outbound_capture_profile_id' => $profile->id,
            'outbound_series_cursor_id' => $series->id,
            'series' => 1,
            'nnf' => 5,
            'status' => OutboundNumberStatus::ConsultQueued,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        OutboundNumberState::query()->create([
            'office_id' => $office->id,
            'outbound_capture_profile_id' => $profile->id,
            'outbound_series_cursor_id' => $series->id,
            'series' => 1,
            'nnf' => 5,
            'status' => OutboundNumberStatus::GapPending,
        ]);
    }

    public function test_flags_default_off(): void
    {
        $this->assertFalse((bool) config('sefaz.ma_outbound.enabled'));
        $this->assertFalse((bool) config('sefaz.ma_outbound.protocol_query_enabled'));
        $this->assertFalse((bool) config('sefaz.ma_outbound.m2m_retrieval_enabled'));
        $this->assertFalse((bool) config('sefaz.ma_outbound.mutating_probe_enabled'));
        $this->assertSame('NO_GO_M2M', config('sefaz.ma_outbound.m2m_status'));
        $this->assertSame('capture-outbound-ma', config('sefaz.ma_outbound.queue'));
        $this->assertSame('SVAN', config('sefaz.ma_outbound.consulta_protocolo.55.authorizer'));
        $this->assertSame('SVRS', config('sefaz.ma_outbound.consulta_protocolo.65.authorizer'));
    }
}
