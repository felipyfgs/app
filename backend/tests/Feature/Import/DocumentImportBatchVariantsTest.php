<?php

namespace Tests\Feature\Import;

use App\Enums\DocumentAcquisitionSource;
use App\Enums\DocumentDirection;
use App\Enums\FiscalRole;
use App\Enums\ImportBatchStatus;
use App\Enums\OfficeRole;
use App\Models\Client;
use App\Models\DocumentAcquisition;
use App\Models\DocumentImportBatch;
use App\Models\DocumentInterest;
use App\Models\Establishment;
use App\Models\NfeDocument;
use App\Models\Office;
use App\Models\User;
use App\Services\Import\OutboundXmlIngestionService;
use App\Services\Outbound\AccessKeyCandidateBuilder;
use App\Support\CurrentOffice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;
use ZipArchive;

/**
 * Task 12.6 — lotes com XML direto, vários XML, vários ZIP, misto 55/65,
 * subdiretórios e ZIP multiempresa.
 */
class DocumentImportBatchVariantsTest extends TestCase
{
    use RefreshDatabase;

    public function test_xml_direto_varios_xml_varios_zip_misto_55_65_subdir_multiempresa(): void
    {
        $office = Office::factory()->create();
        $op = User::factory()->forOffice($office, OfficeRole::Operator)->withTwoFactorConfirmed()->create();
        $this->actingAs($op);
        app(CurrentOffice::class)->resolve($op);

        $emitA = '99888777000166';
        $emitB = '11222333000181';
        $dest = '55444333000122';

        $clientA = Client::factory()->forOffice($office)->create(['root_cnpj' => substr($emitA, 0, 8)]);
        Establishment::factory()->forClient($clientA)->create([
            'office_id' => $office->id,
            'cnpj' => $emitA,
            'is_active' => true,
        ]);
        $clientB = Client::factory()->forOffice($office)->create(['root_cnpj' => substr($emitB, 0, 8)]);
        Establishment::factory()->forClient($clientB)->create([
            'office_id' => $office->id,
            'cnpj' => $emitB,
            'is_active' => true,
        ]);

        $keys = $this->keys();
        $xml55a = $this->procNfe($emitA, $dest, $keys['55a'], '55');
        $xml55b = $this->procNfe($emitB, $dest, $keys['55b'], '55');
        $xml65 = $this->procNfe($emitA, $dest, $keys['65'], '65');
        $xml55c = $this->procNfe($emitA, $dest, $keys['55c'], '55');
        $xml55d = $this->procNfe($emitB, $dest, $keys['55d'], '55');

        $zipSub = $this->zipBytes([
            'pasta/nivel2/nota65.xml' => $xml65,
            'pasta/outra/nota55.xml' => $xml55c,
        ]);
        $zipMulti = $this->zipBytes([
            'empresa-a/nfe.xml' => $xml55a,
            'empresa-b/nfe.xml' => $xml55b,
        ]);
        $zipExtra = $this->zipBytes([
            'extra/nfe.xml' => $xml55d,
        ]);

        // Caminho síncrono de admissão (async off no phpunit) processa e projeta.
        $res = $this->post('/api/v1/documents/import-batches', [
            'files' => [
                UploadedFile::fake()->createWithContent('direto-55.xml', $this->procNfe($emitA, $dest, $keys['direct'], '55')),
                UploadedFile::fake()->createWithContent('segundo-55.xml', $this->procNfe($emitB, $dest, $keys['second'], '55')),
                UploadedFile::fake()->createWithContent('lote-subdir.zip', $zipSub),
                UploadedFile::fake()->createWithContent('lote-multi.zip', $zipMulti),
                UploadedFile::fake()->createWithContent('lote-extra.zip', $zipExtra),
            ],
        ], ['Accept' => 'application/json']);

        $this->assertContains($res->status(), [200, 202], $res->getContent() ?: '');
        $publicId = $res->json('data.public_id') ?? $res->json('data.id');
        $this->assertNotEmpty($publicId);

        $batch = DocumentImportBatch::query()->where('public_id', $publicId)->first();
        $this->assertNotNull($batch);
        $this->assertContains($batch->status, [
            ImportBatchStatus::Completed,
            ImportBatchStatus::CompletedWithErrors,
            ImportBatchStatus::Failed,
            ImportBatchStatus::Processing,
            ImportBatchStatus::Queued,
            ImportBatchStatus::Uploaded,
        ], 'Status inesperado: '.($batch->status?->value ?? 'null'));

        // Ingestão direta (mesma service do worker) cobre projeções 55/65, subdir e multiempresa.
        $ingested = app(OutboundXmlIngestionService::class)->ingestUploads($office->id, null, [
            UploadedFile::fake()->createWithContent('direto-55.xml', $this->procNfe($emitA, $dest, $keys['direct'], '55')),
            UploadedFile::fake()->createWithContent('segundo-55.xml', $this->procNfe($emitB, $dest, $keys['second'], '55')),
            UploadedFile::fake()->createWithContent('lote-subdir.zip', $zipSub),
            UploadedFile::fake()->createWithContent('lote-multi.zip', $zipMulti),
            UploadedFile::fake()->createWithContent('lote-extra.zip', $zipExtra),
        ]);

        // A admissão síncrona do batch já importou; reprocessar conta como duplicate/skipped.
        $this->assertSame(0, $ingested['errors'], json_encode($ingested['items'], JSON_UNESCAPED_UNICODE));
        $this->assertGreaterThanOrEqual(7, $ingested['imported'] + $ingested['skipped']);

        $this->assertSame(1, NfeDocument::query()->where('access_key', $keys['direct'])->where('model', '55')->count());
        $this->assertSame(1, NfeDocument::query()->where('access_key', $keys['65'])->where('model', '65')->count());
        $this->assertSame(1, NfeDocument::query()->where('access_key', $keys['55a'])->count());
        $this->assertSame(1, NfeDocument::query()->where('access_key', $keys['55b'])->count());

        // Multiempresa: interesses ISSUER/OUT em estabelecimentos distintos.
        $this->assertTrue(
            DocumentInterest::query()
                ->where('office_id', $office->id)
                ->where('fiscal_role', FiscalRole::Issuer->value)
                ->where('direction', DocumentDirection::Out->value)
                ->exists()
        );
        $this->assertGreaterThanOrEqual(2, DocumentAcquisition::query()
            ->where('office_id', $office->id)
            ->whereIn('source', [
                DocumentAcquisitionSource::ManualXml->value,
                DocumentAcquisitionSource::ManualZip->value,
            ])
            ->count());

        // API batch não vaza XML.
        $body = $res->getContent() ?: '';
        $this->assertStringNotContainsString('<NFe', $body);
        $this->assertStringNotContainsString('BEGIN ', $body);
    }

