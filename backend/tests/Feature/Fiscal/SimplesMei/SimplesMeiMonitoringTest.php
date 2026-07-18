<?php

namespace Tests\Feature\Fiscal\SimplesMei;

use App\Contracts\IntegraContadorClient;
use App\DTO\Fiscal\FiscalAdapterRequest;
use App\DTO\Serpro\IntegraResponse;
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
use App\Models\SerproServiceCatalogEntry;
use App\Models\TaxProxyPower;
use App\Models\User;
use App\Services\Fiscal\SimplesMei\CcmeiPostConsultService;
use App\Services\Fiscal\SimplesMei\CcmeiRegistrationStatusPostConsultService;
use App\Services\Fiscal\SimplesMei\DefisDeclarationProjector;
use App\Services\Fiscal\SimplesMei\DefisDeclarationReferenceStore;
use App\Services\Fiscal\SimplesMei\DefisLatestDeclarationPostConsultService;
use App\Services\Fiscal\SimplesMei\DefisSpecificDeclarationPostConsultService;
use App\Services\Fiscal\SimplesMei\Pgdasd\PgdasdConsDeclaracao13Codec;
use App\Services\Fiscal\SimplesMei\Pgdasd\PgdasdDocumentCodecs;
use App\Services\Fiscal\SimplesMei\Pgdasd\PgdasdPostConsultService;
use App\Services\Fiscal\SimplesMei\Pgmei\PgmeiDividaAtiva24Codec;
use App\Services\Fiscal\SimplesMei\Pgmei\PgmeiPostConsultService;
use App\Services\Fiscal\SimplesMei\RegimeApplicabilityService;
use App\Services\Fiscal\SimplesMei\RegimeResolutionCodec;
use App\Services\Fiscal\SimplesMei\RegimeResolutionPostConsultService;
use App\Services\Fiscal\SimplesMei\SimplesMeiAdapter;
use App\Services\Fiscal\SimplesMei\SimplesMeiCatalog;
use App\Services\Fiscal\SimplesMei\SimplesMeiQueryService;
use App\Services\Fiscal\SimplesMei\SimplesMeiResponseMapper;
use App\Services\FiscalMonitoring\FiscalAdapterRegistry;
use App\Services\FiscalMonitoring\FiscalMonitoringRunService;
use App\Services\Integra\ContributorCnpjResolver;
use App\Services\Integra\IntegraEligibilityService;
use App\Services\Integra\OfficeSerproAuthorizationService;
use App\Services\Serpro\Catalog\OperationCoordinateResolver;
use App\Services\Serpro\Catalog\OperationKeyMap;
use App\Services\Serpro\SerproContractService;
use App\Services\Serpro\SerproOperationService;
use App\Support\CurrentOffice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Support\Fakes\FakeIntegraContadorClient;
use Tests\Support\UsesSerproTestDoubles;
use Tests\TestCase;

/**
 * Integração tasks 8.3–8.8: regime, evidência, emissão DAS, mutantes, endpoints, idempotência.
 */
class SimplesMeiMonitoringTest extends TestCase
{
    use RefreshDatabase, UsesSerproTestDoubles;

    private Office $office;

    private Client $client;

    private User $admin;

    private FakeIntegraContadorClient $fake;

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
        $this->fake = app(FakeIntegraContadorClient::class);
        $this->fake->reset();
        // O adapter recebe SerproOperationService concretamente. O client é
        // resolvido a cada chamada por esse serviço, portanto este rebind do
        // double offline preserva os gates sem permitir HTTP externo.
        $this->app->instance(IntegraContadorClient::class, $this->fake);
        $this->installOfflineSimplesRegistry();

        $this->seedIntegraChain();
        $this->seedOfflineCatalog();
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

        $out = $this->executeRun($run);

        $this->assertSame(FiscalRunStatus::Completed, $out->status);
        $this->assertSame(FiscalRunResult::Success, $out->result);
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

        $out = $this->executeRun($run);

