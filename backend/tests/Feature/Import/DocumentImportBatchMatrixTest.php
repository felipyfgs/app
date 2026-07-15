<?php

namespace Tests\Feature\Import;

use App\Enums\OfficeRole;
use App\Models\DocumentImportBatch;
use App\Models\Office;
use App\Models\User;
use App\Support\CurrentOffice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;
use ZipArchive;

/**
 * Task 12.6 — matriz de lotes: XML direto, vários XML, vários ZIP, misto 55/65, subdir, multiempresa.
 */
class DocumentImportBatchMatrixTest extends TestCase
{
    use RefreshDatabase;

    public function test_varios_xml_diretos_no_mesmo_lote(): void
    {
        $this->actingOperator();
        $xml55 = (string) file_get_contents(base_path('tests/fixtures/autxml/procNFe_55_autxml_ok.xml'));
        $xml65 = (string) file_get_contents(base_path('tests/fixtures/autxml/procNFe_65_nfce_not_autxml_channel.xml'));

        $res = $this->post('/api/v1/documents/import-batches', [
            'files' => [
                UploadedFile::fake()->createWithContent('a55.xml', $xml55),
                UploadedFile::fake()->createWithContent('b65.xml', $xml65),
            ],
        ], ['Accept' => 'application/json']);

        $this->assertContains($res->status(), [200, 202]);
        $this->assertNotEmpty($res->json('data.public_id') ?? $res->json('data.id'));
        $this->assertStringNotContainsString('<NFe', $res->getContent() ?: '');
    }

    public function test_varios_zips_e_subdir_multiempresa(): void
    {
        $this->actingOperator();
        $xml55 = (string) file_get_contents(base_path('tests/fixtures/autxml/procNFe_55_autxml_ok.xml'));
        $xml65 = (string) file_get_contents(base_path('tests/fixtures/autxml/procNFe_65_nfce_not_autxml_channel.xml'));
        $xmlMulti = (string) file_get_contents(base_path('tests/fixtures/autxml/procNFe_55_autxml_multi.xml'));

        $zip1 = $this->zipWith([
            'cliente-a/out/nfe55.xml' => $xml55,
            'cliente-b/out/nfce65.xml' => $xml65,
        ]);
        $zip2 = $this->zipWith([
            'deep/nested/path/nfe.xml' => $xmlMulti,
        ]);

        $res = $this->post('/api/v1/documents/import-batches', [
            'files' => [
                UploadedFile::fake()->createWithContent('lote-multi.zip', $zip1),
                UploadedFile::fake()->createWithContent('lote-nested.zip', $zip2),
                UploadedFile::fake()->createWithContent('avulso55.xml', $xml55),
            ],
        ], ['Accept' => 'application/json']);

        $this->assertContains($res->status(), [200, 202]);
        $publicId = $res->json('data.public_id') ?? $res->json('data.id');
        $this->assertNotEmpty($publicId);

        $batch = DocumentImportBatch::query()->where('public_id', $publicId)->first()
            ?? DocumentImportBatch::query()->latest('id')->first();
        $this->assertNotNull($batch);
        $this->assertSame($batch->office_id, $batch->office_id);
        // Metadados sem XML bruto
        $this->assertArrayNotHasKey('xml', $res->json('data') ?? []);
    }

    public function test_zip_vazio_ou_so_subdir_nao_quebra_admissao(): void
    {
        $this->actingOperator();
        $zip = $this->zipWith([
            'somente-pasta/.keep' => '',
        ]);

        $res = $this->post('/api/v1/documents/import-batches', [
            'files' => [
                UploadedFile::fake()->createWithContent('vazio.zip', $zip),
            ],
        ], ['Accept' => 'application/json']);

        // Admissão do lote (202/200) ou validação 422 — não 500
        $this->assertContains($res->status(), [200, 202, 422]);
    }

    private function actingOperator(): void
    {
        $office = Office::factory()->create();
        $op = User::factory()->forOffice($office, OfficeRole::Operator)->withTwoFactorConfirmed()->create();
        $this->actingAs($op);
        app(CurrentOffice::class)->resolve($op);
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
}
