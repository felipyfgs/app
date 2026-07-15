<?php

namespace Tests\Feature\Sefaz;

use App\Domain\Sefaz\DistDfeDocumentDto;
use App\Domain\Sefaz\DistDfePageDto;
use App\Enums\CaptureChannel;
use App\Enums\DocumentDirection;
use App\Enums\FiscalRole;
use App\Enums\OfficeAutXmlEnrollmentStatus;
use App\Enums\QuarantineReason;
use App\Enums\SyncCursorStatus;
use App\Models\Client;
use App\Models\Establishment;
use App\Models\FiscalDocumentQuarantine;
use App\Models\NfeDocument;
use App\Models\Office;
use App\Models\OfficeAutXmlEnrollment;
use App\Models\OfficeDistributionCursor;
use App\Models\OfficeFiscalIdentity;
use App\Services\Sefaz\OfficeAutXmlPageProcessor;
use App\Services\Sefaz\NfeXmlProjectionParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfficeAutXmlPageProcessorTest extends TestCase
{
    use RefreshDatabase;

    public function test_promove_procnfe_com_autxml_e_enrollment_como_issuer_out(): void
    {
        $officeCnpj = '11222333000181';
        $issuerCnpj = '99888777000166';
        $chave = '35260799888777000166550010000000011234567920';

        [$cursor, $estab] = $this->seedCursorWithEnrollment($officeCnpj, $issuerCnpj);

        $xml = $this->sampleProcNfe($issuerCnpj, '55444333000122', $chave, $officeCnpj);
        $page = $this->pageWithXml(10, $xml);

        $result = app(OfficeAutXmlPageProcessor::class)->process($cursor, $page);

        $this->assertSame(1, $result['documents']);
        $this->assertSame(0, $result['quarantined']);
        $this->assertSame(10, $result['advanced_to']);

        $this->assertDatabaseHas('nfe_documents', [
            'office_id' => $cursor->office_id,
            'access_key' => $chave,
            'direction' => DocumentDirection::Out->value,
            'fiscal_role' => FiscalRole::Issuer->value,
        ]);
        $this->assertDatabaseHas('document_interests', [
            'establishment_id' => $estab->id,
            'fiscal_role' => FiscalRole::Issuer->value,
            'channel' => CaptureChannel::NfeAutXmlDistDfe->value,
            'direction' => DocumentDirection::Out->value,
        ]);
        $this->assertDatabaseHas('document_acquisitions', [
            'office_id' => $cursor->office_id,
            'source' => 'AUTXML_DIST_NSU',
            'nsu' => 10,
        ]);
        $this->assertSame(0, FiscalDocumentQuarantine::query()->count());
        $this->assertNotNull(OfficeAutXmlEnrollment::query()->first()->first_seen_at);
    }

    public function test_quarentena_sem_emitente_cadastrado_ainda_avanca_nsu(): void
    {
        $officeCnpj = '11222333000181';
        $office = Office::factory()->create();
        $identity = OfficeFiscalIdentity::factory()->forOffice($office)->withCnpj($officeCnpj)->create();
        $cursor = OfficeDistributionCursor::factory()->forIdentity($identity)->create(['last_nsu' => 0]);

        $xml = $this->sampleProcNfe('99888777000166', '55444333000122', '35260799888777000166550010000000011234567921', $officeCnpj);
        $result = app(OfficeAutXmlPageProcessor::class)->process($cursor, $this->pageWithXml(3, $xml));

        $this->assertSame(0, $result['documents']);
        $this->assertSame(1, $result['quarantined']);
        $this->assertSame(3, $result['advanced_to']);
        $this->assertSame(0, NfeDocument::query()->count());
        $this->assertDatabaseHas('fiscal_document_quarantine', [
            'office_id' => $office->id,
            'reason' => QuarantineReason::UnmatchedIssuer->value,
            'nsu' => 3,
        ]);
    }

    public function test_tag_autxml_divergente_vai_para_quarentena(): void
    {
        $officeCnpj = '11222333000181';
        [$cursor] = $this->seedCursorWithEnrollment($officeCnpj, '99888777000166');

        $xml = $this->sampleProcNfe('99888777000166', '55444333000122', '35260799888777000166550010000000011234567922', '99888777000166');
        $result = app(OfficeAutXmlPageProcessor::class)->process($cursor, $this->pageWithXml(4, $xml));

        $this->assertSame(1, $result['quarantined']);
        $this->assertDatabaseHas('fiscal_document_quarantine', [
            'reason' => QuarantineReason::AutXmlTagDivergent->value,
        ]);
    }

    public function test_parser_extrai_todos_autxml_incluindo_alfanumerico(): void
    {
        $xml = <<<'XML'
<?xml version="1.0"?>
<nfeProc xmlns="http://www.portalfiscal.inf.br/nfe">
  <NFe><infNFe Id="NFe35260799888777000166550010000000011234567923">
    <ide><mod>55</mod><tpNF>1</tpNF><tpAmb>1</tpAmb></ide>
    <emit><CNPJ>99888777000166</CNPJ></emit>
    <dest><CNPJ>55444333000122</CNPJ></dest>
    <autXML><CNPJ>11222333000181</CNPJ></autXML>
    <autXML><CNPJ>ab12cd340001ef</CNPJ></autXML>
  </infNFe></NFe>
  <protNFe><infProt><chNFe>35260799888777000166550010000000011234567923</chNFe><cStat>100</cStat></infProt></protNFe>
</nfeProc>
XML;
        $parsed = (new NfeXmlProjectionParser)->parse($xml, 'procNFe');
        $this->assertSame('55', $parsed['model']);
        $this->assertSame('1', $parsed['tp_nf']);
        $this->assertContains('11222333000181', $parsed['aut_xml_cnpjs']);
        $this->assertContains('AB12CD340001EF', $parsed['aut_xml_cnpjs']);
    }

    public function test_cstat_137_agenda_quiet_e_marca_activated(): void
    {
        $office = Office::factory()->create();
        $identity = OfficeFiscalIdentity::factory()->forOffice($office)->create();
        $cursor = OfficeDistributionCursor::factory()->forIdentity($identity)->create([
            'last_nsu' => 0,
            'activated_at' => null,
        ]);

        $page = new DistDfePageDto(
            cStat: '137',
            xMotivo: 'Nenhum documento localizado',
            ultNsu: 0,
            maxNsu: 0,
            documents: [],
        );

        $result = app(OfficeAutXmlPageProcessor::class)->process($cursor, $page);
        $this->assertSame(0, $result['documents']);
        $fresh = $cursor->fresh();
        $this->assertNotNull($fresh->activated_at);
        $this->assertSame(SyncCursorStatus::Idle, $fresh->status);
        $this->assertNotNull($fresh->next_sync_at);
    }

    public function test_nao_enfileira_ciencia_no_processador_autxml(): void
    {
        // Garante que a classe não depende de AutoCienciaScheduler (construtor sem auto ciência).
        $ref = new \ReflectionClass(OfficeAutXmlPageProcessor::class);
        $ctor = $ref->getConstructor();
        $params = $ctor?->getParameters() ?? [];
        $types = array_map(fn ($p) => (string) $p->getType(), $params);
        $this->assertFalse(
            collect($types)->contains(fn ($t) => str_contains($t, 'AutoCiencia')),
            'OfficeAutXmlPageProcessor não deve injetar AutoCienciaScheduler'
        );
    }

    /**
     * @return array{0: OfficeDistributionCursor, 1: Establishment}
     */
    private function seedCursorWithEnrollment(string $officeCnpj, string $issuerCnpj): array
    {
        $office = Office::factory()->create();
        $identity = OfficeFiscalIdentity::factory()->forOffice($office)->withCnpj($officeCnpj)->create();
        $cursor = OfficeDistributionCursor::factory()->forIdentity($identity)->create(['last_nsu' => 0]);

        $client = Client::factory()->forOffice($office)->create(['root_cnpj' => substr($issuerCnpj, 0, 8)]);
        $estab = Establishment::factory()->forClient($client)->create([
            'office_id' => $office->id,
            'cnpj' => $issuerCnpj,
            'is_active' => true,
        ]);

        OfficeAutXmlEnrollment::query()->create([
            'office_id' => $office->id,
            'office_fiscal_identity_id' => $identity->id,
            'establishment_id' => $estab->id,
            'status' => OfficeAutXmlEnrollmentStatus::Pending,
        ]);

        return [$cursor, $estab];
    }

    private function pageWithXml(int $nsu, string $xml): DistDfePageDto
    {
        $b64 = base64_encode(gzencode($xml));

        return new DistDfePageDto(
            cStat: '138',
            xMotivo: 'Documento(s) localizado(s)',
            ultNsu: $nsu,
            maxNsu: $nsu + 10,
            documents: [
                new DistDfeDocumentDto(
                    nsu: $nsu,
                    schema: 'procNFe_v4.00.xsd',
                    contentBase64: $b64,
                    schemaFamily: 'procNFe',
                ),
            ],
        );
    }

    private function sampleProcNfe(string $emitCnpj, string $destCnpj, string $chave, string $autXmlCnpj): string
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<nfeProc xmlns="http://www.portalfiscal.inf.br/nfe">
  <NFe>
    <infNFe Id="NFe{$chave}">
      <ide>
        <mod>55</mod>
        <tpNF>1</tpNF>
        <tpAmb>1</tpAmb>
        <serie>1</serie>
        <nNF>1</nNF>
        <dhEmi>2026-07-01T10:00:00-03:00</dhEmi>
      </ide>
      <emit>
        <CNPJ>{$emitCnpj}</CNPJ>
        <xNome>Emitente</xNome>
      </emit>
      <dest>
        <CNPJ>{$destCnpj}</CNPJ>
        <xNome>Destinatario</xNome>
      </dest>
      <autXML>
        <CNPJ>{$autXmlCnpj}</CNPJ>
      </autXML>
      <total><ICMSTot><vNF>100.00</vNF></ICMSTot></total>
    </infNFe>
  </NFe>
  <protNFe><infProt><chNFe>{$chave}</chNFe><cStat>100</cStat><tpAmb>1</tpAmb></infProt></protNFe>
</nfeProc>
XML;
    }
}
