<?php

namespace Tests\Feature\Work;

use App\Enums\OfficeRole;
use App\Enums\Work\DueRuleType;
use App\Enums\Work\GenerationItemStatus;
use App\Enums\Work\ProcessOrigin;
use App\Models\Client;
use App\Models\Office;
use App\Models\OfficeMembership;
use App\Models\OperationalProcess;
use App\Models\ProcessGenerationBatch;
use App\Models\ProcessTemplate;
use App\Models\ProcessTemplateTask;
use App\Models\User;
use App\Models\WorkDepartment;
use App\Services\Work\OperationalProcessGenerationService;
use App\Support\CurrentOffice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Idempotência e corrida de geração (simula workers concorrentes no mesmo processo).
 * Horizon real: mesmo service path; constraint unique resolve SKIPPED_DUPLICATE.
 */
class OperationalGenerationConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirmacao_idempotente_nao_duplica(): void
    {
        [$office, $admin, $template, $client] = $this->seedWorld();
        $this->actingAs($admin, 'sanctum');
        app(CurrentOffice::class)->clear();
        app(CurrentOffice::class)->resolve($admin);

        $service = app(OperationalProcessGenerationService::class);
        $batch = $service->preview($template, '2026-03', [$client->id], [], 'idem-key-1');
        $first = $service->confirm($batch);
        $second = $service->confirm($batch->fresh());

        $this->assertSame($first->id, $second->id);
        $this->assertSame(
            1,
            OperationalProcess::withoutGlobalScopes()
                ->where('office_id', $office->id)
                ->where('process_template_id', $template->id)
                ->where('client_id', $client->id)
                ->where('competence', '2026-03')
                ->where('origin', ProcessOrigin::Template->value)
                ->count()
        );
    }

    public function test_constraint_unica_marca_skipped_duplicate_em_corrida(): void
    {
        [$office, $admin, $template, $client] = $this->seedWorld();
        $this->actingAs($admin, 'sanctum');
        app(CurrentOffice::class)->clear();
        app(CurrentOffice::class)->resolve($admin);

        $service = app(OperationalProcessGenerationService::class);

        // Processo pré-existente com a chave TEMPLATE
        OperationalProcess::factory()->create([
            'office_id' => $office->id,
            'client_id' => $client->id,
            'process_template_id' => $template->id,
            'origin' => ProcessOrigin::Template,
            'competence' => '2026-04',
            'title' => 'Já existe',
        ]);

        $batch = $service->preview($template, '2026-04', [$client->id], [], 'idem-key-2');
        $item = $batch->items()->first();
        $this->assertTrue((bool) $item?->is_blocked, 'Preview deve bloquear duplicidade existente.');

        // Corrida: planta duplicata DEPOIS do preview e ANTES do confirm/processBatch
        $batchRace = $service->preview($template, '2026-05', [$client->id], [], 'idem-key-race');
        $this->assertFalse((bool) $batchRace->items()->first()?->is_blocked);

        OperationalProcess::factory()->create([
            'office_id' => $office->id,
            'client_id' => $client->id,
            'process_template_id' => $template->id,
            'origin' => ProcessOrigin::Template,
            'competence' => '2026-05',
            'title' => 'Duplicata plantada na corrida',
        ]);

        // Força item elegível e processa (como worker após corrida)
        $batchRace->forceFill([
            'status' => \App\Enums\Work\GenerationBatchStatus::Queued,
            'queued_at' => now(),
        ])->save();
        $batchRace->items()->update([
            'status' => GenerationItemStatus::Queued->value,
            'is_blocked' => false,
        ]);

        $service->processBatch($batchRace->fresh(['items']));
        $raceItem = $batchRace->items()->first();
        $this->assertNotNull($raceItem);
        $this->assertSame(
            GenerationItemStatus::SkippedDuplicate,
            $raceItem->status,
            'Constraint unique deve resultar em SKIPPED_DUPLICATE.',
        );

        $this->assertSame(
            1,
            OperationalProcess::withoutGlobalScopes()
                ->where('office_id', $office->id)
                ->where('process_template_id', $template->id)
                ->where('client_id', $client->id)
                ->where('competence', '2026-05')
                ->where('origin', ProcessOrigin::Template->value)
                ->count()
        );
    }

    public function test_dois_escritorios_mesmo_cnpj_competencia_independentes(): void
    {
        [$officeA, $adminA, $templateA, $clientA] = $this->seedWorld('alpha');
        $officeB = Office::factory()->create(['timezone' => 'America/Sao_Paulo']);
        $adminB = User::factory()->forOffice($officeB, OfficeRole::Admin)->withTwoFactorConfirmed()->create();
        $clientB = Client::factory()->create([
            'office_id' => $officeB->id,
            'root_cnpj' => $clientA->root_cnpj,
            'is_active' => true,
        ]);
        $deptB = WorkDepartment::factory()->create(['office_id' => $officeB->id]);
        $templateB = ProcessTemplate::factory()->create([
            'office_id' => $officeB->id,
            'default_department_id' => $deptB->id,
            'default_due_rule_type' => DueRuleType::FixedDayOfCompetence,
            'default_due_rule_value' => 15,
        ]);
        ProcessTemplateTask::factory()->create([
            'office_id' => $officeB->id,
            'process_template_id' => $templateB->id,
            'sort_order' => 1,
            'due_rule_type' => DueRuleType::FixedDayOfCompetence,
            'due_rule_value' => 10,
        ]);

        $this->actingAs($adminA, 'sanctum');
        app(CurrentOffice::class)->clear();
        app(CurrentOffice::class)->resolve($adminA);
        $service = app(OperationalProcessGenerationService::class);
        $batchA = $service->preview($templateA, '2026-01', [$clientA->id]);
        $service->confirm($batchA);

        $this->actingAs($adminB, 'sanctum');
        app(CurrentOffice::class)->clear();
        app(CurrentOffice::class)->resolve($adminB);
        $batchB = $service->preview($templateB, '2026-01', [$clientB->id]);
        $service->confirm($batchB);

        $this->assertSame(
            1,
            OperationalProcess::withoutGlobalScopes()
                ->where('office_id', $officeA->id)
                ->where('competence', '2026-01')
                ->count()
        );
        $this->assertSame(
            1,
            OperationalProcess::withoutGlobalScopes()
                ->where('office_id', $officeB->id)
                ->where('competence', '2026-01')
                ->count()
        );
    }

    /**
     * @return array{0: Office, 1: User, 2: ProcessTemplate, 3: Client}
     */
    private function seedWorld(string $suffix = 'a'): array
    {
        $office = Office::factory()->create(['timezone' => 'America/Sao_Paulo']);
        $admin = User::factory()->forOffice($office, OfficeRole::Admin)->withTwoFactorConfirmed()->create([
            'email' => "admin-{$suffix}@example.com",
        ]);
        $membership = OfficeMembership::query()->where('office_id', $office->id)->where('user_id', $admin->id)->first();
        $dept = WorkDepartment::factory()->create(['office_id' => $office->id]);
        $client = Client::factory()->create([
            'office_id' => $office->id,
            'root_cnpj' => '12345678000199',
            'is_active' => true,
        ]);
        $template = ProcessTemplate::factory()->create([
            'office_id' => $office->id,
            'default_department_id' => $dept->id,
            'default_due_rule_type' => DueRuleType::FixedDayOfCompetence,
            'default_due_rule_value' => 20,
            'created_by_membership_id' => $membership?->id,
        ]);
        ProcessTemplateTask::factory()->create([
            'office_id' => $office->id,
            'process_template_id' => $template->id,
            'sort_order' => 1,
            'title' => 'Tarefa',
            'due_rule_type' => DueRuleType::DaysBeforeProcessDue,
            'due_rule_value' => 3,
        ]);

        return [$office, $admin, $template->fresh('tasks'), $client];
    }
}
