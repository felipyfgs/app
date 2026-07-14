<?php

namespace App\Support;

use App\Enums\OfficeRole;
use App\Models\Office;
use App\Models\OfficeMembership;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use RuntimeException;

class CurrentOffice
{
    private ?Office $office = null;

    private ?OfficeMembership $membership = null;

    private ?OfficeRole $role = null;

    public function resolve(?Authenticatable $user = null): ?Office
    {
        $user = $user ?? auth()->user();

        if (! $user instanceof User || ! $user->is_active) {
            $this->clear();

            return null;
        }

        if ($this->office !== null && $this->membership?->user_id === $user->id) {
            return $this->office;
        }

        $membership = $user->activeMembership();

        if ($membership === null) {
            $this->clear();

            return null;
        }

        $this->membership = $membership;
        $this->office = $membership->office;
        $this->role = $membership->role;

        return $this->office;
    }

    public function id(): ?int
    {
        return $this->resolve()?->id;
    }

    public function office(): Office
    {
        $office = $this->resolve();

        if ($office === null) {
            throw new RuntimeException('Nenhum escritório ativo para o usuário autenticado.');
        }

        return $office;
    }

    public function role(): ?OfficeRole
    {
        $this->resolve();

        return $this->role;
    }

    public function membership(): ?OfficeMembership
    {
        $this->resolve();

        return $this->membership;
    }

    public function clear(): void
    {
        $this->office = null;
        $this->membership = null;
        $this->role = null;
    }

    /**
     * Never trust client-supplied office_id. Always use resolved office.
     */
    public function assertBelongsToOffice(int $officeId): void
    {
        if ($this->id() !== $officeId) {
            abort(404);
        }
    }
}
