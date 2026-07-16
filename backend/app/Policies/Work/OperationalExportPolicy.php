<?php

namespace App\Policies\Work;

use App\Models\OperationalExport;
use App\Models\User;
use App\Policies\Work\Concerns\UsesRealWorkRole;

class OperationalExportPolicy
{
    use UsesRealWorkRole;

    public function viewAny(User $user): bool
    {
        return $this->realRole()?->canExportWork() === true;
    }

    public function view(User $user, OperationalExport $export): bool
    {
        return $this->sameOfficeId((int) $export->office_id)
            && $this->realRole()?->canExportWork() === true;
    }

    public function create(User $user): bool
    {
        return $this->realRole()?->canExportWork() === true;
    }

    public function download(User $user, OperationalExport $export): bool
    {
        return $this->view($user, $export);
    }
}
