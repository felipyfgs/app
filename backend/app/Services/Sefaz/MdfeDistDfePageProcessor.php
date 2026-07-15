<?php

namespace App\Services\Sefaz;

use App\Contracts\SecureObjectStore;
use App\Domain\Sefaz\DistDfeDocumentDto;
use App\Domain\Sefaz\DistDfePageDto;
use App\Enums\AdnDocumentType;
use App\Enums\CaptureChannel;
use App\Enums\DocumentDirection;
use App\Enums\FiscalRole;
use App\Enums\SyncCursorStatus;
use App\Exceptions\Adn\DocumentDecodeException;
use App\Models\ChannelSyncCursor;
use App\Models\DfeDocument;
use App\Models\DocumentInterest;
use App\Models\Establishment;
use App\Models\MdfeDocument;
use App\Services\Adn\DocumentDecoder;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Persiste lote MDF-e DistDFe e só então avança NSU.
 */
final class MdfeDistDfePageProcessor
{
    public function __construct(
        private readonly DocumentDecoder $decoder,
        private readonly MdfeXmlProjectionParser $parser,
        private readonly SecureObjectStore $store,
        private readonly DistDfeResponseParser $schemaHelper,
    ) {}

    /**
     * @return array{documents: int, advanced_to: int}
     */
    public function process(
        ChannelSyncCursor $cursor,
        Establishment $establishment,
        DistDfePageDto $page,
    ): array {
        if ($page->isAbuse()) {
            $cursor->status = SyncCursorStatus::Blocked;
            $cursor->last_cstat = $page->cStat;
            $cursor->last_xmotivo = mb_substr($page->xMotivo, 0, 255);
            $cursor->last_error = 'Consumo indevido SEFAZ MDF-e (cStat 656). Aguardar ≥1h.';
            $cursor->next_sync_at = now()->addHours((float) config('sefaz.quiet_hours_after_empty', 1));
            $cursor->save();

            return ['documents' => 0, 'advanced_to' => (int) $cursor->last_nsu];
        }

        try {
            return DB::transaction(function () use ($cursor, $establishment, $page) {
                $cursor = ChannelSyncCursor::query()->whereKey($cursor->id)->lockForUpdate()->firstOrFail();
                $persisted = 0;

                foreach ($page->documents as $doc) {
                    $this->persistDocument($cursor, $establishment, $doc);
                    $persisted++;
                }

                $newNsu = $page->isEmpty()
                    ? (int) $cursor->last_nsu
                    : max((int) $cursor->last_nsu, $page->ultNsu);

                $cursor->last_nsu = $newNsu;
                if ($page->maxNsu > 0) {
                    $cursor->max_nsu_seen = max((int) ($cursor->max_nsu_seen ?? 0), $page->maxNsu);
                }
                $cursor->last_cstat = $page->cStat;
                $cursor->last_xmotivo = mb_substr($page->xMotivo, 0, 255);
                $cursor->last_success_at = now();
                $cursor->last_error = null;
                $cursor->consecutive_decode_failures = 0;

                if ($page->isEndOfQueue()) {
                    $cursor->status = SyncCursorStatus::Idle;
                    $cursor->next_sync_at = now()->addHours((float) config('sefaz.quiet_hours_after_empty', 1));
                }

                $cursor->save();

                return ['documents' => $persisted, 'advanced_to' => $newNsu];
            });
        } catch (Throwable $e) {
            if ($e instanceof DocumentDecodeException) {
                $fresh = ChannelSyncCursor::query()->whereKey($cursor->id)->first();
                if ($fresh) {
                    $fresh->consecutive_decode_failures++;
                    $fresh->last_error = $e->getMessage();
                    $threshold = (int) config('sefaz.decode_failure_threshold', 5);
                    if ($fresh->consecutive_decode_failures >= $threshold) {
                        $fresh->status = SyncCursorStatus::Blocked;
                    }
                    $fresh->save();
                }
            }
            throw $e;
        }
    }

    private function persistDocument(
        ChannelSyncCursor $cursor,
        Establishment $establishment,
        DistDfeDocumentDto $doc,
    ): void {
        $decoded = $this->decoder->decodeBase64Gzip($doc->contentBase64);
        $family = $doc->schemaFamily !== 'unknown'
            ? $doc->schemaFamily
            : $this->schemaHelper->schemaFamily($doc->schema);

        $isEvent = $family === 'procEventoMDFe';
        $parsed = $this->parser->parse($decoded['bytes'], $family, $establishment->cnpj);
        $accessKey = $parsed['access_key'] ?? null;
        $docType = $isEvent ? AdnDocumentType::Unknown : AdnDocumentType::Mdfe;

        $dfe = DfeDocument::query()->firstOrCreate(
            [
                'office_id' => $cursor->office_id,
                'sha256' => $decoded['sha256'],
            ],
            [
                'document_type' => $docType,
                'schema_version' => $doc->schema,
                'access_key' => $accessKey,
                'vault_object_id' => $this->store->put($decoded['bytes'], [
                    'office_id' => $cursor->office_id,
                    'sha256' => $decoded['sha256'],
                ]),
                'byte_size' => strlen($decoded['bytes']),
                'parse_status' => $accessKey ? 'OK' : 'REVIEW',
                'parse_alert' => $accessKey ? null : 'Chave MDF-e não extraída; XML preservado.',
            ]
        );

        DocumentInterest::query()->firstOrCreate(
            [
                'establishment_id' => $establishment->id,
                'environment' => $cursor->environment,
                'channel' => CaptureChannel::MdfeDistDfe->value,
                'nsu' => $doc->nsu,
            ],
            [
                'office_id' => $cursor->office_id,
                'dfe_document_id' => $dfe->id,
                'fiscal_role' => ($parsed['fiscal_role'] ?? FiscalRole::Taker)?->value
                    ?? FiscalRole::Taker->value,
            ]
        );

        if ($isEvent) {
            if ($accessKey && ($parsed['status'] ?? null) === 'CANCELLED') {
                MdfeDocument::query()
                    ->where('office_id', $cursor->office_id)
                    ->where('access_key', $accessKey)
                    ->update(['status' => 'CANCELLED']);
            }

            return;
        }

        if (! $accessKey) {
            return;
        }

        $isSummary = (bool) ($parsed['is_summary'] ?? false);
        $role = $parsed['fiscal_role'] ?? FiscalRole::Taker;
        $direction = $parsed['direction'] ?? DocumentDirection::In;

        MdfeDocument::query()->updateOrCreate(
            [
                'office_id' => $cursor->office_id,
                'access_key' => $accessKey,
                'is_summary' => $isSummary,
            ],
            [
                'dfe_document_id' => $dfe->id,
                'number' => $parsed['number'] ?? null,
                'series' => $parsed['series'] ?? null,
                'model' => $parsed['model'] ?? '58',
                'issuer_cnpj' => $parsed['issuer_cnpj'] ?? null,
                'issuer_name' => $parsed['issuer_name'] ?? null,
                'fiscal_role' => $role,
                'direction' => $direction,
                'issued_at' => $parsed['issued_at'] ?? null,
                'total_amount' => $parsed['total_amount'] ?? null,
                'status' => $parsed['status'] ?? 'UNKNOWN',
                'official_status_code' => $parsed['official_status_code'] ?? null,
                'schema_hint' => $doc->schema,
            ]
        );
    }
}
