<?php

namespace Tests\Unit\Sefaz;

use App\Enums\DocumentDirection;
use App\Enums\FiscalRole;
use App\Services\Sefaz\CteXmlProjectionParser;
use App\Services\Sefaz\DistDfeResponseParser;
use PHPUnit\Framework\TestCase;

class CteXmlProjectionParserTest extends TestCase
{
    private CteXmlProjectionParser $parser;

    private string $fixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new CteXmlProjectionParser;
        $this->fixtures = dirname(__DIR__, 2).'/fixtures/cte';
    }

    public function test_parse_proc_cte_tomador_entrada(): void
    {
        $xml = file_get_contents($this->fixtures.'/procCTe_57_roles_all.xml');
        $this->assertNotFalse($xml);

        $parsed = $this->parser->parse($xml, 'procCTe', '34194865000158');
        $this->assertSame('35260711222333000181570010000000421234567890', $parsed['access_key']);
        $this->assertSame('42', $parsed['number']);
        $this->assertSame('11222333000181', $parsed['issuer_cnpj']);
        $this->assertSame('34194865000158', $parsed['taker_cnpj']);
        $this->assertSame('34194865000158', $parsed['effective_taker_cnpj']);
        $this->assertSame(FiscalRole::Taker, $parsed['fiscal_role']);
        $this->assertSame(DocumentDirection::In, $parsed['direction']);
        $this->assertSame('ACTIVE', $parsed['status']);
        $this->assertFalse($parsed['is_summary']);
        $this->assertSame('250.50', $parsed['total_amount']);
        $this->assertContains(FiscalRole::Taker, $parsed['matched_roles']);
    }

    public function test_cinco_papeis_e_autxml_extraidos(): void
    {
        $xml = file_get_contents($this->fixtures.'/procCTe_57_roles_all.xml');
        $this->assertNotFalse($xml);

        $parsed = $this->parser->parse($xml, 'procCTe', null);
        $this->assertSame('11111111000111', $parsed['sender_cnpj']);
        $this->assertSame('22222222000122', $parsed['recipient_cnpj']);
        $this->assertSame('33333333000133', $parsed['expeditor_cnpj']);
        $this->assertSame('44444444000144', $parsed['receiver_cnpj']);
        $this->assertSame(['55666777000155'], $parsed['autxml_cnpjs']);
        $this->assertSame([], $parsed['matched_roles']);
        $this->assertNull($parsed['fiscal_role']);
        $this->assertSame(DocumentDirection::Unknown, $parsed['direction']);
    }

    public function test_papel_remetente_sem_fallback_taker(): void
    {
        $xml = file_get_contents($this->fixtures.'/procCTe_57_roles_all.xml');
        $this->assertNotFalse($xml);

        $parsed = $this->parser->parse($xml, 'procCTe', '11111111000111');
        $this->assertSame([FiscalRole::Sender], $parsed['matched_roles']);
        $this->assertSame(FiscalRole::Sender, $parsed['fiscal_role']);
        $this->assertSame(DocumentDirection::In, $parsed['direction']);
    }

    public function test_papel_expedidor_e_recebedor(): void
    {
        $xml = file_get_contents($this->fixtures.'/procCTe_57_roles_all.xml');
        $this->assertNotFalse($xml);

        $exp = $this->parser->parse($xml, 'procCTe', '33333333000133');
        $this->assertSame([FiscalRole::Expeditor], $exp['matched_roles']);

        $rec = $this->parser->parse($xml, 'procCTe', '44444444000144');
        $this->assertSame([FiscalRole::Receiver], $rec['matched_roles']);
    }

    public function test_toma3_resolve_remetente_como_tomador(): void
    {
        $xml = file_get_contents($this->fixtures.'/procCTe_57_toma3_rem.xml');
        $this->assertNotFalse($xml);

        $parsed = $this->parser->parse($xml, 'procCTe', '11111111000111');
        $this->assertContains(FiscalRole::Sender, $parsed['matched_roles']);
        $this->assertContains(FiscalRole::Taker, $parsed['matched_roles']);
        $this->assertSame('11111111000111', $parsed['effective_taker_cnpj']);
        $this->assertCount(2, $parsed['matched_roles']);
    }

    public function test_emitente_retorna_issuer_sem_assumir_distribuicao(): void
    {
        // Parser identifica o papel; o page processor do canal cliente decide a quarentena.
        $xml = file_get_contents($this->fixtures.'/procCTe_57_issuer_only.xml');
        $this->assertNotFalse($xml);

        $parsed = $this->parser->parse($xml, 'procCTe', '99888777000166');
        $this->assertSame([FiscalRole::Issuer], $parsed['matched_roles']);
        $this->assertSame(FiscalRole::Issuer, $parsed['fiscal_role']);
        $this->assertSame(DocumentDirection::Out, $parsed['direction']);
    }

    public function test_sem_correspondencia_nao_inventa_taker(): void
    {
        $xml = file_get_contents($this->fixtures.'/procCTe_57_roles_all.xml');
        $this->assertNotFalse($xml);

        $parsed = $this->parser->parse($xml, 'procCTe', '00000000000000');
        $this->assertSame([], $parsed['matched_roles']);
        $this->assertNull($parsed['fiscal_role']);
        $this->assertSame(DocumentDirection::Unknown, $parsed['direction']);
    }

    public function test_redacao_oficial_44_noves(): void
    {
        $xml = file_get_contents($this->fixtures.'/procCTe_57_autxml_redacted.xml');
        $this->assertNotFalse($xml);

        $parsed = $this->parser->parse($xml, 'procCTe', '55666777000155');
        $this->assertTrue($parsed['has_official_redaction']);
        $this->assertContains(
            '99999999999999999999999999999999999999999999',
            $parsed['related_access_keys']
        );
        // autXML não entra em matched_roles de cliente
        $this->assertSame([], $parsed['matched_roles']);
    }

    public function test_autxml_original_sem_redacao(): void
    {
        $xml = file_get_contents($this->fixtures.'/procCTe_57_autxml_original.xml');
        $this->assertNotFalse($xml);

        $parsed = $this->parser->parse($xml, 'procCTe', null);
        $this->assertFalse($parsed['has_official_redaction']);
        $this->assertSame(['55666777000155'], $parsed['autxml_cnpjs']);
    }

    public function test_cnpj_alfanumerico(): void
    {
        $xml = file_get_contents($this->fixtures.'/procCTe_57_alphanumeric_cnpj.xml');
        $this->assertNotFalse($xml);

        $parsed = $this->parser->parse($xml, 'procCTe', 'CD98765430001E');
        $this->assertSame('AB12345670001C', $parsed['issuer_cnpj']);
        $this->assertSame('CD98765430001E', $parsed['effective_taker_cnpj']);
        $this->assertSame([FiscalRole::Taker], $parsed['matched_roles']);
    }

    public function test_evento_cancelamento(): void
    {
        $xml = file_get_contents($this->fixtures.'/procEventoCTe_cancel.xml');
        $this->assertNotFalse($xml);

        $parsed = $this->parser->parse($xml, 'procEventoCTe');
        $this->assertTrue($parsed['is_event']);
        $this->assertSame('110111', $parsed['event_type']);
        $this->assertSame(1, $parsed['event_sequence']);
        $this->assertSame('CANCELLED', $parsed['status']);
        $this->assertSame('35260711222333000181570010000000421234567890', $parsed['access_key']);
    }

    public function test_schema_family_cte(): void
    {
        $p = new DistDfeResponseParser;
        $this->assertSame('procCTe', $p->schemaFamily('procCTe_v4.00.xsd'));
        $this->assertSame('resCTe', $p->schemaFamily('resCTe_v4.00.xsd'));
        $this->assertSame('procEventoCTe', $p->schemaFamily('procEventoCTe_v4.00.xsd'));
    }
}
