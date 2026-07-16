<?php

namespace App\Support;

use App\Enums\OfficeAccessMode;
use App\Enums\OfficeRole;
use App\Models\Office;
use App\Models\OfficeMembership;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

/**
 * Contexto do tenant ativo.
 *
 * Ordem de resolução:
 * 1. Modo privilegiado PLATFORM_ADMIN (`platform_selected_office_id`) se flag ON
 * 2. Sessão SPA (`current_office_id`) se membership ainda válida
 * 3. `users.selected_office_id` (troca explícita persistida)
 * 4. Primeira membership ativa (determinística por id)
 *
 * Nunca confia office_id fornecido livremente pelo cliente HTTP.
 */
class CurrentOffice
{
    public const SESSION_KEY = 'current_office_id';

    /** @see PlatformPrivilegedContext::SESSION_KEY */
    public const PLATFORM_SESSION_KEY = PlatformPrivilegedContext::SESSION_KEY;

    private ?Office $office = null;

    private ?OfficeMembership $membership = null;

    private ?OfficeRole $role = null;

    private ?OfficeAccessMode $accessMode = null;

    private ?int $boundUserId = null;

    private ?User $actor = null;

    public function resolve(?Authenticatable $user = null): ?Office
    {
        $user = $user ?? auth()->user();

        // Já ligado explicitamente (bind / bindPlatformPrivileged) — reutiliza se o ator bate.
        if ($this->office !== null && $this->boundUserId !== null) {
            if ($user === null || ($user instanceof User && $user->id === $this->boundUserId)) {
                return $this->office;
            }
        }

        if (! $user instanceof User || ! $user->is_active) {
            $this->clear();

            return null;
        }

        // 1. Contexto privilegiado da plataforma (sem membership fictícia)
        if ($this->tryBindPlatformPrivileged($user)) {
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
     * Não considera seleção privilegiada da plataforma.
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
        $this->accessMode = OfficeAccessMode::Membership;
        $this->actor = $user;
    }

    /**
     * Liga contexto privilegiado (ator real, papel efetivo ADMIN, sem membership).
     */
    public function bindPlatformPrivileged(User $user, Office $office): void
    {
        $this->boundUserId = $user->id;
        $this->membership = null;
        $this->office = $office;
        $this->role = OfficeRole::Admin;
        $this->accessMode = OfficeAccessMode::PlatformPrivileged;
        $this->actor = $user;
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

    public function accessMode(): ?OfficeAccessMode
    {
        $this->resolve();

        return $this->accessMode;
    }

    /**
     * Ator real do contexto (usuário autenticado). Em modo privilegiado é o PLATFORM_ADMIN.
     */
    public function actor(): ?User
    {
        $this->resolve();

        return $this->actor;
    }

    public function isPlatformPrivileged(): bool
    {
        $this->resolve();

        return $this->accessMode === OfficeAccessMode::PlatformPrivileged;
    }

    public function clear(): void
    {
        $this->office = null;
        $this->membership = null;
        $this->role = null;
        $this->accessMode = null;
        $this->boundUserId = null;
        $this->actor = null;
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

    private function tryBindPlatformPrivileged(User $user): bool
    {
        if (! $user->isPlatformAdmin()) {
            return false;
        }

        if (! FeatureFlags::isPlatformPrivilegedContextEnabled()) {
            return false;
        }

        $platformOfficeId = $this->platformSelectedOfficeId($user);
        if ($platformOfficeId === null) {
            return false;
        }

        $office = Office::query()
            ->whereKey($platformOfficeId)
            ->where('is_active', true)
            ->first();

        if ($office === null) {
            $this->forgetPlatformSelection($user);

            return false;
        }

        $this->bindPlatformPrivileged($user, $office);

        return true;
    }

    /**
     * Office id privilegiado: sessão SPA → cache por usuário (token/testes).
     * Nunca usa users.selected_office_id.
     */
    public function platformSelectedOfficeId(?User $user = null): ?int
    {
        $fromSession = $this->sessionInt(self::PLATFORM_SESSION_KEY);
        if ($fromSession !== null) {
            return $fromSession;
        }

        $user ??= auth()->user() instanceof User ? auth()->user() : null;
        if ($user instanceof User) {
            $cached = Cache::get($this->platformCacheKey($user));
            if (is_numeric($cached)) {
                return (int) $cached;
            }
        }

        return null;
    }

    /**
     * Persistência da seleção privilegiada (sessão + cache; sem membership).
     */
    public function rememberPlatformSelection(User $user, int $officeId): void
    {
        if (app()->bound('request')) {
            $request = request();
            if ($request !== null && $request->hasSession()) {
                $request->session()->put(self::PLATFORM_SESSION_KEY, $officeId);
            }
        }

        Cache::put(
            $this->platformCacheKey($user),
            $officeId,
            now()->addDays(7),
        );
    }

    public function forgetPlatformSelection(?User $user = null): void
    {
        $this->forgetSessionKey(self::PLATFORM_SESSION_KEY);

        $user ??= auth()->user() instanceof User ? auth()->user() : null;
        if ($user instanceof User) {
            Cache::forget($this->platformCacheKey($user));
        }
    }

    public function platformCacheKey(User $user): string
    {
        return 'platform.selected_office.'.$user->id;
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
        return $this->sessionInt(self::SESSION_KEY);
    }

    private function sessionInt(string $key): ?int
    {
        if (! app()->bound('request')) {
            return null;
        }

        $request = request();
        if ($request === null || ! $request->hasSession()) {
            return null;
        }

        $value = $request->session()->get($key);

        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private function forgetSessionOfficeId(): void
    {
        $this->forgetSessionKey(self::SESSION_KEY);
    }

    private function forgetSessionKey(string $key): void
    {
        if (! app()->bound('request')) {
            return;
        }

        $request = request();
        if ($request === null || ! $request->hasSession()) {
            return;
        }

        $request->session()->forget($key);
    }
}
