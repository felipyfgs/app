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
}
