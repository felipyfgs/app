<?php

namespace App\Http\Controllers\Api\V1\Fiscal;

use App\Enums\OfficeRole;
use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureOfficeContext;
use App\Models\Client;
use App\Services\Fiscal\SimplesMei\Pgmei\PgmeiCommunicationService;
use App\Services\Fiscal\SimplesMei\Pgmei\PgmeiMonitoringQueryService;
use App\Services\Fiscal\SimplesMei\Pgmei\PgmeiYear;
use App\Support\CurrentOffice;
use App\Support\FeatureFlags;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PgmeiMonitoringController extends Controller
{
    public function __construct(
        private readonly CurrentOffice $currentOffice,
        private readonly PgmeiMonitoringQueryService $queries,
        private readonly PgmeiCommunicationService $communication,
    ) {}

    public function history(Request $request, int $client): JsonResponse
    {
        $this->assertCanRead();
        $office = $this->currentOffice->office();
        $model = $this->findClient($office->id, $client);
        if ($model === null) {
            return response()->json([
                'message' => 'Cliente não encontrado no escritório atual.',
                'code' => 'CLIENT_NOT_FOUND',
            ], 404);
        }

        $year = $request->query('year');
        $yearInt = is_numeric($year) ? (int) $year : null;

        try {
            $data = $this->queries->history($office, $model, $yearInt);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'HISTORY_ERROR'], 422);
        }

        return response()->json(['data' => $data]);
    }

    public function consult(Request $request): JsonResponse
    {
        $this->assertCanWrite();
        $this->assertModuleEnabled();
        if ($rejection = $this->rejectClientOfficeId($request)) {
            return $rejection;
        }

        $data = $request->validate([
            'client_ids' => ['required', 'array', 'min:1', 'max:100'],
            'client_ids.*' => ['integer', 'distinct'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'confirmed' => ['required', 'accepted'],
        ]);

        $office = $this->currentOffice->office();

        try {
            $runs = $this->queries->enqueueManualConsult(
                $office,
                $data['client_ids'],
                (int) $data['year'],
                true,
                $request->user()?->id,
            );
        } catch (HttpException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getStatusCode());
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'data' => $runs,
            'enqueued_count' => count($runs),
            'year' => PgmeiYear::assertValid((int) $data['year']),
        ], 201);
    }

    public function updatePreferences(Request $request, int $client): JsonResponse
    {
        $this->assertCanWrite();
        if ($rejection = $this->rejectClientOfficeId($request)) {
            return $rejection;
        }
        $office = $this->currentOffice->office();
        $role = $this->currentOffice->role();
        if ($role === null) {
            abort(403, 'Perfil não resolvido.');
        }

        $model = $this->findClient($office->id, $client);
        if ($model === null) {
            return response()->json([
                'message' => 'Cliente não encontrado no escritório atual.',
                'code' => 'CLIENT_NOT_FOUND',
            ], 404);
        }

        $data = $request->validate([
            'email_enabled' => ['required', 'boolean'],
            'whatsapp_enabled' => ['required', 'boolean'],
            'automatic_requested' => ['required', 'boolean'],
            'lock_version' => ['required', 'integer', 'min:0'],
        ]);

        $user = $request->user();
        if ($user === null) {
            abort(401);
        }

        try {
            $this->communication->updatePreferences(
                $office,
                $model,
                $user,
                $role,
                $data,
            );
        } catch (ConflictHttpException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => 'OPTIMISTIC_LOCK_CONFLICT',
            ], 409);
        } catch (HttpException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getStatusCode());
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'data' => $this->communication->summary($office, $model),
        ]);
    }

    public function batchPreferences(Request $request): JsonResponse
    {
        $this->assertCanWrite();
        if ($rejection = $this->rejectClientOfficeId($request)) {
            return $rejection;
        }
        $office = $this->currentOffice->office();
        $role = $this->currentOffice->role();
        if ($role === null) {
            abort(403, 'Perfil não resolvido.');
        }

        $data = $request->validate([
            'client_ids' => ['required', 'array', 'min:1', 'max:100'],
            'client_ids.*' => ['integer', 'distinct'],
            'automatic_requested' => ['required', 'boolean'],
        ]);

        $user = $request->user();
        if ($user === null) {
            abort(401);
        }

        try {
            $prefs = $this->communication->batchSetAutomatic(
                $office,
                $user,
                $role,
                $data['client_ids'],
                (bool) $data['automatic_requested'],
            );
        } catch (HttpException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getStatusCode());
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $summaries = $this->communication->summariesForClients(
            $office,
            array_map(static fn ($preference): int => (int) $preference->client_id, $prefs),
        );

        return response()->json([
            'data' => array_values($summaries),
            'updated_count' => count($summaries),
        ]);
    }

    public function preview(int $client): JsonResponse
    {
        $this->assertCanRead();
        $office = $this->currentOffice->office();
        $model = $this->findClient($office->id, $client);
        if ($model === null) {
            return response()->json(['message' => 'Cliente não encontrado.'], 404);
        }

        return response()->json([
            'data' => $this->communication->preview($office, $model),
        ]);
    }

    public function tracking(int $client): JsonResponse
    {
        $this->assertCanRead();
        $office = $this->currentOffice->office();
        $model = $this->findClient($office->id, $client);
        if ($model === null) {
            return response()->json(['message' => 'Cliente não encontrado.'], 404);
        }

        return response()->json([
            'data' => $this->communication->tracking($office, $model),
        ]);
    }

    private function findClient(int $officeId, int $clientId): ?Client
    {
        return Client::query()
            ->withoutGlobalScopes()
            ->where('office_id', $officeId)
            ->whereKey($clientId)
            ->first();
    }

    private function rejectClientOfficeId(Request $request): ?JsonResponse
    {
        if ($request->attributes->get(EnsureOfficeContext::CLIENT_OFFICE_ID_SUPPLIED) !== true) {
            return null;
        }

        return response()->json([
            'message' => 'office_id não é aceito; o escritório é obtido do contexto autenticado.',
            'code' => 'CLIENT_OFFICE_ID_REJECTED',
        ], 422);
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
        if ($role === null) {
            abort(403, 'Perfil não resolvido.');
        }
        if (! in_array($role, [OfficeRole::Admin, OfficeRole::Operator], true)) {
            abort(403, 'Sem permissão de sincronização.');
        }
    }

    private function assertModuleEnabled(): void
    {
        $office = $this->currentOffice->office();
        if (! FeatureFlags::isModuleEnabled('simples_mei', $office->id)
            && ! (bool) config('fiscal_monitoring.enabled', false)) {
            abort(403, 'Módulo simples_mei desabilitado.');
        }
    }
}
