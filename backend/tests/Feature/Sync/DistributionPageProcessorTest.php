<?php

namespace Tests\Feature\Sync;

use App\Domain\Adn\DistributionDocumentDto;
use App\Domain\Adn\DistributionPageDto;
use App\Enums\AdnDocumentType;
use App\Enums\SyncCursorStatus;
use App\Models\Client;
use App\Models\Establishment;
use App\Models\Office;
use App\Models\SyncCursor;
use App\Services\Adn\DistributionPageProcessor;
use App\Support\CurrentOffice;
use Database\Factories\EstablishmentFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DistributionPageProcessorTest extends TestCase
{
    use RefreshDatabase;

    public function test_persiste_pagina_e_avanca_nsu(): void
    {
        $office = Office::factory()->create();
        app(CurrentOffice::class)->clear();
        // desativa global scope via set office no container sem user
        $client = Client::factory()->forOffice($office)->create(['root_cnpj' => '11222333']);
        $cnpj = EstablishmentFactory::cnpjWithRoot('11222333');
        $establishment = Establishment::factory()->forClient($client, $cnpj)->create();

        $cursor = SyncCursor::query()->create([
            'office_id' => $office->id,
            'establishment_id' => $establishment->id,
            'environment' => 'test',
            'last_nsu' => 0,
            'status' => SyncCursorStatus::Idle,
        ]);

        $xml = '<?xml version="1.0"?><NFSe><chNFSe>12345678901234567890123456789012345678901234</chNFSe><emit><CNPJ>11222333000181</CNPJ></emit></NFSe>';
        $b64 = base64_encode(gzencode($xml));

        $page = new DistributionPageDto(
            status: 'DOCUMENTOS_LOCALIZADOS',
            maxNsu: 5,
            ultimoNsu: 1,
            documents: [
                new DistributionDocumentDto(1, AdnDocumentType::Nfse, 'NFSe_v1.00.xsd', $b64),
            ],
            hasMore: true,
        );

        $result = app(DistributionPageProcessor::class)->process($cursor, $establishment, $page);

        $this->assertSame(1, $result['documents']);
        $this->assertSame(1, $result['advanced_to']);
        $this->assertDatabaseHas('dfe_documents', ['office_id' => $office->id]);
        $this->assertDatabaseHas('document_interests', [
            'establishment_id' => $establishment->id,
            'nsu' => 1,
        ]);
        $this->assertDatabaseHas('nfse_notes', [
            'access_key' => '12345678901234567890123456789012345678901234',
        ]);
        $this->assertSame(1, $cursor->fresh()->last_nsu);
    }

    public function test_nao_avanca_em_falha_de_decode(): void
    {
        $office = Office::factory()->create();
        $client = Client::factory()->forOffice($office)->create(['root_cnpj' => '11222333']);
        $establishment = Establishment::factory()->forClient($client, EstablishmentFactory::cnpjWithRoot('11222333'))->create();
        $cursor = SyncCursor::query()->create([
            'office_id' => $office->id,
            'establishment_id' => $establishment->id,
            'environment' => 'test',
            'last_nsu' => 0,
            'status' => SyncCursorStatus::Idle,
        ]);

        $page = new DistributionPageDto(
            status: 'DOCUMENTOS_LOCALIZADOS',
            maxNsu: 1,
            ultimoNsu: 1,
            documents: [
                new DistributionDocumentDto(1, AdnDocumentType::Nfse, 'x', '@@@'),
            ],
            hasMore: false,
        );

        try {
            app(DistributionPageProcessor::class)->process($cursor, $establishment, $page);
            $this->fail('esperava exceção');
        } catch (\Throwable) {
            // expected
        }

        $this->assertSame(0, $cursor->fresh()->last_nsu);
        $this->assertGreaterThanOrEqual(1, $cursor->fresh()->consecutive_decode_failures);
    }

    public function test_chave_envelope_nao_promove_parse_failed_para_ok(): void
    {
        $office = Office::factory()->create();
        $client = Client::factory()->forOffice($office)->create(['root_cnpj' => '11222333']);
        $establishment = Establishment::factory()
            ->forClient($client, EstablishmentFactory::cnpjWithRoot('11222333'))
            ->create();
        $cursor = SyncCursor::query()->create([
            'office_id' => $office->id,
            'establishment_id' => $establishment->id,
            'environment' => 'test',
            'last_nsu' => 0,
            'status' => SyncCursorStatus::Idle,
        ]);

        // XML malformado (gzip válido) + ChaveAcesso no envelope — não pode virar OK.
        $malformed = '<<<not-xml>>>';
        $b64 = base64_encode(gzencode($malformed));
        $envelopeKey = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ012345678901234567';

        $page = new DistributionPageDto(
            status: 'DOCUMENTOS_LOCALIZADOS',
            maxNsu: 1,
            ultimoNsu: 1,
            documents: [
                new DistributionDocumentDto(
                    1,
                    AdnDocumentType::Nfse,
                    'NFSe_v1.00.xsd',
                    $b64,
                    $envelopeKey,
                ),
            ],
            hasMore: false,
        );

        $result = app(DistributionPageProcessor::class)->process($cursor, $establishment, $page);

        $this->assertSame(1, $result['documents']);
        $this->assertDatabaseHas('dfe_documents', [
            'office_id' => $office->id,
            'access_key' => $envelopeKey,
            'parse_status' => 'FAILED',
        ]);
        $this->assertDatabaseMissing('dfe_documents', [
            'office_id' => $office->id,
            'parse_status' => 'OK',
        ]);
    }

    public function test_projecao_enriquece_campos_do_layout_nacional(): void
    {
        $office = Office::factory()->create();
        $client = Client::factory()->forOffice($office)->create(['root_cnpj' => '11222333']);
        $establishment = Establishment::factory()
            ->forClient($client, EstablishmentFactory::cnpjWithRoot('11222333'))
            ->create();
        $cursor = SyncCursor::query()->create([
            'office_id' => $office->id,
            'establishment_id' => $establishment->id,
            'environment' => 'test',
            'last_nsu' => 0,
            'status' => SyncCursorStatus::Idle,
        ]);

        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<NFSe>
  <infNFSe Id="NFS12345678901234567890123456789012345678901234">
    <nNFSe>42</nNFSe>
    <cStat>100</cStat>
    <xLocEmi>SAO PAULO</xLocEmi>
    <xLocPrestacao>CAMPINAS</xLocPrestacao>
    <emit>
      <CNPJ>11222333000181</CNPJ>
      <xNome>Emitente Demo LTDA</xNome>
    </emit>
    <toma>
      <CNPJ>99888777000166</CNPJ>
      <xNome>Tomador Demo SA</xNome>
    </toma>
    <infDPS>
      <dhEmi>2026-07-01T10:00:00-03:00</dhEmi>
    </infDPS>
    <vServPrest>
      <vServ>1500.50</vServ>
    </vServPrest>
  </infNFSe>
</NFSe>
XML;
        $b64 = base64_encode(gzencode($xml));

        $page = new DistributionPageDto(
            status: 'DOCUMENTOS_LOCALIZADOS',
            maxNsu: 1,
            ultimoNsu: 1,
            documents: [
                new DistributionDocumentDto(1, AdnDocumentType::Nfse, 'NFSe_v1.00.xsd', $b64),
            ],
            hasMore: false,
        );

        app(DistributionPageProcessor::class)->process($cursor, $establishment, $page);

        $this->assertDatabaseHas('nfse_notes', [
            'office_id' => $office->id,
            'access_key' => '12345678901234567890123456789012345678901234',
            'number' => '42',
            'issuer_name' => 'Emitente Demo LTDA',
            'taker_name' => 'Tomador Demo SA',
            'issue_location' => 'SAO PAULO',
            'service_location' => 'CAMPINAS',
            'official_status_code' => '100',
        ]);
    }
}