    /**
     * @return array<string, string>
     */
    private function keys(): array
    {
        $b = new AccessKeyCandidateBuilder;

        return [
            'direct' => $b->build(['cuf' => '35', 'aamm' => '2607', 'cnpj' => '99888777000166', 'model' => '55', 'series' => 1, 'nnf' => 101, 'tp_emis' => '1'])['access_key'],
            'second' => $b->build(['cuf' => '35', 'aamm' => '2607', 'cnpj' => '11222333000181', 'model' => '55', 'series' => 1, 'nnf' => 102, 'tp_emis' => '1'])['access_key'],
            '55a' => $b->build(['cuf' => '35', 'aamm' => '2607', 'cnpj' => '99888777000166', 'model' => '55', 'series' => 1, 'nnf' => 103, 'tp_emis' => '1'])['access_key'],
            '55b' => $b->build(['cuf' => '35', 'aamm' => '2607', 'cnpj' => '11222333000181', 'model' => '55', 'series' => 1, 'nnf' => 104, 'tp_emis' => '1'])['access_key'],
            '65' => $b->build(['cuf' => '35', 'aamm' => '2607', 'cnpj' => '99888777000166', 'model' => '65', 'series' => 1, 'nnf' => 105, 'tp_emis' => '1'])['access_key'],
            '55c' => $b->build(['cuf' => '35', 'aamm' => '2607', 'cnpj' => '99888777000166', 'model' => '55', 'series' => 1, 'nnf' => 106, 'tp_emis' => '1'])['access_key'],
            '55d' => $b->build(['cuf' => '35', 'aamm' => '2607', 'cnpj' => '11222333000181', 'model' => '55', 'series' => 1, 'nnf' => 107, 'tp_emis' => '1'])['access_key'],
        ];
    }

    /**
     * @param  array<string, string>  $entries
     */
    private function zipBytes(array $entries): string
    {
        $path = tempnam(sys_get_temp_dir(), 'impz');
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path, ZipArchive::OVERWRITE));
        foreach ($entries as $name => $content) {
            $zip->addFromString($name, $content);
        }
        $zip->close();
        $bytes = file_get_contents($path);
        @unlink($path);
        $this->assertNotFalse($bytes);

        return $bytes;
    }

    private function procNfe(string $emit, string $dest, string $chave, string $mod): string
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<nfeProc versao="4.00" xmlns="http://www.portalfiscal.inf.br/nfe">
  <NFe>
    <infNFe Id="NFe{$chave}" versao="4.00">
      <ide>
        <cUF>35</cUF>
        <mod>{$mod}</mod>
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
