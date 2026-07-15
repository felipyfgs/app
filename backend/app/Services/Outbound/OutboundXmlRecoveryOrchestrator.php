<?php

namespace App\Services\Outbound;

use App\Contracts\SvrsNfceOutboundXmlRetrievalClient;
use App\DTO\Outbound\SvrsNfceRetrievalRequest;
use App\Enums\OutboundCaptureMode;
use App\Enums\OutboundNumberStatus;
use App\Enums\OutboundRetrievalOrigin;
use App\Enums\OutboundRetrievalStatus;
use App\Enums\SvrsNfceFailureReason;
use App\Enums\SvrsNfceRecoveryStatus;
use App\Enums\SvrsNfceTransportOutcome;
use App\Jobs\RecoverSvrsNfceXmlJob;
use App\Models\MaOutboundRetrievalRequest;
use App\Models\OutboundCaptureProfile;
use App\Models\OutboundNumberState;
use App\Models\OutboundXmlRecoveryAttempt;
use App\Services\Audit\AuditLogger;
use App\Services\Certificates\CredentialService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Orquestração idempotente KEY_DISCOVERED/XML_PENDING → recuperação SVRS.
 */
final class OutboundXmlRecoveryOrchestrator
{
    public function __construct(
        private readonly SvrsNfceConfig $config,
        private readonly SvrsNfceRetrievalEligibility $eligibility,
        private readonly SvrsNfceKillSwitchService $killSwitch,
        private readonly SvrsNfceRateLimiter $rateLimiter,
        private readonly SvrsNfceCircuitBreaker $breaker,
        private readonly SvrsNfceOutboundXmlRetrievalClient $client,
        private readonly SvrsNfceXmlIngestionService $ingestion,
        private readonly CredentialService $credentials,
        private readonly AuditLogger $audit,
        private readonly OutboundMetrics $metrics,
    ) {}

    /**
     * Cria ou reutiliza recovery ativa e opcionalmente enfileira.
     */
    public function ensureRecovery(
        OutboundNumberState $number,
        OutboundCaptureProfile $profile,
        bool $queue = true,
        ?int $userId = null,
        string $triggeredBy = 'system',
    ): ?MaOutboundRetrievalRequest {
        if ((int) $number->office_id !== (int) $profile->office_id
            || (int) $number->outbound_capture_profile_id !== (int) $profile->id) {
            return null;
        }

        $a1Ok = $this->hasA1((int) $profile->client_id);
        $eval = $this->eligibility->evaluate($number, $profile, $a1Ok);
        if (! $eval->eligible) {
            return null;
        }

        $key = $this->eligibility->normalizeKey(
            (string) ($number->discovered_access_key ?: $number->candidate_access_key)
        );
        if ($key === null) {
            return null;
        }

        if ($number->status === OutboundNumberStatus::KeyDiscovered) {
            $number->forceFill(['status' => OutboundNumberStatus::XmlPending])->save();
        }

        try {
            $request = DB::transaction(function () use ($number, $profile, $key, $userId) {
                $existing = MaOutboundRetrievalRequest::query()
                    ->where('office_id', $profile->office_id)
                    ->where('outbound_capture_profile_id', $profile->id)
                    ->where('access_key', $key)
                    ->where('origin', OutboundRetrievalOrigin::SvrsPortalByKey)
                    ->whereNotIn('recovery_status', [
                        SvrsNfceRecoveryStatus::Captured->value,
                        SvrsNfceRecoveryStatus::NotAvailableVisible->value,
                        SvrsNfceRecoveryStatus::Blocked->value,
                        SvrsNfceRecoveryStatus::ResolvedByOtherSource->value,
                    ])
                    ->lockForUpdate()
                    ->first();

                if ($existing !== null) {
                    return $existing;
                }

                return MaOutboundRetrievalRequest::query()->create([
                    'office_id' => $profile->office_id,
                    'outbound_capture_profile_id' => $profile->id,
                    'establishment_id' => $profile->establishment_id,
                    'environment' => $profile->environment,
                    'model' => $profile->model,
                    'direction' => 'OUT',
                    'competence' => now()->format('Y-m'),
                    'status' => OutboundRetrievalStatus::Pending,
                    'mode' => OutboundCaptureMode::Automatic,
                    'origin' => OutboundRetrievalOrigin::SvrsPortalByKey,
                    'access_key' => $key,
                    'outbound_number_state_id' => $number->id,
                    'recovery_status' => SvrsNfceRecoveryStatus::Eligible,
                    'attempt_count' => 0,
                    'correlation_id' => (string) Str::uuid(),
                    'created_by' => $userId,
                ]);
            });
        } catch (Throwable) {
            // Unique parcial (PG) ou corrida: re-ler recovery ativa
            $request = MaOutboundRetrievalRequest::query()
                ->where('office_id', $profile->office_id)
                ->where('outbound_capture_profile_id', $profile->id)
                ->where('access_key', $key)
                ->where('origin', OutboundRetrievalOrigin::SvrsPortalByKey)
                ->whereNotIn('recovery_status', [
                    SvrsNfceRecoveryStatus::Captured->value,
                    SvrsNfceRecoveryStatus::NotAvailableVisible->value,
                    SvrsNfceRecoveryStatus::Blocked->value,
                    SvrsNfceRecoveryStatus::ResolvedByOtherSource->value,
                ])
                ->first();
            if ($request === null) {
                return null;
            }
        }

        if ($queue) {
            $this->enqueue($request, $userId, $triggeredBy);
        }

        return $request->fresh();
    }

