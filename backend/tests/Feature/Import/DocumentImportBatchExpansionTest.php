<?php

namespace Tests\Feature\Import;

use App\Enums\ImportBatchItemStatus;
use App\Enums\ImportBatchStatus;
use App\Enums\OfficeRole;
use App\Jobs\ProcessDocumentImportBatchJob;
use App\Models\Client;
use App\Models\DocumentImportBatch;
use App\Models\DocumentImportBatchItem;
use App\Models\Establishment;
use App\Models\Office;
use App\Models\User;
use App\Services\Outbound\AccessKeyCandidateBuilder;
use App\Support\CurrentOffice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;
use ZipArchive;

/**
 * Review fixes: ZIP expansion (sync+async), tenancy establishment_id, spool/retry.
 */
class DocumentImportBatchExpansionTest extends TestCase
{
    use RefreshDatabase;

    public function test_establishment_id_fora_do_escritorio_retorna_422(): void
    {
        $officeA = Office::factory()->create();
        $officeB = Office::factory()->create();
        $op = User::factory()->forOffice($officeA, OfficeRole::Operator)->withTwoFactorConfirmed()->create();
        $this->actingAs($op);
        app(CurrentOffice::class)->resolve($op);

        $clientB = Client::factory()->forOffice($officeB)->create(['root_cnpj' => '11222333']);
        $estB = Establishment::factory()->forClient($clientB)->create(['cnpj' => '11222333000181']);

        $xml = $this->procNfe('11222333000181', '99888777000166', $this->key(11222333000181, 1));
        $res = $this->post('/api/v1/documents/import-batches', [
            'files' => [UploadedFile::fake()->createWithContent('nfe.xml', $xml)],
            'establishment_id' => $estB->id,
        ], ['Accept' => 'application/json']);

        $res->assertStatus(422);
        $this->assertStringContainsString('Estabelecimento', (string) $res->json('message'));
    }

    public function test_client_id_fora_do_escritorio_retorna_422(): void
    {
        $officeA = Office::factory()->create();
        $officeB = Office::factory()->create();
        $op = User::factory()->forOffice($officeA, OfficeRole::Operator)->withTwoFactorConfirmed()->create();
        $this->actingAs($op);
        app(CurrentOffice::class)->resolve($op);

        $clientB = Client::factory()->forOffice($officeB)->create(['root_cnpj' => '11222333']);

        $xml = $this->procNfe('11222333000181', '99888777000166', $this->key(11222333000181, 2));
        $res = $this->post('/api/v1/documents/import-batches', [
            'files' => [UploadedFile::fake()->createWithContent('nfe.xml', $xml)],
            'client_id' => $clientB->id,
        ], ['Accept' => 'application/json']);

        $res->assertStatus(422);
        $this->assertStringContainsString('Cliente', (string) $res->json('message'));
    }

    public function test_establishment_do_escritorio_e_aceito(): void
    {
        $office = Office::factory()->create();
        $op = User::factory()->forOffice($office, OfficeRole::Operator)->withTwoFactorConfirmed()->create();
        $this->actingAs($op);
        app(CurrentOffice::class)->resolve($op);

        $emit = '99888777000166';
        $client = Client::factory()->forOffice($office)->create(['root_cnpj' => substr($emit, 0, 8)]);
        $est = Establishment::factory()->forClient($client)->create([
            'office_id' => $office->id,
            'cnpj' => $emit,
            'is_active' => true,
        ]);

        $key = $this->key((int) $emit, 10);
        $xml = $this->procNfe($emit, '55444333000122', $key);

        $res = $this->post('/api/v1/documents/import-batches', [
            'files' => [UploadedFile::fake()->createWithContent('nfe.xml', $xml)],
            'client_id' => $client->id,
            'establishment_id' => $est->id,
        ], ['Accept' => 'application/json']);

        $this->assertContains($res->status(), [200, 202], $res->getContent() ?: '');
        $batch = DocumentImportBatch::query()->where('public_id', $res->json('data.public_id'))->first();
        $this->assertNotNull($batch);
        $this->assertSame($est->id, $batch->establishment_id);
        $this->assertSame($client->id, $batch->client_id);
    }

