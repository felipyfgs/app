<?php

namespace Tests\Feature\Work;

use App\Enums\OfficeRole;
use App\Enums\Work\DueRuleType;
use App\Enums\Work\TaskStatus;
use App\Models\Client;
use App\Models\Office;
use App\Models\OfficeMembership;
use App\Models\OperationalProcess;
use App\Models\OperationalTask;
use App\Models\ProcessTemplate;
use App\Models\ProcessTemplateTask;
use App\Models\User;
use App\Models\WorkDepartment;
use App\Support\CurrentOffice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithSpaAuth;
use Tests\Support\ApiSecretScanner;
use Tests\TestCase;

class OperationalWorkCoreTest extends TestCase
{
    use InteractsWithSpaAuth;
    use RefreshDatabase;

    private Office $officeA;

    private Office $officeB;

    private User $adminA;

    private User $operatorA;

    private User $viewerA;

    private User $adminB;

    private Client $clientA;

    private Client $clientBSameCnpj;

    private WorkDepartment $deptA;

    private OfficeMembership $adminMembershipA;

    private OfficeMembership $operatorMembershipA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->officeA = Office::factory()->create(['timezone' => 'America/Sao_Paulo']);
        $this->officeB = Office::factory()->create(['timezone' => 'America/Sao_Paulo']);

        $this->adminA = User::factory()->forOffice($this->officeA, OfficeRole::Admin)->withTwoFactorConfirmed()->create([
            'email' => 'admin-a@example.com',
            'password' => 'password',
        ]);
        $this->operatorA = User::factory()->forOffice($this->officeA, OfficeRole::Operator)->create([
            'email' => 'op-a@example.com',
            'password' => 'password',
        ]);
        $this->viewerA = User::factory()->forOffice($this->officeA, OfficeRole::Viewer)->create([
            'email' => 'viewer-a@example.com',
            'password' => 'password',
        ]);
        $this->adminB = User::factory()->forOffice($this->officeB, OfficeRole::Admin)->withTwoFactorConfirmed()->create([
            'email' => 'admin-b@example.com',
            'password' => 'password',
        ]);

        $this->adminMembershipA = OfficeMembership::query()
            ->where('office_id', $this->officeA->id)
            ->where('user_id', $this->adminA->id)
            ->firstOrFail();
        $this->operatorMembershipA = OfficeMembership::query()
            ->where('office_id', $this->officeA->id)
            ->where('user_id', $this->operatorA->id)
            ->firstOrFail();

        $this->clientA = Client::factory()->create([
            'office_id' => $this->officeA->id,
            'root_cnpj' => '12345678000199',
            'is_active' => true,
        ]);
        $this->clientBSameCnpj = Client::factory()->create([
            'office_id' => $this->officeB->id,
            'root_cnpj' => '12345678000199',
            'is_active' => true,
        ]);

