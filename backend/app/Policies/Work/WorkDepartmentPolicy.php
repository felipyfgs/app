<?php

namespace App\Policies\Work;

use App\Models\User;
use App\Models\WorkDepartment;
use App\Policies\Work\Concerns\UsesRealWorkRole;

class WorkDepartmentPolicy
{
    use UsesRealWorkRole;

    public function viewAny(User $user): bool
    {
        return $this->effectiveRole()?->canViewWork() === true;
    }

    public function view(User $user, WorkDepartment $department): bool
    {
        return $this->sameOfficeId((int) $department->office_id)
            && $this->effectiveRole()?->canViewWork() === true;
    }

    public function create(User $user): bool
    {
        return $this->realRole()?->canManageWorkCatalog() === true;
    }

    public function update(User $user, WorkDepartment $department): bool
    {
        return $this->sameOfficeId((int) $department->office_id)
            && $this->realRole()?->canManageWorkCatalog() === true;
    }

    public function delete(User $user, WorkDepartment $department): bool
    {
        return $this->update($user, $department);
    }
}
