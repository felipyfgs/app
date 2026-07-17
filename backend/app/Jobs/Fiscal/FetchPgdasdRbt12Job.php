<?php

namespace App\Jobs\Fiscal;

use App\Models\PgdasdRbt12Projection;
use App\Services\Fiscal\SimplesMei\Pgdasd\PgdasdMonitoringQueryService;
use App\Services\Fiscal\SimplesMei\Pgdasd\PgdasdRbt12Service;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/** Dispara uma única consulta CONSEXTRATO16 para uma source_reference_key reservada. */
final class FetchPgdasdRbt12Job implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public readonly int $rbt12ProjectionId) {}

    public function handle(PgdasdRbt12Service $rbt12, PgdasdMonitoringQueryService $queries): void
    {
        $projection = PgdasdRbt12Projection::query()
            ->withoutGlobalScopes()
            ->with(['projection', 'client'])
            ->find($this->rbt12ProjectionId);
        if ($projection === null || ! $rbt12->markAttempted($projection)) {
            return;
        }

        try {
            $queries->enqueueAutomaticRbt12Extract($projection->refresh());
        } catch (\Throwable) {
            $rbt12->markFailed($projection->refresh(), 'EXTRACT_QUERY_ENQUEUE_FAILED');
        }
    }

    public function failed(?Throwable $exception): void
    {
        $projection = PgdasdRbt12Projection::query()
            ->withoutGlobalScopes()
            ->find($this->rbt12ProjectionId);
        if ($projection !== null && $projection->status?->value === 'PENDING') {
            app(PgdasdRbt12Service::class)->markFailed(
                $projection,
                'EXTRACT_JOB_FAILED',
                $projection->source_run_id,
            );
        }
    }
}
