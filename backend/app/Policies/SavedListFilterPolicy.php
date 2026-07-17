<?php

namespace App\Policies;

use App\Enums\OfficeRole;
use App\Models\SavedListFilter;
use App\Models\User;
use App\Support\CurrentOffice;

/**
 * Ownership e share de presets de filtro de lista.
 *
 * - personal: só o autor lista/edita/exclui
 * - office: membership do Office lista; publicar = ADMIN|OPERATOR; excluir office de terceiros = ADMIN
 */
class SavedListFilterPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->role($user) !== null;
    }

    public function view(User $user, SavedListFilter $filter): bool
    {
        if (! $this->sameOffice($user, $filter)) {
            return false;
        }

        if ($filter->isOfficeShared()) {
            return true;
        }

        return (int) $filter->user_id === (int) $user->id;
    }

    public function create(User $user): bool
    {
        return $this->role($user) !== null;
    }

    /**
     * Publicar/alterar visibility=office exige ADMIN ou OPERATOR.
     */
    public function shareOffice(User $user): bool
    {
        return $this->role($user)?->canShareListFilters() === true;
    }

    public function update(User $user, SavedListFilter $filter): bool
    {
        if (! $this->sameOffice($user, $filter)) {
            return false;
        }

        if ((int) $filter->user_id === (int) $user->id) {
            return true;
        }

        // ADMIN pode gerir presets office de qualquer autor no Office.
        return $filter->isOfficeShared() && $this->role($user) === OfficeRole::Admin;
    }

    public function delete(User $user, SavedListFilter $filter): bool
    {
        return $this->update($user, $filter);
    }

    private function role(User $user): ?OfficeRole
    {
        return app(CurrentOffice::class)->resolve($user)
            ? app(CurrentOffice::class)->role()
            : null;
    }

    private function sameOffice(User $user, SavedListFilter $filter): bool
    {
        $officeId = app(CurrentOffice::class)->resolve($user)?->id;

        return $officeId !== null && (int) $officeId === (int) $filter->office_id;
    }
}
