<?php

namespace App\Services\Import;

use App\Contracts\SecureObjectStore;
use App\Enums\AdnDocumentType;
use App\Enums\DocumentDirection;
use App\Enums\DocumentKind;
use App\Enums\FiscalRole;
use App\Models\DfeDocument;
use App\Models\DocumentInterest;
use App\Models\Establishment;
use App\Models\NfeDocument;
use App\Services\Sefaz\NfeXmlProjectionParser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use ZipArchive;

/**
 * Ingestão de XML de saída (NF-e / NFC-e) — vault + projeção OUT.
 * Não transmite à SEFAZ; apenas armazena XML já autorizado.
 */
final class OutboundXmlIngestionService
{
    public function __construct(
        private readonly SecureObjectStore $store,
        private readonly NfeXmlProjectionParser $parser,
    ) {}

    /**
     * @param  list<UploadedFile>  $files
     * @return array{
     *   imported: int,
     *   skipped: int,
     *   errors: int,
     *   items: list<array{status: string, filename: string, access_key?: string, kind?: string, message?: string, sha256?: string}>
     * }
     */
    public function ingestUploads(int $officeId, ?int $clientId, array $files): array
    {
        $items = [];
        $imported = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($files as $file) {
            $name = $file->getClientOriginalName() ?: 'upload.bin';
            $bytes = @file_get_contents($file->getRealPath() ?: '');
            if ($bytes === false || $bytes === '') {
                $errors++;
                $items[] = ['status' => 'error', 'filename' => $name, 'message' => 'Arquivo vazio ou ilegível.'];

                continue;
            }

            if ($this->looksLikeZip($name, $bytes)) {
                $nested = $this->ingestZipBytes($officeId, $clientId, $bytes, $name);
                foreach ($nested as $row) {
                    $items[] = $row;
                    match ($row['status']) {
                        'imported' => $imported++,
                        'duplicate', 'skipped' => $skipped++,
                        default => $errors++,
                    };
                }

                continue;
            }

            $row = $this->ingestXmlBytes($officeId, $clientId, $bytes, $name);
            $items[] = $row;
            match ($row['status']) {
                'imported' => $imported++,
                'duplicate', 'skipped' => $skipped++,
                default => $errors++,
            };
        }

        return [
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors,
            'items' => $items,
        ];
    }

