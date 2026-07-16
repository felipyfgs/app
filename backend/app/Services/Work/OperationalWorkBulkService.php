<?php

namespace App\Services\Work;

use App\Models\OperationalTask;
use App\Services\Audit\AuditLogger;
use App\Support\CurrentOffice;
use App\Support\Work\OptimisticLock;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Operações em lote atômicas (ADMIN).
 */
final class OperationalWorkBulkService
{
    public const MAX_ITEMS = 100;

    public function __construct(
        private readonly CurrentOffice $currentOffice,
        private readonly MembershipResolver $memberships,
        private readonly OperationalTaskTransitionService $transitions,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  list<array{id: int, lock_version: int}>  $items
     * @param  array{
     *   assignee_membership_id?: int|null,
     *   work_department_id?: int|null,
     *   due_date?: string|null,
     *   status?: string|null
     * }  $changes
     * @return list<OperationalTask>
     */
    public function apply(array $items, array $changes): array
    {
        if (count($items) === 0) {
            throw ValidationException::withMessages(['items' => ['Lote vazio.']]);
        }
        if (count($items) > self::MAX_ITEMS) {
            throw ValidationException::withMessages([
                'items' => ['Lote excede o limite de '.self::MAX_ITEMS.' itens.'],
            ]);
        }

        $officeId = $this->currentOffice->id();
        $ids = array_map(fn ($i) => (int) $i['id'], $items);
        $versionMap = [];
        foreach ($items as $i) {
            $versionMap[(int) $i['id']] = (int) $i['lock_version'];
        }

        $tasks = OperationalTask::query()
            ->where('office_id', $officeId)
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        if ($tasks->count() !== count($ids)) {
            throw ValidationException::withMessages([
                'items' => ['Um ou mais itens são inválidos ou de outro escritório.'],
            ]);
        }

        foreach ($ids as $id) {
            OptimisticLock::assert($tasks[$id], $versionMap[$id], 'operational_task');
        }

        if (array_key_exists('assignee_membership_id', $changes) && $changes['assignee_membership_id'] !== null) {
            $this->memberships->requireActiveMembership((int) $changes['assignee_membership_id']);
        }
        if (array_key_exists('work_department_id', $changes) && $changes['work_department_id'] !== null) {
            $this->memberships->requireActiveDepartment((int) $changes['work_department_id']);
        }

        $correlation = $this->audit->correlationId();

        return DB::transaction(function () use ($tasks, $versionMap, $changes, $correlation): array {
            $updated = [];
            foreach ($tasks as $id => $task) {
                $attrs = [];
                if (array_key_exists('assignee_membership_id', $changes)) {
                    $attrs['assignee_membership_id'] = $changes['assignee_membership_id'];
                }
                if (array_key_exists('work_department_id', $changes)) {
                    $attrs['work_department_id'] = $changes['work_department_id'];
                }
                if (array_key_exists('due_date', $changes)) {
                    $attrs['due_date'] = $changes['due_date'];
                }

                if ($attrs !== []) {
                    OptimisticLock::updateOrConflict($task, $versionMap[$id], $attrs, 'operational_task');
                    $task->refresh();
                }

                if (! empty($changes['status'])) {
                    match ($changes['status']) {
                        'EM_PROGRESSO' => $this->transitions->start($task, (int) $task->lock_version),
                        default => null,
                    };
                    $task->refresh();
                }

                $this->audit->record('work.task.bulk', 'SUCCESS', $task, [
                    'correlation' => $correlation,
                    'changes' => array_keys($changes),
                ]);
                $updated[] = $task;
            }

            return $updated;
        });
    }
}
