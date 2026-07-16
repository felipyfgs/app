<?php

namespace App\Services\FiscalMonitoring;

use App\Enums\FiscalRunStatus;
use App\Enums\FiscalSourceProvenance;
use App\Enums\FiscalTrigger;
use App\Enums\FiscalVerificationState;
use App\Enums\SerproCapabilityDriver;
use App\Jobs\Fiscal\ExecuteFiscalMonitoringRunJob;
use App\Models\FiscalMonitoringRun;
use App\Models\FiscalMonitoringSchedule;
use App\Models\OfficeSubscription;
use App\Services\Serpro\CapabilityDriverResolver;
use App\Support\FeatureFlags;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Espalhamento determinístico, fila justa, limites global/tenant.
 * Revalidação completa ocorre no job imediatamente antes da chamada.
 */
final class FiscalMonitoringScheduler
{
    public function isCoreEnabled(): bool
    {
        if ((bool) config('fiscal_monitoring.kill_switch', false)) {
            return false;
        }
        if (FeatureFlags::isKillSwitchActive()) {
            return false;
        }
        if (! FeatureFlags::isGloballyEnabled() && ! (bool) config('fiscal_monitoring.enabled', false)) {
            return false;
        }

        return (bool) config('fiscal_monitoring.scheduler.enabled', false)
            || (bool) config('fiscal_monitoring.enabled', false);
    }

    /**
     * Minuto preferencial determinístico 0–59 (office + client + system + service).
     */
    public function preferredMinute(int $officeId, int $clientId, string $systemCode, string $serviceCode): int
    {
        $spread = max(1, (int) config('fiscal_monitoring.scheduler.spread_minutes', 60));
        $material = "{$officeId}|{$clientId}|{$systemCode}|{$serviceCode}";
        $hash = crc32($material);

        return (int) ($hash % $spread);
    }

    public function firstRunAt(int $preferredMinute, ?CarbonImmutable $from = null): CarbonImmutable
    {
        $from ??= CarbonImmutable::now();
        $candidate = $from->startOfHour()->addMinutes($preferredMinute);
        if ($candidate->lessThanOrEqualTo($from)) {
            $candidate = $candidate->addHour();
        }

        return $candidate;
    }

    public function nextRunAfter(FiscalMonitoringSchedule $schedule, ?CarbonImmutable $from = null): CarbonImmutable
    {
        $from ??= CarbonImmutable::now();
        $interval = max(1, (int) $schedule->interval_minutes);
        $preferred = (int) $schedule->preferred_minute;
        $base = $from->addMinutes($interval);
        $aligned = $base->startOfHour()->addMinutes($preferred % 60);
        if ($aligned->lessThanOrEqualTo($from)) {
            $aligned = $aligned->addHour();
        }

        // Se intervalo > 60, respeita pelo menos o intervalo a partir de now
        if ($aligned->lessThan($from->addMinutes($interval))) {
            $aligned = $from->addMinutes($interval);
        }

        return $aligned;
    }

    /**
     * Dispara agendas devidas com fairness round-robin por office.
     *
     * @return array{dispatched:int,skipped:int,blocked:int}
     */
    public function dispatchDue(?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $dispatched = 0;
        $skipped = 0;
        $blocked = 0;

        if (! $this->isCoreEnabled()) {
            return compact('dispatched', 'skipped', 'blocked');
        }

        $max = max(1, (int) config('fiscal_monitoring.scheduler.max_dispatch_per_tick', 40));
        $minute = (int) $now->format('i');

        // Agrupa por office para fairness: round-robin entre tenants
        $byOffice = FiscalMonitoringSchedule::query()
            ->withoutGlobalScopes()
            ->where('is_enabled', true)
            ->where(function ($q) use ($now): void {
                $q->whereNull('next_run_at')->orWhere('next_run_at', '<=', $now);
            })
            ->orderBy('office_id')
            ->orderBy('id')
            ->get()
            ->groupBy('office_id');

        if ($byOffice->isEmpty()) {
            return compact('dispatched', 'skipped', 'blocked');
        }

        $officeIds = $byOffice->keys()->values()->all();
        $pointers = array_fill_keys($officeIds, 0);
        $exhausted = [];

        while ($dispatched < $max && count($exhausted) < count($officeIds)) {
            foreach ($officeIds as $officeId) {
                if ($dispatched >= $max) {
                    break;
                }
                if (isset($exhausted[$officeId])) {
                    continue;
                }

                /** @var Collection<int, FiscalMonitoringSchedule> $schedules */
                $schedules = $byOffice[$officeId];
                $idx = $pointers[$officeId];
                if ($idx >= $schedules->count()) {
                    $exhausted[$officeId] = true;

                    continue;
                }

                /** @var FiscalMonitoringSchedule $schedule */
                $schedule = $schedules[$idx];
                $pointers[$officeId] = $idx + 1;

                // Espalhamento: no primeiro ciclo (next nulo) só no minuto preferencial
                if ($schedule->next_run_at === null && (int) $schedule->preferred_minute !== $minute) {
                    $skipped++;

                    continue;
                }

                if (! $this->officeAllowsExternal((int) $officeId)) {
                    $blocked++;
                    $schedule->forceFill([
                        'last_skip_reason' => 'SUBSCRIPTION_BLOCKED',
                        'next_run_at' => $this->nextRunAfter($schedule, $now),
                    ])->save();

                    continue;
                }

                $outcome = $this->claimAndEnqueue($schedule, $now);
                if ($outcome === 'dispatched') {
                    $dispatched++;
                } elseif ($outcome === 'blocked') {
                    $blocked++;
                } else {
                    $skipped++;
                }
            }
        }

        return compact('dispatched', 'skipped', 'blocked');
    }

