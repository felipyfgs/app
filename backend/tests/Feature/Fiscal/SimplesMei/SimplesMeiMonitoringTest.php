<?php

namespace Tests\Feature\Fiscal\SimplesMei;

use App\DTO\Fiscal\FiscalAdapterRequest;
use App\Enums\FiscalGuidePaymentStatus;
use App\Enums\FiscalRunResult;
use App\Enums\FiscalRunStatus;
use App\Enums\FiscalSituation;
use App\Enums\FiscalTrigger;
use App\Enums\OfficeRole;
use App\Enums\SerproAuthorizationStatus;
use App\Enums\SerproContractStatus;
use App\Enums\SerproEnvironment;
use App\Enums\TaxProxyPowerSource;
use App\Enums\TaxProxyPowerStatus;
use App\Enums\TaxRegimeCode;
use App\Models\Client;
use App\Models\ClientTaxRegimePeriod;
use App\Models\Establishment;
use App\Models\FiscalEvidenceArtifact;
use App\Models\FiscalGuideStub;
use App\Models\FiscalMonitoringRun;
use App\Models\FiscalSnapshot;
use App\Models\Office;
use App\Models\OfficeSerproAuthorization;
use App\Models\SerproContract;
use App\Models\TaxProxyPower;
use App\Models\User;
use App\Services\Fiscal\SimplesMei\RegimeApplicabilityService;
use App\Services\Fiscal\SimplesMei\SimplesMeiAdapter;
use App\Services\Fiscal\SimplesMei\SimplesMeiCatalog;
use App\Services\Fiscal\SimplesMei\SimplesMeiQueryService;
use App\Services\FiscalMonitoring\FiscalAdapterRegistry;
use App\Services\FiscalMonitoring\FiscalMonitoringRunService;
use App\Support\CurrentOffice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Integração tasks 8.3–8.8: regime, evidência, DAS stub, mutantes, endpoints, idempotência.
 */
class SimplesMeiMonitoringTest extends TestCase
{
    use RefreshDatabase;

    private Office $office;

