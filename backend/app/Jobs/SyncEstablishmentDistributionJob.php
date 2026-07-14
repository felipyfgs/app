<?php

namespace App\Jobs;

use App\Contracts\AdnContributorClient;
use App\Enums\CredentialStatus;
use App\Enums\SyncCursorStatus;
use App\Exceptions\Adn\AdnException;
use App\Exceptions\Adn\AdnPermanentException;
use App\Exceptions\Adn\DocumentDecodeException;
use App\Models\ClientCredential;
use App\Models\SyncCursor;
use App\Models\SyncRun;
use App\Services\Adn\DistributionPageProcessor;
use App\Services\Certificates\CredentialService;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class SyncEstablishmentDistributionJob implements ShouldQueue
{
    use Queueable;

    public int $timeout;

    public int $tries = 1;

    public function __construct(
        public int $syncCursorId,
        public string $trigger = 'SCHEDULED',
        public ?int $triggeredBy = null,
        public ?string $leaseOwner = null,
    ) {
        $this->timeout = max(60, (int) config('adn.job_timeout_seconds', 900));
    }

    public function handle(
        AdnContributorClient $adn,
        DistributionPageProcessor $processor,
        CredentialService $credentials,
    ): void {
        $cursor = SyncCursor::query()->find($this->syncCursorId);
        if (! $this->hasValidLease($cursor)) {
            return;
        }

        $lock = Cache::lock('adn:est:'.$cursor->establishment_id, $this->lockTtlSeconds());
        if (! $lock->get()) {
            $this->retryClaimedJob();

            return;
        }

        $slotLock = null;
        $run = null;
        $owner = $this->leaseOwner ?? (string) Str::uuid();

        try {
            $slotLock = $this->acquireConcurrentSlot();
            if ($slotLock === null) {
                $this->retryClaimedJob();

                return;
            }

            $cursor = $this->startClaimedCursor($owner);
            if ($cursor === null) {
                return;
            }

            $run = SyncRun::query()->create([
                'office_id' => $cursor->office_id,
                'sync_cursor_id' => $cursor->id,
                'status' => 'RUNNING',
                'trigger' => $this->trigger,
                'triggered_by' => $this->triggeredBy,
                'from_nsu' => $cursor->last_nsu,
                'started_at' => now(),
            ]);

            $credential = ClientCredential::query()
                ->where('client_id', $cursor->establishment->client_id)
                ->where('status', CredentialStatus::Active)
                ->first();

            if ($credential === null || $credential->valid_to->isPast()) {
                $cursor->status = SyncCursorStatus::Blocked;
                $cursor->last_error = 'Credencial A1 ausente ou expirada.';
                $cursor->locked_at = null;
                $cursor->lock_owner = null;
                $cursor->save();
                $run->status = 'FAILED';
                $run->error_message = $cursor->last_error;
                $run->finished_at = now();
                $run->save();

                return;
            }

            $material = $credentials->loadPfxMaterial($credential);
            if ($material === null) {
                throw new \RuntimeException('Material do certificado indisponível.');
            }

            $maxPages = (int) config('adn.max_pages_per_job', 20);
            $pages = 0;
            $docs = 0;
            $hasMore = true;

            while ($pages < $maxPages && $hasMore) {
                $this->throttle();

                $page = $adn->distribution(
                    $material,
                    $cursor->establishment->cnpj,
                    $cursor->last_nsu,
                    true,
                );

                $result = $processor->process($cursor->fresh(), $cursor->establishment, $page);
                $pages++;
                $docs += $result['documents'];
                $hasMore = $page->hasMore;
                $cursor->refresh();
            }

            $cursor->status = SyncCursorStatus::Idle;
            $cursor->next_sync_at = $hasMore ? now()->addSeconds(2) : $this->nextHourlySlot($cursor);
            $cursor->locked_at = null;
            $cursor->lock_owner = null;
            $cursor->attempts = 0;
            $cursor->save();

            $run->status = 'COMPLETED';
            $run->pages_processed = $pages;
            $run->documents_persisted = $docs;
            $run->to_nsu = $cursor->last_nsu;
            $run->finished_at = now();
            $run->save();

            if ($hasMore) {
                app(\App\Services\Adn\SyncDispatchService::class)->claimAndDispatch(
                    $cursor->id,
                    $this->trigger,
                    $this->triggeredBy,
                    now()->addSeconds(2),
                );
            }
        } catch (Throwable $e) {
            $message = $this->safeMessage($e);
            Log::warning('sync.failed', [
                'cursor_id' => $this->syncCursorId,
                'exception_type' => $e::class,
                'http_status' => $e instanceof AdnException ? $e->httpStatus() : null,
                'message' => $message,
            ]);

            $this->recordFailure($owner, $e, $message);

            if ($run !== null) {
                $run->status = 'FAILED';
                $run->error_message = $message;
                $run->finished_at = now();
                $run->save();
            }
        } finally {
            $slotLock?->release();
            $lock->release();
        }
    }

    private function hasValidLease(?SyncCursor $cursor): bool
    {
        if ($cursor === null || $cursor->status === SyncCursorStatus::Blocked) {
            return false;
        }

        if ($this->leaseOwner === null) {
            return ! in_array($cursor->status, [SyncCursorStatus::Running, SyncCursorStatus::Waiting], true)
                && $cursor->lock_owner === null;
        }

        return $cursor->status === SyncCursorStatus::Waiting
            && is_string($cursor->lock_owner)
            && hash_equals($cursor->lock_owner, $this->leaseOwner);
    }

    private function startClaimedCursor(string $owner): ?SyncCursor
    {
        return DB::transaction(function () use ($owner): ?SyncCursor {
            $cursor = SyncCursor::query()->whereKey($this->syncCursorId)->lockForUpdate()->first();

            if (! $this->hasValidLease($cursor)) {
                return null;
            }

            $cursor->status = SyncCursorStatus::Running;
            $cursor->locked_at = now();
            $cursor->lock_owner = $owner;
            $cursor->save();

            return $cursor->load('establishment.client');
        });
    }

    private function retryClaimedJob(): void
    {
        if ($this->leaseOwner === null) {
            return;
        }

        self::dispatch(
            $this->syncCursorId,
            $this->trigger,
            $this->triggeredBy,
            $this->leaseOwner,
        )->delay(now()->addSeconds(5));
    }

    private function recordFailure(string $owner, Throwable $exception, string $message): void
    {
        DB::transaction(function () use ($owner, $exception, $message): void {
            $cursor = SyncCursor::query()->whereKey($this->syncCursorId)->lockForUpdate()->first();

            if ($cursor === null || $cursor->lock_owner !== $owner) {
                return;
            }

            $cursor->attempts++;
            $cursor->last_error = $message;

            if ($cursor->status !== SyncCursorStatus::Blocked) {
                if ($exception instanceof AdnPermanentException) {
                    $cursor->status = SyncCursorStatus::Blocked;
                    $cursor->next_sync_at = null;
                } else {
                    $cursor->status = SyncCursorStatus::Error;
                    $delay = min(3600, (2 ** min($cursor->attempts, 6)) + random_int(0, 30));
                    $cursor->next_sync_at = now()->addSeconds($delay);
                }
            }

            $cursor->locked_at = null;
            $cursor->lock_owner = null;
            $cursor->save();
        });
    }

    private function safeMessage(Throwable $exception): string
    {
        if ($exception instanceof AdnException || $exception instanceof DocumentDecodeException) {
            return $exception->getMessage();
        }

        return 'Falha interna durante a sincronização.';
    }

    private function acquireConcurrentSlot(): ?Lock
    {
        $max = max(1, (int) config('adn.max_concurrent', 4));

        for ($slot = 0; $slot < $max; $slot++) {
            $lock = Cache::lock('adn:slot:'.$slot, $this->lockTtlSeconds());
            if ($lock->get()) {
                return $lock;
            }
        }

        return null;
    }

    private function lockTtlSeconds(): int
    {
        return max(
            $this->timeout + 60,
            (int) config('adn.lock_ttl_seconds', 960),
        );
    }

    private function throttle(): void
    {
        $rps = max(0.1, (float) config('adn.rate_limit_rps', 4));
        $minIntervalMicros = (int) max(1, (int) round(1_000_000 / $rps));

        $mutex = Cache::lock('adn:rate:mutex', 10);
        $mutex->block(10, function () use ($minIntervalMicros): void {
            $now = (int) (microtime(true) * 1_000_000);
            $last = Cache::get('adn:rate:last_at');

            if (is_int($last) && ($now - $last) < $minIntervalMicros) {
                usleep($minIntervalMicros - ($now - $last));
                $now = (int) (microtime(true) * 1_000_000);
            }

            Cache::put('adn:rate:last_at', $now, 10);
        });
    }

    private function nextHourlySlot(SyncCursor $cursor): Carbon
    {
        $preferred = $cursor->id % 60;
        $candidate = now()->addHour()->startOfHour()->addMinutes($preferred);

        if ($candidate->lessThanOrEqualTo(now())) {
            $candidate = $candidate->addHour();
        }

        return $candidate;
    }
}
