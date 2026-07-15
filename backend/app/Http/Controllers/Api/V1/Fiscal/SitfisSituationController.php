<?php

namespace App\Http\Controllers\Api\V1\Fiscal;

use App\Enums\OfficeRole;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Services\Integra\Sitfis\SitfisSnapshotService;
use App\Support\CurrentOffice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Situação Fiscal (SITFIS): leitura com idade do snapshot; refresh respeita TTL.
 */
class SitfisSituationController extends Controller
{
    public function __construct(
        private readonly CurrentOffice $currentOffice,
        private readonly SitfisSnapshotService $sitfis,
    ) {}

    /**
     * GET — devolve snapshot existente + idade. Nunca dispara nova chamada só por abrir a tela.
     */
    public function show(Request $request): JsonResponse
    {
        $this->assertCanRead();
        $office = $this->currentOffice->office();

        $data = $request->validate([
            'client_id' => ['required', 'integer'],
        ]);

        $client = $this->findClient($office->id, (int) $data['client_id']);
        if ($client === null) {
            return response()->json(['message' => 'Cliente não encontrado.'], 404);
        }

        $view = $this->sitfis->current($office, $client);

        return response()->json([
            'data' => $this->sitfis->publicView($view),
        ]);
    }

    /**
     * POST — solicita nova emissão só se TTL expirado, ausente ou force=true.
     */
    public function refresh(Request $request): JsonResponse
    {
        $this->assertCanWrite();
        $office = $this->currentOffice->office();

        $data = $request->validate([
            'client_id' => ['required', 'integer'],
            'force' => ['sometimes', 'boolean'],
        ]);

        $client = $this->findClient($office->id, (int) $data['client_id']);
        if ($client === null) {
            return response()->json(['message' => 'Cliente não encontrado.'], 404);
        }

        try {
            $result = $this->sitfis->refresh(
                office: $office,
                client: $client,
                force: (bool) ($data['force'] ?? false),
                actorId: $request->user()?->id,
                dispatch: true,
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $status = $result['enqueued'] ? 202 : 200;

        return response()->json([
            'data' => [
                'enqueued' => $result['enqueued'],
                'reused_snapshot' => $result['reused_snapshot'],
                'reason' => $result['reason'],
                'run' => $result['run']?->toPublicArray(),
                'situation' => $result['view'],
            ],
        ], $status);
    }

    private function findClient(int $officeId, int $clientId): ?Client
    {
        return Client::query()
            ->withoutGlobalScopes()
            ->where('office_id', $officeId)
            ->whereKey($clientId)
            ->first();
    }

    private function assertCanRead(): void
    {
        if ($this->currentOffice->role() === null) {
            abort(403, 'Perfil não resolvido.');
        }
    }

    private function assertCanWrite(): void
    {
        $role = $this->currentOffice->role();
        if ($role === null || ! in_array($role, [OfficeRole::Admin, OfficeRole::Operator], true)) {
            abort(403, 'Ação não autorizada para o perfil atual.');
        }
    }
}