    private Client $client;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'features.global_enabled' => true,
            'features.kill_switch' => false,
            'features.mutating.enabled' => false,
            'features.modules.simples_mei.enabled' => true,
            'features.modules.simples_mei.allow_all_offices' => true,
            'features.modules.simples_mei.mutating_enabled' => false,
            'fiscal_monitoring.enabled' => true,
            'fiscal_monitoring.kill_switch' => false,
            'fiscal_monitoring.mutating_enabled' => false,
            'fiscal_monitoring.simples_mei.das_stub_without_mutating' => true,
            'serpro.kill_switch' => false,
            'serpro.default_environment' => 'TRIAL',
            'serpro.trial.use_fake_clients' => true,
        ]);

        $this->office = Office::factory()->create();
        $this->client = Client::factory()->forOffice($this->office)->create();
        Establishment::factory()->create([
            'office_id' => $this->office->id,
            'client_id' => $this->client->id,
            'cnpj' => '11222333000181',
            'is_matrix' => true,
        ]);
        $this->admin = User::factory()->forOffice($this->office, OfficeRole::Admin)->withTwoFactorConfirmed()->create();

        $this->seedIntegraChain();
    }

    public function test_consulta_pgdasd_gera_evidencia_e_snapshot(): void
    {
        $svc = app(SimplesMeiQueryService::class);
        $run = $svc->enqueueConsult(
            $this->office,
            $this->client,
            'INTEGRA_SN',
            'PGDASD',
            'CONSULTAR_DECLARACAO',
            periodKey: '2026-03',
            correlationId: 'corr-pgdasd-1',
            dispatch: false,
        );

        $out = app(FiscalMonitoringRunService::class)->execute($run->id);

        $this->assertSame(FiscalRunStatus::Completed, $out->status);
        $this->assertSame(FiscalRunResult::Success, $out->result);
        $this->assertSame(FiscalSituation::UpToDate, $out->situation);

        $this->assertSame(1, FiscalSnapshot::query()->withoutGlobalScopes()
            ->where('office_id', $this->office->id)
            ->where('system_code', 'INTEGRA_SN')
            ->count());
        $this->assertSame(1, FiscalEvidenceArtifact::query()->withoutGlobalScopes()
            ->where('office_id', $this->office->id)
            ->count());
    }

    public function test_competencia_inconclusiva_permanece_unknown(): void
    {
        $svc = app(SimplesMeiQueryService::class);
        $run = $svc->enqueueConsult(
            $this->office,
            $this->client,
            'INTEGRA_SN',
            'PGDASD',
            'CONSULTAR_DECLARACAO',
            periodKey: '2026-04',
            correlationId: 'corr-inconclusivo',
            dispatch: false,
        );
        $run->forceFill(['progress' => ['force_status' => 'INCONCLUSIVO']])->save();

        $out = app(FiscalMonitoringRunService::class)->execute($run->id);

        $this->assertSame(FiscalSituation::Unknown, $out->situation);
        $this->assertSame(FiscalRunResult::Success, $out->result);
    }

    public function test_mudanca_regime_nao_mistura_sn_e_mei(): void
    {
        $regimes = app(RegimeApplicabilityService::class);
        $regimes->projectFromNormalized($this->office, $this->client, [
            'current_regime' => 'SIMPLES_NACIONAL',
            'periods' => [
                ['regime' => 'MEI', 'effective_from' => '2023-01-01', 'effective_to' => '2023-12-31'],
                ['regime' => 'SIMPLES_NACIONAL', 'effective_from' => '2024-01-01', 'effective_to' => null],
            ],
        ]);

        $this->assertSame(TaxRegimeCode::Mei, $regimes->regimeForPeriod($this->office, $this->client, '2023-06'));
        $this->assertSame(TaxRegimeCode::SimplesNacional, $regimes->regimeForPeriod($this->office, $this->client, '2024-06'));

        $defMei = SimplesMeiCatalog::find('INTEGRA_MEI', 'PGMEI', 'CONSULTAR');
        $block = $regimes->assertOperationApplicable($this->office, $this->client, $defMei, '2024-06');
        $this->assertNotNull($block);
        $this->assertSame(FiscalSituation::NotApplicable, $block->situation);

        $defSn = SimplesMeiCatalog::find('INTEGRA_SN', 'PGDASD', 'CONSULTAR_DECLARACAO');
        $ok = $regimes->assertOperationApplicable($this->office, $this->client, $defSn, '2024-06');
        $this->assertNull($ok);

        // Histórico de MEI permanece
        $this->assertSame(2, ClientTaxRegimePeriod::query()->withoutGlobalScopes()
            ->where('office_id', $this->office->id)
            ->where('client_id', $this->client->id)
            ->count());
    }

    public function test_servico_nao_suportado_retorna_unsupported(): void
    {
        $this->actingAs($this->admin);
        app(CurrentOffice::class)->resolve($this->admin);

        $this->postJson('/api/v1/fiscal/simples-mei/consult', [
            'client_id' => $this->client->id,
            'system_code' => 'INTEGRA_SN',
            'service_code' => 'PORTAL_SCRAPER',
            'operation_code' => 'CONSULTAR',
            'dispatch' => false,
        ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'UNSUPPORTED');
    }

    public function test_sem_procuracao_bloqueia_consulta(): void
    {
        TaxProxyPower::query()->withoutGlobalScopes()->delete();

        $svc = app(SimplesMeiQueryService::class);
        $run = $svc->enqueueConsult(
            $this->office,
            $this->client,
            'INTEGRA_SN',
            'PGDASD',
            'CONSULTAR_DECLARACAO',
            periodKey: '2026-03',
            correlationId: 'corr-proxy-missing',
            dispatch: false,
        );

        $out = app(FiscalMonitoringRunService::class)->execute($run->id);

        $this->assertSame(FiscalRunResult::Blocked, $out->result);
        $this->assertSame('PROXY_POWER_MISSING', $out->error_code);
    }

    public function test_transmissao_mutante_bloqueada_no_piloto(): void
    {
        $svc = app(SimplesMeiQueryService::class);
        $run = $svc->enqueueTransmit(
            $this->office,
            $this->client,
            'PGDASD',
            periodKey: '2026-03',
            correlationId: 'corr-transmit-block',
            dispatch: false,
        );

        $out = app(FiscalMonitoringRunService::class)->execute($run->id);

        $this->assertSame(FiscalRunResult::Blocked, $out->result);
        $this->assertTrue(in_array($out->error_code, ['MUTATING_DISABLED', 'MUTATING_DISABLED'], true)
            || $out->skip_reason === 'MUTATING_DISABLED'
            || $out->error_code === 'MUTATING_DISABLED');
    }

    public function test_das_assistido_stub_sem_marcar_pagamento(): void
    {
        $svc = app(SimplesMeiQueryService::class);
        $run = $svc->enqueueDasGeneration(
            $this->office,
            $this->client,
            'SIMPLES_NACIONAL',
            periodKey: '2026-02',
            correlationId: 'corr-das-stub',
            dispatch: false,
        );

        $out = app(FiscalMonitoringRunService::class)->execute($run->id);

        $this->assertSame(FiscalRunResult::Success, $out->result);
        $stub = FiscalGuideStub::query()->withoutGlobalScopes()
            ->where('office_id', $this->office->id)
            ->where('client_id', $this->client->id)
            ->first();
        $this->assertNotNull($stub);
        $this->assertSame(FiscalGuidePaymentStatus::Unknown, $stub->payment_status);
        $this->assertFalse($stub->is_external_call);
    }

    public function test_idempotencia_mesma_correlation(): void
    {
        $svc = app(SimplesMeiQueryService::class);
        $a = $svc->enqueueConsult(
            $this->office, $this->client, 'INTEGRA_MEI', 'PGMEI', 'CONSULTAR',
            periodKey: '2026-01', correlationId: 'same-sm-corr', dispatch: false,
        );
        $b = $svc->enqueueConsult(
            $this->office, $this->client, 'INTEGRA_MEI', 'PGMEI', 'CONSULTAR',
            periodKey: '2026-01', correlationId: 'same-sm-corr', dispatch: false,
        );

        $this->assertSame($a->id, $b->id);

        app(FiscalMonitoringRunService::class)->execute($a->id);
        app(FiscalMonitoringRunService::class)->execute($a->id);

        $this->assertSame(1, FiscalSnapshot::query()->withoutGlobalScopes()
            ->where('office_id', $this->office->id)
            ->where('service_code', 'PGMEI')
            ->count());
    }

    public function test_api_catalog_e_tenant_scoped(): void
    {
        $this->actingAs($this->admin);
        app(CurrentOffice::class)->resolve($this->admin);

        $this->getJson('/api/v1/fiscal/simples-mei/catalog')
            ->assertOk()
            ->assertJsonPath('module', 'simples_mei')
            ->assertJsonStructure(['data' => [['system_code', 'service_code', 'operation_code', 'mutability']]]);

        $this->postJson('/api/v1/fiscal/simples-mei/consult', [
            'client_id' => $this->client->id,
            'system_code' => 'INTEGRA_SN',
            'service_code' => 'REGIME_APURACAO',
            'operation_code' => 'CONSULTAR',
            'correlation_id' => 'api-regime-1',
            'dispatch' => false,
        ])->assertCreated();

        $runId = FiscalMonitoringRun::query()->withoutGlobalScopes()
            ->where('office_id', $this->office->id)
            ->where('service_code', 'REGIME_APURACAO')
            ->value('id');
        app(FiscalMonitoringRunService::class)->execute((int) $runId);

        $this->getJson('/api/v1/fiscal/simples-mei/clients/'.$this->client->id.'/regimes')
            ->assertOk()
            ->assertJsonStructure(['data']);

        // Outro tenant não vê runs/stubs do office
        $other = Office::factory()->create();
        $otherAdmin = User::factory()->forOffice($other, OfficeRole::Admin)->withTwoFactorConfirmed()->create();
        $this->actingAs($otherAdmin);
        app(CurrentOffice::class)->clear();
        app(CurrentOffice::class)->resolve($otherAdmin);

        $this->getJson('/api/v1/fiscal/simples-mei/clients/'.$this->client->id.'/regimes')
            ->assertNotFound();
    }

    public function test_adapters_registrados_no_registry(): void
    {
        $registry = app(FiscalAdapterRegistry::class);
        $count = 0;
        foreach (SimplesMeiCatalog::all() as $def) {
            $req = new FiscalAdapterRequest(
                office: $this->office,
                client: $this->client,
                run: new FiscalMonitoringRun([
                    'office_id' => $this->office->id,
                    'client_id' => $this->client->id,
                    'system_code' => $def->systemCode,
                    'service_code' => $def->serviceCode,
                    'operation_code' => $def->operationCode,
                ]),
                systemCode: $def->systemCode,
                serviceCode: $def->serviceCode,
                operationCode: $def->operationCode,
                trigger: FiscalTrigger::Manual,
            );
            $adapter = $registry->resolve($req);
            $this->assertInstanceOf(SimplesMeiAdapter::class, $adapter);
            $count++;
        }
        $this->assertGreaterThanOrEqual(15, $count);
    }

    private function seedIntegraChain(): void
    {
        SerproContract::query()->create([
            'environment' => SerproEnvironment::Trial,
            'status' => SerproContractStatus::Active,
            'contractor_cnpj' => '11222333000181',
            'health_status' => 'OK',
        ]);

        $auth = OfficeSerproAuthorization::query()->create([
            'office_id' => $this->office->id,
            'environment' => SerproEnvironment::Trial,
            'status' => SerproAuthorizationStatus::TokenActive,
            'author_identity_type' => 'CPF',
            'author_identity' => '52998224725',
            'termo_vault_object_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            'termo_valid_to' => now()->addYear(),
            'procurador_token_vault_object_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            'procurador_token_expires_at' => now()->addHours(6),
        ]);

        // Legado (elegibilidade por service_code) + códigos e-CAC oficiais do manifesto
        // (gate SerproOperationService via required_proxy_powers da operation_key).
        $powers = [
            ['PGDASD', 'INTEGRA_SN', 'PGDASD'],
            ['DEFIS', 'INTEGRA_SN', 'DEFIS'],
            ['REGIME_APURACAO', 'INTEGRA_SN', 'REGIME_APURACAO'],
            ['PGMEI', 'INTEGRA_MEI', 'PGMEI'],
            ['CCMEI', 'INTEGRA_MEI', 'CCMEI'],
            ['DASN_SIMEI', 'INTEGRA_MEI', 'DASN_SIMEI'],
            ['00146', 'INTEGRA_SN', 'PGDASD'], // pgdasd.*
            ['00060', 'INTEGRA_SN', 'REGIME_APURACAO'], // regimeapuracao.*
        ];

        foreach ($powers as [$powerCode, $system, $service]) {
            TaxProxyPower::query()->create([
                'office_id' => $this->office->id,
                'client_id' => $this->client->id,
                'office_serpro_authorization_id' => $auth->id,
                'author_identity' => '52998224725',
                'contributor_cnpj' => '11222333000181',
                'system_code' => $system,
                'service_code' => $service,
                'power_code' => $powerCode,
                'source' => TaxProxyPowerSource::ManualOfficialEvidence,
                'status' => TaxProxyPowerStatus::Active,
                'valid_from' => now()->subDay(),
                'valid_to' => now()->addYear(),
            ]);
        }
    }
}
