<?php

namespace App\Services\Clients;

use App\Enums\CaptureChannel;
use App\Enums\CredentialStatus;
use App\Enums\SyncCursorStatus;
use App\Models\Client;
use App\Models\ClientCredential;
use App\Models\Establishment;
use App\Models\SyncCursor;

/**
 * Regra central: Cliente ativo + Estabelecimento ativo + capture_enabled
 * + credencial válida + cursor não bloqueado.
 * Inclui resumo por canal (ADN / DistDFe NF-e / CT-e / MDF-e).
 */
final class CaptureEligibilityService
{
    /**
     * @return array{
     *   eligible: bool,
     *   reasons: list<string>,
     *   reasons_codes: list<string>,
     *   channels: array<string, array{label: string, enabled: bool, eligible: bool}>
     * }
     */
    public function evaluate(Establishment $establishment, ?SyncCursor $cursor = null): array
    {
        $reasons = [];
        $codes = [];

        $client = $establishment->relationLoaded('client')
            ? $establishment->client
            : Client::query()->find($establishment->client_id);

        if ($client === null || ! $client->is_active || $client->trashed()) {
            $reasons[] = 'Cliente inativo ou indisponível.';
            $codes[] = 'client_inactive';
        }

        if (! $establishment->is_active || $establishment->trashed()) {
            $reasons[] = 'Estabelecimento inativo.';
            $codes[] = 'establishment_inactive';
        }

        if (! $establishment->capture_enabled) {
            $reasons[] = 'Captura desabilitada para este estabelecimento.';
            $codes[] = 'capture_disabled';
        }

        $credential = ClientCredential::query()
            ->where('client_id', $establishment->client_id)
            ->where('status', CredentialStatus::Active)
            ->first();

        if ($credential === null) {
            $reasons[] = 'Credencial A1 ausente ou inativa.';
            $codes[] = 'credential_missing';
        } elseif ($credential->valid_to !== null && $credential->valid_to->isPast()) {
            $reasons[] = 'Credencial A1 expirada.';
            $codes[] = 'credential_expired';
        }

        if ($cursor !== null && $cursor->status === SyncCursorStatus::Blocked) {
            $reasons[] = 'Cursor de sincronização bloqueado.';
            $codes[] = 'cursor_blocked';
        }

        $baseOk = $reasons === [];

        return [
            'eligible' => $baseOk,
            'reasons' => $reasons,
            'reasons_codes' => $codes,
            'channels' => $this->channelSummary($baseOk),
        ];
    }

    public function isEligible(Establishment $establishment, ?SyncCursor $cursor = null): bool
    {
        return $this->evaluate($establishment, $cursor)['eligible'];
    }

    /**
     * @return array<string, array{label: string, enabled: bool, eligible: bool}>
     */
    private function channelSummary(bool $baseOk): array
    {
        $out = [];
        foreach (CaptureChannel::cases() as $channel) {
            $enabled = $channel->isEnabled();
            $out[$channel->value] = [
                'label' => $channel->label(),
                'enabled' => $enabled,
                'eligible' => $baseOk && $enabled,
            ];
        }

        return $out;
    }
}
