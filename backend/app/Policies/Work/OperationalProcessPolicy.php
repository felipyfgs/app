<?php

namespace App\Policies\Work;

use App\Models\OperationalProcess;
use App\Models\User;
use App\Support\CurrentOffice;

class OperationalProcessPolicy
{
    public function viewAny(User $user): bool
    {
        return app(CurrentOffice::class)->role()?->canViewWork() === true;
    }

    public function view(User $user, OperationalProcess $process): bool
    {
        return $this->sameOffice($process);
    }

    public function create(User $user): bool
    {
        return app(CurrentOffice::class)->role()?->canCreateWorkProcesses() === true;
    }

    public function update(User $user, OperationalProcess $process): bool
    {
        if (! $this->sameOffice($process)) {
            return false;
        }
        $role = app(CurrentOffice::class)->role();

        return $role?->canAdministerWork() === true || $role?->canCreateWorkProcesses() === true;
    }

    public function archive(User $user, OperationalProcess $process): bool
    {
        return $this->sameOffice($process)
            && app(CurrentOffice::class)->role()?->canAdministerWork() === true;
    }

    public function comment(User $user, OperationalProcess $process): bool
    {
        return $this->sameOffice($process)
            && app(CurrentOffice::class)->role()?->canExecuteWorkTasks() === true;
    }

    private function sameOffice(OperationalProcess $process): bool
    {
        $officeId = app(CurrentOffice::class)->id();

        return $officeId !== null && $officeId === (int) $process->office_id;
    }
}
