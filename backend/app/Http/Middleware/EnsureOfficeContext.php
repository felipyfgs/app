<?php

namespace App\Http\Middleware;

use App\Support\CurrentOffice;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOfficeContext
{
    public function __construct(private readonly CurrentOffice $currentOffice) {}

    public function handle(Request $request, Closure $next): Response
    {
        $office = $this->currentOffice->resolve($request->user());

        if ($office === null) {
            return response()->json([
                'message' => 'Usuário sem escritório ativo.',
            ], 403);
        }

        // Strip any client-supplied office_id so domain code never trusts it.
        $request->request->remove('office_id');
        $request->query->remove('office_id');

        return $next($request);
    }
}