    public function officeAllowsExternal(int $officeId): bool
    {
        $sub = OfficeSubscription::query()->where('office_id', $officeId)->first();
        if ($sub === null) {
            return false;
        }

        return $sub->status->allowsExternalCalls();
    }

    /**
     * @return 'dispatched'|'skipped'|'blocked'
     */
    public function claimAndEnqueue(FiscalMonitoringSchedule $schedule, CarbonImmutable $now): string
    {
        $run = null;

        $created = DB::transaction(function () use ($schedule, $now, &$run) {
            $locked = FiscalMonitoringSchedule::query()
                ->withoutGlobalScopes()
                ->whereKey($schedule->id)
                ->lockForUpdate()
                ->first();

            if ($locked === null || ! $locked->is_enabled) {
                return 'skipped';
            }

            // Scheduler NUNCA cria intenção mutante (fail-closed).
            if ($this->looksMutating($locked)) {
                $locked->forceFill([
                    'next_run_at' => $this->nextRunAfter($locked, $now),
                    'last_skip_reason' => 'MUTATING_NOT_SCHEDULED',
                ])->save();

                return 'blocked';
            }

            $sitfisDriver = null;
            if (strtoupper((string) $locked->service_code) === 'SITFIS') {
                $sitfisDriver = app(CapabilityDriverResolver::class)->forCapability('sitfis');
                if ($sitfisDriver === SerproCapabilityDriver::Disabled) {
                    return 'blocked';
                }
            }
            if ($locked->next_run_at !== null && $locked->next_run_at->greaterThan($now)) {
                return 'skipped';
            }

            $slot = FiscalIdempotency::scheduledSlot($now);
            $key = FiscalIdempotency::runKey(
                (int) $locked->office_id,
                (int) $locked->client_id,
                $locked->system_code,
                $locked->service_code,
                $locked->operation_code,
                null,
                FiscalTrigger::Scheduled,
                $slot,
            );

            $existing = FiscalMonitoringRun::query()
                ->withoutGlobalScopes()
                ->where('office_id', $locked->office_id)
                ->where('idempotency_key', $key)
                ->first();

            if ($existing !== null) {
                $locked->forceFill([
                    'next_run_at' => $this->nextRunAfter($locked, $now),
                    'last_skip_reason' => 'IDEMPOTENT_REPLAY',
                ])->save();

                return 'skipped';
            }

            $run = FiscalMonitoringRun::query()->create([
                'office_id' => $locked->office_id,
                'client_id' => $locked->client_id,
                'fiscal_category_id' => $locked->fiscal_category_id,
                'schedule_id' => $locked->id,
                'system_code' => $locked->system_code,
                'service_code' => $locked->service_code,
                'operation_code' => $locked->operation_code,
                'operation_key' => $sitfisDriver !== null ? 'sitfis.emitir_relatorio' : null,
                'source_provenance' => match ($sitfisDriver) {
                    SerproCapabilityDriver::Simulated => FiscalSourceProvenance::Simulated,
                    SerproCapabilityDriver::Real => FiscalSourceProvenance::SerproReal,
                    default => null,
                },
                'verification_state' => $sitfisDriver !== null
                    ? FiscalVerificationState::Unverified
                    : null,
                'trigger' => FiscalTrigger::Scheduled,
                'idempotency_key' => $key,
                'status' => FiscalRunStatus::Queued,
                'situation' => 'UNKNOWN',
                'coverage' => 'UNKNOWN',
                'mutability' => 'READ_ONLY',
                'correlation_id' => bin2hex(random_bytes(8)),
            ]);

            $locked->forceFill([
                'last_run_at' => $now,
                'next_run_at' => $this->nextRunAfter($locked, $now),
                'last_skip_reason' => null,
            ])->save();

            return 'dispatched';
        });

        if ($created === 'dispatched' && $run !== null) {
            ExecuteFiscalMonitoringRunJob::dispatch($run->id)
                ->onQueue((string) config('fiscal_monitoring.job.queue', 'default'));
        }

        return $created;
    }

