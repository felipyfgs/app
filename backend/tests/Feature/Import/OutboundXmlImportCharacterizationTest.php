<?php

namespace Tests\Feature\Import;

use App\Enums\OfficeRole;
use App\Models\Client;
use App\Models\Establishment;
use App\Models\Office;
use App\Models\User;
use App\Services\Outbound\AccessKeyCandidateBuilder;
use App\Support\CurrentOffice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Caracterização do import síncrono atual (antes do batch assíncrono).
 * Contrato de resposta e efeitos colaterais que a transição deve preservar
 * ou migrar de forma explícita.
 */
class OutboundXmlImportCharacterizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_limites_de_config_import_alinhados_ao_design(): void
    {
        $this->assertFalse((bool) config('import.async_batches_enabled'));
        $this->assertSame(50, (int) config('import.max_top_level_files'));
        $this->assertSame(20 * 1024 * 1024, (int) config('import.max_request_compressed_bytes'));
        $this->assertSame(5000, (int) config('import.max_xml_entries_per_batch'));
        $this->assertSame(5 * 1024 * 1024, (int) config('import.max_xml_bytes'));
        $this->assertSame(250 * 1024 * 1024, (int) config('import.max_batch_uncompressed_bytes'));
        $this->assertSame(100.0, (float) config('import.max_compression_ratio'));
    }

    public function test_resposta_sincrona_contem_contadores_e_items(): void
    {
        [$office, $user, $client] = $this->seedOfficeWithClient('11222333000181');
        $this->actingAs($user);
        app(CurrentOffice::class)->resolve($user);

        $key = (new AccessKeyCandidateBuilder)->build([
            'cuf' => '35',
            'aamm' => '2607',
            'cnpj' => '11222333000181',
            'model' => '55',
            'series' => 1,
            'nnf' => 910,
            'tp_emis' => '1',
        ])['access_key'];
        $xml = $this->sampleProcNfe('11222333000181', '99888777000166', $key);
        $file = UploadedFile::fake()->createWithContent('nfe-out.xml', $xml);

        $response = $this->post('/api/v1/documents/import', [
            'files' => [$file],
            'client_id' => $client->id,
        ], ['Accept' => 'application/json']);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'imported',
                    'skipped',
                    'errors',
                    'items' => [
                        ['status', 'filename'],
                    ],
                ],
            ])
            ->assertJsonPath('data.imported', 1)
            ->assertJsonPath('data.errors', 0)
            ->assertJsonPath('data.items.0.status', 'imported');

        $body = $response->getContent() ?: '';
        $this->assertStringNotContainsString('vault_object_id', $body);
        $this->assertStringNotContainsString('BEGIN PRIVATE', $body);
        $this->assertStringNotContainsString('/tmp/', $body);
        // XML bruto não deve vazar na resposta JSON
        $this->assertStringNotContainsString('<nfeProc', $body);
        $this->assertStringNotContainsString($xml, $body);
    }

    public function test_import_sem_arquivos_rejeita_validacao(): void
    {
        [$office, $user] = $this->seedOfficeUser();
        $this->actingAs($user);
        app(CurrentOffice::class)->resolve($user);

        $this->postJson('/api/v1/documents/import', [])
            ->assertStatus(422);
    }

    public function test_client_id_de_outro_office_e_rejeitado(): void
    {
        [$officeA, $userA] = $this->seedOfficeUser();
        $officeB = Office::factory()->create();
        $clientB = Client::factory()->forOffice($officeB)->create();

        $this->actingAs($userA);
        app(CurrentOffice::class)->resolve($userA);

        $key = (new AccessKeyCandidateBuilder)->build([
            'cuf' => '35',
            'aamm' => '2607',
            'cnpj' => '11222333000181',
            'model' => '55',
            'series' => 1,
            'nnf' => 911,
            'tp_emis' => '1',
        ])['access_key'];
        $xml = $this->sampleProcNfe('11222333000181', '99888777000166', $key);
        $file = UploadedFile::fake()->createWithContent('nfe.xml', $xml);

        $this->post('/api/v1/documents/import', [
            'files' => [$file],
            'client_id' => $clientB->id,
        ], ['Accept' => 'application/json'])->assertStatus(422);
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
