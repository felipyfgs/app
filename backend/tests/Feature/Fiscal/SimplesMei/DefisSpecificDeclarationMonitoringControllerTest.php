<?php

namespace Tests\Feature\Fiscal\SimplesMei;

use App\Enums\OfficeRole;
use App\Models\Client;
use App\Models\DefisDeclarationProjection;
use App\Models\Office;
use App\Models\User;
use App\Services\Fiscal\SimplesMei\DefisDeclarationReferenceStore;
use App\Support\CurrentOffice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DefisSpecificDeclarationMonitoringControllerTest extends TestCase
{
    use RefreshDatabase;

    private Office $office;

    private Client $client;

    private int $referenceId;

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

        $reference = app(DefisDeclarationReferenceStore::class)->store($this->office, $this->client, '900000002025001', null, 'SIMULATED');
        $this->referenceId = $reference->id;
        DefisDeclarationProjection::query()->create([
            'office_id' => $this->office->id,
            'client_id' => $this->client->id,
            'calendar_year' => 2025,
            'declaration_type' => '1',
            'last_observed_at' => now(),
            'defis_declaration_reference_id' => $reference->id,
            'source_provenance' => 'SIMULATED',
        ]);
    }

    public function test_requires_reference_and_explicit_confirmation_before_queuing(): void
    {
        Queue::fake();
        $url = "/api/v1/fiscal/simples-mei/defis/specific-declaration/clients/{$this->client->id}/consult";
        $this->postJson($url, ['confirmed' => true])->assertStatus(422);
        $this->postJson($url, ['confirmed' => true, 'reference_id' => $this->referenceId, 'office_id' => $this->office->id])
            ->assertStatus(422)->assertJsonPath('code', 'CLIENT_OFFICE_ID_REJECTED');
        $this->postJson($url, ['confirmed' => true, 'reference_id' => $this->referenceId])
            ->assertCreated()
            ->assertJsonPath('data.client_id', $this->client->id)
            ->assertJsonPath('data.operation_code', 'CONSULTAR_DECLARACAO_RECIBO');
    }

    public function test_history_is_tenant_scoped_local_and_does_not_expose_id_defis(): void
    {
        $foreign = Client::factory()->forOffice(Office::factory()->create())->create();
        $response = $this->getJson("/api/v1/fiscal/simples-mei/defis/specific-declaration/clients/{$this->client->id}/history?reference_id={$this->referenceId}");
        $response->assertOk()
            ->assertJsonPath('data.references.0.reference_id', $this->referenceId)
            ->assertJsonPath('data.documents', [])
            ->assertJsonPath('data.provenance.serpro_called', false);
        $this->assertStringNotContainsString('900000002025001', $response->getContent());
        $this->getJson("/api/v1/fiscal/simples-mei/defis/specific-declaration/clients/{$foreign->id}/history")->assertNotFound();
        $this->postJson("/api/v1/fiscal/simples-mei/defis/specific-declaration/clients/{$this->client->id}/consult", [
            'confirmed' => true,
            'reference_id' => 999999,
        ])->assertNotFound();
    }
}
