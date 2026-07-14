<?php

namespace App\Services\Adn;

use App\Contracts\SecureObjectStore;
use App\Domain\Adn\DistributionDocumentDto;
use App\Domain\Adn\DistributionPageDto;
use App\Enums\AdnDocumentType;
use App\Enums\FiscalRole;
use App\Enums\SyncCursorStatus;
use App\Exceptions\Adn\AdnInvalidResponseException;
use App\Exceptions\Adn\DocumentDecodeException;
use App\Models\DfeDocument;
use App\Models\DocumentInterest;
use App\Models\Establishment;
use App\Models\NfseEvent;
use App\Models\NfseNote;
use App\Models\SyncCursor;
use Illuminate\Support\Facades\DB;
use Throwable;

final class DistributionPageProcessor
{
    public function __construct(
        private readonly DocumentDecoder $decoder,
        private readonly NfseXmlParser $parser,
        private readonly SecureObjectStore $store,
    ) {}

    /**
     * Persiste a página inteira e só então avança o NSU.
     *
     * @return array{documents: int, advanced_to: int}
     */
    public function process(SyncCursor $cursor, Establishment $establishment, DistributionPageDto $page): array
    {
        try {
            return DB::transaction(function () use ($cursor, $establishment, $page) {
                $cursor = SyncCursor::query()->whereKey($cursor->id)->lockForUpdate()->firstOrFail();
                $this->assertPageCanAdvance($cursor, $page);
                $persisted = 0;

                foreach ($page->documents as $doc) {
                    $this->persistDocument($cursor, $establishment, $doc);
                    $persisted++;
                }

                // cStat 137 preserva o cursor; cStat 138 foi validado item a item.
                $newNsu = $page->status === '137'
                    ? $cursor->last_nsu
                    : $page->ultimoNsu;
                $cursor->last_nsu = $newNsu;
                $cursor->last_success_at = now();
                $cursor->last_error = null;
                $cursor->consecutive_decode_failures = 0;
                $cursor->save();

                return ['documents' => $persisted, 'advanced_to' => $newNsu];
            });
        } catch (Throwable $e) {
            if ($e instanceof DocumentDecodeException) {
                // Fora da transação: não avança NSU e registra falha de decode
                $fresh = SyncCursor::query()->whereKey($cursor->id)->first();
                if ($fresh) {
                    $fresh->consecutive_decode_failures++;
                    $fresh->last_error = $e->getMessage();
                    if ($fresh->consecutive_decode_failures >= (int) config('adn.decode_failure_threshold', 5)) {
                        $fresh->status = SyncCursorStatus::Blocked;
                    }
                    $fresh->save();
                }
            }
            throw $e;
        }
    }

    private function assertPageCanAdvance(SyncCursor $cursor, DistributionPageDto $page): void
    {
        if ($page->status === '137') {
            if ($page->documents !== [] || $page->hasMore) {
                throw new AdnInvalidResponseException;
            }

            return;
        }

        if ($page->status !== '138'
            || $page->documents === []
            || $page->ultimoNsu <= $cursor->last_nsu
            || $page->maxNsu < $page->ultimoNsu) {
            throw new AdnInvalidResponseException;
        }

        $nsus = [];
        foreach ($page->documents as $document) {
            if (! $document instanceof DistributionDocumentDto
                || $document->nsu <= $cursor->last_nsu
                || $document->nsu > $page->ultimoNsu
                || $document->schema === ''
                || $document->contentBase64 === '') {
                throw new AdnInvalidResponseException;
            }

            $nsus[] = $document->nsu;
        }

        if (count($nsus) !== count(array_unique($nsus)) || max($nsus) !== $page->ultimoNsu) {
            throw new AdnInvalidResponseException;
        }
    }

