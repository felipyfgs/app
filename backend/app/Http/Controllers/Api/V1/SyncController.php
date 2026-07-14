<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\SyncCursorStatus;
use App\Http\Controllers\Controller;
use App\Jobs\SyncEstablishmentDistributionJob;
use App\Models\Establishment;
use App\Models\SyncCursor;
use App\Models\SyncRun;
use App\Services\Adn\SyncDispatchService;
use App\Support\CurrentOffice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SyncController extends Controller
{
    public function history(Request $request): JsonResponse
    {
        $query = SyncRun::query()->orderByDesc('id');
        if ($cursor = $request->string('cursor')->toString()) {
            $query->where('id', '<', (int) $cursor);
        }
        $limit = min(max((int) $request->input('limit', 25), 1), 100);
        $items = $query->limit($limit + 1)->get();
        $hasMore = $items->count() > $limit;
        if ($hasMore) {
            $items = $items->take($limit);
        }

        return response()->json([
            'data' => $items->values(),
            'meta' => ['next_cursor' => $hasMore ? (string) $items->last()->id : null],
        ]);
    }

    public function trigger(
        Request $request,
        CurrentOffice $currentOffice,
        SyncDispatchService $dispatcher,
    ): JsonResponse
    {
        $role = $currentOffice->role();
        if ($role === null || ! $role->canTriggerSync()) {
            abort(403);
        }

        $data = $request->validate([
            'establishment_id' => ['required', 'integer'],
        ]);

        $establishment = Establishment::query()->findOrFail($data['establishment_id']);
        $env = (string) config('adn.environment', 'restricted_production');

        $cursor = SyncCursor::query()->firstOrCreate(
            [
                'establishment_id' => $establishment->id,
                'environment' => $env,
            ],
            [
                'office_id' => $establishment->office_id,
                'last_nsu' => 0,
                'status' => SyncCursorStatus::Idle,
                'next_sync_at' => now(),
            ]
        );

        if ($cursor->status === SyncCursorStatus::Blocked) {
            return response()->json(['message' => 'Cursor bloqueado; resolva a falha antes de sincronizar.'], 422);
        }

        $dispatched = $dispatcher->claimAndDispatch(
            $cursor->id,
            'MANUAL',
            auth()->id(),
        );

        if (! $dispatched) {
            return response()->json(['message' => 'Sincronização já enfileirada ou em execução.'], 409);
        }

        return response()->json(['data' => ['sync_cursor_id' => $cursor->id]], 202);
    }
}
