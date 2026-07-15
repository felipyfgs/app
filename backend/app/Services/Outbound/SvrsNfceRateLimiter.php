<?php

namespace App\Services\Outbound;

use Illuminate\Support\Facades\Cache;

/**
 * Semáforo global (1 em voo), intervalo global 5s e por raiz 30s.
 * Acquire atômico via Cache::lock (fail-closed se o lock não for obtido).
 */
final class SvrsNfceRateLimiter
{
    private const INFLIGHT_KEY = 'sefaz.svrs_nfce_xml.inflight';
    private const GLOBAL_LAST_KEY = 'sefaz.svrs_nfce_xml.last_global_at';
    private const ROOT_LAST_PREFIX = 'sefaz.svrs_nfce_xml.last_root.';
    private const MUTEX_KEY = 'sefaz.svrs_nfce_xml.rate_mutex';

    public function __construct(
        private readonly SvrsNfceConfig $config,
    ) {}

    /**
     * @return array{allowed: bool, retry_after_seconds: int, reason: ?string}
     */
    public function acquire(int $clientId): array
    {
        $lock = Cache::lock(self::MUTEX_KEY, 5);
        if (! $lock->get()) {
            return ['allowed' => false, 'retry_after_seconds' => 2, 'reason' => 'mutex'];
        }

        try {
            $now = microtime(true);

            $inflight = (int) Cache::get(self::INFLIGHT_KEY, 0);
            if ($inflight >= $this->config->maxInflightGlobal()) {
                return ['allowed' => false, 'retry_after_seconds' => 5, 'reason' => 'inflight'];
            }

            $lastGlobal = (float) Cache::get(self::GLOBAL_LAST_KEY, 0);
            $globalWait = $this->config->minIntervalGlobalSeconds() - ($now - $lastGlobal);
            if ($globalWait > 0) {
                return ['allowed' => false, 'retry_after_seconds' => (int) ceil($globalWait), 'reason' => 'global_interval'];
            }

            $rootKey = self::ROOT_LAST_PREFIX.$clientId;
            $lastRoot = (float) Cache::get($rootKey, 0);
            $rootWait = $this->config->minIntervalRootSeconds() - ($now - $lastRoot);
            if ($rootWait > 0) {
                return ['allowed' => false, 'retry_after_seconds' => (int) ceil($rootWait), 'reason' => 'root_interval'];
            }

            Cache::put(self::INFLIGHT_KEY, $inflight + 1, 120);
            Cache::put(self::GLOBAL_LAST_KEY, $now, 3600);
            Cache::put($rootKey, $now, 3600);

            return ['allowed' => true, 'retry_after_seconds' => 0, 'reason' => null];
        } finally {
            $lock->release();
        }
    }

    public function release(): void
    {
        $lock = Cache::lock(self::MUTEX_KEY, 5);
        if (! $lock->get()) {
            // Best-effort decrement sem lock
            $inflight = (int) Cache::get(self::INFLIGHT_KEY, 0);
            Cache::put(self::INFLIGHT_KEY, max(0, $inflight - 1), 120);

            return;
        }

        try {
            $inflight = (int) Cache::get(self::INFLIGHT_KEY, 0);
            Cache::put(self::INFLIGHT_KEY, max(0, $inflight - 1), 120);
        } finally {
            $lock->release();
        }
    }

    /** @internal testes */
    public function reset(): void
    {
        Cache::forget(self::INFLIGHT_KEY);
        Cache::forget(self::GLOBAL_LAST_KEY);
        Cache::forget(self::MUTEX_KEY);
    }
}