    public function test_sync_zip_expande_em_n_itens_parent_mantem_spool(): void
    {
        config(['import.async_batches_enabled' => false]);

        $office = Office::factory()->create();
        $op = User::factory()->forOffice($office, OfficeRole::Operator)->withTwoFactorConfirmed()->create();
        $this->actingAs($op);
        app(CurrentOffice::class)->resolve($op);

        $emit = '99888777000166';
        $client = Client::factory()->forOffice($office)->create(['root_cnpj' => substr($emit, 0, 8)]);
        Establishment::factory()->forClient($client)->create([
            'office_id' => $office->id,
            'cnpj' => $emit,
            'is_active' => true,
        ]);

        $k1 = $this->key((int) $emit, 21);
        $k2 = $this->key((int) $emit, 22);
        $k3 = $this->key((int) $emit, 23);
        $zip = $this->zipWith([
            'a/nfe1.xml' => $this->procNfe($emit, '55444333000122', $k1),
            'b/nfe2.xml' => $this->procNfe($emit, '55444333000122', $k2),
            'c/nfe3.xml' => $this->procNfe($emit, '55444333000122', $k3),
        ]);

        $res = $this->post('/api/v1/documents/import-batches', [
            'files' => [UploadedFile::fake()->createWithContent('lote.zip', $zip)],
        ], ['Accept' => 'application/json']);

        $this->assertContains($res->status(), [200, 202], $res->getContent() ?: '');
        $publicId = $res->json('data.public_id') ?? $res->json('data.id');
        $batch = DocumentImportBatch::query()->where('public_id', $publicId)->first();
        $this->assertNotNull($batch);

        $items = DocumentImportBatchItem::query()
            ->where('document_import_batch_id', $batch->id)
            ->orderBy('item_index')
            ->get();

        // 1 parent ZIP_EXPANDED + 3 filhos
        $this->assertCount(4, $items);
        $parent = $items->firstWhere('result_code', 'ZIP_EXPANDED');
        $this->assertNotNull($parent);
        $this->assertNotNull($parent->spool_vault_object_id);
        $this->assertNull($parent->entry_name);

        $children = $items->where('result_code', '!=', 'ZIP_EXPANDED')->values();
        $this->assertCount(3, $children);
        foreach ($children as $child) {
            $this->assertNull($child->spool_vault_object_id);
            $this->assertNotNull($child->entry_name);
            $this->assertSame('lote.zip', $child->source_name);
        }

        $batch->refresh();
        // item_count / counters excluem o parent resumo
        $this->assertSame(3, $batch->item_count);
        $this->assertSame(3, (int) $batch->imported_count);
        $this->assertContains($batch->status, [
            ImportBatchStatus::Completed,
            ImportBatchStatus::CompletedWithErrors,
        ]);

        // Retry de filho sem spool próprio, com parent spool: aceito (via parent)
        $failedChild = $children->first();
        $failedChild->status = ImportBatchItemStatus::Unmatched;
        $failedChild->result_code = 'UNMATCHED';
        $failedChild->save();

        Bus::fake([ProcessDocumentImportBatchJob::class]);
        $retry = $this->postJson(
            "/api/v1/documents/import-batches/{$batch->public_id}/items/{$failedChild->id}/retry"
        );
        $retry->assertOk();
        Bus::assertDispatched(ProcessDocumentImportBatchJob::class);
    }

