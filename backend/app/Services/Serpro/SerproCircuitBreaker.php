<?php

namespace App\Services\Serpro;

use App\Services\Audit\AuditLogger;
use App\Services\Operations\OperationsMetrics;
use Illuminate\Support\Facades\Cache;

/**
 * Circuit breaker global e por solução.
 */
final class SerproCircuitBreaker
{
    private const GLOBAL_KEY = 'serpro.breaker.global';

    private const SOLUTION_PREFIX = 'serpro.breaker.solution.';

    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    public function isCallAllowed(?string $solutionCode = null): bool
    {
        if (! $this->scopeAllows(self::GLOBAL_KEY)) {
            return false;
        }

        if ($solutionCode !== null && ! $this->scopeAllows(self::SOLUTION_PREFIX.strtoupper($solutionCode))) {
            return false;
        }

        return true;
    }

    public function recordSuccess(?string $solutionCode = null): void
    {
        $this->close(self::GLOBAL_KEY);
        if ($solutionCode !== null) {
            $this->close(self::SOLUTION_PREFIX.strtoupper($solutionCode));
        }
    }

    public function recordFailure(?string $solutionCode = null, ?string $reason = null): void
    {
        $this->incrementTowardTrip(self::GLOBAL_KEY, $reason);
        if ($solutionCode !== null) {
            $this->incrementTowardTrip(self::SOLUTION_PREFIX.strtoupper($solutionCode), $reason);
        }
    }

    public function trip(string $scope, string $reason, ?int $userId = null): void
    {
        $openSeconds = (int) config('serpro.circuit_breaker.open_seconds', 120);
        $payload = [
            'state' => 'open',
            'open_until' => now()->addSeconds($openSeconds)->getTimestamp(),
            'failures' => (int) config('serpro.circuit_breaker.failure_threshold', 5),
            'reason' => mb_substr($reason, 0, 200),
        ];
        Cache::put($scope, $payload, $openSeconds + 60);

        $this->audit->record('serpro.breaker.trip', 'SUCCESS', null, [
            'scope' => $scope,
            'reason' => mb_substr($reason, 0, 200),
            'breaker_state' => 'open',
        ], $userId, null);

        try {
            app(OperationsMetrics::class)->increment(
                'serpro.breaker.trip',
                1,
                ['breaker_state' => 'open', 'channel' => 'serpro'],
            );
        } catch (\Throwable) {
            // métricas não derrubam breaker
        }
    }

    public function resetGlobal(string $reason, ?int $userId = null): void
    {
        $this->close(self::GLOBAL_KEY);
        $this->audit->record('serpro.breaker.global_reset', 'SUCCESS', null, [
            'reason' => mb_substr($reason, 0, 200),
        ], $userId, null);
    }

    /**
     * @return array{state: string, open_until: ?int, failures: int}
     */
    public function globalStatus(): array
    {
        return $this->status(self::GLOBAL_KEY);
    }

    /**
     * @return array{state: string, open_until: ?int, failures: int}
     */
    public function solutionStatus(string $solutionCode): array
    {
        return $this->status(self::SOLUTION_PREFIX.strtoupper($solutionCode));
    }

    /**
     * @return array{state: string, open_until: ?int, failures: int}
     */
    private function status(string $key): array
    {
        /** @var array{state?: string, open_until?: int, failures?: int}|null $data */
        $data = Cache::get($key);
        if (! is_array($data)) {
            return ['state' => 'closed', 'open_until' => null, 'failures' => 0];
        }

        $openUntil = isset($data['open_until']) ? (int) $data['open_until'] : null;
        $state = (string) ($data['state'] ?? 'closed');

        if ($state === 'open' && $openUntil !== null && $openUntil <= time()) {
            return ['state' => 'half_open', 'open_until' => $openUntil, 'failures' => (int) ($data['failures'] ?? 0)];
        }

        return [
            'state' => $state,
            'open_until' => $openUntil,
            'failures' => (int) ($data['failures'] ?? 0),
        ];
    }

    private function scopeAllows(string $key): bool
    {
        $status = $this->status($key);

        return match ($status['state']) {
            'open' => false,
            'half_open', 'closed' => true,
            default => true,
        };
    }

    private function close(string $key): void
    {
        Cache::forget($key);
    }

    private function incrementTowardTrip(string $key, ?string $reason): void
    {
        $threshold = (int) config('serpro.circuit_breaker.failure_threshold', 5);
        /** @var array{state?: string, open_until?: int, failures?: int}|null $data */
        $data = Cache::get($key);
        $failures = is_array($data) ? ((int) ($data['failures'] ?? 0)) + 1 : 1;

        if ($failures >= $threshold) {
            $this->trip($key, $reason ?? 'threshold', null);

            return;
        }

        Cache::put($key, [
            'state' => 'closed',
            'open_until' => null,
            'failures' => $failures,
        ], 600);
    }
}
