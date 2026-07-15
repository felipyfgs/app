<?php

namespace Tests\Unit\Adn;

use App\Enums\FiscalRole;
use App\Services\Adn\NfseXmlParser;
use PHPUnit\Framework\TestCase;

class NfseXmlParserTest extends TestCase
{
    public function test_parse_nota_emitente_tomador_intermediario(): void
    {
        $parser = new NfseXmlParser;
        $xml = file_get_contents(dirname(__DIR__, 2).'/fixtures/adn/nfse_issuer.xml');
        $parsed = $parser->parseNote($xml);

        $this->assertSame('OK', $parsed['parse_status']);
        $this->assertSame('35260711222333000181550010000000011123456789', $parsed['access_key']);
        $this->assertSame('11222333000181', $parsed['issuer_cnpj']);
        $this->assertSame('99888777000166', $parsed['taker_cnpj']);
        $this->assertSame('2026-07', $parsed['competence']);
        $this->assertSame(FiscalRole::Issuer, ($parsed['fiscal_role_for'])('11222333000181'));
        $this->assertSame(FiscalRole::Taker, ($parsed['fiscal_role_for'])('99888777000166'));
    }

    public function test_parse_intermediario(): void
    {
        $parser = new NfseXmlParser;
        $xml = file_get_contents(dirname(__DIR__, 2).'/fixtures/adn/nfse_intermediary.xml');
        $parsed = $parser->parseNote($xml);
        $this->assertSame(FiscalRole::Intermediary, ($parsed['fiscal_role_for'])('11222333000181'));
    }

    public function test_xml_malformado_preserva_alerta(): void
    {
        $parser = new NfseXmlParser;
        $parsed = $parser->parseNote('<not-closed');
        $this->assertSame('FAILED', $parsed['parse_status']);
        $this->assertNotNull($parsed['parse_alert']);
    }

    public function test_evento_cancelamento(): void
    {
        $parser = new NfseXmlParser;
        $xml = file_get_contents(dirname(__DIR__, 2).'/fixtures/adn/event_cancel.xml');
        $parsed = $parser->parseEvent($xml);
        $this->assertSame('OK', $parsed['parse_status']);
        $this->assertSame('CANCELAMENTO', $parsed['event_type']);
        $this->assertSame('35260711222333000181550010000000011123456789', $parsed['access_key']);
    }

    public function test_layout_nacional_chave_em_id_inf_nfse(): void
    {
        $parser = new NfseXmlParser;
        $xml = <<<'XML'
<?xml version="1.0" encoding="utf-8"?>
<NFSe versao="1.00" xmlns="http://www.sped.fazenda.gov.br/nfse">
  <infNFSe Id="NFS21053022254880350000119000000000005024102528648917">
    <xLocEmi>Imperatriz</xLocEmi>
    <xLocPrestacao>Imperatriz</xLocPrestacao>
    <nNFSe>64</nNFSe>
    <cStat>100</cStat>
    <emit>
      <CNPJ>54880350000119</CNPJ>
      <xNome>PRESTADOR EXEMPLO LTDA</xNome>
    </emit>
    <valores><vLiq>375.00</vLiq></valores>
    <DPS>
      <infDPS>
        <dhEmi>2024-10-16T13:23:49-03:00</dhEmi>
        <dCompet>2024-10-16</dCompet>
        <toma>
          <CNPJ>34194865000158</CNPJ>
          <xNome>S. E. L. DE SOUZA SUARES VEICULOS</xNome>
        </toma>
        <valores><vServPrest><vServ>375.00</vServ></vServPrest></valores>
      </infDPS>
    </DPS>
  </infNFSe>
</NFSe>
XML;
        $parsed = $parser->parseNote($xml);
        $this->assertSame('OK', $parsed['parse_status']);
        $this->assertSame('21053022254880350000119000000000005024102528648917', $parsed['access_key']);
        $this->assertSame('64', $parsed['number']);
        $this->assertSame('54880350000119', $parsed['issuer_cnpj']);
        $this->assertSame('PRESTADOR EXEMPLO LTDA', $parsed['issuer_name']);
        $this->assertSame('34194865000158', $parsed['taker_cnpj']);
        $this->assertSame('S. E. L. DE SOUZA SUARES VEICULOS', $parsed['taker_name']);
        $this->assertSame('375.00', $parsed['service_amount']);
        $this->assertSame('2024-10', $parsed['competence']);
        $this->assertSame('Imperatriz', $parsed['issue_location']);
        $this->assertSame('100', $parsed['official_status_code']);
        $this->assertSame('ACTIVE', $parsed['status']);
        $this->assertSame(FiscalRole::Taker, ($parsed['fiscal_role_for'])('34194865000158'));
    }

    public function test_cstat_101_e_substituta_nao_cancelada(): void
    {
        $parser = new NfseXmlParser;
        $xml = <<<'XML'
<?xml version="1.0" encoding="utf-8"?>
<NFSe versao="1.00" xmlns="http://www.sped.fazenda.gov.br/nfse">
  <infNFSe Id="NFS21053022254880350000119000000000005024102528648918">
    <nNFSe>65</nNFSe>
    <cStat>101</cStat>
    <emit><CNPJ>54880350000119</CNPJ><xNome>PRESTADOR</xNome></emit>
    <DPS><infDPS><dhEmi>2024-10-16T13:23:49-03:00</dhEmi><dCompet>2024-10-16</dCompet>
      <toma><CNPJ>34194865000158</CNPJ><xNome>TOMADOR</xNome></toma>
      <valores><vServPrest><vServ>10.00</vServ></vServPrest></valores>
    </infDPS></DPS>
  </infNFSe>
</NFSe>
XML;
        $parsed = $parser->parseNote($xml);
        $this->assertSame('101', $parsed['official_status_code']);
        $this->assertSame('SUBSTITUTE', $parsed['status']);
    }

    public function test_cstat_102_judicial_e_ausente_unknown(): void
    {
        $parser = new NfseXmlParser;
        $with102 = <<<'XML'
<?xml version="1.0"?><NFSe><infNFSe Id="NFS21053022254880350000119000000000005024102528648919">
  <cStat>102</cStat><nNFSe>1</nNFSe>
  <emit><CNPJ>54880350000119</CNPJ></emit>
  <DPS><infDPS><dhEmi>2024-10-16T13:23:49-03:00</dhEmi><dCompet>2024-10-16</dCompet>
    <toma><CNPJ>34194865000158</CNPJ></toma>
    <valores><vServPrest><vServ>1</vServ></vServPrest></valores>
  </infDPS></DPS>
</infNFSe></NFSe>
XML;
        $this->assertSame('JUDICIAL', $parser->parseNote($with102)['status']);

        $noStat = <<<'XML'
<?xml version="1.0"?><NFSe><infNFSe Id="NFS21053022254880350000119000000000005024102528648920">
  <nNFSe>2</nNFSe>
  <emit><CNPJ>54880350000119</CNPJ></emit>
  <DPS><infDPS><dhEmi>2024-10-16T13:23:49-03:00</dhEmi><dCompet>2024-10-16</dCompet>
    <toma><CNPJ>34194865000158</CNPJ></toma>
    <valores><vServPrest><vServ>1</vServ></vServPrest></valores>
  </infDPS></DPS>
</infNFSe></NFSe>
XML;
        $this->assertSame('UNKNOWN', $parser->parseNote($noStat)['status']);
    }
}
