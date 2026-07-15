<?php

namespace App\Services\Sefaz;

use App\Enums\OfficeRole;
use App\Enums\QuarantineReason;
use App\Enums\QuarantineResolutionStatus;
use App\Models\FiscalDocumentQuarantine;
use App\Models\User;
use App\Support\CurrentOffice;
use RuntimeException;

/**
 * Resolução de quarentena fiscal — sem XML bruto, sem promoção cega de bytes divergentes.
 */
final class FiscalDocumentQuarantineService
{
    /**
     * @return list<FiscalDocumentQuarantine>
     */
    public function listOpen(int $officeId, ?string $reason = null, int $limit = 50): array
    {
        $q = FiscalDocumentQuarantine::query()
            ->where('office_id', $officeId)
            ->where('resolution_status', QuarantineResolutionStatus::Open)
            ->orderByDesc('id')
            ->limit(min(100, max(1, $limit)));

        if ($reason !== null && $reason !== '') {
            $q->where('reason', strtoupper($reason));
        }

        return $q->get()->all();
    }

    public function resolve(
        FiscalDocumentQuarantine $item,
        User $actor,
        OfficeRole $role,
        string $resolutionStatus,
        ?string $code = null,
        ?string $notes = null,
    ): FiscalDocumentQuarantine {
        if ((int) $item->office_id !== (int) app(CurrentOffice::class)->id()) {
            throw new RuntimeException('Quarentena não pertence ao escritório da sessão.');
        }

        if ($item->resolution_status !== QuarantineResolutionStatus::Open) {
            throw new RuntimeException('Item de quarentena já resolvido.');
        }

        $status = QuarantineResolutionStatus::tryFrom(strtoupper($resolutionStatus));
        if ($status === null || $status === QuarantineResolutionStatus::Open) {
            throw new RuntimeException('Status de resolução inválido (use RESOLVED ou DISMISSED).');
        }

        // BYTES_DIVERGE: nunca aceitar substituir canônico — só dismiss/ack com motivo
        if (
            $item->reason === QuarantineReason::BytesDiverge
            && $status === QuarantineResolutionStatus::Resolved
            && ($code === null || strtoupper($code) === 'ACCEPT_BYTES')
        ) {
            throw new RuntimeException(
                'Conflito de bytes: não é permitido aceitar o candidato. Use DISMISSED com motivo auditável ou resolva na origem.'
            );
        }

        // Mutações de resolução: OPERATOR+ ; 2FA de ADMIN já é middleware de grupo.
        if (! $role->canManageClients()) {
            throw new RuntimeException('Perfil sem permissão para resolver quarentena.');
        }

        $item->resolution_status = $status;
        $item->resolved_by = $actor->id;
        $item->resolved_at = now();
        $item->resolution_code = $code !== null ? mb_substr(strtoupper($code), 0, 64) : $status->value;
        $item->resolution_notes = $notes !== null
            ? mb_substr(preg_replace('/\s+/', ' ', trim($notes)) ?? '', 0, 500)
            : null;
        $item->save();

        return $item->fresh() ?? $item;
    }
}
