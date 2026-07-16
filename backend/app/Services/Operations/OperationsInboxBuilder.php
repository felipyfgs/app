<?php

namespace App\Services\Operations;

use App\Enums\CaptureChannel;
use App\Enums\CredentialStatus;
use App\Enums\DocumentAcquisitionSource;
use App\Enums\FiscalFindingSeverity;
use App\Enums\FiscalMutationStatus;
use App\Enums\FiscalPendingStatus;
use App\Enums\FiscalRunResult;
use App\Enums\OfficeRole;
use App\Enums\OutboundNumberStatus;
use App\Enums\OutboundRetrievalOrigin;
use App\Enums\OutboundRetrievalStatus;
use App\Enums\OutboundSeriesStatus;
use App\Enums\QuarantineReason;
use App\Enums\QuarantineResolutionStatus;
use App\Enums\SerproAuthorizationStatus;
use App\Enums\SerproEnvironment;
use App\Enums\SvrsNfceFailureReason;
use App\Enums\SvrsNfceRecoveryStatus;
use App\Enums\SyncCursorStatus;
use App\Enums\TaxGuidePaymentStatus;
use App\Enums\TaxProxyPowerStatus;
use App\Models\ChannelSyncCursor;
use App\Models\Client;
use App\Models\ClientCredential;
use App\Models\DocumentAcquisition;
use App\Models\Establishment;
use App\Models\FiscalDocumentQuarantine;
use App\Models\FiscalMonitoringRun;
use App\Models\FiscalMutationOperation;
use App\Models\FiscalPendingItem;
use App\Models\InstanceBackupRun;
use App\Models\MailboxAlert;
use App\Models\MaOutboundRetrievalRequest;
use App\Models\OfficeDistributionCursor;
use App\Models\OfficeSerproAuthorization;
use App\Models\OutboundNumberState;
use App\Models\OutboundSeriesCursor;
use App\Models\SyncCursor;
use App\Models\SyncRun;
use App\Models\TaxGuide;
use App\Models\TaxProxyPower;
use App\Services\Clients\CaptureEligibilityService;
use App\Services\Integra\TenantIntegraHealthService;
use App\Services\Outbound\SvrsNfceCircuitBreaker;
use App\Services\Usage\OfficeUsageQueryService;
use App\Support\LogSanitizer;
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
        // Canal MA outbound (nNF)
        'outbound_gap_exhausted',
        'outbound_562_no_key',
        'outbound_656',
        'outbound_retrieval_expired',
        'outbound_xml_divergent',
        'outbound_authorized_unexpected',
        'outbound_cancel_failed',
        // Canal SVRS NFC-e XML
        'svrs_nfce_a1',
        'svrs_nfce_auth',
        'svrs_nfce_rate_limit',
        'svrs_nfce_multiple_queries',
        'svrs_nfce_budget',
        'svrs_nfce_contract_changed',
        'svrs_nfce_xml_signature',
        'svrs_nfce_divergent',
        'svrs_nfce_breaker',
        'svrs_nfce_exhausted',
        // Quarentena autXML / import
        'quarantine_unmatched_issuer',
        'quarantine_autxml_tag',
        'quarantine_orphan_event',
        'quarantine_bytes_diverge',
        'quarantine_schema',
        'quarantine_other',
        // CT-e tipados
        'cte_a1_missing',
        'cte_593',
        'cte_656',
        'cte_decode_failures',
        'cte_heartbeat_stale',
        'cte_external_consumer',
        'cte_unexpected_own_issuer',
        'cte_redaction',
        'cte_conflict',
        'cte_pending_import',
        // Caixa Postal — alertas sanitizados (sem corpo/anexo)
        'mailbox_message',
        'mailbox_message_urgent',
        // Hub fiscal / SERPRO (tenant-scoped)
        'serpro_termo_missing',
        'serpro_termo_expired',
        'serpro_token_expiring',
        'serpro_auth_action_required',
        'serpro_auth_blocked',
        'proxy_power_expired',
        'proxy_power_missing',
        'source_unavailable',
        'query_blocked',
        'fiscal_pending',
        'guide_due_soon',
        'usage_high',
        'usage_franchise_exceeded',
        'mutation_unknown_result',
        'parsing_alert',
        'sitfis_run_completed',
        'sitfis_run_failed',
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
        'outbound_gap_exhausted' => 'high',
        'outbound_562_no_key' => 'medium',
        'outbound_656' => 'critical',
        'outbound_retrieval_expired' => 'high',
        'outbound_xml_divergent' => 'high',
        'outbound_authorized_unexpected' => 'critical',
        'outbound_cancel_failed' => 'critical',
        'svrs_nfce_a1' => 'high',
        'svrs_nfce_auth' => 'critical',
        'svrs_nfce_rate_limit' => 'medium',
        'svrs_nfce_multiple_queries' => 'critical',
        'svrs_nfce_budget' => 'medium',
        'svrs_nfce_contract_changed' => 'critical',
        'svrs_nfce_xml_signature' => 'critical',
        'svrs_nfce_divergent' => 'high',
        'svrs_nfce_breaker' => 'critical',
        'svrs_nfce_exhausted' => 'high',
        'quarantine_unmatched_issuer' => 'high',
        'quarantine_autxml_tag' => 'high',
        'quarantine_orphan_event' => 'medium',
        'quarantine_bytes_diverge' => 'critical',
        'quarantine_schema' => 'medium',
        'quarantine_other' => 'medium',
        'cte_a1_missing' => 'high',
        'cte_593' => 'critical',
        'cte_656' => 'critical',
        'cte_decode_failures' => 'high',
        'cte_heartbeat_stale' => 'medium',
        'cte_external_consumer' => 'high',
        'cte_unexpected_own_issuer' => 'high',
        'cte_redaction' => 'medium',
        'cte_conflict' => 'critical',
        'cte_pending_import' => 'medium',
        'mailbox_message' => 'medium',
        'mailbox_message_urgent' => 'critical',
        'serpro_termo_missing' => 'critical',
        'serpro_termo_expired' => 'critical',
        'serpro_token_expiring' => 'high',
        'serpro_auth_action_required' => 'critical',
        'serpro_auth_blocked' => 'critical',
        'proxy_power_expired' => 'high',
        'proxy_power_missing' => 'high',
        'source_unavailable' => 'high',
        'query_blocked' => 'high',
        'fiscal_pending' => 'medium',
        'guide_due_soon' => 'high',
        'usage_high' => 'medium',
        'usage_franchise_exceeded' => 'high',
        'mutation_unknown_result' => 'critical',
        'parsing_alert' => 'medium',
        'sitfis_run_completed' => 'low',
        'sitfis_run_failed' => 'high',
    ];

    private const SEVERITY_RANK = [
        'critical' => 0,
        'high' => 1,
        'medium' => 2,
        'low' => 3,
    ];

    public function __construct(
        private readonly CaptureEligibilityService $eligibility,
        private readonly TenantIntegraHealthService $integraHealth,
        private readonly OfficeUsageQueryService $usage,
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
        $items = $items->merge($this->channelCursorItems($officeId, $role));
        $items = $items->merge($this->cteOperationalItems($officeId, $role));
        $items = $items->merge($this->syncFailedItems($officeId, $role));
        $items = $items->merge($this->credentialItems($officeId));
        $items = $items->merge($this->backupItems());
        $items = $items->merge($this->outboundMaItems($officeId, $role));
        $items = $items->merge($this->svrsNfceItems($officeId, $role));
        $items = $items->merge($this->quarantineItems($officeId, $role));
        $items = $items->merge($this->mailboxAlertItems($officeId));
        $items = $items->merge($this->serproAuthItems($officeId));
        $items = $items->merge($this->proxyPowerItems($officeId));
        $items = $items->merge($this->sourceAvailabilityItems($officeId));
        $items = $items->merge($this->fiscalPendingItems($officeId));
        $items = $items->merge($this->guideDueItems($officeId));
        $items = $items->merge($this->usageItems($officeId));
        $items = $items->merge($this->uncertainMutationItems($officeId));
        $items = $items->merge($this->parsingAlertItems($officeId));
        $items = $items->merge($this->sitfisRunItems($officeId));

        return $items->values();
    }

    /** @return Collection<int, array<string, mixed>> */
    private function sitfisRunItems(int $officeId): Collection
    {
        // Só falhas e conclusões com alerta de parse — COMPLETED limpos não poluem a inbox.
        return FiscalMonitoringRun::query()
            ->where('office_id', $officeId)
            ->where('service_code', 'SITFIS')
            ->where(function ($q): void {
                $q->where('status', 'FAILED')
                    ->orWhere(function ($q2): void {
                        $q2->where('status', 'COMPLETED')
                            ->where('verification_state', 'PARSE_ALERT');
                    })
                    ->orWhere('status', 'BLOCKED');
            })
            ->where('created_at', '>=', now()->subDays(3))
            ->with('client')
            ->orderByDesc('id')
            ->limit(20)
            ->get()
            ->map(function (FiscalMonitoringRun $run): array {
                $status = $run->status?->value ?? '';
                $failed = $status === 'FAILED';
                $blocked = $status === 'BLOCKED';
                $parseAlert = $run->verification_state?->value === 'PARSE_ALERT';
                $type = match (true) {
                    $failed => 'sitfis_run_failed',
                    $blocked => 'sitfis_run_failed',
                    $parseAlert => 'sitfis_run_completed',
                    default => 'sitfis_run_failed',
                };
                $title = match (true) {
                    $failed => 'Atualização SITFIS requer atenção',
                    $blocked => 'Atualização SITFIS bloqueada',
                    $parseAlert => 'SITFIS concluída com alerta de layout',
                    default => 'Atualização SITFIS requer atenção',
                };
                $body = match (true) {
                    $failed => 'A consulta terminou com erro operacional. Abra o detalhe para revisar a próxima ação.',
                    $blocked => 'A consulta foi bloqueada por gate operacional (autorização, capacidade ou orçamento).',
                    $parseAlert => 'Relatório capturado, mas o layout não foi reconhecido. Revise o artefato e o parser.',
                    default => 'A atualização SITFIS requer atenção.',
                };
                $item = $this->item(
                    type: $type,
                    title: $title,
                    body: $body,
                    reasons: array_values(array_filter([
                        $failed ? 'failed' : ($blocked ? 'blocked' : ($parseAlert ? 'parse_alert' : 'attention')),
                        $run->error_code,
                        'source:'.($run->source_provenance?->value ?? 'UNVERIFIED'),
                    ])),
                    clientId: $run->client_id,
                    establishmentId: null,
                    occurredAt: $run->finished_at?->toIso8601String()
                        ?? $run->updated_at?->toIso8601String()
                        ?? now()->toIso8601String(),
                    role: null,
                    establishment: null,
                    cursor: null,
                );
                $item['id'] = substr(hash('sha256', 'sitfis-run:'.$run->id), 0, 32);
                $item['links'] = ['run' => '/fiscal/runs/'.$run->id];

                return $item;
            })
            ->values();
    }

    /**
     * Inbox tipada CT-e (cursores cliente/autXML + quarentenas específicas).
     * Sem ações de portal automático; retry só se quiet/circuito permitir.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function cteOperationalItems(int $officeId, ?OfficeRole $role): Collection
    {
        $items = collect();
        $threshold = (int) config('sefaz.decode_failure_threshold', 5);

        $clientCursors = ChannelSyncCursor::query()
            ->where('office_id', $officeId)
            ->where('channel', CaptureChannel::CteDistDfe->value)
            ->with(['establishment.client'])
            ->orderBy('id')
            ->limit(80)
            ->get();

        foreach ($clientCursors as $cursor) {
            $client = $cursor->establishment?->client;
            $est = $cursor->establishment;
            $label = $client ? $this->clientLabel($client) : 'estabelecimento';
            $quiet = $cursor->next_sync_at?->isFuture() ?? false;
            $blocked = $cursor->status === SyncCursorStatus::Blocked;
            $retryAllowed = ! $blocked && ! $quiet
                && $role !== null
                && $role->canTriggerSync();

            if ($cursor->last_cstat === '656' || ($blocked && $cursor->last_cstat === '656')) {
                $items->push($this->cteItem(
                    type: 'cte_656',
                    title: 'CT-e circuito 656: '.$label,
                    body: 'Consumo indevido no DistDFe CT-e. Aguardar quiet ≥1h; sem retry manual até liberar.',
                    reasons: ['cte_656', 'cstat:656', 'chcur'.$cursor->id],
                    clientId: $client?->id,
                    establishmentId: $est?->id,
                    occurredAt: $cursor->updated_at?->toIso8601String() ?? now()->toIso8601String(),
                    role: $role,
                    retryAllowed: false,
                    cursorId: $cursor->id,
                ));
            }

            if ($cursor->last_cstat === '593') {
                $items->push($this->cteItem(
                    type: 'cte_593',
                    title: 'CT-e rejeição 593: '.$label,
                    body: 'Certificado/CNPJ divergente no DistDFe CT-e. Corrija A1 ou cadastro antes de retomar.',
                    reasons: ['cte_593', 'cstat:593', 'chcur'.$cursor->id],
                    clientId: $client?->id,
                    establishmentId: $est?->id,
                    occurredAt: $cursor->updated_at?->toIso8601String() ?? now()->toIso8601String(),
                    role: $role,
                    retryAllowed: false,
                    cursorId: $cursor->id,
                ));
            }

            if ((int) $cursor->consecutive_decode_failures >= max(1, $threshold - 1)) {
                $items->push($this->cteItem(
                    type: 'cte_decode_failures',
                    title: 'CT-e falhas de decode: '.$label,
                    body: 'Falhas consecutivas de Base64/GZip no mesmo NSU. Cursor preservado; sem avanço silencioso.',
                    reasons: ['cte_decode_failures', 'chcur'.$cursor->id],
                    clientId: $client?->id,
                    establishmentId: $est?->id,
                    occurredAt: $cursor->updated_at?->toIso8601String() ?? now()->toIso8601String(),
                    role: $role,
                    retryAllowed: $retryAllowed,
                    cursorId: $cursor->id,
                ));
            }
        }

        // A1 ausente para clientes com cursor CT-e ativo
        $clientIdsWithCte = $clientCursors->pluck('establishment.client_id')->filter()->unique();
        if ($clientIdsWithCte->isNotEmpty()) {
            $withA1 = ClientCredential::query()
                ->where('office_id', $officeId)
                ->whereIn('client_id', $clientIdsWithCte)
                ->where('status', CredentialStatus::Active)
                ->pluck('client_id')
                ->unique();
            foreach ($clientIdsWithCte->diff($withA1) as $clientId) {
                $items->push($this->cteItem(
                    type: 'cte_a1_missing',
                    title: 'CT-e sem A1 ativo',
                    body: 'Cursor CT-e existe mas não há credencial A1 ACTIVE do cliente. Sem portal automático.',
                    reasons: ['cte_a1_missing', 'c'.$clientId],
                    clientId: (int) $clientId,
                    establishmentId: null,
                    occurredAt: now()->toIso8601String(),
                    role: $role,
                    retryAllowed: false,
                    cursorId: null,
                ));
            }
        }

        $officeCursors = OfficeDistributionCursor::query()
            ->where('office_id', $officeId)
            ->where('channel', CaptureChannel::CteAutXmlDistDfe->value)
            ->orderBy('id')
            ->limit(40)
            ->get();

        foreach ($officeCursors as $oc) {
            if ($oc->external_consumer_status === 'EXTERNAL_CONSUMER_CONFLICT') {
                $items->push($this->cteItem(
                    type: 'cte_external_consumer',
                    title: 'CT-e autXML: consumidor externo',
                    body: 'Stream do escritório em conflito com consumidor externo. Reconcilie ownership antes de retomar.',
                    reasons: ['cte_external_consumer', 'oc'.$oc->id],
                    clientId: null,
                    establishmentId: null,
                    occurredAt: $oc->updated_at?->toIso8601String() ?? now()->toIso8601String(),
                    role: $role,
                    retryAllowed: false,
                    cursorId: $oc->id,
                ));
            }
            $heartbeat = $oc->last_heartbeat_at;
            if ($heartbeat !== null && $heartbeat->lt(now()->subHours(36)) && $oc->status !== SyncCursorStatus::Idle) {
                $items->push($this->cteItem(
                    type: 'cte_heartbeat_stale',
                    title: 'CT-e autXML heartbeat atrasado',
                    body: 'Último heartbeat do stream autXML há mais de 36h.',
                    reasons: ['cte_heartbeat_stale', 'oc'.$oc->id],
                    clientId: null,
                    establishmentId: null,
                    occurredAt: $heartbeat->toIso8601String(),
                    role: $role,
                    retryAllowed: ! ($oc->status === SyncCursorStatus::Blocked)
                        && ! ($oc->next_sync_at?->isFuture() ?? false),
                    cursorId: $oc->id,
                ));
            }
            if ($oc->last_cstat === '656') {
                $items->push($this->cteItem(
                    type: 'cte_656',
                    title: 'CT-e autXML circuito 656',
                    body: 'Consumo indevido no canal autXML do escritório. Quiet obrigatório.',
                    reasons: ['cte_656', 'autxml', 'oc'.$oc->id],
                    clientId: null,
                    establishmentId: null,
                    occurredAt: $oc->updated_at?->toIso8601String() ?? now()->toIso8601String(),
                    role: $role,
                    retryAllowed: false,
                    cursorId: $oc->id,
                ));
            }
        }

        // Quarentenas CT-e tipadas (além do agrupamento genérico)
        $cteQuarantines = FiscalDocumentQuarantine::query()
            ->where('office_id', $officeId)
            ->where('resolution_status', QuarantineResolutionStatus::Open)
            ->where(function ($q): void {
                $q->where('model', '57')->orWhere('schema_family', 'like', '%CTe%')
                    ->orWhere('channel', CaptureChannel::CteDistDfe->value)
                    ->orWhere('channel', CaptureChannel::CteAutXmlDistDfe->value);
            })
            ->orderByDesc('id')
            ->limit(40)
            ->get();

        foreach ($cteQuarantines as $q) {
            $type = match ($q->reason) {
                QuarantineReason::UnexpectedOwnIssuerDocument => 'cte_unexpected_own_issuer',
                QuarantineReason::PendingImport => 'cte_pending_import',
                QuarantineReason::BytesDiverge => 'cte_conflict',
                QuarantineReason::OrphanEvent => 'quarantine_orphan_event',
                default => null,
            };
            if ($type === null) {
                // Redação: metadado ou qualidade implícita
                $meta = $q->metadata ?? [];
                if (($meta['artifact_quality'] ?? null) === 'AUTXML_REDACTED'
                    || ($meta['origin'] ?? null) === 'AUTXML_REDACTED') {
                    $type = 'cte_redaction';
                } else {
                    continue;
                }
            }

            $keyHint = $q->access_key ? mb_substr($q->access_key, 0, 8).'…' : 'sem chave';
            $items->push($this->cteItem(
                type: $type,
                title: $q->reason->label(),
                body: $this->sanitizeText('CT-e · '.$keyHint.' · '.$q->reason->label().'. Sem XML no painel.')
                    ?? $q->reason->label(),
                reasons: array_values(array_filter([
                    $type,
                    $q->reason->value,
                    $q->channel?->value,
                ])),
                clientId: null,
                establishmentId: null,
                occurredAt: $q->created_at?->toIso8601String() ?? now()->toIso8601String(),
                role: $role,
                retryAllowed: false,
                cursorId: null,
                quarantineId: $q->id,
            ));
        }

        return $items->values();
    }

    /**
     * @param  list<string>  $reasons
     * @return array<string, mixed>
     */
    private function cteItem(
        string $type,
        string $title,
        string $body,
        array $reasons,
        ?int $clientId,
        ?int $establishmentId,
        string $occurredAt,
        ?OfficeRole $role,
        bool $retryAllowed,
        ?int $cursorId,
        ?int $quarantineId = null,
    ): array {
        $severity = self::TYPE_SEVERITY[$type] ?? 'medium';
        $subject = implode(':', array_filter([
            $type,
            $clientId !== null ? 'c'.$clientId : null,
            $establishmentId !== null ? 'e'.$establishmentId : null,
            $cursorId !== null ? 'cur'.$cursorId : null,
            $quarantineId !== null ? 'q'.$quarantineId : null,
        ]));
        $id = substr(hash('sha256', $subject), 0, 32);

        $actions = [['type' => 'open', 'label' => 'Abrir']];
        if ($retryAllowed && $cursorId !== null && $role !== null && $role->canTriggerSync()) {
            $actions[] = [
                'type' => 'repair_known_nsu',
                'label' => 'Reparo NSU conhecido',
                'cursor_id' => $cursorId,
                'requires_known_nsu' => true,
            ];
        }
        if ($quarantineId !== null && $role !== null && $role->canManageClients()) {
            $actions[] = [
                'type' => 'resolve_quarantine',
                'label' => 'Resolver',
                'quarantine_id' => $quarantineId,
            ];
        }

        $links = ['health' => '/health?type='.$type];
        if ($clientId !== null) {
            $links['client'] = '/clients/'.$clientId;
            $links['sync'] = '/clients/'.$clientId.'/sincronizacao';
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

    /**
     * Alertas de Caixa Postal — título/body sanitizados (sem corpo, anexo ou assunto fiscal).
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function mailboxAlertItems(int $officeId): Collection
    {
        $rows = MailboxAlert::query()
            ->withoutGlobalScopes()
            ->where('office_id', $officeId)
            ->where('is_active', true)
            ->orderByDesc('id')
            ->limit(40)
            ->get();

        return $rows->map(function (MailboxAlert $alert) {
            $sev = $alert->severity?->value ?? 'medium';
            $type = in_array($sev, ['critical', 'high'], true)
                ? 'mailbox_message_urgent'
                : 'mailbox_message';

            $subject = implode(':', ['mb', (string) $alert->id, $type]);
            $id = substr(hash('sha256', $subject), 0, 32);

            return [
                'id' => $id,
                'type' => $type,
                'severity' => self::TYPE_SEVERITY[$type] ?? $sev,
                'title' => $this->sanitizeText($alert->title) ?? 'Caixa Postal',
                'body' => $this->sanitizeText($alert->body) ?? 'Nova mensagem. Abrir detalhe autorizado.',
                'reasons' => ['mailbox', 'category_meta_only'],
                'client_id' => $alert->client_id,
                'establishment_id' => null,
                'occurred_at' => $alert->created_at?->toIso8601String() ?? now()->toIso8601String(),
                'links' => [
                    'mailbox' => $alert->deep_link,
                ],
                'actions' => [
                    ['type' => 'open', 'label' => 'Abrir mensagem', 'message_id' => $alert->mailbox_message_id],
                ],
            ];
        })->values();
    }

    /**
     * Itens de quarentena abertos — sem XML, vault ou caminho.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function quarantineItems(int $officeId, ?OfficeRole $role): Collection
    {
        $rows = FiscalDocumentQuarantine::query()
            ->where('office_id', $officeId)
            ->where('resolution_status', QuarantineResolutionStatus::Open)
            ->orderByDesc('id')
            ->limit(40)
            ->get();

        return $rows->map(function (FiscalDocumentQuarantine $q) use ($role) {
            $type = match ($q->reason) {
                QuarantineReason::UnmatchedIssuer, QuarantineReason::EnrollmentMissing => 'quarantine_unmatched_issuer',
                QuarantineReason::AutXmlTagMissing, QuarantineReason::AutXmlTagDivergent => 'quarantine_autxml_tag',
                QuarantineReason::OrphanEvent => 'quarantine_orphan_event',
                QuarantineReason::BytesDiverge => 'quarantine_bytes_diverge',
                QuarantineReason::SchemaIncomplete, QuarantineReason::UnknownSchema => 'quarantine_schema',
                default => 'quarantine_other',
            };

            $keyHint = $q->access_key
                ? mb_substr($q->access_key, 0, 8).'…'
                : 'sem chave';
            $issuer = $q->issuer_cnpj ? 'emit '.$q->issuer_cnpj : 'emitente desconhecido';

            $actions = [
                ['type' => 'open', 'label' => 'Revisar'],
            ];
            if ($role !== null && $role->canManageClients()) {
                $actions[] = [
                    'type' => 'resolve_quarantine',
                    'label' => 'Resolver',
                    'quarantine_id' => $q->id,
                ];
            }

            $severity = self::TYPE_SEVERITY[$type] ?? 'medium';
            $subject = implode(':', ['q', (string) $q->id, $type]);
            $id = substr(hash('sha256', $subject), 0, 32);

            return [
                'id' => $id,
                'type' => $type,
                'severity' => $severity,
                'title' => $q->reason->label(),
                'body' => $this->sanitizeText(
                    $issuer.' · '.$keyHint.' · '.$q->reason->label().'. Sem XML no painel.'
                ) ?? $q->reason->label(),
                'reasons' => array_values(array_filter([
                    $q->reason->value,
                    $q->model ? 'model:'.$q->model : null,
                    $q->channel?->value,
                ])),
                'client_id' => null,
                'establishment_id' => null,
                'occurred_at' => $q->created_at?->toIso8601String() ?? now()->toIso8601String(),
                'links' => [
                    'quarantine' => '/health?type='.$type,
                ],
                'actions' => $actions,
            ];
        })->values();
    }

    /**
     * Inbox tipada do canal SVRS NFC-e (sem chave completa / HTML / XML).
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function svrsNfceItems(int $officeId, ?OfficeRole $role): Collection
    {
        $items = collect();

        $map = [
            SvrsNfceFailureReason::A1Unavailable->value => 'svrs_nfce_a1',
            SvrsNfceFailureReason::A1NotRelated->value => 'svrs_nfce_a1',
            SvrsNfceFailureReason::AuthForbidden->value => 'svrs_nfce_auth',
            SvrsNfceFailureReason::RateLimited->value => 'svrs_nfce_budget',
            SvrsNfceFailureReason::EgressBlockedMultipleQueries->value => 'svrs_nfce_multiple_queries',
            SvrsNfceFailureReason::ResponseContractChanged->value => 'svrs_nfce_contract_changed',
            SvrsNfceFailureReason::InvalidSignature->value => 'svrs_nfce_xml_signature',
            SvrsNfceFailureReason::InvalidXml->value => 'svrs_nfce_xml_signature',
            SvrsNfceFailureReason::IdentityMismatch->value => 'svrs_nfce_xml_signature',
            SvrsNfceFailureReason::DivergentBytes->value => 'svrs_nfce_divergent',
            SvrsNfceFailureReason::MaxAttempts->value => 'svrs_nfce_exhausted',
            SvrsNfceFailureReason::BreakerOpen->value => 'svrs_nfce_breaker',
        ];

        $recoveries = MaOutboundRetrievalRequest::query()
            ->where('office_id', $officeId)
            ->where('origin', OutboundRetrievalOrigin::SvrsPortalByKey)
            ->whereIn('recovery_status', [
                SvrsNfceRecoveryStatus::Blocked,
                SvrsNfceRecoveryStatus::NotAvailableVisible,
                SvrsNfceRecoveryStatus::RetryScheduled,
            ])
            ->whereNotNull('failure_reason')
            ->with(['establishment.client', 'profile'])
            ->orderByDesc('id')
            ->limit(40)
            ->get();

        foreach ($recoveries as $req) {
            $reason = $req->failure_reason instanceof SvrsNfceFailureReason
                ? $req->failure_reason->value
                : (string) $req->failure_reason;
            $type = $map[$reason] ?? null;
            if ($type === null) {
                if ($req->recovery_status === SvrsNfceRecoveryStatus::NotAvailableVisible) {
                    $type = 'svrs_nfce_exhausted';
                } else {
                    continue;
                }
            }
            $establishment = $req->establishment;
            $client = $establishment?->client;
            $item = $this->item(
                type: $type,
                title: 'SVRS NFC-e: '.($req->failure_reason instanceof SvrsNfceFailureReason
                    ? $req->failure_reason->label()
                    : $reason),
                body: 'Recovery '.$req->recovery_status?->value.' — fallback assistido disponível. '
                    .$this->sanitizeText($req->last_error),
                reasons: [$type, 'origin:SVRS_PORTAL_BY_KEY'],
                clientId: $client?->id,
                establishmentId: $establishment?->id,
                occurredAt: $req->updated_at?->toIso8601String() ?? now()->toIso8601String(),
                role: $role,
                establishment: $establishment,
                cursor: null,
            );
            $item['id'] = substr(hash('sha256', 'svrs:rec:'.$req->id.':'.$type), 0, 32);
            $item['links'] = array_filter([
                'recovery_id' => $req->id,
                'profile_id' => $req->outbound_capture_profile_id,
                'establishment_id' => $req->establishment_id,
            ]);
            $items->push($item);
        }

        // Breaker global open
        try {
            $breaker = app(SvrsNfceCircuitBreaker::class);
            $global = $breaker->globalStatus();
            if (($global['state'] ?? 'closed') === 'open') {
                $item = $this->item(
                    type: 'svrs_nfce_breaker',
                    title: 'Circuit breaker SVRS global aberto',
                    body: 'Novos GET/POST bloqueados. Use fallback assistido; reset somente ADMIN+2FA após smoke.',
                    reasons: ['svrs_nfce_breaker', 'scope:global'],
                    clientId: null,
                    establishmentId: null,
                    occurredAt: now()->toIso8601String(),
                    role: $role,
                    establishment: null,
                    cursor: null,
                );
                $item['id'] = substr(hash('sha256', 'svrs:breaker:global'), 0, 32);
                $items->push($item);
            }
        } catch (\Throwable) {
            // ignore
        }

        // Divergentes SVRS
        $divergent = DocumentAcquisition::query()
            ->where('office_id', $officeId)
            ->where('bytes_diverge_from_canonical', true)
            ->whereIn('source', [
                DocumentAcquisitionSource::SvrsNfceDownloadXmlDfe->value,
                DocumentAcquisitionSource::SvrsNfe55DownloadXmlDfe->value,
            ])
            ->orderByDesc('id')
            ->limit(15)
            ->get();

        foreach ($divergent as $acq) {
            $item = $this->item(
                type: 'svrs_nfce_divergent',
                title: 'XML SVRS divergente (chave mascarada)',
                body: 'Canônico preservado; revisão humana necessária.',
                reasons: ['svrs_nfce_divergent'],
                clientId: null,
                establishmentId: $acq->establishment_id,
                occurredAt: $acq->created_at?->toIso8601String() ?? now()->toIso8601String(),
                role: $role,
                establishment: null,
                cursor: null,
            );
            $item['id'] = substr(hash('sha256', 'svrs:div:'.$acq->id), 0, 32);
            $items->push($item);
        }

        return $items->values();
    }

    /**
     * Itens allowlisted do canal de saídas MA (posição nNF, sem last_nsu).
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function outboundMaItems(int $officeId, ?OfficeRole $role): Collection
    {
        $items = collect();

        // Lacunas esgotadas
        $exhausted = OutboundNumberState::query()
            ->where('office_id', $officeId)
            ->where('status', OutboundNumberStatus::ExhaustedVisible)
            ->with(['seriesCursor.establishment.client'])
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        foreach ($exhausted as $state) {
            $series = $state->seriesCursor;
            $establishment = $series?->establishment;
            $client = $establishment?->client;
            if ($establishment === null || $client === null) {
                continue;
            }
            $item = $this->item(
                type: 'outbound_gap_exhausted',
                title: 'Lacuna esgotada (nNF '.$state->nnf.'): '.$this->clientLabel($client),
                body: 'Série '.$state->series.' esgotou tentativas de consulta. Posição nNF — não é NSU. Requer revisão humana.',
                reasons: ['outbound_gap_exhausted', 'nnf:'.$state->nnf],
                clientId: $client->id,
                establishmentId: $establishment->id,
                occurredAt: $state->updated_at?->toIso8601String() ?? now()->toIso8601String(),
                role: $role,
                establishment: $establishment,
                cursor: null,
            );
            $item['id'] = substr(hash('sha256', 'out:gap:'.$state->id), 0, 32);
            $items->push($item);
        }

        // 562 sem chave
        $noKey = OutboundNumberState::query()
            ->where('office_id', $officeId)
            ->where('status', OutboundNumberStatus::LimitedNoKey)
            ->with(['seriesCursor.establishment.client'])
            ->orderByDesc('id')
            ->limit(30)
            ->get();

        foreach ($noKey as $state) {
            $series = $state->seriesCursor;
            $establishment = $series?->establishment;
            $client = $establishment?->client;
            if ($establishment === null || $client === null) {
                continue;
            }
            $item = $this->item(
                type: 'outbound_562_no_key',
                title: '562 sem chave (nNF '.$state->nnf.'): '.$this->clientLabel($client),
                body: 'Consulta retornou limitação sem chNFe. Força bruta de cNF bloqueada. Use pacote oficial assistido.',
                reasons: ['outbound_562_no_key'],
                clientId: $client->id,
                establishmentId: $establishment->id,
                occurredAt: $state->updated_at?->toIso8601String() ?? now()->toIso8601String(),
                role: $role,
                establishment: $establishment,
                cursor: null,
            );
            $item['id'] = substr(hash('sha256', 'out:562:'.$state->id), 0, 32);
            $items->push($item);
        }

        // 656 / séries bloqueadas
        $blocked = OutboundSeriesCursor::query()
            ->where('office_id', $officeId)
            ->whereIn('status', [OutboundSeriesStatus::Blocked, OutboundSeriesStatus::FiscalIncident])
            ->with(['establishment.client'])
            ->orderByDesc('id')
            ->limit(30)
            ->get();

        foreach ($blocked as $series) {
            $establishment = $series->establishment;
            $client = $establishment?->client;
            if ($establishment === null || $client === null) {
                continue;
            }
            $type = $series->status === OutboundSeriesStatus::FiscalIncident
                ? 'outbound_authorized_unexpected'
                : 'outbound_656';
            $item = $this->item(
                type: $type,
                title: ($type === 'outbound_656' ? 'Bloqueio MA (cStat 656/série)' : 'Incidente fiscal MA').': '.$this->clientLabel($client),
                body: 'Série '.$series->series.' modelo '.$series->model->value.'. '.($this->sanitizeText($series->last_error) ?? 'Intervenção necessária. Kill switch pode estar ativo.'),
                reasons: [$type, 'series:'.$series->id],
                clientId: $client->id,
                establishmentId: $establishment->id,
                occurredAt: $series->updated_at?->toIso8601String() ?? now()->toIso8601String(),
                role: $role,
                establishment: $establishment,
                cursor: null,
            );
            $item['id'] = substr(hash('sha256', 'out:series:'.$series->id.':'.$type), 0, 32);
            $items->push($item);
        }

        // Recuperação expirada
        $expired = MaOutboundRetrievalRequest::query()
            ->where('office_id', $officeId)
            ->where('status', OutboundRetrievalStatus::Expired)
            ->with(['establishment.client'])
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        foreach ($expired as $req) {
            $establishment = $req->establishment;
            $client = $establishment?->client;
            if ($establishment === null || $client === null) {
                continue;
            }
            $item = $this->item(
                type: 'outbound_retrieval_expired',
                title: 'Recuperação MA expirada ('.$req->competence.'): '.$this->clientLabel($client),
                body: 'Solicitação de pacote OUT modelo '.$req->model->value.' expirou. Reenvie em modo assistido se necessário.',
                reasons: ['outbound_retrieval_expired'],
                clientId: $client->id,
                establishmentId: $establishment->id,
                occurredAt: $req->updated_at?->toIso8601String() ?? now()->toIso8601String(),
                role: $role,
                establishment: $establishment,
                cursor: null,
            );
            $item['id'] = substr(hash('sha256', 'out:ret:'.$req->id), 0, 32);
            $items->push($item);
        }

        // XML divergente (quarentena)
        $divergent = DocumentAcquisition::query()
            ->where('office_id', $officeId)
            ->where('bytes_diverge_from_canonical', true)
            ->whereIn('source', [
                DocumentAcquisitionSource::MaOfficialPackage->value,
                DocumentAcquisitionSource::MaAssistedUpload->value,
                DocumentAcquisitionSource::MaM2mRetrieval->value,
                DocumentAcquisitionSource::SvrsNfceDownloadXmlDfe->value,
            ])
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        foreach ($divergent as $acq) {
            $item = $this->item(
                type: 'outbound_xml_divergent',
                title: 'XML divergente MA: chave '.substr((string) $acq->access_key, 0, 10).'…',
                body: 'Mesma chave com bytes diferentes — quarentena. Canônico preservado. '.$this->sanitizeText($acq->quarantine_reason),
                reasons: ['outbound_xml_divergent'],
                clientId: null,
                establishmentId: $acq->establishment_id,
                occurredAt: $acq->created_at?->toIso8601String() ?? now()->toIso8601String(),
                role: $role,
                establishment: null,
                cursor: null,
            );
            $item['id'] = substr(hash('sha256', 'out:div:'.$acq->id), 0, 32);
            $items->push($item);
        }

        // Cancelamento falho / incidente
        $cancelFailed = OutboundNumberState::query()
            ->where('office_id', $officeId)
            ->whereIn('status', [
                OutboundNumberStatus::FiscalIncident,
                OutboundNumberStatus::CancelPending,
            ])
            ->with(['seriesCursor.establishment.client'])
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        foreach ($cancelFailed as $state) {
            $series = $state->seriesCursor;
            $establishment = $series?->establishment;
            $client = $establishment?->client;
            if ($establishment === null || $client === null) {
                continue;
            }
            $type = $state->status === OutboundNumberStatus::FiscalIncident
                ? 'outbound_authorized_unexpected'
                : 'outbound_cancel_failed';
            $item = $this->item(
                type: $type,
                title: 'Incidente mutante MA (nNF '.$state->nnf.'): '.$this->clientLabel($client),
                body: 'Estado '.$state->status->value.'. Canal bloqueado até intervenção humana. Documento/evento preservados.',
                reasons: [$type],
                clientId: $client->id,
                establishmentId: $establishment->id,
                occurredAt: $state->updated_at?->toIso8601String() ?? now()->toIso8601String(),
                role: $role,
                establishment: $establishment,
                cursor: null,
            );
            $item['id'] = substr(hash('sha256', 'out:mut:'.$state->id), 0, 32);
            $items->push($item);
        }

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
                ? 'Cursor ADN bloqueado. Intervenção necessária antes de retomar a captura.'
                : 'Cursor ADN em erro. Verifique o histórico de sincronização.';

            if ($envLabel !== null) {
                $body .= ' Ambiente: '.$envLabel.'.';
            }

            $sanitizedError = $this->sanitizeText($cursor->last_error);
            if ($sanitizedError !== null && $sanitizedError !== '') {
                $body .= ' '.$sanitizedError;
            }

            $titleBase = $type === 'cursor_blocked'
                ? 'Cursor ADN bloqueado: '.$this->clientLabel($client)
                : 'Cursor ADN com erro: '.$this->clientLabel($client);

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
     * Cursores multi-canal SEFAZ (NF-e DistDFe, CT-e, …) em channel_sync_cursors.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function channelCursorItems(int $officeId, ?OfficeRole $role): Collection
    {
        $cursors = ChannelSyncCursor::query()
            ->where('office_id', $officeId)
            ->whereIn('status', [SyncCursorStatus::Blocked, SyncCursorStatus::Error])
            ->with(['establishment.client'])
            ->orderBy('id')
            ->get();

        return $cursors->map(function (ChannelSyncCursor $cursor) use ($role) {
            $establishment = $cursor->establishment;
            $client = $establishment?->client;
            if ($establishment === null || $client === null) {
                return null;
            }

            $channel = $cursor->channel instanceof CaptureChannel
                ? $cursor->channel
                : CaptureChannel::tryFrom((string) $cursor->channel);
            $channelLabel = $channel?->label() ?? (string) ($cursor->channel?->value ?? $cursor->channel ?? 'SEFAZ');

            $type = $cursor->status === SyncCursorStatus::Blocked
                ? 'cursor_blocked'
                : 'cursor_error';

            $envLabel = is_string($cursor->environment) && $cursor->environment !== ''
                ? $cursor->environment
                : null;

            $body = $type === 'cursor_blocked'
                ? "Cursor {$channelLabel} bloqueado (cStat ".($cursor->last_cstat ?? '—').').'
                : "Cursor {$channelLabel} em erro.";

            if ($envLabel !== null) {
                $body .= ' Ambiente: '.$envLabel.'.';
            }

            $sanitizedError = $this->sanitizeText($cursor->last_error);
            if ($sanitizedError !== null && $sanitizedError !== '') {
                $body .= ' '.$sanitizedError;
            }

            $titleBase = $type === 'cursor_blocked'
                ? "Cursor {$channelLabel} bloqueado: ".$this->clientLabel($client)
                : "Cursor {$channelLabel} com erro: ".$this->clientLabel($client);

            // item() espera SyncCursor; passamos null e embutimos id no subject via reasons/title uniqueness
            $item = $this->item(
                type: $type,
                title: $envLabel !== null ? $titleBase.' ('.$envLabel.')' : $titleBase,
                body: $body,
                reasons: [$type, 'channel:'.($channel?->value ?? 'unknown'), 'chcur'.$cursor->id],
                clientId: $client->id,
                establishmentId: $establishment->id,
                occurredAt: $cursor->updated_at?->toIso8601String() ?? now()->toIso8601String(),
                role: $role,
                establishment: $establishment,
                cursor: null,
            );
            // id estável e distinto de cursores ADN
            $item['id'] = substr(hash('sha256', 'channel:'.$type.':'.$cursor->id), 0, 32);

            return $item;
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
     * Autorização SERPRO do escritório: Termo, token, bloqueio (sem XML/token).
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function serproAuthItems(int $officeId): Collection
    {
        $items = collect();
        $env = SerproEnvironment::tryFrom((string) config('serpro.default_environment', 'TRIAL'))
            ?? SerproEnvironment::Trial;

        $auth = OfficeSerproAuthorization::query()
            ->where('office_id', $officeId)
            ->where('environment', $env->value)
            ->first();

        if ($auth === null) {
            $items->push($this->item(
                type: 'serpro_termo_missing',
                title: 'Integra Contador não configurado',
                body: 'Configure o Autor do Pedido e envie o Termo de Autorização. Credenciais globais SERPRO não são expostas ao tenant.',
                reasons: ['serpro_auth_missing', 'next:CONFIGURE_AUTHOR'],
                clientId: null,
                establishmentId: null,
                occurredAt: now()->toIso8601String(),
                role: null,
                establishment: null,
                cursor: null,
            ));

            return $items->values();
        }

        $actions = $auth->computeActionsRequired();
        $actionCodes = array_column($actions, 'code');

        if (in_array('UPLOAD_TERMO', $actionCodes, true)) {
            $items->push($this->item(
                type: 'serpro_termo_missing',
                title: 'Termo de Autorização ausente',
                body: 'Envie o Termo assinado externamente. O XML e tokens não são recuperáveis pela API.',
                reasons: ['UPLOAD_TERMO'],
                clientId: null,
                establishmentId: null,
                occurredAt: $auth->updated_at?->toIso8601String() ?? now()->toIso8601String(),
                role: null,
                establishment: null,
                cursor: null,
            ));
        }

        if (in_array('TERMO_EXPIRED', $actionCodes, true)) {
            $items->push($this->item(
                type: 'serpro_termo_expired',
                title: 'Termo de Autorização expirado',
                body: 'Envie um novo Termo assinado. Consultas e mutações permanecem bloqueadas até regularização.',
                reasons: ['TERMO_EXPIRED'],
                clientId: null,
                establishmentId: null,
                occurredAt: $auth->termo_valid_to?->toIso8601String() ?? now()->toIso8601String(),
                role: null,
                establishment: null,
                cursor: null,
            ));
        }

        if (in_array('REFRESH_PROCURADOR_TOKEN', $actionCodes, true)) {
            $items->push($this->item(
                type: 'serpro_token_expiring',
                title: 'Token do procurador ausente ou expirado',
                body: 'Renove o token do procurador (reapresentação do Termo conforme política). Sem material de token na resposta.',
                reasons: ['REFRESH_PROCURADOR_TOKEN'],
                clientId: null,
                establishmentId: null,
                occurredAt: $auth->procurador_token_expires_at?->toIso8601String() ?? now()->toIso8601String(),
                role: null,
                establishment: null,
                cursor: null,
            ));
        } elseif (
            $auth->procurador_token_expires_at !== null
            && $auth->procurador_token_expires_at->isFuture()
            && $auth->procurador_token_expires_at->lessThan(now()->addHours(24))
        ) {
            $items->push($this->item(
                type: 'serpro_token_expiring',
                title: 'Token do procurador expira em breve',
                body: 'Planeje a renovação nas próximas 24 horas.',
                reasons: ['TOKEN_EXPIRING_24H'],
                clientId: null,
                establishmentId: null,
                occurredAt: $auth->procurador_token_expires_at->toIso8601String(),
                role: null,
                establishment: null,
                cursor: null,
            ));
        }

        if ($auth->status === SerproAuthorizationStatus::ActionRequired
            || in_array('SIGNATURE_REQUIRED', $actionCodes, true)
            || in_array('A3_INTERACTIVE', $actionCodes, true)
        ) {
            $items->push($this->item(
                type: 'serpro_auth_action_required',
                title: 'Ação necessária na autorização SERPRO',
                body: $this->sanitizeText($auth->action_required_reason)
                    ?? 'Assinatura interativa ou revalidação do Termo necessária.',
                reasons: ['ACTION_REQUIRED'],
                clientId: null,
                establishmentId: null,
                occurredAt: $auth->updated_at?->toIso8601String() ?? now()->toIso8601String(),
                role: null,
                establishment: null,
                cursor: null,
            ));
        }

        if (in_array($auth->status, [
            SerproAuthorizationStatus::Blocked,
            SerproAuthorizationStatus::Revoked,
            SerproAuthorizationStatus::Expired,
        ], true)) {
            $items->push($this->item(
                type: 'serpro_auth_blocked',
                title: 'Autorização SERPRO bloqueada ('.$auth->status->value.')',
                body: 'Chamadas ao Integra Contador estão impedidas para este escritório até regularização.',
                reasons: ['auth:'.$auth->status->value],
                clientId: null,
                establishmentId: null,
                occurredAt: $auth->updated_at?->toIso8601String() ?? now()->toIso8601String(),
                role: null,
                establishment: null,
                cursor: null,
            ));
        }

        // Deep-links de settings
        return $items->map(function (array $item) {
            $item['links'] = array_merge($item['links'] ?? [], [
                'serpro_authorization' => '/settings/integracao-serpro',
            ]);

            return $item;
        })->values();
    }

    /**
     * Procurações expiradas / ausentes para clientes ativos (sem conteúdo do instrumento).
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function proxyPowerItems(int $officeId): Collection
    {
        $items = collect();

        $expired = TaxProxyPower::query()
            ->where('office_id', $officeId)
            ->where(function ($q) {
                $q->where('status', TaxProxyPowerStatus::Expired)
                    ->orWhere(function ($q2) {
                        $q2->where('status', TaxProxyPowerStatus::Active)
                            ->whereNotNull('valid_to')
                            ->where('valid_to', '<=', now());
                    });
            })
            ->with('client')
            ->orderByDesc('id')
            ->limit(40)
            ->get();

        foreach ($expired as $power) {
            $client = $power->client;
            $item = $this->item(
                type: 'proxy_power_expired',
                title: 'Procuração expirada: '.($client ? $this->clientLabel($client) : 'cliente'),
                body: 'Poder '.$power->power_code.' (serviço '.($power->service_code ?? '—').') exige renovação. Conteúdo da procuração e tokens não são expostos.',
                reasons: ['proxy_expired', 'power:'.$power->power_code],
                clientId: $power->client_id,
                establishmentId: null,
                occurredAt: $power->valid_to?->toIso8601String() ?? $power->updated_at?->toIso8601String() ?? now()->toIso8601String(),
                role: null,
                establishment: null,
                cursor: null,
            );
            $item['id'] = substr(hash('sha256', 'proxy:exp:'.$power->id), 0, 32);
            $item['links'] = array_merge($item['links'] ?? [], [
                'proxy' => '/clients/'.$power->client_id.'/procuracoes',
            ]);
            $items->push($item);
        }

        // Clientes ativos sem nenhuma procuração ACTIVE
        $clientIdsWithActive = TaxProxyPower::query()
            ->where('office_id', $officeId)
            ->where('status', TaxProxyPowerStatus::Active)
            ->where(function ($q) {
                $q->whereNull('valid_to')->orWhere('valid_to', '>', now());
            })
            ->pluck('client_id')
            ->unique()
            ->all();

        $clientsMissing = Client::query()
            ->where('office_id', $officeId)
            ->where('is_active', true)
            ->when($clientIdsWithActive !== [], fn ($q) => $q->whereNotIn('id', $clientIdsWithActive))
            ->orderBy('id')
            ->limit(25)
            ->get();

        // Só alertar ausência se já existe onboarding SERPRO (evita ruído em escritórios só ADN)
        $hasAuth = OfficeSerproAuthorization::query()
            ->where('office_id', $officeId)
            ->whereNotNull('termo_vault_object_id')
            ->exists();

        if ($hasAuth) {
            foreach ($clientsMissing as $client) {
                $item = $this->item(
                    type: 'proxy_power_missing',
                    title: 'Procuração ausente: '.$this->clientLabel($client),
                    body: 'Nenhum poder ACTIVE vinculado. Importe ou sincronize procurações antes de consultas Integra.',
                    reasons: ['proxy_missing'],
                    clientId: $client->id,
                    establishmentId: null,
                    occurredAt: now()->toIso8601String(),
                    role: null,
                    establishment: null,
                    cursor: null,
                );
                $item['id'] = substr(hash('sha256', 'proxy:miss:'.$client->id), 0, 32);
                $item['links'] = array_merge($item['links'] ?? [], [
                    'proxy' => '/clients/'.$client->id.'/procuracoes',
                ]);
                $items->push($item);
            }
        }

        return $items->values();
    }

    /**
     * Fonte indisponível / consulta bloqueada (saúde platform sanitizada + runs blocked).
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function sourceAvailabilityItems(int $officeId): Collection
    {
        $items = collect();
        $env = SerproEnvironment::tryFrom((string) config('serpro.default_environment', 'TRIAL'))
            ?? SerproEnvironment::Trial;

        try {
            $health = $this->integraHealth->forEnvironment($env);
        } catch (\Throwable) {
            $health = ['available' => true, 'kill_switch' => false, 'circuit_open' => false];
        }

        if (! ($health['available'] ?? true) || ($health['kill_switch'] ?? false) || ($health['circuit_open'] ?? false)) {
            $reasons = [];
            if ($health['kill_switch'] ?? false) {
                $reasons[] = 'kill_switch';
            }
            if ($health['circuit_open'] ?? false) {
                $reasons[] = 'circuit_open';
            }
            if (! ($health['available'] ?? true)) {
                $reasons[] = 'platform_unavailable';
            }
            $item = $this->item(
                type: 'source_unavailable',
                title: 'Integra Contador temporariamente indisponível',
                body: 'Indisponibilidade geral sanitizada. Sem métricas ou identidade de outros escritórios. Tente novamente após normalização.',
                reasons: $reasons,
                clientId: null,
                establishmentId: null,
                occurredAt: now()->toIso8601String(),
                role: null,
                establishment: null,
                cursor: null,
            );
            $item['id'] = substr(hash('sha256', 'src:unavail:'.$officeId.':'.implode(',', $reasons)), 0, 32);
            $items->push($item);
        }

        $blockedRuns = FiscalMonitoringRun::query()
            ->where('office_id', $officeId)
            ->where('status', 'BLOCKED')
            ->where('created_at', '>=', now()->subDay())
            ->with('client')
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        foreach ($blockedRuns as $run) {
            $client = $run->client;
            $item = $this->item(
                type: 'query_blocked',
                title: 'Consulta bloqueada: '.($client ? $this->clientLabel($client) : 'cliente'),
                body: $this->sanitizeText($run->error_message)
                    ?? ('Motivo: '.($run->error_code ?? $run->skip_reason ?? 'BLOCKED').'. Serviço '.$run->service_code.'.'),
                reasons: array_values(array_filter([
                    'query_blocked',
                    $run->error_code,
                    $run->skip_reason,
                    'svc:'.$run->service_code,
                ])),
                clientId: $run->client_id,
                establishmentId: null,
                occurredAt: $run->finished_at?->toIso8601String()
                    ?? $run->created_at?->toIso8601String()
                    ?? now()->toIso8601String(),
                role: null,
                establishment: null,
                cursor: null,
            );
            $item['id'] = substr(hash('sha256', 'qblock:'.$run->id), 0, 32);
            $item['links'] = array_merge($item['links'] ?? [], [
                'run' => '/fiscal/runs/'.$run->id,
            ]);
            // Sem retry imediato se for elegibilidade
            $item['actions'] = [
                ['type' => 'open', 'label' => 'Revisar bloqueio'],
            ];
            $items->push($item);
        }

        return $items->values();
    }

    /**
     * Pendências fiscais abertas de severidade alta/crítica.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function fiscalPendingItems(int $officeId): Collection
    {
        $rows = FiscalPendingItem::query()
            ->where('office_id', $officeId)
            ->where('status', FiscalPendingStatus::Open)
            ->whereIn('severity', [
                FiscalFindingSeverity::Critical->value,
                FiscalFindingSeverity::High->value,
                FiscalFindingSeverity::Medium->value,
            ])
            ->with('client')
            ->orderByDesc('id')
            ->limit(40)
            ->get();

        return $rows->map(function (FiscalPendingItem $pending) {
            $sev = match ($pending->severity) {
                FiscalFindingSeverity::Critical => 'critical',
                FiscalFindingSeverity::High => 'high',
                default => 'medium',
            };
            $client = $pending->client;
            $item = $this->item(
                type: 'fiscal_pending',
                title: $this->sanitizeText($pending->title) ?? 'Pendência fiscal',
                body: $this->sanitizeText($pending->detail)
                    ?? ('Código '.($pending->code ?? '—').'. Abrir detalhe do cliente.'),
                reasons: array_values(array_filter([
                    'fiscal_pending',
                    $pending->code,
                    $pending->situation?->value,
                ])),
                clientId: $pending->client_id,
                establishmentId: null,
                occurredAt: $pending->due_at?->toIso8601String()
                    ?? $pending->created_at?->toIso8601String()
                    ?? now()->toIso8601String(),
                role: null,
                establishment: null,
                cursor: null,
            );
            $item['severity'] = $sev;
            $item['id'] = substr(hash('sha256', 'fpend:'.$pending->id), 0, 32);
            $item['links'] = array_merge($item['links'] ?? [], [
                'pending' => '/fiscal/pendencias/'.$pending->id,
                'client' => $pending->client_id ? '/clients/'.$pending->client_id : null,
            ]);
            $item['links'] = array_filter($item['links']);

            return $item;
        })->values();
    }

    /**
     * Guias com vencimento próximo (sem PDF/bytes).
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function guideDueItems(int $officeId): Collection
    {
        $guides = TaxGuide::query()
            ->where('office_id', $officeId)
            ->whereNotNull('due_at')
            ->where('due_at', '<=', now()->addDays(7))
            ->where('due_at', '>=', now()->subDays(3))
            ->whereNotIn('payment_status', [TaxGuidePaymentStatus::Confirmed->value])
            ->with('client')
            ->orderBy('due_at')
            ->limit(30)
            ->get();

        return $guides->map(function (TaxGuide $guide) {
            $client = $guide->client;
            $item = $this->item(
                type: 'guide_due_soon',
                title: 'Guia a vencer: '.($client ? $this->clientLabel($client) : 'cliente'),
                body: 'Serviço '.($guide->service_code ?? '—').' · vencimento '
                    .($guide->due_at?->toDateString() ?? '—')
                    .'. Pagamento: '.($guide->payment_status?->value ?? 'UNKNOWN').'. Sem artefato na inbox.',
                reasons: ['guide_due', 'svc:'.($guide->service_code ?? 'na')],
                clientId: $guide->client_id,
                establishmentId: $guide->establishment_id,
                occurredAt: $guide->due_at?->toIso8601String() ?? now()->toIso8601String(),
                role: null,
                establishment: null,
                cursor: null,
            );
            $item['id'] = substr(hash('sha256', 'guide:due:'.$guide->id), 0, 32);
            $item['links'] = array_merge($item['links'] ?? [], [
                'guide' => '/fiscal/guias/'.$guide->id,
            ]);

            return $item;
        })->values();
    }

    /**
     * Consumo elevado / franquia.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function usageItems(int $officeId): Collection
    {
        $items = collect();

        try {
            $raw = $this->usage->summary($officeId);
            $summary = is_array($raw['summary'] ?? null) ? $raw['summary'] : [];
        } catch (\Throwable) {
            return $items;
        }

        $ratio = $summary['franchise_ratio'] ?? null;
        $alert = (bool) ($summary['alert_threshold_reached'] ?? false);
        $remaining = $summary['remaining'] ?? null;
        $quota = $summary['franchise_quota'] ?? null;

        if ($quota !== null && $remaining !== null && $remaining <= 0) {
            $item = $this->item(
                type: 'usage_franchise_exceeded',
                title: 'Franquia SERPRO esgotada no período',
                body: 'Uso '.($summary['used_quantity'] ?? 0).' de '.$quota
                    .'. Chamadas não essenciais podem ser bloqueadas conforme plano. Detalhe em consumo do escritório.',
                reasons: ['FRANCHISE_EXCEEDED'],
                clientId: null,
                establishmentId: null,
                occurredAt: now()->toIso8601String(),
                role: null,
                establishment: null,
                cursor: null,
            );
            $item['id'] = substr(hash('sha256', 'usage:ex:'.$officeId.':'.($summary['period_year'] ?? '').($summary['period_month'] ?? '')), 0, 32);
            $item['links'] = ['usage' => '/settings/consumo'];
            $items->push($item);
        } elseif ($alert || ($ratio !== null && $ratio >= 0.8)) {
            $pct = $ratio !== null ? (int) round($ratio * 100) : null;
            $item = $this->item(
                type: 'usage_high',
                title: 'Consumo SERPRO próximo do limite',
                body: ($pct !== null ? "Uso em {$pct}% da franquia. " : '')
                    .'Revise o detalhamento do próprio tenant. Sem fatura global ou custo de outros escritórios.',
                reasons: ['USAGE_ALERT', 'threshold'],
                clientId: null,
                establishmentId: null,
                occurredAt: now()->toIso8601String(),
                role: null,
                establishment: null,
                cursor: null,
            );
            $item['id'] = substr(hash('sha256', 'usage:hi:'.$officeId.':'.($summary['period_year'] ?? '').($summary['period_month'] ?? '')), 0, 32);
            $item['links'] = ['usage' => '/settings/consumo'];
            $items->push($item);
        }

        return $items->values();
    }

    /**
     * Mutações / guias com resultado incerto — crítico, sem retry imediato.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function uncertainMutationItems(int $officeId): Collection
    {
        $ops = FiscalMutationOperation::query()
            ->where('office_id', $officeId)
            ->whereIn('status', [
                FiscalMutationStatus::UnknownResult->value,
                FiscalMutationStatus::Reconciling->value,
                FiscalMutationStatus::Sent->value,
            ])
            ->with('client')
            ->orderByDesc('id')
            ->limit(30)
            ->get();

        return $ops->map(function (FiscalMutationOperation $op) {
            $client = $op->client;
            $item = $this->item(
                type: 'mutation_unknown_result',
                title: 'Resultado incerto: '.($op->operation_code ?? 'mutação')
                    .' ('.($client ? $this->clientLabel($client) : 'cliente').')',
                body: 'Estado '.$op->status->value.'. Reconciliação obrigatória antes de nova tentativa. Retry cego bloqueado.',
                reasons: ['UNKNOWN_RESULT', 'status:'.$op->status->value, 'no_blind_retry'],
                clientId: $op->client_id,
                establishmentId: null,
                occurredAt: $op->sent_at?->toIso8601String()
                    ?? $op->updated_at?->toIso8601String()
                    ?? now()->toIso8601String(),
                role: null,
                establishment: null,
                cursor: null,
            );
            $item['id'] = substr(hash('sha256', 'mut:unc:'.$op->id), 0, 32);
            $item['links'] = array_merge($item['links'] ?? [], [
                'mutation' => '/fiscal/mutacoes/'.$op->id,
            ]);
            // Apenas reconciliação — nunca retry
            $item['actions'] = [
                ['type' => 'reconcile', 'label' => 'Reconciliar', 'mutation_id' => $op->id],
                ['type' => 'open', 'label' => 'Abrir'],
            ];

            return $item;
        })->values();
    }

    /**
     * Alertas de parsing / resultado de run com PARSE_ALERT.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function parsingAlertItems(int $officeId): Collection
    {
        $runs = FiscalMonitoringRun::query()
            ->where('office_id', $officeId)
            ->where(function ($q) {
                $q->where('error_code', 'like', '%PARSE%')
                    ->orWhere('result', FiscalRunResult::Partial->value ?? 'PARTIAL')
                    ->orWhere('skip_reason', 'like', '%PARSE%');
            })
            ->where('created_at', '>=', now()->subDays(3))
            ->with('client')
            ->orderByDesc('id')
            ->limit(15)
            ->get();

        // Filtrar só os que realmente indicam parsing (result Partial sozinho é ruidoso)
        $runs = $runs->filter(function (FiscalMonitoringRun $run) {
            $code = strtoupper((string) ($run->error_code ?? ''));
            $skip = strtoupper((string) ($run->skip_reason ?? ''));

            return str_contains($code, 'PARSE')
                || str_contains($skip, 'PARSE')
                || str_contains($code, 'SCHEMA')
                || str_contains($code, 'XSD');
        });

        return $runs->map(function (FiscalMonitoringRun $run) {
            $client = $run->client;
            $item = $this->item(
                type: 'parsing_alert',
                title: 'Alerta de parsing: '.($client ? $this->clientLabel($client) : 'run #'.$run->id),
                body: $this->sanitizeText($run->error_message)
                    ?? 'Resposta oficial com schema/parsing incompleto. Evidência preservada quando bem-formada.',
                reasons: array_values(array_filter(['parsing', $run->error_code, $run->skip_reason])),
                clientId: $run->client_id,
                establishmentId: null,
                occurredAt: $run->finished_at?->toIso8601String()
                    ?? $run->created_at?->toIso8601String()
                    ?? now()->toIso8601String(),
                role: null,
                establishment: null,
                cursor: null,
            );
            $item['id'] = substr(hash('sha256', 'parse:'.$run->id), 0, 32);

            return $item;
        })->values();
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

        return mb_substr(LogSanitizer::scrubString($text), 0, 280);
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
