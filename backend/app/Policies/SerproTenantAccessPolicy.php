<?php

namespace App\Policies;

use App\Enums\OfficeRole;
use App\Models\User;
use App\Support\CurrentOffice;

/**
 * PLATFORM_ADMIN sem membership NÃO obtém dados fiscais/tenant SERPRO.
 * Dados fiscais exigem membership ativa no office corrente.
 */
final class SerproTenantAccessPolicy
{
    public function __construct(
        private readonly CurrentOffice $currentOffice,
    ) {}

    public function viewTenantSerpro(User $user): bool
    {
        // Platform admin sozinho não basta
        if ($user->isPlatformAdmin() && $user->activeMembership() === null) {
            return false;
        }

        $role = $this->currentOffice->role();

        return in_array($role, [OfficeRole::Admin, OfficeRole::Operator, OfficeRole::Viewer], true);
    }

    public function mutateTenantSerpro(User $user): bool
    {
        if ($user->isPlatformAdmin() && $user->activeMembership() === null) {
            return false;
        }

        $role = $this->currentOffice->role();

        return $role === OfficeRole::Admin;
    }

    public function operateTenantSerpro(User $user): bool
    {
        if ($user->isPlatformAdmin() && $user->activeMembership() === null) {
            return false;
        }

        $role = $this->currentOffice->role();

        return in_array($role, [OfficeRole::Admin, OfficeRole::Operator], true);
    }
}