    /**
     * Enfileira somente se ainda não há job em andamento (QUEUED/RUNNING).
     */
    public function enqueue(MaOutboundRetrievalRequest $request, ?int $userId = null, string $triggeredBy = 'system'): bool
    {
        if ($request->origin !== OutboundRetrievalOrigin::SvrsPortalByKey) {
            return false;
        }
        if ($request->recovery_status?->isTerminal()) {
            return false;
        }

        // Não re-dispatch se já está na fila ou em execução
        if (in_array($request->recovery_status, [
            SvrsNfceRecoveryStatus::Queued,
            SvrsNfceRecoveryStatus::Running,
        ], true)) {
            return false;
        }

        // RETRY_SCHEDULED só se next_attempt_at vencido (ou null)
        if ($request->recovery_status === SvrsNfceRecoveryStatus::RetryScheduled
            && $request->next_attempt_at !== null
            && $request->next_attempt_at->isFuture()) {
            return false;
        }

        $request->forceFill([
            'recovery_status' => SvrsNfceRecoveryStatus::Queued,
            'status' => OutboundRetrievalStatus::Requested,
            'requested_at' => $request->requested_at ?? now(),
        ])->save();

        RecoverSvrsNfceXmlJob::dispatch($request->id)
            ->onQueue($this->config->queue());

        $this->audit->record('svrs_nfce.recovery.queued', 'SUCCESS', $request, [
            'triggered_by' => $triggeredBy,
            'correlation_id' => $request->correlation_id,
        ], $userId, $request->office_id);

        $this->metrics->increment('svrs_nfce_queued');

        return true;
    }

    /**
     * Executa uma tentativa (chamado pelo job). Payload do job só tem id interno.
     */
    public function runAttempt(int $requestId): void
    {
        $ttl = max(30, $this->config->lockTtlSeconds());
        $lock = Cache::lock('svrs_nfce.recovery.'.$requestId, $ttl);
        if (! $lock->get()) {
            return;
        }

        $acquiredRate = false;

        try {
            $this->runAttemptLocked($requestId, $acquiredRate);
        } finally {
            if ($acquiredRate) {
                $this->rateLimiter->release();
            }
            $lock->release();
        }
    }

