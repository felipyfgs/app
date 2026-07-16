<?php

namespace App\Services\Serpro;

use App\Contracts\SecureObjectStore;
use App\Contracts\SerproContractAuthenticator;
use App\Domain\Cnpj;
use App\Enums\SecureObjectPurpose;
use App\Enums\SerproCredentialVersionStatus;
use App\Enums\SerproDataSegregationClass;
use App\Enums\SerproEnvironment;
use App\Models\SerproContract;
use App\Models\SerproCredentialApproval;
use App\Models\SerproCredentialVersion;
use App\Services\Audit\AuditLogger;
use App\Services\Certificates\ContractorPfxValidator;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Ciclo de vida de versões de credencial SERPRO (sem expor segredos).
 * PENDING → VERIFIED → ACTIVE → RETIRED|COMPROMISED
 */
final class SerproCredentialVersionService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly SecureObjectStore $store,
        private readonly ContractorPfxValidator $pfxValidator,
        private readonly SerproTokenCache $tokenCache,
    ) {}

    public function nextVersionNumber(SerproEnvironment $environment): int
    {
        $max = (int) SerproCredentialVersion::query()
            ->where('environment', $environment->value)
            ->max('version_number');

        return $max + 1;
    }

    /**
     * Cadastra versão PENDING com Key/Secret/PFX no vault.
     * Retorna somente metadados sanitizados via model; nunca reexibe segredo.
     */
    public function registerPending(
        SerproEnvironment $environment,
        string $pfxBinary,
        string $password,
        string $consumerKey,
        string $consumerSecret,
        ?SerproContract $contract = null,
        ?string $notes = null,
        ?int $actorUserId = null,
        ?string $expectedCnpj = null,
    ): SerproCredentialVersion {
        $consumerKey = trim($consumerKey);
        $consumerSecret = trim($consumerSecret);
        if ($consumerKey === '' || $consumerSecret === '') {
            throw new RuntimeException('Consumer Key e Consumer Secret são obrigatórios.');
        }

        $expected = $expectedCnpj
            ?? $contract?->contractor_cnpj
            ?? null;

        $meta = $this->pfxValidator->validate(
            $pfxBinary,
            $password,
            $expected,
            (int) config('serpro.contractor_pfx.min_horizon_days', 7),
            (bool) config('serpro.contractor_pfx.require_chain', false),
        );

        $holder = Cnpj::parse($meta['cnpj']);
        $versionNumber = $this->nextVersionNumber($environment);

        $pfxAad = SecureObjectPurpose::SerproContractorPfx->aadBase([
            'environment' => $environment->value,
            'fingerprint' => $meta['fingerprint_sha256'],
            'contractor_cnpj' => $holder->value(),
            'credential_version' => $versionNumber,
        ]);

        $pfxPayload = json_encode([
            'pfx' => base64_encode($pfxBinary),
            'password' => $password,
        ], JSON_THROW_ON_ERROR);

        $oauthAad = SecureObjectPurpose::SerproOauthSecrets->aadBase([
            'environment' => $environment->value,
            'contractor_cnpj' => $holder->value(),
            'credential_version' => $versionNumber,
        ]);

        $oauthPayload = json_encode([
            'consumer_key' => $consumerKey,
            'consumer_secret' => $consumerSecret,
        ], JSON_THROW_ON_ERROR);

        $pfxObjectId = $this->store->put($pfxPayload, $pfxAad);
        $oauthObjectId = null;

        try {
            $oauthObjectId = $this->store->put($oauthPayload, $oauthAad);

            $version = SerproCredentialVersion::query()->create([
                'serpro_contract_id' => $contract?->id,
                'environment' => $environment,
                'version_number' => $versionNumber,
                'status' => SerproCredentialVersionStatus::Pending,
                'was_exposed' => false,
                'consumer_key_hint' => $this->hint($consumerKey),
                'fingerprint_sha256' => $meta['fingerprint_sha256'],
                'contractor_cnpj' => $holder->value(),
                'subject_name' => $meta['subject_name'],
                'cert_valid_from' => $meta['valid_from'],
                'cert_valid_to' => $meta['valid_to'],
                'pfx_vault_object_id' => $pfxObjectId,
                'oauth_vault_object_id' => $oauthObjectId,
                'segregation_class' => SerproDataSegregationClass::HistoricalUnverified,
                'metadata' => [
                    'pfx' => $this->pfxValidator->toSanitizedMetadata($meta),
                ],
                'notes' => $notes,
            ]);
        } catch (Throwable $e) {
            $this->safeDelete($pfxObjectId);
            if ($oauthObjectId !== null) {
                $this->safeDelete($oauthObjectId);
            }
            throw $e;
        } finally {
            unset($pfxBinary, $password, $consumerSecret, $pfxPayload, $oauthPayload, $meta);
        }

        $this->audit->record('serpro.credential.register_pending', 'SUCCESS', $version, [
            'environment' => $environment->value,
            'version_number' => $version->version_number,
            'fingerprint_sha256' => $version->fingerprint_sha256,
        ], $actorUserId, null);

        return $version;
    }

    /**
     * Verifica versão PENDING: relê vault, revalida PFX, marca VERIFIED.
     * Não executa OAuth faturável de negócio; prova de leitura do cofre apenas.
     */
    public function verifyPending(
        SerproCredentialVersion $version,
        ?int $actorUserId = null,
    ): SerproCredentialVersion {
        if ($version->status !== SerproCredentialVersionStatus::Pending) {
            throw new RuntimeException('Somente versões PENDING podem ser verificadas.');
        }

        if ($version->pfx_vault_object_id === null || $version->oauth_vault_object_id === null) {
            throw new RuntimeException('Versão sem material no cofre.');
        }

        $pfx = $this->loadPfxMaterial($version);
        $oauth = $this->loadOauthSecrets($version);

        if ($oauth['consumer_key'] === '' || $oauth['consumer_secret'] === '') {
            throw new RuntimeException('OAuth do cofre incompleto.');
        }

        $meta = $this->pfxValidator->validate(
            $pfx['pfx'],
            $pfx['password'],
            $version->contractor_cnpj,
            (int) config('serpro.contractor_pfx.min_horizon_days', 7),
            (bool) config('serpro.contractor_pfx.require_chain', false),
        );

        if (! hash_equals((string) $version->fingerprint_sha256, $meta['fingerprint_sha256'])) {
            throw new RuntimeException('Fingerprint do PFX no cofre diverge do registrado.');
        }

        unset($pfx, $oauth, $meta);

        $version->forceFill([
            'status' => SerproCredentialVersionStatus::Verified,
            'verified_at' => now(),
            'verified_by_user_id' => $actorUserId,
        ])->save();

        $this->audit->record('serpro.credential.verified', 'SUCCESS', $version, [
            'environment' => $version->environment->value,
            'version_number' => $version->version_number,
        ], $actorUserId, null);

        return $version->refresh();
    }

    /**
     * Cutover atômico: exige N aprovações distintas + TOTP, OAuth prévio da versão,
     * invalida tokens/caches e retira a ACTIVE anterior.
     *
     * @param  callable(SerproContract): mixed|null  $oauthProbe
     *                                                            Injetável para testes; default usa SerproContractAuthenticator.
     */
    public function cutover(
        SerproCredentialVersion $version,
        ?SerproContract $contract = null,
        ?int $actorUserId = null,
        ?callable $oauthProbe = null,
        bool $skipOauth = false,
    ): SerproCredentialVersion {
        if ($version->status !== SerproCredentialVersionStatus::Verified) {
            throw new RuntimeException('Cutover exige versão VERIFIED.');
        }

        // skip_oauth só em local/testing — produção exige probe OAuth/mTLS real.
        if ($skipOauth && ! app()->environment(['local', 'testing'])) {
            throw new RuntimeException(
                'skip_oauth não é permitido fora de local/testing; execute cutover com probe OAuth.'
            );
        }

        $required = max(2, (int) config('serpro.contractor_pfx.cutover_approvals_required', 2));
        $approvers = $this->distinctApprovers($version, 'CUTOVER');
        if ($approvers < $required) {
            throw new RuntimeException(
                "Cutover exige {$required} aprovadores PLATFORM_ADMIN distintos com TOTP; há {$approvers}."
            );
        }

        $contract = $contract
            ?? ($version->serpro_contract_id
                ? SerproContract::query()->find($version->serpro_contract_id)
                : null);

        if ($contract === null) {
            throw new RuntimeException('Cutover exige contrato vinculado à versão.');
        }

        return DB::transaction(function () use ($version, $contract, $actorUserId, $oauthProbe, $skipOauth): SerproCredentialVersion {
            $locked = SerproCredentialVersion::query()->whereKey($version->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== SerproCredentialVersionStatus::Verified) {
                throw new RuntimeException('Estado da versão mudou durante o cutover.');
            }

            $contractLocked = SerproContract::query()->whereKey($contract->id)->lockForUpdate()->firstOrFail();

            // Snapshot do material atual do contrato para rollback se OAuth falhar.
            $previousPfx = $contractLocked->pfx_vault_object_id;
            $previousOauth = $contractLocked->oauth_vault_object_id;
            $previousToken = $contractLocked->token_vault_object_id;
            $previousFingerprint = $contractLocked->fingerprint_sha256;
            $previousCnpj = $contractLocked->contractor_cnpj;
            $previousSubject = $contractLocked->subject_name;
            $previousCertFrom = $contractLocked->cert_valid_from;
            $previousCertTo = $contractLocked->cert_valid_to;
            $previousHint = $contractLocked->consumer_key_hint;

            // Re-sela material da versão com AAD do contrato (sem credential_version)
            // para o autenticador canônico e loaders existentes.
            $contractPfxId = $this->resealVersionMaterialForContract($locked, $contractLocked);
            $contractOauthId = $contractPfxId['oauth'];
            $contractPfxObjectId = $contractPfxId['pfx'];

            $contractLocked->forceFill([
                'pfx_vault_object_id' => $contractPfxObjectId,
                'oauth_vault_object_id' => $contractOauthId,
                'fingerprint_sha256' => $locked->fingerprint_sha256,
                'contractor_cnpj' => $locked->contractor_cnpj,
                'subject_name' => $locked->subject_name,
                'cert_valid_from' => $locked->cert_valid_from,
                'cert_valid_to' => $locked->cert_valid_to,
                'consumer_key_hint' => $locked->consumer_key_hint,
                'token_vault_object_id' => null,
                'token_expires_at' => null,
            ])->save();

            // Invalida caches/tokens da versão anterior no contrato.
            $this->tokenCache->invalidate($contractLocked->refresh());

            if (! $skipOauth) {
                try {
                    if ($oauthProbe !== null) {
                        $oauthProbe($contractLocked);
                    } else {
                        app(SerproContractAuthenticator::class)->authenticate($contractLocked);
                    }
                } catch (Throwable $e) {
                    // Rollback material do contrato para o estado anterior.
                    $this->safeDelete($contractPfxObjectId);
                    $this->safeDelete($contractOauthId);
                    $contractLocked->forceFill([
                        'pfx_vault_object_id' => $previousPfx,
                        'oauth_vault_object_id' => $previousOauth,
                        'token_vault_object_id' => $previousToken,
                        'fingerprint_sha256' => $previousFingerprint,
                        'contractor_cnpj' => $previousCnpj,
                        'subject_name' => $previousSubject,
                        'cert_valid_from' => $previousCertFrom,
                        'cert_valid_to' => $previousCertTo,
                        'consumer_key_hint' => $previousHint,
                    ])->save();

                    throw new RuntimeException(
                        'OAuth pré-cutover falhou; versão permanece VERIFIED e ACTIVE anterior intacta.',
                        0,
                        $e,
                    );
                }
            }

            $env = $locked->environment instanceof SerproEnvironment
                ? $locked->environment
                : SerproEnvironment::from((string) $locked->environment);

            $previousActive = SerproCredentialVersion::query()
                ->where('environment', $env->value)
                ->where('status', SerproCredentialVersionStatus::Active->value)
                ->where('id', '!=', $locked->id)
                ->lockForUpdate()
                ->get();

            foreach ($previousActive as $old) {
                $old->forceFill([
                    'status' => SerproCredentialVersionStatus::Retired,
                    'retired_at' => now(),
                    'token_vault_object_id' => null,
                    'token_expires_at' => null,
                    'notes' => trim((string) $old->notes."\nRetirada segura no cutover da v{$locked->version_number}."),
                ])->save();

                $this->audit->record('serpro.credential.retired', 'SUCCESS', $old, [
                    'environment' => $env->value,
                    'version_number' => $old->version_number,
                    'reason' => 'cutover',
                    'replaced_by' => $locked->id,
                ], $actorUserId, null);
            }

            $locked->forceFill([
                'status' => SerproCredentialVersionStatus::Active,
                'activated_at' => now(),
                'activated_by_user_id' => $actorUserId,
                'serpro_contract_id' => $contractLocked->id,
            ])->save();

            $contractLocked->forceFill([
                'active_credential_version_id' => $locked->id,
                'credentials_exposed' => false,
                'health_status' => 'OK',
                'health_message' => 'Cutover de credencial concluído.',
                'last_verified_at' => now(),
            ])->save();

            $this->audit->record('serpro.credential.cutover', 'SUCCESS', $locked, [
                'environment' => $env->value,
                'version_number' => $locked->version_number,
                'retired_count' => $previousActive->count(),
                'approvers' => $this->distinctApprovers($locked, 'CUTOVER'),
            ], $actorUserId, null);

            return $locked->refresh();
        });
    }

    /**
     * @return array{pfx: string, password: string}
     */
    public function loadPfxMaterial(SerproCredentialVersion $version): array
    {
        if ($version->pfx_vault_object_id === null) {
            throw new RuntimeException('Versão sem PFX no cofre.');
        }

        $aad = SecureObjectPurpose::SerproContractorPfx->aadBase([
            'environment' => $version->environment->value,
            'fingerprint' => $version->fingerprint_sha256,
            'contractor_cnpj' => $version->contractor_cnpj,
            'credential_version' => $version->version_number,
        ]);

        $json = $this->store->get($version->pfx_vault_object_id, $aad);
        /** @var array{pfx: string, password: string} $data */
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        $pfx = base64_decode((string) ($data['pfx'] ?? ''), true);
        if ($pfx === false || $pfx === '') {
            throw new RuntimeException('Material PFX da versão corrompido no cofre.');
        }

        return [
            'pfx' => $pfx,
            'password' => (string) ($data['password'] ?? ''),
        ];
    }

    /**
     * @return array{consumer_key: string, consumer_secret: string}
     */
    public function loadOauthSecrets(SerproCredentialVersion $version): array
    {
        if ($version->oauth_vault_object_id === null) {
            throw new RuntimeException('Versão sem OAuth no cofre.');
        }

        $aad = SecureObjectPurpose::SerproOauthSecrets->aadBase([
            'environment' => $version->environment->value,
            'contractor_cnpj' => $version->contractor_cnpj,
            'credential_version' => $version->version_number,
        ]);

        $json = $this->store->get($version->oauth_vault_object_id, $aad);
        /** @var array{consumer_key: string, consumer_secret: string} $data */
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        return [
            'consumer_key' => (string) ($data['consumer_key'] ?? ''),
            'consumer_secret' => (string) ($data['consumer_secret'] ?? ''),
        ];
    }

    /**
     * Marca material atual do contrato como versão exposta (contenção P0).
     * Não apaga histórico; não promove estados por inferência.
     */
    public function markContractCredentialsExposed(
        SerproContract $contract,
        string $reason,
        ?int $actorUserId = null,
    ): SerproCredentialVersion {
        return DB::transaction(function () use ($contract, $reason, $actorUserId): SerproCredentialVersion {
            $env = $contract->environment instanceof SerproEnvironment
                ? $contract->environment
                : SerproEnvironment::from((string) $contract->environment);

            $existing = SerproCredentialVersion::query()
                ->where('serpro_contract_id', $contract->id)
                ->where('was_exposed', true)
                ->orderByDesc('id')
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            $version = SerproCredentialVersion::query()->create([
                'serpro_contract_id' => $contract->id,
                'environment' => $env,
                'version_number' => $this->nextVersionNumber($env),
                'status' => SerproCredentialVersionStatus::Active,
                'was_exposed' => true,
                'exposure_reason' => mb_substr($reason, 0, 500),
                'exposed_at' => now(),
                'consumer_key_hint' => $contract->consumer_key_hint,
                'fingerprint_sha256' => $contract->fingerprint_sha256,
                'contractor_cnpj' => $contract->contractor_cnpj,
                'subject_name' => $contract->subject_name,
                'cert_valid_from' => $contract->cert_valid_from,
                'cert_valid_to' => $contract->cert_valid_to,
                'pfx_vault_object_id' => $contract->pfx_vault_object_id,
                'oauth_vault_object_id' => $contract->oauth_vault_object_id,
                'token_vault_object_id' => $contract->token_vault_object_id,
                'token_expires_at' => $contract->token_expires_at,
                'activated_at' => $contract->activated_at ?? now(),
                'segregation_class' => SerproDataSegregationClass::HistoricalUnverified,
                'notes' => 'Marcação de contenção: credencial considerada exposta.',
            ]);

            $contract->forceFill([
                'credentials_exposed' => true,
                'active_credential_version_id' => $version->id,
                'segregation_class' => SerproDataSegregationClass::HistoricalUnverified->value,
            ])->save();

            $this->audit->record('serpro.credential.mark_exposed', 'SUCCESS', $version, [
                'environment' => $env->value,
                'version_number' => $version->version_number,
                'reason' => mb_substr($reason, 0, 200),
            ], $actorUserId, null);

            return $version;
        });
    }

    public function markCompromised(
        SerproCredentialVersion $version,
        string $reason,
        ?int $actorUserId = null,
    ): SerproCredentialVersion {
        if ($version->status === SerproCredentialVersionStatus::Compromised) {
            return $version;
        }

        $version->forceFill([
            'status' => SerproCredentialVersionStatus::Compromised,
            'compromised_at' => now(),
            'was_exposed' => true,
            'exposure_reason' => mb_substr($reason, 0, 500),
            'exposed_at' => $version->exposed_at ?? now(),
            'token_vault_object_id' => null,
            'token_expires_at' => null,
        ])->save();

        $this->audit->record('serpro.credential.compromised', 'SUCCESS', $version, [
            'environment' => $version->environment->value,
            'version_number' => $version->version_number,
            'reason' => mb_substr($reason, 0, 200),
        ], $actorUserId, null);

        return $version->refresh();
    }

    public function markRetired(
        SerproCredentialVersion $version,
        string $reason,
        ?int $actorUserId = null,
    ): SerproCredentialVersion {
        if ($version->status === SerproCredentialVersionStatus::Retired) {
            return $version;
        }

        if ($version->status === SerproCredentialVersionStatus::Compromised) {
            throw new RuntimeException('Versão COMPROMISED não pode ser marcada RETIRED.');
        }

        $version->forceFill([
            'status' => SerproCredentialVersionStatus::Retired,
            'retired_at' => now(),
            'token_vault_object_id' => null,
            'token_expires_at' => null,
            'notes' => trim((string) $version->notes."\n".$reason),
        ])->save();

        $this->audit->record('serpro.credential.retired', 'SUCCESS', $version, [
            'environment' => $version->environment->value,
            'version_number' => $version->version_number,
            'reason' => mb_substr($reason, 0, 200),
        ], $actorUserId, null);

        return $version->refresh();
    }

    /**
     * Registra um dos dois olhos de aprovação (cutover / retire / compromise).
     */
    public function recordApproval(
        SerproCredentialVersion $version,
        string $action,
        int $approverUserId,
        bool $totpVerified,
        string $decision,
        ?string $reason = null,
    ): SerproCredentialApproval {
        if (! $totpVerified) {
            throw new RuntimeException('Aprovação de credencial SERPRO exige TOTP verificado.');
        }

        return SerproCredentialApproval::query()->create([
            'serpro_credential_version_id' => $version->id,
            'action' => strtoupper($action),
            'approver_user_id' => $approverUserId,
            'approver_role' => 'PLATFORM_ADMIN',
            'totp_verified' => true,
            'decision' => strtoupper($decision),
            'reason' => $reason !== null ? mb_substr($reason, 0, 500) : null,
            'decided_at' => now(),
        ]);
    }

    /**
     * Contagem de aprovadores distintos para uma ação.
     */
    public function distinctApprovers(SerproCredentialVersion $version, string $action): int
    {
        return SerproCredentialApproval::query()
            ->where('serpro_credential_version_id', $version->id)
            ->where('action', strtoupper($action))
            ->where('decision', 'APPROVE')
            ->where('totp_verified', true)
            ->pluck('approver_user_id')
            ->unique()
            ->count();
    }

    /**
     * Copia material da versão (AAD com credential_version) para AAD de contrato.
     *
     * @return array{pfx: string, oauth: string}
     */
    private function resealVersionMaterialForContract(
        SerproCredentialVersion $version,
        SerproContract $contract,
    ): array {
        $pfx = $this->loadPfxMaterial($version);
        $oauth = $this->loadOauthSecrets($version);

        $env = $version->environment instanceof SerproEnvironment
            ? $version->environment->value
            : (string) $version->environment;

        $pfxAad = SecureObjectPurpose::SerproContractorPfx->aadBase([
            'environment' => $env,
            'fingerprint' => $version->fingerprint_sha256,
            'contractor_cnpj' => $version->contractor_cnpj,
        ]);
        $oauthAad = SecureObjectPurpose::SerproOauthSecrets->aadBase([
            'environment' => $env,
            'contractor_cnpj' => $version->contractor_cnpj,
        ]);

        $pfxPayload = json_encode([
            'pfx' => base64_encode($pfx['pfx']),
            'password' => $pfx['password'],
        ], JSON_THROW_ON_ERROR);
        $oauthPayload = json_encode([
            'consumer_key' => $oauth['consumer_key'],
            'consumer_secret' => $oauth['consumer_secret'],
        ], JSON_THROW_ON_ERROR);

        $pfxId = $this->store->put($pfxPayload, $pfxAad);
        try {
            $oauthId = $this->store->put($oauthPayload, $oauthAad);
        } catch (Throwable $e) {
            $this->safeDelete($pfxId);
            throw $e;
        } finally {
            unset($pfx, $oauth, $pfxPayload, $oauthPayload);
        }

        return ['pfx' => $pfxId, 'oauth' => $oauthId];
    }

    private function hint(string $consumerKey): string
    {
        $len = strlen($consumerKey);
        if ($len <= 4) {
            return str_repeat('*', $len);
        }

        return substr($consumerKey, 0, 2).str_repeat('*', min(8, $len - 4)).substr($consumerKey, -2);
    }

    private function safeDelete(string $objectId): void
    {
        try {
            $this->store->delete($objectId);
        } catch (Throwable $e) {
            report(new RuntimeException('Falha ao compensar objeto de credencial no cofre.', 0, $e));
        }
    }
}
