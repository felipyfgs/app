<?php

namespace Tests\Unit\Sefaz;

use App\Services\Adn\CurlMtlsTransport;
use App\Services\Sefaz\HttpSefazNfeManifestationClient;
use App\Services\Sefaz\ManifestationResponseParser;
use ReflectionClass;
use Tests\TestCase;

class ManifestationResponseParserTest extends TestCase
{
    public function test_parse_ret_env_evento_aceito(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<retEnvEvento versao="1.00" xmlns="http://www.portalfiscal.inf.br/nfe">
  <idLote>1</idLote>
  <tpAmb>1</tpAmb>
  <verAplic>AN</verAplic>
  <cOrgao>91</cOrgao>
  <cStat>128</cStat>
  <xMotivo>Lote de Evento Processado</xMotivo>
  <retEvento versao="1.00">
    <infEvento>
      <tpAmb>1</tpAmb>
      <verAplic>AN</verAplic>
      <cOrgao>91</cOrgao>
      <cStat>135</cStat>
      <xMotivo>Evento registrado e vinculado a NF-e</xMotivo>
      <chNFe>35240111222333000181550010000000011000000010</chNFe>
      <tpEvento>210210</tpEvento>
      <xEvento>Ciencia da Operacao</xEvento>
      <nSeqEvento>1</nSeqEvento>
      <dhRegEvento>2026-07-14T12:00:00-03:00</dhRegEvento>
      <nProt>191234567890123</nProt>
    </infEvento>
  </retEvento>
</retEnvEvento>
XML;

        $dto = (new ManifestationResponseParser)->parse($xml);
        $this->assertSame('128', $dto->cStat);
        $this->assertSame('135', $dto->eventCStat);
        $this->assertTrue($dto->isAccepted());
        $this->assertSame('191234567890123', $dto->protocol);
        $this->assertSame('210210', $dto->tpEvento);
    }

    public function test_parse_rejeicao_evento(): void
    {
        $xml = <<<'XML'
<retEnvEvento xmlns="http://www.portalfiscal.inf.br/nfe" versao="1.00">
  <cStat>128</cStat>
  <xMotivo>Lote de Evento Processado</xMotivo>
  <retEvento versao="1.00">
    <infEvento>
      <cStat>573</cStat>
      <xMotivo>Rejeicao: Duplicidade de Evento</xMotivo>
      <tpEvento>210210</tpEvento>
    </infEvento>
  </retEvento>
</retEnvEvento>
XML;

        $dto = (new ManifestationResponseParser)->parse($xml);
        $this->assertFalse($dto->isAccepted());
        $this->assertSame('573', $dto->effectiveCStat());
    }

    public function test_client_source_sem_pem_temp(): void
    {
        $source = file_get_contents((new ReflectionClass(HttpSefazNfeManifestationClient::class))->getFileName());
        $this->assertStringNotContainsString('tempnam', $source);
        $this->assertStringNotContainsString('sys_get_temp_dir', $source);
        $this->assertStringNotContainsString('file_put_contents', $source);
        $this->assertStringContainsString('CURLOPT_SSLCERT_BLOB', file_get_contents(
            (new ReflectionClass(CurlMtlsTransport::class))->getFileName()
        ));
    }
}