    private function persistDocument(SyncCursor $cursor, Establishment $establishment, DistributionDocumentDto $doc): void
    {
        $decoded = $this->decoder->decodeBase64Gzip($doc->contentBase64);
        $bytes = $decoded['bytes'];
        $sha = $decoded['sha256'];

        $existing = DfeDocument::query()
            ->where('office_id', $cursor->office_id)
            ->where('sha256', $sha)
            ->first();

        if ($existing === null) {
            $objectId = $this->store->put($bytes, [
                'office_id' => $cursor->office_id,
                'sha256' => $sha,
            ]);

            $parseStatus = 'OK';
            $parseAlert = null;
            $accessKey = null;
            $fiscalRole = null;

            if ($doc->type === AdnDocumentType::Nfse || $doc->type === AdnDocumentType::Unknown) {
                $parsed = $this->parser->parseNote($bytes);
                $accessKey = $parsed['access_key'];
                $parseStatus = $parsed['parse_status'];
                $parseAlert = $parsed['parse_alert'];
                $fiscalRole = ($parsed['fiscal_role_for'])($establishment->cnpj);
            } elseif ($doc->type === AdnDocumentType::Event) {
                $parsed = $this->parser->parseEvent($bytes);
                $accessKey = $parsed['access_key'];
                $parseStatus = $parsed['parse_status'];
                $parseAlert = $parsed['parse_alert'];
            }

            $existing = DfeDocument::query()->create([
                'office_id' => $cursor->office_id,
                'sha256' => $sha,
                'document_type' => $doc->type,
                'schema_version' => $doc->schema,
                'access_key' => $accessKey,
                'vault_object_id' => $objectId,
                'byte_size' => strlen($bytes),
                'parse_status' => $parseStatus,
                'parse_alert' => $parseAlert,
            ]);

            if ($doc->type === AdnDocumentType::Nfse && isset($parsed) && is_array($parsed) && ($parsed['access_key'] ?? null)) {
                NfseNote::query()->firstOrCreate(
                    [
                        'office_id' => $cursor->office_id,
                        'access_key' => $parsed['access_key'],
                    ],
                    [
                        'dfe_document_id' => $existing->id,
                        'issuer_cnpj' => $parsed['issuer_cnpj'],
                        'taker_cnpj' => $parsed['taker_cnpj'],
                        'intermediary_cnpj' => $parsed['intermediary_cnpj'],
                        'fiscal_role' => $fiscalRole,
                        'competence' => $parsed['competence'],
                        'issued_at' => $parsed['issued_at'],
                        'service_amount' => $parsed['service_amount'],
                        'status' => $parsed['status'],
                    ]
                );
            }

            if ($doc->type === AdnDocumentType::Event && isset($parsed) && is_array($parsed)) {
                NfseEvent::query()->create([
                    'office_id' => $cursor->office_id,
                    'dfe_document_id' => $existing->id,
                    'access_key' => $parsed['access_key'] ?? '',
                    'event_type' => $parsed['event_type'] ?? null,
                    'event_at' => $parsed['event_at'] ?? null,
                    'status' => $parsed['status'] ?? null,
                ]);
            }
        } else {
            // Papel fiscal é por estabelecimento (interesse), não o da nota original.
            $fiscalRole = $this->fiscalRoleForEstablishment($existing, $establishment);
        }

        DocumentInterest::query()->firstOrCreate(
            [
                'establishment_id' => $establishment->id,
                'environment' => $cursor->environment,
                'nsu' => $doc->nsu,
            ],
            [
                'office_id' => $cursor->office_id,
                'dfe_document_id' => $existing->id,
                'fiscal_role' => $fiscalRole ?? null,
            ]
        );
    }

    private function fiscalRoleForEstablishment(DfeDocument $document, Establishment $establishment): ?FiscalRole
    {
        $note = $document->note;
        if ($note === null) {
            return null;
        }

        $cnpj = strtoupper($establishment->cnpj);

        if ($note->issuer_cnpj === $cnpj) {
            return FiscalRole::Issuer;
        }
        if ($note->taker_cnpj === $cnpj) {
            return FiscalRole::Taker;
        }
        if ($note->intermediary_cnpj === $cnpj) {
            return FiscalRole::Intermediary;
        }

        return null;
    }
}
