<?php

namespace App\Services\Integra;

use App\Contracts\AutenticarProcuradorClient;
use App\Contracts\PfxReaderInterface;
use App\Contracts\SecureObjectStore;
use App\Contracts\SerproContractAuthenticator;
use App\DTO\Serpro\ProcuradorAuthRequest;
use App\Enums\AuthorCertificateMode;
use App\Enums\AuthorIdentityType;
use App\Enums\SecureObjectPurpose;
use App\Enums\SerproAuthorizationStatus;
use App\Enums\SerproEnvironment;
use App\Enums\TermRePresentationStrategy;
use App\Models\Office;
use App\Models\OfficeSerproAuthorization;
use App\Models\OfficeSerproAuthorizationEvent;
use App\Services\Audit\AuditLogger;
use App\Services\Serpro\SerproContractService;
use RuntimeException;
use Throwable;

/**
 * Onboarding Autor do Pedido / Termo / token do procurador (tenant-scoped).
 * Nunca retorna XML, PFX ou tokens.
 */
final class OfficeSerproAuthorizationService
{
    public function __construct(
        private readonly SecureObjectStore $store,
        private readonly PfxReaderInterface $pfxReader,
        private readonly TermoXmlValidator $termoValidator,
        private readonly AutenticarProcuradorClient $procuradorClient,
        private readonly SerproContractService $contracts,
        private readonly SerproContractAuthenticator $authenticator,
        private readonly AuditLogger $audit,
    ) {}

    public function getOrCreate(Office $office, SerproEnvironment $environment): OfficeSerproAuthorization
    {
        $auth = OfficeSerproAuthorization::query()
            ->where('office_id', $office->id)
            ->where('environment', $environment->value)
            ->first();

        if ($auth !== null) {
            return $auth;
        }

        return OfficeSerproAuthorization::query()->create([
            'office_id' => $office->id,
            'environment' => $environment,
            'status' => SerproAuthorizationStatus::Draft,
            'author_identity_type' => AuthorIdentityType::Cnpj,
            'author_identity' => '00000000000000',
            'certificate_mode' => AuthorCertificateMode::ExternalSignature,
        ]);
    }

    public function configureAuthor(
        Office $office,
        SerproEnvironment $environment,
        AuthorIdentityType $identityType,
        string $identity,
        ?string $authorName = null,
        AuthorCertificateMode $mode = AuthorCertificateMode::ExternalSignature,
        ?int $actorUserId = null,
    ): OfficeSerproAuthorization {
        $identity = $this->normalizeIdentity($identity);
        $this->assertIdentity($identityType, $identity);

        $auth = $this->getOrCreate($office, $environment);
        $from = $auth->status;

        $auth->author_identity_type = $identityType;
        $auth->author_identity = $identity;
        $auth->author_name = $authorName;
        $auth->certificate_mode = $mode;

        if ($auth->status === SerproAuthorizationStatus::Draft) {
            $auth->status = SerproAuthorizationStatus::PendingTerm;
        }

        if ($mode === AuthorCertificateMode::InteractiveA3) {
            // A3 nunca é automatizado
            $auth->managed_a1_consent = false;
        }

        $auth->save();

        $this->recordEvent($auth, $from, $auth->status, 'author.configure', 'Autor configurado.', $actorUserId);

        $this->audit->record('serpro.authorization.author_configure', 'SUCCESS', $auth, [
            'environment' => $environment->value,
            'certificate_mode' => $mode->value,
            'identity_type' => $identityType->value,
        ], $actorUserId, $office->id);

        return $auth->refresh();
    }

