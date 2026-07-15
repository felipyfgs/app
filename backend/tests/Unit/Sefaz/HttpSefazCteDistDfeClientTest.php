<?php

namespace Tests\Unit\Sefaz;

use App\Domain\Sefaz\DistDfePageDto;
use App\Exceptions\Adn\AdnPermanentException;
use App\Services\Adn\CurlMtlsTransport;
use App\Services\Sefaz\DistDfeResponseParser;
use App\Services\Sefaz\HttpSefazCteDistDfeClient;
use Mockery;
use Tests\TestCase;

class HttpSefazCteDistDfeClientTest extends TestCase
{
    private string $soap137;

    protected function setUp(): void
    {
        parent::setUp();
        $this->soap137 = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<soap12:Envelope xmlns:soap12="http://www.w3.org/2003/05/soap-envelope">
  <soap12:Body>
    <cteDistDFeInteresseResponse xmlns="http://www.portalfiscal.inf.br/cte/wsdl/CTeDistribuicaoDFe">
      <cteDistDFeInteresseResult>
        <retDistDFeInt xmlns="http://www.portalfiscal.inf.br/cte" versao="1.00">
          <tpAmb>1</tpAmb>
          <cStat>137</cStat>
          <xMotivo>Nenhum documento localizado</xMotivo>
          <ultNSU>000000000000000</ultNSU>
          <maxNSU>000000000000000</maxNSU>
        </retDistDFeInt>
      </cteDistDFeInteresseResult>
    </cteDistDFeInteresseResponse>
  </soap12:Body>
</soap12:Envelope>
XML;
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_dist_by_last_nsu_envia_dist_nsu_com_ult_nsu_15_posicoes(): void
    {
        $transport = Mockery::mock(CurlMtlsTransport::class);
        $captured = null;
        $transport->shouldReceive('post')
            ->once()
            ->andReturnUsing(function ($url, $cert, $body) use (&$captured) {
                $captured = $body;

                return ['status' => 200, 'body' => $this->soap137];
            });

        $client = new HttpSefazCteDistDfeClient($transport, new DistDfeResponseParser);
        $result = $client->distByLastNsu(
            ['pfx' => 'blob', 'password' => 'x'],
            '11222333000181',
            42,
            '35',
        );

        $this->assertInstanceOf(DistDfePageDto::class, $result);
        $this->assertSame('137', $result->cStat);
        $this->assertStringContainsString('<distNSU><ultNSU>000000000000042</ultNSU></distNSU>', $captured);
        $this->assertStringContainsString('<CNPJ>11222333000181</CNPJ>', $captured);
        $this->assertStringNotContainsString('consNSU', $captured);
        $this->assertStringNotContainsString('consChCTe', $captured);
    }

    public function test_dist_by_nsu_alias_compatibilidade(): void
    {
        $transport = Mockery::mock(CurlMtlsTransport::class);
        $transport->shouldReceive('post')->once()
            ->andReturn(['status' => 200, 'body' => $this->soap137]);

        $client = new HttpSefazCteDistDfeClient($transport, new DistDfeResponseParser);
        $result = $client->distByNsu(['pfx' => 'b', 'password' => 'p'], '11222333000181', 0, '35');
        $this->assertSame('137', $result->cStat);
    }

    public function test_find_by_nsu_envia_cons_nsu(): void
    {
        $transport = Mockery::mock(CurlMtlsTransport::class);
        $captured = null;
        $transport->shouldReceive('post')
            ->once()
            ->andReturnUsing(function ($url, $cert, $body) use (&$captured) {
                $captured = $body;

                return ['status' => 200, 'body' => $this->soap137];
            });

        $client = new HttpSefazCteDistDfeClient($transport, new DistDfeResponseParser);
        $client->findByNsu(['pfx' => 'b', 'password' => 'p'], '11222333000181', 100, '35');

        $this->assertStringContainsString('<consNSU><NSU>000000000000100</NSU></consNSU>', $captured);
        $this->assertStringNotContainsString('distNSU', $captured);
    }

    public function test_find_by_nsu_rejeita_nsu_zero(): void
    {
        $client = new HttpSefazCteDistDfeClient(
            Mockery::mock(CurlMtlsTransport::class),
            new DistDfeResponseParser,
        );

        $this->expectException(AdnPermanentException::class);
        $client->findByNsu(['pfx' => 'b', 'password' => 'p'], '11222333000181', 0, '35');
    }

    public function test_cnpj_invalido(): void
    {
        $client = new HttpSefazCteDistDfeClient(
            Mockery::mock(CurlMtlsTransport::class),
            new DistDfeResponseParser,
        );

        $this->expectException(AdnPermanentException::class);
        $client->distByLastNsu(['pfx' => 'b', 'password' => 'p'], '123', 0, '35');
    }

    public function test_cnpj_alfanumerico_aceito(): void
    {
        $transport = Mockery::mock(CurlMtlsTransport::class);
        $captured = null;
        $transport->shouldReceive('post')->once()->andReturnUsing(function ($u, $c, $body) use (&$captured) {
            $captured = $body;

            return ['status' => 200, 'body' => $this->soap137];
        });

        $client = new HttpSefazCteDistDfeClient($transport, new DistDfeResponseParser);
        $client->distByLastNsu(['pfx' => 'b', 'password' => 'p'], 'ab12345670001c', 0, '35');
        $this->assertStringContainsString('<CNPJ>AB12345670001C</CNPJ>', $captured);
    }
}
