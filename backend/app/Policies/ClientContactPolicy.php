<?php

namespace App\Policies;

use App\Enums\OfficeRole;
use App\Models\ClientContact;
use App\Models\User;
use App\Support\CurrentOffice;

class ClientContactPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->role($user) !== null;
    }

    public function view(User $user, ClientContact $contact): bool
    {
        return $this->sameOffice($user, $contact);
    }

    public function create(User $user): bool
    {
        return $this->role($user)?->canManageClients() === true;
    }

    public function update(User $user, ClientContact $contact): bool
    {
        return $this->sameOffice($user, $contact)
            && $this->role($user)?->canManageClients() === true;
    }

    public function delete(User $user, ClientContact $contact): bool
    {
        return $this->sameOffice($user, $contact)
            && $this->role($user)?->canManageClients() === true;
    }

    private function role(User $user): ?OfficeRole
    {
        return app(CurrentOffice::class)->resolve($user)
            ? app(CurrentOffice::class)->role()
            : null;
    }

    private function sameOffice(User $user, ClientContact $contact): bool
    {
        $officeId = app(CurrentOffice::class)->resolve($user)?->id;

        return $officeId !== null && $officeId === $contact->office_id;
    }
}
