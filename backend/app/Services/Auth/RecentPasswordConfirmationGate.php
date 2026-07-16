<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Contracts\Session\Session;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Janela de reconfirmação de senha para ações sensíveis em contexto privilegiado.
 * Independente do login: exige re-entrada da senha dentro da janela configurável.
 */
final class RecentPasswordConfirmationGate
{
    public const SESSION_KEY = 'auth.password_confirmed_at';

    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    public function windowMinutes(): int
    {
        return max(1, (int) config('auth.password_confirmation_window_minutes', 15));
    }

    public function isRecentlyConfirmed(?User $user = null, ?Request $request = null): bool
    {
        $user ??= auth()->user();
        if (! $user instanceof User) {
            return false;
        }

        $confirmedAt = $this->confirmedAtTimestamp($request, $user);
        if ($confirmedAt === null) {
            return false;
        }

        $expires = $confirmedAt + ($this->windowMinutes() * 60);

        return time() < $expires;
    }

    public function secondsRemaining(?Request $request = null, ?User $user = null): int
    {
        $confirmedAt = $this->confirmedAtTimestamp($request, $user);
        if ($confirmedAt === null) {
            return 0;
        }

        $expires = $confirmedAt + ($this->windowMinutes() * 60);

        return max(0, $expires - time());
    }

    /**
     * Valida a senha do ator e grava timestamp na sessão.
     *
     * @throws RuntimeException
     */
    public function confirmWithPassword(User $user, string $password, ?Request $request = null): void
    {
        $password = (string) $password;
        if ($password === '') {
            throw new RuntimeException('Senha obrigatória.');
        }

        if (! Hash::check($password, $user->password)) {
            $this->audit->record('auth.password_challenge', 'FAILURE', $user, [
                'reason' => 'invalid_password',
            ], $user->id);

            throw new RuntimeException('Senha inválida.');
        }

        $this->markConfirmed($user, $request);
    }

    /**
     * Marca confirmação recente (uso interno / testes).
     */
    public function markConfirmed(User $user, ?Request $request = null): void
    {
        $now = time();
        $session = $this->session($request);
        if ($session !== null) {
            $session->put(self::SESSION_KEY, $now);
        }

        $req = $request ?? request();
        $req?->attributes->set(self::SESSION_KEY, $now);

        Cache::put(
            $this->cacheKey($user),
            $now,
            now()->addMinutes($this->windowMinutes() + 1),
        );

        $this->audit->record('auth.password_challenge', 'SUCCESS', $user, [
            'window_minutes' => $this->windowMinutes(),
        ], $user->id);
    }

    public function clear(?Request $request = null, ?User $user = null): void
    {
        $session = $this->session($request);
        $session?->forget(self::SESSION_KEY);
        request()?->attributes->remove(self::SESSION_KEY);
        $user ??= auth()->user();
        if ($user instanceof User) {
            Cache::forget($this->cacheKey($user));
        }
    }

    /**
     * Simula expiração da janela (testes).
     */
    public function expire(?Request $request = null, ?User $user = null): void
    {
        $past = time() - (($this->windowMinutes() + 1) * 60);
        $session = $this->session($request);
        if ($session !== null) {
            $session->put(self::SESSION_KEY, $past);
        }
        request()?->attributes->set(self::SESSION_KEY, $past);
        $user ??= auth()->user();
        if ($user instanceof User) {
            Cache::put(
                $this->cacheKey($user),
                $past,
                now()->addMinutes($this->windowMinutes() + 1),
            );
        }
    }

    private function confirmedAtTimestamp(?Request $request = null, ?User $user = null): ?int
    {
        $session = $this->session($request);
        if ($session !== null) {
            $value = $session->get(self::SESSION_KEY);
            if (is_numeric($value)) {
                return (int) $value;
            }
        }

        $attr = ($request ?? request())?->attributes->get(self::SESSION_KEY);
        if (is_numeric($attr)) {
            return (int) $attr;
        }

        $user ??= auth()->user();
        if ($user instanceof User) {
            $cached = Cache::get($this->cacheKey($user));
            if (is_numeric($cached)) {
                return (int) $cached;
            }
        }

        return null;
    }

    private function cacheKey(User $user): string
    {
        return 'auth.password_confirmed.'.$user->id;
    }

    private function session(?Request $request = null): ?Session
    {
        $request ??= request();
        if ($request === null) {
            return null;
        }

        try {
            if ($request->hasSession()) {
                return $request->session();
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
    }
}