    public function uploadTermo(
        Office $office,
        SerproEnvironment $environment,
        string $termoXml,
        ?int $actorUserId = null,
    ): OfficeSerproAuthorization {
        $auth = $this->getOrCreate($office, $environment);

        if ($auth->author_identity === '' || $auth->author_identity === '00000000000000') {
            throw new RuntimeException('Configure a identidade do Autor do Pedido antes do Termo.');
        }

        $destination = (string) config('serpro.termo_destination_cnpj', '');
        $contract = $this->contracts->activeFor($environment);
        if ($destination === '' && $contract !== null) {
            $destination = $contract->contractor_cnpj;
        }

        $validation = $this->termoValidator->validate(
            $termoXml,
            $auth->author_identity,
            $destination,
        );

        if (! $validation->valid) {
            $this->audit->record('serpro.authorization.termo_upload', 'FAILED', $auth, [
                'error_code' => $validation->errorCode,
                'message' => $validation->errorMessage,
            ], $actorUserId, $office->id);

            throw new RuntimeException($validation->errorMessage ?? 'Termo inválido.');
        }

        $aad = SecureObjectPurpose::SerproTermoXml->aadBase([
            'office_id' => $office->id,
            'environment' => $environment->value,
            'sha256' => $validation->sha256,
            'author_identity' => $auth->author_identity,
        ]);

        // Imutável: novo objeto; referência antiga permanece no histórico de vault (não sobrescreve bytes).
        $objectId = $this->store->put($termoXml, $aad);

        $from = $auth->status;
        $auth->termo_vault_object_id = $objectId;
        $auth->termo_sha256 = $validation->sha256;
        $auth->termo_valid_from = $validation->validFrom;
        $auth->termo_valid_to = $validation->validTo;
        $auth->termo_destination_cnpj = $validation->destinationCnpj;
        $auth->termo_signed_by = $validation->signedBy;
        $auth->termo_uploaded_at = now();
        $auth->last_validation_result = 'TERM_VALID';
        $auth->last_validation_message = 'Termo validado (estrutura crítica).';
        $auth->last_validated_at = now();
        $auth->status = SerproAuthorizationStatus::TermValid;
        $auth->action_required_reason = null;
        $auth->save();

        $this->recordEvent($auth, $from, $auth->status, 'termo.upload', 'Termo assinado armazenado.', $actorUserId, [
            'termo_sha256' => $validation->sha256,
            'signature_checked' => $validation->signatureChecked,
        ]);

        $this->audit->record('serpro.authorization.termo_upload', 'SUCCESS', $auth, [
            'termo_sha256' => $validation->sha256,
            'environment' => $environment->value,
        ], $actorUserId, $office->id);

        return $auth->refresh();
    }

    /**
     * A1 gerenciado opcional do Autor — consentimento + purpose exclusivo.
     */
    public function storeManagedAuthorA1(
        Office $office,
        SerproEnvironment $environment,
        string $pfxBinary,
        string $password,
        bool $consent,
        ?int $actorUserId = null,
    ): OfficeSerproAuthorization {
        if (! $consent) {
            throw new RuntimeException('Consentimento explícito é obrigatório para A1 gerenciado.');
        }

        $auth = $this->getOrCreate($office, $environment);
        $meta = $this->pfxReader->read($pfxBinary, $password);

        $holder = $this->normalizeIdentity($meta['cnpj']);
        // A1 de PF pode vir sem CNPJ — PfxReader exige CNPJ; para CPF, comparar author se numérico 11.
        if ($auth->author_identity_type === AuthorIdentityType::Cnpj) {
            if ($holder !== $auth->author_identity) {
                throw new RuntimeException('CNPJ do certificado A1 diverge do Autor configurado.');
            }
        }

        $aad = SecureObjectPurpose::SerproAuthorPfx->aadBase([
            'office_id' => $office->id,
            'environment' => $environment->value,
            'fingerprint' => $meta['fingerprint_sha256'],
            'author_identity' => $auth->author_identity,
        ]);

        $payload = json_encode([
            'pfx' => base64_encode($meta['pfx']),
            'password' => $meta['password'],
        ], JSON_THROW_ON_ERROR);

        $objectId = $this->store->put($payload, $aad);
        $previous = $auth->author_pfx_vault_object_id;

        $auth->certificate_mode = AuthorCertificateMode::ManagedA1;
        $auth->managed_a1_consent = true;
        $auth->managed_a1_consented_at = now();
        $auth->author_pfx_vault_object_id = $objectId;
        $auth->author_fingerprint_sha256 = $meta['fingerprint_sha256'];
        $auth->author_cert_valid_from = $meta['valid_from'];
        $auth->author_cert_valid_to = $meta['valid_to'];
        $auth->save();

        if ($previous !== null && $previous !== $objectId) {
            try {
                $this->store->delete($previous);
            } catch (Throwable) {
            }
        }

        unset($pfxBinary, $password, $meta, $payload);

        $this->audit->record('serpro.authorization.author_a1', 'SUCCESS', $auth, [
            'fingerprint_sha256' => $auth->author_fingerprint_sha256,
            'environment' => $environment->value,
        ], $actorUserId, $office->id);

        return $auth->refresh();
    }

