<?php

namespace App\Services\Integra;

use App\Contracts\SecureObjectStore;
use App\Enums\AuthorCertificateMode;
use App\Enums\OfficeSerproOnboardingStatus;
use App\Enums\SerproAuthorizationStatus;
use App\Enums\SerproEnvironment;
use App\Enums\TermoAuthorizationState;
use App\Jobs\Serpro\ProcessOfficeSerproOnboardingJob;
use App\Jobs\Serpro\SignTermoWithManagedA1Job;
use App\Models\Office;
use App\Models\OfficeInstitutionalProfile;
use App\Models\OfficeSerproAuthorization;
use App\Models\OfficeSerproOnboardingState;
use App\Models\OfficeTechnicalConsent;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * State machine + enqueue de onboarding SERPRO automatizado (F-3.1).
 * Deriva estado de perfil/consentimento/A1 (quando existirem) + OfficeSerproAuthorization.
 */
final class OfficeSerproOnboardingService
{
    public function __construct(
        private readonly OfficeSerproAuthorizationService $authorizations,
        private readonly AuditLogger $audit,
    ) {}

    public function getOrCreateState(Office $office, SerproEnvironment $environment): OfficeSerproOnboardingState
    {
        $state = OfficeSerproOnboardingState::query()
            ->where('office_id', $office->id)
            ->where('environment', $environment->value)
            ->first();

        if ($state !== null) {
            return $state;
        }

        return OfficeSerproOnboardingState::query()->create([
            'office_id' => $office->id,
            'environment' => $environment,
            'status' => OfficeSerproOnboardingStatus::Incomplete,
            'last_transition_at' => now(),
        ]);
    }

    /**
     * Reavalia pré-requisitos e, se ready, enfileira job idempotente.
     *
     * @return array{
     *   state: OfficeSerproOnboardingState,
     *   enqueued: bool,
     *   prerequisites: array<string, bool>
     * }
     */
    public function evaluateAndMaybeEnqueue(
        Office $office,
        SerproEnvironment $environment,
        ?int $actorUserId = null,
        ?string $correlationId = null,
        bool $force = false,
    ): array {
        $state = $this->getOrCreateState($office, $environment);
        $prereq = $this->evaluatePrerequisites($office, $environment);
        $correlationId ??= (string) Str::uuid();

        if ($state->status === OfficeSerproOnboardingStatus::Revoked) {
            return ['state' => $state, 'enqueued' => false, 'prerequisites' => $prereq];
        }

        if ($state->status === OfficeSerproOnboardingStatus::Authorized && ! $force) {
            $this->clearActionable($state);

            return ['state' => $state->refresh(), 'enqueued' => false, 'prerequisites' => $prereq];
        }

        if (! $prereq['complete']) {
            $this->transition(
                $state,
                OfficeSerproOnboardingStatus::Incomplete,
                lastStep: 'prerequisites',
                actionableCode: $prereq['missing_code'],
                actionableMessage: $prereq['missing_message'],
                correlationId: $correlationId,
            );

            return ['state' => $state->refresh(), 'enqueued' => false, 'prerequisites' => $prereq];
        }

        if (
            in_array($state->status, [
                OfficeSerproOnboardingStatus::Provisioning,
                OfficeSerproOnboardingStatus::Authorized,
            ], true)
            && ! $force
        ) {
            // Idempotência: já em andamento / autorizado
            return ['state' => $state, 'enqueued' => false, 'prerequisites' => $prereq];
        }

        $idempotencyKey = $this->buildIdempotencyKey($office, $environment, $prereq['fingerprint']);

        if (! $force && $state->idempotency_key === $idempotencyKey
            && $state->status === OfficeSerproOnboardingStatus::Provisioning
        ) {
            return ['state' => $state, 'enqueued' => false, 'prerequisites' => $prereq];
        }

        // Pré-requisitos ok → Ready (auditável) e em seguida Provisioning + enqueue.
        if ($state->status !== OfficeSerproOnboardingStatus::Ready
            || $state->idempotency_key !== $idempotencyKey
        ) {
            $this->transition(
                $state,
                OfficeSerproOnboardingStatus::Ready,
                lastStep: 'ready',
                correlationId: $correlationId,
                idempotencyKey: $idempotencyKey,
                readyAt: $state->ready_at ?? now(),
                clearTechnical: true,
                clearActionable: true,
            );
        }

        $this->transition(
            $state,
            OfficeSerproOnboardingStatus::Provisioning,
            lastStep: 'enqueued',
            correlationId: $correlationId,
            idempotencyKey: $idempotencyKey,
            readyAt: $state->ready_at ?? now(),
            provisioningStartedAt: now(),
            clearTechnical: true,
            clearActionable: true,
        );

        ProcessOfficeSerproOnboardingJob::dispatch(
            officeId: (int) $office->id,
            environment: $environment->value,
            idempotencyKey: $idempotencyKey,
            actorUserId: $actorUserId,
            correlationId: $correlationId,
        );

        $this->audit->record('serpro.onboarding.enqueue', 'SUCCESS', $state, [
            'environment' => $environment->value,
            'idempotency_key' => $idempotencyKey,
        ], $actorUserId, $office->id);

        return ['state' => $state->refresh(), 'enqueued' => true, 'prerequisites' => $prereq];
    }

