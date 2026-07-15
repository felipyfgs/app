<?php

namespace App\Services\Certificates;

use App\Contracts\PfxReaderInterface;
use App\Contracts\SecureObjectStore;
use App\Domain\Cnpj;
use App\Enums\CredentialStatus;
use App\Enums\OfficeCredentialPurpose;
use App\Enums\SyncCursorStatus;
use App\Models\OfficeCredential;
use App\Models\OfficeDistributionCursor;
use App\Models\OfficeFiscalIdentity;
use App\Support\CurrentOffice;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Ciclo de vida do A1 do escritório (finalidade NFE_AUTXML_DISTDFE).
 * Nunca materializa PEM em disco; PFX só em memória via vault.
 */
final class OfficeCredentialService
{
    public function __construct(
        private readonly SecureObjectStore $store,
        private readonly PfxReaderInterface $pfxReader,
        private readonly CurrentOffice $currentOffice,
    ) {}

    public function activate(
        OfficeFiscalIdentity $identity,
        string $pfxBinary,
        string $password,
        OfficeCredentialPurpose $purpose = OfficeCredentialPurpose::NfeAutXmlDistDfe,
    ): OfficeCredential {
        $officeId = $this->currentOffice->office()->id;
        if ($identity->office_id !== $officeId) {
            abort(404);
        }

        $meta = $this->pfxReader->read($pfxBinary, $password);
        $holder = Cnpj::parse($meta['cnpj']);

        // RV 593: raiz do certificado deve coincidir com a identidade do escritório.
        if ($holder->root() !== $identity->root_cnpj) {
            throw new RuntimeException(
                'A raiz do CNPJ do certificado diverge da identidade fiscal do escritório (equivalente à RV 593).'
            );
        }

        $payload = json_encode([
            'pfx' => base64_encode($meta['pfx']),
            'password' => $meta['password'],
        ], JSON_THROW_ON_ERROR);

        $aad = [
            'office_id' => $officeId,
            'office_fiscal_identity_id' => $identity->id,
            'purpose' => $purpose->value,
            'fingerprint' => $meta['fingerprint_sha256'],
        ];

        $objectId = $this->store->put($payload, $aad);
        $superseded = [];

        try {
            $credential = DB::transaction(function () use (
                $identity,
                $meta,
                $objectId,
                $officeId,
                $holder,
                $purpose,
                &$superseded,
            ): OfficeCredential {
                OfficeFiscalIdentity::query()->whereKey($identity->id)->lockForUpdate()->firstOrFail();

                $previous = OfficeCredential::query()
                    ->where('office_fiscal_identity_id', $identity->id)
                    ->where('purpose', $purpose->value)
                    ->where('status', CredentialStatus::Active)
                    ->lockForUpdate()
                    ->get();

                foreach ($previous as $old) {
                    $superseded[] = [
                        'id' => $old->id,
                        'object_id' => $old->vault_object_id,
                    ];
                    $old->status = CredentialStatus::Superseded;
                    $old->superseded_at = now();
                    $old->save();
                }

                return OfficeCredential::query()->create([
                    'office_id' => $officeId,
                    'office_fiscal_identity_id' => $identity->id,
                    'purpose' => $purpose,
                    'status' => CredentialStatus::Active,
                    'subject_name' => $meta['subject_name'],
                    'holder_cnpj' => $holder->value(),
                    'fingerprint_sha256' => $meta['fingerprint_sha256'],
                    'valid_from' => $meta['valid_from'],
                    'valid_to' => $meta['valid_to'],
                    'vault_object_id' => $objectId,
                    'activated_at' => now(),
                ]);
            });
        } catch (Throwable $e) {
            try {
                $this->store->delete($objectId);
            } catch (Throwable $cleanupError) {
                report(new RuntimeException('Falha ao compensar objeto de credencial do escritório.', 0, $cleanupError));
            }

            throw $e;
        }

        foreach ($superseded as $old) {
            $this->invalidateSupersededObject($old['id'], $old['object_id']);
        }

        return $credential;
    }

    public function activeFor(
        OfficeFiscalIdentity $identity,
        OfficeCredentialPurpose $purpose = OfficeCredentialPurpose::NfeAutXmlDistDfe,
    ): ?OfficeCredential {
        return OfficeCredential::query()
            ->where('office_fiscal_identity_id', $identity->id)
            ->where('purpose', $purpose->value)
            ->where('status', CredentialStatus::Active)
            ->first();
    }

