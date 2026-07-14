<?php

namespace App\Http\Middleware;

use App\Enums\OfficeRole;
use App\Models\User;
use App\Support\CurrentOffice;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminTwoFactor
{
    public function __construct(private readonly CurrentOffice $currentOffice) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! config('fortify.two_factor_required', true)) {
            return $next($request);
        }

        $user = $request->user();

        if (! $user instanceof User) {
            return $next($request);
        }

        $role = $this->currentOffice->role();

        if ($role !== OfficeRole::Admin) {
            return $next($request);
        }

        if ($user->hasConfirmedTwoFactor()) {
            return $next($request);
        }

        if ($this->isTwoFactorSetupRoute($request)) {
            return $next($request);
        }

        return response()->json([
            'message' => 'Confirme o segundo fator (TOTP) para acessar funções administrativas.',
            'code' => 'two_factor_required',
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
