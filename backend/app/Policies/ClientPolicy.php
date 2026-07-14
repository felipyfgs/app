<?php

namespace App\Policies;

use App\Enums\OfficeRole;
use App\Models\Client;
use App\Models\User;
use App\Support\CurrentOffice;

class ClientPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->role($user) !== null;
    }

    public function view(User $user, Client $client): bool
    {
        return $this->sameOffice($user, $client);
    }

    public function create(User $user): bool
    {
        $role = $this->role($user);

        return $role?->canManageClients() === true;
    }

    public function update(User $user, Client $client): bool
    {
        return $this->sameOffice($user, $client)
            && $this->role($user)?->canManageClients() === true;
    }

    public function delete(User $user, Client $client): bool
    {
        return $this->sameOffice($user, $client)
            && $this->role($user) === OfficeRole::Admin;
    }

    private function role(User $user): ?OfficeRole
    {
        return app(CurrentOffice::class)->resolve($user)
            ? app(CurrentOffice::class)->role()
            : null;
    }

    private function sameOffice(User $user, Client $client): bool
    {
        $officeId = app(CurrentOffice::class)->resolve($user)?->id;

        return $officeId !== null && $officeId === $client->office_id;
    }
}