    public function markActionRequired(
        OfficeSerproAuthorization $auth,
        string $reason,
        ?int $actorUserId = null,
    ): OfficeSerproAuthorization {
        $from = $auth->status;
        $auth->status = SerproAuthorizationStatus::ActionRequired;
        $auth->action_required_reason = mb_substr($reason, 0, 500);
        $auth->save();

        $this->recordEvent($auth, $from, $auth->status, 'action_required', $reason, $actorUserId);

        return $auth;
    }

    /**
     * Renova token do procurador conforme estratégia de reapresentação do Termo.
     */
    public function refreshProcuradorToken(
        Office $office,
        SerproEnvironment $environment,
        ?int $actorUserId = null,
    ): OfficeSerproAuthorization {
        $auth = $this->getOrCreate($office, $environment);

        if ($auth->termo_vault_object_id === null) {
            throw new RuntimeException('Termo assinado é obrigatório para obter token do procurador.');
        }

        if ($auth->certificate_mode === AuthorCertificateMode::InteractiveA3) {
            return $this->markActionRequired(
                $auth,
                'Certificado A3 exige assinatura interativa; não é possível renovar automaticamente.',
                $actorUserId,
            );
        }

        // Token ainda válido
        if (
            $auth->procurador_token_vault_object_id !== null
            && $auth->procurador_token_expires_at !== null
            && $auth->procurador_token_expires_at->isFuture()
        ) {
            return $auth;
        }

        $strategy = $this->representationStrategy($environment);

        if (
            $auth->procurador_token_expires_at !== null
            && $auth->procurador_token_expires_at->isPast()
        ) {
            if ($strategy === TermRePresentationStrategy::RequireNewSignature) {
                return $this->markActionRequired(
                    $auth,
                    'Ambiente exige nova assinatura do Termo após expiração do token.',
                    $actorUserId,
                );
            }
            if ($strategy === TermRePresentationStrategy::PendingValidation) {
                return $this->markActionRequired(
                    $auth,
                    'Estratégia de reapresentação do Termo ainda PENDING_VALIDATION neste ambiente.',
                    $actorUserId,
                );
            }
            // REUSE_STORED_TERM: continua
        }

        $termoAad = SecureObjectPurpose::SerproTermoXml->aadBase([
            'office_id' => $office->id,
            'environment' => $environment->value,
            'sha256' => $auth->termo_sha256,
            'author_identity' => $auth->author_identity,
        ]);
        $termoXml = $this->store->get($auth->termo_vault_object_id, $termoAad);

        $contract = $this->contracts->activeFor($environment);
        $allowSimulatedBearer = (bool) config('serpro.trial.use_fake_clients', false);
        $bearer = null;

        if ($contract !== null && $contract->isUsable()) {
            try {
                $token = $this->authenticator->authenticate($contract);
                $bearer = $token->accessToken;
            } catch (Throwable $e) {
                if (! $allowSimulatedBearer) {
                    throw new RuntimeException(
                        'Falha ao autenticar contrato SERPRO para token do procurador.',
                        0,
                        $e,
                    );
                }
                // Trial/fake: permite Bearer simulado apenas com use_fake_clients.
                $bearer = 'SIMULATED';
            }
        } elseif ($allowSimulatedBearer) {
            $bearer = 'SIMULATED';
        } else {
            throw new RuntimeException('Contrato SERPRO indisponível para autenticar procurador.');
        }

        $result = $this->procuradorClient->authenticate(new ProcuradorAuthRequest(
            officeId: $office->id,
            environment: $environment->value,
            authorIdentity: $auth->author_identity,
            termoXml: $termoXml,
            contractorBearerToken: $bearer,
            correlationId: $this->audit->correlationId(),
        ));

        unset($termoXml, $bearer);

        if ($result->requiresNewSignature || (! $result->success && $result->errorCode === 'SIGNATURE_REQUIRED')) {
            return $this->markActionRequired(
                $auth,
                $result->errorMessage ?? 'Nova assinatura do Termo exigida pelo ambiente.',
                $actorUserId,
            );
        }

        if (! $result->success || $result->token === null || $result->expiresAt === null) {
            throw new RuntimeException($result->errorMessage ?? 'Falha ao autenticar procurador.');
        }

        $tokenAad = SecureObjectPurpose::SerproProcuradorToken->aadBase([
            'office_id' => $office->id,
            'environment' => $environment->value,
            'author_identity' => $auth->author_identity,
        ]);

        $tokenPayload = json_encode([
            'token' => $result->token,
            'expires_at' => $result->expiresAt->toIso8601String(),
        ], JSON_THROW_ON_ERROR);

        $objectId = $this->store->put($tokenPayload, $tokenAad);
        $previous = $auth->procurador_token_vault_object_id;

        $from = $auth->status;
        $auth->procurador_token_vault_object_id = $objectId;
        $auth->procurador_token_expires_at = $result->expiresAt;
        $auth->last_token_refresh_at = now();
        $auth->status = SerproAuthorizationStatus::TokenActive;
        $auth->action_required_reason = null;
        $auth->save();

        if ($previous !== null && $previous !== $objectId) {
            try {
                $this->store->delete($previous);
            } catch (Throwable) {
            }
        }

        $this->recordEvent($auth, $from, $auth->status, 'procurador.token_refresh', 'Token do procurador renovado.', $actorUserId, [
            'simulated' => $result->simulated,
            'expires_at' => $result->expiresAt->toIso8601String(),
        ]);

        $this->audit->record('serpro.authorization.token_refresh', 'SUCCESS', $auth, [
            'simulated' => $result->simulated,
            'expires_at' => $result->expiresAt->toIso8601String(),
        ], $actorUserId, $office->id);

        return $auth->refresh();
    }

