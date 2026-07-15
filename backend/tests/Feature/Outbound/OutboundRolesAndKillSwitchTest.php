<?php

namespace Tests\Feature\Outbound;

use App\Enums\OfficeRole;
use App\Enums\OutboundCaptureMode;
use App\Enums\OutboundFiscalModel;
use App\Enums\OutboundProfileStatus;
use App\Enums\OutboundSeriesStatus;
use App\Models\Client;
use App\Models\Establishment;
use App\Models\Office;
use App\Models\OutboundCaptureProfile;
use App\Models\OutboundSeriesCursor;
use App\Models\User;
use App\Support\CurrentOffice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OutboundRolesAndKillSwitchTest extends TestCase
{
    use RefreshDatabase;

    public function test_viewer_somente_leitura_kill_switch_status(): void
    {
        $office = Office::factory()->create();
        $viewer = User::factory()->forOffice($office, OfficeRole::Viewer)->withTwoFactorConfirmed()->create();
        $this->actingAs($viewer);
        app(CurrentOffice::class)->resolve($viewer);

        $this->getJson('/api/v1/outbound/kill-switch')
            ->assertOk()
            ->assertJsonPath('data.m2m_status', 'NO_GO_M2M')
            ->assertJsonPath('data.enabled', false);

        $this->postJson('/api/v1/outbound/kill-switch', [
            'active' => true,
            'reason' => 'teste indevido',
        ])->assertForbidden();
    }

    public function test_admin_ativa_kill_switch_global(): void
    {
        $office = Office::factory()->create();
        $admin = User::factory()->forOffice($office, OfficeRole::Admin)->withTwoFactorConfirmed()->create();
        $this->actingAs($admin);
        app(CurrentOffice::class)->resolve($admin);

        $this->postJson('/api/v1/outbound/kill-switch', [
            'active' => true,
            'reason' => 'drill operacional 656',
        ])->assertOk()->assertJsonPath('data.global_active', true);

        $this->getJson('/api/v1/outbound/kill-switch')
            ->assertOk()
            ->assertJsonPath('data.global_active', true);

        $this->postJson('/api/v1/outbound/kill-switch', [
            'active' => false,
            'reason' => 'fim do drill',
        ])->assertOk()->assertJsonPath('data.global_active', false);
    }

    public function test_admin_ativa_perfil_com_mandato(): void
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
            'model' => OutboundFiscalModel::Nfe,
            'mode' => OutboundCaptureMode::Assisted,
            'status' => OutboundProfileStatus::SeedReady,
        ]);

        $this->actingAs($admin);
        app(CurrentOffice::class)->resolve($admin);

        $this->postJson('/api/v1/outbound/profiles/'.$profile->id.'/activate', [
            'mandate_reference' => 'CONTRATO-2026-001',
            'allowlisted' => true,
        ])->assertOk()
            ->assertJsonPath('data.status', 'ACTIVE')
            ->assertJsonPath('data.allowlisted', true);
    }

    public function test_reset_exige_motivo_e_confirmacao(): void
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
            'model' => OutboundFiscalModel::Nfe,
            'mode' => OutboundCaptureMode::Assisted,
            'status' => OutboundProfileStatus::Active,
        ]);
        $series = OutboundSeriesCursor::query()->create([
            'office_id' => $office->id,
            'outbound_capture_profile_id' => $profile->id,
            'establishment_id' => $est->id,
            'environment' => 'homologation',
            'model' => OutboundFiscalModel::Nfe,
            'series' => 1,
            'seed_nnf' => 10,
            'discovery_position' => 50,
            'status' => OutboundSeriesStatus::Idle,
        ]);

        $this->actingAs($admin);
        app(CurrentOffice::class)->resolve($admin);

        $this->postJson('/api/v1/outbound/series/'.$series->id.'/reset', [
            'reason' => 'reprocessar lacunas do mês',
            'discovery_position' => 20,
            'confirm' => true,
        ])->assertOk()->assertJsonPath('data.discovery_position', 20);
    }
}