    /**
     * @return list<array{status: string, filename: string, access_key?: string, kind?: string, message?: string, sha256?: string}>
     */
    private function ingestZipBytes(int $officeId, ?int $clientId, string $zipBytes, string $zipName): array
    {
        $tmp = tempnam(sys_get_temp_dir(), 'xmlzip');
        if ($tmp === false) {
            return [['status' => 'error', 'filename' => $zipName, 'message' => 'Falha ao criar temp.']];
        }

        try {
            file_put_contents($tmp, $zipBytes);
            $zip = new ZipArchive;
            if ($zip->open($tmp) !== true) {
                return [['status' => 'error', 'filename' => $zipName, 'message' => 'ZIP inválido.']];
            }

            $out = [];
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entry = $zip->getNameIndex($i);
                if ($entry === false || str_ends_with($entry, '/')) {
                    continue;
                }
                $base = basename($entry);
                if (str_starts_with($base, '.')) {
                    continue;
                }
                if (! preg_match('/\.xml$/i', $base)) {
                    continue;
                }
                $content = $zip->getFromIndex($i);
                if ($content === false || $content === '') {
                    $out[] = ['status' => 'error', 'filename' => $zipName.'/'.$base, 'message' => 'Entrada ZIP vazia.'];

                    continue;
                }
                $out[] = $this->ingestXmlBytes($officeId, $clientId, $content, $zipName.'/'.$base);
            }
            $zip->close();

            if ($out === []) {
                return [['status' => 'error', 'filename' => $zipName, 'message' => 'ZIP sem arquivos XML.']];
            }

            return $out;
        } finally {
            @unlink($tmp);
        }
    }

    /**
     * @return array{status: string, filename: string, access_key?: string, kind?: string, message?: string, sha256?: string}
     */
    public function ingestXmlBytes(int $officeId, ?int $clientId, string $bytes, string $filename): array
    {
        $sha = hash('sha256', $bytes);

        $existing = DfeDocument::query()
            ->where('office_id', $officeId)
            ->where('sha256', $sha)
            ->first();

        if ($existing !== null) {
            return [
                'status' => 'duplicate',
                'filename' => $filename,
                'access_key' => $existing->access_key,
                'sha256' => $sha,
                'message' => 'SHA-256 já existe no escritório (idempotente).',
            ];
        }

        $family = $this->detectSchemaFamily($bytes);
        if ($family === null) {
            return [
                'status' => 'error',
                'filename' => $filename,
                'sha256' => $sha,
                'message' => 'XML não reconhecido como NF-e/NFC-e (procNFe/NFe).',
            ];
        }

        $parsed = $this->parser->parse($bytes, $family);
        $accessKey = $parsed['access_key'] ?? null;
        if (! $accessKey) {
            return [
                'status' => 'error',
                'filename' => $filename,
                'sha256' => $sha,
                'message' => 'Chave de acesso não extraída do XML.',
            ];
        }

        $model = (string) ($parsed['model'] ?? '55');
        $kind = $model === '65' ? DocumentKind::Nfce : DocumentKind::Nfe;
        $issuer = isset($parsed['issuer_cnpj']) ? strtoupper((string) $parsed['issuer_cnpj']) : null;

        // Estabelecimento emitente (vínculo de interesse / validação de cliente).
        $establishmentQuery = Establishment::query()
            ->whereHas('client', fn ($q) => $q->where('office_id', $officeId));
        if ($clientId !== null) {
            $establishmentQuery->where('client_id', $clientId);
        }
        if ($issuer === null || $issuer === '') {
            return [
                'status' => 'error',
                'filename' => $filename,
                'access_key' => $accessKey,
                'message' => 'CNPJ emitente ausente no XML.',
            ];
        }
        $establishment = (clone $establishmentQuery)->where('cnpj', $issuer)->first();
        if ($clientId !== null && $establishment === null) {
            return [
                'status' => 'error',
                'filename' => $filename,
                'access_key' => $accessKey,
                'message' => 'Emitente do XML não corresponde a estabelecimento do cliente.',
            ];
        }
        // Sem client_id: ainda tenta amarrar ao emitente do office (se existir).
        if ($establishment === null) {
            $establishment = Establishment::query()
                ->whereHas('client', fn ($q) => $q->where('office_id', $officeId))
                ->where('cnpj', $issuer)
                ->first();
        }

        // Não sobrescrever captura DistDFe de entrada com import de saída na mesma chave.
        $existingFull = NfeDocument::query()
            ->where('office_id', $officeId)
            ->where('access_key', $accessKey)
            ->where('is_summary', false)
            ->first();
        if ($existingFull !== null) {
            $existingDir = $existingFull->direction?->value ?? null;
            $isImport = str_starts_with((string) $existingFull->schema_hint, 'import:');
            if (! $isImport && $existingDir === DocumentDirection::In->value) {
                return [
                    'status' => 'error',
                    'filename' => $filename,
                    'access_key' => $accessKey,
                    'message' => 'Já existe XML completo de entrada (DistDFe) para esta chave; import de saída recusado.',
                ];
            }
            if ($isImport || $existingDir === DocumentDirection::Out->value) {
                // Re-import da mesma saída: só se sha mudar o dfe; projeção já OUT
                $existingDfe = $existingFull->document;
                if ($existingDfe && $existingDfe->sha256 === $sha) {
                    return [
                        'status' => 'duplicate',
                        'filename' => $filename,
                        'access_key' => $accessKey,
                        'sha256' => $sha,
                        'message' => 'SHA-256 já existe no escritório (idempotente).',
                    ];
                }
            }
        }

        try {
            DB::transaction(function () use (
                $officeId,
                $bytes,
                $sha,
                $accessKey,
                $parsed,
                $family,
                $model,
                $establishment,
            ): void {
                $existingDfe = DfeDocument::query()
                    ->where('office_id', $officeId)
                    ->where('sha256', $sha)
                    ->first();
                if ($existingDfe !== null) {
                    $dfe = $existingDfe;
                } else {
                    $objectId = $this->store->put($bytes, [
                        'office_id' => $officeId,
                        'sha256' => $sha,
                    ]);
                    $dfe = DfeDocument::query()->create([
                        'office_id' => $officeId,
                        'sha256' => $sha,
                        'document_type' => AdnDocumentType::Nfe,
                        'schema_version' => $family === 'procNFe' ? 'procNFe_v4.00.xsd' : 'NFe_import.xml',
                        'access_key' => $accessKey,
                        'vault_object_id' => $objectId,
                        'byte_size' => strlen($bytes),
                        'parse_status' => 'OK',
                        'parse_alert' => 'Importação manual de saída (não DistDFe).',
                    ]);
                }

                NfeDocument::query()->updateOrCreate(
                    [
                        'office_id' => $officeId,
                        'access_key' => $accessKey,
                        'is_summary' => false,
                    ],
                    [
                        'dfe_document_id' => $dfe->id,
                        'number' => $parsed['number'] ?? null,
                        'series' => $parsed['series'] ?? null,
                        'model' => $model,
                        'issuer_cnpj' => $parsed['issuer_cnpj'] ?? null,
                        'issuer_name' => $parsed['issuer_name'] ?? null,
                        'recipient_cnpj' => $parsed['recipient_cnpj'] ?? null,
                        'recipient_name' => $parsed['recipient_name'] ?? null,
                        'fiscal_role' => FiscalRole::Issuer,
                        'direction' => DocumentDirection::Out,
                        'issued_at' => $parsed['issued_at'] ?? null,
                        'total_amount' => $parsed['total_amount'] ?? null,
                        'status' => $parsed['status'] ?? 'ACTIVE',
                        'official_status_code' => $parsed['official_status_code'] ?? null,
                        'manifestation_status' => null,
                        'schema_hint' => 'import:'.$family,
                    ]
                );

                if ($establishment !== null) {
                    // NSU sintético estável por chave (canal IMPORT_XML, sem colidir DistDFe).
                    $syntheticNsu = (int) sprintf('%u', crc32('import:'.$accessKey));
                    DocumentInterest::query()->firstOrCreate(
                        [
                            'establishment_id' => $establishment->id,
                            'environment' => 'import',
                            'channel' => 'IMPORT_XML',
                            'nsu' => $syntheticNsu,
                        ],
                        [
                            'office_id' => $officeId,
                            'dfe_document_id' => $dfe->id,
                            'fiscal_role' => FiscalRole::Issuer->value,
                        ]
                    );
                }
            });
        } catch (\Throwable $e) {
            Log::warning('documents.import.persist_failed', [
                'access_key' => $accessKey,
                'sha256' => $sha,
                'error' => $e->getMessage(),
            ]);

            return [
                'status' => 'error',
                'filename' => $filename,
                'access_key' => $accessKey,
                'sha256' => $sha,
                'message' => 'Falha ao persistir o XML importado.',
            ];
        }

        return [
            'status' => 'imported',
            'filename' => $filename,
            'access_key' => $accessKey,
            'kind' => $kind->value,
            'sha256' => $sha,
        ];
    }

    private function looksLikeZip(string $name, string $bytes): bool
    {
        if (preg_match('/\.zip$/i', $name)) {
            return true;
        }

        return str_starts_with($bytes, "PK\x03\x04") || str_starts_with($bytes, "PK\x05\x06");
    }

    private function detectSchemaFamily(string $xml): ?string
    {
        if (! str_contains($xml, '<') || ! str_contains(strtolower($xml), 'nfe')) {
            return null;
        }
        if (preg_match('/<(?:\w+:)?nfeProc\b/i', $xml) || preg_match('/local-name\(\)\s*=\s*[\'"]nfeProc/i', $xml)) {
            return 'procNFe';
        }
        if (preg_match('/<(?:\w+:)?NFe\b/i', $xml)) {
            return 'procNFe'; // parser de projeção cobre NFe embutida via mesmos XPaths
        }
        if (preg_match('/<(?:\w+:)?resNFe\b/i', $xml)) {
            return 'resNFe';
        }

        return null;
    }
}