    private function runAttemptLocked(int $requestId, bool &$acquiredRate): void
    {
        $request = MaOutboundRetrievalRequest::withoutGlobalScopes()->find($requestId);
        if ($request === null) {
            return;
        }

        if ($request->origin !== OutboundRetrievalOrigin::SvrsPortalByKey) {
            return;
        }

        if ($request->recovery_status === SvrsNfceRecoveryStatus::Captured
            || $request->recovery_status === SvrsNfceRecoveryStatus::ResolvedByOtherSource) {
            return;
        }

        // Já RUNNING por outro worker (não deveria com lock, mas defensivo)
        if ($request->recovery_status === SvrsNfceRecoveryStatus::Running
            && $request->updated_at !== null
            && $request->updated_at->gt(now()->subSeconds($this->config->lockTtlSeconds()))) {
            return;
        }

        $profile = OutboundCaptureProfile::withoutGlobalScopes()->find($request->outbound_capture_profile_id);
        $number = OutboundNumberState::withoutGlobalScopes()->find($request->outbound_number_state_id);
        if ($profile === null || $number === null) {
            return;
        }

        if ((int) $request->office_id !== (int) $profile->office_id
            || (int) $number->office_id !== (int) $profile->office_id) {
            return;
        }

        if (in_array($number->status, [OutboundNumberStatus::XmlCaptured, OutboundNumberStatus::Complete], true)
            && $number->dfe_document_id) {
            $request->forceFill([
                'recovery_status' => SvrsNfceRecoveryStatus::ResolvedByOtherSource,
                'failure_reason' => SvrsNfceFailureReason::CapturedByOther,
            ])->save();

            return;
        }

        if (! $this->config->retrievalEnabled() || $this->killSwitch->isActive()) {
            $this->scheduleRetry(
                $request,
                $this->killSwitch->isActive() ? SvrsNfceFailureReason::KillSwitch : SvrsNfceFailureReason::ChannelDisabled,
                'Canal off ou kill switch — fallback assistido.',
                900,
            );

            return;
        }

        $globalState = $this->breaker->globalStatus()['state'];
        $rootState = $this->breaker->rootStatus((int) $profile->client_id)['state'];
        $isProbe = $globalState === 'half_open' || $rootState === 'half_open';

        // Probe half-open: qualquer recovery elegível (não exige allowlist de piloto)
        if (! $this->breaker->isCallAllowed((int) $profile->client_id, $isProbe)) {
            $this->scheduleRetry($request, SvrsNfceFailureReason::BreakerOpen, 'Circuit breaker aberto.', 900);

            return;
        }

        $rate = $this->rateLimiter->acquire((int) $profile->client_id);
        if (! $rate['allowed']) {
            $this->scheduleRetry(
                $request,
                SvrsNfceFailureReason::RateLimited,
                'Rate limit.',
                max(1, $rate['retry_after_seconds']),
            );

            return;
        }
        $acquiredRate = true;

        $correlationId = $request->correlation_id ?: (string) Str::uuid();
        $attemptNumber = (int) $request->attempt_count + 1;
        $startedAt = now();

        $request->forceFill([
            'recovery_status' => SvrsNfceRecoveryStatus::Running,
            'status' => OutboundRetrievalStatus::Processing,
            'correlation_id' => $correlationId,
            'attempt_count' => $attemptNumber,
        ])->save();

        $certificate = null;
        $resultOutcome = null;
        $failure = null;
        $detail = null;
        $sha = null;
        $getMs = null;
        $postMs = null;
        $totalMs = null;
        $httpStatus = null;
        $parserVersion = null;
        $retryAfterHint = null;

        try {
            $certificate = $this->materializeA1((int) $profile->client_id);
            if ($certificate === null) {
                $failure = SvrsNfceFailureReason::A1Unavailable;
                $detail = 'A1 indisponível.';
                $resultOutcome = SvrsNfceTransportOutcome::AuthForbidden;
            } else {
                $dto = new SvrsNfceRetrievalRequest(
                    accessKey: (string) $request->access_key,
                    environment: (string) $profile->environment,
                    correlationId: $correlationId,
                    officeId: (int) $profile->office_id,
                    profileId: (int) $profile->id,
                    clientId: (int) $profile->client_id,
                    establishmentId: (int) $profile->establishment_id,
                );

                $result = $this->client->retrieve($dto, $certificate);
                $resultOutcome = $result->outcome;
                $getMs = $result->getLatencyMs;
                $postMs = $result->postLatencyMs;
                $totalMs = $result->totalLatencyMs;
                $httpStatus = $result->httpStatus;
                $parserVersion = $result->parserVersion;
                $detail = $result->sanitizedDetail;
                $retryAfterHint = $result->retryAfterSeconds;

                if ($result->isSuccess()) {
                    $establishment = $profile->establishment()->withoutGlobalScopes()->first()
                        ?? \App\Models\Establishment::withoutGlobalScopes()->find($profile->establishment_id);

                    if ($establishment === null) {
                        $failure = SvrsNfceFailureReason::NotEligible;
                        $detail = 'Estabelecimento ausente.';
                    } else {
                        $ingest = $this->ingestion->ingestValidatedBytes(
                            $profile,
                            $establishment,
                            $number,
                            $request,
                            $result->xmlBytes ?? '',
                            (string) $request->access_key,
                            $correlationId,
                        );

                        $sha = $ingest['sha256'] ?? $result->sha256;

                        if (($ingest['status'] ?? '') === 'captured' || ($ingest['status'] ?? '') === 'duplicate') {
                            $this->breaker->recordSuccess((int) $profile->client_id);
                            $this->metrics->increment(
                                ($ingest['status'] ?? '') === 'duplicate' ? 'svrs_nfce_duplicate' : 'svrs_nfce_captured'
                            );
                            $this->recordAttempt(
                                $request, $profile, $number, $correlationId, $attemptNumber,
                                SvrsNfceRecoveryStatus::Captured, null, $resultOutcome,
                                $httpStatus, $parserVersion, $getMs, $postMs, $totalMs, $detail, $sha, $startedAt
                            );

                            return;
                        }

                        $failure = $ingest['failure_reason'] ?? SvrsNfceFailureReason::InvalidXml;
                        $detail = $ingest['sanitized_detail'] ?? $detail;
                    }
                } else {
                    $failure = $result->outcome->toFailureReason() ?? SvrsNfceFailureReason::HttpTransient;
                }
            }
        } catch (Throwable $e) {
            $failure = SvrsNfceFailureReason::HttpTransient;
            $detail = 'Falha interna sanitizada.';
            // não re-lançar: nunca deixar RUNNING órfão
        } finally {
            if (is_array($certificate)) {
                $certificate['pfx'] = '';
                $certificate['password'] = '';
            }
            unset($certificate);
        }

        if ($failure === null) {
            $failure = SvrsNfceFailureReason::HttpTransient;
        }

        $this->breaker->recordFailure($failure, (int) $profile->client_id, null, (int) $profile->office_id);

        $terminalStatus = $this->resolveTerminalStatus($failure, $attemptNumber);
        if ($terminalStatus === SvrsNfceRecoveryStatus::NotAvailableVisible) {
            $failure = SvrsNfceFailureReason::MaxAttempts;
        }

        $this->recordAttempt(
            $request, $profile, $number, $correlationId, $attemptNumber,
            $terminalStatus, $failure, $resultOutcome,
            $httpStatus, $parserVersion, $getMs, $postMs, $totalMs, $detail, $sha, $startedAt
        );

        if ($terminalStatus === SvrsNfceRecoveryStatus::RetryScheduled) {
            $delay = $this->backoffSeconds($attemptNumber);
            if ($retryAfterHint !== null && $retryAfterHint > 0) {
                $delay = max($delay, $retryAfterHint);
            }
            $request->forceFill([
                'recovery_status' => SvrsNfceRecoveryStatus::RetryScheduled,
                'failure_reason' => $failure,
                'last_error' => mb_substr((string) $detail, 0, 500),
                'next_attempt_at' => now()->addSeconds($delay),
            ])->save();

            $this->metrics->increment('svrs_nfce_retry');
            RecoverSvrsNfceXmlJob::dispatch($request->id)
                ->delay(now()->addSeconds($delay))
                ->onQueue($this->config->queue());

            if ($number->status !== OutboundNumberStatus::XmlCaptured) {
                $number->forceFill(['status' => OutboundNumberStatus::XmlPending])->save();
            }

            return;
        }

        $request->forceFill([
            'recovery_status' => $terminalStatus,
            'failure_reason' => $failure,
            'last_error' => mb_substr((string) $detail, 0, 500),
            'next_attempt_at' => null,
        ])->save();

        if ($number->status !== OutboundNumberStatus::XmlCaptured) {
            $number->forceFill([
                'status' => OutboundNumberStatus::XmlPending,
                'block_reason' => $terminalStatus === SvrsNfceRecoveryStatus::Blocked
                    ? mb_substr((string) $detail, 0, 500)
                    : $number->block_reason,
            ])->save();
        }

        $this->metrics->increment(
            $terminalStatus === SvrsNfceRecoveryStatus::Blocked ? 'svrs_nfce_blocked' : 'svrs_nfce_fallback'
        );
    }

