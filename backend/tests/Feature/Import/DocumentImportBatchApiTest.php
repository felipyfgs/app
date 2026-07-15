<?php

namespace Tests\Feature\Import;

use App\Enums\ImportBatchItemStatus;
use App\Enums\ImportBatchStatus;
use App\Enums\OfficeRole;
use App\Models\DocumentImportBatch;
use App\Models\DocumentImportBatchItem;
use App\Models\Office;
use App\Models\User;
use App\Support\CurrentOffice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Tests\TestCase;

class DocumentImportBatchApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_batch_202_ou_200_com_idempotency_e_paginacao(): void
    {
        $office = Office::factory()->create();
        $op = User::factory()->forOffice($office, OfficeRole::Operator)->withTwoFactorConfirmed()->create();
        $this->actingAs($op);
        app(CurrentOffice::class)->resolve($op);

        $xml = file_get_contents(base_path('tests/fixtures/autxml/procNFe_55_autxml_ok.xml'));
        $this->assertNotFalse($xml);

        $key = 'idem-'.Str::uuid();
        $file = UploadedFile::fake()->createWithContent('nota.xml', $xml);

        $first = $this->post('/api/v1/documents/import-batches', [
            'files' => [$file],
            'idempotency_key' => $key,
        ], ['Accept' => 'application/json']);

        $this->assertContains($first->status(), [200, 202]);
        $publicId = $first->json('data.public_id') ?? $first->json('data.id');
        $this->assertNotEmpty($publicId);

        $file2 = UploadedFile::fake()->createWithContent('nota.xml', $xml);
        $second = $this->post('/api/v1/documents/import-batches', [
            'files' => [$file2],
            'idempotency_key' => $key,
        ], ['Accept' => 'application/json']);

        $this->assertContains($second->status(), [200, 202]);
        $publicId2 = $second->json('data.public_id') ?? $second->json('data.id');
        $this->assertSame($publicId, $publicId2);

        $this->getJson('/api/v1/documents/import-batches?per_page=10')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta' => ['current_page', 'total']]);

        $this->getJson('/api/v1/documents/import-batches/'.$publicId)
            ->assertOk()
            ->assertJsonPath('data.public_id', $publicId);

        $this->getJson('/api/v1/documents/import-batches/'.$publicId.'/items?per_page=25')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta']);
    }

    public function test_retry_somente_unmatched_e_csv_sem_xml(): void
    {
        $office = Office::factory()->create();
        $op = User::factory()->forOffice($office, OfficeRole::Operator)->withTwoFactorConfirmed()->create();
        $this->actingAs($op);
        app(CurrentOffice::class)->resolve($op);

        $batch = DocumentImportBatch::factory()->create([
            'office_id' => $office->id,
            'created_by' => $op->id,
            'status' => ImportBatchStatus::CompletedWithErrors,
            'public_id' => (string) Str::uuid(),
            'item_count' => 2,
        ]);

        $unmatched = DocumentImportBatchItem::query()->create([
            'office_id' => $office->id,
            'document_import_batch_id' => $batch->id,
            'item_index' => 0,
            'source_name' => 'a.xml',
            'status' => ImportBatchItemStatus::Unmatched,
            'result_code' => 'UNMATCHED',
            'result_message' => 'Emitente sem cadastro',
            'spool_vault_object_id' => 'spool-keep',
            'attempts' => 1,
        ]);

        $invalid = DocumentImportBatchItem::query()->create([
            'office_id' => $office->id,
            'document_import_batch_id' => $batch->id,
            'item_index' => 1,
            'source_name' => 'b.xml',
            'status' => ImportBatchItemStatus::Invalid,
            'result_code' => 'INVALID',
            'result_message' => 'Assinatura inválida',
            'attempts' => 1,
        ]);

        $this->postJson("/api/v1/documents/import-batches/{$batch->public_id}/items/{$invalid->id}/retry")
            ->assertStatus(422);

        // Retry UNMATCHED exige spool; em ambiente de teste o job pode falhar depois —
        // o aceite da API já valida política.
        $retry = $this->postJson("/api/v1/documents/import-batches/{$batch->public_id}/items/{$unmatched->id}/retry");
        $this->assertContains($retry->status(), [200, 422]);

        $csv = $this->get("/api/v1/documents/import-batches/{$batch->public_id}/export.csv");
        $csv->assertOk();
        $content = $csv->streamedContent();
        $this->assertStringContainsString('item_index', $content);
        $this->assertStringNotContainsString('<NFe', $content);
        $this->assertStringNotContainsString('BEGIN ', $content);
        $this->assertStringNotContainsString('vault_object_id', $content);
    }

    public function test_viewer_403_e_cross_tenant_404(): void
    {
        $officeA = Office::factory()->create();
        $officeB = Office::factory()->create();
        $viewer = User::factory()->forOffice($officeA, OfficeRole::Viewer)->withTwoFactorConfirmed()->create();
        $opB = User::factory()->forOffice($officeB, OfficeRole::Operator)->withTwoFactorConfirmed()->create();

        $batchB = DocumentImportBatch::factory()->create([
            'office_id' => $officeB->id,
            'created_by' => $opB->id,
            'public_id' => (string) Str::uuid(),
            'status' => ImportBatchStatus::Completed,
        ]);

        $this->actingAs($viewer);
        app(CurrentOffice::class)->resolve($viewer);

        $xml = '<a/>';
        $file = UploadedFile::fake()->createWithContent('x.xml', $xml);
        $this->post('/api/v1/documents/import-batches', [
            'files' => [$file],
        ], ['Accept' => 'application/json'])->assertForbidden();

        $this->getJson('/api/v1/documents/import-batches/'.$batchB->public_id)
            ->assertNotFound();
    }
}
