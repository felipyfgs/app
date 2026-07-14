<?php

namespace Tests\Unit\Adn;

use App\Services\Adn\DocumentDecoder;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class DocumentDecoderTest extends TestCase
{
    public function test_decodifica_gzip_base64(): void
    {
        $xml = '<?xml version="1.0"?><root>ok</root>';
        $encoded = base64_encode(gzencode($xml));
        $result = (new DocumentDecoder)->decodeBase64Gzip($encoded);
        $this->assertSame($xml, $result['bytes']);
        $this->assertSame(hash('sha256', $xml), $result['sha256']);
    }

    public function test_base64_invalido(): void
    {
        $this->expectException(RuntimeException::class);
        (new DocumentDecoder)->decodeBase64Gzip('@@@');
    }

    public function test_gzip_invalido(): void
    {
        $this->expectException(RuntimeException::class);
        (new DocumentDecoder)->decodeBase64Gzip(base64_encode('not-gzip'));
    }
}
