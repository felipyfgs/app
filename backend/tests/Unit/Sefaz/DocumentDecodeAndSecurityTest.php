<?php

namespace Tests\Unit\Sefaz;

use App\Services\Adn\CurlMtlsTransport;
use App\Services\Adn\DocumentDecoder;
use App\Services\Sefaz\HttpSefazDistDfeClient;
use ReflectionClass;
use RuntimeException;
use Tests\TestCase;

class DocumentDecodeAndSecurityTest extends TestCase
{
    public function test_decode_base64_gzip_roundtrip(): void
    {
        $xml = '<?xml version="1.0"?><resNFe><chNFe>35240111222333000181550010000000011000000010</chNFe></resNFe>';
        $b64 = base64_encode(gzencode($xml, 9));
        $out = (new DocumentDecoder)->decodeBase64Gzip($b64);
        $this->assertSame($xml, $out['bytes']);
        $this->assertSame(hash('sha256', $xml), $out['sha256']);
    }

    public function test_curl_transport_rejects_tls_off(): void
    {
        $this->expectException(RuntimeException::class);
        new CurlMtlsTransport(verifyTls: false);
    }

    public function test_curl_transport_uses_blob_and_verify_peer(): void
    {
        $this->assertTrue(defined('CURLOPT_SSLCERT_BLOB'), 'CURLOPT_SSLCERT_BLOB obrigatório');
        $source = file_get_contents((new ReflectionClass(CurlMtlsTransport::class))->getFileName());
        $this->assertStringContainsString('CURLOPT_SSL_VERIFYPEER', $source);
        $this->assertStringContainsString('CURLOPT_SSLCERT_BLOB', $source);
        $this->assertStringNotContainsString('tempnam', $source);
        $this->assertStringNotContainsString('.pem', $source);
        // post SOAP reutiliza o mesmo path seguro
        $this->assertStringContainsString('function post', $source);
    }

    public function test_http_sefaz_client_source_has_no_pem_temp(): void
    {
        $source = file_get_contents((new ReflectionClass(HttpSefazDistDfeClient::class))->getFileName());
        $this->assertStringNotContainsString('tempnam', $source);
        $this->assertStringNotContainsString('sys_get_temp_dir', $source);
        $this->assertStringNotContainsString('NFePHP\\', $source);
    }
}
