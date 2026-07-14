<?php

namespace App\Http\Middleware;

use App\Enums\OfficeRole;
use App\Support\CurrentOffice;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOfficeRole
{
    public function __construct(private readonly CurrentOffice $currentOffice) {}

    /**
     * @param  string  ...$roles  Role values: ADMIN, OPERATOR, VIEWER
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $current = $this->currentOffice->role();

        if ($current === null) {
            return response()->json(['message' => 'Perfil não resolvido.'], 403);
        }

        $allowed = array_map(
            fn (string $role) => OfficeRole::from(strtoupper($role)),
            $roles
        );

        if (! in_array($current, $allowed, true)) {
            return response()->json(['message' => 'Ação não autorizada para o perfil atual.'], 403);
        }

        return $next($request);
    }
}
