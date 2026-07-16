<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\CurrentOffice;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate Work: mutações e exportações exigem OfficeMembership real no Office corrente.
 * Leitura global privilegiada NÃO passa por este middleware.
 *
 * @see config/work_route_matrix.php
 */
class EnsureWorkRealMembership
{
    public function __construct(private readonly CurrentOffice $currentOffice) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return $next($request);
        }

        $this->currentOffice->resolve($user);

        if ($this->currentOffice->hasRealMembership()) {
            return $next($request);
        }

        // Admin global puro (sem membership real) — leitura ok, escrita/export negada.
        if ($this->currentOffice->isPlatformPrivileged()) {
            return response()->json([
                'message' => 'Operações de escrita/exportação Work exigem membership ativa no escritório.',
                'code' => 'work_real_membership_required',
            ], 403);
        }

        // Sem membership e sem contexto privilegiado: já deveria ter falhado em EnsureOfficeContext.
        return response()->json([
            'message' => 'Membership de escritório necessária para esta operação.',
            'code' => 'work_real_membership_required',
        ], 403);
    }
}
