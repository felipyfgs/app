<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\CurrentOffice;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOfficeContext
{
    public const CLIENT_OFFICE_ID_SUPPLIED = 'client_office_id_supplied';

    public function __construct(private readonly CurrentOffice $currentOffice) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $office = $this->currentOffice->resolve($user);

        if ($office === null) {
            // PLATFORM_ADMIN sem Office válido: 409 estável para o cliente corrigir o padrão.
            if ($user instanceof User && $user->isPlatformAdmin()) {
                return response()->json([
                    'message' => 'Selecione um escritório ativo para continuar.',
                    'code' => CurrentOffice::CONTEXT_STATUS_REQUIRED,
                ], 409);
            }

            return response()->json([
                'message' => 'Usuário sem escritório ativo.',
                'code' => 'office_membership_required',
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
        $supplied = $request->request->has('office_id')
            || $request->query->has('office_id')
            || ($request->isJson() && $request->json() !== null && $request->json()->has('office_id'));
        if ($supplied) {
            // Endpoints novos que exigem rejeição explícita podem consultar o marker
            // sem jamais ler/confiar no valor fornecido pelo cliente.
            $request->attributes->set(self::CLIENT_OFFICE_ID_SUPPLIED, true);
        }

        $request->request->remove('office_id');
        $request->query->remove('office_id');
        if ($request->isJson() && $request->json() !== null) {
            $request->json()->remove('office_id');
        }
    }
}
