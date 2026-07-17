<?php

namespace App\Services\Fiscal\SimplesMei\Pgdasd;

use App\Enums\PgdasdDocumentKind;
use App\Models\FiscalEvidenceArtifact;
use App\Models\FiscalMonitoringRun;
use App\Models\PgdasdArtifact;
use App\Services\FiscalMonitoring\FiscalEvidenceStore;
use Carbon\CarbonImmutable;
use RuntimeException;

/**
 * Decodifica Base64 estrito, valida %PDF + limite, grava no cofre e devolve descritor sanitizado.
 */
final class PgdasdDocumentSanitizer
{
    public const MAX_PDF_BYTES = 10 * 1024 * 1024;

    public function __construct(
        private readonly FiscalEvidenceStore $evidenceStore,
        private readonly PgdasdDocumentCodecs $codecs,
    ) {}

    /**
     * @return array{
     *   sanitized_dados: array<string, mixed>,
     *   artifacts: list<PgdasdArtifact>,
     *   evidence: list<FiscalEvidenceArtifact>,
     *   failures: list<array{field: string, reason: string}>
     * }
     */
    public function sanitizeAndStore(
        FiscalMonitoringRun $run,
        int $clientId,
        mixed $dados,
        string $operationKey,
        int $projectionId,
        ?string $periodKey = null,
        ?string $periodoApuracao = null,
        ?string $numeroDas = null,
        ?int $pgdasdOperationId = null,
    ): array {
        $docs = $this->codecs->extractDocumentFields($dados, $operationKey);
        $descriptors = [];
        $artifacts = [];
        $evidence = [];
        $failures = [];
        $observed = CarbonImmutable::now();

        foreach ($docs as $doc) {
            try {
                $bytes = $this->decodeStrictBase64($doc['base64']);
                $this->assertPdf($bytes);

                $artifact = $this->evidenceStore->store(
                    run: $run,
                    bytes: $bytes,
                    contentType: 'application/pdf',
                    source: 'PGDASD_'.$operationKey,
                    sourceVersion: '1',
                    observedAt: $observed,
                );
                $evidence[] = $artifact;

                $kind = $doc['kind'] instanceof PgdasdDocumentKind
                    ? $doc['kind']->value
                    : (string) $doc['kind'];

                $pgArtifact = PgdasdArtifact::query()->create([
                    'office_id' => $run->office_id,
                    'client_id' => $clientId,
                    'projection_id' => $projectionId,
                    'operation_id' => $pgdasdOperationId,
                    'fiscal_evidence_artifact_id' => $artifact->id,
                    'declaration_number' => $doc['numero_declaracao'],
                    'das_number' => $numeroDas,
                    'kind' => $kind,
                    'filename' => $doc['filename_hint'] ?: ('pgdasd-'.$kind.'-'.$artifact->id.'.pdf'),
                    'content_type' => 'application/pdf',
                    'observed_at' => $observed,
                    'source_run_id' => $run->id,
                    'metadata' => [
                        'field' => $doc['field'],
                        'source_operation_key' => $operationKey,
                        'period_key' => $periodKey,
                        'periodo_apuracao' => $periodoApuracao,
                        'sha256' => $artifact->content_sha256,
                        'byte_size' => $artifact->byte_size,
                    ],
                ]);
                $artifacts[] = $pgArtifact;

                $descriptors[$doc['field']] = [
                    'sanitized' => true,
                    'document_kind' => $kind,
                    'pgdasd_artifact_id' => $pgArtifact->id,
                    'evidence_artifact_id' => $artifact->id,
                    'content_type' => 'application/pdf',
                    'byte_size' => $artifact->byte_size,
                    'content_sha256' => $artifact->content_sha256,
                ];
            } catch (\Throwable $e) {
                $failures[] = [
                    'field' => $doc['field'],
                    'reason' => $e->getMessage(),
                ];
                $descriptors[$doc['field']] = [
                    'sanitized' => true,
                    'failed' => true,
                    'reason' => 'DOCUMENT_SANITIZE_FAILED',
                ];
            }
        }

        return [
            'sanitized_dados' => $this->codecs->sanitizeDados($dados, $descriptors),
            'artifacts' => $artifacts,
            'evidence' => $evidence,
            'failures' => $failures,
        ];
    }

    public function decodeStrictBase64(string $base64): string
    {
        $clean = preg_replace('/\s+/', '', $base64) ?? '';
        if ($clean === '' || ! preg_match('/^[A-Za-z0-9+\/]+=*$/', $clean)) {
            throw new RuntimeException('Base64 inválido (não estrito).');
        }
        $decoded = base64_decode($clean, true);
        if ($decoded === false) {
            throw new RuntimeException('Falha ao decodificar Base64.');
        }
        if (strlen($decoded) > self::MAX_PDF_BYTES) {
            throw new RuntimeException('PDF excede limite de '.self::MAX_PDF_BYTES.' bytes.');
        }

        return $decoded;
    }

    public function assertPdf(string $bytes): void
    {
        if ($bytes === '' || ! str_starts_with($bytes, '%PDF')) {
            throw new RuntimeException('Conteúdo não começa com assinatura %PDF.');
        }
    }
}
