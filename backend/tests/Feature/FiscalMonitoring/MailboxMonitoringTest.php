<?php

namespace Tests\Feature\FiscalMonitoring;

use App\Contracts\CaixaPostalClient;
use App\Contracts\DteIndicatorClient;
use App\DTO\Mailbox\CaixaPostalDetailResult;
use App\Enums\MailboxAccessAction;
use App\Enums\MailboxDteStatus;
use App\Enums\MailboxMessagesConsultStatus;
use App\Enums\MailboxTriageStatus;
use App\Enums\OfficeRole;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\FiscalLastUpdateEvent;
use App\Models\FiscalMonitoringRun;
use App\Models\MailboxAccessEvent;
use App\Models\MailboxAlert;
use App\Models\MailboxAttachment;
use App\Models\MailboxMessage;
use App\Models\Office;
use App\Models\User;
use App\Services\FiscalMonitoring\FiscalMonitoringRunService;
use App\Services\Integra\Mailbox\MailboxEventService;
use App\Services\Integra\Mailbox\MailboxMessageStore;
use App\Services\Integra\Mailbox\MailboxStateService;
use App\Support\CurrentOffice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Fakes\FakeCaixaPostalClient;
use Tests\Support\Fakes\FakeDteIndicatorClient;
use Tests\Support\UsesSerproTestDoubles;
use Tests\TestCase;

/**
 * Tasks 10.5–10.9 — Caixa Postal / DTE:
 * evento repetido, tenant cruzado, sigilo, leitura interna ≠ oficial.
 */
class MailboxMonitoringTest extends TestCase
{
    use RefreshDatabase;
    use UsesSerproTestDoubles;

    private Office $office;

    private Client $client;

    private User $admin;

    private User $viewer;

    private FakeCaixaPostalClient $caixaFake;

