<?php

namespace Tests\Feature\Fiscal\SimplesMei;

use App\Enums\OfficeRole;
use App\Models\Client;
use App\Models\Office;
use App\Models\User;
use App\Services\Fiscal\SimplesMei\CcmeiCertificateProjector;
use App\Services\Fiscal\SimplesMei\CcmeiRegistrationStatusProjector;
use App\Support\CurrentOffice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CcmeiMonitoringControllerTest extends TestCase
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
        $user = User::factory()
            ->forOffice($this->office, OfficeRole::Admin)
            ->withTwoFactorConfirmed()
            ->create();
        $this->actingAs($user);
        app(CurrentOffice::class)->clear();
        app(CurrentOffice::class)->resolve($user);
    }

    #[Test]
    public function returns_only_sanitized_local_history_for_current_office_client(): void
    {
        app(CcmeiCertificateProjector::class)->project(
            $this->office,
            $this->client,
            ['status' => 'ATIVA', 'situation' => 'UP_TO_DATE'],
            null,
            'SIMULATED',
        );

        $this->getJson("/api/v1/fiscal/simples-mei/ccmei/clients/{$this->client->id}/history")
            ->assertOk()
            ->assertJsonPath('data.client_id', $this->client->id)
            ->assertJsonPath('data.current.status', 'ATIVA')
            ->assertJsonPath('data.provenance.serpro_called', false)
            ->assertJsonMissingPath('data.current.cnpj')
            ->assertJsonMissingPath('data.current.cpf')
            ->assertJsonMissingPath('data.current.qrcode');
    }

    #[Test]
    public function refuses_foreign_client_and_client_office_id(): void
    {
        $foreign = Client::factory()->forOffice(Office::factory()->create())->create();

        $this->getJson("/api/v1/fiscal/simples-mei/ccmei/clients/{$foreign->id}/history")
            ->assertNotFound()
            ->assertJsonPath('code', 'CLIENT_NOT_FOUND');
        $this->postJson("/api/v1/fiscal/simples-mei/ccmei/clients/{$this->client->id}/consult", [
            'confirmed' => true,
            'office_id' => $this->office->id,
        ])->assertStatus(422)->assertJsonPath('code', 'CLIENT_OFFICE_ID_REJECTED');
    }

    #[Test]
    public function enqueues_explicit_confirmed_consult_without_client_tax_identifier(): void
    {
        Queue::fake();

        $this->postJson("/api/v1/fiscal/simples-mei/ccmei/clients/{$this->client->id}/consult", [
            'confirmed' => true,
        ])->assertCreated()
            ->assertJsonPath('data.client_id', $this->client->id)
            ->assertJsonPath('data.service_code', 'CCMEI')
            ->assertJsonPath('data.operation_code', 'MONITOR')
            ->assertJsonMissingPath('data.cnpj');
    }

    #[Test]
    public function exposes_only_sanitized_registration_status_history_and_enqueues_123(): void
    {
        app(CcmeiRegistrationStatusProjector::class)->project($this->office, $this->client, [
            'status' => 'ATIVA', 'enquadrado_mei' => true, 'situation' => 'UP_TO_DATE', 'count' => 1,
        ], null, 'SIMULATED');

        $this->getJson("/api/v1/fiscal/simples-mei/ccmei/registration-status/clients/{$this->client->id}/history")
            ->assertOk()->assertJsonPath('data.current.status', 'ATIVA')->assertJsonPath('data.current.enquadrado_mei', true)
            ->assertJsonMissingPath('data.current.cnpj')->assertJsonMissingPath('data.history.0.cnpj');

        Queue::fake();
        $this->postJson("/api/v1/fiscal/simples-mei/ccmei/registration-status/clients/{$this->client->id}/consult", ['confirmed' => true])
            ->assertCreated()->assertJsonPath('data.service_code', 'CCMEI')
            ->assertJsonPath('data.operation_code', 'CONSULTAR_SITUACAO_CADASTRAL')->assertJsonMissingPath('data.cnpj');
    }
}
