<?php

namespace App\Policies\Work;

use App\Models\ProcessTemplate;
use App\Models\User;
use App\Policies\Work\Concerns\UsesRealWorkRole;

class ProcessTemplatePolicy
{
    use UsesRealWorkRole;

    public function viewAny(User $user): bool
    {
        return $this->effectiveRole()?->canViewWork() === true;
    }

    public function view(User $user, ProcessTemplate $template): bool
    {
        return $this->sameOfficeId((int) $template->office_id)
            && $this->effectiveRole()?->canViewWork() === true;
    }

    public function create(User $user): bool
    {
        return $this->realRole()?->canManageWorkCatalog() === true;
    }

    public function update(User $user, ProcessTemplate $template): bool
    {
        return $this->sameOfficeId((int) $template->office_id)
            && $this->realRole()?->canManageWorkCatalog() === true;
    }

    public function generate(User $user, ProcessTemplate $template): bool
    {
        return $this->sameOfficeId((int) $template->office_id)
            && $template->is_active
            && $this->realRole()?->canCreateWorkProcesses() === true;
    }
}