    public function test_async_zip_expande_como_sync_com_todos_os_resultados(): void
    {
        config(['import.async_batches_enabled' => true]);

        $office = Office::factory()->create();
        $op = User::factory()->forOffice($office, OfficeRole::Operator)->withTwoFactorConfirmed()->create();
        $this->actingAs($op);
        app(CurrentOffice::class)->resolve($op);

        $emit = '99888777000166';
        $client = Client::factory()->forOffice($office)->create(['root_cnpj' => substr($emit, 0, 8)]);
        Establishment::factory()->forClient($client)->create([
            'office_id' => $office->id,
            'cnpj' => $emit,
            'is_active' => true,
        ]);

        $k1 = $this->key((int) $emit, 31);
        $k2 = $this->key((int) $emit, 32);
        $zip = $this->zipWith([
            'x/one.xml' => $this->procNfe($emit, '55444333000122', $k1),
            'y/two.xml' => $this->procNfe($emit, '55444333000122', $k2),
        ]);

        // QUEUE_CONNECTION=sync no phpunit → job roda na admissão
        $res = $this->post('/api/v1/documents/import-batches', [
            'files' => [UploadedFile::fake()->createWithContent('async-lote.zip', $zip)],
        ], ['Accept' => 'application/json']);

        $this->assertContains($res->status(), [200, 202], $res->getContent() ?: '');
        $publicId = $res->json('data.public_id') ?? $res->json('data.id');
        $batch = DocumentImportBatch::query()->where('public_id', $publicId)->first();
        $this->assertNotNull($batch);

        $items = DocumentImportBatchItem::query()
            ->where('document_import_batch_id', $batch->id)
            ->orderBy('item_index')
            ->get();

        $this->assertCount(3, $items, 'parent + 2 filhos');
        $this->assertNotNull($items->firstWhere('result_code', 'ZIP_EXPANDED'));
        $this->assertSame(2, $items->where('result_code', '!=', 'ZIP_EXPANDED')->count());

        $batch->refresh();
        $this->assertSame(2, $batch->item_count);
        $this->assertSame(2, (int) $batch->imported_count);

        // API de itens + CSV cobrem parent e filhos (entry_name só na API)
        $list = $this->getJson("/api/v1/documents/import-batches/{$batch->public_id}/items");
        $list->assertOk();
        $payload = collect($list->json('data'));
        $this->assertTrue($payload->contains(fn ($r) => ($r['result_code'] ?? '') === 'ZIP_EXPANDED'));
        $entries = $payload->pluck('entry_name')->filter()->values()->all();
        $this->assertNotEmpty($entries);
        $this->assertTrue(
            collect($entries)->contains(fn ($e) => str_contains((string) $e, 'one.xml')),
            'entry_name do 1º XML ausente: '.json_encode($entries)
        );
        $this->assertTrue(
            collect($entries)->contains(fn ($e) => str_contains((string) $e, 'two.xml')),
            'entry_name do 2º XML ausente: '.json_encode($entries)
        );

        $csv = $this->get("/api/v1/documents/import-batches/{$batch->public_id}/export.csv");
        $csv->assertOk();
        $body = $csv->streamedContent();
        $this->assertStringContainsString('ZIP_EXPANDED', $body);
        $this->assertStringContainsString($k1, $body);
        $this->assertStringContainsString($k2, $body);
        $this->assertStringNotContainsString('<NFe', $body);
    }

    public function test_retry_filho_sem_parent_spool_pede_reenvio_do_zip(): void
    {
        $office = Office::factory()->create();
        $op = User::factory()->forOffice($office, OfficeRole::Operator)->withTwoFactorConfirmed()->create();
        $this->actingAs($op);
        app(CurrentOffice::class)->resolve($op);

        $batch = DocumentImportBatch::factory()->forOffice($office, $op)->create([
            'status' => ImportBatchStatus::CompletedWithErrors,
            'item_count' => 1,
        ]);

        $child = DocumentImportBatchItem::query()->create([
            'office_id' => $office->id,
            'document_import_batch_id' => $batch->id,
            'item_index' => 1,
            'source_name' => 'lote.zip',
            'entry_name' => 'a/nfe.xml',
            'status' => ImportBatchItemStatus::Failed,
            'result_code' => 'FAILED',
            'spool_vault_object_id' => null,
            'attempts' => 1,
        ]);

        $res = $this->postJson(
            "/api/v1/documents/import-batches/{$batch->public_id}/items/{$child->id}/retry"
        );
        $res->assertStatus(422);
        $this->assertStringContainsString('reenvie o ZIP', (string) $res->json('message'));
    }