    /**
     * Execução interna do job (com lock).
     */
    public function process(
        Office $office,
        SerproEnvironment $environment,
        string $idempotencyKey,
        ?int $actorUserId = null,
        ?string $correlationId = null,
    ): OfficeSerproOnboardingState {
        $lock = Cache::lock($this->lockKey($office, $environment), 120);
        if (! $lock->get()) {
            throw new RuntimeException('ONBOARDING_LOCK_BUSY');
        }

        try {
            $state = $this->getOrCreateState($office, $environment);

            if (
                $state->status === OfficeSerproOnboardingStatus::Authorized
                && $state->idempotency_key === $idempotencyKey
            ) {
                return $state;
            }

            if (
                $state->status === OfficeSerproOnboardingStatus::Provisioning
                && $state->idempotency_key === $idempotencyKey
                && $state->last_step === 'token_refresh'
                && $state->authorized_at !== null
            ) {
                return $state;
            }

            $prereq = $this->evaluatePrerequisites($office, $environment);
            if (! $prereq['complete']) {
                $this->transition(
                    $state,
                    OfficeSerproOnboardingStatus::Incomplete,
                    lastStep: 'prerequisites',
                    actionableCode: $prereq['missing_code'],
                    actionableMessage: $prereq['missing_message'],
                    correlationId: $correlationId,
                );

                return $state->refresh();
            }

            $this->transition(
                $state,
                OfficeSerproOnboardingStatus::Provisioning,
                lastStep: 'provisioning_start',
                correlationId: $correlationId,
                idempotencyKey: $idempotencyKey,
                provisioningStartedAt: $state->provisioning_started_at ?? now(),
                clearTechnical: true,
                clearActionable: true,
            );

            $auth = $this->authorizations->getOrCreate($office, $environment);

            // 1) Draft do Termo se ausente
            $meta = is_array($auth->metadata) ? $auth->metadata : [];
            if (empty($meta['termo_draft_vault_object_id']) && $auth->termo_vault_object_id === null) {
                $this->setStep($state, 'termo_draft');
                $this->authorizations->generateTermoDraft($office, $environment, null, $actorUserId);
                $auth = $auth->refresh();
            }

            // 2) Assinar com A1 gerenciado de forma síncrona (mesmo path do job dedicado)
            if ($auth->termo_vault_object_id === null) {
                $this->setStep($state, 'termo_sign');
                if ($auth->certificate_mode === AuthorCertificateMode::ManagedA1) {
                    if (empty($meta['termo_draft_vault_object_id'])) {
                        $this->authorizations->generateTermoDraft($office, $environment, null, $actorUserId);
                        $auth = $auth->refresh();
                    }

                    $signJob = new SignTermoWithManagedA1Job(
                        $office->id,
                        $environment->value,
                        $auth->id,
                        $actorUserId,
                        $correlationId,
                    );
                    $signJob->handle(
                        app(SecureObjectStore::class),
                        app(TermoXmlSigner::class),
                        $this->authorizations,
                        $this->audit,
                    );
                    $auth = $auth->refresh();
                    if ($auth->termo_vault_object_id === null) {
                        $this->transition(
                            $state,
                            OfficeSerproOnboardingStatus::ActionRequired,
                            lastStep: 'termo_sign',
                            actionableCode: 'UPLOAD_TERMO',
                            actionableMessage: 'Falha ao assinar o Termo com A1 gerenciado.',
                            correlationId: $correlationId,
                        );

                        return $state->refresh();
                    }
                } else {
                    $this->transition(
                        $state,
                        OfficeSerproOnboardingStatus::ActionRequired,
                        lastStep: 'termo_sign',
                        actionableCode: 'UPLOAD_TERMO',
                        actionableMessage: 'Envie o Termo de Autorização assinado ou use A1 gerenciado.',
                        correlationId: $correlationId,
                    );

                    return $state->refresh();
                }
            }

            // 3) Apoiar / token procurador
            $this->setStep($state, 'token_refresh');
            try {
                $auth = $this->authorizations->refreshProcuradorToken($office, $environment, $actorUserId);
            } catch (Throwable $e) {
                $this->markTechnicalError(
                    $state,
                    'APOIAR_FAILED',
                    $this->sanitizeTechnicalMessage($e->getMessage()),
                    $correlationId,
                );
                throw $e;
            }

            if ($auth->status === SerproAuthorizationStatus::ActionRequired) {
                $this->transition(
                    $state,
                    OfficeSerproOnboardingStatus::ActionRequired,
                    lastStep: 'token_refresh',
                    actionableCode: 'SIGNATURE_REQUIRED',
                    actionableMessage: $auth->action_required_reason
                        ?? 'Ação necessária para renovar autorização SERPRO.',
                    correlationId: $correlationId,
                );

                return $state->refresh();
            }

            $tokenOk = $auth->procurador_token_vault_object_id !== null
                && $auth->procurador_token_expires_at !== null
                && $auth->procurador_token_expires_at->isFuture();

            $termoAccepted = in_array($auth->termo_authorization_state, [
                TermoAuthorizationState::SerproAccepted,
                TermoAuthorizationState::LocalValidated,
            ], true);

            if (! $tokenOk && ! $termoAccepted) {
                $this->markTechnicalError(
                    $state,
                    'TOKEN_MISSING',
                    'Token do procurador ausente após Apoiar.',
                    $correlationId,
                );

                return $state->refresh();
            }

            $this->transition(
                $state,
                OfficeSerproOnboardingStatus::Authorized,
                lastStep: 'authorized',
                correlationId: $correlationId,
                idempotencyKey: $idempotencyKey,
                authorizedAt: now(),
                clearTechnical: true,
                clearActionable: true,
            );

            $this->audit->record('serpro.onboarding.authorized', 'SUCCESS', $state, [
                'environment' => $environment->value,
                'authorization_status' => $auth->status->value,
            ], $actorUserId, $office->id);

            return $state->refresh();
        } finally {
            $lock->release();
        }
    }

