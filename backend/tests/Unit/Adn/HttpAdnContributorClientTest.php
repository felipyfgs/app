<?php

namespace Tests\Unit\Adn;

use App\Domain\Adn\DistributionPageDto;
use App\Enums\AdnDocumentType;
use App\Services\Adn\CurlMtlsTransport;
use App\Services\Adn\HttpAdnContributorClient;
use PHPUnit\Framework\TestCase;

class HttpAdnContributorClientTest extends TestCase
{
    public function test_parse_pagina_distribuicao(): void
    {
        $xml = file_get_contents(dirname(__DIR__, 2).'/fixtures/adn/distribution_page.xml');
        $transport = new class extends CurlMtlsTransport
        {
            public string $body = '';

            public function __construct()
            {
                parent::__construct(verifyTls: true);
            }

            public function get(string $url, array $certificate): array
            {
                return ['status' => 200, 'body' => $this->body, 'headers' => []];
            }
        };
        $transport->body = $xml;

        $client = new HttpAdnContributorClient($transport, 'https://example.test/contribuintes');
        $page = $client->distribution(['pfx' => 'x', 'password' => 'y'], '11222333000181', 0);

        $this->assertInstanceOf(DistributionPageDto::class, $page);
        $this->assertSame('138', $page->status);
        $this->assertSame(2, $page->ultimoNsu);
        $this->assertSame(10, $page->maxNsu);
        $this->assertTrue($page->hasMore);
        $this->assertCount(2, $page->documents);
        $this->assertSame(AdnDocumentType::Nfse, $page->documents[0]->type);
        $this->assertSame(AdnDocumentType::Event, $page->documents[1]->type);
    }

    public function test_pagina_vazia(): void
    {
        $xml = file_get_contents(dirname(__DIR__, 2).'/fixtures/adn/distribution_empty.xml');
        $transport = new class extends CurlMtlsTransport
        {
            public string $body = '';

            public function __construct()
            {
                parent::__construct(verifyTls: true);
            }

            public function get(string $url, array $certificate): array
            {
                return ['status' => 200, 'body' => $this->body, 'headers' => []];
            }
        };
        $transport->body = $xml;
        $client = new HttpAdnContributorClient($transport, 'https://example.test');
        $page = $client->distribution(['pfx' => 'x', 'password' => 'y'], '11222333000181', 0);
        $this->assertFalse($page->hasMore);
        $this->assertSame([], $page->documents);
    }

    public function test_doczip_com_schema_antes_de_nsu(): void
    {
        $xml = file_get_contents(dirname(__DIR__, 2).'/fixtures/adn/distribution_schema_first.xml');
        $transport = new class extends CurlMtlsTransport
        {
            public string $body = '';

            public function __construct()
            {
                parent::__construct(verifyTls: true);
            }

            public function get(string $url, array $certificate): array
            {
                return ['status' => 200, 'body' => $this->body, 'headers' => []];
            }
        };
        $transport->body = $xml;
        $client = new HttpAdnContributorClient($transport, 'https://example.test');
        $page = $client->distribution(['pfx' => 'x', 'password' => 'y'], '11222333000181', 0);

        $this->assertCount(2, $page->documents);
        $this->assertSame(1, $page->documents[0]->nsu);
        $this->assertSame(2, $page->documents[1]->nsu);
        $this->assertSame(AdnDocumentType::Nfse, $page->documents[0]->type);
        $this->assertSame(AdnDocumentType::Event, $page->documents[1]->type);
    }
}
