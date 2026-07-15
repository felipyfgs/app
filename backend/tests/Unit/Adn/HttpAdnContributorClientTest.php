<?php

namespace Tests\Unit\Adn;

use App\Domain\Adn\DistributionPageDto;
use App\Enums\AdnDocumentType;
use App\Exceptions\Adn\AdnInvalidResponseException;
use App\Exceptions\Adn\AdnPermanentException;
use App\Exceptions\Adn\AdnRetryableException;
use App\Services\Adn\CurlMtlsTransport;
use App\Services\Adn\HttpAdnContributorClient;
use PHPUnit\Framework\TestCase;

class HttpAdnContributorClientTest extends TestCase
{
    public function test_parse_pagina_distribuicao_json(): void
    {
        $body = file_get_contents(dirname(__DIR__, 2).'/fixtures/adn/distribution_page.json');
        $client = $this->clientWithBody($body);
        $page = $client->distribution(['pfx' => 'x', 'password' => 'y'], '11222333000181', 0);

        $this->assertInstanceOf(DistributionPageDto::class, $page);
        $this->assertSame(HttpAdnContributorClient::STATUS_DOCUMENTS_FOUND, $page->status);
        $this->assertSame(2, $page->ultimoNsu);
        $this->assertSame(2, $page->maxNsu);
        $this->assertTrue($page->hasMore);
        $this->assertCount(2, $page->documents);
        $this->assertSame(AdnDocumentType::Nfse, $page->documents[0]->type);
        $this->assertSame(AdnDocumentType::Event, $page->documents[1]->type);
        $this->assertSame(1, $page->documents[0]->nsu);
        $this->assertSame(2, $page->documents[1]->nsu);
        $this->assertNotSame('', $page->documents[0]->contentBase64);
    }

    public function test_pagina_vazia_nenhum_documento(): void
    {
        $body = file_get_contents(dirname(__DIR__, 2).'/fixtures/adn/distribution_empty.json');
        $client = $this->clientWithBody($body);
        $page = $client->distribution(['pfx' => 'x', 'password' => 'y'], '11222333000181', 0);

        $this->assertSame(HttpAdnContributorClient::STATUS_NONE_FOUND, $page->status);
        $this->assertFalse($page->hasMore);
        $this->assertSame([], $page->documents);
        $this->assertSame(0, $page->ultimoNsu);
    }

    public function test_rejeicao_e_permanente(): void
    {
        $body = file_get_contents(dirname(__DIR__, 2).'/fixtures/adn/distribution_reject.json');
        $client = $this->clientWithBody($body);

        $this->expectException(AdnPermanentException::class);
        $this->expectExceptionMessage('E9999');
        $client->distribution(['pfx' => 'x', 'password' => 'y'], '11222333000181', 0);
    }

    public function test_nsu_duplicado_invalido(): void
    {
        $body = file_get_contents(dirname(__DIR__, 2).'/fixtures/adn/distribution_duplicate_nsu.json');
        $client = $this->clientWithBody($body);

        $this->expectException(AdnInvalidResponseException::class);
        $client->distribution(['pfx' => 'x', 'password' => 'y'], '11222333000181', 0);
    }

    public function test_envelope_xml_legado_rejeitado(): void
    {
        $xml = '<?xml version="1.0"?><retDistDFeInt><cStat>138</cStat></retDistDFeInt>';
        $client = $this->clientWithBody($xml);

        $this->expectException(AdnInvalidResponseException::class);
        $client->distribution(['pfx' => 'x', 'password' => 'y'], '11222333000181', 0);
    }

    public function test_events_json_por_chave(): void
    {
        $body = file_get_contents(dirname(__DIR__, 2).'/fixtures/adn/events_page.json');
        $client = $this->clientWithBody($body);
        $page = $client->events(['pfx' => 'x', 'password' => 'y'], '12345678901234567890123456789012345678901234');

        $this->assertCount(1, $page->events);
        $this->assertSame(AdnDocumentType::Event, $page->events[0]->type);
        $this->assertSame(
            '12345678901234567890123456789012345678901234',
            $page->events[0]->accessKey,
        );
    }

    public function test_events_rejeicao_e_permanente(): void
    {
        $body = file_get_contents(dirname(__DIR__, 2).'/fixtures/adn/distribution_reject.json');
        $client = $this->clientWithBody($body);

        $this->expectException(AdnPermanentException::class);
        $this->expectExceptionMessage('E9999');
        $client->events(['pfx' => 'x', 'password' => 'y'], '12345678901234567890123456789012345678901234');
    }

    public function test_chave_acesso_normalizada_uppercase(): void
    {
        $payload = [
            'StatusProcessamento' => 'DOCUMENTOS_LOCALIZADOS',
            'LoteDFe' => [[
                'NSU' => 1,
                'ChaveAcesso' => 'abc45678901234567890123456789012345678901234',
                'TipoDocumento' => 'NFSE',
                'ArquivoXml' => 'QUJD',
            ]],
            'Alertas' => [],
            'Erros' => [],
        ];
        $client = $this->clientWithBody(json_encode($payload, JSON_THROW_ON_ERROR));
        $page = $client->distribution(['pfx' => 'x', 'password' => 'y'], '11222333000181', 0);

        $this->assertSame(
            'ABC45678901234567890123456789012345678901234',
            $page->documents[0]->accessKey,
        );
    }

    public function test_http_404_com_json_nenhum_documento_e_fim(): void
    {
        $body = file_get_contents(dirname(__DIR__, 2).'/fixtures/adn/distribution_empty.json');
        $client = $this->clientWithBody($body, httpStatus: 404);
        $page = $client->distribution(['pfx' => 'x', 'password' => 'y'], '11222333000181', 105);

        $this->assertSame(HttpAdnContributorClient::STATUS_NONE_FOUND, $page->status);
        $this->assertFalse($page->hasMore);
        $this->assertSame([], $page->documents);
        $this->assertSame(105, $page->ultimoNsu);
    }

    public function test_http_404_json_generico_e_retryable(): void
    {
        $client = $this->clientWithBody('{"message":"not found"}', httpStatus: 404);

        $this->expectException(AdnRetryableException::class);
        $client->distribution(['pfx' => 'x', 'password' => 'y'], '11222333000181', 0);
    }

    private function clientWithBody(string $body, int $httpStatus = 200): HttpAdnContributorClient
    {
        $transport = new class($httpStatus) extends CurlMtlsTransport
        {
            public string $body = '';

            public function __construct(private readonly int $httpStatus = 200)
            {
                parent::__construct(verifyTls: true);
            }

            public function get(string $url, array $certificate): array
            {
                return ['status' => $this->httpStatus, 'body' => $this->body, 'headers' => []];
            }
        };
        $transport->body = $body;

        return new HttpAdnContributorClient($transport, 'https://example.test/contribuintes');
    }
}
