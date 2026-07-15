<?php

namespace Tests\Feature\Import;

use App\Enums\OfficeRole;
use App\Models\Office;
use App\Models\User;
use App\Support\CurrentOffice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;
use ZipArchive;

class DocumentImportMixedSourcesTest extends TestCase
{
    use RefreshDatabase;

    public function test_lote_misto_xml_55_e_65_e_zip(): void
    {
        $office = Office::factory()->create();
        $op = User::factory()->forOffice($office, OfficeRole::Operator)->withTwoFactorConfirmed()->create();
        $this->actingAs($op);
        app(CurrentOffice::class)->resolve($op);

        $xml55 = file_get_contents(base_path('tests/fixtures/autxml/procNFe_55_autxml_ok.xml'));
        $xml65 = file_get_contents(base_path('tests/fixtures/autxml/procNFe_65_nfce_not_autxml_channel.xml'));
        $this->assertNotFalse($xml55);
        $this->assertNotFalse($xml65);

        $zipPath = tempnam(sys_get_temp_dir(), 'impzip');
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($zipPath, ZipArchive::OVERWRITE));
        $zip->addFromString('subdir/nota65.xml', $xml65);
        $zip->close();
        $zipBytes = file_get_contents($zipPath);
        @unlink($zipPath);
        $this->assertNotFalse($zipBytes);

        $files = [
            UploadedFile::fake()->createWithContent('nfe55.xml', $xml55),
            UploadedFile::fake()->createWithContent('lote.zip', $zipBytes),
        ];

        $res = $this->post('/api/v1/documents/import-batches', [
            'files' => $files,
        ], ['Accept' => 'application/json']);

        $this->assertContains($res->status(), [200, 202]);
        $publicId = $res->json('data.public_id') ?? $res->json('data.id');
        $this->assertNotEmpty($publicId);

        // Não deve vazar XML
        $body = $res->getContent() ?: '';
        $this->assertStringNotContainsString('<NFe', $body);
        $this->assertStringNotContainsString('BEGIN ', $body);
    }
}