    public function reactToProfileOrCredentialChange(
        Office $office,
        SerproEnvironment $environment,
        string $reason,
        ?int $actorUserId = null,
    ): OfficeSerproOnboardingState {
        $state = $this->getOrCreateState($office, $environment);

        if (in_array($state->status, [
            OfficeSerproOnboardingStatus::Authorized,
            OfficeSerproOnboardingStatus::Provisioning,
            OfficeSerproOnboardingStatus::Ready,
        ], true)) {
            $this->transition(
                $state,
                OfficeSerproOnboardingStatus::ActionRequired,
                lastStep: 'invalidate_'.$reason,
                actionableCode: 'REONBOARD_REQUIRED',
                actionableMessage: 'Perfil, consentimento ou A1 alterados — reonboarding necessário.',
            );

            try {
                $auth = $this->authorizations->getOrCreate($office, $environment);
                $this->authorizations->invalidateDerivedAuthorization(
                    $auth,
                    $office,
                    $environment,
                    reason: $reason,
                    actorUserId: $actorUserId,
                );
            } catch (Throwable) {
            }
        }

        return $this->evaluateAndMaybeEnqueue($office, $environment, $actorUserId)['state'];
    }

    /**
     * @return array{
     *   complete: bool,
     *   profile: bool,
     *   consent: bool,
     *   a1: bool,
     *   author: bool,
     *   missing_code: ?string,
     *   missing_message: ?string,
     *   fingerprint: string
     * }
     */
    public function evaluatePrerequisites(Office $office, SerproEnvironment $environment): array
    {
        $auth = OfficeSerproAuthorization::query()
            ->where('office_id', $office->id)
            ->where('environment', $environment->value)
            ->first();

        $profileOk = $this->hasInstitutionalProfile($office);
        $consentOk = $this->hasTechnicalConsent($office, $auth);
        $a1Ok = $auth !== null
            && $auth->author_pfx_vault_object_id !== null
            && $auth->certificate_mode === AuthorCertificateMode::ManagedA1;
        // External signature path: A1 not required if author configured (tenant signs offline)
        $externalOk = $auth !== null
            && $auth->certificate_mode === AuthorCertificateMode::ExternalSignature
            && $auth->author_identity !== ''
            && $auth->author_identity !== '00000000000000';

        $authorOk = $auth !== null
            && $auth->author_identity !== ''
            && $auth->author_identity !== '00000000000000';

        $credentialOk = $a1Ok || $externalOk;

        $missingCode = null;
        $missingMessage = null;
        if (! $profileOk) {
            $missingCode = 'COMPLETE_PROFILE';
            $missingMessage = 'Complete o perfil institucional (CNPJ, razão social, e-mail e telefone).';
        } elseif (! $consentOk) {
            $missingCode = 'CONSENT_REQUIRED';
            $missingMessage = 'Aceite o consentimento técnico vigente para uso do certificado.';
        } elseif (! $authorOk) {
            $missingCode = 'AUTHOR_REQUIRED';
            $missingMessage = 'Identidade do autor do pedido ainda não está disponível a partir do perfil.';
        } elseif (! $credentialOk) {
            $missingCode = 'A1_REQUIRED';
            $missingMessage = 'Envie o certificado e-CNPJ A1 do escritório.';
        }

        $complete = $profileOk && $consentOk && $authorOk && $credentialOk;
        $fingerprint = hash('sha256', implode('|', [
            (string) $office->id,
            $environment->value,
            $auth?->author_identity ?? '',
            $auth?->author_fingerprint_sha256 ?? '',
            $auth?->certificate_mode?->value ?? '',
            $complete ? '1' : '0',
        ]));

        return [
            'complete' => $complete,
            'profile' => $profileOk,
            'consent' => $consentOk,
            'a1' => $credentialOk,
            'author' => $authorOk,
            'missing_code' => $missingCode,
            'missing_message' => $missingMessage,
            'fingerprint' => $fingerprint,
        ];
    }