    public function test_xml_avulso_mantem_spool_no_item(): void
    {
        config(['import.async_batches_enabled' => false]);

        $office = Office::factory()->create();
        $op = User::factory()->forOffice($office, OfficeRole::Operator)->withTwoFactorConfirmed()->create();
        $this->actingAs($op);
        app(CurrentOffice::class)->resolve($op);

        $emit = '99888777000166';
        $client = Client::factory()->forOffice($office)->create(['root_cnpj' => substr($emit, 0, 8)]);
        Establishment::factory()->forClient($client)->create([
            'office_id' => $office->id,
            'cnpj' => $emit,
            'is_active' => true,
        ]);

        $key = $this->key((int) $emit, 40);
        $xml = $this->procNfe($emit, '55444333000122', $key);

        $res = $this->post('/api/v1/documents/import-batches', [
            'files' => [UploadedFile::fake()->createWithContent('avulso.xml', $xml)],
        ], ['Accept' => 'application/json']);

        $this->assertContains($res->status(), [200, 202]);
        $batch = DocumentImportBatch::query()->where('public_id', $res->json('data.public_id'))->first();
        $item = DocumentImportBatchItem::query()
            ->where('document_import_batch_id', $batch->id)
            ->first();

        $this->assertNotNull($item);
        $this->assertNotNull($item->spool_vault_object_id);
        $this->assertNull($item->entry_name);
        $this->assertSame(1, $batch->item_count);
    }

    /**
     * @param  array<string, string>  $entries
     */
    private function zipWith(array $entries): string
    {
        $path = tempnam(sys_get_temp_dir(), 'impzip');
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path, ZipArchive::OVERWRITE));
        foreach ($entries as $name => $content) {
            $zip->addFromString($name, $content);
        }
        $zip->close();
        $bytes = (string) file_get_contents($path);
        @unlink($path);

        return $bytes;
    }

    private function key(int $cnpjAsInt, int $nnf): string
    {
        $cnpj = str_pad((string) $cnpjAsInt, 14, '0', STR_PAD_LEFT);
        $b = new AccessKeyCandidateBuilder;

        return $b->build([
            'cuf' => '35',
            'aamm' => '2607',
            'cnpj' => $cnpj,
            'model' => '55',
            'series' => 1,
            'nnf' => $nnf,
            'tp_emis' => '1',
        ])['access_key'];
    }

    private function procNfe(string $emit, string $dest, string $chave): string
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<nfeProc versao="4.00" xmlns="http://www.portalfiscal.inf.br/nfe">
  <NFe>
    <infNFe Id="NFe{$chave}" versao="4.00">
      <ide>
        <cUF>35</cUF>
        <mod>55</mod>
        <serie>1</serie>
        <nNF>1</nNF>
        <tpNF>1</tpNF>
        <tpAmb>2</tpAmb>
        <dhEmi>2026-07-01T10:00:00-03:00</dhEmi>
      </ide>
      <emit><CNPJ>{$emit}</CNPJ><xNome>EMIT</xNome></emit>
      <dest><CNPJ>{$dest}</CNPJ><xNome>DEST</xNome></dest>
      <total><ICMSTot><vNF>10.00</vNF></ICMSTot></total>
    </infNFe>
  </NFe>
  <protNFe versao="4.00">
    <infProt>
      <tpAmb>2</tpAmb>
      <chNFe>{$chave}</chNFe>
      <nProt>135260000000099</nProt>
      <cStat>100</cStat>
      <xMotivo>Autorizado o uso da NF-e</xMotivo>
    </infProt>
  </protNFe>
</nfeProc>
XML;
    }
}
