<?php

namespace Tests\Feature\Fiscal\SimplesMei;

use App\DTO\Fiscal\FiscalAdapterRequest;
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
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\ClientTaxRegimePeriod;
use App\Models\DefisDeclarationReference;
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
use App\Services\Fiscal\SimplesMei\DefisDeclarationReferenceStore;
use App\Services\Fiscal\SimplesMei\RegimeApplicabilityService;
use App\Services\Fiscal\SimplesMei\SimplesMeiAdapter;
use App\Services\Fiscal\SimplesMei\SimplesMeiCatalog;
use App\Services\Fiscal\SimplesMei\SimplesMeiQueryService;
use App\Services\FiscalMonitoring\FiscalAdapterRegistry;
use App\Services\FiscalMonitoring\FiscalMonitoringRunService;
use App\Support\CurrentOffice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Integração tasks 8.3–8.8: regime, evidência, emissão DAS, mutantes, endpoints, idempotência.
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
            'serpro.kill_switch' => false,
            'serpro.default_environment' => 'TRIAL',
            'serpro.trial.use_fake_clients' => true,
            'serpro.capabilities.simples_mei' => 'real',
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
        // Fake/simulated: estado fail-closed permanece UNKNOWN (UNVERIFIED), sem promover verde
        $this->assertSame(FiscalSituation::Unknown, $out->situation);

        $this->assertSame(1, FiscalSnapshot::query()->withoutGlobalScopes()
            ->where('office_id', $this->office->id)
            ->where('system_code', 'INTEGRA_SN')
            ->count());
        $this->assertSame(1, FiscalEvidenceArtifact::query()->withoutGlobalScopes()
            ->where('office_id', $this->office->id)
            ->count());
    }

    public function test_ccmei_capability_desabilitada_falha_sem_gerar_evidencia(): void
    {
        config(['serpro.capabilities.simples_mei' => 'disabled']);
        $run = app(SimplesMeiQueryService::class)->enqueueConsult(
            $this->office,
            $this->client,
            'INTEGRA_MEI',
            'CCMEI',
            'MONITOR',
            correlationId: 'ccmei-capability-disabled',
            dispatch: false,
        );

        $out = app(FiscalMonitoringRunService::class)->execute($run->id);

        $this->assertSame(FiscalRunResult::Failed, $out->result);
        $this->assertSame('CAPABILITY_DISABLED', $out->error_code);
        $this->assertDatabaseCount('fiscal_evidence_artifacts', 0);
    }

    public function test_ccmei_123_simulado_persiste_resumo_sem_identificador_fiscal(): void
    {
        $run = app(SimplesMeiQueryService::class)->enqueueConsult(
            $this->office,
            $this->client,
            'INTEGRA_MEI',
            'CCMEI',
            'CONSULTAR_SITUACAO_CADASTRAL',
            correlationId: 'ccmei-status-123-simulated',
            dispatch: false,
        );

        $out = app(FiscalMonitoringRunService::class)->execute($run->id);

        $this->assertSame(FiscalRunStatus::Completed, $out->status);
        $this->assertSame(FiscalRunResult::Success, $out->result);
        $this->assertDatabaseHas('ccmei_registration_status_projections', [
            'office_id' => $this->office->id,
            'client_id' => $this->client->id,
            'status' => 'ATIVA',
            'enquadrado_mei' => true,
        ]);
        $evidence = FiscalEvidenceArtifact::query()->withoutGlobalScopes()->latest('id')->firstOrFail();
        $this->assertStringNotContainsString('00000000000000', (string) $evidence->payload_json);
        $this->assertStringNotContainsString('cnpj', strtolower((string) $evidence->payload_json));
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

    public function test_regime_102_persiste_calendario_idempotente_sem_mudar_regime_tributario(): void
    {
        $svc = app(SimplesMeiQueryService::class);
        $run = $svc->enqueueConsult(
            $this->office,
            $this->client,
            'INTEGRA_SN',
            'REGIME_APURACAO',
            'CONSULTAR_ANOS_CALENDARIOS',
            correlationId: 'regime-102-idempotente',
            dispatch: false,
        );

        app(FiscalMonitoringRunService::class)->execute($run->id);
        app(FiscalMonitoringRunService::class)->execute($run->id);

        $items = app(RegimeApplicabilityService::class)->listCalendarOptions($this->office, $this->client);
        $this->assertSame([
            ['calendar_year' => 2026, 'regime_apuracao' => 'COMPETENCIA'],
            ['calendar_year' => 2025, 'regime_apuracao' => 'CAIXA'],
        ], array_map(static fn (array $item): array => [
            'calendar_year' => $item['calendar_year'],
            'regime_apuracao' => $item['regime_apuracao'],
        ], $items));
        $this->assertSame(2, ClientTaxRegimePeriod::query()->withoutGlobalScopes()
            ->where('office_id', $this->office->id)
            ->where('client_id', $this->client->id)
            ->count());
        $this->assertNull($this->client->fresh()->tax_regime);
    }

    public function test_regime_103_consulta_opcao_por_ano_sem_expor_retorno_bruto(): void
    {
        $run = app(SimplesMeiQueryService::class)->enqueueConsult(
            $this->office,
            $this->client,
            'INTEGRA_SN',
            'REGIME_APURACAO',
            'CONSULTAR',
            periodKey: '2025',
            correlationId: 'regime-103-opcao',
            dispatch: false,
        );

        $out = app(FiscalMonitoringRunService::class)->execute($run->id);

        $this->assertSame(FiscalRunStatus::Completed, $out->status);
        $this->assertSame(FiscalRunResult::Success, $out->result);
        $this->assertSame(FiscalSituation::UpToDate, $out->situation);
        $this->assertSame([
            ['calendar_year' => 2025, 'regime_apuracao' => 'CAIXA'],
        ], array_map(static fn (array $item): array => [
            'calendar_year' => $item['calendar_year'],
            'regime_apuracao' => $item['regime_apuracao'],
        ], app(RegimeApplicabilityService::class)->listRegimeOptions($this->office, $this->client)));

    }

    public function test_defis_142_projeta_lista_sem_identificador_declaracao(): void
    {
        $run = app(SimplesMeiQueryService::class)->enqueueConsult(
            $this->office,
            $this->client,
            'INTEGRA_SN',
            'DEFIS',
            'CONSULTAR',
            correlationId: 'defis-142-lista',
            dispatch: false,
        );

        $out = app(FiscalMonitoringRunService::class)->execute($run->id);

        $this->assertSame(FiscalRunResult::Success, $out->result);
        $this->assertDatabaseHas('defis_declaration_projections', [
            'office_id' => $this->office->id,
            'client_id' => $this->client->id,
            'calendar_year' => now()->year,
            'declaration_type' => '1',
        ]);
        $this->assertDatabaseMissing('defis_declaration_observations', ['digest' => 'DEFIS-FAKE-NAO-EXPOSTO']);
        $reference = DefisDeclarationReference::query()->withoutGlobalScopes()->sole();
        $store = app(DefisDeclarationReferenceStore::class);
        $this->assertSame('000000002025001', $store->read($reference, $this->office));
        $this->assertSame($reference->id, $store->store($this->office, $this->client, '000000002025001', $run->id, 'SIMULATED')->id);
        $this->assertDatabaseCount('defis_declaration_references', 1);

        $this->expectException(\RuntimeException::class);
        $store->read($reference, Office::factory()->create());
    }

    public function test_defis_143_guarda_pdfs_no_cofre_sem_identificador_na_projecao(): void
    {
        $run = app(SimplesMeiQueryService::class)->enqueueConsult(
            $this->office,
            $this->client,
            'INTEGRA_SN',
            'DEFIS',
            'CONSULTAR_ULTIMA_DECLARACAO_RECIBO',
            periodKey: '2025',
            correlationId: 'defis-143-documentos',
            dispatch: false,
        );

        $out = app(FiscalMonitoringRunService::class)->execute($run->id);

        $this->assertSame(FiscalRunResult::Success, $out->result);
        $this->assertDatabaseCount('defis_latest_declaration_artifacts', 2);
        $this->assertDatabaseHas('defis_latest_declaration_artifacts', [
            'office_id' => $this->office->id,
            'client_id' => $this->client->id,
            'calendar_year' => 2025,
            'kind' => 'RECIBO',
        ]);
        $this->assertDatabaseMissing('defis_latest_declaration_artifacts', ['digest' => 'DEFIS-FAKE-NAO-EXPOSTO']);
    }

    public function test_defis_144_recupera_referencia_opaca_sem_expor_id_defis(): void
    {
        $list = app(SimplesMeiQueryService::class)->enqueueConsult(
            $this->office,
            $this->client,
            'INTEGRA_SN',
            'DEFIS',
            'CONSULTAR',
            correlationId: 'defis-142-para-144',
            dispatch: false,
        );
        app(FiscalMonitoringRunService::class)->execute($list->id);
        $reference = DefisDeclarationReference::query()->withoutGlobalScopes()->sole();

        $run = app(SimplesMeiQueryService::class)->enqueueConsult(
            $this->office,
            $this->client,
            'INTEGRA_SN',
            'DEFIS',
            'CONSULTAR_DECLARACAO_RECIBO',
            correlationId: 'defis-144-documentos',
            dispatch: false,
        );
        $run->forceFill(['progress' => ['defis_reference_id' => $reference->id]])->save();

        $out = app(FiscalMonitoringRunService::class)->execute($run->id);

        $this->assertSame(FiscalRunResult::Success, $out->result);
        $this->assertSame(FiscalSituation::UpToDate, $out->situation);
        $this->assertDatabaseCount('defis_specific_declaration_artifacts', 2);
        $this->assertStringNotContainsString('000000002025001', json_encode($out->toPublicArray(), JSON_THROW_ON_ERROR));
    }

    public function test_api_regime_102_rejeita_office_id_e_get_so_le_projecao_local(): void
    {
        $this->actingAs($this->admin);
        app(CurrentOffice::class)->resolve($this->admin);

        $this->postJson('/api/v1/fiscal/simples-mei/regime-calendar/consult', [
            'client_id' => $this->client->id,
            'office_id' => $this->office->id,
        ])->assertStatus(422)->assertJsonPath('code', 'CLIENT_OFFICE_ID_REJECTED');

        $this->getJson('/api/v1/fiscal/simples-mei/clients/'.$this->client->id.'/regime-calendar')
            ->assertOk()
            ->assertJsonPath('data', [])
            ->assertJsonPath('provenance.serpro_called', false);

        $this->postJson('/api/v1/fiscal/simples-mei/regime-calendar/consult', [
            'client_id' => $this->client->id,
            'correlation_id' => 'api-regime-102',
        ])->assertCreated()
            ->assertJsonPath('serpro_call', 'QUEUED');
    }

    public function test_api_regime_103_rejeita_office_id_e_separa_leitura_local_da_consulta(): void
    {
        $this->actingAs($this->admin);
        app(CurrentOffice::class)->resolve($this->admin);

        $this->getJson('/api/v1/fiscal/simples-mei/clients/'.$this->client->id.'/regime-options')
            ->assertOk()
            ->assertJsonPath('data', [])
            ->assertJsonPath('provenance.serpro_called', false);

        $this->postJson('/api/v1/fiscal/simples-mei/regime-option/consult', [
            'client_id' => $this->client->id,
            'year' => 2025,
            'office_id' => $this->office->id,
        ])->assertStatus(422)->assertJsonPath('code', 'CLIENT_OFFICE_ID_REJECTED');

        $this->postJson('/api/v1/fiscal/simples-mei/regime-option/consult', [
            'client_id' => $this->client->id,
            'year' => 2025,
            'correlation_id' => 'api-regime-103',
        ])->assertCreated()
            ->assertJsonPath('serpro_call', 'QUEUED');
    }

    public function test_regime_104_guarda_texto_no_cofre_e_lista_somente_descritor_local(): void
    {
        $run = app(SimplesMeiQueryService::class)->enqueueConsult(
            $this->office,
            $this->client,
            'INTEGRA_SN',
            'REGIME_APURACAO',
            'CONSULTAR_RESOLUCAO',
            periodKey: '2025',
            correlationId: 'regime-104-fake',
            dispatch: false,
        );

        $out = app(FiscalMonitoringRunService::class)->execute($run->id);

        $this->assertSame(FiscalRunResult::Success, $out->result);
        $artifact = FiscalEvidenceArtifact::query()->withoutGlobalScopes()
            ->where('office_id', $this->office->id)
            ->where('operation_key', 'regimeapuracao.consultarresolucao')
            ->sole();
        $this->assertSame('RESOLUTION_TEXT', $artifact->metadata['content_kind']);
        $this->assertSame(2025, $artifact->metadata['calendar_year']);

        $items = app(RegimeApplicabilityService::class)->listResolutions($this->office, $this->client);
        $this->assertCount(1, $items);
        $this->assertSame(2025, $items[0]['calendar_year']);
        $this->assertSame('Ver resolução do Regime de Caixa', $items[0]['document']['label']);
        $this->assertStringStartsWith('/api/v1/fiscal/evidence/', $items[0]['document']['href']);
        $this->assertStringNotContainsString('textoResolucao', json_encode($items, JSON_THROW_ON_ERROR));
    }

    public function test_api_regime_104_rejeita_office_id_e_get_nao_chama_serpro(): void
    {
        $this->actingAs($this->admin);
        app(CurrentOffice::class)->resolve($this->admin);

        $this->postJson('/api/v1/fiscal/simples-mei/regime-resolution/consult', [
            'client_id' => $this->client->id,
            'year' => 2025,
            'office_id' => $this->office->id,
        ])->assertStatus(422)->assertJsonPath('code', 'CLIENT_OFFICE_ID_REJECTED');

        $this->getJson('/api/v1/fiscal/simples-mei/clients/'.$this->client->id.'/regime-resolutions')
            ->assertOk()
            ->assertJsonPath('data', [])
            ->assertJsonPath('provenance.serpro_called', false);

        $this->postJson('/api/v1/fiscal/simples-mei/regime-resolution/consult', [
            'client_id' => $this->client->id,
            'year' => 2025,
            'correlation_id' => 'api-regime-104',
        ])->assertCreated()
            ->assertJsonPath('serpro_call', 'QUEUED');
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

    public function test_gerar_das_com_mutacoes_desligadas_bloqueia_sem_stub_e_sem_http(): void
    {
        Http::preventStrayRequests();
        $this->assertNull(config('fiscal_monitoring.simples_mei.das_stub_without_mutating'));

        $svc = app(SimplesMeiQueryService::class);
        $run = $svc->enqueueDasGeneration(
            $this->office,
            $this->client,
            'SIMPLES_NACIONAL',
            periodKey: '2026-02',
            correlationId: 'corr-das-mutating-disabled',
            dispatch: false,
        );

        $request = new FiscalAdapterRequest(
            office: $this->office,
            client: $this->client,
            run: $run,
            systemCode: $run->system_code,
            serviceCode: $run->service_code,
            operationCode: $run->operation_code,
            trigger: $run->trigger,
            competence: $run->competence,
        );
        $adapter = app(FiscalAdapterRegistry::class)->resolve($request);
        $this->assertTrue($adapter->mutability()->isMutating());
        $directResult = $adapter->execute($request);
        $this->assertSame(FiscalRunResult::Blocked, $directResult->result);
        $this->assertSame('MUTATING_DISABLED', $directResult->errorCode);

        $out = app(FiscalMonitoringRunService::class)->execute($run->id);

        $this->assertSame(FiscalRunResult::Blocked, $out->result);
        $this->assertSame('MUTATING_DISABLED', $out->error_code);
        $this->assertSame(0, $out->items_processed);
        $this->assertSame(0, $out->evidenceArtifacts()->count());
        $this->assertSame(0, $out->findings()->count());
        $this->assertSame(0, FiscalGuideStub::query()->withoutGlobalScopes()
            ->where('office_id', $this->office->id)
            ->where('client_id', $this->client->id)
            ->count());
        $this->assertFalse(AuditLog::query()
            ->where('office_id', $this->office->id)
            ->where('action', 'fiscal.simples_mei.das_stub')
            ->exists());
        Http::assertNothingSent();
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