    private function hasInstitutionalProfile(Office $office): bool
    {
        $profile = OfficeInstitutionalProfile::query()
            ->where('office_id', $office->id)
            ->first();

        if ($profile !== null) {
            return $profile->isComplete();
        }

        // Fallback legacy: office name + author identity (pré-backfill A-1.1).
        if (trim((string) $office->name) === '') {
            return false;
        }

        $auth = OfficeSerproAuthorization::query()->where('office_id', $office->id)->first();

        return $auth !== null
            && $auth->author_identity !== ''
            && $auth->author_identity !== '00000000000000';
    }

    private function hasTechnicalConsent(Office $office, ?OfficeSerproAuthorization $auth): bool
    {
        $consent = OfficeTechnicalConsent::query()
            ->where('office_id', $office->id)
            ->whereNull('revoked_at')
            ->orderByDesc('id')
            ->first();

        if ($consent !== null) {
            return $consent->isActive();
        }

        if ($auth === null) {
            return false;
        }

        // Compat: managed A1 consent flag ou autor externo configurado.
        if ($auth->certificate_mode === AuthorCertificateMode::ManagedA1) {
            return (bool) $auth->managed_a1_consent;
        }

        return $auth->author_identity !== '' && $auth->author_identity !== '00000000000000';
    }

