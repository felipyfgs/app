<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Auth\RecentPasswordConfirmationGate;
use App\Support\CurrentOffice;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Em access_mode=platform_privileged, exige reconfirmação recente de senha
 * para ações sensíveis (A1 replace/remove, mutações fiscais privilegiadas).
 * Membership comum não é afetada (continua com TOTP/gates existentes).
 */
class EnsurePrivilegedPasswordConfirmation
{
    public function __construct(
        private readonly CurrentOffice $currentOffice,
        private readonly RecentPasswordConfirmationGate $passwordGate,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return $next($request);
        }

        $this->currentOffice->resolve($user);

        if (! $this->currentOffice->isPlatformPrivileged()) {
            return $next($request);
        }

        if ($this->passwordGate->isRecentlyConfirmed($user, $request)) {
            return $next($request);
        }

        return response()->json([
            'message' => 'Reconfirme sua senha para continuar esta ação privilegiada.',
            'code' => 'password_confirmation_required',
            'window_minutes' => $this->passwordGate->windowMinutes(),
            'seconds_remaining' => $this->passwordGate->secondsRemaining($request, $user),
        ], 403);
    }
}
