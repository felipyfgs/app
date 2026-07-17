<?php

namespace Tests\Feature\Fiscal\SimplesMei;

use App\Enums\OfficeRole;
use App\Models\Client;
use App\Models\ClientCommunicationDispatch;
use App\Models\ClientCommunicationEvent;
use App\Models\ClientCommunicationPreference;
use App\Models\ClientContact;
use App\Models\Office;
use App\Models\User;
use App\Support\CurrentOffice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PgdasdCommunicationTemplateTest extends TestCase
{
    use RefreshDatabase;

    private const BASE = '/api/v1/fiscal/simples-mei/pgdasd';

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
        $this->admin = User::factory()
            ->forOffice($this->office, OfficeRole::Admin)
            ->withTwoFactorConfirmed()
            ->create();
        $this->actAs($this->admin);
    }

    #[Test]
    public function preview_e_tracking_get_sao_read_only_mascarados_e_sem_envio(): void
    {
        Queue::fake();
        Mail::fake();

        ClientContact::factory()->forClient($this->client)->create([
            'name' => 'Contato Fiscal',
            'email' => 'contador@example.com',
            'phone' => '11987651234',
            'is_whatsapp' => true,
            'receives_alerts' => true,
            'is_active' => true,
        ]);

        $this->assertDatabaseCount('client_communication_preferences', 0);
        $this->assertDatabaseCount('client_communication_dispatches', 0);
        $this->assertDatabaseCount('client_communication_events', 0);

        $preview = $this->getJson(self::BASE."/clients/{$this->client->id}/communication-preview");

        $preview
            ->assertOk()
            ->assertJsonPath('data.can_send', false)
            ->assertJsonPath('data.execution_mode', 'TEMPLATE_ONLY')
            ->assertJsonPath('data.automatic_effective', false)
            ->assertJsonPath('data.preferences.lock_version', 0)
            ->assertJsonPath('data.preferences.tracking_status', 'NOT_CONFIGURED')
            ->assertJsonPath('data.channels.0.channel', 'EMAIL')
            ->assertJsonPath('data.channels.0.recipients.0.masked', 'c***@example.com')
            ->assertJsonPath('data.channels.1.channel', 'WHATSAPP')
            ->assertJsonPath('data.channels.1.recipients.0.masked', '***1234');

        $json = $preview->getContent() ?: '';
        $this->assertStringNotContainsString('contador@example.com', $json);
        $this->assertStringNotContainsString('11987651234', $json);
        $this->assertStringNotContainsString('recipient_hash', $json);

        $this->getJson(self::BASE."/clients/{$this->client->id}/communications")
            ->assertOk()
            ->assertJsonPath('data.status', 'NOT_CONFIGURED');

        $this->assertDatabaseCount('client_communication_preferences', 0);
        $this->assertDatabaseCount('client_communication_dispatches', 0);
        $this->assertDatabaseCount('client_communication_events', 0);
        Queue::assertNothingPushed();
        Mail::assertNothingSent();
    }

    #[Test]
    public function admin_configura_preferencia_valida_sem_criar_dispatch_evento_job_ou_email(): void
    {
        Queue::fake();
        Mail::fake();
        ClientContact::factory()->forClient($this->client)->create([
            'email' => 'fiscal@example.com',
            'receives_alerts' => true,
            'is_active' => true,
        ]);

        $response = $this->patchJson(
            self::BASE."/clients/{$this->client->id}/communication-preference",
            [
                'automatic_requested' => true,
                'email_enabled' => true,
                'whatsapp_enabled' => false,
                'lock_version' => 0,
            ],
        );

        $response
            ->assertOk()
            ->assertJsonPath('data.automatic_requested', true)
            ->assertJsonPath('data.automatic_effective', false)
            ->assertJsonPath('data.execution_mode', 'TEMPLATE_ONLY')
            ->assertJsonPath('data.lock_version', 1)
            ->assertJsonPath('data.tracking_status', 'NO_HISTORY');

        $this->assertDatabaseHas('client_communication_preferences', [
            'office_id' => $this->office->id,
            'client_id' => $this->client->id,
            'module_key' => 'simples_mei',
            'submodule_key' => 'pgdasd',
            'automatic_requested' => true,
            'email_enabled' => true,
            'whatsapp_enabled' => false,
            'lock_version' => 1,
        ]);
        $this->assertDatabaseCount('client_communication_dispatches', 0);
        $this->assertDatabaseCount('client_communication_events', 0);
        Queue::assertNothingPushed();
        Mail::assertNothingSent();
    }

    #[Test]
    public function ativacao_sem_destinatario_elegivel_e_rejeitada_sem_persistir(): void
    {
        ClientContact::factory()->forClient($this->client)->create([
            'email' => 'publico@example.com',
            'phone' => '11999990000',
            'is_whatsapp' => true,
            'receives_alerts' => false,
            'is_active' => true,
        ]);

        $this->patchJson(
            self::BASE."/clients/{$this->client->id}/communication-preference",
            [
                'automatic_requested' => true,
                'email_enabled' => true,
                'whatsapp_enabled' => true,
                'lock_version' => 0,
            ],
        )->assertUnprocessable();

        $this->assertDatabaseCount('client_communication_preferences', 0);
        $this->assertDatabaseCount('client_communication_dispatches', 0);
    }

    #[Test]
    public function optimistic_lock_rejeita_versao_obsoleta_sem_sobrescrever(): void
    {
        $url = self::BASE."/clients/{$this->client->id}/communication-preference";
        $this->patchJson($url, [
            'automatic_requested' => false,
            'email_enabled' => true,
            'whatsapp_enabled' => false,
            'lock_version' => 0,
        ])->assertOk()->assertJsonPath('data.lock_version', 1);

        $this->patchJson($url, [
            'automatic_requested' => false,
            'email_enabled' => false,
            'whatsapp_enabled' => true,
            'lock_version' => 0,
        ])
            ->assertStatus(409)
            ->assertJsonPath('code', 'OPTIMISTIC_LOCK_CONFLICT');

        $this->assertDatabaseHas('client_communication_preferences', [
            'client_id' => $this->client->id,
            'email_enabled' => true,
            'whatsapp_enabled' => false,
            'lock_version' => 1,
        ]);
    }

    #[Test]
    public function viewer_nao_altera_e_operator_pode_alterar_preferencia(): void
    {
        $viewer = User::factory()
            ->forOffice($this->office, OfficeRole::Viewer)
            ->withTwoFactorConfirmed()
            ->create();
        $this->actAs($viewer);

        $url = self::BASE."/clients/{$this->client->id}/communication-preference";
        $payload = [
            'automatic_requested' => false,
            'email_enabled' => false,
            'whatsapp_enabled' => false,
            'lock_version' => 0,
        ];
        $this->patchJson($url, $payload)->assertForbidden();
        $this->assertDatabaseCount('client_communication_preferences', 0);

        $operator = User::factory()
            ->forOffice($this->office, OfficeRole::Operator)
            ->withTwoFactorConfirmed()
            ->create();
        $this->actAs($operator);
        $this->patchJson($url, $payload)
            ->assertOk()
            ->assertJsonPath('data.lock_version', 1);
    }

    #[Test]
    public function lote_invalido_e_atomico_e_nao_altera_nenhum_cliente(): void
    {
        $eligible = Client::factory()->forOffice($this->office)->create();
        $ineligible = Client::factory()->forOffice($this->office)->create();
        ClientContact::factory()->forClient($eligible)->create([
            'email' => 'ok@example.com',
            'receives_alerts' => true,
            'is_active' => true,
        ]);

        foreach ([$eligible, $ineligible] as $client) {
            $this->patchJson(self::BASE."/clients/{$client->id}/communication-preference", [
                'automatic_requested' => false,
                'email_enabled' => true,
                'whatsapp_enabled' => false,
                'lock_version' => 0,
            ])->assertOk();
        }

        $this->patchJson(self::BASE.'/communication-preferences/bulk', [
            'client_ids' => [$eligible->id, $ineligible->id],
            'automatic_requested' => true,
        ])->assertUnprocessable();

        foreach ([$eligible, $ineligible] as $client) {
            $this->assertDatabaseHas('client_communication_preferences', [
                'client_id' => $client->id,
                'automatic_requested' => false,
                'lock_version' => 1,
            ]);
        }
    }

    #[Test]
    public function lote_valido_altera_apenas_switch_geral_e_incrementa_lock(): void
    {
        $second = Client::factory()->forOffice($this->office)->create();
        foreach ([$this->client, $second] as $client) {
            ClientContact::factory()->forClient($client)->create([
                'email' => "fiscal{$client->id}@example.com",
                'receives_alerts' => true,
                'is_active' => true,
            ]);
            $this->patchJson(self::BASE."/clients/{$client->id}/communication-preference", [
                'automatic_requested' => false,
                'email_enabled' => true,
                'whatsapp_enabled' => false,
                'lock_version' => 0,
            ])->assertOk();
        }

        $this->patchJson(self::BASE.'/communication-preferences/bulk', [
            'client_ids' => [$this->client->id, $second->id],
            'automatic_requested' => true,
        ])
            ->assertOk()
            ->assertJsonPath('updated_count', 2)
            ->assertJsonCount(2, 'data');

        foreach ([$this->client, $second] as $client) {
            $this->assertDatabaseHas('client_communication_preferences', [
                'client_id' => $client->id,
                'automatic_requested' => true,
                'email_enabled' => true,
                'whatsapp_enabled' => false,
                'lock_version' => 2,
            ]);
        }
    }

    #[Test]
    public function recursos_de_outro_escritorio_nao_sao_revelados_e_office_id_do_body_nao_troca_tenant(): void
    {
        $otherOffice = Office::factory()->create();
        $otherClient = Client::factory()->forOffice($otherOffice)->create();

        $this->getJson(self::BASE."/clients/{$otherClient->id}/communication-preview")
            ->assertNotFound();
        $this->patchJson(self::BASE."/clients/{$otherClient->id}/communication-preference", [
            'automatic_requested' => false,
            'email_enabled' => false,
            'whatsapp_enabled' => false,
            'lock_version' => 0,
        ])->assertNotFound();

        $this->patchJson(self::BASE."/clients/{$this->client->id}/communication-preference", [
            'office_id' => $otherOffice->id,
            'automatic_requested' => false,
            'email_enabled' => false,
            'whatsapp_enabled' => false,
            'lock_version' => 0,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'CLIENT_OFFICE_ID_REJECTED');

        $this->assertDatabaseCount('client_communication_preferences', 0);
        $this->assertDatabaseMissing('client_communication_preferences', [
            'office_id' => $otherOffice->id,
            'client_id' => $this->client->id,
        ]);
    }

    #[Test]
    public function abrir_rastreio_preserva_status_e_eventos_existentes(): void
    {
        ClientContact::factory()->forClient($this->client)->create([
            'email' => 'fiscal@example.com',
            'receives_alerts' => true,
            'is_active' => true,
        ]);
        $preference = ClientCommunicationPreference::query()->create([
            'office_id' => $this->office->id,
            'client_id' => $this->client->id,
            'module_key' => 'simples_mei',
            'submodule_key' => 'pgdasd',
            'automatic_requested' => true,
            'email_enabled' => true,
            'whatsapp_enabled' => false,
            'lock_version' => 1,
            'updated_by_user_id' => $this->admin->id,
        ]);
        $dispatch = ClientCommunicationDispatch::query()->create([
            'office_id' => $this->office->id,
            'client_id' => $this->client->id,
            'preference_id' => $preference->id,
            'module_key' => 'simples_mei',
            'submodule_key' => 'pgdasd',
            'period_key' => '2026-06',
            'channel' => 'EMAIL',
            'status' => 'DELIVERED',
            'recipient_masked' => 'f***@example.com',
            'recipient_hash' => hash('sha256', 'fiscal@example.com'),
            'idempotency_key' => hash('sha256', 'test-dispatch'),
            'delivered_at' => now()->subMinute(),
        ]);
        ClientCommunicationEvent::query()->create([
            'office_id' => $this->office->id,
            'dispatch_id' => $dispatch->id,
            'status' => 'DELIVERED',
            'occurred_at' => now()->subMinute(),
            'source' => 'TEST',
        ]);

        $beforeEvents = ClientCommunicationEvent::query()->count();
        $this->getJson(self::BASE."/clients/{$this->client->id}/communications")
            ->assertOk()
            ->assertJsonPath('data.status', 'DELIVERED')
            ->assertJsonPath('data.channels.0.dispatches.0.status', 'DELIVERED')
            ->assertJsonPath('data.channels.0.dispatches.0.recipient_masked', 'f***@example.com');

        $this->assertSame('DELIVERED', $dispatch->fresh()->status->value);
        $this->assertSame($beforeEvents, ClientCommunicationEvent::query()->count());
    }

    private function actAs(User $user): void
    {
        $this->actingAs($user);
        app(CurrentOffice::class)->clear();
        app(CurrentOffice::class)->resolve($user);
    }
}
