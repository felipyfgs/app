<?php

namespace Tests\Feature\Fiscal\ManualConsult;

use App\Enums\AuthorIdentityType;
use App\Enums\OfficeRole;
use App\Enums\SerproAuthorizationStatus;
use App\Enums\SerproContractStatus;
use App\Enums\SerproEnvironment;
use App\Enums\TaxProxyPowerSource;
use App\Enums\TaxProxyPowerStatus;
use App\Models\Client;
use App\Models\Establishment;
use App\Models\Office;
use App\Models\OfficeSerproAuthorization;
use App\Models\SerproContract;
use App\Models\TaxProxyPower;
use App\Models\User;
use App\Services\Integra\TaxProxyPowerService;
use App\Support\CurrentOffice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ManualConsultExecutionTest extends TestCase
{
    use RefreshDatabase;

    private Office $office;

    private Office $otherOffice;

    private Client $client;

    private Client $otherClient;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'features.global_enabled' => true,
            'features.kill_switch' => false,
            'features.modules.simples_mei.enabled' => true,
            'features.modules.simples_mei.allow_all_offices' => true,
            'features.modules.sitfis.enabled' => true,
            'features.modules.sitfis.allow_all_offices' => true,
            'features.modules.guias.enabled' => true,
            'features.modules.guias.allow_all_offices' => true,
            'fiscal_monitoring.enabled' => true,
            'serpro.kill_switch' => false,
            'serpro.default_environment' => 'TRIAL',
            'serpro.trial.use_fake_clients' => true,
            'serpro.capabilities.simples_mei' => 'real',
            'serpro.capabilities.sitfis' => 'real',
            'serpro.capabilities.guides' => 'real',
        ]);

        $this->office = Office::factory()->create();
        $this->otherOffice = Office::factory()->create();
        $this->client = Client::factory()->forOffice($this->office)->create();
        $this->otherClient = Client::factory()->forOffice($this->otherOffice)->create();
        Establishment::factory()->create([
            'office_id' => $this->office->id,
            'client_id' => $this->client->id,
            'cnpj' => '11222333000181',
            'is_matrix' => true,
        ]);
        $this->user = User::factory()->forOffice($this->office, OfficeRole::Admin)->withTwoFactorConfirmed()->create();
        $this->actingAs($this->user);
        app(CurrentOffice::class)->clear();
        app(CurrentOffice::class)->resolve($this->user);
        $this->seedIntegraChain();
    }

    #[Test]
    public function it_requires_confirmation_before_execution(): void
    {
        $this->postJson('/api/v1/fiscal/manual-consults', [
            'action_id' => 'simples_mei_ccmei:ccmei.dadosccmei',
            'client_id' => $this->client->id,
            'confirmed' => false,
        ])->assertStatus(422);
    }

    #[Test]
    public function it_rejects_cross_tenant_client(): void
    {
        $this->postJson('/api/v1/fiscal/manual-consults', [
            'action_id' => 'simples_mei_ccmei:ccmei.dadosccmei',
            'client_id' => $this->otherClient->id,
            'confirmed' => true,
        ])->assertStatus(404)->assertJsonPath('code', 'CLIENT_NOT_FOUND');
    }

    #[Test]
    public function it_rejects_office_id_from_body(): void
    {
        $this->postJson('/api/v1/fiscal/manual-consults', [
            'action_id' => 'simples_mei_ccmei:ccmei.dadosccmei',
            'client_id' => $this->client->id,
            'confirmed' => true,
            'office_id' => $this->office->id,
        ])->assertStatus(422)->assertJsonPath('code', 'CLIENT_OFFICE_ID_REJECTED');
    }

    #[Test]
    public function it_rejects_when_capability_disabled(): void
    {
        config(['serpro.capabilities.simples_mei' => 'disabled']);

        $this->postJson('/api/v1/fiscal/manual-consults', [
            'action_id' => 'simples_mei_ccmei:ccmei.dadosccmei',
            'client_id' => $this->client->id,
            'confirmed' => true,
        ])->assertStatus(422)->assertJsonPath('code', 'capability_off');
    }

    #[Test]
    public function it_rejects_adapter_missing_actions(): void
    {
        // comprovante de arrecadação está no inventário mas sem handler (none)
        $inventory = $this->getJson('/api/v1/fiscal/manual-consults')->json('data.actions');
        $missing = collect($inventory)->firstWhere('eligibility', 'adapter_missing');
        if ($missing === null) {
            $this->markTestSkipped('Nenhuma ação adapter_missing no inventário atual.');
        }

        $this->postJson('/api/v1/fiscal/manual-consults', [
            'action_id' => $missing['action_id'],
            'client_id' => $this->client->id,
            'confirmed' => true,
        ])->assertStatus(422)->assertJsonPath('code', 'adapter_missing');
    }

    #[Test]
    public function it_enqueues_synchronous_ccmei_consult_without_secrets(): void
    {
        Queue::fake();

        $response = $this->postJson('/api/v1/fiscal/manual-consults', [
            'action_id' => 'simples_mei_ccmei:ccmei.dadosccmei',
            'client_id' => $this->client->id,
            'confirmed' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.action_id', 'simples_mei_ccmei:ccmei.dadosccmei')
            ->assertJsonPath('data.eligibility', 'ready')
            ->assertJsonMissingPath('data.result.autenticar_procurador_token')
            ->assertJsonMissingPath('data.result.consumer_secret');

        $encoded = json_encode($response->json(), JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('autenticar_procurador_token', $encoded);
        $this->assertStringNotContainsString('BEGIN CERTIFICATE', $encoded);
    }

    #[Test]
    public function it_enqueues_async_sitfis_refresh(): void
    {
        Queue::fake();

        $response = $this->postJson('/api/v1/fiscal/manual-consults', [
            'action_id' => 'sitfis:sitfis.solicitar_protocolo',
            'client_id' => $this->client->id,
            'confirmed' => true,
        ]);

        // 202 se enfileirou, 200 se reusou snapshot — ambos OK e sem segredos
        $this->assertContains($response->status(), [200, 201, 202]);
        $response->assertJsonPath('data.action_id', 'sitfis:sitfis.solicitar_protocolo');
        $response->assertJsonPath('data.async', true);
        $encoded = json_encode($response->json(), JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('autenticar_procurador_token', $encoded);
    }

    #[Test]
    public function it_enqueues_sicalc_support_with_params(): void
    {
        Queue::fake();

        $this->postJson('/api/v1/fiscal/manual-consults', [
            'action_id' => 'guides:sicalc.consultaapoioreceitas',
            'client_id' => $this->client->id,
            'confirmed' => true,
            'params' => ['codigo_receita' => '1082'],
        ])->assertCreated()
            ->assertJsonPath('data.action_id', 'guides:sicalc.consultaapoioreceitas')
            ->assertJsonPath('data.result.service_code', 'SICALC');
    }

    #[Test]
    public function it_enqueues_installment_with_canonical_operation_and_derived_modality(): void
    {
        config([
            'features.modules.parcelamentos.enabled' => true,
            'features.modules.parcelamentos.allow_all_offices' => true,
            'serpro.capabilities.installments' => 'real',
        ]);
        // Poder alternativo único (ANY-of) da família PARCSN.
        TaxProxyPower::query()->create([
            'office_id' => $this->office->id,
            'client_id' => $this->client->id,
            'environment' => SerproEnvironment::Trial->value,
            'author_identity' => '52998224725',
            'contributor_cnpj' => '11222333000181',
            'system_code' => 'PARCSN',
            'service_code' => 'PARCSN',
            'power_code' => '00076',
            'source' => TaxProxyPowerSource::ManualOfficialEvidence,
            'provenance' => TaxProxyPowerService::PROVENANCE_MANUAL_APPROVED,
            'segregation_class' => 'PRODUCTION',
            'status' => TaxProxyPowerStatus::Active,
            'valid_from' => now()->subDay(),
            'valid_to' => now()->addYear(),
            'accepted_at' => now()->subDay(),
            'freshness_checked_at' => now(),
            'verified_at' => now(),
        ]);

        Queue::fake();

        $this->postJson('/api/v1/fiscal/manual-consults', [
            'action_id' => 'installments:parcsn.pedidosparc',
            'client_id' => $this->client->id,
            'confirmed' => true,
        ])->assertCreated()
            ->assertJsonPath('data.action_id', 'installments:parcsn.pedidosparc')
            ->assertJsonPath('data.result.service_code', 'PARCSN')
            ->assertJsonPath('data.result.operation_code', 'CONSULTAR_PEDIDOS');
    }

    #[Test]
    public function it_rejects_installment_modality_mismatch(): void
    {
        config([
            'features.modules.parcelamentos.enabled' => true,
            'features.modules.parcelamentos.allow_all_offices' => true,
            'serpro.capabilities.installments' => 'real',
        ]);
        TaxProxyPower::query()->create([
            'office_id' => $this->office->id,
            'client_id' => $this->client->id,
            'environment' => SerproEnvironment::Trial->value,
            'author_identity' => '52998224725',
            'contributor_cnpj' => '11222333000181',
            'system_code' => 'PARCSN',
            'service_code' => 'PARCSN',
            'power_code' => '00076',
            'source' => TaxProxyPowerSource::ManualOfficialEvidence,
            'provenance' => TaxProxyPowerService::PROVENANCE_MANUAL_APPROVED,
            'segregation_class' => 'PRODUCTION',
            'status' => TaxProxyPowerStatus::Active,
            'valid_from' => now()->subDay(),
            'valid_to' => now()->addYear(),
            'accepted_at' => now()->subDay(),
            'freshness_checked_at' => now(),
            'verified_at' => now(),
        ]);

        $this->postJson('/api/v1/fiscal/manual-consults', [
            'action_id' => 'installments:parcsn.pedidosparc',
            'client_id' => $this->client->id,
            'confirmed' => true,
            'params' => ['modality' => 'PARCMEI'],
        ])->assertStatus(422);
    }

    private function seedIntegraChain(): void
    {
        SerproContract::query()->create([
            'environment' => SerproEnvironment::Trial,
            'status' => SerproContractStatus::Active,
            'contractor_cnpj' => '11222333000181',
            'health_status' => 'OK',
        ]);
        OfficeSerproAuthorization::query()->create([
            'office_id' => $this->office->id,
            'environment' => SerproEnvironment::Trial,
            'status' => SerproAuthorizationStatus::TokenActive,
            'author_identity_type' => AuthorIdentityType::Cpf,
            'author_identity' => '52998224725',
            'termo_vault_object_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            'termo_valid_to' => now()->addYear(),
            'procurador_token_vault_object_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            'procurador_token_expires_at' => now()->addHours(6),
        ]);

        // Poder SITFIS (00002) — mesma política de findUsablePower (não-simulado).
        TaxProxyPower::query()->create([
            'office_id' => $this->office->id,
            'client_id' => $this->client->id,
            'environment' => SerproEnvironment::Trial->value,
            'author_identity' => '52998224725',
            'contributor_cnpj' => '11222333000181',
            'system_code' => 'SITFIS',
            'service_code' => 'SITFIS',
            'power_code' => '00002',
            'source' => TaxProxyPowerSource::ManualOfficialEvidence,
            'provenance' => TaxProxyPowerService::PROVENANCE_MANUAL_APPROVED,
            'segregation_class' => 'PRODUCTION',
            'status' => TaxProxyPowerStatus::Active,
            'valid_from' => now()->subDay(),
            'valid_to' => now()->addYear(),
            'accepted_at' => now()->subDay(),
            'freshness_checked_at' => now(),
            'verified_at' => now(),
        ]);
    }
}