    private function buildIdempotencyKey(Office $office, SerproEnvironment $environment, string $fingerprint): string
    {
        return substr(hash('sha256', 'onboard|'.$office->id.'|'.$environment->value.'|'.$fingerprint), 0, 64);
    }

    private function lockKey(Office $office, SerproEnvironment $environment): string
    {
        return sprintf('serpro:onboarding:%d:%s', $office->id, $environment->value);
    }

    private function transition(
        OfficeSerproOnboardingState $state,
        OfficeSerproOnboardingStatus $to,
        ?string $lastStep = null,
        ?string $actionableCode = null,
        ?string $actionableMessage = null,
        ?string $technicalCode = null,
        ?string $technicalMessage = null,
        ?string $correlationId = null,
        ?string $idempotencyKey = null,
        mixed $readyAt = false,
        mixed $provisioningStartedAt = false,
        mixed $authorizedAt = false,
        bool $clearTechnical = false,
        bool $clearActionable = false,
    ): void {
        $state->status = $to;
        if ($lastStep !== null) {
            $state->last_step = $lastStep;
        }
        if ($clearActionable) {
            $state->actionable_code = null;
            $state->actionable_message = null;
        } elseif ($actionableCode !== null) {
            $state->actionable_code = $actionableCode;
            $state->actionable_message = $actionableMessage !== null
                ? mb_substr($actionableMessage, 0, 500)
                : null;
        }
        if ($clearTechnical) {
            $state->technical_code = null;
            $state->technical_message = null;
        } elseif ($technicalCode !== null) {
            $state->technical_code = $technicalCode;
            $state->technical_message = $technicalMessage !== null
                ? mb_substr($technicalMessage, 0, 500)
                : null;
        }
        if ($correlationId !== null) {
            $state->correlation_id = $correlationId;
        }
        if ($idempotencyKey !== null) {
            $state->idempotency_key = $idempotencyKey;
        }
        if ($readyAt !== false) {
            $state->ready_at = $readyAt;
        }
        if ($provisioningStartedAt !== false) {
            $state->provisioning_started_at = $provisioningStartedAt;
        }
        if ($authorizedAt !== false) {
            $state->authorized_at = $authorizedAt;
        }
        $state->last_transition_at = now();
        $state->save();
    }

    private function setStep(OfficeSerproOnboardingState $state, string $step): void
    {
        $state->last_step = $step;
        $state->last_transition_at = now();
        $state->save();
    }

    private function markTechnicalError(
        OfficeSerproOnboardingState $state,
        string $code,
        string $message,
        ?string $correlationId,
    ): void {
        $this->transition(
            $state,
            OfficeSerproOnboardingStatus::TechnicalError,
            lastStep: $state->last_step,
            technicalCode: $code,
            technicalMessage: $message,
            correlationId: $correlationId,
            // Tenant: indisponível sem detalhe OAuth/mTLS
            actionableCode: 'PLATFORM_UNAVAILABLE',
            actionableMessage: 'Integração SERPRO temporariamente indisponível. Tente novamente mais tarde.',
        );
    }

    private function clearActionable(OfficeSerproOnboardingState $state): void
    {
        if ($state->actionable_code === null && $state->technical_code === null) {
            return;
        }
        $state->actionable_code = null;
        $state->actionable_message = null;
        $state->save();
    }

    private function sanitizeTechnicalMessage(string $message): string
    {
        $message = preg_replace('/\b[A-Za-z0-9+\/]{40,}={0,2}\b/', '[redacted]', $message) ?? $message;
        $message = preg_replace('/Bearer\s+\S+/i', 'Bearer [redacted]', $message) ?? $message;
        $message = preg_replace('/consumer[_-]?secret[^\s]*/i', 'consumer_secret=[redacted]', $message) ?? $message;

        return mb_substr($message, 0, 500);
    }
}
