<?php

namespace App\Services\Serpro;

use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\Cache;

/**
 * Kill switch global e por solução Integra Contador.
 * Não apaga contratos, tokens cifrados, Termos nem ledger.
 */
final class SerproKillSwitchService
{
    private const GLOBAL_CACHE_KEY = 'serpro.kill_switch.global';

    private const SOLUTION_PREFIX = 'serpro.kill_switch.solution.';

    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    public function isGlobalActive(): bool
    {
        if ((bool) config('serpro.kill_switch', false)) {
            return true;
        }

        return (bool) Cache::get(self::GLOBAL_CACHE_KEY, false);
    }

    public function isSolutionBlocked(string $solutionCode): bool
    {
        if ($this->isGlobalActive()) {
            return true;
        }

        $configMap = config('serpro.solution_kill_switches', []);
        if (is_array($configMap) && ! empty($configMap[$solutionCode])) {
            return true;
        }

        return (bool) Cache::get(self::SOLUTION_PREFIX.strtoupper($solutionCode), false);
    }

    public function activateGlobal(string $reason, ?int $userId = null): void
    {
        Cache::forever(self::GLOBAL_CACHE_KEY, true);
        $this->audit->record('serpro.kill_switch.global_on', 'SUCCESS', null, [
            'reason' => mb_substr($reason, 0, 500),
        ], $userId, null);
    }

    public function deactivateGlobal(string $reason, ?int $userId = null): void
    {
        Cache::forget(self::GLOBAL_CACHE_KEY);
        $this->audit->record('serpro.kill_switch.global_off', 'SUCCESS', null, [
            'reason' => mb_substr($reason, 0, 500),
        ], $userId, null);
    }

    public function activateSolution(string $solutionCode, string $reason, ?int $userId = null): void
    {
        $code = strtoupper($solutionCode);
        Cache::forever(self::SOLUTION_PREFIX.$code, true);
        $this->audit->record('serpro.kill_switch.solution_on', 'SUCCESS', null, [
            'solution' => $code,
            'reason' => mb_substr($reason, 0, 500),
        ], $userId, null);
    }

    public function deactivateSolution(string $solutionCode, string $reason, ?int $userId = null): void
    {
        $code = strtoupper($solutionCode);
        Cache::forget(self::SOLUTION_PREFIX.$code);
        $this->audit->record('serpro.kill_switch.solution_off', 'SUCCESS', null, [
            'solution' => $code,
            'reason' => mb_substr($reason, 0, 500),
        ], $userId, null);
    }

    /**
     * @return array{global: array{active: bool, source: string|null}, solutions: array<string, bool>}
     */
    public function status(): array
    {
        $env = (bool) config('serpro.kill_switch', false);
        $runtime = (bool) Cache::get(self::GLOBAL_CACHE_KEY, false);

        return [
            'global' => [
                'active' => $env || $runtime,
                'source' => $env ? 'config' : ($runtime ? 'runtime' : null),
            ],
            'solutions' => is_array(config('serpro.solution_kill_switches'))
                ? array_map('boolval', config('serpro.solution_kill_switches'))
                : [],
        ];
    }
}
