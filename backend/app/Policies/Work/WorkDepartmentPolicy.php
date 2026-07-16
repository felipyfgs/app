<?php

namespace App\Policies\Work;

use App\Models\User;
use App\Models\WorkDepartment;
use App\Support\CurrentOffice;

class WorkDepartmentPolicy
{
    public function viewAny(User $user): bool
    {
        return app(CurrentOffice::class)->role()?->canViewWork() === true;
    }

    public function view(User $user, WorkDepartment $department): bool
    {
        return $this->sameOffice($department);
    }

    public function create(User $user): bool
    {
        return app(CurrentOffice::class)->role()?->canManageWorkCatalog() === true;
    }

    public function update(User $user, WorkDepartment $department): bool
    {
        return $this->sameOffice($department)
            && app(CurrentOffice::class)->role()?->canManageWorkCatalog() === true;
    }

    public function delete(User $user, WorkDepartment $department): bool
    {
        return $this->update($user, $department);
    }

    private function sameOffice(WorkDepartment $department): bool
    {
        $officeId = app(CurrentOffice::class)->id();

        return $officeId !== null && $officeId === (int) $department->office_id;
    }
}