        $this->deptA = WorkDepartment::factory()->create([
            'office_id' => $this->officeA->id,
            'name' => 'Fiscal',
            'code' => 'FIS',
        ]);
        $this->operatorMembershipA->forceFill(['work_department_id' => $this->deptA->id])->save();
    }

    private function loginAs(User $user): static
    {
        // Guard sanctum + limpeza do CurrentOffice entre trocas de usuário.
        app(CurrentOffice::class)->clear();
        $this->actingAs($user, 'sanctum');
        app(CurrentOffice::class)->resolve($user);

        return $this;
    }

    public function test_admin_cria_departamento_e_operador_nao(): void
    {
        $this->loginAs($this->adminA)
            ->postJson('/api/v1/work/departments', [
                'name' => 'Contábil',
                'code' => 'CTB',
                'color' => '#112233',
                'office_id' => $this->officeB->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.code', 'CTB')
            ->assertJsonMissingPath('data.office_id');

        $this->assertDatabaseHas('work_departments', [
            'office_id' => $this->officeA->id,
            'code' => 'CTB',
        ]);

        $this->loginAs($this->operatorA)
            ->postJson('/api/v1/work/departments', [
                'name' => 'RH',
                'code' => 'RH',
            ])
            ->assertForbidden();
    }

    public function test_platform_admin_sem_membership_nao_acessa_work(): void
    {
        $platform = User::factory()->asPlatformAdmin()->create([
            'email' => 'platform@example.com',
            'password' => 'password',
        ]);

        // Sem contexto privilegiado/default resolvido: 409 office_context_required.
        // Com flag privilegiada + default ativo, leitura Work seria 200 (ver PlatformWorkReadOnlyTest).
        $this->loginAs($platform)
            ->getJson('/api/v1/work/queue')
            ->assertStatus(409)
            ->assertJsonPath('code', 'office_context_required');
    }

    public function test_modelo_preview_geracao_e_unicidade_template(): void
    {
        $template = $this->createTemplate();

        $preview = $this->loginAs($this->adminA)
            ->postJson("/api/v1/work/templates/{$template->id}/preview", [
                'competence' => '2026-06',
                'client_ids' => [$this->clientA->id, $this->clientBSameCnpj->id],
                'office_id' => $this->officeB->id,
            ])
            ->assertCreated()
            ->json('data');

        $this->assertSame('PREVIEWED', $preview['status']);
        // Cliente B de outro office deve vir bloqueado
        $blocked = collect($preview['items'])->firstWhere('client_id', $this->clientBSameCnpj->id);
        $this->assertTrue($blocked['is_blocked']);

        $ready = collect($preview['items'])->firstWhere('client_id', $this->clientA->id);
        $this->assertFalse($ready['is_blocked']);

        $confirm = $this->loginAs($this->adminA)
            ->postJson("/api/v1/work/generation-batches/{$preview['id']}/confirm")
            ->assertOk()
            ->json('data');

        $this->assertContains($confirm['status'], ['COMPLETED', 'COMPLETED_WITH_ERRORS']);
        $this->assertDatabaseHas('operational_processes', [
            'office_id' => $this->officeA->id,
            'client_id' => $this->clientA->id,
            'competence' => '2026-06',
            'origin' => 'TEMPLATE',
        ]);

        // Segunda geração mesma chave → conflito no preview
        $preview2 = $this->loginAs($this->adminA)
            ->postJson("/api/v1/work/templates/{$template->id}/preview", [
                'competence' => '2026-06',
                'client_ids' => [$this->clientA->id],
            ])
            ->assertCreated()
            ->json('data');

        $item = $preview2['items'][0];
        $this->assertTrue($item['is_blocked']);
    }

    public function test_criacao_manual_transicoes_e_evidencia(): void
    {
        Storage::fake('local');

        $processResp = $this->loginAs($this->operatorA)
            ->postJson('/api/v1/work/processes', [
                'client_id' => $this->clientA->id,
                'title' => 'DAS mensal',
                'competence' => '2026-05',
                'due_date' => '2026-06-20',
                'subject_to_fine' => true,
                'work_department_id' => $this->deptA->id,
                'tasks' => [
                    [
                        'title' => 'Apurar',
                        'sort_order' => 1,
                        'requires_evidence' => true,
                        'is_critical' => true,
                        'work_department_id' => $this->deptA->id,
                        'assignee_membership_id' => $this->operatorMembershipA->id,
                    ],
                    [
                        'title' => 'Transmitir',
                        'sort_order' => 2,
                        'work_department_id' => $this->deptA->id,
                    ],
                ],
            ])
            ->assertCreated()
            ->json('data');

        $taskId = $processResp['tasks'][0]['id'];
        $lock = $processResp['tasks'][0]['lock_version'];

        $this->loginAs($this->operatorA)
            ->postJson("/api/v1/work/tasks/{$taskId}/start", ['lock_version' => $lock])
            ->assertOk()
            ->assertJsonPath('data.status', 'EM_PROGRESSO');

        $task = OperationalTask::query()->findOrFail($taskId);
        $this->assertSame(TaskStatus::EmProgresso, $task->status);

        // Conclusão sem evidência falha
        $this->loginAs($this->operatorA)
            ->postJson("/api/v1/work/tasks/{$taskId}/complete", ['lock_version' => $task->lock_version])
            ->assertStatus(422);

        $file = UploadedFile::fake()->create('comp.pdf', 100, 'application/pdf');
        // fake create may not set real PDF magic; use real content
        $real = UploadedFile::fake()->createWithContent('comp.pdf', '%PDF-1.4 fake content for test');

        $upload = $this->loginAs($this->operatorA)
            ->post("/api/v1/work/tasks/{$taskId}/evidences", [
                'file' => $real,
            ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->json('data');

        $this->assertArrayNotHasKey('vault_object_id', $upload);

        $task->refresh();
        $this->loginAs($this->operatorA)
            ->postJson("/api/v1/work/tasks/{$taskId}/complete", ['lock_version' => $task->lock_version])
            ->assertOk()
            ->assertJsonPath('data.status', 'CONCLUIDA');

        // Viewer não muta
        $this->loginAs($this->viewerA)
            ->postJson("/api/v1/work/tasks/{$taskId}/start", ['lock_version' => 99])
            ->assertForbidden();
    }

    public function test_isolamento_cruzado_mesmo_cnpj(): void
    {
        $processA = OperationalProcess::factory()->create([
            'office_id' => $this->officeA->id,
            'client_id' => $this->clientA->id,
            'competence' => '2026-04',
            'title' => 'Proc A',
        ]);
        $taskA = OperationalTask::factory()->create([
            'office_id' => $this->officeA->id,
            'operational_process_id' => $processA->id,
            'title' => 'Task A',
        ]);

        // Admin B não vê processo A
        $this->loginAs($this->adminB)
            ->getJson("/api/v1/work/processes/{$processA->id}")
            ->assertNotFound();

        $this->loginAs($this->adminB)
            ->getJson("/api/v1/work/tasks/{$taskA->id}")
            ->assertNotFound();
    }

    public function test_busca_de_processos_e_modelos_respeita_o_contrato_da_ui(): void
    {
        $this->clientA->forceFill(['legal_name' => 'Cliente Agulha'])->save();
        OperationalProcess::factory()->create([
            'office_id' => $this->officeA->id,
            'client_id' => $this->clientA->id,
            'title' => 'Rotina mensal',
        ]);
        OperationalProcess::factory()->create([
            'office_id' => $this->officeA->id,
            'client_id' => $this->clientA->id,
            'title' => 'Processo Farol',
        ]);
        OperationalProcess::factory()->create([
            'office_id' => $this->officeB->id,
            'client_id' => $this->clientBSameCnpj->id,
            'title' => 'Processo Farol',
        ]);

        ProcessTemplate::factory()->create([
            'office_id' => $this->officeA->id,
            'name' => 'Modelo mensal',
            'description' => 'Descrição Farol',
        ]);
        ProcessTemplate::factory()->create([
            'office_id' => $this->officeB->id,
            'name' => 'Modelo Farol externo',
        ]);

        $this->loginAs($this->adminA)
            ->getJson('/api/v1/work/processes?q=agulha')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->getJson('/api/v1/work/processes?q=farol')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Processo Farol');

        $this->getJson('/api/v1/work/templates?q=farol')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Modelo mensal');
    }

    public function test_fila_e_kpis_tenant_scoped(): void
    {
        $process = OperationalProcess::factory()->create([
            'office_id' => $this->officeA->id,
            'client_id' => $this->clientA->id,
            'due_date' => now()->subDay()->toDateString(),
            'subject_to_fine' => true,
        ]);
        OperationalTask::factory()->create([
            'office_id' => $this->officeA->id,
            'operational_process_id' => $process->id,
            'status' => TaskStatus::AFazer,
            'due_date' => '2020-01-01',
            'assignee_membership_id' => $this->operatorMembershipA->id,
            'work_department_id' => $this->deptA->id,
        ]);

        $queue = $this->loginAs($this->operatorA)
            ->getJson('/api/v1/work/queue')
            ->assertOk()
            ->json('data');

        $this->assertNotEmpty($queue);
        $this->assertArrayHasKey('bucket', $queue[0]);
        $this->assertArrayHasKey('risks', $queue[0]);

        $kpis = $this->loginAs($this->adminA)
            ->getJson('/api/v1/work/kpis')
            ->assertOk()
            ->json('data.kpis');

        $this->assertGreaterThanOrEqual(1, $kpis['total_open']);
        $this->assertGreaterThanOrEqual(1, $kpis['atrasadas']);
    }

    public function test_export_csv_sem_campos_sensiveis(): void
    {
        OperationalTask::factory()->create([
            'office_id' => $this->officeA->id,
            'operational_process_id' => OperationalProcess::factory()->create([
                'office_id' => $this->officeA->id,
                'client_id' => $this->clientA->id,
            ])->id,
        ]);

        $export = $this->loginAs($this->adminA)
            ->postJson('/api/v1/work/exports', ['filters' => []])
            ->assertCreated()
            ->json('data');

        $this->assertArrayNotHasKey('storage_path', $export);
        $this->assertSame('READY', $export['status']);

        $this->loginAs($this->viewerA)
            ->postJson('/api/v1/work/exports', ['filters' => []])
            ->assertForbidden();
    }

    public function test_payload_work_sem_marcadores_sensiveis(): void
    {
        $process = OperationalProcess::factory()->create([
            'office_id' => $this->officeA->id,
            'client_id' => $this->clientA->id,
        ]);
        $task = OperationalTask::factory()->create([
            'office_id' => $this->officeA->id,
            'operational_process_id' => $process->id,
            'assignee_membership_id' => $this->operatorMembershipA->id,
        ]);

        $queue = $this->loginAs($this->operatorA)
            ->getJson('/api/v1/work/queue')
            ->assertOk();
        ApiSecretScanner::assertClean(json_encode($queue->json()) ?: '', 'work.queue');

        $show = $this->loginAs($this->operatorA)
            ->getJson("/api/v1/work/tasks/{$task->id}")
            ->assertOk();
        ApiSecretScanner::assertClean(json_encode($show->json()) ?: '', 'work.task');

        $kpis = $this->loginAs($this->adminA)
            ->getJson('/api/v1/work/kpis')
            ->assertOk();
        ApiSecretScanner::assertClean(json_encode($kpis->json()) ?: '', 'work.kpis');
    }

    public function test_concorrencia_otimista_409(): void
    {
        $process = OperationalProcess::factory()->create([
            'office_id' => $this->officeA->id,
            'client_id' => $this->clientA->id,
        ]);
        $task = OperationalTask::factory()->create([
            'office_id' => $this->officeA->id,
            'operational_process_id' => $process->id,
            'assignee_membership_id' => $this->operatorMembershipA->id,
            'lock_version' => 1,
        ]);

        $this->loginAs($this->operatorA)
            ->postJson("/api/v1/work/tasks/{$task->id}/start", ['lock_version' => 1])
            ->assertOk();

        $this->loginAs($this->operatorA)
            ->postJson("/api/v1/work/tasks/{$task->id}/block", [
                'lock_version' => 1,
                'reason' => 'stale',
            ])
            ->assertStatus(409)
            ->assertJsonPath('error', 'OPTIMISTIC_LOCK_CONFLICT');
    }

    private function createTemplate(): ProcessTemplate
    {
        $template = ProcessTemplate::factory()->create([
            'office_id' => $this->officeA->id,
            'name' => 'Obrigações mensais',
            'default_department_id' => $this->deptA->id,
            'default_due_rule_type' => DueRuleType::FixedDayOfCompetence,
            'default_due_rule_value' => 20,
            'created_by_membership_id' => $this->adminMembershipA->id,
        ]);

        ProcessTemplateTask::factory()->create([
            'office_id' => $this->officeA->id,
            'process_template_id' => $template->id,
            'sort_order' => 1,
            'title' => 'Calcular',
            'due_rule_type' => DueRuleType::DaysBeforeProcessDue,
            'due_rule_value' => 5,
            'default_department_id' => $this->deptA->id,
            'is_required' => true,
        ]);

        ProcessTemplateTask::factory()->create([
            'office_id' => $this->officeA->id,
            'process_template_id' => $template->id,
            'sort_order' => 2,
            'title' => 'Entregar',
            'due_rule_type' => DueRuleType::DaysBeforeProcessDue,
            'due_rule_value' => 0,
            'is_required' => true,
            'requires_evidence' => true,
        ]);

        return $template->fresh('tasks');
    }
}
