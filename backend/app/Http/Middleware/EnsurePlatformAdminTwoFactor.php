<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exige TOTP confirmado para PLATFORM_ADMIN em rotas de plataforma.
 * Independente de membership de office (admin global pode não ter tenant).
 * Não substitui EnsureAdminTwoFactor do tenant (OfficeRole::Admin).
 */
class EnsurePlatformAdminTwoFactor
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('fortify.two_factor_required', true)) {
            return $next($request);
        }

        $user = $request->user();

        if (! $user instanceof User) {
            return $next($request);
        }

        if ($user->hasConfirmedTwoFactor()) {
            return $next($request);
        }

        if ($this->isTwoFactorSetupRoute($request)) {
            return $next($request);
        }

        return response()->json([
            'message' => 'Confirme o segundo fator (TOTP) para ações de administração da plataforma.',
            'code' => 'platform_two_factor_required',
        ], 403);
    }

    private function isTwoFactorSetupRoute(Request $request): bool
    {
        $path = trim($request->path(), '/');

        $allowed = [
            'api/v1/me',
            'api/v1/logout',
            'user/two-factor-authentication',
            'user/confirmed-two-factor-authentication',
            'user/two-factor-qr-code',
            'user/two-factor-secret-key',
            'user/two-factor-recovery-codes',
            'logout',
        ];

        foreach ($allowed as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                return true;
            }
        }

        return false;
    }
}
