<?php

namespace Tests\Feature\FiscalMonitoring;

use App\Contracts\SerproOperationExecutor;
use App\DTO\Serpro\IntegraResponse;
use App\Enums\DctfwebArtifactKind;
use App\Enums\DctfwebTransmissionStatus;
use App\Enums\FiscalRunStatus;
use App\Enums\FiscalSituation;
use App\Enums\MitEncerramentoStatus;
use App\Enums\OfficeRole;
use App\Enums\SerproAuthorizationStatus;
use App\Enums\SerproContractStatus;
use App\Enums\SerproEnvironment;
use App\Enums\TaxProxyPowerSource;
use App\Enums\TaxProxyPowerStatus;
use App\Models\Client;
use App\Models\DctfwebDarfDocument;
use App\Models\DctfwebDeclaration;
use App\Models\DctfwebEvidenceVersion;
use App\Models\Establishment;
use App\Models\FiscalEvidenceArtifact;
use App\Models\FiscalLastUpdateEvent;
use App\Models\FiscalMonitoringRun;
use App\Models\FiscalPendingItem;
use App\Models\MitApuracao;
use App\Models\Office;
use App\Models\OfficeSerproAuthorization;
use App\Models\SerproContract;
use App\Models\SerproServiceCatalogEntry;
use App\Models\TaxProxyPower;
use App\Models\User;
use App\Services\Fiscal\Mutations\RecentTwoFactorGate;
use App\Services\FiscalMonitoring\FiscalAdapterRegistry;
use App\Services\FiscalMonitoring\FiscalMonitoringRunService;
use App\Services\Integra\Dctfweb\DctfwebAdapterRegistrar;
use App\Services\Integra\Dctfweb\DctfwebCodes;
use App\Services\Integra\Dctfweb\DctfwebCompetenceResolver;
use App\Services\Integra\Dctfweb\DctfwebEventIngestionService;
use App\Services\Integra\Dctfweb\DctfwebMutationGuard;
use App\Services\Integra\Dctfweb\MitApuracaoService;
use App\Support\CurrentOffice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Support\Fakes\FakeIntegraContadorClient;
use Tests\Support\FakeSerproOperationExecutor;
use Tests\Support\UsesSerproTestDoubles;
use Tests\TestCase;

/**
 * Tasks 9.1–9.4, 9.8–9.9 — DCTFWeb/MIT (sem parcelamentos).
 */
class DctfwebMitMonitoringTest extends TestCase
{
    use RefreshDatabase;
    use UsesSerproTestDoubles;

    private Office $office;

    private Client $client;

    private User $admin;