    public function resolveByOtherSource(int $officeId, string $accessKey, string $sourceLabel = 'other'): void
    {
        $key = $this->eligibility->normalizeKey($accessKey);
        if ($key === null) {
            return;
        }

        MaOutboundRetrievalRequest::withoutGlobalScopes()
            ->where('office_id', $officeId)
            ->where('access_key', $key)
            ->where('origin', OutboundRetrievalOrigin::SvrsPortalByKey)
            ->whereNotIn('recovery_status', [
                SvrsNfceRecoveryStatus::Captured->value,
                SvrsNfceRecoveryStatus::ResolvedByOtherSource->value,
            ])
            ->update([
                'recovery_status' => SvrsNfceRecoveryStatus::ResolvedByOtherSource->value,
                'failure_reason' => SvrsNfceFailureReason::CapturedByOther->value,
                'last_error' => 'Resolvido por '.$sourceLabel,
                'next_attempt_at' => null,
            ]);
    }

    private function scheduleRetry(
        MaOutboundRetrievalRequest $request,
        SvrsNfceFailureReason $reason,
        string $detail,
        int $delaySeconds,
    ): void {
        $request->forceFill([
            'recovery_status' => SvrsNfceRecoveryStatus::RetryScheduled,
            'failure_reason' => $reason,
            'last_error' => mb_substr($detail, 0, 500),
            'next_attempt_at' => now()->addSeconds(max(1, $delaySeconds)),
        ])->save();

        RecoverSvrsNfceXmlJob::dispatch($request->id)
            ->delay(now()->addSeconds(max(1, $delaySeconds)))
            ->onQueue($this->config->queue());
    }

