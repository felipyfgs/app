<?php

namespace Tests\Feature\Sefaz;

use App\Enums\CaptureChannel;
use App\Enums\CteCoverageStatus;
use App\Enums\DocumentAcquisitionSource;
use App\Enums\DocumentArtifactQuality;
use App\Enums\DocumentDirection;
use App\Enums\FiscalRole;
use App\Enums\SignatureVerificationResult;
use App\Models\CteCoverageSnapshot;
use App\Models\CteDocument;
use App\Models\CteEvent;
use App\Models\DfeDocument;
use App\Models\DocumentAcquisition;
use App\Models\DocumentInterest;
use App\Models\Office;
use App\Models\OfficeDistributionCursor;
use App\Models\OfficeFiscalIdentity;
use App\Enums\AdnDocumentType;
use App\Enums\OfficeFiscalIdentityStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CteDomainSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_adiciona_colunas_qualidade_e_metadados_cte(): void
    {
        $this->assertTrue(Schema::hasColumn('document_acquisitions', 'artifact_quality'));
        $this->assertTrue(Schema::hasColumn('document_acquisitions', 'signature_result'));
        $this->assertTrue(Schema::hasColumn('cte_documents', 'expeditor_cnpj'));
        $this->assertTrue(Schema::hasColumn('cte_documents', 'receiver_cnpj'));
        $this->assertTrue(Schema::hasColumn('cte_documents', 'effective_taker_cnpj'));
        $this->assertTrue(Schema::hasColumn('cte_documents', 'schema_version'));
        $this->assertTrue(Schema::hasTable('cte_events'));
        $this->assertTrue(Schema::hasTable('cte_coverage_snapshots'));
    }

    public function test_papeis_fiscais_cte_e_canais_novos(): void
    {
        $this->assertSame('SENDER', FiscalRole::Sender->value);
        $this->assertSame('RECIPIENT', FiscalRole::Recipient->value);
        $this->assertSame('EXPEDITOR', FiscalRole::Expeditor->value);
        $this->assertSame('RECEIVER', FiscalRole::Receiver->value);
        $this->assertSame('ISSUER', FiscalRole::Issuer->value);
        $this->assertSame('TAKER', FiscalRole::Taker->value);
        $this->assertSame('INTERMEDIARY', FiscalRole::Intermediary->value);

        $this->assertSame('CTE_AUTXML_DISTDFE', CaptureChannel::CteAutXmlDistDfe->value);
        $this->assertTrue(CaptureChannel::CteAutXmlDistDfe->usesOfficeCursor());
        $this->assertSame('CTE_DIST_NSU', DocumentAcquisitionSource::CteDistNsu->value);
        $this->assertSame('CTE_AUTXML_DIST_NSU', DocumentAcquisitionSource::CteAutXmlDistNsu->value);
        $this->assertSame('EMITTER_PUSH', DocumentAcquisitionSource::EmitterPush->value);
        $this->assertSame('ORIGINAL', DocumentArtifactQuality::Original->value);
        $this->assertSame('AUTXML_REDACTED', DocumentArtifactQuality::AutXmlRedacted->value);
        $this->assertSame('VALID', SignatureVerificationResult::Valid->value);
        $this->assertSame('NOT_VERIFIABLE_OFFICIAL_REDACTION', SignatureVerificationResult::NotVerifiableOfficialRedaction->value);
        $this->assertSame('PENDING_IMPORT', CteCoverageStatus::PendingImport->value);
    }

    public function test_multiplos_papeis_mesmo_documento_estabelecimento(): void
    {
        $office = Office::factory()->create();
        $client = \App\Models\Client::factory()->forOffice($office)->create();
        $est = \App\Models\Establishment::factory()->forClient($client)->create([
            'cnpj' => '11111111000111',
        ]);

        $dfe = $this->seedDfe($office->id, '35260711222333000181570010000000421234567890');

        DocumentInterest::query()->create([
            'office_id' => $office->id,
            'dfe_document_id' => $dfe->id,
            'establishment_id' => $est->id,
            'environment' => 'production',
            'channel' => CaptureChannel::CteDistDfe->value,
            'nsu' => 10,
            'fiscal_role' => FiscalRole::Sender,
            'direction' => DocumentDirection::In,
        ]);

        DocumentInterest::query()->create([
            'office_id' => $office->id,
            'dfe_document_id' => $dfe->id,
            'establishment_id' => $est->id,
            'environment' => 'production',
            'channel' => CaptureChannel::CteDistDfe->value,
            'nsu' => 10,
            'fiscal_role' => FiscalRole::Taker,
            'direction' => DocumentDirection::In,
        ]);

        $this->assertSame(2, DocumentInterest::query()->where('dfe_document_id', $dfe->id)->count());
    }

    public function test_aquisicao_com_qualidade_sem_duplicar_bytes(): void
    {
        $office = Office::factory()->create();
        $dfe = $this->seedDfe($office->id, '35260711222333000181570010000000421234567891');
        $sha = $dfe->sha256;

        DocumentAcquisition::query()->create([
            'office_id' => $office->id,
            'dfe_document_id' => $dfe->id,
            'access_key' => $dfe->access_key,
            'source' => DocumentAcquisitionSource::CteDistNsu,
            'channel' => CaptureChannel::CteDistDfe,
            'nsu' => 5,
            'sha256' => $sha,
            'artifact_quality' => DocumentArtifactQuality::Original,
            'signature_result' => SignatureVerificationResult::Valid,
            'is_canonical' => true,
        ]);

        DocumentAcquisition::query()->create([
            'office_id' => $office->id,
            'dfe_document_id' => $dfe->id,
            'access_key' => $dfe->access_key,
            'source' => DocumentAcquisitionSource::CteAutXmlDistNsu,
            'channel' => CaptureChannel::CteAutXmlDistDfe,
            'nsu' => 99,
            'sha256' => $sha,
            'artifact_quality' => DocumentArtifactQuality::AutXmlRedacted,
            'signature_result' => SignatureVerificationResult::NotVerifiableOfficialRedaction,
            'is_canonical' => false,
        ]);

        $this->assertSame(2, DocumentAcquisition::query()->where('dfe_document_id', $dfe->id)->count());
        $this->assertSame(1, DfeDocument::query()->whereKey($dfe->id)->count());
    }

    public function test_cursor_central_cte_autxml_unicidade_por_canal(): void
    {
        $office = Office::factory()->create();
        $identity = OfficeFiscalIdentity::factory()->create([
            'office_id' => $office->id,
            'cnpj' => '55666777000155',
            'root_cnpj' => '55666777',
            'status' => OfficeFiscalIdentityStatus::Active,
        ]);

        OfficeDistributionCursor::factory()->create([
            'office_id' => $office->id,
            'office_fiscal_identity_id' => $identity->id,
            'interested_root_cnpj' => '55666777',
            'query_cnpj' => '55666777000155',
            'environment' => 'production',
            'channel' => CaptureChannel::NfeAutXmlDistDfe->value,
            'last_nsu' => 1,
        ]);

        OfficeDistributionCursor::factory()->create([
            'office_id' => $office->id,
            'office_fiscal_identity_id' => $identity->id,
            'interested_root_cnpj' => '55666777',
            'query_cnpj' => '55666777000155',
            'environment' => 'production',
            'channel' => CaptureChannel::CteAutXmlDistDfe->value,
            'last_nsu' => 0,
        ]);

        $this->assertSame(2, OfficeDistributionCursor::query()
            ->where('office_id', $office->id)
            ->where('interested_root_cnpj', '55666777')
            ->count());

        $this->expectException(\Illuminate\Database\QueryException::class);
        OfficeDistributionCursor::factory()->create([
            'office_id' => $office->id,
            'office_fiscal_identity_id' => $identity->id,
            'interested_root_cnpj' => '55666777',
            'query_cnpj' => '55666777000155',
            'environment' => 'production',
            'channel' => CaptureChannel::CteAutXmlDistDfe->value,
            'last_nsu' => 3,
        ]);
    }

    public function test_projecao_cte_e_evento_e_cobertura(): void
    {
        $office = Office::factory()->create();
        $client = \App\Models\Client::factory()->forOffice($office)->create();
        $dfe = $this->seedDfe($office->id, '35260711222333000181570010000000421234567892');
        $dfeEvent = $this->seedDfe($office->id, 'evt-35260711222333000181570010000000421234567892', AdnDocumentType::Unknown);

        $cte = CteDocument::query()->create([
            'office_id' => $office->id,
            'dfe_document_id' => $dfe->id,
            'access_key' => $dfe->access_key,
            'issuer_cnpj' => '11222333000181',
            'sender_cnpj' => '11111111000111',
            'recipient_cnpj' => '22222222000122',
            'expeditor_cnpj' => '33333333000133',
            'receiver_cnpj' => '44444444000144',
            'taker_cnpj' => '34194865000158',
            'effective_taker_cnpj' => '34194865000158',
            'fiscal_role' => FiscalRole::Taker,
            'direction' => DocumentDirection::In,
            'schema_version' => '4.00',
            'coverage_status' => CteCoverageStatus::CapturedOriginal,
            'status' => 'ACTIVE',
            'is_summary' => false,
        ]);

        CteEvent::query()->create([
            'office_id' => $office->id,
            'dfe_document_id' => $dfeEvent->id,
            'cte_document_id' => $cte->id,
            'access_key' => $dfe->access_key,
            'event_type' => '110111',
            'sequence' => 1,
            'cstat' => '135',
            'status' => 'REGISTERED',
        ]);

        CteCoverageSnapshot::query()->create([
            'office_id' => $office->id,
            'client_id' => $client->id,
            'period' => '2026-07',
            'status' => CteCoverageStatus::CapturedOriginal,
            'documents_count' => 1,
            'original_count' => 1,
            'computed_at' => now(),
        ]);

        $this->assertSame(1, $cte->events()->count());
        $this->assertSame(
            CteCoverageStatus::CapturedOriginal,
            CteCoverageSnapshot::query()->firstOrFail()->status
        );
    }

    private function seedDfe(
        int $officeId,
        string $accessKey,
        AdnDocumentType $type = AdnDocumentType::Cte,
    ): DfeDocument {
        $xml = '<cte>'.$accessKey.'</cte>';
        $sha = hash('sha256', $xml);
        $store = app(\App\Contracts\SecureObjectStore::class);
        $objectId = $store->put($xml, ['office_id' => $officeId, 'sha256' => $sha]);

        return DfeDocument::query()->create([
            'office_id' => $officeId,
            'sha256' => $sha,
            'document_type' => $type,
            'schema_version' => 'procCTe_v4.00.xsd',
            'access_key' => strlen($accessKey) >= 44 ? $accessKey : null,
            'vault_object_id' => $objectId,
            'byte_size' => strlen($xml),
            'parse_status' => 'OK',
        ]);
    }
}
