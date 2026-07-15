<?php

namespace Tests\Feature\AutXml;

use App\Enums\AdnDocumentType;
use App\Enums\CaptureChannel;
use App\Enums\CredentialStatus;
use App\Enums\DocumentAcquisitionSource;
use App\Enums\DocumentDirection;
use App\Enums\FiscalRole;
use App\Enums\ImportBatchItemStatus;
use App\Enums\ImportBatchStatus;
use App\Enums\OfficeAutXmlEnrollmentStatus;
use App\Enums\OfficeCredentialPurpose;
use App\Enums\OfficeFiscalIdentityStatus;
use App\Enums\OfficeRole;
use App\Enums\QuarantineReason;
use App\Enums\QuarantineResolutionStatus;
use App\Enums\SyncCursorStatus;
use App\Models\Client;
use App\Models\DfeDocument;
use App\Models\DocumentImportBatch;
use App\Models\DocumentImportBatchItem;
use App\Models\DocumentInterest;
use App\Models\Establishment;
use App\Models\FiscalDocumentQuarantine;
use App\Models\Office;
use App\Models\OfficeAutXmlEnrollment;
use App\Models\OfficeCredential;
use App\Models\OfficeDistributionCursor;
use App\Models\OfficeDistributionRun;
use App\Models\OfficeFiscalIdentity;
use App\Models\User;
use App\Support\CurrentOffice;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class AutXmlSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_tabelas_autxml_e_import_existem(): void
    {
        $this->assertTrue(Schema::hasTable('office_fiscal_identities'));
        $this->assertTrue(Schema::hasTable('office_credentials'));
        $this->assertTrue(Schema::hasTable('office_autxml_enrollments'));
        $this->assertTrue(Schema::hasTable('office_distribution_cursors'));
        $this->assertTrue(Schema::hasTable('office_distribution_runs'));
        $this->assertTrue(Schema::hasTable('fiscal_document_quarantine'));
        $this->assertTrue(Schema::hasTable('document_import_batches'));
        $this->assertTrue(Schema::hasTable('document_import_batch_items'));
        $this->assertTrue(Schema::hasTable('document_acquisitions'));

        $this->assertTrue(Schema::hasColumns('document_acquisitions', [
            'nsu',
            'office_distribution_cursor_id',
            'document_import_batch_item_id',
        ]));
        $this->assertTrue(Schema::hasColumn('document_interests', 'direction'));

        // Cursor de escritório não tem establishment_id
        $this->assertFalse(Schema::hasColumn('office_distribution_cursors', 'establishment_id'));
    }

    public function test_identidade_fiscal_cnpj_texto_uppercase_e_unicidade(): void
    {
        $office = Office::factory()->create();
        $identity = OfficeFiscalIdentity::factory()->forOffice($office)->withCnpj('ab123456000101')->create();

        $this->assertSame('AB123456000101', $identity->cnpj);
        $this->assertSame('AB123456', $identity->root_cnpj);
        $this->assertSame(OfficeFiscalIdentityStatus::Active, $identity->status);

        $this->expectException(QueryException::class);
        OfficeFiscalIdentity::factory()->forOffice($office)->withCnpj('AB123456000101')->create();
    }

    public function test_cursor_unico_por_raiz_ambiente_canal(): void
    {
        $office = Office::factory()->create();
        $identity = OfficeFiscalIdentity::factory()->forOffice($office)->create();

        OfficeDistributionCursor::factory()->forIdentity($identity)->create([
            'environment' => 'production',
        ]);

        $this->expectException(QueryException::class);
        OfficeDistributionCursor::factory()->forIdentity($identity)->create([
            'environment' => 'production',
        ]);
    }

    public function test_credencial_esconde_vault_object_id_na_serializacao(): void
    {
        $office = Office::factory()->create();
        $identity = OfficeFiscalIdentity::factory()->forOffice($office)->create();
        $cred = OfficeCredential::factory()->forIdentity($identity)->create();

        $array = $cred->toArray();
        $this->assertArrayNotHasKey('vault_object_id', $array);

        $public = $cred->toPublicArray();
        $this->assertArrayNotHasKey('vault_object_id', $public);
        $this->assertSame(OfficeCredentialPurpose::NfeAutXmlDistDfe->value, $public['purpose']);
        $this->assertSame(CredentialStatus::Active->value, $public['status']);
    }

    public function test_enrollment_por_identidade_e_estabelecimento(): void
    {
        $office = Office::factory()->create();
        $identity = OfficeFiscalIdentity::factory()->forOffice($office)->create();
        $client = Client::factory()->forOffice($office)->create();
        $estab = Establishment::factory()->forClient($client)->create();

        $enrollment = OfficeAutXmlEnrollment::query()->create([
            'office_id' => $office->id,
            'office_fiscal_identity_id' => $identity->id,
            'establishment_id' => $estab->id,
            'status' => OfficeAutXmlEnrollmentStatus::Pending,
        ]);

        $this->assertSame(OfficeAutXmlEnrollmentStatus::Pending, $enrollment->status);
        $this->assertNull($enrollment->first_seen_at);

        $this->expectException(QueryException::class);
        OfficeAutXmlEnrollment::query()->create([
            'office_id' => $office->id,
            'office_fiscal_identity_id' => $identity->id,
            'establishment_id' => $estab->id,
            'status' => OfficeAutXmlEnrollmentStatus::Confirmed,
        ]);
    }

    public function test_mesma_chave_in_e_out_para_estabelecimentos_distintos(): void
    {
        $office = Office::factory()->create();
        app(CurrentOffice::class)->clear();
        $clientA = Client::factory()->forOffice($office)->create();
        $clientB = Client::factory()->forOffice($office)->create();
        $estabA = Establishment::factory()->forClient($clientA)->create();
        $estabB = Establishment::factory()->forClient($clientB)->create();

        $dfe = DfeDocument::query()->create([
            'office_id' => $office->id,
            'sha256' => hash('sha256', 'doc-in-out'),
            'document_type' => AdnDocumentType::Nfe,
            'schema_version' => 'procNFe_v4.00.xsd',
            'access_key' => '35260711222333000181550010000000011234567999',
            'vault_object_id' => '01TESTTESTTESTTESTTESTTESTTESTXX',
            'byte_size' => 10,
            'parse_status' => 'OK',
        ]);

        DocumentInterest::query()->create([
            'office_id' => $office->id,
            'dfe_document_id' => $dfe->id,
            'establishment_id' => $estabA->id,
            'nsu' => 1,
            'environment' => 'production',
            'channel' => CaptureChannel::NfeAutXmlDistDfe->value,
            'fiscal_role' => FiscalRole::Issuer,
            'direction' => DocumentDirection::Out,
        ]);

        DocumentInterest::query()->create([
            'office_id' => $office->id,
            'dfe_document_id' => $dfe->id,
            'establishment_id' => $estabB->id,
            'nsu' => 2,
            'environment' => 'production',
            'channel' => CaptureChannel::NfeDistDfe->value,
            'fiscal_role' => FiscalRole::Taker,
            'direction' => DocumentDirection::In,
        ]);

        $this->assertSame(2, DocumentInterest::query()->where('dfe_document_id', $dfe->id)->count());
    }

    public function test_batch_e_item_estados_e_ocultacao_de_spool(): void
    {
        $office = Office::factory()->create();
        $user = User::factory()->forOffice($office, OfficeRole::Operator)->withTwoFactorConfirmed()->create();

        $batch = DocumentImportBatch::factory()->forOffice($office, $user)->create([
            'status' => ImportBatchStatus::Queued,
            'spool_vault_object_id' => strtoupper(Str::ulid()->toBase32()),
            'idempotency_key' => 'idem-1',
            'selection_digest' => hash('sha256', 'files'),
        ]);

        $item = DocumentImportBatchItem::query()->create([
            'office_id' => $office->id,
            'document_import_batch_id' => $batch->id,
            'item_index' => 0,
            'source_name' => 'nfe.xml',
            'status' => ImportBatchItemStatus::Pending,
            'spool_vault_object_id' => strtoupper(Str::ulid()->toBase32()),
        ]);

        $this->assertArrayNotHasKey('spool_vault_object_id', $batch->toArray());
        $this->assertArrayNotHasKey('spool_vault_object_id', $item->toArray());
        $this->assertArrayNotHasKey('spool_vault_object_id', $batch->toPublicArray());
        $this->assertSame($batch->public_id, $batch->toPublicArray()['id']);
    }

    public function test_quarentena_invisivel_ao_catalogo_e_sem_vault_publico(): void
    {
        $office = Office::factory()->create();
        $q = FiscalDocumentQuarantine::query()->create([
            'office_id' => $office->id,
            'sha256' => hash('sha256', 'q'),
            'vault_object_id' => strtoupper(Str::ulid()->toBase32()),
            'byte_size' => 100,
            'reason' => QuarantineReason::UnmatchedIssuer,
            'source' => DocumentAcquisitionSource::AutXmlDistNsu,
            'channel' => CaptureChannel::NfeAutXmlDistDfe,
            'resolution_status' => QuarantineResolutionStatus::Open,
            'issuer_cnpj' => '99888777000166',
        ]);

        $public = $q->toPublicArray();
        $this->assertArrayNotHasKey('vault_object_id', $public);
        $this->assertSame(QuarantineReason::UnmatchedIssuer->value, $public['reason']);
        $this->assertArrayNotHasKey('vault_object_id', $q->toArray());
    }

    public function test_run_historico_cursor_e_aquisicao_fontes_novas(): void
    {
        $office = Office::factory()->create();
        $identity = OfficeFiscalIdentity::factory()->forOffice($office)->create();
        $cursor = OfficeDistributionCursor::factory()->forIdentity($identity)->create([
            'status' => SyncCursorStatus::Idle,
            'last_nsu' => 0,
        ]);

        $run = OfficeDistributionRun::query()->create([
            'office_id' => $office->id,
            'office_distribution_cursor_id' => $cursor->id,
            'status' => 'COMPLETED',
            'trigger' => 'SCHEDULED',
            'from_nsu' => 0,
            'to_nsu' => 0,
            'pages_processed' => 1,
            'last_cstat' => '137',
            'started_at' => now(),
            'finished_at' => now(),
        ]);

        $this->assertSame('137', $run->toPublicArray()['last_cstat']);
        $this->assertTrue(DocumentAcquisitionSource::AutXmlDistNsu->isAutXml());
        $this->assertTrue(DocumentAcquisitionSource::ManualXml->isManualImport());
        $this->assertTrue(DocumentAcquisitionSource::ManualZip->isManualImport());
        $this->assertFalse(DocumentAcquisitionSource::NfeDistDfe->isAutXml());
    }

    public function test_down_migration_segura_dropa_tabelas_novas(): void
    {
        $migration = require database_path('migrations/2026_07_15_040000_create_office_autxml_and_import_tables.php');
        $migration->down();

        $this->assertFalse(Schema::hasTable('office_fiscal_identities'));
        $this->assertFalse(Schema::hasTable('office_credentials'));
        $this->assertFalse(Schema::hasTable('office_distribution_cursors'));
        $this->assertFalse(Schema::hasTable('document_import_batches'));
        $this->assertFalse(Schema::hasTable('document_import_batch_items'));
        $this->assertFalse(Schema::hasTable('fiscal_document_quarantine'));
        $this->assertTrue(Schema::hasTable('document_acquisitions')); // MA ownership

        // Reaplica (RefreshDatabase isola por teste; garante up idempotente em memória)
        $migration->up();
        $this->assertTrue(Schema::hasTable('office_fiscal_identities'));
        $this->assertTrue(Schema::hasTable('document_import_batches'));
    }
}
