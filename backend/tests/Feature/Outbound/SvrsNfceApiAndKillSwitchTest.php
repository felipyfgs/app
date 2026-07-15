<?php

namespace Tests\Feature\Outbound;

use App\Enums\OfficeRole;
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
use Tests\TestCase;

class SvrsNfceApiAndKillSwitchTest extends TestCase
{
    use RefreshDatabase;

    public function test_viewer_consulta_summary_sem_segredos(): void
    {
        $office = Office::factory()->create();
        $viewer = User::factory()->forOffice($office, OfficeRole::Viewer)->withTwoFactorConfirmed()->create();
        $this->actingAs($viewer);
        app(CurrentOffice::class)->resolve($viewer);

        $res = $this->getJson('/api/v1/outbound/svrs-nfce/summary')
            ->assertOk()
            ->assertJsonPath('data.retrieval_enabled', false);

        $json = $res->json('data');
        $this->assertArrayNotHasKey('pfx', $json);
        $this->assertArrayNotHasKey('password', $json);
        $this->assertArrayNotHasKey('cookie', $json);
        $this->assertArrayNotHasKey('vault_object_id', $json);
    }

    public function test_viewer_nao_ativa_kill_switch(): void
    {
        $office = Office::factory()->create();
        $viewer = User::factory()->forOffice($office, OfficeRole::Viewer)->withTwoFactorConfirmed()->create();
        $this->actingAs($viewer);
        app(CurrentOffice::class)->resolve($viewer);

        $this->postJson('/api/v1/outbound/svrs-nfce/kill-switch', [
            'active' => true,
            'reason' => 'teste',
        ])->assertForbidden();
    }

    public function test_admin_kill_switch_preserva_estado(): void
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

        $this->actingAs($admin);
        app(CurrentOffice::class)->resolve($admin);

        $this->postJson('/api/v1/outbound/svrs-nfce/kill-switch', [
            'active' => true,
            'reason' => 'drill svrs',
        ])->assertOk()->assertJsonPath('data.active', true);

        $number->refresh();
        $this->assertSame(OutboundNumberStatus::XmlPending, $number->status);
        $this->assertSame(1, OutboundNumberState::withoutGlobalScopes()->count());
    }

    public function test_office_id_cliente_ignorado_no_enqueue(): void
    {
        $office = Office::factory()->create();
        $other = Office::factory()->create();
        $op = User::factory()->forOffice($office, OfficeRole::Operator)->withTwoFactorConfirmed()->create();
        $this->actingAs($op);
        app(CurrentOffice::class)->resolve($op);

        // number inexistente no office → 404; office_id injetado não muda o escopo
        $this->postJson('/api/v1/outbound/svrs-nfce/recoveries', [
            'number_state_id' => 999999,
            'office_id' => $other->id,
            'url' => 'https://evil.example/x',
            'host' => 'evil.example',
        ])->assertNotFound();
    }
}
