<?php

namespace App\Policies;

use App\Enums\OfficeRole;
use App\Models\OfficeCredential;
use App\Models\OfficeFiscalIdentity;
use App\Models\User;
use App\Support\CurrentOffice;

/**
 * Leitura de metadados: ADMIN, OPERATOR, VIEWER do office.
 * Mutação (A1): somente ADMIN (+ middleware EnsureAdminTwoFactor na rota).
 */
class OfficeFiscalCredentialPolicy
{
    public function view(User $user): bool
    {
        return $this->inOffice($user) !== null;
    }

    public function manage(User $user): bool
    {
        return $this->inOffice($user) === OfficeRole::Admin;
    }

    public function viewIdentity(User $user, OfficeFiscalIdentity $identity): bool
    {
        return $this->sameOffice($user, $identity->office_id);
    }

    public function manageIdentity(User $user, OfficeFiscalIdentity $identity): bool
    {
        return $this->sameOffice($user, $identity->office_id)
            && app(CurrentOffice::class)->role() === OfficeRole::Admin;
    }

    public function viewCredential(User $user, OfficeCredential $credential): bool
    {
        return $this->sameOffice($user, $credential->office_id);
    }

    public function manageCredential(User $user, OfficeCredential $credential): bool
    {
        return $this->sameOffice($user, $credential->office_id)
            && app(CurrentOffice::class)->role() === OfficeRole::Admin;
    }

    private function inOffice(User $user): ?OfficeRole
    {
        $office = app(CurrentOffice::class)->resolve($user);

        return $office !== null ? app(CurrentOffice::class)->role() : null;
    }

    private function sameOffice(User $user, int $officeId): bool
    {
        return app(CurrentOffice::class)->resolve($user)?->id === $officeId;
    }
}
