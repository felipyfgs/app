<?php

namespace Tests\Feature\FiscalMonitoring;

use App\Enums\DctfwebArtifactKind;
use App\Enums\DctfwebMutationStatus;
use App\Enums\DctfwebTransmissionStatus;
use App\Enums\FiscalPaymentStatus;
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
use App\Models\DctfwebDeclaration;
use App\Models\DctfwebEvidenceVersion;
use App\Models\DctfwebMutationAttempt;
use App\Models\FiscalEvidenceArtifact;
use App\Models\FiscalLastUpdateEvent;
use App\Models\FiscalMonitoringRun;
use App\Models\FiscalPendingItem;
use App\Models\MitApuracao;
use App\Models\Office;
use App\Models\OfficeSerproAuthorization;
use App\Models\SerproContract;
use App\Models\TaxProxyPower;
use App\Models\User;
use App\Services\FiscalMonitoring\FiscalMonitoringRunService;
use App\Services\Integra\Dctfweb\DctfwebCodes;
use App\Services\Integra\Dctfweb\DctfwebEventIngestionService;
use App\Services\Integra\Dctfweb\DctfwebMutationGuard;
use App\Services\Integra\Dctfweb\MitApuracaoService;
use App\Services\Integra\FakeIntegraContadorClient;
use App\Support\CurrentOffice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tasks 9.1–9.4, 9.8–9.9 — DCTFWeb/MIT (sem parcelamentos).
 */
