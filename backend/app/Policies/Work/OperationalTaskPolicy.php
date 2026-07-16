<?php

namespace App\Policies\Work;

use App\Models\OperationalTask;
use App\Models\User;
use App\Support\CurrentOffice;

class OperationalTaskPolicy
{
    public function viewAny(User $user): bool
    {
        return app(CurrentOffice::class)->role()?->canViewWork() === true;
    }

    public function view(User $user, OperationalTask $task): bool
    {
        return $this->sameOffice($task);
    }

    public function update(User $user, OperationalTask $task): bool
    {
        if (! $this->sameOffice($task)) {
            return false;
        }

        $role = app(CurrentOffice::class)->role();
        if ($role?->canAdministerWork() === true) {
            return true;
        }

        if ($role?->canExecuteWorkTasks() !== true) {
            return false;
        }

        $membershipId = app(CurrentOffice::class)->membership()?->id;

        // Executor: responsável ou tarefa livre do próprio departamento
        if ($task->assignee_membership_id !== null) {
            return (int) $task->assignee_membership_id === (int) $membershipId;
        }

        $dept = app(CurrentOffice::class)->membership()?->work_department_id;

        return $dept !== null && (int) $task->work_department_id === (int) $dept;
    }

    public function assign(User $user, OperationalTask $task): bool
    {
        return $this->sameOffice($task)
            && app(CurrentOffice::class)->role()?->canAdministerWork() === true;
    }

    public function claim(User $user, OperationalTask $task): bool
    {
        if (! $this->sameOffice($task)) {
            return false;
        }
        if (app(CurrentOffice::class)->role()?->canExecuteWorkTasks() !== true) {
            return false;
        }
        if ($task->assignee_membership_id !== null) {
            return false;
        }
        $dept = app(CurrentOffice::class)->membership()?->work_department_id;

        return $dept !== null && (int) $task->work_department_id === (int) $dept;
    }

    public function transition(User $user, OperationalTask $task): bool
    {
        return $this->update($user, $task);
    }

    public function dispense(User $user, OperationalTask $task): bool
    {
        return $this->sameOffice($task)
            && app(CurrentOffice::class)->role()?->canAdministerWork() === true;
    }

    public function reopen(User $user, OperationalTask $task): bool
    {
        return $this->dispense($user, $task);
    }

    public function comment(User $user, OperationalTask $task): bool
    {
        return $this->sameOffice($task)
            && app(CurrentOffice::class)->role()?->canExecuteWorkTasks() === true;
    }

    public function uploadEvidence(User $user, OperationalTask $task): bool
    {
        return $this->update($user, $task);
    }

    public function downloadEvidence(User $user, OperationalTask $task): bool
    {
        return $this->sameOffice($task)
            && app(CurrentOffice::class)->role()?->canDownloadWorkEvidence() === true;
    }

    public function bulk(User $user): bool
    {
        return app(CurrentOffice::class)->role()?->canAdministerWork() === true;
    }

    private function sameOffice(OperationalTask $task): bool
    {
        $officeId = app(CurrentOffice::class)->id();

        return $officeId !== null && $officeId === (int) $task->office_id;
    }
}
