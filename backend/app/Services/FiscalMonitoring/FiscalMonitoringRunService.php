<?php

namespace App\Services\FiscalMonitoring;

use App\DTO\Fiscal\FiscalAdapterRequest;
use App\DTO\Fiscal\FiscalAdapterResult;
use App\DTO\Fiscal\FiscalPersistPayload;
use App\Enums\FiscalCoverage;
use App\Enums\FiscalMutability;
use App\Enums\FiscalRunResult;
use App\Enums\FiscalRunStatus;
use App\Enums\FiscalSituation;
use App\Enums\FiscalTrigger;
use App\Jobs\Fiscal\ExecuteFiscalMonitoringRunJob;
use App\Models\Client;
use App\Models\FiscalCompetence;
use App\Models\FiscalMonitoringRun;
use App\Models\FiscalMonitoringSchedule;
use App\Models\Office;
use App\Services\Audit\AuditLogger;
use App\Services\Operations\OperationsMetrics;
use App\Services\Operations\StructuredLogger;
use App\Services\Platform\OfficeSubscriptionGate;
use App\Support\FeatureFlags;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Ciclo de vida de execuções: criação idempotente, revalidação, orquestração e requeue.
 */
final class FiscalMonitoringRunService
{
    public function __construct(
        private readonly FiscalAdapterRegistry $registry,
        private readonly FiscalSnapshotPersistence $persistence,
        private readonly FiscalMonitoringScheduler $scheduler,
        private readonly OfficeSubscriptionGate $subscriptionGate,
        private readonly AuditLogger $audit,
        private readonly StructuredLogger $structuredLog,
        private readonly OperationsMetrics $metrics,
    ) {}

    /**
     * @return LengthAwarePaginator<int, FiscalMonitoringRun>
     */
    public function paginate(Office $office, int $perPage = 50, ?int $clientId = null, ?string $status = null): LengthAwarePaginator
    {
        $q = FiscalMonitoringRun::query()
            ->withoutGlobalScopes()
            ->where('office_id', $office->id)
            ->orderByDesc('id');

        if ($clientId !== null) {
            $q->where('client_id', $clientId);
        }
        if ($status !== null) {
            $q->where('status', $status);
        }

        return $q->paginate($perPage);
    }

    public function findForOffice(Office $office, int $runId): ?FiscalMonitoringRun
    {
        return FiscalMonitoringRun::query()
            ->withoutGlobalScopes()
            ->where('office_id', $office->id)
            ->whereKey($runId)
            ->first();
    }

    /**
     * Cria (ou reutiliza) execução manual idempotente.
     */
    public function enqueueManual(
        Office $office,
        Client $client,
        string $systemCode,
        string $serviceCode,
        string $operationCode = 'MONITOR',
        ?FiscalCompetence $competence = null,
        ?int $actorId = null,
        ?string $correlationId = null,
        bool $dispatch = true,
    ): FiscalMonitoringRun {
        if ((int) $client->office_id !== (int) $office->id) {
            throw new RuntimeException('Cliente não pertence ao escritório ativo.');
        }

        $correlationId ??= (string) Str::uuid();
        $slot = FiscalIdempotency::manualSlot($correlationId);
        $key = FiscalIdempotency::runKey(
            (int) $office->id,
            (int) $client->id,
            $systemCode,
            $serviceCode,
            $operationCode,
            $competence?->period_key,
            FiscalTrigger::Manual,
            $slot,
        );

        $run = FiscalMonitoringRun::query()
            ->withoutGlobalScopes()
            ->where('office_id', $office->id)
            ->where('idempotency_key', $key)
            ->first();

        if ($run !== null) {
            return $run;
        }

        $run = FiscalMonitoringRun::query()->create([
            'office_id' => $office->id,
            'client_id' => $client->id,
            'competence_id' => $competence?->id,
            'system_code' => strtoupper($systemCode),
            'service_code' => strtoupper($serviceCode),
            'operation_code' => strtoupper($operationCode),
            'trigger' => FiscalTrigger::Manual,
            'idempotency_key' => $key,
            'status' => FiscalRunStatus::Queued,
            'situation' => FiscalSituation::Unknown,
            'coverage' => 'UNKNOWN',
            'mutability' => FiscalMutability::ReadOnly,
            'correlation_id' => $correlationId,
            'triggered_by' => $actorId,
        ]);

        if ($dispatch) {
            ExecuteFiscalMonitoringRunJob::dispatch($run->id)
                ->onQueue((string) config('fiscal_monitoring.job.queue', 'default'));
        }

        return $run;
    }

