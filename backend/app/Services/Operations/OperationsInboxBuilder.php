<?php

namespace App\Services\Operations;

use App\Enums\CredentialStatus;
use App\Enums\OfficeRole;
use App\Enums\SyncCursorStatus;
use App\Models\Client;
use App\Models\ClientCredential;
use App\Models\Establishment;
use App\Models\InstanceBackupRun;
use App\Models\SyncCursor;
use App\Models\SyncRun;
use App\Services\Clients\CaptureEligibilityService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Projeção sob demanda da inbox operacional do escritório (sem fila persistida).
 */
final class OperationsInboxBuilder
{
    public const TYPES = [
        'cursor_blocked',
        'cursor_error',
        'sync_failed_recent',
        'credential_expired',
        'credential_expiring_7d',
        'credential_expiring_30d',
        'backup_stale',
        'backup_never',
    ];

    public const SEVERITIES = [
        'critical',
        'high',
        'medium',
        'low',
    ];

    private const TYPE_SEVERITY = [
        'cursor_blocked' => 'critical',
        'cursor_error' => 'high',
        'sync_failed_recent' => 'high',
        'credential_expired' => 'critical',
        'credential_expiring_7d' => 'high',
        'credential_expiring_30d' => 'medium',
        'backup_stale' => 'high',
        'backup_never' => 'critical',
    ];

    private const SEVERITY_RANK = [
        'critical' => 0,
        'high' => 1,
        'medium' => 2,
        'low' => 3,
    ];

    public function __construct(
        private readonly CaptureEligibilityService $eligibility,
    ) {}

    /**
     * @return array{
     *   data: list<array<string, mixed>>,
     *   meta: array{next_cursor: ?string, total_estimate: int, generated_at: string}
     * }
     */
    public function build(
        int $officeId,
        ?OfficeRole $role,
        ?string $severity = null,
        ?string $type = null,
        int $limit = 50,
        ?string $cursor = null,
    ): array {
        $items = $this->collectAll($officeId, $role);

        if ($severity !== null && $severity !== '' && in_array($severity, self::SEVERITIES, true)) {
            $items = $items->filter(fn (array $item) => $item['severity'] === $severity)->values();
        }

        if ($type !== null && $type !== '' && in_array($type, self::TYPES, true)) {
            $items = $items->filter(fn (array $item) => $item['type'] === $type)->values();
        }

        $items = $items->sort(function (array $a, array $b): int {
            $rank = (self::SEVERITY_RANK[$a['severity']] ?? 9) <=> (self::SEVERITY_RANK[$b['severity']] ?? 9);
            if ($rank !== 0) {
                return $rank;
            }
            $time = strcmp((string) ($b['occurred_at'] ?? ''), (string) ($a['occurred_at'] ?? ''));
            if ($time !== 0) {
                return $time;
            }

            return strcmp((string) $a['id'], (string) $b['id']);
        })->values();

        $total = $items->count();

        if ($cursor !== null && $cursor !== '') {
            $offset = $this->decodeCursor($cursor);
            if ($offset > 0) {
                $items = $items->slice($offset)->values();
            }
        }

        $limit = min(max($limit, 1), 100);
        $page = $items->take($limit)->values();
        $taken = $page->count();
        $startOffset = $cursor !== null && $cursor !== '' ? $this->decodeCursor($cursor) : 0;
        $nextOffset = $startOffset + $taken;
        $hasMore = $nextOffset < $total;

        return [
            'data' => $page->all(),
            'meta' => [
                'next_cursor' => $hasMore ? $this->encodeCursor($nextOffset) : null,
                'total_estimate' => $total,
                'generated_at' => now()->toIso8601String(),
            ],
        ];
    }