class DctfwebMitMonitoringTest extends TestCase
{
    use RefreshDatabase;

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
            'features.modules.dctfweb_mit.enabled' => true,
            'features.modules.dctfweb_mit.mutating_enabled' => false,
            'features.modules.dctfweb_mit.allow_all_offices' => true,
            'fiscal_monitoring.enabled' => true,
            'fiscal_monitoring.kill_switch' => false,
            'fiscal_monitoring.mutating_enabled' => false,
            'serpro.trial.use_fake_clients' => true,
        ]);

        $this->office = Office::factory()->create();
        $this->client = Client::factory()->forOffice($this->office)->create([
            'root_cnpj' => '11222333',
        ]);
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
            'activated_at' => now(),
        ]);

        $auth = OfficeSerproAuthorization::query()->create([
            'office_id' => $this->office->id,
            'environment' => SerproEnvironment::Trial,
            'status' => SerproAuthorizationStatus::TokenActive,
            'author_identity_type' => 'CNPJ',
            'author_identity' => '99888777000166',
            'certificate_mode' => 'EXTERNAL_SIGNATURE',
            'termo_vault_object_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            'termo_valid_to' => now()->addYear(),
            'procurador_token_vault_object_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            'procurador_token_expires_at' => now()->addHours(6),
        ]);

        foreach (
            [
                ['INTEGRA_DCTFWEB', 'DCTFWEB', 'DCTFWEB'],
                ['INTEGRA_MIT', 'MIT', 'MIT'],
            ] as [$system, $service, $power]
        ) {
            TaxProxyPower::query()->create([
                'office_id' => $this->office->id,
                'client_id' => $this->client->id,
                'office_serpro_authorization_id' => $auth->id,
                'author_identity' => '99888777000166',
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

    public function test_evento_duplicado_nao_duplica_run_nem_reconcilia_duas_vezes(): void
    {
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
        $runs = app(FiscalMonitoringRunService::class);
        $period = '2026-02';

        $this->fake->queue(
            DctfwebCodes::SYSTEM_DCTFWEB,
            DctfwebCodes::SERVICE_DCTFWEB,
            DctfwebCodes::OP_CONSULTAR_RECIBO,
            FakeIntegraContadorClient::productiveRecibo($period, 'REC-ORIG', retificadora: false),
        );

        $competence = app(\App\Services\Integra\Dctfweb\DctfwebCompetenceResolver::class)
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

    public function test_mit_encerrado_sem_transmissao_dctfweb_mantem_estados_independentes(): void
    {
        $period = '2026-03';
        $runs = app(FiscalMonitoringRunService::class);

        $this->fake->queue(
            DctfwebCodes::SYSTEM_MIT,
            DctfwebCodes::SERVICE_MIT,
            DctfwebCodes::OP_MIT_SITUACAO,
            FakeIntegraContadorClient::productiveMitEncerrado($period),
        );

        $competence = app(\App\Services\Integra\Dctfweb\DctfwebCompetenceResolver::class)
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
        $competence = app(\App\Services\Integra\Dctfweb\DctfwebCompetenceResolver::class)
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
        // Habilita mutação só para exercitar timeout incerto
        config([
            'fiscal_monitoring.mutating_enabled' => true,
            'features.mutating.enabled' => true,
            'features.modules.dctfweb_mit.mutating_enabled' => true,
            'fortify.two_factor_required' => true,
        ]);

        // Catálogo mutante OFF por default — habilita para o path de timeout.
        \App\Models\SerproServiceCatalogEntry::query()
            ->where('solution_code', DctfwebCodes::SYSTEM_DCTFWEB)
            ->where('service_code', DctfwebCodes::SERVICE_DCTFWEB)
            ->where('operation_code', DctfwebCodes::OP_TRANSMITIR)
            ->update(['is_enabled' => true, 'is_mutating' => true]);

        // Actor + 2FA recente obrigatórios (fail-closed no guard)
        $this->actingAs($this->admin);
        app(CurrentOffice::class)->resolve($this->admin);
        app(\App\Services\Fiscal\Mutations\RecentTwoFactorGate::class)->markConfirmed($this->admin);
        $this->withSession([
            \App\Services\Fiscal\Mutations\RecentTwoFactorGate::SESSION_KEY => time(),
        ]);

        $period = '2026-05';
        $this->fake->queue(
            DctfwebCodes::SYSTEM_DCTFWEB,
            DctfwebCodes::SERVICE_DCTFWEB,
            DctfwebCodes::OP_TRANSMITIR,
            FakeIntegraContadorClient::uncertainTimeout(),
        );

        $runs = app(FiscalMonitoringRunService::class);
        $competence = app(\App\Services\Integra\Dctfweb\DctfwebCompetenceResolver::class)
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
        $this->assertSame('UNCERTAIN_TIMEOUT', $done->error_code);
        $this->assertSame(FiscalSituation::Error, $done->situation);

        $attempt = DctfwebMutationAttempt::query()->withoutGlobalScopes()
            ->where('office_id', $this->office->id)
            ->where('operation_code', DctfwebCodes::OP_TRANSMITIR)
            ->first();
        $this->assertNotNull($attempt);
        $this->assertSame(DctfwebMutationStatus::Uncertain, $attempt->status);
        $this->assertNotNull($attempt->blocked_retry_until);

        $gate = app(DctfwebMutationGuard::class)->assertMayMutate(
            office: $this->office,
            client: $this->client,
            systemCode: DctfwebCodes::SYSTEM_DCTFWEB,
            serviceCode: DctfwebCodes::SERVICE_DCTFWEB,
            operationCode: DctfwebCodes::OP_TRANSMITIR,
            periodKey: $period,
            actor: $this->admin,
        );
        $this->assertFalse($gate['allowed']);
        $this->assertSame('UNCERTAIN_RETRY_BLOCKED', $gate['code']);

        // Sem actor → fail-closed
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

        // Nova tentativa mutante bloqueada no adapter (mesmo com actor)
        $this->fake->queue(
            DctfwebCodes::SYSTEM_DCTFWEB,
            DctfwebCodes::SERVICE_DCTFWEB,
            DctfwebCodes::OP_TRANSMITIR,
            FakeIntegraContadorClient::productiveRecibo($period, 'SHOULD-NOT-RUN'),
        );
        $run2 = $runs->enqueueManual(
            $this->office,
            $this->client,
            DctfwebCodes::SYSTEM_DCTFWEB,
            DctfwebCodes::SERVICE_DCTFWEB,
            DctfwebCodes::OP_TRANSMITIR,
            competence: $competence,
            actorId: $this->admin->id,
            correlationId: 'unc-2',
            dispatch: false,
        );
        $done2 = $runs->execute($run2->id);
        $this->assertSame(FiscalRunStatus::Blocked, $done2->status);
        $this->assertSame('UNCERTAIN_RETRY_BLOCKED', $done2->error_code);
        // Fake não deve ter sido chamado na segunda (só a 1ª timeout)
        $this->assertSame(1, $this->fake->calls);
    }

    public function test_darf_emitido_nao_marca_pagamento(): void
    {
        $period = '2026-06';
        $this->fake->queue(
            DctfwebCodes::SYSTEM_DCTFWEB,
            DctfwebCodes::SERVICE_DCTFWEB,
            DctfwebCodes::OP_EMITIR_DARF,
            new \App\DTO\Serpro\IntegraResponse(
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
        $competence = app(\App\Services\Integra\Dctfweb\DctfwebCompetenceResolver::class)
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
        $runs->execute($run->id);

        $darf = \App\Models\DctfwebDarfDocument::query()->withoutGlobalScopes()
            ->where('office_id', $this->office->id)->first();
        $this->assertNotNull($darf);
        $this->assertSame(FiscalPaymentStatus::Unknown, $darf->payment_status);
        $this->assertSame('DARF-99', $darf->document_number);

        $decl = DctfwebDeclaration::query()->withoutGlobalScopes()
            ->where('period_key', $period)->first();
        $this->assertNotNull($decl);
        $this->assertSame(FiscalPaymentStatus::Unknown, $decl->payment_status);
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
}
