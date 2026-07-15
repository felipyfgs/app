<?php

namespace App\Support;

use App\Enums\OfficeRole;
use App\Models\Office;
use App\Models\OfficeMembership;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use RuntimeException;

/**
 * Contexto do tenant ativo derivado exclusivamente de membership autorizada.
 * Nunca confia office_id fornecido livremente pelo cliente.
 *
 * Ordem de resolução:
 * 1. Sessão SPA (`current_office_id`) se membership ainda válida
 * 2. `users.selected_office_id` (troca explícita persistida)
 * 3. Primeira membership ativa (determinística por id)
 */
class CurrentOffice
{
    public const SESSION_KEY = 'current_office_id';

    private ?Office $office = null;

    private ?OfficeMembership $membership = null;

    private ?OfficeRole $role = null;

    private ?int $boundUserId = null;

    public function resolve(?Authenticatable $user = null): ?Office
    {
        $user = $user ?? auth()->user();

        if (! $user instanceof User || ! $user->is_active) {
            $this->clear();

            return null;
        }

        if ($this->office !== null && $this->boundUserId === $user->id) {
            return $this->office;
        }

        $membership = $this->resolveMembership($user);

        if ($membership === null) {
            $this->clear();

            return null;
        }

        $this->bind($user, $membership);

        return $this->office;
    }

    /**
     * Resolve membership ativa: sessão → preferência persistida → primeira ativa.
     */
    public function resolveMembership(User $user): ?OfficeMembership
    {
        $candidates = [];

        $sessionOfficeId = $this->sessionOfficeId();
        if ($sessionOfficeId !== null) {
            $candidates[] = $sessionOfficeId;
        }

        if ($user->selected_office_id !== null) {
            $candidates[] = (int) $user->selected_office_id;
        }

        foreach (array_unique($candidates) as $officeId) {
            $membership = $this->activeMembershipFor($user, $officeId);
            if ($membership !== null) {
                return $membership;
            }

            // Preferência inválida (revogada / office inativo)
            if ($sessionOfficeId === $officeId) {
                $this->forgetSessionOfficeId();
            }
            if ((int) $user->selected_office_id === $officeId) {
                $user->forceFill(['selected_office_id' => null])->saveQuietly();
            }
        }

        return $user->memberships()
            ->where('is_active', true)
            ->whereHas('office', fn ($q) => $q->where('is_active', true))
            ->with('office')
            ->orderBy('id')
            ->first();
    }

    public function bind(User $user, OfficeMembership $membership): void
    {
        $this->boundUserId = $user->id;
        $this->membership = $membership;
        $this->office = $membership->office;
        $this->role = $membership->role;
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
        $this->boundUserId = null;
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

    private function activeMembershipFor(User $user, int $officeId): ?OfficeMembership
    {
        return $user->memberships()
            ->where('office_id', $officeId)
            ->where('is_active', true)
            ->whereHas('office', fn ($q) => $q->where('is_active', true))
            ->with('office')
            ->first();
    }

    private function sessionOfficeId(): ?int
    {
        if (! app()->bound('request')) {
            return null;
        }

        $request = request();
        if ($request === null || ! $request->hasSession()) {
            return null;
        }

        $value = $request->session()->get(self::SESSION_KEY);

        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private function forgetSessionOfficeId(): void
    {
        if (! app()->bound('request')) {
            return;
        }

        $request = request();
        if ($request === null || ! $request->hasSession()) {
            return;
        }

        $request->session()->forget(self::SESSION_KEY);
    }
}
