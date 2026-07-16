<?php

namespace App\Services\Work;

use App\Domain\Work\DueDateCalculator;
use App\Domain\Work\WorkRiskCalculator;
use App\Enums\Work\TaskStatus;
use App\Enums\Work\WorkRisk;
use App\Models\OperationalProcess;
use App\Models\OperationalTask;
use App\Support\CurrentOffice;
use App\Support\Work\OfficeTimezone;
use Illuminate\Support\Facades\DB;

/**
 * KPIs e listas de risco do bloco operacional do dashboard.
 */
final class OperationalKpiQuery
{
    public function __construct(
        private readonly CurrentOffice $currentOffice,
        private readonly WorkRiskCalculator $risks = new WorkRiskCalculator,
        private readonly DueDateCalculator $dates = new DueDateCalculator,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $office = $this->currentOffice->office();
        $tz = OfficeTimezone::for($office);
        $today = $this->dates->todayInOffice($tz);

        $openStatuses = [
            TaskStatus::AFazer->value,
            TaskStatus::EmProgresso->value,
            TaskStatus::Impedida->value,
        ];

        $tasks = OperationalTask::query()
            ->with('process:id,due_date,subject_to_fine,client_id,office_id')
            ->where('office_id', $office->id)
            ->whereIn('status', array_merge($openStatuses, [
                TaskStatus::Concluida->value,
                TaskStatus::Dispensada->value,
            ]))
            ->get();

        $totalOpen = 0;
        $atrasadas = 0;
        $emMulta = 0;
        $venceHoje = 0;
        $emProgresso = 0;
        $concluidas = 0;
        $semResponsavel = 0;
        $riskRows = [];

        foreach ($tasks as $task) {
            if ($task->status->isTerminal()) {
                if ($task->status === TaskStatus::Concluida) {
                    $concluidas++;
                }

                continue;
            }

            $totalOpen++;
            if ($task->status === TaskStatus::EmProgresso) {
                $emProgresso++;
            }

            $effective = $this->risks->effectiveDueDate(
                $task->due_date?->format('Y-m-d'),
                $task->process?->due_date?->format('Y-m-d'),
            );
            $riskList = $this->risks->forTask(
                $task->status,
                $task->due_date?->format('Y-m-d'),
                $task->process?->due_date?->format('Y-m-d'),
                (bool) ($task->process?->subject_to_fine),
                $task->assignee_membership_id,
                $today,
            );
            $values = array_map(fn (WorkRisk $r) => $r->value, $riskList);

            if (in_array(WorkRisk::Atrasada->value, $values, true)) {
                $atrasadas++;
            }
            if (in_array(WorkRisk::EmMulta->value, $values, true)) {
                $emMulta++;
            }
            if ($effective === $today) {
                $venceHoje++;
            }
            if (in_array(WorkRisk::SemResponsavel->value, $values, true)) {
                $semResponsavel++;
            }

            if ($riskList !== []) {
                $riskRows[] = [
                    'task_id' => $task->id,
                    'title' => $task->title,
                    'process_id' => $task->operational_process_id,
                    'risks' => $values,
                    'effective_due_date' => $effective,
                ];
            }
        }

        usort($riskRows, function (array $a, array $b): int {
            $score = static function (array $r): int {
                $s = 0;
                if (in_array(WorkRisk::EmMulta->value, $r['risks'], true)) {
                    $s += 100;
                }
                if (in_array(WorkRisk::Atrasada->value, $r['risks'], true)) {
                    $s += 50;
                }

                return $s;
            };

            return $score($b) <=> $score($a);
        });

        $byDepartment = OperationalTask::query()
            ->select('work_department_id', DB::raw('count(*) as total'))
            ->where('office_id', $office->id)
            ->whereIn('status', $openStatuses)
            ->groupBy('work_department_id')
            ->get()
            ->map(fn ($r) => [
                'work_department_id' => $r->work_department_id,
                'total' => (int) $r->total,
            ])
            ->all();

        $byAssignee = OperationalTask::query()
            ->select('assignee_membership_id', DB::raw('count(*) as total'))
            ->where('office_id', $office->id)
            ->whereIn('status', $openStatuses)
            ->groupBy('assignee_membership_id')
            ->get()
            ->map(fn ($r) => [
                'assignee_membership_id' => $r->assignee_membership_id,
                'total' => (int) $r->total,
            ])
            ->all();

        $processesWithoutOwner = OperationalProcess::query()
            ->where('office_id', $office->id)
            ->whereNull('assignee_membership_id')
            ->whereNotIn('status', ['CONCLUIDO', 'ARQUIVADO'])
            ->orderBy('due_date')
            ->limit(10)
            ->get(['id', 'title', 'competence', 'due_date', 'client_id'])
            ->map(fn (OperationalProcess $p) => [
                'id' => $p->id,
                'title' => $p->title,
                'competence' => $p->competence,
                'due_date' => $p->due_date?->format('Y-m-d'),
                'client_id' => $p->client_id,
            ])
            ->all();

        return [
            'generated_at' => now()->toIso8601String(),
            'office_timezone' => $tz,
            'today' => $today,
            'kpis' => [
                'total_open' => $totalOpen,
                'atrasadas' => $atrasadas,
                'em_multa' => $emMulta,
                'vence_hoje' => $venceHoje,
                'em_progresso' => $emProgresso,
                'concluidas' => $concluidas,
                'sem_responsavel' => $semResponsavel,
            ],
            'by_department' => $byDepartment,
            'by_assignee' => $byAssignee,
            'top_risks' => array_slice($riskRows, 0, 15),
            'processes_without_owner' => $processesWithoutOwner,
            'filters_effective' => [
                'office_id' => $office->id,
            ],
        ];
    }
}
