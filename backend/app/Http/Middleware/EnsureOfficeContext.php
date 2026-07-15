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

        // Strip any client-supplied office_id so domain code never trusts it
        // (inclui body JSON: getInputSource() usa json() para application/json).
        // Troca de tenant usa endpoint dedicado fora deste middleware.
        $this->stripClientOfficeId($request);

        return $next($request);
    }

    private function stripClientOfficeId(Request $request): void
    {
        $request->request->remove('office_id');
        $request->query->remove('office_id');
        if ($request->isJson() && $request->json() !== null) {
            $request->json()->remove('office_id');
        }
    }
}