    public function revoke(OfficeCredential $credential, string $reason = 'Revogada pelo administrador.'): void
    {
        $officeId = $this->currentOffice->office()->id;
        if ($credential->office_id !== $officeId) {
            abort(404);
        }

        if ($credential->status === CredentialStatus::Revoked) {
            return;
        }

        $credential->status = CredentialStatus::Revoked;
        $credential->superseded_at = now();
        $credential->save();

        $this->blockAutXmlCursorsForIdentity(
            (int) $credential->office_fiscal_identity_id,
            $reason,
        );
    }

    /**
     * Material sensível apenas em memória — nunca expor via API.
     *
     * @return array{pfx: string, password: string}|null
     */
    public function loadPfxMaterial(OfficeCredential $credential): ?array
    {
        if (! $credential->status->isUsable()) {
            return null;
        }

        if ($credential->purpose !== OfficeCredentialPurpose::NfeAutXmlDistDfe) {
            return null;
        }

        if ($credential->valid_to->isPast()) {
            $credential->status = CredentialStatus::Expired;
            $credential->save();
            $this->blockAutXmlCursorsForIdentity(
                (int) $credential->office_fiscal_identity_id,
                'Credencial A1 do escritório expirada.',
            );

            return null;
        }

        $aad = [
            'office_id' => $credential->office_id,
            'office_fiscal_identity_id' => $credential->office_fiscal_identity_id,
            'purpose' => $credential->purpose->value,
            'fingerprint' => $credential->fingerprint_sha256,
        ];

        $json = $this->store->get($credential->vault_object_id, $aad);
        /** @var array{pfx: string, password: string} $data */
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        $pfx = base64_decode((string) ($data['pfx'] ?? ''), true);
        if ($pfx === false || $pfx === '') {
            throw new RuntimeException('Material PFX do escritório corrompido no cofre.');
        }

        $credential->last_used_at = now();
        $credential->save();

        return [
            'pfx' => $pfx,
            'password' => (string) ($data['password'] ?? ''),
        ];
    }

    /**
     * @return array{credentials: int, cursors_blocked: int}
     */
    public function refreshExpiryAlerts(): array
    {
        $credentialsUpdated = 0;
        $cursorsBlocked = 0;
        $now = now();

        OfficeCredential::query()
            ->where('status', CredentialStatus::Active)
            ->orderBy('id')
            ->chunkById(100, function ($rows) use ($now, &$credentialsUpdated, &$cursorsBlocked): void {
                foreach ($rows as $credential) {
                    /** @var OfficeCredential $credential */
                    if ($credential->valid_to->isPast()) {
                        $credential->status = CredentialStatus::Expired;
                        $credential->save();
                        $credentialsUpdated++;
                        $cursorsBlocked += $this->blockAutXmlCursorsForIdentity(
                            (int) $credential->office_fiscal_identity_id,
                            'Credencial A1 do escritório expirada.',
                        );

                        continue;
                    }

                    $days = (int) floor($now->floatDiffInRealDays($credential->valid_to, false));
                    $changed = false;
                    if ($days <= 30 && ! $credential->expires_alert_30) {
                        $credential->expires_alert_30 = true;
                        $changed = true;
                    }
                    if ($days <= 7 && ! $credential->expires_alert_7) {
                        $credential->expires_alert_7 = true;
                        $changed = true;
                    }
                    if ($days <= 1 && ! $credential->expires_alert_1) {
                        $credential->expires_alert_1 = true;
                        $changed = true;
                    }
                    if ($changed) {
                        $credential->save();
                        $credentialsUpdated++;
                    }
                }
            });

        return ['credentials' => $credentialsUpdated, 'cursors_blocked' => $cursorsBlocked];
    }

    private function blockAutXmlCursorsForIdentity(int $identityId, string $reason): int
    {
        $blocked = 0;
        OfficeDistributionCursor::query()
            ->where('office_fiscal_identity_id', $identityId)
            ->whereNot('status', SyncCursorStatus::Blocked)
            ->each(function (OfficeDistributionCursor $cursor) use ($reason, &$blocked): void {
                $cursor->status = SyncCursorStatus::Blocked;
                $cursor->last_error = mb_substr($reason, 0, 500);
                $cursor->save();
                $blocked++;
            });

        return $blocked;
    }

    private function invalidateSupersededObject(int $credentialId, string $objectId): void
    {
        try {
            $this->store->delete($objectId);
        } catch (Throwable $e) {
            report(new RuntimeException(
                "Falha ao invalidar vault de credencial de escritório supersedida #{$credentialId}.",
                0,
                $e,
            ));
        }
    }
}
