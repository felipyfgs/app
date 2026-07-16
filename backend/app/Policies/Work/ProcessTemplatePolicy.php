<?php

namespace App\Policies\Work;

use App\Models\ProcessTemplate;
use App\Models\User;
use App\Support\CurrentOffice;

class ProcessTemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return app(CurrentOffice::class)->role()?->canViewWork() === true;
    }

    public function view(User $user, ProcessTemplate $template): bool
    {
        return $this->sameOffice($template);
    }

    public function create(User $user): bool
    {
        return app(CurrentOffice::class)->role()?->canManageWorkCatalog() === true;
    }

    public function update(User $user, ProcessTemplate $template): bool
    {
        return $this->sameOffice($template)
            && app(CurrentOffice::class)->role()?->canManageWorkCatalog() === true;
    }

    public function generate(User $user, ProcessTemplate $template): bool
    {
        return $this->sameOffice($template)
            && $template->is_active
            && app(CurrentOffice::class)->role()?->canCreateWorkProcesses() === true;
    }

    private function sameOffice(ProcessTemplate $template): bool
    {
        $officeId = app(CurrentOffice::class)->id();

        return $officeId !== null && $officeId === (int) $template->office_id;
    }
}
