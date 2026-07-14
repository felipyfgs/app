<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\SyncCursorStatus;
use App\Http\Controllers\Controller;
use App\Models\Establishment;
use App\Models\SyncCursor;
use App\Models\SyncRun;
use App\Services\Adn\SyncDispatchService;
use App\Services\Audit\AuditLogger;
use App\Services\Clients\CaptureEligibilityService;
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
        AuditLogger $audit,
        CaptureEligibilityService $eligibility,
    ): JsonResponse {
        $role = $currentOffice->role();
        if ($role === null || ! $role->canTriggerSync()) {
            abort(403);
        }

        $data = $request->validate([
            'establishment_id' => ['required', 'integer'],
        ]);

        $establishment = Establishment::query()->with('client')->findOrFail($data['establishment_id']);
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

        $eval = $eligibility->evaluate($establishment, $cursor);
        if (! $eval['eligible']) {
            $audit->record('sync.trigger', 'FAILED', $cursor, [
                'establishment_id' => $establishment->id,
                'reason' => 'ineligible',
                'reasons_codes' => $eval['reasons_codes'],
            ]);

            return response()->json([
                'message' => $eval['reasons'][0] ?? 'Estabelecimento inelegível para captura.',
                'data' => [
                    'eligible' => false,
                    'reasons' => $eval['reasons'],
                    'reasons_codes' => $eval['reasons_codes'],
                    'last_nsu' => $cursor->last_nsu,
                ],
            ], 422);
        }

        $dispatched = $dispatcher->claimAndDispatch(
            $cursor->id,
            'MANUAL',
            auth()->id(),
        );

        if (! $dispatched) {
            $audit->record('sync.trigger', 'FAILED', $cursor, [
                'establishment_id' => $establishment->id,
                'reason' => 'already_running',
            ]);

            return response()->json(['message' => 'Sincronização já enfileirada ou em execução.'], 409);
        }

        $audit->record('sync.trigger', 'SUCCESS', $cursor, [
            'establishment_id' => $establishment->id,
            'last_nsu' => $cursor->last_nsu,
        ]);

        return response()->json(['data' => ['sync_cursor_id' => $cursor->id]], 202);
    }
}
