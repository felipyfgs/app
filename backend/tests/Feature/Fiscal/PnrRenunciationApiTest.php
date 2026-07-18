<?php

namespace Tests\Feature\Fiscal;

use App\Enums\FiscalSourceProvenance;
use App\Enums\OfficeRole;
use App\Models\Client;
use App\Models\FiscalPnrRenunciation;
use App\Models\Office;
use App\Models\User;
use App\Support\CurrentOffice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PnrRenunciationApiTest extends TestCase
{
    use RefreshDatabase;

    private Office $office;

    private Client $client;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->office = Office::factory()->create();
        $this->client = Client::factory()->forOffice($this->office)->create();
        $this->admin = User::factory()->forOffice($this->office, OfficeRole::Admin)->create();
    }

    public function test_lista_projecoes_do_cliente_atual_sem_expor_chaves_de_cofre(): void
    {
        FiscalPnrRenunciation::query()->create([
            'office_id' => $this->office->id,
            'client_id' => $this->client->id,
            'contributor_cnpj' => '11222333000181',
            'renunciation_id' => 42,
            'status' => 'RENOUNCED',
            'source_provenance' => FiscalSourceProvenance::SerproTrial->value,
            'receipt_vault_object_id' => '01JPNRRENUNCIATIONRECEIPT1',
            'receipt_sha256' => str_repeat('a', 64),
            'receipt_mime_type' => 'application/pdf',
            'receipt_byte_size' => 10,
            'refreshed_at' => now(),
        ]);

        $this->actingAs($this->admin);
        app(CurrentOffice::class)->resolve($this->admin);

        $response = $this->getJson("/api/v1/fiscal/clients/{$this->client->id}/pnr-renunciations");
        $response->assertOk()
            ->assertJsonPath('data.client_id', $this->client->id)
            ->assertJsonPath('data.renunciations.0.renunciation_id', 42)
            ->assertJsonPath('data.renunciations.0.source_provenance', 'SERPRO_TRIAL');
        $body = (string) $response->getContent();
        $this->assertStringNotContainsString('receipt_vault_object_id', $body);
        $this->assertStringNotContainsString(str_repeat('a', 64), $body);
        $this->assertStringNotContainsString('11222333000181', $body);
    }

    public function test_rejeita_office_id_e_cliente_de_outro_escritorio_antes_da_consulta(): void
    {
        $otherOffice = Office::factory()->create();
        $otherClient = Client::factory()->forOffice($otherOffice)->create();

        $this->actingAs($this->admin);
        app(CurrentOffice::class)->resolve($this->admin);

        $this->postJson("/api/v1/fiscal/clients/{$this->client->id}/pnr-renunciations/history", [
            'office_id' => $otherOffice->id,
        ])->assertStatus(422)->assertJsonPath('code', 'CLIENT_OFFICE_ID_REJECTED');

        $this->postJson("/api/v1/fiscal/clients/{$this->client->id}/pnr-renunciations/history", [
            'filters' => ['office_id' => $otherOffice->id],
        ])->assertStatus(422)->assertJsonPath('code', 'CLIENT_OFFICE_ID_REJECTED');

        $this->postJson("/api/v1/fiscal/clients/{$otherClient->id}/pnr-renunciations/status", [
            'id_solicitacao' => 'request-42',
        ])->assertNotFound();
    }

    public function test_viewer_pode_ler_mas_nao_dispara_consulta_manual(): void
    {
        $viewer = User::factory()->forOffice($this->office, OfficeRole::Viewer)->create();
        $this->actingAs($viewer);
        app(CurrentOffice::class)->resolve($viewer);

        $this->getJson("/api/v1/fiscal/clients/{$this->client->id}/pnr-renunciations")
            ->assertOk()
            ->assertJsonCount(0, 'data.renunciations');

        $this->postJson("/api/v1/fiscal/clients/{$this->client->id}/pnr-renunciations/status", [
            'id_solicitacao' => 'request-42',
        ])->assertForbidden();
    }
}
