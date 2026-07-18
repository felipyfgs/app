<?php

namespace Tests\Feature\Fiscal\Guides;

use App\Enums\AuthorIdentityType;
use App\Enums\FiscalRunResult;
use App\Enums\FiscalRunStatus;
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
use App\Services\Fiscal\Guides\PagtowebPaymentCountQueryService;
use App\Services\FiscalMonitoring\FiscalMonitoringRunService;
use App\Support\CurrentOffice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PagtowebPaymentCountMonitoringTest extends TestCase
{
    use RefreshDatabase;

    private Office $office;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        config(['features.global_enabled' => true, 'features.kill_switch' => false, 'features.modules.guias.enabled' => true, 'features.modules.guias.allow_all_offices' => true, 'fiscal_monitoring.enabled' => true, 'serpro.kill_switch' => false, 'serpro.default_environment' => 'TRIAL', 'serpro.capabilities.guides' => 'real']);
        $this->office = Office::factory()->create();
        $this->client = Client::factory()->forOffice($this->office)->create();
        Establishment::factory()->create(['office_id' => $this->office->id, 'client_id' => $this->client->id, 'cnpj' => '11222333000181', 'is_matrix' => true]);
        $user = User::factory()->forOffice($this->office, OfficeRole::Admin)->withTwoFactorConfirmed()->create();
        $this->actingAs($user);
        app(CurrentOffice::class)->clear();
        app(CurrentOffice::class)->resolve($user);
        SerproContract::query()->create(['environment' => SerproEnvironment::Trial, 'status' => SerproContractStatus::Active, 'contractor_cnpj' => '11222333000181', 'health_status' => 'OK']);
        $auth = OfficeSerproAuthorization::query()->create(['office_id' => $this->office->id, 'environment' => SerproEnvironment::Trial, 'status' => SerproAuthorizationStatus::TokenActive, 'author_identity_type' => AuthorIdentityType::Cpf, 'author_identity' => '52998224725', 'termo_vault_object_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV', 'termo_valid_to' => now()->addYear(), 'procurador_token_vault_object_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV', 'procurador_token_expires_at' => now()->addHours(6)]);
        TaxProxyPower::query()->create(['office_id' => $this->office->id, 'client_id' => $this->client->id, 'office_serpro_authorization_id' => $auth->id, 'author_identity' => '52998224725', 'contributor_cnpj' => '11222333000181', 'system_code' => 'PAGTOWEB', 'service_code' => 'CONTACONSDOCARRPG73', 'power_code' => '00004', 'source' => TaxProxyPowerSource::ManualOfficialEvidence, 'status' => TaxProxyPowerStatus::Active, 'valid_from' => now()->subDay(), 'valid_to' => now()->addYear()]);
    }

    #[Test]
    public function it_projects_a_simulated_count_without_persisting_fiscal_identifiers(): void
    {
        $run = app(PagtowebPaymentCountQueryService::class)->enqueueManualConsult($this->office, $this->client, ['intervalo_data_arrecadacao' => ['data_inicial' => '2026-01-01', 'data_final' => '2026-01-31']], null);
        $out = app(FiscalMonitoringRunService::class)->execute($run['id']);
        $this->assertSame(FiscalRunStatus::Completed, $out->status);
        $this->assertSame(FiscalRunResult::Success, $out->result);
        $this->assertDatabaseHas('pagtoweb_payment_count_projections', ['office_id' => $this->office->id, 'client_id' => $this->client->id, 'payment_count' => 1]);
        $this->assertStringNotContainsString('11222333000181', (string) $out->evidenceArtifacts()->firstOrFail()->payload_json);
    }

    #[Test]
    public function it_scopes_history_and_rejects_client_office_id(): void
    {
        Queue::fake();
        $base = "/api/v1/fiscal/guides/payment-count/clients/{$this->client->id}";
        $this->postJson("{$base}/consult", ['confirmed' => true, 'office_id' => $this->office->id, 'filters' => ['codigo_receita_lista' => ['1082']]])->assertStatus(422)->assertJsonPath('code', 'CLIENT_OFFICE_ID_REJECTED');
        $this->postJson("{$base}/consult", ['confirmed' => true, 'filters' => ['numero_documento' => '123']])->assertStatus(422)->assertJsonPath('code', 'INVALID_PAYMENT_COUNT_FILTERS');
        $this->postJson("{$base}/consult", ['confirmed' => true, 'filters' => ['codigo_receita_lista' => ['1082']]])->assertCreated()->assertJsonPath('data.service_code', 'PAGTOWEB')->assertJsonPath('data.operation_code', 'CONTAR_CONSULTA_PAGAMENTOS');
        $this->getJson("{$base}/history")->assertOk()->assertJsonPath('data.provenance.serpro_called', false)->assertJsonMissingPath('data.current.cnpj');
    }
}
