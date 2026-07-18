<?php

namespace Tests\Feature\Fiscal\Guides;

use App\Enums\AuthorIdentityType;
use App\Enums\FiscalRunResult;
use App\Enums\FiscalRunStatus;
use App\Enums\OfficeRole;
use App\Enums\SerproAuthorizationStatus;
use App\Enums\SerproContractStatus;
use App\Enums\SerproEnvironment;
use App\Models\Client;
use App\Models\Establishment;
use App\Models\Office;
use App\Models\OfficeSerproAuthorization;
use App\Models\SerproContract;
use App\Models\User;
use App\Services\Fiscal\Guides\SicalcRevenueSupportProjector;
use App\Services\Fiscal\Guides\SicalcRevenueSupportQueryService;
use App\Services\FiscalMonitoring\FiscalMonitoringRunService;
use App\Support\CurrentOffice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SicalcRevenueSupportMonitoringTest extends TestCase
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
            'features.modules.guias.enabled' => true,
            'features.modules.guias.allow_all_offices' => true,
            'fiscal_monitoring.enabled' => true,
            'serpro.kill_switch' => false,
            'serpro.default_environment' => 'TRIAL',
            'serpro.trial.use_fake_clients' => true,
            'serpro.capabilities.guides' => 'real',
        ]);
        $this->office = Office::factory()->create();
        $this->client = Client::factory()->forOffice($this->office)->create();
        Establishment::factory()->create([
            'office_id' => $this->office->id,
            'client_id' => $this->client->id,
            'cnpj' => '11222333000181',
            'is_matrix' => true,
        ]);
        $user = User::factory()->forOffice($this->office, OfficeRole::Admin)->withTwoFactorConfirmed()->create();
        $this->actingAs($user);
        app(CurrentOffice::class)->clear();
        app(CurrentOffice::class)->resolve($user);
        $this->seedIntegraChain();
    }

    #[Test]
    public function it_projects_simulated_sicalc_support_without_fiscal_identifiers(): void
    {
        $run = app(SicalcRevenueSupportQueryService::class)->enqueueManualConsult($this->office, $this->client, '1082', null);
        $out = app(FiscalMonitoringRunService::class)->execute($run['id']);

        $this->assertSame(FiscalRunStatus::Completed, $out->status);
        $this->assertSame(FiscalRunResult::Success, $out->result);
        $this->assertDatabaseHas('sicalc_revenue_support_projections', [
            'office_id' => $this->office->id,
            'client_id' => $this->client->id,
            'revenue_code' => '1082',
        ]);
        $evidence = $out->evidenceArtifacts()->firstOrFail();
        $this->assertStringNotContainsString('11222333000181', (string) $evidence->payload_json);
        $this->assertStringNotContainsString('cnpj', strtolower((string) $evidence->payload_json));
    }

    #[Test]
    public function it_exposes_only_tenant_scoped_sanitized_history_and_enqueues_confirmed_consult(): void
    {
        app(SicalcRevenueSupportProjector::class)->project($this->office, $this->client, [
            'revenue_code' => '1082', 'description' => 'IRRF - Trabalho assalariado',
            'extensions' => [['obrigatorios' => ['dataPA' => true], 'opcionais' => [], 'informacoes' => ['calculado' => true]]],
        ], null, 'SIMULATED');

        $this->getJson("/api/v1/fiscal/guides/revenue-support/clients/{$this->client->id}/history?codigo_receita=1082")
            ->assertOk()->assertJsonPath('data.current.0.revenue_code', '1082')
            ->assertJsonMissingPath('data.current.0.cnpj')->assertJsonMissingPath('data.history.0.cpf');
        $this->postJson("/api/v1/fiscal/guides/revenue-support/clients/{$this->client->id}/consult", [
            'confirmed' => true, 'codigo_receita' => '1082', 'office_id' => $this->office->id,
        ])->assertStatus(422)->assertJsonPath('code', 'CLIENT_OFFICE_ID_REJECTED');

        Queue::fake();
        $this->postJson("/api/v1/fiscal/guides/revenue-support/clients/{$this->client->id}/consult", [
            'confirmed' => true, 'codigo_receita' => '1082',
        ])->assertCreated()->assertJsonPath('data.service_code', 'SICALC')
            ->assertJsonPath('data.operation_code', 'CONSULTAR_APOIO_RECEITAS')->assertJsonMissingPath('data.cnpj');
    }

    private function seedIntegraChain(): void
    {
        SerproContract::query()->create([
            'environment' => SerproEnvironment::Trial, 'status' => SerproContractStatus::Active,
            'contractor_cnpj' => '11222333000181', 'health_status' => 'OK',
        ]);
        OfficeSerproAuthorization::query()->create([
            'office_id' => $this->office->id, 'environment' => SerproEnvironment::Trial,
            'status' => SerproAuthorizationStatus::TokenActive, 'author_identity_type' => AuthorIdentityType::Cpf,
            'author_identity' => '52998224725', 'termo_vault_object_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            'termo_valid_to' => now()->addYear(), 'procurador_token_vault_object_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            'procurador_token_expires_at' => now()->addHours(6),
        ]);
    }
}
