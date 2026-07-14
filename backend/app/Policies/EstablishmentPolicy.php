<?php

namespace App\Policies;

use App\Enums\OfficeRole;
use App\Models\Establishment;
use App\Models\User;
use App\Support\CurrentOffice;

class EstablishmentPolicy
{
    public function viewAny(User $user): bool
    {
        return app(CurrentOffice::class)->resolve($user) !== null;
    }

    public function view(User $user, Establishment $establishment): bool
    {
        return $this->sameOffice($user, $establishment);
    }

    public function create(User $user): bool
    {
        $role = app(CurrentOffice::class)->role();

        return $role?->canManageClients() === true;
    }

    public function update(User $user, Establishment $establishment): bool
    {
        return $this->sameOffice($user, $establishment)
            && app(CurrentOffice::class)->role()?->canManageClients() === true;
    }

    public function delete(User $user, Establishment $establishment): bool
    {
        return $this->sameOffice($user, $establishment)
            && app(CurrentOffice::class)->role() === OfficeRole::Admin;
    }

    private function sameOffice(User $user, Establishment $establishment): bool
    {
        $officeId = app(CurrentOffice::class)->resolve($user)?->id;

        return $officeId !== null && $officeId === $establishment->office_id;
    }
}