    /**
     * Executa a run: revalida elegibilidade, chama adapter, persiste atomicamente.
     */
    public function execute(int $runId): FiscalMonitoringRun
    {
        $run = FiscalMonitoringRun::query()->withoutGlobalScopes()->find($runId);
        if ($run === null) {
            throw new RuntimeException("Run fiscal #{$runId} não encontrada.");
        }

        // REQUEUED é terminal para este id (progresso salvo + continuação enfileirada).
        if ($run->status->isTerminal()) {
            return $run;
        }

        $lock = Cache::lock(
            FiscalIdempotency::runLockKey((int) $run->office_id, $run->idempotency_key),
            (int) config('fiscal_monitoring.job.lock_ttl_seconds', 360),
        );

        if (! $lock->get()) {
            return $run;
        }

        $slots = null;

        try {
            $run = FiscalMonitoringRun::query()->withoutGlobalScopes()->findOrFail($runId);
            $office = Office::query()->find($run->office_id);
            $client = Client::query()->withoutGlobalScopes()
                ->where('office_id', $run->office_id)
                ->whereKey($run->client_id)
                ->first();

            if ($office === null || $client === null) {
                return $this->markBlocked($run, 'OFFICE_OR_CLIENT_MISSING', 'Escritório ou cliente ausente.');
            }

            // Revalidação imediata pré-chamada
            $block = $this->preflightBlockReason($office, $client, $run);
            if ($block !== null) {
                return $this->markBlocked($run, $block['code'], $block['message']);
            }

            $slots = $this->scheduler->acquireSlots((int) $office->id, (int) config('fiscal_monitoring.job.lock_ttl_seconds', 360));
            if ($slots === null) {
                // Sem slot: requeue leve sem consumir adapter
                $run->forceFill([
                    'status' => FiscalRunStatus::Queued,
                    'skip_reason' => 'RATE_LIMITED',
                ])->save();
                ExecuteFiscalMonitoringRunJob::dispatch($run->id)
                    ->delay(now()->addSeconds(15 + random_int(0, 15)))
                    ->onQueue((string) config('fiscal_monitoring.job.queue', 'default'));

                return $run->fresh();
            }

            $owner = (string) Str::uuid();
            $run->forceFill([
                'status' => FiscalRunStatus::Running,
                'started_at' => $run->started_at ?? CarbonImmutable::now(),
                'lease_owner' => $owner,
                'locked_at' => CarbonImmutable::now(),
                'situation' => FiscalSituation::Processing,
            ])->save();

            $request = new FiscalAdapterRequest(
                office: $office,
                client: $client,
                run: $run,
                systemCode: $run->system_code,
                serviceCode: $run->service_code,
                operationCode: $run->operation_code,
                trigger: $run->trigger,
                competence: $run->competence_id
                    ? FiscalCompetence::query()->withoutGlobalScopes()->find($run->competence_id)
                    : null,
                progressCursor: $run->progress_cursor,
                progress: $run->progress ?? [],
            );

            $adapter = $this->registry->resolve($request);

            // Mutações desabilitadas por padrão
            if ($adapter->mutability()->isMutating() && ! (bool) config('fiscal_monitoring.mutating_enabled', false)) {
                $result = FiscalAdapterResult::blocked(
                    'Operações mutantes desabilitadas no núcleo fiscal.',
                    'MUTATING_DISABLED',
                );
            } else {
                $module = $adapter->moduleKey();
                if ($module !== null && ! FeatureFlags::isModuleEnabled($module, $office->id)
                    && ! (bool) config('fiscal_monitoring.enabled', false)) {
                    $result = FiscalAdapterResult::blocked(
                        "Módulo {$module} desabilitado.",
                        'FEATURE_DISABLED',
                    );
                } else {
                    $result = $adapter->execute($request);
                }
            }

            $persist = FiscalPersistPayload::fromAdapterResult($run, $result, $adapter::class);
            $out = $this->persistence->persist($persist);
            $fresh = $out['run'];

            $latencyMs = null;
            if ($fresh->started_at !== null) {
                $latencyMs = (int) max(
                    0,
                    (CarbonImmutable::now()->getTimestampMs() - $fresh->started_at->getTimestampMs()),
                );
            }

            // Auditoria de consulta (sem payload fiscal / CNPJ / XML).
            $this->audit->record(
                action: 'fiscal.consulta.execute',
                result: $fresh->result?->value ?? $fresh->status->value,
                subject: $fresh,
                context: [
                    'system_code' => $fresh->system_code,
                    'service_code' => $fresh->service_code,
                    'operation_code' => $fresh->operation_code,
                    'status' => $fresh->status->value,
                    'result' => $fresh->result?->value,
                    'situation' => $fresh->situation?->value,
                    'coverage' => $fresh->coverage?->value,
                    'client_id' => $fresh->client_id,
                    'correlation_id' => $fresh->correlation_id,
                    'error_code' => $fresh->error_code,
                    'skip_reason' => $fresh->skip_reason,
                    'latency_ms' => $latencyMs,
                ],
                officeId: (int) $fresh->office_id,
            );

            $this->structuredLog->externalCall(
                channel: 'fiscal_monitoring',
                result: $fresh->result?->value ?? $fresh->status->value,
                latencyMs: $latencyMs,
                httpStatus: null,
                context: [
                    'service_code' => $fresh->service_code,
                    'operation_code' => $fresh->operation_code,
                    'module' => $adapter->moduleKey(),
                    'status' => $fresh->status->value,
                ],
                officeId: (int) $fresh->office_id,
            );

            $this->metrics->increment('fiscal.consulta.result', 1, [
                'result' => $fresh->result?->value ?? 'UNKNOWN',
                'service_code' => $fresh->service_code,
                'status' => $fresh->status->value,
            ]);

            if ($fresh->status === FiscalRunStatus::Requeued) {
                $delay = $result->requeueAfterSeconds;
                if ($delay === null || $delay < 0) {
                    $delay = (int) ($fresh->progress['requeue_after_seconds'] ?? 0);
                }
                $this->enqueueContinuation($fresh, max(0, (int) $delay));
            }

            if ($fresh->schedule_id && $fresh->result === FiscalRunResult::Success) {
                FiscalMonitoringSchedule::query()
                    ->withoutGlobalScopes()
                    ->whereKey($fresh->schedule_id)
                    ->update([
                        'last_success_at' => CarbonImmutable::now(),
                        'last_result' => FiscalRunResult::Success->value,
                    ]);
            }

            return $fresh;
        } finally {
            $this->scheduler->releaseSlots($slots);
            $lock->release();
        }
    }