    /**
     * Heurística fail-closed: códigos de operação tipicamente mutantes não entram no scheduler.
     */
    private function looksMutating(FiscalMonitoringSchedule $schedule): bool
    {
        $meta = is_array($schedule->metadata) ? $schedule->metadata : [];
        $flag = strtoupper((string) ($meta['mutability'] ?? $meta['is_mutating'] ?? ''));
        if (in_array($flag, ['MUTATING', 'WRITE', 'MUTATION', '1', 'TRUE'], true)) {
            return true;
        }

        $op = strtoupper((string) $schedule->operation_code);
        $mutatingOps = [
            'TRANSMITIR', 'TRANSMITIR_DECLARACAO', 'TRANSDECLARACAO',
            'EMITIR_GUIA', 'GERARGUIA', 'GERARDAS', 'GERAR_DAS',
            'ENCERRAR', 'ENCAPURACAO', 'ADERIR', 'EFETUAROPCAOREGIME',
            'ATUBENEFICIO', 'SOLICRENUNCIA',
        ];

        return in_array($op, $mutatingOps, true);
    }

    public function globalSlotAvailable(): bool
    {
        $limit = max(1, (int) config('fiscal_monitoring.scheduler.global_concurrent_limit', 8));
        $key = FiscalIdempotency::cacheKey(0, 'global-slots');
        $current = (int) Cache::get($key, 0);

        return $current < $limit;
    }

    public function tenantSlotAvailable(int $officeId): bool
    {
        $limit = max(1, (int) config('fiscal_monitoring.scheduler.tenant_concurrent_limit', 2));
        $key = FiscalIdempotency::cacheKey($officeId, 'tenant-slots');
        $current = (int) Cache::get($key, 0);

        return $current < $limit;
    }

    /**
     * Reserva slots global+tenant sob lock (claim atômico — evita TOCTOU entre workers).
     *
     * @return array{global:string,tenant:string}|null
     */
    public function acquireSlots(int $officeId, int $ttlSeconds): ?array
    {
        $lock = Cache::lock('fiscal-monitoring:slot-claim', 10);

        try {
            return $lock->block(5, function () use ($officeId, $ttlSeconds) {
                $globalLimit = max(1, (int) config('fiscal_monitoring.scheduler.global_concurrent_limit', 8));
                $tenantLimit = max(1, (int) config('fiscal_monitoring.scheduler.tenant_concurrent_limit', 2));
                $globalKey = FiscalIdempotency::cacheKey(0, 'global-slots');
                $tenantKey = FiscalIdempotency::cacheKey($officeId, 'tenant-slots');

                $globalCurrent = (int) Cache::get($globalKey, 0);
                $tenantCurrent = (int) Cache::get($tenantKey, 0);

                if ($globalCurrent >= $globalLimit || $tenantCurrent >= $tenantLimit) {
                    return null;
                }

                // Put com valor computado sob o mesmo lock (sem increment+put stale).
                Cache::put($globalKey, $globalCurrent + 1, $ttlSeconds);
                Cache::put($tenantKey, $tenantCurrent + 1, $ttlSeconds);

                return ['global' => $globalKey, 'tenant' => $tenantKey];
            });
        } catch (LockTimeoutException) {
            return null;
        }
    }

    public function releaseSlots(?array $keys): void
    {
        if ($keys === null) {
            return;
        }
        foreach (['global', 'tenant'] as $k) {
            if (! isset($keys[$k])) {
                continue;
            }
            $key = $keys[$k];
            $val = (int) Cache::get($key, 0);
            if ($val <= 1) {
                Cache::forget($key);
            } else {
                Cache::decrement($key);
            }
        }
    }
}
