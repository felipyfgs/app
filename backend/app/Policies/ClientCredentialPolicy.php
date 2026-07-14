<?php

namespace App\Policies;

use App\Enums\OfficeRole;
use App\Models\Client;
use App\Models\ClientCredential;
use App\Models\User;
use App\Support\CurrentOffice;

class ClientCredentialPolicy
{
    public function view(User $user, Client $client): bool
    {
        return $this->sameOffice($user, $client->office_id)
            && app(CurrentOffice::class)->role() === OfficeRole::Admin;
    }

    public function manage(User $user, Client $client): bool
    {
        return $this->view($user, $client);
    }

    public function viewCredential(User $user, ClientCredential $credential): bool
    {
        return $this->sameOffice($user, $credential->office_id)
            && app(CurrentOffice::class)->role() === OfficeRole::Admin;
    }

    private function sameOffice(User $user, int $officeId): bool
    {
        return app(CurrentOffice::class)->resolve($user)?->id === $officeId;
    }
}