    public function representationStrategy(SerproEnvironment $environment): TermRePresentationStrategy
    {
        $map = config('serpro.term_representation', []);
        $raw = is_array($map) ? ($map[$environment->value] ?? 'PENDING_VALIDATION') : 'PENDING_VALIDATION';

        return TermRePresentationStrategy::tryFrom((string) $raw)
            ?? TermRePresentationStrategy::PendingValidation;
    }

    private function recordEvent(
        OfficeSerproAuthorization $auth,
        SerproAuthorizationStatus|string|null $from,
        SerproAuthorizationStatus|string $to,
        string $event,
        ?string $message,
        ?int $actorUserId,
        array $context = [],
    ): void {
        OfficeSerproAuthorizationEvent::query()->create([
            'office_id' => $auth->office_id,
            'office_serpro_authorization_id' => $auth->id,
            'from_status' => $from instanceof SerproAuthorizationStatus ? $from->value : $from,
            'to_status' => $to instanceof SerproAuthorizationStatus ? $to->value : $to,
            'event' => $event,
            'message' => $message !== null ? mb_substr($message, 0, 500) : null,
            'actor_user_id' => $actorUserId,
            'context' => $context,
            'created_at' => now(),
        ]);
    }

    private function normalizeIdentity(string $raw): string
    {
        return strtoupper(preg_replace('/[^0-9A-Za-z]/', '', $raw) ?? '');
    }

    private function assertIdentity(AuthorIdentityType $type, string $identity): void
    {
        if ($type === AuthorIdentityType::Cpf && strlen($identity) !== 11) {
            throw new RuntimeException('CPF do Autor deve ter 11 dígitos.');
        }
        if ($type === AuthorIdentityType::Cnpj && strlen($identity) !== 14) {
            throw new RuntimeException('CNPJ do Autor deve ter 14 caracteres.');
        }
    }
}