    private function resolveTerminalStatus(SvrsNfceFailureReason $failure, int $attemptNumber): SvrsNfceRecoveryStatus
    {
        if (! $failure->isRecoverable()) {
            return SvrsNfceRecoveryStatus::Blocked;
        }
        if ($attemptNumber >= $this->config->maxRecoverableAttempts()) {
            return SvrsNfceRecoveryStatus::NotAvailableVisible;
        }

        return SvrsNfceRecoveryStatus::RetryScheduled;
    }

    private function backoffSeconds(int $attemptNumber): int
    {
        $schedule = $this->config->retryBackoffSeconds();
        $idx = min(max(0, $attemptNumber - 1), count($schedule) - 1);
        $base = $schedule[$idx];
        $jitter = $this->config->retryJitterRatio();
        $delta = (int) round($base * $jitter * (mt_rand(-1000, 1000) / 1000));

        return max(1, $base + $delta);
    }

    private function recordAttempt(
        MaOutboundRetrievalRequest $request,
        OutboundCaptureProfile $profile,
        OutboundNumberState $number,
        string $correlationId,
        int $attemptNumber,
        SvrsNfceRecoveryStatus $result,
        ?SvrsNfceFailureReason $failure,
        ?SvrsNfceTransportOutcome $outcome,
        ?int $httpStatus,
        ?string $parserVersion,
        ?int $getMs,
        ?int $postMs,
        ?int $totalMs,
        ?string $detail,
        ?string $sha,
        $startedAt,
    ): void {
        try {
            OutboundXmlRecoveryAttempt::query()->create([
                'office_id' => $request->office_id,
                'ma_outbound_retrieval_request_id' => $request->id,
                'outbound_capture_profile_id' => $profile->id,
                'outbound_number_state_id' => $number->id,
                'access_key' => $request->access_key,
                'correlation_id' => $correlationId,
                'attempt_number' => $attemptNumber,
                'result' => $result,
                'failure_reason' => $failure,
                'transport_outcome' => $outcome,
                'http_status' => $httpStatus,
                'parser_version' => $parserVersion,
                'get_latency_ms' => $getMs,
                'post_latency_ms' => $postMs,
                'total_latency_ms' => $totalMs,
                'sanitized_detail' => $detail ? mb_substr($detail, 0, 500) : null,
                'sha256' => $sha,
                'started_at' => $startedAt,
                'finished_at' => now(),
            ]);
        } catch (Throwable) {
            // unique attempt_number sob corrida residual — não derruba o fluxo
        }
    }

    private function hasA1(int $clientId): bool
    {
        $client = \App\Models\Client::withoutGlobalScopes()->find($clientId);
        if ($client === null) {
            return false;
        }

        return $this->credentials->activeFor($client) !== null;
    }

    /**
     * @return array{pfx: string, password: string}|null
     */
    private function materializeA1(int $clientId): ?array
    {
        $client = \App\Models\Client::withoutGlobalScopes()->find($clientId);
        if ($client === null) {
            return null;
        }

        $credential = $this->credentials->activeFor($client);
        if ($credential === null) {
            return null;
        }

        try {
            $material = $this->credentials->loadPfxMaterial($credential);
        } catch (Throwable) {
            return null;
        }
        if (! is_array($material) || ($material['pfx'] ?? '') === '') {
            return null;
        }

        return [
            'pfx' => $material['pfx'],
            'password' => (string) ($material['password'] ?? ''),
        ];
    }
}
