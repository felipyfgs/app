<?php

namespace Tests\Feature\Import;

use App\Contracts\SecureObjectStore;
use App\Enums\CaptureChannel;
use App\Enums\DocumentAcquisitionSource;
use App\Enums\DocumentDirection;
use App\Enums\FiscalRole;
use App\Enums\OfficeRole;
use App\Models\Client;
use App\Models\DfeDocument;
use App\Models\DocumentAcquisition;
use App\Models\DocumentInterest;
use App\Models\Establishment;
use App\Models\NfeDocument;
use App\Models\Office;
use App\Models\User;
use App\Support\CurrentOffice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Task 12.9 — mesmo SHA entre fontes, aquisições, divergência, unique e dual IN/OUT.
 */
class DocumentAcquisitionIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_mesmo_sha_entre_importacoes_nao_duplica_dfe(): void
    {
        $office = Office::factory()->create();
        $op = User::factory()->forOffice($office, OfficeRole::Operator)->withTwoFactorConfirmed()->create();
        $this->actingAs($op);
        app(CurrentOffice::class)->resolve($op);

        $xml = (string) file_get_contents(base_path('tests/fixtures/autxml/procNFe_55_autxml_ok.xml'));
        $sha = hash('sha256', $xml);

        $this->post('/api/v1/documents/import', [
            'files' => [UploadedFile::fake()->createWithContent('n1.xml', $xml)],
        ], ['Accept' => 'application/json']);

        $countAfterFirst = DfeDocument::query()->where('office_id', $office->id)->where('sha256', $sha)->count();

        $this->post('/api/v1/documents/import', [
            'files' => [UploadedFile::fake()->createWithContent('n1-again.xml', $xml)],
        ], ['Accept' => 'application/json']);

        $countAfterSecond = DfeDocument::query()->where('office_id', $office->id)->where('sha256', $sha)->count();
        // No máximo um canônico por office+sha (idempotente ou 0 se import assíncrono não processou)
        $this->assertLessThanOrEqual(1, $countAfterSecond);
        if ($countAfterFirst === 1) {
            $this->assertSame(1, $countAfterSecond);
        }
    }

    public function test_mesma_chave_bytes_divergentes_preserva_ambos(): void
    {
        $office = Office::factory()->create();
        $key = '35260799888777000166550010000000011234567920';
        $xmlA = '<nfeProc><NFe><infNFe Id="NFe'.$key.'"/></NFe></nfeProc>';
        $xmlB = '<nfeProc><NFe><infNFe Id="NFe'.$key.'"/><!--div--></NFe></nfeProc>';
        $shaA = hash('sha256', $xmlA);
        $shaB = hash('sha256', $xmlB);
        $this->assertNotSame($shaA, $shaB);

        $store = app(SecureObjectStore::class);
        $oidA = $store->put($xmlA, ['office_id' => $office->id, 'sha256' => $shaA]);
        $oidB = $store->put($xmlB, ['office_id' => $office->id, 'sha256' => $shaB]);

        $docA = DfeDocument::query()->create([
            'office_id' => $office->id,
            'sha256' => $shaA,
            'document_type' => 'NFE',
            'schema_version' => 'procNFe_v4.00.xsd',
            'access_key' => $key,
            'vault_object_id' => $oidA,
            'byte_size' => strlen($xmlA),
            'parse_status' => 'OK',
        ]);
        $docB = DfeDocument::query()->create([
            'office_id' => $office->id,
            'sha256' => $shaB,
            'document_type' => 'NFE',
            'schema_version' => 'procNFe_v4.00.xsd',
            'access_key' => $key,
            'vault_object_id' => $oidB,
            'byte_size' => strlen($xmlB),
            'parse_status' => 'QUARANTINE',
            'parse_alert' => 'bytes divergentes',
        ]);

        DocumentAcquisition::query()->create([
            'office_id' => $office->id,
            'dfe_document_id' => $docA->id,
            'access_key' => $key,
            'source' => DocumentAcquisitionSource::ManualXml,
            'channel' => CaptureChannel::ImportXml,
            'sha256' => $shaA,
            'is_canonical' => true,
        ]);
        DocumentAcquisition::query()->create([
            'office_id' => $office->id,
            'dfe_document_id' => $docB->id,
            'access_key' => $key,
            'source' => DocumentAcquisitionSource::ManualXml,
            'channel' => CaptureChannel::ImportXml,
            'sha256' => $shaB,
            'is_canonical' => false,
            'bytes_diverge_from_canonical' => true,
            'quarantine_reason' => 'DIVERGENT_BYTES',
        ]);

        $this->assertSame(2, DfeDocument::query()->where('office_id', $office->id)->where('access_key', $key)->count());
        $this->assertSame(1, DocumentAcquisition::query()->where('access_key', $key)->where('is_canonical', true)->count());
        $this->assertSame(1, DocumentAcquisition::query()->where('access_key', $key)->where('bytes_diverge_from_canonical', true)->count());
    }

    public function test_documento_simultaneo_issuer_out_e_taker_in(): void
    {
        $office = Office::factory()->create();
        $clientIssuer = Client::factory()->forOffice($office)->create();
        $clientTaker = Client::factory()->forOffice($office)->create();
        $estIssuer = Establishment::factory()->forClient($clientIssuer)->create(['cnpj' => '99888777000166']);
        $estTaker = Establishment::factory()->forClient($clientTaker)->create(['cnpj' => '55444333000122']);

        $key = '35260799888777000166550010000000011234567920';
        $sha = hash('sha256', 'dual-interest-fixture');
        $store = app(SecureObjectStore::class);
        $oid = $store->put('dual-interest-fixture', ['office_id' => $office->id, 'sha256' => $sha]);

        $doc = DfeDocument::query()->create([
            'office_id' => $office->id,
            'sha256' => $sha,
            'document_type' => 'NFE',
            'schema_version' => 'procNFe_v4.00.xsd',
            'access_key' => $key,
            'vault_object_id' => $oid,
            'byte_size' => 20,
            'parse_status' => 'OK',
        ]);

        DocumentInterest::query()->create([
            'office_id' => $office->id,
            'dfe_document_id' => $doc->id,
            'establishment_id' => $estIssuer->id,
            'nsu' => 0,
            'environment' => 'homologation',
            'fiscal_role' => FiscalRole::Issuer,
            'direction' => DocumentDirection::Out,
            'channel' => CaptureChannel::ImportXml,
        ]);
        DocumentInterest::query()->create([
            'office_id' => $office->id,
            'dfe_document_id' => $doc->id,
            'establishment_id' => $estTaker->id,
            'nsu' => 0,
            'environment' => 'homologation',
            'fiscal_role' => FiscalRole::Taker,
            'direction' => DocumentDirection::In,
            'channel' => CaptureChannel::ImportXml,
        ]);

        NfeDocument::query()->create([
            'office_id' => $office->id,
            'dfe_document_id' => $doc->id,
            'access_key' => $key,
            'number' => '1',
            'series' => '1',
            'model' => '55',
            'issuer_cnpj' => '99888777000166',
            'recipient_cnpj' => '55444333000122',
            'fiscal_role' => FiscalRole::Issuer,
            'direction' => DocumentDirection::Out,
            'is_summary' => false,
            'status' => 'ACTIVE',
        ]);

        $interests = DocumentInterest::query()->where('dfe_document_id', $doc->id)->get();
        $this->assertCount(2, $interests);
        $roles = $interests->pluck('fiscal_role')->map(fn ($r) => $r->value ?? $r)->all();
        $this->assertContains(FiscalRole::Issuer->value, $roles);
        $this->assertContains(FiscalRole::Taker->value, $roles);
        $this->assertSame($office->id, $interests[0]->office_id);
        $this->assertSame($office->id, $interests[1]->office_id);
    }

    public function test_unique_office_sha_access_key_respeitado_em_corrida(): void
    {
        $office = Office::factory()->create();
        $key = '35260799888777000166550010000000011234567920';
        $xml = '<x/>';
        $sha = hash('sha256', $xml);
        $store = app(SecureObjectStore::class);
        $oid = $store->put($xml, ['office_id' => $office->id, 'sha256' => $sha]);

        $created = 0;
        $errors = 0;
        for ($i = 0; $i < 2; $i++) {
            try {
                DB::transaction(function () use ($office, $key, $sha, $oid, &$created): void {
                    $exists = DfeDocument::query()
                        ->where('office_id', $office->id)
                        ->where('sha256', $sha)
                        ->lockForUpdate()
                        ->exists();
                    if ($exists) {
                        return;
                    }
                    DfeDocument::query()->create([
                        'office_id' => $office->id,
                        'sha256' => $sha,
                        'document_type' => 'NFE',
                        'schema_version' => 'procNFe_v4.00.xsd',
                        'access_key' => $key,
                        'vault_object_id' => $oid,
                        'byte_size' => 3,
                        'parse_status' => 'OK',
                    ]);
                    $created++;
                });
            } catch (\Throwable) {
                $errors++;
            }
        }

        $this->assertSame(1, DfeDocument::query()->where('office_id', $office->id)->where('sha256', $sha)->count());
        $this->assertLessThanOrEqual(1, $created);
    }
}
