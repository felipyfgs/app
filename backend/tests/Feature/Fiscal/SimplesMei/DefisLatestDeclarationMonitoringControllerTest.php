<?php

namespace Tests\Feature\Fiscal\SimplesMei;

use App\Enums\OfficeRole;
use App\Models\Client;
use App\Models\Office;
use App\Models\User;
use App\Support\CurrentOffice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DefisLatestDeclarationMonitoringControllerTest extends TestCase
{
    use RefreshDatabase;

    private Office $office;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        config(['features.global_enabled' => true, 'features.kill_switch' => false, 'features.modules.simples_mei.enabled' => true, 'features.modules.simples_mei.allow_all_offices' => true, 'fiscal_monitoring.enabled' => true]);
        $this->office = Office::factory()->create();
        $this->client = Client::factory()->forOffice($this->office)->create();
        $user = User::factory()->forOffice($this->office, OfficeRole::Admin)->withTwoFactorConfirmed()->create();
        $this->actingAs($user);
        app(CurrentOffice::class)->clear();
        app(CurrentOffice::class)->resolve($user);
    }

    public function test_requires_year_and_explicit_confirmation_before_queuing(): void
    {
        Queue::fake();
        $url = "/api/v1/fiscal/simples-mei/defis/latest-declaration/clients/{$this->client->id}/consult";
        $this->postJson($url, ['confirmed' => true])->assertStatus(422);
        $this->postJson($url, ['confirmed' => true, 'calendar_year' => 2025, 'office_id' => $this->office->id])
            ->assertStatus(422)->assertJsonPath('code', 'CLIENT_OFFICE_ID_REJECTED');
        $this->postJson($url, ['confirmed' => true, 'calendar_year' => 2025])
            ->assertCreated()
            ->assertJsonPath('data.client_id', $this->client->id)
            ->assertJsonPath('data.operation_code', 'CONSULTAR_ULTIMA_DECLARACAO_RECIBO');
    }

    public function test_history_is_tenant_scoped_and_local_only(): void
    {
        $foreign = Client::factory()->forOffice(Office::factory()->create())->create();
        $this->getJson("/api/v1/fiscal/simples-mei/defis/latest-declaration/clients/{$this->client->id}/history")
            ->assertOk()->assertJsonPath('data.documents', [])->assertJsonPath('data.provenance.serpro_called', false);
        $this->getJson("/api/v1/fiscal/simples-mei/defis/latest-declaration/clients/{$foreign->id}/history")->assertNotFound();
    }
}
