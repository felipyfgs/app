<?php

namespace App\Policies;

use App\Enums\OfficeAccessMode;
use App\Enums\OfficeRole;
use App\Models\User;
use App\Support\CurrentOffice;

/**
 * Configuração unificada do escritório (perfil, consentimento, A1 canônico).
 * Mutações: OfficeRole::ADMIN ou PLATFORM_ADMIN em contexto privilegiado.
 * Leitura: qualquer membership ativa do office (ou privilegiado).
 */
class OfficeSettingsPolicy
{
    public function view(User $user): bool
    {
        return $this->inOffice($user) !== null || $this->isPrivilegedAdmin($user);
    }

    public function manage(User $user): bool
    {
        if ($this->isPrivilegedAdmin($user)) {
            return true;
        }

        return $this->inOffice($user) === OfficeRole::Admin;
    }

    private function inOffice(User $user): ?OfficeRole
    {
        $current = app(CurrentOffice::class);
        $office = $current->resolve($user);

        return $office !== null ? $current->role() : null;
    }

    private function isPrivilegedAdmin(User $user): bool
    {
        if (! $user->isPlatformAdmin()) {
            return false;
        }

        $current = app(CurrentOffice::class);
        if ($current->resolve($user) === null) {
            return false;
        }

        return $current->accessMode() === OfficeAccessMode::PlatformPrivileged
            || $current->role() === OfficeRole::Admin;
    }
}
