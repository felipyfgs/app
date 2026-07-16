<?php

namespace App\Policies\Work;

use App\Models\OperationalExport;
use App\Models\User;
use App\Support\CurrentOffice;

class OperationalExportPolicy
{
    public function viewAny(User $user): bool
    {
        return app(CurrentOffice::class)->role()?->canExportWork() === true;
    }

    public function view(User $user, OperationalExport $export): bool
    {
        return $this->sameOffice($export)
            && app(CurrentOffice::class)->role()?->canExportWork() === true;
    }

    public function create(User $user): bool
    {
        return app(CurrentOffice::class)->role()?->canExportWork() === true;
    }

    public function download(User $user, OperationalExport $export): bool
    {
        return $this->view($user, $export);
    }

    private function sameOffice(OperationalExport $export): bool
    {
        $officeId = app(CurrentOffice::class)->id();

        return $officeId !== null && $officeId === (int) $export->office_id;
    }
}
