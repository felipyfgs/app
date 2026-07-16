<?php

namespace App\Policies\Work;

use App\Models\OperationalProcess;
use App\Models\User;
use App\Policies\Work\Concerns\UsesRealWorkRole;

class OperationalProcessPolicy
{
    use UsesRealWorkRole;

    public function viewAny(User $user): bool
    {
        return $this->effectiveRole()?->canViewWork() === true;
    }

    public function view(User $user, OperationalProcess $process): bool
    {
        return $this->sameOfficeId((int) $process->office_id)
            && $this->effectiveRole()?->canViewWork() === true;
    }

    public function create(User $user): bool
    {
        return $this->realRole()?->canCreateWorkProcesses() === true;
    }

    public function update(User $user, OperationalProcess $process): bool
    {
        if (! $this->sameOfficeId((int) $process->office_id)) {
            return false;
        }
        $role = $this->realRole();
        if ($role === null) {
            return false;
        }

        return $role->canAdministerWork() === true || $role->canCreateWorkProcesses() === true;
    }

    public function archive(User $user, OperationalProcess $process): bool
    {
        return $this->sameOfficeId((int) $process->office_id)
            && $this->realRole()?->canAdministerWork() === true;
    }

    public function comment(User $user, OperationalProcess $process): bool
    {
        return $this->sameOfficeId((int) $process->office_id)
            && $this->realRole()?->canExecuteWorkTasks() === true;
    }
}
