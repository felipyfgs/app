<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\CredentialStatus;
use App\Enums\SyncCursorStatus;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientCredential;
use App\Models\Establishment;
use App\Models\Export;
use App\Models\InstanceBackupRun;
use App\Models\NfseNote;
use App\Models\SyncCursor;
use App\Models\SyncRun;
use App\Enums\OutboundRetrievalOrigin;
use App\Enums\SvrsNfceRecoveryStatus;
use App\Models\MaOutboundRetrievalRequest;
use App\Services\Operations\OperationsInboxBuilder;
use App\Services\Outbound\SvrsNfceCircuitBreaker;
use App\Services\Outbound\SvrsNfceConfig;
use App\Services\Outbound\SvrsNfceKillSwitchService;
use App\Support\CurrentOffice;
use Illuminate\Http\JsonResponse;

class OperationsSummaryController extends Controller
{
    public function __invoke(
        CurrentOffice $currentOffice,
        OperationsInboxBuilder $inbox,
        SvrsNfceConfig $svrsConfig,
        SvrsNfceKillSwitchService $svrsKill,
        SvrsNfceCircuitBreaker $svrsBreaker,
    ): JsonResponse {
        $officeId = $currentOffice->id();
        abort_if($officeId === null, 403);

        $counts = $inbox->counts($officeId, $currentOffice->role());
        $backup = InstanceBackupRun::statusSummary();

        $svrsBacklog = MaOutboundRetrievalRequest::query()
            ->where('office_id', $officeId)
            ->where('origin', OutboundRetrievalOrigin::SvrsPortalByKey)
            ->whereIn('recovery_status', [
                SvrsNfceRecoveryStatus::Eligible,
                SvrsNfceRecoveryStatus::Queued,
                SvrsNfceRecoveryStatus::Running,
                SvrsNfceRecoveryStatus::RetryScheduled,
            ])
            ->count();

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
                'inbox_critical' => $counts['inbox_critical'],
                'inbox_high' => $counts['inbox_high'],
                'inbox_total' => $counts['inbox_total'],
                'backup' => $backup,
                'svrs_nfce' => [
                    'retrieval_enabled' => $svrsConfig->retrievalEnabled(),
                    'auto_queue_enabled' => $svrsConfig->autoQueueEnabled(),
                    'kill_switch' => $svrsKill->isActive(),
                    'breaker_global' => $svrsBreaker->globalStatus()['state'],
                    'backlog' => $svrsBacklog,
                ],
                'generated_at' => now()->toIso8601String(),
            ],
        ]);
    }
}
