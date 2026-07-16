<?php

namespace App\Services\Work;

use App\Domain\Work\DueDateCalculator;
use App\Domain\Work\QueueBucketResolver;
use App\Domain\Work\WorkRiskCalculator;
use App\Enums\OfficeRole;
use App\Enums\Work\QueueBucket;
use App\Enums\Work\TaskStatus;
use App\Enums\Work\WorkRisk;
use App\Models\OperationalTask;
use App\Support\CurrentOffice;
use App\Support\Work\OfficeTimezone;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Fila determinística “Minha fila” com buckets e filtros.
 */
final class OperationalQueueQuery
{
    public function __construct(
        private readonly CurrentOffice $currentOffice,
        private readonly WorkRiskCalculator $risks = new WorkRiskCalculator,
        private readonly QueueBucketResolver $buckets = new QueueBucketResolver,
        private readonly DueDateCalculator $dates = new DueDateCalculator,
    ) {}

    /**
     * @param  array{
     *   tab?: string,
     *   department_id?: int|null,
     *   assignee_membership_id?: int|null,
     *   client_id?: int|null,
     *   q?: string|null,
     *   per_page?: int,
     *   page?: int,
     *   scope?: string
     * }  $filters
     */
    public function paginate(array $filters = []): LengthAwarePaginator
    {
        $office = $this->currentOffice->office();
        $tz = OfficeTimezone::for($office);
        $today = $this->dates->todayInOffice($tz);
        $role = $this->currentOffice->role();
        $membership = $this->currentOffice->membership();

        $query = OperationalTask::query()
            ->with([
                'process:id,title,client_id,competence,due_date,subject_to_fine,status,office_id',
                'process.client:id,legal_name,display_name,root_cnpj',
                'department:id,name,code,color',
                'assigneeMembership:id,user_id,office_id',
                'assigneeMembership.user:id,name',
            ])
            ->where('operational_tasks.office_id', $office->id);

        $tab = $filters['tab'] ?? 'open';
        if ($tab === 'concluidas' || $tab === 'completed') {
            $query->whereIn('status', [TaskStatus::Concluida->value, TaskStatus::Dispensada->value]);
        } elseif ($tab === 'impedidas') {
            $query->where('status', TaskStatus::Impedida->value);
        } else {
            $query->whereIn('status', [
                TaskStatus::AFazer->value,
                TaskStatus::EmProgresso->value,
                TaskStatus::Impedida->value,
            ]);
        }

        // Escopo padrão OPERATOR: próprias + livres do departamento
        $scope = $filters['scope'] ?? 'default';
        if ($role === OfficeRole::Operator && $scope !== 'office' && $membership !== null) {
            $query->where(function (Builder $q) use ($membership): void {
                $q->where('assignee_membership_id', $membership->id);
                if ($membership->work_department_id !== null) {
                    $q->orWhere(function (Builder $inner) use ($membership): void {
                        $inner->whereNull('assignee_membership_id')
                            ->where('work_department_id', $membership->work_department_id);
                    });
                }
            });
        }

        if (! empty($filters['department_id'])) {
            $query->where('work_department_id', (int) $filters['department_id']);
        }
        if (! empty($filters['assignee_membership_id'])) {
            $query->where('assignee_membership_id', (int) $filters['assignee_membership_id']);
        }
        if (! empty($filters['client_id'])) {
            $query->whereHas('process', fn (Builder $q) => $q->where('client_id', (int) $filters['client_id']));
        }
        if (! empty($filters['q'])) {
            $needle = '%'.mb_strtolower((string) $filters['q']).'%';
            $query->where(function (Builder $q) use ($needle): void {
                $q->whereRaw('LOWER(title) LIKE ?', [$needle])
                    ->orWhereHas('process', fn (Builder $p) => $p->whereRaw('LOWER(title) LIKE ?', [$needle]));
            });
        }

        $perPage = min(max((int) ($filters['per_page'] ?? 25), 1), 100);
        $tasks = $query->get();

        $enriched = $tasks->map(function (OperationalTask $task) use ($today) {
            $process = $task->process;
            $effectiveDue = $this->risks->effectiveDueDate(
                $task->due_date?->format('Y-m-d'),
                $process?->due_date?->format('Y-m-d'),
            );
            $riskList = $this->risks->forTask(
                $task->status,
                $task->due_date?->format('Y-m-d'),
                $process?->due_date?->format('Y-m-d'),
                (bool) ($process?->subject_to_fine),
                $task->assignee_membership_id,
                $today,
            );
            $bucket = $this->buckets->resolve($task->status, $riskList, $effectiveDue, $today);

            // Filtro de aba por bucket
            return [
                'task' => $task,
                'bucket' => $bucket,
                'risks' => array_map(fn (WorkRisk $r) => $r->value, $riskList),
                'effective_due' => $effectiveDue,
                'sort' => [
                    'bucket' => $bucket,
                    'effective_due' => $effectiveDue,
                    'is_critical' => (bool) $task->is_critical,
                    'created_at' => (string) $task->created_at,
                    'id' => (int) $task->id,
                ],
            ];
        });

        $tabFilter = $filters['tab'] ?? null;
        if ($tabFilter === 'hoje') {
            $enriched = $enriched->filter(fn ($i) => $i['bucket'] === QueueBucket::VenceHoje
                || $i['bucket'] === QueueBucket::EmMulta
                || $i['bucket'] === QueueBucket::Atrasada);
        } elseif ($tabFilter === 'atrasadas') {
            $enriched = $enriched->filter(fn ($i) => in_array(WorkRisk::Atrasada->value, $i['risks'], true)
                || in_array(WorkRisk::EmMulta->value, $i['risks'], true));
        } elseif ($tabFilter === 'semana') {
            $enriched = $enriched->filter(fn ($i) => in_array($i['bucket'], [
                QueueBucket::VenceHoje,
                QueueBucket::VenceEmTresDias,
                QueueBucket::Atrasada,
                QueueBucket::EmMulta,
            ], true));
        }

        $sorted = $enriched->sort(fn ($a, $b) => $this->buckets->compare($a['sort'], $b['sort']))->values();

        $page = max((int) ($filters['page'] ?? 1), 1);
        $total = $sorted->count();
        $slice = $sorted->slice(($page - 1) * $perPage, $perPage)->values();

        $items = $slice->map(fn ($row) => [
            'id' => $row['task']->id,
            'title' => $row['task']->title,
            'status' => $row['task']->status->value,
            'due_date' => $row['task']->due_date?->format('Y-m-d'),
            'effective_due_date' => $row['effective_due'],
            'is_critical' => $row['task']->is_critical,
            'is_required' => $row['task']->is_required,
            'requires_evidence' => $row['task']->requires_evidence,
            'block_reason' => $row['task']->block_reason,
            'lock_version' => $row['task']->lock_version,
            'bucket' => $row['bucket']->value,
            'risks' => $row['risks'],
            'department' => $row['task']->department ? [
                'id' => $row['task']->department->id,
                'name' => $row['task']->department->name,
                'code' => $row['task']->department->code,
            ] : null,
            'assignee' => $row['task']->assigneeMembership?->user ? [
                'membership_id' => $row['task']->assigneeMembership->id,
                'name' => $row['task']->assigneeMembership->user->name,
            ] : null,
            'process' => $row['task']->process ? [
                'id' => $row['task']->process->id,
                'title' => $row['task']->process->title,
                'competence' => $row['task']->process->competence,
                'status' => $row['task']->process->status->value,
                'subject_to_fine' => $row['task']->process->subject_to_fine,
                'client' => $row['task']->process->client ? [
                    'id' => $row['task']->process->client->id,
                    'name' => $row['task']->process->client->display_name
                        ?: $row['task']->process->client->legal_name,
                ] : null,
            ] : null,
        ]);

        return new \Illuminate\Pagination\LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()],
        );
    }
}
