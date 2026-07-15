<?php

namespace App\Services\Outbound;

use App\Contracts\SecureObjectStore;
use App\Enums\AdnDocumentType;
use App\Enums\CaptureChannel;
use App\Enums\DocumentAcquisitionSource;
use App\Enums\DocumentDirection;
use App\Enums\DocumentPurpose;
use App\Enums\FiscalRole;
use App\Enums\OutboundNumberStatus;
use App\Enums\SvrsNfceFailureReason;
use App\Enums\SvrsNfceRecoveryStatus;
use App\Models\DfeDocument;
use App\Models\DocumentAcquisition;
use App\Models\Establishment;
use App\Models\MaOutboundRetrievalRequest;
use App\Models\NfeDocument;
use App\Models\OutboundCaptureProfile;
use App\Models\OutboundNumberState;
use App\Services\Audit\AuditLogger;
use App\Services\Sefaz\NfeXmlProjectionParser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Ingestão automática de bytes SVRS — vault → acquisition → projeção → recovery.
 * Não passa por DTO de upload humano; não emite/cancela/inutiliza.
 */
final class SvrsNfceXmlIngestionService
{
    public function __construct(
        private readonly SecureObjectStore $store,
        private readonly NfeXmlProjectionParser $parser,
        private readonly SvrsNfceXmlValidator $validator,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @return array{
     *   status: string,
     *   sha256?: string,
     *   dfe_document_id?: int,
     *   failure_reason?: SvrsNfceFailureReason,
     *   sanitized_detail?: string,
     * }
     */
    public function ingestValidatedBytes(
        OutboundCaptureProfile $profile,
        Establishment $establishment,
        OutboundNumberState $number,
        MaOutboundRetrievalRequest $request,
        string $xmlBytes,
        string $expectedAccessKey,
        string $correlationId,
    ): array {
        $validated = $this->validator->validate(
            $xmlBytes,
            $expectedAccessKey,
            $establishment,
            (string) $profile->environment,
        );

        if ($validated['failure_reason'] !== null) {
            return [
                'status' => 'rejected',
                'sha256' => $validated['sha256'],
                'failure_reason' => $validated['failure_reason'],
                'sanitized_detail' => $validated['sanitized_detail'] ?? 'Validação falhou.',
            ];
        }

        $sha = $validated['sha256'];
        $key = $validated['access_key'];
        $officeId = (int) $profile->office_id;

        return DB::transaction(function () use (
            $profile, $establishment, $number, $request, $xmlBytes, $sha, $key, $officeId, $validated, $correlationId
        ) {
            // Idempotência: mesma chave+hash
            $existingSame = DfeDocument::query()
                ->where('office_id', $officeId)
                ->where('access_key', $key)
                ->where('sha256', $sha)
                ->first();

            if ($existingSame !== null) {
                DocumentAcquisition::query()->firstOrCreate(
                    [
                        'dfe_document_id' => $existingSame->id,
                        'source' => DocumentAcquisitionSource::SvrsNfceDownloadXmlDfe->value,
                        'sha256' => $sha,
                    ],
                    [
                        'office_id' => $officeId,
                        'access_key' => $key,
                        'channel' => CaptureChannel::MaOutbound->value,
                        'is_canonical' => true,
                        'establishment_id' => $establishment->id,
                        'ma_outbound_retrieval_request_id' => $request->id,
                        'outbound_number_state_id' => $number->id,
                        'metadata' => [
                            'correlation_id' => $correlationId,
                            'signer_fingerprint' => $validated['signer_fingerprint'],
                        ],
                    ]
                );

                $this->markCaptured($number, $request, $key, $sha, $existingSame->id);

                return [
                    'status' => 'duplicate',
                    'sha256' => $sha,
                    'dfe_document_id' => $existingSame->id,
                ];
            }

            // Divergência de bytes
            $other = DfeDocument::query()
                ->where('office_id', $officeId)
                ->where('access_key', $key)
                ->where('sha256', '!=', $sha)
                ->first();

            // Vault primeiro
            $objectId = $this->store->put($xmlBytes, [
                'office_id' => $officeId,
                'sha256' => $sha,
                'kind' => 'svrs_nfce_xml',
            ]);

            $doc = DfeDocument::query()->create([
                'office_id' => $officeId,
                'sha256' => $sha,
                'document_type' => AdnDocumentType::Nfe,
                'schema_version' => 'procNFe_v4.00.xsd',
                'access_key' => $key,
                'vault_object_id' => $objectId,
                'byte_size' => strlen($xmlBytes),
                'parse_status' => $other ? 'QUARANTINE' : 'OK',
                'parse_alert' => $other ? 'Mesma chave com bytes divergentes (SVRS)' : null,
            ]);

            $isCanonical = $other === null;

            DocumentAcquisition::query()->create([
                'office_id' => $officeId,
                'dfe_document_id' => $doc->id,
                'access_key' => $key,
                'source' => DocumentAcquisitionSource::SvrsNfceDownloadXmlDfe,
                'channel' => CaptureChannel::MaOutbound,
                'sha256' => $sha,
                'is_canonical' => $isCanonical,
                'bytes_diverge_from_canonical' => ! $isCanonical,
                'quarantine_reason' => $isCanonical ? null : 'DIVERGENT_BYTES',
                'establishment_id' => $establishment->id,
                'ma_outbound_retrieval_request_id' => $request->id,
                'outbound_number_state_id' => $number->id,
                'metadata' => [
                    'correlation_id' => $correlationId,
                    'environment' => $validated['environment'],
                    'signer_fingerprint' => $validated['signer_fingerprint'],
                    'signer_not_before' => $validated['signer_not_before'],
                    'signer_not_after' => $validated['signer_not_after'],
                    // sem HTML/XML bruto, sem NSU
                ],
            ]);

            if (! $isCanonical) {
                $request->forceFill([
                    'recovery_status' => SvrsNfceRecoveryStatus::Blocked,
                    'failure_reason' => SvrsNfceFailureReason::DivergentBytes,
                    'sha256' => $sha,
                    'last_error' => 'Bytes divergentes do canônico',
                ])->save();

                $this->audit->record('svrs_nfce.ingest.divergent', 'FAILURE', $request, [
                    'profile_id' => $profile->id,
                    'correlation_id' => $correlationId,
                    'sha256' => $sha,
                ], null, $officeId);

                return [
                    'status' => 'divergent',
                    'sha256' => $sha,
                    'dfe_document_id' => $doc->id,
                    'failure_reason' => SvrsNfceFailureReason::DivergentBytes,
                    'sanitized_detail' => 'Bytes divergentes — canônico preservado.',
                ];
            }

            $parsed = $this->parser->parse($xmlBytes, 'procNFe');

            NfeDocument::query()->updateOrCreate(
                [
                    'office_id' => $officeId,
                    'access_key' => $key,
                    'is_summary' => false,
                ],
                [
                    'dfe_document_id' => $doc->id,
                    'number' => $parsed['number'] ?? (string) $number->nnf,
                    'series' => $parsed['series'] ?? (string) $number->series,
                    'model' => '65',
                    'issuer_cnpj' => $validated['issuer_cnpj'],
                    'issuer_name' => $parsed['issuer_name'] ?? null,
                    'recipient_cnpj' => $parsed['recipient_cnpj'] ?? null,
                    'recipient_name' => $parsed['recipient_name'] ?? null,
                    'fiscal_role' => FiscalRole::Issuer,
                    'direction' => DocumentDirection::Out,
                    'purpose' => DocumentPurpose::Commercial,
                    'acquisition_source' => DocumentAcquisitionSource::SvrsNfceDownloadXmlDfe,
                    'issued_at' => $parsed['issued_at'] ?? null,
                    'total_amount' => $parsed['total_amount'] ?? null,
                    'status' => $parsed['status'] ?? 'ACTIVE',
                    'official_status_code' => $validated['cstat'],
                    'is_summary' => false,
                    'schema_hint' => 'procNFe',
                ]
            );

            $this->markCaptured($number, $request, $key, $sha, $doc->id);

            $this->audit->record('svrs_nfce.ingest.captured', 'SUCCESS', $request, [
                'profile_id' => $profile->id,
                'correlation_id' => $correlationId,
                'sha256' => $sha,
                'dfe_document_id' => $doc->id,
            ], null, $officeId);

            Log::info('svrs_nfce.ingest.captured', [
                'office_id' => $officeId,
                'profile_id' => $profile->id,
                'correlation_id' => $correlationId,
                'sha256' => $sha,
            ]);

            return [
                'status' => 'captured',
                'sha256' => $sha,
                'dfe_document_id' => $doc->id,
            ];
        });
    }

    private function markCaptured(
        OutboundNumberState $number,
        MaOutboundRetrievalRequest $request,
        string $key,
        string $sha,
        int $dfeId,
    ): void {
        $number->forceFill([
            'status' => OutboundNumberStatus::XmlCaptured,
            'discovered_access_key' => $key,
            'xml_captured_at' => now(),
            'dfe_document_id' => $dfeId,
        ])->save();

        $request->forceFill([
            'recovery_status' => SvrsNfceRecoveryStatus::Captured,
            'status' => \App\Enums\OutboundRetrievalStatus::Ingested,
            'sha256' => $sha,
            'dfe_document_id' => $dfeId,
            'ingested_at' => now(),
            'failure_reason' => null,
            'last_error' => null,
            'next_attempt_at' => null,
        ])->save();
    }
}