    public function enqueueContinuation(FiscalMonitoringRun $parent, int $delaySeconds = 0): FiscalMonitoringRun
    {
        $attempt = $parent->attempt + 1;
        $slot = FiscalIdempotency::continuationSlot((int) $parent->id, $attempt);
        $key = FiscalIdempotency::runKey(
            (int) $parent->office_id,
            (int) $parent->client_id,
            $parent->system_code,
            $parent->service_code,
            $parent->operation_code,
            $parent->competence?->period_key,
            FiscalTrigger::Continuation,
            $slot,
        );

        $existing = FiscalMonitoringRun::query()
            ->withoutGlobalScopes()
            ->where('office_id', $parent->office_id)
            ->where('idempotency_key', $key)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $child = FiscalMonitoringRun::query()->create([
            'office_id' => $parent->office_id,
            'client_id' => $parent->client_id,
            'fiscal_category_id' => $parent->fiscal_category_id,
            'competence_id' => $parent->competence_id,
            'schedule_id' => $parent->schedule_id,
            'last_update_event_id' => $parent->last_update_event_id,
            'system_code' => $parent->system_code,
            'service_code' => $parent->service_code,
            'operation_code' => $parent->operation_code,
            'operation_key' => $parent->operation_key,
            'source_provenance' => $parent->source_provenance,
            'verification_state' => $parent->verification_state,
            'trigger' => FiscalTrigger::Continuation,
            'idempotency_key' => $key,
            'status' => FiscalRunStatus::Queued,
            'situation' => FiscalSituation::Processing,
            'coverage' => $parent->coverage,
            'mutability' => $parent->mutability,
            'attempt' => $attempt,
            'parent_run_id' => $parent->id,
            'correlation_id' => $parent->correlation_id,
            'progress_cursor' => $parent->progress_cursor,
            'progress' => $parent->progress,
            'items_processed' => $parent->items_processed,
            'pages_processed' => $parent->pages_processed,
        ]);

        $pending = ExecuteFiscalMonitoringRunJob::dispatch($child->id)
            ->onQueue((string) config('fiscal_monitoring.job.queue', 'default'));

        if ($delaySeconds > 0) {
            $pending->delay(now()->addSeconds($delaySeconds));
        }

        return $child;
    }

    /**
     * @return array{code:string,message:string}|null
     */
    private function preflightBlockReason(Office $office, Client $client, FiscalMonitoringRun $run): ?array
    {
        if ((bool) config('fiscal_monitoring.kill_switch', false) || FeatureFlags::isKillSwitchActive()) {
            return ['code' => 'KILL_SWITCH', 'message' => 'Kill switch ativo.'];
        }

        if (! FeatureFlags::isGloballyEnabled() && ! (bool) config('fiscal_monitoring.enabled', false)) {
            return ['code' => 'FEATURE_DISABLED', 'message' => 'Monitoramento fiscal desabilitado.'];
        }

        if (! $this->subscriptionGate->allowsExternalCalls($office)) {
            return ['code' => 'SUBSCRIPTION_BLOCKED', 'message' => 'Assinatura do escritório bloqueia chamadas externas.'];
        }

        if ((int) $client->office_id !== (int) $office->id) {
            return ['code' => 'CONTRIBUTOR_CROSS_TENANT', 'message' => 'Contribuinte de outro tenant.'];
        }

        if (! $office->is_active) {
            return ['code' => 'OFFICE_INACTIVE', 'message' => 'Escritório inativo.'];
        }

        return null;
    }

    private function markBlocked(FiscalMonitoringRun $run, string $code, string $message): FiscalMonitoringRun
    {
        $payload = new FiscalPersistPayload(
            run: $run,
            result: FiscalRunResult::Blocked,
            situation: FiscalSituation::Blocked,
            coverage: $run->coverage ?? FiscalCoverage::Unknown,
            skipReason: $code,
            errorCode: $code,
            errorMessage: $message,
        );

        return $this->persistence->persist($payload)['run'];
    }
}
