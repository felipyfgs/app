<?php

namespace App\Services\Outbound;

use App\Contracts\SecureObjectStore;
use App\Models\OutboundCaptureProfile;
use App\Services\Audit\AuditLogger;
use RuntimeException;

/**
 * Grava/substitui CSC e ID CSC no cofre por estabelecimento+ambiente.
 * Nunca expõe o valor do CSC via retorno ou log.
 */
final class CscVaultService
{
    public function __construct(
        private readonly SecureObjectStore $store,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @return array{configured: bool, csc_id: ?string, configured_at: ?string}
     */
    public function storeCsc(
        OutboundCaptureProfile $profile,
        string $cscToken,
        string $cscId,
        int $userId,
    ): array {
        $cscToken = trim($cscToken);
        $cscId = strtoupper(trim($cscId));

        if ($cscToken === '' || $cscId === '') {
            throw new RuntimeException('CSC e ID CSC são obrigatórios.');
        }

        if ($profile->model->value !== '65') {
            throw new RuntimeException('CSC só se aplica ao modelo 65 (NFC-e).');
        }

        $metadata = [
            'office_id' => $profile->office_id,
            'establishment_id' => $profile->establishment_id,
            'environment' => $profile->environment,
            'kind' => 'csc',
        ];

        $objectId = $this->store->put($cscToken, $metadata);

        // Remove objeto anterior se existir (substituição)
        $previous = $profile->csc_vault_object_id;
        if ($previous !== null && $previous !== $objectId) {
            try {
                $this->store->delete($previous);
            } catch (\Throwable) {
                // não bloquear substituição
            }
        }

        $profile->forceFill([
            'csc_vault_object_id' => $objectId,
            'csc_id' => $cscId,
            'csc_configured' => true,
            'csc_configured_at' => now(),
        ])->save();

        $this->audit->record(
            'outbound.csc.replaced',
            'SUCCESS',
            $profile,
            [
                'profile_id' => $profile->id,
                'establishment_id' => $profile->establishment_id,
                'environment' => $profile->environment,
                'csc_id' => $cscId,
                'configured' => true,
            ],
            $userId,
            $profile->office_id,
        );

        return $profile->cscPublicState();
    }

    /**
     * Materializa CSC em memória — somente para fallback mutante modelo 65 aprovado.
     * Chamador deve limpar a string o quanto antes.
     */
    public function materializeCsc(OutboundCaptureProfile $profile): ?string
    {
        if (! $profile->csc_configured || $profile->csc_vault_object_id === null) {
            return null;
        }

        $metadata = [
            'office_id' => $profile->office_id,
            'establishment_id' => $profile->establishment_id,
            'environment' => $profile->environment,
            'kind' => 'csc',
        ];

        return $this->store->get($profile->csc_vault_object_id, $metadata);
    }
}
