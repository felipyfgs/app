<?php

namespace Tests\Feature\Fiscal\SimplesMei;

use App\Enums\OfficeRole;
use App\Models\Client;
use App\Models\Office;
use App\Models\User;
use App\Services\Fiscal\SimplesMei\DefisDeclarationProjector;
use App\Support\CurrentOffice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DefisDeclarationsMonitoringControllerTest extends TestCase
{
    use RefreshDatabase;

    private Office $office;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'features.global_enabled' => true,
            'features.kill_switch' => false,
            'features.modules.simples_mei.enabled' => true,
            'features.modules.simples_mei.allow_all_offices' => true,
            'fiscal_monitoring.enabled' => true,
        ]);
        $this->office = Office::factory()->create();
        $this->client = Client::factory()->forOffice($this->office)->create();
        $user = User::factory()->forOffice($this->office, OfficeRole::Admin)->withTwoFactorConfirmed()->create();
        $this->actingAs($user);
        app(CurrentOffice::class)->clear();
        app(CurrentOffice::class)->resolve($user);
    }

    public function test_returns_only_sanitized_local_history(): void
    {
        app(DefisDeclarationProjector::class)->project(
            $this->office,
            $this->client,
            [['calendar_year' => 2025, 'type' => '2', 'transmitted_at' => null]],
            null,
            'SIMULATED',
        );

        $this->getJson("/api/v1/fiscal/simples-mei/defis/clients/{$this->client->id}/history")
            ->assertOk()
            ->assertJsonPath('data.client_id', $this->client->id)
            ->assertJsonPath('data.declarations.0.calendar_year', 2025)
            ->assertJsonPath('data.declarations.0.declaration_type', '2')
            ->assertJsonPath('data.provenance.serpro_called', false)
            ->assertJsonMissingPath('data.declarations.0.idDefis')
            ->assertJsonMissingPath('data.declarations.0.transmitted_at');
    }

    public function test_refuses_foreign_client_and_client_office_id(): void
    {
        $foreign = Client::factory()->forOffice(Office::factory()->create())->create();

        $this->getJson("/api/v1/fiscal/simples-mei/defis/clients/{$foreign->id}/history")
            ->assertNotFound()
            ->assertJsonPath('code', 'CLIENT_NOT_FOUND');
        $this->postJson("/api/v1/fiscal/simples-mei/defis/clients/{$this->client->id}/consult", [
            'confirmed' => true,
            'office_id' => $this->office->id,
        ])->assertStatus(422)->assertJsonPath('code', 'CLIENT_OFFICE_ID_REJECTED');
    }

    public function test_enqueues_only_explicit_confirmed_consult(): void
    {
        Queue::fake();

        $this->postJson("/api/v1/fiscal/simples-mei/defis/clients/{$this->client->id}/consult", [])
            ->assertStatus(422);
        $this->postJson("/api/v1/fiscal/simples-mei/defis/clients/{$this->client->id}/consult", [
            'confirmed' => true,
        ])->assertCreated()
            ->assertJsonPath('data.client_id', $this->client->id)
            ->assertJsonPath('data.service_code', 'DEFIS')
            ->assertJsonPath('data.operation_code', 'CONSULTAR')
            ->assertJsonMissingPath('data.cnpj');
    }
}
