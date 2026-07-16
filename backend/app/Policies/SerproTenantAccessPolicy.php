<?php

namespace App\Policies;

use App\Enums\OfficeRole;
use App\Models\User;
use App\Support\CurrentOffice;

/**
 * Dados fiscais/tenant SERPRO exigem CurrentOffice resolvido (membership ou
 * platform_privileged). PLATFORM_ADMIN sem seleção privilegiada e sem membership
 * não obtém acesso fiscal implícito.
 */
final class SerproTenantAccessPolicy
{
    public function __construct(
        private readonly CurrentOffice $currentOffice,
    ) {}

    public function viewTenantSerpro(User $user): bool
    {
        if ($this->currentOffice->resolve($user) === null) {
            return false;
        }

        $role = $this->currentOffice->role();

        return in_array($role, [OfficeRole::Admin, OfficeRole::Operator, OfficeRole::Viewer], true);
    }

    public function mutateTenantSerpro(User $user): bool
    {
        if ($this->currentOffice->resolve($user) === null) {
            return false;
        }

        return $this->currentOffice->role() === OfficeRole::Admin;
    }

    public function operateTenantSerpro(User $user): bool
    {
        if ($this->currentOffice->resolve($user) === null) {
            return false;
        }

        $role = $this->currentOffice->role();

        return in_array($role, [OfficeRole::Admin, OfficeRole::Operator], true);
    }
}
