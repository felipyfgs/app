<?php

namespace Tests\Feature\Fiscal;

use App\Enums\FiscalMutationDenialCode;
use App\Enums\MailboxSource;
use App\Enums\MailboxTriageStatus;
use App\Enums\OfficeRole;
use App\Enums\SerproEnvironment;
use App\Models\Client;
use App\Models\FiscalCategory;
use App\Models\MailboxMessage;
use App\Models\Office;
use App\Models\User;
use App\Services\Fiscal\Mutations\FiscalMutationPolicy;
use App\Support\CurrentOffice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Tasks 8.5 / 8.6 / 8.7 — policies de ações fiscais do hub de monitoramento:
 * VIEWER não exporta/muta; OPERATOR exporta e executa ações de leitura/triagem;
 * mutações de alto risco usam catálogo + bloqueio demo; office_id do request é ignorado.
 */
class FiscalActionsPolicyTest extends TestCase
{
    use RefreshDatabase;

    private Office $office;

    private Office $otherOffice;

    private Client $client;

    private User $admin;

    private User $operator;

    private User $viewer;

    private FiscalCategory $sitfisCategory;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'features.global_enabled' => true,
            'features.kill_switch' => false,
            'features.modules.sitfis.enabled' => true,
            'features.modules.sitfis.allow_all_offices' => true,
            'features.modules.mailbox.enabled' => true,
            'features.modules.mailbox.allow_all_offices' => true,
            'features.modules.fgts.enabled' => true,
            'features.modules.fgts.allow_all_offices' => true,
            'fiscal_monitoring.enabled' => true,
            'fiscal_monitoring.kill_switch' => false,
            'fiscal_demo.enabled' => true,
            'fiscal_demo.office_slug' => 'demo',
            'fiscal_monitoring.demo.office_slug' => 'demo',
            'features.mutating.enabled' => true,
            'fiscal_mutations.enabled' => true,
            'fiscal_mutations.kill_switch' => false,
        ]);

        $this->office = Office::factory()->create(['slug' => 'actions-office']);
        $this->otherOffice = Office::factory()->create(['slug' => 'actions-other']);
        $this->client = Client::factory()->forOffice($this->office)->create();
        $this->admin = User::factory()->forOffice($this->office, OfficeRole::Admin)->withTwoFactorConfirmed()->create();
        $this->operator = User::factory()->forOffice($this->office, OfficeRole::Operator)->withTwoFactorConfirmed()->create();
        $this->viewer = User::factory()->forOffice($this->office, OfficeRole::Viewer)->withTwoFactorConfirmed()->create();
        $this->sitfisCategory = FiscalCategory::query()->where('code', 'SITFIS')->firstOrFail();
    }

    public function test_viewer_nao_exporta_nem_dispara_acoes_de_escrita(): void
    {
        $this->actAs($this->viewer);

        $this->postJson('/api/v1/exports', [
            'filters' => [
                'export_scope' => 'fiscal_portfolio',
                'module_key' => 'sitfis',
            ],
        ])->assertForbidden();

        $this->postJson('/api/v1/fiscal/category-links/batch', [
            'fiscal_category_id' => $this->sitfisCategory->id,
            'client_ids' => [$this->client->id],
            'office_id' => $this->otherOffice->id,
        ])->assertForbidden();

        $this->postJson('/api/v1/fiscal/runs', [
            'client_id' => $this->client->id,
            'system_code' => 'INTEGRA_SITFIS',
            'service_code' => 'SITFIS',
            'operation_code' => 'MONITOR',
            'office_id' => $this->otherOffice->id,
        ])->assertForbidden();

        $this->postJson('/api/v1/fiscal/sitfis/refresh', [
            'client_id' => $this->client->id,
            'office_id' => $this->otherOffice->id,
        ])->assertForbidden();

        $this->postJson('/api/v1/fiscal/fgts/sync', [
            'client_id' => $this->client->id,
            'competence_period_key' => '2026-06',
            'office_id' => $this->otherOffice->id,
        ])->assertForbidden();

        $this->postJson('/api/v1/fiscal/mutations/preflight', [
            'client_id' => $this->client->id,
            'solution_code' => 'INTEGRA_PAGAMENTO',
            'service_code' => 'SICALC',
            'operation_code' => 'EMITIR_GUIA',
            'environment' => 'TRIAL',
            'module' => 'guias',
            'idempotency_key' => 'viewer-mut-1',
        ])->assertForbidden();

        // Alto risco: somente ADMIN (OPERATOR exporta/triar/refresh, mas não muta).
        $this->actAs($this->operator);
        $this->postJson('/api/v1/fiscal/mutations/preflight', [
            'client_id' => $this->client->id,
            'solution_code' => 'INTEGRA_PAGAMENTO',
            'service_code' => 'SICALC',
            'operation_code' => 'EMITIR_GUIA',
            'environment' => 'TRIAL',
            'module' => 'guias',
            'idempotency_key' => 'operator-mut-1',
        ])->assertForbidden();
    }

    public function test_operator_exporta_associa_e_dispara_runs_sitfis_fgts(): void
    {
        Queue::fake();
        $this->actAs($this->operator);

        $this->postJson('/api/v1/exports', [
            'filters' => [
                'export_scope' => 'fiscal_portfolio',
                'module_key' => 'sitfis',
            ],
            'office_id' => $this->otherOffice->id,
        ])->assertStatus(202)
            ->assertJsonPath('data.status', 'PENDING');

        $batch = $this->postJson('/api/v1/fiscal/category-links/batch', [
            'fiscal_category_id' => $this->sitfisCategory->id,
            'client_ids' => [$this->client->id],
            'office_id' => $this->otherOffice->id,
        ]);
        $batch->assertOk()
            ->assertJsonPath('data.created', 1)
            ->assertJsonPath('data.errors', []);

        // Escritório ativo permanece o do operator (office_id forjado ignorado).
        $this->assertSame($this->office->id, app(CurrentOffice::class)->id());

        $run = $this->postJson('/api/v1/fiscal/runs', [
            'client_id' => $this->client->id,
            'system_code' => 'INTEGRA_SITFIS',
            'service_code' => 'SITFIS',
            'operation_code' => 'MONITOR',
            'office_id' => $this->otherOffice->id,
            'correlation_id' => 'op-run-'.Str::uuid(),
        ]);
        $this->assertContains($run->status(), [201, 200]);
        $this->assertSame($this->office->id, (int) $run->json('data.office_id'));

        // Refresh/SITFIS pode retornar 202/200/422 (depende de SERPRO/TTL), mas nunca 403 para OPERATOR.
        $refresh = $this->postJson('/api/v1/fiscal/sitfis/refresh', [
            'client_id' => $this->client->id,
            'office_id' => $this->otherOffice->id,
        ]);
        $this->assertNotSame(403, $refresh->status());
        $this->assertContains($refresh->status(), [200, 202, 422]);

        $fgts = $this->postJson('/api/v1/fiscal/fgts/sync', [
            'client_id' => $this->client->id,
            'competence_period_key' => '2026-06',
            'office_id' => $this->otherOffice->id,
            'dispatch_job' => false,
            'create_run' => false,
        ]);
        $this->assertNotSame(403, $fgts->status());
        $fgts->assertStatus(503)
            ->assertJsonPath('code', 'ESOCIAL_SOURCE_UNAVAILABLE');
    }

    public function test_operator_triagem_mailbox_new_in_review_resolved_e_rejeita_invalido(): void
    {
        $message = MailboxMessage::query()->create([
            'office_id' => $this->office->id,
            'client_id' => $this->client->id,
            'external_id' => 'mbx-policy-1',
            'message_hash' => hash('sha256', 'mbx-policy-1'),
            'source' => MailboxSource::CaixaPostal,
            'sensitivity_class' => 'NORMAL',
            'category_code' => 'INTIMACAO',
            'category_label' => 'Intimação',
            'sender_code' => 'RFB',
            'sender_label' => 'Receita Federal',
            'subject_preview' => 'DEMONSTRAÇÃO — mensagem sintética',
            'received_at_official' => now()->subDay(),
            'due_at' => now()->addDays(3),
            'severity_hint' => 'MEDIUM',
            'official_read_indicator' => false,
            'has_body' => false,
            'attachment_count' => 0,
            'body_byte_size' => 0,
            'triage_status' => MailboxTriageStatus::New,
        ]);

        $this->actAs($this->operator);

        $this->patchJson('/api/v1/fiscal/mailbox/messages/'.$message->id.'/triage', [
            'triage_status' => 'IN_REVIEW',
            'note' => 'Em análise operacional',
            'office_id' => $this->otherOffice->id,
        ])->assertOk()
            ->assertJsonPath('data.triage_status', 'IN_REVIEW')
            ->assertJsonPath('meta.official_read_indicator', false);

        $message->refresh();
        $this->assertSame(MailboxTriageStatus::InReview, $message->triage_status);
        $this->assertFalse((bool) $message->official_read_indicator);

        $this->patchJson('/api/v1/fiscal/mailbox/messages/'.$message->id.'/triage', [
            'triage_status' => 'RESOLVED',
        ])->assertOk()
            ->assertJsonPath('data.triage_status', 'RESOLVED');

        $message->refresh();
        $this->assertSame(MailboxTriageStatus::Resolved, $message->triage_status);

        $this->patchJson('/api/v1/fiscal/mailbox/messages/'.$message->id.'/triage', [
            'triage_status' => 'NEW',
        ])->assertOk()
            ->assertJsonPath('data.triage_status', 'NEW');

        $this->patchJson('/api/v1/fiscal/mailbox/messages/'.$message->id.'/triage', [
            'triage_status' => 'OFFICIAL_READ',
        ])->assertStatus(422);

        $this->patchJson('/api/v1/fiscal/mailbox/messages/'.$message->id.'/triage', [
            'triage_status' => 'ARBITRARY',
        ])->assertStatus(422);

        $message->refresh();
        $this->assertFalse((bool) $message->official_read_indicator);
    }

    public function test_mutacao_alto_risco_rejeita_operacao_fora_do_catalogo_e_bloqueia_demo(): void
    {
        $this->actAs($this->admin);

        $unknown = $this->postJson('/api/v1/fiscal/mutations/preflight', [
            'client_id' => $this->client->id,
            'solution_code' => 'INTEGRA_FAKE',
            'service_code' => 'NAO_EXISTE',
            'operation_code' => 'TRANSMITIR_QUALQUER_COISA',
            'environment' => 'TRIAL',
            'module' => 'guias',
            'idempotency_key' => 'catalog-miss-1',
        ]);
        // Operação inventada nunca é elegível (catálogo, coorte ou flag).
        $this->assertContains($unknown->status(), [403, 422]);
        $this->assertNotTrue((bool) $unknown->json('data.eligible'));
        $codes = $unknown->json('data.codes') ?? [];
        $joined = strtoupper(json_encode($codes) ?: '');
        $this->assertTrue(
            str_contains($joined, 'CATALOG')
            || str_contains($joined, 'SERVICE_NOT')
            || str_contains($joined, 'DISABLED')
            || str_contains($joined, 'FEATURE')
            || str_contains($joined, 'UNKNOWN')
            || str_contains($joined, 'NOT_ALLOWED')
            || str_contains($joined, 'MUTATING'),
            'Esperava rejeição de catálogo/feature, got: '.$joined.' body='.$unknown->getContent()
        );

        $demo = Office::factory()->create(['slug' => 'demo']);
        $demoClient = Client::factory()->forOffice($demo)->create();
        $demoAdmin = User::factory()->forOffice($demo, OfficeRole::Admin)->withTwoFactorConfirmed()->create();

        $policy = app(FiscalMutationPolicy::class);
        $result = $policy->evaluate(
            office: $demo,
            client: $demoClient,
            user: $demoAdmin,
            solutionCode: 'INTEGRA_SN',
            serviceCode: 'PGDASD',
            operationCode: 'TRANSMITIR',
            environment: SerproEnvironment::Trial,
            module: 'simples_mei',
            options: ['require_totp' => false, 'skip_anti_repeat' => true, 'skip_uncertain_check' => true],
        );

        $this->assertFalse($result->allowed);
        $codes = array_map(fn (FiscalMutationDenialCode $c) => $c->value, $result->codes);
        $this->assertContains(FiscalMutationDenialCode::DemoMode->value, $codes);
    }

    private function actAs(User $user): void
    {
        $this->actingAs($user);
        app(CurrentOffice::class)->clear();
        app(CurrentOffice::class)->resolve($user);
    }
}
