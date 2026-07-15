<?php

namespace Tests\Unit\Outbound;

use App\Services\Outbound\ProtocolQueryResponseParser;
use PHPUnit\Framework\TestCase;

class ProtocolQueryResponseParserTest extends TestCase
{
    private ProtocolQueryResponseParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new ProtocolQueryResponseParser;
    }

    public function test_562_com_chave_no_xmotivo(): void
    {
        $xml = file_get_contents(dirname(__DIR__, 2).'/fixtures/ma-outbound/consulta_562_com_chave.xml');
        $result = $this->parser->parse((string) $xml, '21260712345678000190550010000000011234567890');

        $this->assertSame('562', $result->cStat);
        $this->assertTrue($result->is562WithKey());
        $this->assertSame(44, strlen((string) $result->returnedAccessKey));
    }

    public function test_562_sem_chave_nao_forca_bruta(): void
    {
        $xml = file_get_contents(dirname(__DIR__, 2).'/fixtures/ma-outbound/consulta_562_sem_chave.xml');
        $result = $this->parser->parse((string) $xml, '21260712345678000190550010000000011234567890');

        $this->assertTrue($result->is562WithoutKey());
        $this->assertTrue($result->isLimitedWithoutKey());
    }

    public function test_217_nao_localizado(): void
    {
        $xml = file_get_contents(dirname(__DIR__, 2).'/fixtures/ma-outbound/consulta_217.xml');
        $result = $this->parser->parse((string) $xml, '21260712345678000190550010000000991234567890');

        $this->assertTrue($result->isNotFound());
    }

    public function test_656_consumo_indevido(): void
    {
        $xml = file_get_contents(dirname(__DIR__, 2).'/fixtures/ma-outbound/consulta_656.xml');
        $result = $this->parser->parse((string) $xml, '21260712345678000190550010000000011234567890');

        $this->assertTrue($result->isUnauthorizedConsumption());
    }
}
