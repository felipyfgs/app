<?php

namespace Tests\Unit\Import;

use App\Services\Import\ImportXmlClassifier;
use App\Services\Import\SecureXmlLoader;
use App\Services\Import\SecureZipReader;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

class SecureZipAndXmlTest extends TestCase
{
    public function test_secure_xml_rejects_doctype(): void
    {
        $this->expectException(RuntimeException::class);
        (new SecureXmlLoader)->load('<?xml version="1.0"?><!DOCTYPE foo [<!ENTITY xxe SYSTEM "file:///etc/passwd">]><root>&xxe;</root>');
    }

    public function test_secure_xml_loads_simple(): void
    {
        $doc = (new SecureXmlLoader)->load('<?xml version="1.0"?><root><child>a</child></root>');
        $this->assertSame('root', $doc->documentElement?->tagName);
    }

    public function test_classifier_rejects_pdf_and_html(): void
    {
        $c = new ImportXmlClassifier;
        $this->assertSame('unsupported', $c->classify('%PDF-1.4...')['kind']);
        $this->assertSame('unsupported', $c->classify('<html><body>x</body></html>')['kind']);
    }

    public function test_classifier_detects_bare_nfe(): void
    {
        $xml = '<?xml version="1.0"?><NFe xmlns="http://www.portalfiscal.inf.br/nfe"><infNFe Id="NFe'.str_repeat('1', 44).'"/><mod>55</mod></NFe>';
        $r = (new ImportXmlClassifier)->classify($xml);
        $this->assertSame('NFe_bare', $r['kind']);
    }

    public function test_zip_rejects_nested_zip_entry(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'zt');
        $zip = new ZipArchive;
        $zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('inner.zip', "PK\x03\x04fake");
        $zip->close();
        $bytes = (string) file_get_contents($tmp);
        @unlink($tmp);

        $entries = (new SecureZipReader)->extractXmlEntries($bytes, 't.zip');
        $this->assertNotEmpty($entries);
        $this->assertNotSame('ok', $entries[0]['status']);
    }

    public function test_zip_extracts_xml(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'zt');
        $zip = new ZipArchive;
        $zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('nota.xml', '<?xml version="1.0"?><nfeProc><mod>65</mod></nfeProc>');
        $zip->close();
        $bytes = (string) file_get_contents($tmp);
        @unlink($tmp);

        $entries = (new SecureZipReader)->extractXmlEntries($bytes, 'ok.zip');
        $ok = array_values(array_filter($entries, fn ($e) => $e['status'] === 'ok'));
        $this->assertCount(1, $ok);
        $this->assertStringContainsString('nfeProc', $ok[0]['bytes']);
    }

    public function test_zip_rejects_traversal(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'zt');
        $zip = new ZipArchive;
        $zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('../evil.xml', '<?xml version="1.0"?><nfeProc/>');
        $zip->close();
        $bytes = (string) file_get_contents($tmp);
        @unlink($tmp);

        $entries = (new SecureZipReader)->extractXmlEntries($bytes, 'bad.zip');
        $this->assertTrue(collect($entries)->every(fn ($e) => $e['status'] !== 'ok'));
    }
}
