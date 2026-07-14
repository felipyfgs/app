<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\CredentialStatus;
use App\Enums\SyncCursorStatus;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientCredential;
use App\Models\Establishment;
use App\Models\Export;
use App\Models\NfseNote;
use App\Models\SyncCursor;
use App\Models\SyncRun;
use Illuminate\Http\JsonResponse;

class OperationsSummaryController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'data' => [
                'clients' => Client::query()->count(),
                'establishments' => Establishment::query()->count(),
                'notes' => NfseNote::query()->count(),
                'exports_ready' => Export::query()->where('status', 'READY')->count(),
                'exports_pending' => Export::query()->whereIn('status', ['PENDING', 'PROCESSING'])->count(),
                'sync_due' => SyncCursor::query()
                    ->whereNotIn('status', [SyncCursorStatus::Blocked, SyncCursorStatus::Running])
                    ->where('next_sync_at', '<=', now())
                    ->count(),
                'sync_blocked' => SyncCursor::query()->where('status', SyncCursorStatus::Blocked)->count(),
                'sync_failures_24h' => SyncRun::query()
                    ->where('status', 'FAILED')
                    ->where('created_at', '>=', now()->subDay())
                    ->count(),
                'credentials_expiring_30d' => ClientCredential::query()
                    ->where('status', CredentialStatus::Active)
                    ->where('valid_to', '<=', now()->addDays(30))
                    ->count(),
                'generated_at' => now()->toIso8601String(),
            ],
        ]);
    }
}
