<?php

namespace Tests\Unit\Sefaz;

use App\Services\Sefaz\DistDfeResponseParser;
use Tests\TestCase;

class DistDfeResponseParserTest extends TestCase
{
    public function test_parse_cstat_138_com_doczip(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="utf-8"?>
<retDistDFeInt xmlns="http://www.portalfiscal.inf.br/nfe" versao="1.01">
  <tpAmb>1</tpAmb>
  <cStat>138</cStat>
  <xMotivo>Documento(s) localizado(s)</xMotivo>
  <ultNSU>000000000000010</ultNSU>
  <maxNSU>000000000000100</maxNSU>
  <loteDistDFeInt>
    <docZip NSU="000000000000010" schema="resNFe_v1.01.xsd">H4sIAAAAAAAAAytJLSSxK8hIzcnJ11EIzy/KSVFU8EjNycnXUQjPL8pJUQQAAP//</docZip>
  </loteDistDFeInt>
</retDistDFeInt>
XML;

        $page = (new DistDfeResponseParser)->parse($xml);
        $this->assertSame('138', $page->cStat);
        $this->assertTrue($page->hasDocuments());
        $this->assertSame(10, $page->ultNsu);
        $this->assertSame(100, $page->maxNsu);
        $this->assertCount(1, $page->documents);
        $this->assertSame('resNFe', $page->documents[0]->schemaFamily);
        $this->assertSame(10, $page->documents[0]->nsu);
    }

    public function test_parse_cstat_137_vazio(): void
    {
        $xml = <<<'XML'
<retDistDFeInt xmlns="http://www.portalfiscal.inf.br/nfe" versao="1.01">
  <cStat>137</cStat>
  <xMotivo>Nenhum documento localizado</xMotivo>
  <ultNSU>000000000000050</ultNSU>
  <maxNSU>000000000000050</maxNSU>
</retDistDFeInt>
XML;
        $page = (new DistDfeResponseParser)->parse($xml);
        $this->assertTrue($page->isEmpty());
        $this->assertTrue($page->isEndOfQueue());
        $this->assertFalse($page->isAbuse());
    }

    public function test_parse_cstat_656_abuso(): void
    {
        $xml = <<<'XML'
<retDistDFeInt xmlns="http://www.portalfiscal.inf.br/nfe" versao="1.01">
  <cStat>656</cStat>
  <xMotivo>Rejeicao: Consumo Indevido</xMotivo>
  <ultNSU>000000000000001</ultNSU>
  <maxNSU>000000000000100</maxNSU>
</retDistDFeInt>
XML;
        $page = (new DistDfeResponseParser)->parse($xml);
        $this->assertTrue($page->isAbuse());
    }

    public function test_schema_family_variants(): void
    {
        $p = new DistDfeResponseParser;
        $this->assertSame('procNFe', $p->schemaFamily('procNFe_v4.00.xsd'));
        $this->assertSame('procEventoNFe', $p->schemaFamily('procEventoNFe_v1.00.xsd'));
        $this->assertSame('unknown', $p->schemaFamily('foo.xsd'));
    }
}
