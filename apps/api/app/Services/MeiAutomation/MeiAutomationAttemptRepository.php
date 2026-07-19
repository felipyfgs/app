<?php

namespace App\Services\MeiAutomation;

use App\DTO\MeiAutomation\MeiAutomationJobResult;
use App\Enums\FiscalSourceProvenance;
use App\Enums\FiscalVerificationKind;
use App\Enums\MeiProvider;
use App\Models\MeiAutomationAttempt;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use LogicException;

final class MeiAutomationAttemptRepository
{
    public function __construct(
        private readonly MeiAutomationMetadataSanitizer $sanitizer,
    ) {}

    /** @param array<string, mixed> $attributes */
    public function createOrGet(int $officeId, string $idempotencyKey, int $attemptNumber, array $attributes): MeiAutomationAttempt
    {
        $attempt = MeiAutomationAttempt::query()
            ->withoutGlobalScopes()
            ->where('office_id', $officeId)
            ->where('idempotency_key', $idempotencyKey)
            ->where('attempt_number', $attemptNumber)
            ->first();

        if ($attempt !== null) {
            if (! hash_equals((string) $attempt->request_fingerprint, (string) ($attributes['request_fingerprint'] ?? ''))) {
                throw new LogicException('Idempotência MEI reutilizada com conteúdo diferente.');
            }

            return $attempt;
        }

        return MeiAutomationAttempt::query()->withoutGlobalScopes()->create([
            ...$attributes,
            'office_id' => $officeId,
            'idempotency_key' => $idempotencyKey,
            'attempt_number' => $attemptNumber,
            'safe_metadata' => $this->sanitizer->sanitize((array) ($attributes['safe_metadata'] ?? [])),
        ]);
    }

    public function findForOffice(int $officeId, int $attemptId): MeiAutomationAttempt
    {
        return MeiAutomationAttempt::query()
            ->withoutGlobalScopes()
            ->where('office_id', $officeId)
            ->findOrFail($attemptId);
    }

    public function findByExternalJobForOffice(int $officeId, string $externalJobId): MeiAutomationAttempt
    {
        $attempt = MeiAutomationAttempt::query()
            ->withoutGlobalScopes()
            ->where('office_id', $officeId)
            ->where('external_job_id', $externalJobId)
            ->first();

        if ($attempt === null) {
            throw (new ModelNotFoundException)->setModel(MeiAutomationAttempt::class, [$externalJobId]);
        }

        return $attempt;
    }

    /** @param array<string, mixed> $metadata */
    public function synchronize(MeiAutomationAttempt $attempt, MeiAutomationJobResult $job, array $metadata = []): MeiAutomationAttempt
    {
        $errorCode = is_string($job->error['code'] ?? null) ? $job->error['code'] : null;
        $errorMessage = is_string($job->error['message'] ?? null) ? $job->error['message'] : null;
        $provider = $attempt->provider;

        $attempt->forceFill([
            'external_job_id' => $job->id,
            'status' => $job->status,
            'source_provenance' => $provider === MeiProvider::ReceitaPortal
                ? FiscalSourceProvenance::ReceitaPortal
                : ($provider === MeiProvider::Serpro ? FiscalSourceProvenance::SerproReal : null),
            'verification_kind' => $provider === MeiProvider::ReceitaPortal
                ? FiscalVerificationKind::PortalArtifact
                : ($provider === MeiProvider::Serpro ? FiscalVerificationKind::SerproApi : null),
            'error_code' => $errorCode === null ? null : mb_substr($errorCode, 0, 80),
            'error_message' => $this->sanitizer->error($errorMessage),
            'safe_metadata' => $this->sanitizer->sanitize([
                ...$metadata,
                'action_type' => $job->actionType,
                'artifact_count' => count($job->artifacts),
            ]),
            'started_at' => $attempt->started_at ?? now(),
            'finished_at' => $job->status->isTerminal() ? now() : null,
        ])->save();

        return $attempt->refresh();
    }
}
