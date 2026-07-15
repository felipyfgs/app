<?php

namespace Tests\Feature\Import;

use App\Contracts\SecureObjectStore;
use App\Enums\AdnDocumentType;
use App\Enums\DocumentDirection;
use App\Enums\FiscalRole;
use App\Enums\OfficeRole;
use App\Models\Client;
use App\Models\DfeDocument;
use App\Models\Establishment;
use App\Models\NfeDocument;
use App\Models\Office;
use App\Models\User;
use App\Support\CurrentOffice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class OutboundXmlImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_operator_importa_procnfe_como_saida(): void
    {
        [$office, $user, $client] = $this->seedOfficeWithClient('11222333000181');
        $this->actingAs($user);
        app(CurrentOffice::class)->resolve($user);

        $xml = $this->sampleProcNfe('11222333000181', '99888777000166', '35260711222333000181550010000000011234567890');
        $file = UploadedFile::fake()->createWithContent('nfe.xml', $xml);

        $response = $this->post('/api/v1/documents/import', [
            'files' => [$file],
            'client_id' => $client->id,
        ], ['Accept' => 'application/json']);

        $response->assertOk()
            ->assertJsonPath('data.imported', 1)
            ->assertJsonPath('data.errors', 0);

        $this->assertDatabaseHas('nfe_documents', [
            'office_id' => $office->id,
            'access_key' => '35260711222333000181550010000000011234567890',
            'direction' => 'OUT',
            'is_summary' => false,
        ]);

        $this->getJson('/api/v1/documents?kind=NFE&direction=OUT')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.direction', 'OUT')
            ->assertJsonPath('data.0.has_full_xml', true);
    }

    public function test_import_duplicata_e_idempotente(): void
    {
        [$office, $user, $client] = $this->seedOfficeWithClient('11222333000181');
        $this->actingAs($user);
        app(CurrentOffice::class)->resolve($user);

        $xml = $this->sampleProcNfe('11222333000181', '99888777000166', '35260711222333000181550010000000011234567891');
        $file1 = UploadedFile::fake()->createWithContent('nfe.xml', $xml);
        $file2 = UploadedFile::fake()->createWithContent('nfe-copy.xml', $xml);

        $this->post('/api/v1/documents/import', [
            'files' => [$file1],
            'client_id' => $client->id,
        ], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('data.imported', 1);

        $this->post('/api/v1/documents/import', [
            'files' => [$file2],
            'client_id' => $client->id,
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.imported', 0)
            ->assertJsonPath('data.skipped', 1);

        $this->assertSame(1, NfeDocument::query()->where('office_id', $office->id)->count());
    }

    public function test_viewer_recebe_403(): void
    {
        $office = Office::factory()->create();
        $viewer = User::factory()->forOffice($office, OfficeRole::Viewer)->withTwoFactorConfirmed()->create();
        $this->actingAs($viewer);
        app(CurrentOffice::class)->resolve($viewer);

        $xml = $this->sampleProcNfe('11222333000181', '99888777000166', '35260711222333000181550010000000011234567892');
        $file = UploadedFile::fake()->createWithContent('nfe.xml', $xml);

        $this->post('/api/v1/documents/import', [
            'files' => [$file],
        ], ['Accept' => 'application/json'])->assertForbidden();
    }

    public function test_download_prefere_full_sobre_resumo(): void
    {
        [$office, $user] = $this->seedOfficeUser();
        $this->actingAs($user);
        app(CurrentOffice::class)->resolve($user);

        $key = '35260711222333000181550010000000011234567893';
        $summaryXml = '<resNFe><chNFe>'.$key.'</chNFe></resNFe>';
        $fullXml = $this->sampleProcNfe('11222333000181', '99888777000166', $key);

        $this->seedNfeProjection($office->id, $key, $summaryXml, true);
        $this->seedNfeProjection($office->id, $key, $fullXml, false);

        $download = $this->get('/api/v1/documents/'.$key.'/xml');
        $download->assertOk();
        $this->assertStringContainsString('nfeProc', $download->streamedContent());
        $this->assertStringNotContainsString('resNFe', $download->streamedContent());

        $detail = $this->getJson('/api/v1/documents/'.$key)->assertOk();
        $detail->assertJsonPath('data.note.has_full_xml', true);
        $detail->assertJsonPath('data.note.xml_completeness', 'FULL');
    }

    /**
     * @return array{0: Office, 1: User, 2: Client}
     */
    private function seedOfficeWithClient(string $cnpj): array
    {
        [$office, $user] = $this->seedOfficeUser();
        $client = Client::factory()->forOffice($office)->create(['root_cnpj' => substr($cnpj, 0, 8)]);
        Establishment::factory()->forClient($client)->create(['cnpj' => $cnpj]);

        return [$office, $user, $client];
    }

    /**
     * @return array{0: Office, 1: User}
     */
    private function seedOfficeUser(): array
    {
        $office = Office::factory()->create();
        $user = User::factory()->forOffice($office, OfficeRole::Operator)->withTwoFactorConfirmed()->create();

        return [$office, $user];
    }

    private function seedNfeProjection(int $officeId, string $accessKey, string $xml, bool $summary): void
    {
        $sha = hash('sha256', $xml.($summary ? 's' : 'f'));
        $store = app(SecureObjectStore::class);
        $objectId = $store->put($xml, ['office_id' => $officeId, 'sha256' => $sha]);

        $doc = DfeDocument::query()->create([
            'office_id' => $officeId,
            'sha256' => $sha,
            'document_type' => AdnDocumentType::Nfe,
            'schema_version' => $summary ? 'resNFe_v1.01.xsd' : 'procNFe_v4.00.xsd',
            'access_key' => $accessKey,
            'vault_object_id' => $objectId,
            'byte_size' => strlen($xml),
            'parse_status' => 'OK',
        ]);

        NfeDocument::query()->create([
            'office_id' => $officeId,
            'dfe_document_id' => $doc->id,
            'access_key' => $accessKey,
            'number' => '1',
            'model' => '55',
            'issuer_cnpj' => '11222333000181',
            'recipient_cnpj' => '99888777000166',
            'fiscal_role' => FiscalRole::Taker,
            'direction' => DocumentDirection::In,
            'issued_at' => '2026-07-01',
            'total_amount' => '10.00',
            'status' => $summary ? 'SUMMARY' : 'ACTIVE',
            'is_summary' => $summary,
        ]);
    }

    private function sampleProcNfe(string $emitCnpj, string $destCnpj, string $chave): string
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<nfeProc xmlns="http://www.portalfiscal.inf.br/nfe">
  <NFe>
    <infNFe Id="NFe{$chave}">
      <ide>
        <mod>55</mod>
        <serie>1</serie>
        <nNF>1</nNF>
        <dhEmi>2026-07-01T10:00:00-03:00</dhEmi>
      </ide>
      <emit>
        <CNPJ>{$emitCnpj}</CNPJ>
        <xNome>Emitente Teste</xNome>
      </emit>
      <dest>
        <CNPJ>{$destCnpj}</CNPJ>
        <xNome>Destinatario</xNome>
      </dest>
      <total><ICMSTot><vNF>100.00</vNF></ICMSTot></total>
    </infNFe>
  </NFe>
  <protNFe><infProt><chNFe>{$chave}</chNFe><nProt>135260000000001</nProt><cStat>100</cStat></infProt></protNFe>
</nfeProc>
XML;
    }
}