    private FakeIntegraContadorClient $fake;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            // Cenário histórico inteiramente offline; o double é instalado
            // explicitamente e nunca constitui evidência de homologação.
            'serpro.default_environment' => SerproEnvironment::Trial->value,
            'features.global_enabled' => true,
            'features.kill_switch' => false,
            'features.mutating.enabled' => false,
            'features.modules.dctfweb_mit.enabled' => true,
            'features.modules.dctfweb_mit.mutating_enabled' => false,
            'features.modules.dctfweb_mit.allow_all_offices' => true,
            'fiscal_monitoring.enabled' => true,
            'fiscal_monitoring.kill_switch' => false,
            'fiscal_monitoring.mutating_enabled' => false,
            // Double offline explícito; o runtime exercitado continua real-only.
            'serpro.capabilities.dctfweb' => 'real',
        ]);

        $this->office = Office::factory()->create();
        $this->client = Client::factory()->forOffice($this->office)->create([
            'root_cnpj' => '11222333',
        ]);
        Establishment::factory()->forClient($this->client, '11222333000181')->create();
        $this->admin = User::factory()->forOffice($this->office, OfficeRole::Admin)->withTwoFactorConfirmed()->create();
        $this->fake = app(FakeIntegraContadorClient::class);
        $this->fake->reset();
        $this->seedSerproChain();
    }

    private function seedSerproChain(): void
    {
        SerproContract::query()->create([
            'environment' => SerproEnvironment::Trial,
            'status' => SerproContractStatus::Active,
            'contractor_cnpj' => '11222333000181',
            'health_status' => 'OK',
            'credentials_exposed' => false,
            'activated_at' => now(),
        ]);

        $auth = OfficeSerproAuthorization::query()->create([
            'office_id' => $this->office->id,
            'environment' => SerproEnvironment::Trial,
            'status' => SerproAuthorizationStatus::TokenActive,
            'author_identity_type' => 'CNPJ',
            'author_identity' => '99888777000100',
            'certificate_mode' => 'EXTERNAL_SIGNATURE',
            'termo_vault_object_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            'termo_valid_to' => now()->addYear(),
            'procurador_token_vault_object_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            'procurador_token_expires_at' => now()->addHours(6),
        ]);

        // Legado (catálogo por service) + código e-CAC 00103 (dctfweb.* / mit.* no manifesto).
        foreach (
            [
                ['INTEGRA_DCTFWEB', 'DCTFWEB', 'DCTFWEB'],
                ['INTEGRA_MIT', 'MIT', 'MIT'],
                ['INTEGRA_DCTFWEB', 'DCTFWEB', '00103'],
            ] as [$system, $service, $power]
        ) {
            TaxProxyPower::query()->create([
                'office_id' => $this->office->id,
                'client_id' => $this->client->id,
                'office_serpro_authorization_id' => $auth->id,
                'author_identity' => '99888777000100',
                'contributor_cnpj' => '11222333000181',
                'system_code' => $system,
                'service_code' => $service,
                'power_code' => $power,
                'source' => TaxProxyPowerSource::ManualOfficialEvidence,
                'status' => TaxProxyPowerStatus::Active,
                'valid_from' => now()->subDay(),
                'valid_to' => now()->addYear(),
            ]);
        }
    }

    private function installOfflineReadExecutor(): void
    {
        $this->app->instance(
            SerproOperationExecutor::class,
            new FakeSerproOperationExecutor($this->fake),
        );

        // Os adapters são construídos no boot com o executor central. Para os
        // quatro cenários legados de leitura, recriar somente o registry DCTF
        // após o override mantém mutações e gates nos demais testes intactos.
        $registry = new FiscalAdapterRegistry;
        app(DctfwebAdapterRegistrar::class)->register($registry);
        $this->app->instance(FiscalAdapterRegistry::class, $registry);
    }

    public function test_evento_duplicado_nao_duplica_run_nem_reconcilia_duas_vezes(): void
    {
        $this->installOfflineReadExecutor();

        $this->fake->queue(
            DctfwebCodes::SYSTEM_DCTFWEB,
            DctfwebCodes::SERVICE_DCTFWEB,
            DctfwebCodes::OP_CONSULTAR_RECIBO,
            FakeIntegraContadorClient::productiveRecibo('2026-01', 'REC-A'),
        );

        $events = app(DctfwebEventIngestionService::class);

        $first = $events->ingestAndDirect(
            office: $this->office,
            client: $this->client,
            periodKey: '2026-01',
            eventType: DctfwebCodes::EVENT_TRANSMISSAO,
            externalId: 'evt-dctf-1',
            payloadDigest: 'digest-1',
            enqueue: false,
        );
        $this->assertFalse($first['duplicate']);
        $this->assertNotNull($first['run']);
        $this->assertSame('2026-01', $first['period_key']);
        $this->assertNotNull($first['run']->competence_id);

        $second = $events->ingestAndDirect(
            office: $this->office,
            client: $this->client,
            periodKey: '2026-01',
            eventType: DctfwebCodes::EVENT_TRANSMISSAO,
            externalId: 'evt-dctf-1',
            payloadDigest: 'digest-1',
            enqueue: false,
        );
        $this->assertTrue($second['duplicate']);
        $this->assertSame(1, FiscalLastUpdateEvent::query()->withoutGlobalScopes()
            ->where('office_id', $this->office->id)->count());
        $this->assertSame(1, FiscalMonitoringRun::query()->withoutGlobalScopes()
            ->where('office_id', $this->office->id)
            ->where('trigger', 'EVENT')
            ->count());

        $done = app(FiscalMonitoringRunService::class)->execute($first['run']->id);
        $this->assertSame(FiscalRunStatus::Completed, $done->status);

        $decl = DctfwebDeclaration::query()->withoutGlobalScopes()
            ->where('office_id', $this->office->id)
            ->where('period_key', '2026-01')
            ->first();
        $this->assertNotNull($decl);
        $this->assertSame(DctfwebTransmissionStatus::Transmitted, $decl->transmission_status);
        $this->assertSame('REC-A', $decl->receipt_number);

        // Segunda execução da mesma run é no-op (terminal)
        app(FiscalMonitoringRunService::class)->execute($first['run']->id);
        $this->assertSame(1, $this->fake->calls);
        $this->assertSame(1, DctfwebEvidenceVersion::query()->withoutGlobalScopes()
            ->where('office_id', $this->office->id)
            ->where('artifact_kind', DctfwebArtifactKind::Recibo->value)
            ->count());
    }

    public function test_retificacao_cria_nova_versao_sem_sobrescrever_evidencia_anterior(): void
    {
        $this->installOfflineReadExecutor();

        $runs = app(FiscalMonitoringRunService::class);
        $period = '2026-02';

        $this->fake->queue(
            DctfwebCodes::SYSTEM_DCTFWEB,
            DctfwebCodes::SERVICE_DCTFWEB,
            DctfwebCodes::OP_CONSULTAR_RECIBO,
            FakeIntegraContadorClient::productiveRecibo($period, 'REC-ORIG', retificadora: false),
        );

        $competence = app(DctfwebCompetenceResolver::class)
            ->resolve($this->office, $this->client, $period);

        $run1 = $runs->enqueueManual(
            $this->office,
            $this->client,
            DctfwebCodes::SYSTEM_DCTFWEB,
            DctfwebCodes::SERVICE_DCTFWEB,
            DctfwebCodes::OP_CONSULTAR_RECIBO,
            competence: $competence,
            correlationId: 'rect-1',
            dispatch: false,
        );
        $runs->execute($run1->id);

        $v1 = DctfwebEvidenceVersion::query()->withoutGlobalScopes()
            ->where('office_id', $this->office->id)
            ->where('artifact_kind', DctfwebArtifactKind::Recibo->value)
            ->where('is_current', true)
            ->first();
        $this->assertNotNull($v1);
        $this->assertSame(1, $v1->version);
        $this->assertFalse($v1->is_retification);
        $sha1 = $v1->content_sha256;
        $artifactId1 = $v1->evidence_artifact_id;

        // XML original
        $this->fake->queue(
            DctfwebCodes::SYSTEM_DCTFWEB,
            DctfwebCodes::SERVICE_DCTFWEB,
            DctfwebCodes::OP_CONSULTAR_XML,
            FakeIntegraContadorClient::productiveRecibo($period, 'REC-ORIG', xmlHint: '<dctf>original</dctf>'),
        );
        $runXml1 = $runs->enqueueManual(
            $this->office,
            $this->client,
            DctfwebCodes::SYSTEM_DCTFWEB,
            DctfwebCodes::SERVICE_DCTFWEB,
            DctfwebCodes::OP_CONSULTAR_XML,
            competence: $competence,
            correlationId: 'xml-1',
            dispatch: false,
        );
        $runs->execute($runXml1->id);

        $xmlV1 = DctfwebEvidenceVersion::query()->withoutGlobalScopes()
            ->where('artifact_kind', DctfwebArtifactKind::Xml->value)
            ->where('is_current', true)
            ->first();
        $this->assertNotNull($xmlV1);
        $xmlSha1 = $xmlV1->content_sha256;
        $xmlArtifact1 = $xmlV1->evidence_artifact_id;

        // Retificação com recibo e XML diferentes
        $this->fake->queue(
            DctfwebCodes::SYSTEM_DCTFWEB,
            DctfwebCodes::SERVICE_DCTFWEB,
            DctfwebCodes::OP_CONSULTAR_RECIBO,
            FakeIntegraContadorClient::productiveRecibo($period, 'REC-RET', retificadora: true),
        );
        $run2 = $runs->enqueueManual(
            $this->office,
            $this->client,
            DctfwebCodes::SYSTEM_DCTFWEB,
            DctfwebCodes::SERVICE_DCTFWEB,
            DctfwebCodes::OP_CONSULTAR_RECIBO,
            competence: $competence,
            correlationId: 'rect-2',
            dispatch: false,
        );
        $runs->execute($run2->id);

        $this->fake->queue(
            DctfwebCodes::SYSTEM_DCTFWEB,
            DctfwebCodes::SERVICE_DCTFWEB,
            DctfwebCodes::OP_CONSULTAR_XML,
            FakeIntegraContadorClient::productiveRecibo($period, 'REC-RET', retificadora: true, xmlHint: '<dctf>retificada</dctf>'),
        );
        $runXml2 = $runs->enqueueManual(
            $this->office,
            $this->client,
            DctfwebCodes::SYSTEM_DCTFWEB,
            DctfwebCodes::SERVICE_DCTFWEB,
            DctfwebCodes::OP_CONSULTAR_XML,
            competence: $competence,
            correlationId: 'xml-2',
            dispatch: false,
        );
        $runs->execute($runXml2->id);

        $reciboVersions = DctfwebEvidenceVersion::query()->withoutGlobalScopes()
            ->where('office_id', $this->office->id)
            ->where('artifact_kind', DctfwebArtifactKind::Recibo->value)
            ->orderBy('version')
            ->get();
        $this->assertCount(2, $reciboVersions);
        $this->assertFalse($reciboVersions[0]->is_current);
        $this->assertTrue($reciboVersions[1]->is_current);
        $this->assertTrue($reciboVersions[1]->is_retification);
        $this->assertNotSame($sha1, $reciboVersions[1]->content_sha256);

        // Artefato original permanece no cofre (imutável)
        $this->assertTrue(
            FiscalEvidenceArtifact::query()->withoutGlobalScopes()->whereKey($artifactId1)->exists()
        );
        $this->assertSame($sha1, FiscalEvidenceArtifact::query()->withoutGlobalScopes()
            ->whereKey($artifactId1)->value('content_sha256'));

        $xmlVersions = DctfwebEvidenceVersion::query()->withoutGlobalScopes()
            ->where('artifact_kind', DctfwebArtifactKind::Xml->value)
            ->orderBy('version')
            ->get();
        $this->assertCount(2, $xmlVersions);
        $this->assertFalse($xmlVersions[0]->is_current);
        $this->assertTrue($xmlVersions[1]->is_current);
        $this->assertNotSame($xmlSha1, $xmlVersions[1]->content_sha256);
        $this->assertTrue(
            FiscalEvidenceArtifact::query()->withoutGlobalScopes()->whereKey($xmlArtifact1)->exists()
        );

        $decl = DctfwebDeclaration::query()->withoutGlobalScopes()
            ->where('office_id', $this->office->id)
            ->where('period_key', $period)
            ->first();
        $this->assertNotNull($decl);
        $this->assertSame(DctfwebTransmissionStatus::Rectified, $decl->transmission_status);
        $this->assertSame('RECTIFICADORA', $decl->declaration_type);
    }

    public function test_historico_e_download_dctfweb_preservam_mime_e_nome_xml_sanitizados(): void
    {
        $this->installOfflineReadExecutor();

        $period = '2026-06';
        $competence = app(DctfwebCompetenceResolver::class)
            ->resolve($this->office, $this->client, $period);
        $this->fake->queue(
            DctfwebCodes::SYSTEM_DCTFWEB,
            DctfwebCodes::SERVICE_DCTFWEB,
            DctfwebCodes::OP_CONSULTAR_XML,
            FakeIntegraContadorClient::productiveRecibo($period, 'REC-XML', xmlHint: '<dctf>seguro</dctf>'),
        );

        $run = app(FiscalMonitoringRunService::class)->enqueueManual(
            $this->office,
            $this->client,
            DctfwebCodes::SYSTEM_DCTFWEB,
            DctfwebCodes::SERVICE_DCTFWEB,
            DctfwebCodes::OP_CONSULTAR_XML,
            competence: $competence,
            correlationId: 'dctfweb-xml-download',
            dispatch: false,
        );
        app(FiscalMonitoringRunService::class)->execute($run->id);

        $version = DctfwebEvidenceVersion::query()->withoutGlobalScopes()
            ->where('office_id', $this->office->id)
            ->where('artifact_kind', DctfwebArtifactKind::Xml->value)
            ->firstOrFail();

        $this->actingAs($this->admin);
        app(CurrentOffice::class)->resolve($this->admin);
        $path = '/api/v1/fiscal/dctfweb/clients/'.$this->client->id.'/history';
        $this->getJson($path)
            ->assertOk()
            ->assertJsonPath('data.periods.0.documents.0.content_type', 'application/xml')
            ->assertJsonPath(
                'data.periods.0.documents.0.filename',
                'dctfweb-xml-'.$version->id.'.xml',
            )
            ->assertJsonPath(
                'data.periods.0.documents.0.download_path',
                '/api/v1/fiscal/dctfweb/clients/'.$this->client->id.'/evidence/'.$version->id.'/download',
            );

        $download = $this->get(
            '/api/v1/fiscal/dctfweb/clients/'.$this->client->id.'/evidence/'.$version->id.'/download',
        );
        $download->assertOk();
        $this->assertSame('application/xml', $download->headers->get('Content-Type'));
        $this->assertStringContainsString(
            'dctfweb-xml-'.$version->id.'.xml',
            (string) $download->headers->get('Content-Disposition'),
        );
    }

    public function test_mit_encerrado_sem_transmissao_dctfweb_mantem_estados_independentes(): void
    {
        $this->installOfflineReadExecutor();

        $period = '2026-03';
        $runs = app(FiscalMonitoringRunService::class);

        $this->fake->queue(
            DctfwebCodes::SYSTEM_MIT,
            DctfwebCodes::SERVICE_MIT,
            DctfwebCodes::OP_MIT_SITUACAO,
            FakeIntegraContadorClient::productiveMitEncerrado($period),
        );

        $competence = app(DctfwebCompetenceResolver::class)
            ->resolve($this->office, $this->client, $period, DctfwebCodes::CATEGORY_MIT);

        $run = $runs->enqueueManual(
            $this->office,
            $this->client,
            DctfwebCodes::SYSTEM_MIT,
            DctfwebCodes::SERVICE_MIT,
            DctfwebCodes::OP_MIT_SITUACAO,
            competence: $competence,
            correlationId: 'mit-1',
            dispatch: false,
        );
        $done = $runs->execute($run->id);
        $this->assertSame(FiscalRunStatus::Completed, $done->status);

        $mit = MitApuracao::query()->withoutGlobalScopes()
            ->where('office_id', $this->office->id)
            ->where('period_key', $period)
            ->first();
        $this->assertNotNull($mit);
        $this->assertSame(MitEncerramentoStatus::Encerrado, $mit->encerramento_status);
        // Sem recibo DCTFWeb → transmissão NÃO vira TRANSMITTED
        $this->assertTrue(in_array(
            $mit->dctfweb_transmission_status,
            [DctfwebTransmissionStatus::Unknown, DctfwebTransmissionStatus::Pending],
            true,
        ));
        $this->assertFalse($mit->dctfweb_transmission_status->isConfirmed());

        $stages = $mit->toPublicArray()['stages'];
        $this->assertSame(MitEncerramentoStatus::Encerrado->value, $stages['mit_encerramento']);
        $this->assertNotSame(DctfwebTransmissionStatus::Transmitted->value, $stages['dctfweb_transmissao']);

        $decl = DctfwebDeclaration::query()->withoutGlobalScopes()
            ->where('office_id', $this->office->id)
            ->where('period_key', $period)
            ->first();
        $this->assertNotNull($decl);
        $this->assertFalse($decl->transmission_status->isConfirmed());

        $this->assertGreaterThanOrEqual(1, FiscalPendingItem::query()->withoutGlobalScopes()
            ->where('office_id', $this->office->id)
            ->where('code', 'MIT_ENCERRADO_SEM_DCTFWEB')
            ->count());
    }

    public function test_transmissao_e_encerramento_bloqueados_com_flags_mutantes_off(): void
    {
        $this->actingAs($this->admin);
        app(CurrentOffice::class)->resolve($this->admin);

        $this->postJson('/api/v1/fiscal/dctfweb/transmit', [
            'client_id' => $this->client->id,
            'period_key' => '2026-04',
            'confirmation' => true,
        ])->assertForbidden()
            ->assertJsonPath('code', 'MUTATING_DISABLED');

        $this->postJson('/api/v1/fiscal/mit/encerrar', [
            'client_id' => $this->client->id,
            'period_key' => '2026-04',
            'confirmation' => true,
        ])->assertForbidden()
            ->assertJsonPath('code', 'MUTATING_DISABLED');

        // Adapter mutante também bloqueado no núcleo
        config(['fiscal_monitoring.mutating_enabled' => false]);
        $runs = app(FiscalMonitoringRunService::class);
        $competence = app(DctfwebCompetenceResolver::class)
            ->resolve($this->office, $this->client, '2026-04');
        $run = $runs->enqueueManual(
            $this->office,
            $this->client,
            DctfwebCodes::SYSTEM_DCTFWEB,
            DctfwebCodes::SERVICE_DCTFWEB,
            DctfwebCodes::OP_TRANSMITIR,
            competence: $competence,
            correlationId: 'mut-off',
            dispatch: false,
        );
        $done = $runs->execute($run->id);
        $this->assertSame(FiscalRunStatus::Blocked, $done->status);
        $this->assertSame(0, $this->fake->calls);
    }

    public function test_timeout_incerto_bloqueia_retry_ate_reconciliacao(): void
    {
        // Flags mutantes ON + actor/2FA — ainda assim o executor tipado bloqueia
        // Emitir/Declarar nesta change (MutationAuthorization hard-block, task 6.7).
        config([
            'fiscal_monitoring.mutating_enabled' => true,
            'features.mutating.enabled' => true,
            'features.modules.dctfweb_mit.mutating_enabled' => true,
            'fortify.two_factor_required' => true,
        ]);

        SerproServiceCatalogEntry::query()
            ->where('solution_code', DctfwebCodes::SYSTEM_DCTFWEB)
            ->where('service_code', DctfwebCodes::SERVICE_DCTFWEB)
            ->where('operation_code', DctfwebCodes::OP_TRANSMITIR)
            ->update(['is_enabled' => true, 'is_mutating' => true]);

        $this->actingAs($this->admin);
        app(CurrentOffice::class)->resolve($this->admin);
        app(RecentTwoFactorGate::class)->markConfirmed($this->admin);
        $this->withSession([
            RecentTwoFactorGate::SESSION_KEY => time(),
        ]);

        $period = '2026-05';
        $this->fake->queue(
            DctfwebCodes::SYSTEM_DCTFWEB,
            DctfwebCodes::SERVICE_DCTFWEB,
            DctfwebCodes::OP_TRANSMITIR,
            FakeIntegraContadorClient::uncertainTimeout(),
        );

        $runs = app(FiscalMonitoringRunService::class);
        $competence = app(DctfwebCompetenceResolver::class)
            ->resolve($this->office, $this->client, $period);

        $run = $runs->enqueueManual(
            $this->office,
            $this->client,
            DctfwebCodes::SYSTEM_DCTFWEB,
            DctfwebCodes::SERVICE_DCTFWEB,
            DctfwebCodes::OP_TRANSMITIR,
            competence: $competence,
            actorId: $this->admin->id,
            correlationId: 'unc-1',
            dispatch: false,
        );
        $done = $runs->execute($run->id);

        $this->assertSame(FiscalRunStatus::Failed, $done->status);
        $this->assertSame('MUTATION_DISABLED', $done->error_code);
        $this->assertSame(FiscalSituation::Error, $done->situation);
        // Nenhum HTTP mutante deve sair (fila não consumida).
        $this->assertSame(0, $this->fake->calls);

        // Guard sem actor permanece fail-closed (independente do executor).
        $gateNoActor = app(DctfwebMutationGuard::class)->assertMayMutate(
            office: $this->office,
            client: $this->client,
            systemCode: DctfwebCodes::SYSTEM_DCTFWEB,
            serviceCode: DctfwebCodes::SERVICE_DCTFWEB,
            operationCode: DctfwebCodes::OP_TRANSMITIR,
            periodKey: $period,
            actor: null,
        );
        $this->assertFalse($gateNoActor['allowed']);
        $this->assertSame('ACTOR_REQUIRED', $gateNoActor['code']);
    }

    public function test_darf_emitido_nao_marca_pagamento(): void
    {
        // EMITIR_DARF é mutante: executor central bloqueia nesta change (task 6.7).
        // Quando a autorização tipada for liberada, o adapter deve persistir DARF
        // com payment_status=UNKNOWN (nunca pago por inferência de emissão).
        $period = '2026-06';
        $this->fake->queue(
            DctfwebCodes::SYSTEM_DCTFWEB,
            DctfwebCodes::SERVICE_DCTFWEB,
            DctfwebCodes::OP_EMITIR_DARF,
            new IntegraResponse(
                success: true,
                httpStatus: 200,
                body: [
                    'competencia' => $period,
                    'numero' => 'DARF-99',
                    'valor' => 1500.55,
                    'vencimento' => '2026-07-20',
                ],
                simulated: false,
            ),
        );

        $runs = app(FiscalMonitoringRunService::class);
        $competence = app(DctfwebCompetenceResolver::class)
            ->resolve($this->office, $this->client, $period);
        $run = $runs->enqueueManual(
            $this->office,
            $this->client,
            DctfwebCodes::SYSTEM_DCTFWEB,
            DctfwebCodes::SERVICE_DCTFWEB,
            DctfwebCodes::OP_EMITIR_DARF,
            competence: $competence,
            correlationId: 'darf-1',
            dispatch: false,
        );
        $done = $runs->execute($run->id);

        $this->assertSame(FiscalRunStatus::Failed, $done->status);
        $this->assertSame('MUTATION_DISABLED', $done->error_code);
        $this->assertSame(0, $this->fake->calls);

        $darf = DctfwebDarfDocument::query()->withoutGlobalScopes()
            ->where('office_id', $this->office->id)->first();
        $this->assertNull($darf, 'sem emissão real não deve materializar DARF');
    }

    public function test_api_evento_e_listagem_tenant_scoped(): void
    {
        $this->actingAs($this->admin);
        app(CurrentOffice::class)->resolve($this->admin);

        $this->postJson('/api/v1/fiscal/dctfweb/events', [
            'client_id' => $this->client->id,
            'period_key' => '2026-07',
            'event_type' => DctfwebCodes::EVENT_ULTIMA_ATUALIZACAO,
            'external_id' => 'api-evt-1',
            'enqueue' => false,
        ])->assertCreated()
            ->assertJsonPath('data.duplicate', false)
            ->assertJsonPath('data.period_key', '2026-07');

        $this->getJson('/api/v1/fiscal/dctfweb/declarations')
            ->assertOk()
            ->assertJsonPath('data.0.period_key', '2026-07');

        // MIT listagem vazia até projeção
        app(MitApuracaoService::class)->findOrCreate($this->office, $this->client, '2026-07');
        $this->getJson('/api/v1/fiscal/mit/apuracoes')
            ->assertOk()
            ->assertJsonPath('data.0.period_key', '2026-07')
            ->assertJsonPath('data.0.stages.dctfweb_transmissao', DctfwebTransmissionStatus::Unknown->value);
    }

    public function test_lista_apuracoes_317_projeta_fixture_offline_sem_artefato_documental(): void
    {
        Queue::fake();
        $this->actingAs($this->admin);
        app(CurrentOffice::class)->resolve($this->admin);

        $beforeRuns = FiscalMonitoringRun::query()->withoutGlobalScopes()->count();
        $this->postJson('/api/v1/fiscal/mit/lista-apuracoes', [
            'client_id' => $this->client->id,
            'mesApuracao' => 5,
        ])->assertUnprocessable();
        $this->assertSame($beforeRuns, FiscalMonitoringRun::query()->withoutGlobalScopes()->count());

        $this->postJson('/api/v1/fiscal/mit/lista-apuracoes', [
            'client_id' => $this->client->id,
            'anoApuracao' => 2026,
            'mesApuracao' => 5,
            'situacaoApuracao' => 2,
            'correlation_id' => 'mit-317-fixture',
        ])->assertCreated()
            ->assertJsonPath('data.operation_code', DctfwebCodes::OP_MIT_LISTAR_APURACOES)
            ->assertJsonPath('data.operation_key', DctfwebCodes::OPERATION_KEY_MIT_LISTA_APURACOES)
            ->assertJsonPath('serpro_call', 'QUEUED');

        $run = FiscalMonitoringRun::query()->withoutGlobalScopes()
            ->where('correlation_id', 'mit-317-fixture')
            ->firstOrFail();
        $this->assertSame([
            'anoApuracao' => 2026,
            'mesApuracao' => 5,
            'situacaoApuracao' => 2,
        ], $run->progress['mit_lista_apuracoes']);

        $fixture = json_decode(
            (string) file_get_contents(base_path('tests/fixtures/serpro/mit/listaapuracoes317.json')),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        $this->fake->queue(
            DctfwebCodes::SYSTEM_MIT,
            DctfwebCodes::SERVICE_MIT,
            DctfwebCodes::OP_MIT_LISTAR_APURACOES,
            new IntegraResponse(success: true, httpStatus: 200, body: $fixture, simulated: true),
        );

        $done = app(FiscalMonitoringRunService::class)->execute($run->id);
        $this->assertSame(FiscalRunStatus::Completed, $done->status);
        $this->assertSame(1, $this->fake->calls);
        $this->assertSame('mit.listaapuracoes', $this->fake->history[0]->operationKey);
        $this->assertSame([
            'anoApuracao' => 2026,
            'mesApuracao' => 5,
            'situacaoApuracao' => 2,
        ], $this->fake->history[0]->businessData);
        $this->assertSame(0, FiscalEvidenceArtifact::query()->withoutGlobalScopes()->count());

        $this->getJson('/api/v1/fiscal/mit/lista-apuracoes?client_id='.$this->client->id.'&year=2026')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.period_key', '2026-05')
            ->assertJsonPath('data.0.lista_apuracoes_317.id_apuracao', 71001)
            ->assertJsonPath('provenance.serpro_called', false)
            ->assertJsonMissingPath('data.0.metadata');
    }

    public function test_lista_apuracoes_317_rejeita_office_id_e_esconde_cliente_de_outro_escritorio(): void
    {
        $this->actingAs($this->admin);
        app(CurrentOffice::class)->resolve($this->admin);

        $this->postJson('/api/v1/fiscal/mit/lista-apuracoes', [
            'client_id' => $this->client->id,
            'anoApuracao' => 2026,
            'office_id' => 999999,
        ])->assertUnprocessable()
            ->assertJsonPath('code', 'CLIENT_OFFICE_ID_REJECTED');

        $otherOffice = Office::factory()->create();
        $otherClient = Client::factory()->forOffice($otherOffice)->create();
        $otherAdmin = User::factory()->forOffice($otherOffice, OfficeRole::Admin)->withTwoFactorConfirmed()->create();
        $this->actingAs($otherAdmin);
        app(CurrentOffice::class)->resolve($otherAdmin);

        $this->getJson('/api/v1/fiscal/mit/lista-apuracoes?client_id='.$this->client->id)
            ->assertNotFound();
        $this->postJson('/api/v1/fiscal/mit/lista-apuracoes', [
            'client_id' => $this->client->id,
            'anoApuracao' => 2026,
        ])->assertNotFound();
        $this->assertNotNull($otherClient);
    }

    public function test_lista_apuracoes_317_rejeita_resposta_malformada_sem_projetar(): void
    {
        $this->fake->queue(
            DctfwebCodes::SYSTEM_MIT,
            DctfwebCodes::SERVICE_MIT,
            DctfwebCodes::OP_MIT_LISTAR_APURACOES,
            new IntegraResponse(success: true, httpStatus: 200, body: [
                'Apuracoes' => [[
                    'periodoApuracao' => '202613',
                    'idApuracao' => 1,
                    'situacao' => 1,
                    'eventoEspecial' => false,
                    'valorTotalApurado' => 0,
                ]],
            ], simulated: true),
        );

        $run = app(FiscalMonitoringRunService::class)->enqueueManual(
            $this->office,
            $this->client,
            DctfwebCodes::SYSTEM_MIT,
            DctfwebCodes::SERVICE_MIT,
            DctfwebCodes::OP_MIT_LISTAR_APURACOES,
            correlationId: 'mit-317-invalid-response',
            dispatch: false,
        );
        $run->forceFill([
            'operation_key' => DctfwebCodes::OPERATION_KEY_MIT_LISTA_APURACOES,
            'progress' => ['mit_lista_apuracoes' => ['anoApuracao' => 2026]],
        ])->save();

        $done = app(FiscalMonitoringRunService::class)->execute($run->id);
        $this->assertSame(FiscalRunStatus::Failed, $done->status);
        $this->assertSame('MIT_LISTA_APURACOES_RESPONSE_INVALID', $done->error_code);
        $this->assertSame(0, MitApuracao::query()->withoutGlobalScopes()
            ->where('office_id', $this->office->id)
            ->count());
        $this->assertSame(0, FiscalEvidenceArtifact::query()->withoutGlobalScopes()->count());
    }
}