        $this->assertSame(FiscalRunResult::Failed, $out->result);
        $this->assertSame('CAPABILITY_DISABLED', $out->error_code);
        $this->assertDatabaseCount('fiscal_evidence_artifacts', 0);
    }

    public function test_ccmei_123_offline_persiste_resumo_sem_identificador_fiscal(): void
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

        $out = $this->executeRun($run);

        $this->assertSame(FiscalRunStatus::Completed, $out->status);
        $this->assertSame(FiscalRunResult::Success, $out->result);
        $this->assertDatabaseHas('ccmei_registration_status_projections', [
            'office_id' => $this->office->id,
            'client_id' => $this->client->id,
            'status' => 'ATIVA',
            'enquadrado_mei' => true,
        ]);
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

        $out = $this->executeRun($run);

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

    public function test_regime_102_fixture_offline_unverified_persiste_calendario_idempotente(): void
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

        $out = $this->executeRun($run);
        $this->executeRun($run);

        $this->assertSame(FiscalRunResult::Success, $out->result);
        $this->assertSame('UNVERIFIED', $out->source_provenance->value);
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

    public function test_regime_103_fixture_offline_unverified_projeta_opcao_sem_expor_retorno_bruto(): void
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

        $out = $this->executeRun($run);

        $this->assertSame(FiscalRunStatus::Completed, $out->status);
        $this->assertSame(FiscalRunResult::Success, $out->result);
        $this->assertSame('UNVERIFIED', $out->source_provenance->value);
        $this->assertSame(FiscalSituation::UpToDate, $out->situation);
        $this->assertSame([
            ['calendar_year' => 2025, 'regime_apuracao' => 'CAIXA'],
        ], array_map(static fn (array $item): array => [
            'calendar_year' => $item['calendar_year'],
            'regime_apuracao' => $item['regime_apuracao'],
        ], app(RegimeApplicabilityService::class)->listRegimeOptions($this->office, $this->client)));

    }

    public function test_defis_142_fixture_offline_unverified_projeta_lista_sem_identificador_declaracao(): void
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

        $out = $this->executeRun($run);

        $this->assertSame(FiscalRunResult::Success, $out->result);
        $this->assertSame('UNVERIFIED', $out->source_provenance->value);
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
        $this->assertSame($reference->id, $store->store($this->office, $this->client, '000000002025001', $run->id, 'UNVERIFIED')->id);
        $this->assertDatabaseCount('defis_declaration_references', 1);

        $this->expectException(\RuntimeException::class);
        $store->read($reference, Office::factory()->create());
    }

    public function test_defis_143_fixture_offline_unverified_guarda_pdfs_no_cofre_sem_identificador_na_projecao(): void
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

        $out = $this->executeRun($run);

        $this->assertSame(FiscalRunResult::Success, $out->result);
        $this->assertSame('UNVERIFIED', $out->source_provenance->value);
        $this->assertDatabaseCount('defis_latest_declaration_artifacts', 2);
        $this->assertDatabaseHas('defis_latest_declaration_artifacts', [
            'office_id' => $this->office->id,
            'client_id' => $this->client->id,
            'calendar_year' => 2025,
            'kind' => 'RECIBO',
        ]);
        $this->assertDatabaseMissing('defis_latest_declaration_artifacts', ['digest' => 'DEFIS-FAKE-NAO-EXPOSTO']);
    }

    public function test_defis_144_fixture_offline_unverified_recupera_referencia_opaca_sem_expor_id_defis(): void
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
        $this->executeRun($list);
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

        $out = $this->executeRun($run);

        $this->assertSame(FiscalRunResult::Success, $out->result);
        $this->assertSame('UNVERIFIED', $out->source_provenance->value);
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

    public function test_regime_104_fixture_offline_unverified_guarda_texto_no_cofre_e_lista_somente_descritor_local(): void
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

        $out = $this->executeRun($run);

        $this->assertSame(FiscalRunResult::Success, $out->result);
        $this->assertSame('UNVERIFIED', $out->source_provenance->value);
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

    public function test_sem_procuracao_bloqueia_consulta_sem_projecao(): void
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

        $out = $this->executeRun($run);

        $this->assertSame(FiscalRunResult::Blocked, $out->result);
        $this->assertSame('PROXY_POWER_MISSING', $out->error_code);
        $this->assertDatabaseCount('fiscal_evidence_artifacts', 0);
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
        $this->assertFalse((bool) config('fiscal_monitoring.simples_mei.das_stub_without_mutating', false));

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
        $this->assertFalse(AuditLog::query()
            ->where('subject_type', FiscalMonitoringRun::class)
            ->where('subject_id', $out->id)
            ->where('result', 'SUCCESS')
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

        $this->executeRun($a);
        $this->executeRun($a);

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
        $this->executeRun((int) $runId);

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
            'credentials_exposed' => false,
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

    private function installOfflineSimplesRegistry(): void
    {
        $registry = new FiscalAdapterRegistry;
        $executor = app(SerproOperationService::class);

        foreach (SimplesMeiCatalog::all() as $definition) {
            $registry->register(new SimplesMeiAdapter(
                definition: $definition,
                eligibility: app(IntegraEligibilityService::class),
                operations: $executor,
                mapper: app(SimplesMeiResponseMapper::class),
                contracts: app(SerproContractService::class),
                authorizations: app(OfficeSerproAuthorizationService::class),
                regimeApplicability: app(RegimeApplicabilityService::class),
                contributors: app(ContributorCnpjResolver::class),
                pgdasdCodec13: app(PgdasdConsDeclaracao13Codec::class),
                pgdasdDocumentCodecs: app(PgdasdDocumentCodecs::class),
                pgdasdPostConsult: app(PgdasdPostConsultService::class),
                pgmeiCodec24: app(PgmeiDividaAtiva24Codec::class),
                pgmeiPostConsult: app(PgmeiPostConsultService::class),
                ccmeiPostConsult: app(CcmeiPostConsultService::class),
                ccmeiRegistrationStatusPost: app(CcmeiRegistrationStatusPostConsultService::class),
                regimeResolutionCodec: app(RegimeResolutionCodec::class),
                regimeResolutionPost: app(RegimeResolutionPostConsultService::class),
                defisProjector: app(DefisDeclarationProjector::class),
                defisLatestDeclarationPost: app(DefisLatestDeclarationPostConsultService::class),
                defisSpecificDeclarationPost: app(DefisSpecificDeclarationPostConsultService::class),
                defisReferences: app(DefisDeclarationReferenceStore::class),
            ));
        }

        $this->app->instance(FiscalAdapterRegistry::class, $registry);
    }

    private function seedOfflineCatalog(): void
    {
        $resolver = app(OperationCoordinateResolver::class);

        foreach (SimplesMeiCatalog::all() as $definition) {
            $operationKey = OperationKeyMap::require(
                null,
                $definition->systemCode,
                $definition->serviceCode,
                $definition->operationCode,
            );
            $coordinates = $resolver->resolve($operationKey);

            SerproServiceCatalogEntry::query()->updateOrCreate(
                [
                    'catalog_version' => 999001,
                    'environment' => SerproEnvironment::Trial,
                    'operation_key' => $operationKey,
                ],
                [
                    'solution_code' => $definition->systemCode,
                    'service_code' => $definition->serviceCode,
                    'operation_code' => $definition->operationCode,
                    'id_sistema' => $coordinates['id_sistema'],
                    'id_servico' => $coordinates['id_servico'],
                    'versao_sistema' => $coordinates['versao_sistema'],
                    'functional_route' => $coordinates['route']->value,
                    'official_state' => $coordinates['official_state']?->value,
                    'platform_support' => $coordinates['platform_support']->value,
                    'dados_mode' => $coordinates['dados_mode'],
                    'label' => $coordinates['label'],
                    'is_mutating' => $coordinates['is_mutating'],
                    'is_enabled' => true,
                    'required_proxy_power' => $coordinates['required_proxy_power'],
                    'billable_class' => $coordinates['billable_class'],
                    'cache_ttl_seconds' => 0,
                    'rate_limit_per_minute' => 0,
                    'coverage' => 'FULL',
                    'metadata' => ['required_proxy_powers' => $coordinates['required_proxy_powers']],
                    'effective_from' => now(),
                    'effective_to' => null,
                ],
            );
        }
    }

    private function executeRun(FiscalMonitoringRun|int $run): FiscalMonitoringRun
    {
        $run = is_int($run)
            ? FiscalMonitoringRun::query()->withoutGlobalScopes()->findOrFail($run)
            : $run;

        $this->fake->queue(
            $run->system_code,
            $run->service_code,
            $run->operation_code,
            $this->offlineResponseFor($run),
        );

        return app(FiscalMonitoringRunService::class)->execute($run->id);
    }

    /**
     * Fixtures locais mínimas para codecs e projeções. A proveniência é
     * explicitamente UNVERIFIED: não representa Trial válido nem canário real.
     */
    private function offlineResponseFor(FiscalMonitoringRun $run): IntegraResponse
    {
        $period = (string) ($run->competence?->period_key ?? '2026-01');
        $progress = is_array($run->progress) ? $run->progress : [];
        $force = strtoupper((string) ($progress['force_status'] ?? ''));
        $service = strtoupper($run->service_code);
        $operation = strtoupper($run->operation_code);

        $body = match ($service) {
            'PGDASD' => [
                'dto_version' => '1',
                'data' => [
                    'competence' => $period,
                    'status' => $force === 'INCONCLUSIVO' ? 'INCONCLUSIVO' : 'ENTREGUE',
                    'receipt_number' => $force === 'INCONCLUSIVO' ? null : 'REC-PGDASD-'.str_replace('-', '', $period),
                    'declaration_id' => 'DECL-PGDASD-1',
                    'transmitted_at' => $force === 'INCONCLUSIVO' ? null : now()->toIso8601String(),
                ],
            ],
            'REGIME_APURACAO' => match ($operation) {
                'CONSULTAR_ANOS_CALENDARIOS' => [
                    'status' => 200,
                    'dados' => json_encode([
                        ['anoCalendario' => 2025, 'regimeApurado' => 'CAIXA'],
                        ['anoCalendario' => 2026, 'regimeApurado' => 'COMPETENCIA'],
                    ], JSON_THROW_ON_ERROR),
                ],
                'CONSULTAR_RESOLUCAO' => [
                    'status' => 200,
                    'mensagens' => [],
                    'dados' => json_encode([
                        'anoCalendario' => (int) $period,
                        'textoResolucao' => base64_encode('Resolução do Regime de Caixa (fixture offline).'),
                    ], JSON_THROW_ON_ERROR),
                ],
                default => [
                    'status' => 200,
                    'mensagens' => [],
                    'dados' => json_encode([
                        'anoCalendario' => (int) $period,
                        'regimeEscolhido' => 'CAIXA',
                        'dataHoraOpcao' => ((int) $period).'0101000000',
                    ], JSON_THROW_ON_ERROR),
                ],
            },
            'DEFIS' => match ($operation) {
                'CONSULTAR_ULTIMA_DECLARACAO_RECIBO' => [
                    'status' => 200,
                    'mensagens' => [],
                    'dados' => json_encode([
                        'ano' => (int) $period,
                        'idDefis' => '000000002025001',
                        'recibo' => base64_encode('%PDF-1.7\n% fixture recibo DEFIS'),
                        'declaracao' => base64_encode('%PDF-1.7\n% fixture declaracao DEFIS'),
                    ], JSON_THROW_ON_ERROR),
                ],
                'CONSULTAR_DECLARACAO_RECIBO' => [
                    'status' => 200,
                    'mensagens' => [],
                    'dados' => json_encode([
                        'recibo' => base64_encode('%PDF-1.7\n% fixture recibo DEFIS especifica'),
                        'declaracao' => base64_encode('%PDF-1.7\n% fixture declaracao DEFIS especifica'),
                    ], JSON_THROW_ON_ERROR),
                ],
                default => [
                    'status' => 200,
                    'mensagens' => [],
                    'dados' => json_encode([[
                        'anoCalendario' => (int) $period,
                        'idDefis' => '000000002025001',
                        'tipo' => '1',
                        'dataHora' => ((int) $period).'0101000000',
                    ]], JSON_THROW_ON_ERROR),
                ],
            },
            'CCMEI' => [
                'dto_version' => '1',
                'data' => [[
                    'cnpj' => '00000000000000',
                    'situacao' => 'ATIVA',
                    'enquadradoMei' => true,
                ]],
            ],
            'PGMEI' => [
                'dto_version' => '1',
                'data' => [
                    'competence' => $period,
                    'status' => 'EMITIDO',
                    'das_number' => 'DAS-MEI-1',
                    'due_date' => now()->addDays(10)->toDateString(),
                    'amount' => 71.60,
                ],
            ],
            default => ['dto_version' => '1', 'data' => []],
        };

        return new IntegraResponse(
            success: true,
            httpStatus: 200,
            body: $body,
            headers: ['x-test-double' => 'offline'],
            simulated: false,
            correlationId: $run->correlation_id,
            latencyMs: 0,
            sourceProvenance: 'UNVERIFIED',
        );
    }
}
