<?php

namespace Tests\Unit\Sefaz;

use App\Enums\DocumentDirection;
use App\Enums\FiscalRole;
use App\Services\Sefaz\CteXmlProjectionParser;
use App\Services\Sefaz\DistDfeResponseParser;
use PHPUnit\Framework\TestCase;

class CteXmlProjectionParserTest extends TestCase
{
    public function test_parse_proc_cte_tomador_entrada(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<cteProc xmlns="http://www.portalfiscal.inf.br/cte">
  <CTe>
    <infCte Id="CTe35260711222333000181570010000000011234567890">
      <ide>
        <mod>57</mod>
        <serie>1</serie>
        <nCT>42</nCT>
        <dhEmi>2026-07-01T10:00:00-03:00</dhEmi>
      </ide>
      <emit>
        <CNPJ>11222333000181</CNPJ>
        <xNome>Transportadora</xNome>
      </emit>
      <rem>
        <CNPJ>11111111000111</CNPJ>
      </rem>
      <dest>
        <CNPJ>22222222000122</CNPJ>
      </dest>
      <toma4>
        <CNPJ>34194865000158</CNPJ>
        <xNome>Tomador Frete</xNome>
      </toma4>
      <vPrest><vTPrest>250.50</vTPrest></vPrest>
    </infCte>
  </CTe>
  <protCTe><infProt><cStat>100</cStat><chCTe>35260711222333000181570010000000011234567890</chCTe></infProt></protCTe>
</cteProc>
XML;

        $parsed = (new CteXmlProjectionParser)->parse($xml, 'procCTe', '34194865000158');
        $this->assertSame('35260711222333000181570010000000011234567890', $parsed['access_key']);
        $this->assertSame('42', $parsed['number']);
        $this->assertSame('11222333000181', $parsed['issuer_cnpj']);
        $this->assertSame('34194865000158', $parsed['taker_cnpj']);
        $this->assertSame(FiscalRole::Taker, $parsed['fiscal_role']);
        $this->assertSame(DocumentDirection::In, $parsed['direction']);
        $this->assertSame('ACTIVE', $parsed['status']);
        $this->assertFalse($parsed['is_summary']);
        $this->assertSame('250.50', $parsed['total_amount']);
    }

    public function test_parse_proc_cte_emitente_saida(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<cteProc>
  <CTe>
    <infCte Id="CTe35260799888777000166570010000000019999999999">
      <ide><mod>57</mod><serie>1</serie><nCT>1</nCT><dhEmi>2026-07-02T12:00:00-03:00</dhEmi></ide>
      <emit><CNPJ>99888777000166</CNPJ><xNome>Eu Emito</xNome></emit>
      <dest><CNPJ>11111111000111</CNPJ></dest>
    </infCte>
  </CTe>
  <protCTe><infProt><cStat>100</cStat></infProt></protCTe>
</cteProc>
XML;
        $parsed = (new CteXmlProjectionParser)->parse($xml, 'procCTe', '99888777000166');
        $this->assertSame(FiscalRole::Issuer, $parsed['fiscal_role']);
        $this->assertSame(DocumentDirection::Out, $parsed['direction']);
    }

    public function test_schema_family_cte(): void
    {
        $p = new DistDfeResponseParser;
        $this->assertSame('procCTe', $p->schemaFamily('procCTe_v4.00.xsd'));
        $this->assertSame('resCTe', $p->schemaFamily('resCTe_v4.00.xsd'));
        $this->assertSame('procEventoCTe', $p->schemaFamily('procEventoCTe_v4.00.xsd'));
    }
}
