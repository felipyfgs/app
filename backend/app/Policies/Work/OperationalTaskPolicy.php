<?php

namespace App\Policies\Work;

use App\Models\OperationalTask;
use App\Models\User;
use App\Policies\Work\Concerns\UsesRealWorkRole;

class OperationalTaskPolicy
{
    use UsesRealWorkRole;

    public function viewAny(User $user): bool
    {
        return $this->effectiveRole()?->canViewWork() === true;
    }

    public function view(User $user, OperationalTask $task): bool
    {
        return $this->sameOfficeId((int) $task->office_id)
            && $this->effectiveRole()?->canViewWork() === true;
    }

    public function update(User $user, OperationalTask $task): bool
    {
        if (! $this->sameOfficeId((int) $task->office_id)) {
            return false;
        }

        $role = $this->realRole();
        if ($role === null) {
            return false;
        }

        if ($role->canAdministerWork() === true) {
            return true;
        }

        if ($role->canExecuteWorkTasks() !== true) {
            return false;
        }

        $membershipId = $this->currentOffice()->realMembership()?->id;

        // Executor: responsável ou tarefa livre do próprio departamento
        if ($task->assignee_membership_id !== null) {
            return (int) $task->assignee_membership_id === (int) $membershipId;
        }

        $dept = $this->currentOffice()->realMembership()?->work_department_id;

        return $dept !== null && (int) $task->work_department_id === (int) $dept;
    }

    public function assign(User $user, OperationalTask $task): bool
    {
        return $this->sameOfficeId((int) $task->office_id)
            && $this->realRole()?->canAdministerWork() === true;
    }

    public function claim(User $user, OperationalTask $task): bool
    {
        if (! $this->sameOfficeId((int) $task->office_id)) {
            return false;
        }
        if ($this->realRole()?->canExecuteWorkTasks() !== true) {
            return false;
        }
        if ($task->assignee_membership_id !== null) {
            return false;
        }
        $dept = $this->currentOffice()->realMembership()?->work_department_id;

        return $dept !== null && (int) $task->work_department_id === (int) $dept;
    }

    public function transition(User $user, OperationalTask $task): bool
    {
        return $this->update($user, $task);
    }

    public function dispense(User $user, OperationalTask $task): bool
    {
        return $this->sameOfficeId((int) $task->office_id)
            && $this->realRole()?->canAdministerWork() === true;
    }

    public function reopen(User $user, OperationalTask $task): bool
    {
        return $this->dispense($user, $task);
    }

    public function comment(User $user, OperationalTask $task): bool
    {
        return $this->sameOfficeId((int) $task->office_id)
            && $this->realRole()?->canExecuteWorkTasks() === true;
    }

    public function uploadEvidence(User $user, OperationalTask $task): bool
    {
        return $this->update($user, $task);
    }

    public function downloadEvidence(User $user, OperationalTask $task): bool
    {
        // Download de evidência: exige membership real (não só leitura privilegiada)
        return $this->sameOfficeId((int) $task->office_id)
            && $this->realRole()?->canDownloadWorkEvidence() === true;
    }

    public function bulk(User $user): bool
    {
        return $this->realRole()?->canAdministerWork() === true;
    }
}