    private FakeDteIndicatorClient $dteFake;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'features.global_enabled' => true,
            'features.kill_switch' => false,
            'features.modules.mailbox.enabled' => true,
            'features.modules.mailbox.allow_all_offices' => true,
            'fiscal_monitoring.enabled' => true,
            'fiscal_monitoring.kill_switch' => false,
            'fiscal_monitoring.scheduler.enabled' => true,
            'fiscal_monitoring.mutating_enabled' => false,
        ]);

        $this->office = Office::factory()->create();
        $this->client = Client::factory()->forOffice($this->office)->create();
        $this->admin = User::factory()->forOffice($this->office, OfficeRole::Admin)->withTwoFactorConfirmed()->create();
        $this->viewer = User::factory()->forOffice($this->office, OfficeRole::Viewer)->withTwoFactorConfirmed()->create();

        $this->caixaFake = app(FakeCaixaPostalClient::class);
        $this->caixaFake->resetDefaults();
        $this->dteFake = app(FakeDteIndicatorClient::class);
        $this->dteFake->status = MailboxDteStatus::Active;
        $this->dteFake->success = true;
        $this->dteFake->calls = 0;

        $this->assertInstanceOf(CaixaPostalClient::class, $this->caixaFake);
        $this->assertInstanceOf(DteIndicatorClient::class, $this->dteFake);
    }

    private function actAs(User $user): void
    {
        $this->actingAs($user);
        app(CurrentOffice::class)->clear();
        app(CurrentOffice::class)->resolve($user);
    }

    public function test_evento_nova_mensagem_agenda_consulta_idempotente(): void
    {
        $events = app(MailboxEventService::class);

        $first = $events->ingestNewMessageEvent(
            office: $this->office,
            client: $this->client,
            externalEventId: 'evt-mbx-1',
            payloadDigest: 'digest-a',
            enqueue: false,
        );

        $this->assertFalse($first['duplicate']);
        $this->assertNotNull($first['run']);
        $this->assertSame('INTEGRA_CAIXAPOSTAL', $first['run']->system_code);
        $this->assertSame('CAIXA_POSTAL', $first['run']->service_code);
        $this->assertSame('LISTAR', $first['run']->operation_code);

        app(FiscalMonitoringRunService::class)->execute($first['run']->id);

        $this->assertSame(1, $this->caixaFake->listCalls);
        $this->assertSame(1, MailboxMessage::query()->withoutGlobalScopes()
            ->where('office_id', $this->office->id)->count());
        $this->assertSame(1, MailboxAlert::query()->withoutGlobalScopes()
            ->where('office_id', $this->office->id)->count());
    }

    public function test_evento_repetido_nao_duplica_mensagem_chamada_nem_alerta(): void
    {
        $events = app(MailboxEventService::class);

        $first = $events->ingestNewMessageEvent(
            office: $this->office,
            client: $this->client,
            externalEventId: 'evt-mbx-dup',
            payloadDigest: 'same',
            enqueue: false,
        );
        $second = $events->ingestNewMessageEvent(
            office: $this->office,
            client: $this->client,
            externalEventId: 'evt-mbx-dup',
            payloadDigest: 'same',
            enqueue: false,
        );

        $this->assertTrue($second['duplicate']);
        $this->assertSame(1, FiscalLastUpdateEvent::query()->withoutGlobalScopes()
            ->where('office_id', $this->office->id)->count());
        $this->assertSame(1, FiscalMonitoringRun::query()->withoutGlobalScopes()
            ->where('office_id', $this->office->id)
            ->where('system_code', 'INTEGRA_CAIXAPOSTAL')
            ->count());

        app(FiscalMonitoringRunService::class)->execute($first['run']->id);
        // Reexecução da mesma run terminal não chama de novo o adapter com sucesso parcial —
        // mas se enfileirarmos de novo o mesmo evento, zero runs extras.
        $third = $events->ingestNewMessageEvent(
            office: $this->office,
            client: $this->client,
            externalEventId: 'evt-mbx-dup',
            payloadDigest: 'same',
            enqueue: false,
        );
        $this->assertTrue($third['duplicate']);

        $this->assertSame(1, $this->caixaFake->listCalls);
        $this->assertSame(1, MailboxMessage::query()->withoutGlobalScopes()
            ->where('office_id', $this->office->id)->count());
        $this->assertSame(1, MailboxAlert::query()->withoutGlobalScopes()
            ->where('office_id', $this->office->id)->count());
    }

    public function test_dte_ativo_sem_mensagens_consultadas_mantem_proveniencia_separada(): void
    {
        $runSvc = app(FiscalMonitoringRunService::class);
        $run = $runSvc->enqueueManual(
            $this->office,
            $this->client,
            'INTEGRA_CAIXAPOSTAL',
            'DTE',
            'INDICADOR',
            dispatch: false,
        );
        $runSvc->execute($run->id);

        $state = app(MailboxStateService::class)->findForOffice($this->office, (int) $this->client->id);
        $this->assertNotNull($state);
        $this->assertSame(MailboxDteStatus::Active, $state->dte_status);
        $this->assertSame(MailboxMessagesConsultStatus::Unknown, $state->messages_status);

        $public = $state->toPublicArray();
        $this->assertSame('ACTIVE', $public['dte']['status']);
        $this->assertSame('UNKNOWN', $public['messages']['status']);
        $this->assertSame('DTE_INDICATOR', $public['dte']['source']);
        $this->assertNull($public['messages']['source']);
    }

    public function test_lista_e_detalhe_armazenam_corpo_anexo_com_hash_e_retencao(): void
    {
        $runSvc = app(FiscalMonitoringRunService::class);

        $listRun = $runSvc->enqueueManual(
            $this->office,
            $this->client,
            'INTEGRA_CAIXAPOSTAL',
            'CAIXA_POSTAL',
            'LISTAR',
            dispatch: false,
        );
        $runSvc->execute($listRun->id);

        $msg = MailboxMessage::query()->withoutGlobalScopes()
            ->where('office_id', $this->office->id)->firstOrFail();
        $this->assertSame(MailboxTriageStatus::New, $msg->triage_status);
        $this->assertSame('FISCAL_RESTRICTED', $msg->sensitivity_class);
        $this->assertNotNull($msg->retention_until);
        $this->assertFalse($msg->has_body);

        // Detalhe via store (run DETALHE com progress)
        $detailRun = $runSvc->enqueueManual(
            $this->office,
            $this->client,
            'INTEGRA_CAIXAPOSTAL',
            'CAIXA_POSTAL',
            'DETALHE',
            correlationId: 'detail-'.$msg->external_id,
            dispatch: false,
        );
        $detailRun->forceFill([
            'progress' => ['external_message_id' => $msg->external_id],
        ])->save();
        $runSvc->execute($detailRun->id);

        $msg->refresh();
        $this->assertTrue($msg->has_body);
        $this->assertNotNull($msg->body_vault_object_id);
        $this->assertNotNull($msg->body_sha256);
        $this->assertGreaterThan(0, $msg->body_byte_size);
        $this->assertGreaterThanOrEqual(1, $msg->attachment_count);

        $att = MailboxAttachment::query()->withoutGlobalScopes()
            ->where('mailbox_message_id', $msg->id)->firstOrFail();
        $this->assertNotNull($att->vault_object_id);
        $this->assertNotNull($att->content_sha256);
        $this->assertNotNull($att->retention_until);
    }

    public function test_tenant_cruzado_nao_ve_mensagem_nem_alerta(): void
    {
        $runSvc = app(FiscalMonitoringRunService::class);
        $listRun = $runSvc->enqueueManual(
            $this->office,
            $this->client,
            'INTEGRA_CAIXAPOSTAL',
            'CAIXA_POSTAL',
            'LISTAR',
            dispatch: false,
        );
        $runSvc->execute($listRun->id);

        $msg = MailboxMessage::query()->withoutGlobalScopes()
            ->where('office_id', $this->office->id)->firstOrFail();

        $other = Office::factory()->create();
        $otherAdmin = User::factory()->forOffice($other, OfficeRole::Admin)->withTwoFactorConfirmed()->create();
        $this->actAs($otherAdmin);

        $this->getJson('/api/v1/fiscal/mailbox/messages')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->getJson('/api/v1/fiscal/mailbox/messages/'.$msg->id)
            ->assertNotFound();

        $this->getJson('/api/v1/fiscal/mailbox/alerts')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->getJson('/api/v1/operations/inbox')
            ->assertOk();
        $types = collect($this->getJson('/api/v1/operations/inbox')->json('data'))->pluck('type');
        $this->assertFalse($types->contains('mailbox_message'));
        $this->assertFalse($types->contains('mailbox_message_urgent'));
    }

    public function test_viewer_abre_mensagem_registra_trilha_sem_alterar_leitura_oficial(): void
    {
        $runSvc = app(FiscalMonitoringRunService::class);
        $listRun = $runSvc->enqueueManual(
            $this->office,
            $this->client,
            'INTEGRA_CAIXAPOSTAL',
            'CAIXA_POSTAL',
            'LISTAR',
            dispatch: false,
        );
        $runSvc->execute($listRun->id);

        $msg = MailboxMessage::query()->withoutGlobalScopes()
            ->where('office_id', $this->office->id)->firstOrFail();

        // Detalhe com corpo
        $detail = $this->caixaFake->getMessageDetail($msg->external_id);
        app(MailboxMessageStore::class)->applyDetail($this->office, $this->client, $detail);

        $msg->refresh();
        $officialBefore = $msg->official_read_indicator; // false do fake
        $this->assertFalse((bool) $officialBefore);

        $this->actAs($this->viewer);

        $response = $this->getJson('/api/v1/fiscal/mailbox/messages/'.$msg->id);
        $response->assertOk()
            ->assertJsonPath('meta.official_read_unchanged', true)
            ->assertJsonPath('data.official_read_indicator', false)
            ->assertJsonPath('data.triage_status', MailboxTriageStatus::InReview->value);

        $msg->refresh();
        $this->assertFalse((bool) $msg->official_read_indicator);
        $this->assertSame(MailboxTriageStatus::InReview, $msg->triage_status);

        $this->assertSame(1, MailboxAccessEvent::query()->withoutGlobalScopes()
            ->where('office_id', $this->office->id)
            ->where('mailbox_message_id', $msg->id)
            ->where('action', MailboxAccessAction::View->value)
            ->count());

        // Download do corpo
        $dl = $this->get('/api/v1/fiscal/mailbox/messages/'.$msg->id.'/body');
        $dl->assertOk();
        $this->assertStringContainsString('Corpo simulado', $dl->streamedContent());

        $msg->refresh();
        $this->assertFalse((bool) $msg->official_read_indicator);

        $this->assertSame(1, MailboxAccessEvent::query()->withoutGlobalScopes()
            ->where('action', MailboxAccessAction::DownloadBody->value)
            ->count());
    }

    public function test_triagem_interna_nao_executa_leitura_oficial_remota(): void
    {
        $runSvc = app(FiscalMonitoringRunService::class);
        $listRun = $runSvc->enqueueManual(
            $this->office,
            $this->client,
            'INTEGRA_CAIXAPOSTAL',
            'CAIXA_POSTAL',
            'LISTAR',
            dispatch: false,
        );
        $runSvc->execute($listRun->id);

        $msg = MailboxMessage::query()->withoutGlobalScopes()
            ->where('office_id', $this->office->id)->firstOrFail();
        $msg->forceFill(['official_read_indicator' => false])->save();

        $detailCallsBefore = $this->caixaFake->detailCalls;

        $this->actAs($this->admin);
        $this->patchJson('/api/v1/fiscal/mailbox/messages/'.$msg->id.'/triage', [
            'triage_status' => 'RESOLVED',
            'note' => 'Analisado internamente',
        ])->assertOk()
            ->assertJsonPath('data.triage_status', 'RESOLVED')
            ->assertJsonPath('meta.official_read_indicator', false);

        $msg->refresh();
        $this->assertSame(MailboxTriageStatus::Resolved, $msg->triage_status);
        $this->assertFalse((bool) $msg->official_read_indicator);
        $this->assertSame($detailCallsBefore, $this->caixaFake->detailCalls);
    }

    public function test_alerta_e_inbox_sem_corpo_anexo_nem_assunto_fiscal(): void
    {
        $secretSubject = 'Assunto secreto NÃO DEVE VAZAR 99999';
        $secretBody = 'Corpo fiscal confidencial XYZ-SECRET-BODY';

        $this->caixaFake->listItems = [[
            'external_id' => 'msg-secret-1',
            'category_code' => 'INTIMACAO',
            'category_label' => 'Intimação',
            'sender_code' => 'RFB',
            'sender_label' => 'Receita Federal',
            'subject' => $secretSubject,
            'received_at' => now()->subHour()->toIso8601String(),
            'due_at' => now()->addDays(2)->toIso8601String(),
            'severity_hint' => 'CRITICAL',
            'official_read' => false,
            'has_attachment' => true,
        ]];
        $this->caixaFake->detailsByExternalId['msg-secret-1'] = new CaixaPostalDetailResult(
            success: true,
            externalId: 'msg-secret-1',
            bodyBytes: $secretBody,
            bodyContentType: 'text/plain',
            attachments: [[
                'external_id' => 'a1',
                'filename' => 'intimacao-secreta.pdf',
                'content_type' => 'application/pdf',
                'bytes' => '%PDF secret payload',
            ]],
            categoryCode: 'INTIMACAO',
            categoryLabel: 'Intimação',
            senderCode: 'RFB',
            senderLabel: 'Receita Federal',
            subject: $secretSubject,
            receivedAt: now()->subHour()->toIso8601String(),
            dueAt: now()->addDays(2)->toIso8601String(),
            severityHint: 'CRITICAL',
            officialRead: false,
            simulated: true,
        );

        $runSvc = app(FiscalMonitoringRunService::class);
        $listRun = $runSvc->enqueueManual(
            $this->office,
            $this->client,
            'INTEGRA_CAIXAPOSTAL',
            'CAIXA_POSTAL',
            'LISTAR',
            dispatch: false,
        );
        $runSvc->execute($listRun->id);

        $alert = MailboxAlert::query()->withoutGlobalScopes()
            ->where('office_id', $this->office->id)->firstOrFail();

        $this->assertStringNotContainsString($secretSubject, $alert->title);
        $this->assertStringNotContainsString($secretSubject, $alert->body);
        $this->assertStringNotContainsString($secretBody, $alert->title);
        $this->assertStringNotContainsString($secretBody, $alert->body);
        $this->assertStringNotContainsString('intimacao-secreta', $alert->body);
        $this->assertStringContainsString('Intimação', $alert->title);
        $this->assertStringContainsString('Receita Federal', $alert->body);

        $this->actAs($this->admin);
        $inbox = $this->getJson('/api/v1/operations/inbox')->assertOk()->json('data');
        $mbItems = collect($inbox)->filter(fn ($i) => str_starts_with($i['type'], 'mailbox_'));
        $this->assertGreaterThanOrEqual(1, $mbItems->count());
        foreach ($mbItems as $item) {
            $this->assertStringNotContainsString($secretSubject, $item['title'] ?? '');
            $this->assertStringNotContainsString($secretSubject, $item['body'] ?? '');
            $this->assertStringNotContainsString($secretBody, $item['body'] ?? '');
            $this->assertStringNotContainsString('intimacao-secreta', $item['body'] ?? '');
        }

        // API de alertas também sanitizada
        $apiAlerts = $this->getJson('/api/v1/fiscal/mailbox/alerts')->assertOk()->json('data');
        foreach ($apiAlerts as $a) {
            $this->assertStringNotContainsString($secretBody, $a['body'] ?? '');
            $this->assertStringNotContainsString($secretSubject, $a['title'] ?? '');
        }
    }

    public function test_auditoria_de_view_nao_registra_corpo(): void
    {
        $runSvc = app(FiscalMonitoringRunService::class);
        $listRun = $runSvc->enqueueManual(
            $this->office,
            $this->client,
            'INTEGRA_CAIXAPOSTAL',
            'CAIXA_POSTAL',
            'LISTAR',
            dispatch: false,
        );
        $runSvc->execute($listRun->id);

        $msg = MailboxMessage::query()->withoutGlobalScopes()
            ->where('office_id', $this->office->id)->firstOrFail();
        $detail = $this->caixaFake->getMessageDetail($msg->external_id);
        app(MailboxMessageStore::class)->applyDetail($this->office, $this->client, $detail);

        $this->actAs($this->viewer);
        $this->getJson('/api/v1/fiscal/mailbox/messages/'.$msg->id)->assertOk();

        $log = AuditLog::query()
            ->where('action', 'mailbox.message.view')
            ->where('office_id', $this->office->id)
            ->latest('id')
            ->first();
        $this->assertNotNull($log);
        $ctx = json_encode($log->context ?? []);
        $this->assertStringNotContainsString('Corpo simulado', $ctx);
        $this->assertStringNotContainsString('Assunto simulado', $ctx);
    }

    public function test_estado_api_reflete_dte_e_mensagens_separados(): void
    {
        // Só DTE
        $runSvc = app(FiscalMonitoringRunService::class);
        $dteRun = $runSvc->enqueueManual(
            $this->office,
            $this->client,
            'INTEGRA_CAIXAPOSTAL',
            'DTE',
            'INDICADOR',
            dispatch: false,
        );
        $runSvc->execute($dteRun->id);

        $this->actAs($this->admin);
        $this->getJson('/api/v1/fiscal/mailbox/state?client_id='.$this->client->id)
            ->assertOk()
            ->assertJsonPath('data.dte.status', 'ACTIVE')
            ->assertJsonPath('data.messages.status', 'UNKNOWN');

        // Lista mensagens
        $listRun = $runSvc->enqueueManual(
            $this->office,
            $this->client,
            'INTEGRA_CAIXAPOSTAL',
            'CAIXA_POSTAL',
            'LISTAR',
            dispatch: false,
        );
        $runSvc->execute($listRun->id);

        $this->getJson('/api/v1/fiscal/mailbox/state?client_id='.$this->client->id)
            ->assertOk()
            ->assertJsonPath('data.dte.status', 'ACTIVE')
            ->assertJsonPath('data.messages.status', 'CONSULTED')
            ->assertJsonPath('data.messages.source', 'CAIXA_POSTAL');
    }
}