    /**
     * Contagens agregadas (sem paginação).
     *
     * @return array{inbox_critical: int, inbox_high: int, inbox_total: int}
     */
    public function counts(int $officeId, ?OfficeRole $role = null): array
    {
        $items = $this->collectAll($officeId, $role);

        return [
            'inbox_critical' => $items->where('severity', 'critical')->count(),
            'inbox_high' => $items->where('severity', 'high')->count(),
            'inbox_total' => $items->count(),
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function collectAll(int $officeId, ?OfficeRole $role): Collection
    {
        $items = collect();

        $items = $items->merge($this->cursorItems($officeId, $role));
        $items = $items->merge($this->syncFailedItems($officeId, $role));
        $items = $items->merge($this->credentialItems($officeId));
        $items = $items->merge($this->backupItems());

        return $items->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function cursorItems(int $officeId, ?OfficeRole $role): Collection
    {
        $cursors = SyncCursor::query()
            ->where('office_id', $officeId)
            ->whereIn('status', [SyncCursorStatus::Blocked, SyncCursorStatus::Error])
            ->with(['establishment.client'])
            ->orderBy('id')
            ->get();

        return $cursors->map(function (SyncCursor $cursor) use ($role) {
            $establishment = $cursor->establishment;
            $client = $establishment?->client;
            if ($establishment === null || $client === null) {
                return null;
            }

            $type = $cursor->status === SyncCursorStatus::Blocked
                ? 'cursor_blocked'
                : 'cursor_error';

            $envLabel = is_string($cursor->environment) && $cursor->environment !== ''
                ? $cursor->environment
                : null;

            $body = $type === 'cursor_blocked'
                ? 'Cursor bloqueado. Intervenção necessária antes de retomar a captura.'
                : 'Cursor em erro. Verifique o histórico de sincronização.';

            if ($envLabel !== null) {
                $body .= ' Ambiente: '.$envLabel.'.';
            }

            $sanitizedError = $this->sanitizeText($cursor->last_error);
            if ($sanitizedError !== null && $sanitizedError !== '') {
                $body .= ' '.$sanitizedError;
            }

            $titleBase = $type === 'cursor_blocked'
                ? 'Cursor bloqueado: '.$this->clientLabel($client)
                : 'Cursor com erro: '.$this->clientLabel($client);

            return $this->item(
                type: $type,
                title: $envLabel !== null ? $titleBase.' ('.$envLabel.')' : $titleBase,
                body: $body,
                reasons: [$type],
                clientId: $client->id,
                establishmentId: $establishment->id,
                occurredAt: $cursor->updated_at?->toIso8601String() ?? now()->toIso8601String(),
                role: $role,
                establishment: $establishment,
                cursor: $cursor,
            );
        })->filter()->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function syncFailedItems(int $officeId, ?OfficeRole $role): Collection
    {
        $since = now()->subDay();

        $failedRuns = SyncRun::query()
            ->where('office_id', $officeId)
            ->where('status', 'FAILED')
            ->where('created_at', '>=', $since)
            ->with(['cursor.establishment.client'])
            ->orderByDesc('id')
            ->get();

        $seenEstablishments = [];
        $items = collect();

        foreach ($failedRuns as $run) {
            $cursor = $run->cursor;
            $establishment = $cursor?->establishment;
            $client = $establishment?->client;
            if ($establishment === null || $client === null) {
                continue;
            }
            // Já coberto por item de cursor BLOCKED/ERROR no mesmo estabelecimento.
            if ($cursor !== null && in_array($cursor->status, [SyncCursorStatus::Blocked, SyncCursorStatus::Error], true)) {
                continue;
            }
            if (isset($seenEstablishments[$establishment->id])) {
                continue;
            }
            $seenEstablishments[$establishment->id] = true;

            $items->push($this->item(
                type: 'sync_failed_recent',
                title: 'Falha de sincronização: '.$this->clientLabel($client),
                body: $this->sanitizeText($run->error_message)
                    ?? 'Falha sanitizada na sincronização ADN nas últimas 24 horas.',
                reasons: ['sync_failed_recent'],
                clientId: $client->id,
                establishmentId: $establishment->id,
                occurredAt: $run->finished_at?->toIso8601String()
                    ?? $run->created_at?->toIso8601String()
                    ?? now()->toIso8601String(),
                role: $role,
                establishment: $establishment,
                cursor: $cursor,
            ));
        }

        return $items->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function credentialItems(int $officeId): Collection
    {
        $now = CarbonImmutable::now();
        $credentials = ClientCredential::query()
            ->where('office_id', $officeId)
            ->whereIn('status', [CredentialStatus::Active, CredentialStatus::Expired])
            ->with('client')
            ->orderBy('id')
            ->get();

        $items = collect();

        foreach ($credentials as $credential) {
            $client = $credential->client;
            if ($client === null) {
                continue;
            }

            $validTo = $credential->valid_to;
            $expired = $credential->status === CredentialStatus::Expired
                || ($validTo !== null && $validTo->isPast());

            if ($expired) {
                $items->push($this->item(
                    type: 'credential_expired',
                    title: 'Certificado A1 vencido: '.$this->clientLabel($client),
                    body: 'A credencial ACTIVE/operacional está vencida. Atualize o certificado do cliente.',
                    reasons: ['credential_expired'],
                    clientId: $client->id,
                    establishmentId: null,
                    occurredAt: $validTo?->toIso8601String() ?? $credential->updated_at?->toIso8601String() ?? $now->toIso8601String(),
                    role: null,
                    establishment: null,
                    cursor: null,
                ));

                continue;
            }

            if ($credential->status !== CredentialStatus::Active || $validTo === null) {
                continue;
            }

            // Alinhado a CredentialService (floor de floatDiffInRealDays).
            $days = (int) floor($now->floatDiffInRealDays($validTo, false));
            if ($days < 0) {
                continue;
            }

            if ($credential->expires_alert_1 || $credential->expires_alert_7 || $days <= 7) {
                $items->push($this->item(
                    type: 'credential_expiring_7d',
                    title: 'Certificado A1 vence em breve: '.$this->clientLabel($client),
                    body: 'Vencimento em até 7 dias ('.$validTo->toDateString().').',
                    reasons: ['credential_expiring_7d'],
                    clientId: $client->id,
                    establishmentId: null,
                    occurredAt: $validTo->toIso8601String(),
                    role: null,
                    establishment: null,
                    cursor: null,
                ));

                continue;
            }

            if ($credential->expires_alert_30 || $days <= 30) {
                $items->push($this->item(
                    type: 'credential_expiring_30d',
                    title: 'Certificado A1 a vencer: '.$this->clientLabel($client),
                    body: 'Vencimento em até 30 dias ('.$validTo->toDateString().').',
                    reasons: ['credential_expiring_30d'],
                    clientId: $client->id,
                    establishmentId: null,
                    occurredAt: $validTo->toIso8601String(),
                    role: null,
                    establishment: null,
                    cursor: null,
                ));
            }
        }

        return $items->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function backupItems(): Collection
    {
        $summary = InstanceBackupRun::statusSummary();
        $items = collect();

        if ($summary['never']) {
            $items->push($this->item(
                type: 'backup_never',
                title: 'Nenhum backup bem-sucedido registrado',
                body: 'A instância ainda não possui backup SUCCESS. Execute o backup operacional e o restore drill antes do piloto com dados reais.',
                reasons: ['backup_never'],
                clientId: null,
                establishmentId: null,
                occurredAt: now()->toIso8601String(),
                role: null,
                establishment: null,
                cursor: null,
            ));
        } elseif ($summary['stale']) {
            $items->push($this->item(
                type: 'backup_stale',
                title: 'Backup da instância atrasado',
                body: 'Não há backup SUCCESS nas últimas 24 horas.'
                    .($summary['last_success_at'] ? ' Último sucesso: '.$summary['last_success_at'].'.' : ''),
                reasons: ['backup_stale'],
                clientId: null,
                establishmentId: null,
                occurredAt: $summary['last_success_at'] ?? now()->toIso8601String(),
                role: null,
                establishment: null,
                cursor: null,
            ));
        }

        return $items->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function item(
        string $type,
        string $title,
        string $body,
        array $reasons,
        ?int $clientId,
        ?int $establishmentId,
        string $occurredAt,
        ?OfficeRole $role,
        ?Establishment $establishment,
        ?SyncCursor $cursor,
    ): array {
        $severity = self::TYPE_SEVERITY[$type] ?? 'medium';
        // Inclui cursor_id + environment para não colidir multi-ambiente no mesmo establishment.
        $subject = implode(':', array_filter([
            $type,
            $clientId !== null ? 'c'.$clientId : null,
            $establishmentId !== null ? 'e'.$establishmentId : null,
            $cursor?->id !== null ? 'cur'.$cursor->id : null,
            is_string($cursor?->environment) && $cursor->environment !== ''
                ? 'env'.$cursor->environment
                : null,
        ], fn ($part) => $part !== null && $part !== ''));
        $id = substr(hash('sha256', $subject), 0, 32);

        $links = [];
        if ($clientId !== null) {
            $links['client'] = '/clients/'.$clientId;
            $links['sync'] = '/clients/'.$clientId.'/sincronizacao';
            $links['credential'] = '/clients/'.$clientId.'/certificado';
        }

        $actions = [
            ['type' => 'open', 'label' => 'Abrir'],
        ];

        if (
            $role !== null
            && $role->canTriggerSync()
            && $establishment !== null
            && in_array($type, ['cursor_error', 'sync_failed_recent'], true)
        ) {
            $eval = $this->eligibility->evaluate($establishment, $cursor);
            if ($eval['eligible']) {
                $actions[] = [
                    'type' => 'trigger_sync',
                    'label' => 'Sincronizar',
                    'establishment_id' => $establishment->id,
                ];
            }
        }

        return [
            'id' => $id,
            'type' => $type,
            'severity' => $severity,
            'title' => $title,
            'body' => $body,
            'reasons' => $reasons,
            'client_id' => $clientId,
            'establishment_id' => $establishmentId,
            'occurred_at' => $occurredAt,
            'links' => $links,
            'actions' => $actions,
        ];
    }

    private function clientLabel(Client $client): string
    {
        $name = $client->display_name ?: $client->legal_name;

        return (string) $name;
    }

    private function sanitizeText(?string $text): ?string
    {
        if ($text === null || $text === '') {
            return null;
        }

        $text = preg_replace('/\s+/', ' ', trim($text)) ?? '';
        // Remove possíveis blobs base64 longos / material sensível óbvio.
        $text = preg_replace('/[A-Za-z0-9+\/]{80,}={0,2}/', '[redacted]', $text) ?? $text;
        $forbidden = ['BEGIN ', 'PRIVATE KEY', 'VAULT_MASTER_KEY', 'vault_object_id', 'password=', 'pfx'];
        foreach ($forbidden as $needle) {
            if (stripos($text, $needle) !== false) {
                return 'Mensagem sanitizada (conteúdo sensível omitido).';
            }
        }

        return mb_substr($text, 0, 280);
    }

    private function encodeCursor(int $offset): string
    {
        return rtrim(strtr(base64_encode((string) $offset), '+/', '-_'), '=');
    }

    private function decodeCursor(string $cursor): int
    {
        $raw = base64_decode(strtr($cursor, '-_', '+/'), true);
        if ($raw === false || ! ctype_digit($raw)) {
            return 0;
        }

        return max(0, (int) $raw);
    }
}
