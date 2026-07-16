<?php

namespace Tests\Feature\FiscalDataModel;

use App\Models\Client;
use App\Models\Establishment;
use App\Models\Office;
use App\Support\FiscalDataModel\FiscalModelAggregates;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DocumentProvenanceBackfillTest extends TestCase
{
    use RefreshDatabase;

    public function test_cria_aquisicao_sintetica_sem_alterar_sha_do_documento(): void
    {
        $office = Office::factory()->create();
        $client = Client::factory()->forOffice($office)->create();
        $est = Establishment::factory()->forClient($client)->create();

        $sha = hash('sha256', 'xml-bytes-fixture');
        $docId = DB::table('dfe_documents')->insertGetId([
            'office_id' => $office->id,
            'sha256' => $sha,
            'document_type' => 'NFSE',
            'vault_object_id' => '01TESTVAULTOBJECT00000000',
            'byte_size' => 100,
            'parse_status' => 'OK',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('document_interests')->insert([
            'office_id' => $office->id,
            'dfe_document_id' => $docId,
            'establishment_id' => $est->id,
            'environment' => 'production',
            'channel' => 'NFSE_ADN',
            'nsu' => 42,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertSame(0, (int) DB::table('document_acquisitions')->count());

        $exit = Artisan::call('fiscal-model:backfill', [
            'aggregate' => FiscalModelAggregates::DOCUMENTOS_CURSORES,
            '--json' => true,
        ]);
        $this->assertSame(0, $exit);

        $this->assertSame(1, (int) DB::table('document_acquisitions')->count());
        $acq = DB::table('document_acquisitions')->first();
        $this->assertSame($sha, $acq->sha256);
        $this->assertSame('LEGACY_BACKFILL', $acq->source);
        $this->assertSame($sha, DB::table('dfe_documents')->where('id', $docId)->value('sha256'));

        // Idempotente
        Artisan::call('fiscal-model:backfill', [
            'aggregate' => FiscalModelAggregates::DOCUMENTOS_CURSORES,
            '--json' => true,
        ]);
        $this->assertSame(1, (int) DB::table('document_acquisitions')->count());
    }
}
