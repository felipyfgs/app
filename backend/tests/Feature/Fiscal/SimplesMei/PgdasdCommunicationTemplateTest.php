<?php

namespace Tests\Feature\Fiscal\SimplesMei;

use App\Enums\OfficeRole;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Office;
use App\Models\User;
use App\Support\CurrentOffice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PgdasdCommunicationTemplateTest extends TestCase
{
    use RefreshDatabase;

    private Office $office;

    private User $admin;

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
        $this->admin = User::factory()->forOffice($this->office, OfficeRole::Admin)->withTwoFactorConfirmed()->create();
    }

    #[Test]
    public function preview_never_allows_send_and_masks_recipients(): void
    {
        ClientContact::factory()->create([
            'office_id' => $this->office->id,
            'client_id' => $this->client->id,
            'email' => 'contador@example.com',
            'receives_alerts' => true,
            'is_active' => true,
        ]);

        $this->actingAs($this->admin);
        app(CurrentOffice::class)->resolve($this->admin);

        $response = $this->getJson("/api/v1/fiscal/simples-mei/pgdasd/clients/{$this->client->id}/communication-preview");

        $response->assertOk();
        $response->assertJsonPath('data.can_send', false);
        $response->assertJsonPath('data.execution_mode', 'TEMPLATE_ONLY');
        $response->assertJsonPath('data.automatic_effective', false);
        $json = $response->getContent() ?: '';
        $this->assertStringNotContainsString('contador@example.com', $json);
    }

    #[Test]
    public function tracking_without_history_is_no_history(): void
    {
        $this->actingAs($this->admin);
        app(CurrentOffice::class)->resolve($this->admin);

        $response = $this->getJson("/api/v1/fiscal/simples-mei/pgdasd/clients/{$this->client->id}/communications");

        $response->assertOk();
        // Sem dispatches: NOT_CONFIGURED ou NO_HISTORY (ambos válidos na spec)
        $status = $response->json('data.status');
        $this->assertContains($status, ['NO_HISTORY', 'NOT_CONFIGURED']);
    }

    #[Test]
    public function viewer_cannot_update_preferences(): void
    {
        $viewer = User::factory()->forOffice($this->office, OfficeRole::Viewer)->withTwoFactorConfirmed()->create();
        $this->actingAs($viewer);
        app(CurrentOffice::class)->clear();
        app(CurrentOffice::class)->resolve($viewer);

        $response = $this->patchJson("/api/v1/fiscal/simples-mei/pgdasd/clients/{$this->client->id}/communication-preference", [
            'automatic_requested' => true,
            'email_enabled' => true,
            'whatsapp_enabled' => false,
            'lock_version' => 1,
        ]);

        $response->assertForbidden();
    }

    #[Test]
    public function history_is_tenant_scoped(): void
    {
        $other = Office::factory()->create();
        $otherClient = Client::factory()->forOffice($other)->create();

        $this->actingAs($this->admin);
        app(CurrentOffice::class)->resolve($this->admin);

        $response = $this->getJson("/api/v1/fiscal/simples-mei/pgdasd/clients/{$otherClient->id}/history");

        $response->assertNotFound();
    }

    #[Test]
    public function history_returns_spa_contract_for_office_client(): void
    {
        $this->actingAs($this->admin);
        app(CurrentOffice::class)->resolve($this->admin);

        $response = $this->getJson("/api/v1/fiscal/simples-mei/pgdasd/clients/{$this->client->id}/history");

        $response->assertOk();
        $response->assertJsonPath('data.client.id', $this->client->id);
        $response->assertJsonPath('data.provenance.serpro_called', false);
        $response->assertJsonStructure([
            'data' => [
                'client',
                'expected_period_key',
                'declaration_state',
                'periods',
                'history',
                'provenance',
            ],
        ]);
    }
}
