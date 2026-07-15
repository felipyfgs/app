<?php

namespace Tests\Unit\Outbound;

use App\Enums\SvrsNfceTransportOutcome;
use App\Services\Outbound\SvrsNfceConfig;
use App\Services\Outbound\SvrsNfceDownloadResponseParser;
use Tests\TestCase;

class SvrsNfceDownloadResponseParserTest extends TestCase
{
    private SvrsNfceDownloadResponseParser $parser;

    private string $fixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new SvrsNfceDownloadResponseParser(new SvrsNfceConfig);
        $this->fixtures = base_path('tests/fixtures/svrs-nfce');
    }

    public function test_get_form_ok(): void
    {
        $html = file_get_contents($this->fixtures.'/get_form_ok.html');
        $r = $this->parser->parseFormPage($html);
        $this->assertSame(SvrsNfceTransportOutcome::FormOk, $r->outcome);
    }

    public function test_post_success_preserves_bytes(): void
    {
        $html = file_get_contents($this->fixtures.'/post_success.html');
        $r = $this->parser->parseDownloadPage($html);
        $this->assertTrue($r->isSuccess());
        $this->assertNotNull($r->xmlBytes);
        $this->assertStringContainsString('nfeProc', $r->xmlBytes);
        $this->assertStringContainsString('21260712345678000190650010000000011234567892', $r->xmlBytes);

        // Sem normalização: re-parse deve devolver mesmos bytes
        $r2 = $this->parser->parseDownloadPage($html);
        $this->assertSame($r->xmlBytes, $r2->xmlBytes);
        $this->assertSame(hash('sha256', $r->xmlBytes), hash('sha256', $r2->xmlBytes));
    }

    public function test_post_success_with_object_property_blob_contract(): void
    {
        $html = file_get_contents($this->fixtures.'/post_success_object_property.html');
        $r = $this->parser->parseDownloadPage($html);

        $this->assertTrue($r->isSuccess());
        $this->assertNotNull($r->xmlBytes);
        $this->assertStringStartsWith('<nfeProc', $r->xmlBytes);
        $this->assertStringContainsString('<nNF>1</nNF>', $r->xmlBytes);
        $this->assertStringContainsString('21260712345678000190650010000000011234567892', $r->xmlBytes);
    }

    public function test_post_not_available(): void
    {
        $html = file_get_contents($this->fixtures.'/post_not_available.html');
        $r = $this->parser->parseDownloadPage($html);
        $this->assertSame(SvrsNfceTransportOutcome::RemoteNotFound, $r->outcome);
    }

    public function test_post_auth_denied(): void
    {
        $html = file_get_contents($this->fixtures.'/post_auth_denied.html');
        $r = $this->parser->parseDownloadPage($html);
        $this->assertSame(SvrsNfceTransportOutcome::AuthForbidden, $r->outcome);
    }

    public function test_post_contract_changed(): void
    {
        $html = file_get_contents($this->fixtures.'/post_contract_changed.html');
        $r = $this->parser->parseDownloadPage($html);
        $this->assertSame(SvrsNfceTransportOutcome::ResponseContractChanged, $r->outcome);
    }

    public function test_malicious_concat_rejected(): void
    {
        $html = file_get_contents($this->fixtures.'/post_malicious_concat.html');
        $r = $this->parser->parseDownloadPage($html);
        $this->assertSame(SvrsNfceTransportOutcome::ResponseContractChanged, $r->outcome);
    }

    public function test_malicious_template_rejected(): void
    {
        $html = file_get_contents($this->fixtures.'/post_malicious_template.html');
        $r = $this->parser->parseDownloadPage($html);
        $this->assertSame(SvrsNfceTransportOutcome::ResponseContractChanged, $r->outcome);
    }

    public function test_malicious_multiple_rejected(): void
    {
        $html = file_get_contents($this->fixtures.'/post_malicious_multiple.html');
        $r = $this->parser->parseDownloadPage($html);
        $this->assertSame(SvrsNfceTransportOutcome::ResponseContractChanged, $r->outcome);
    }

    public function test_malicious_escape_rejected(): void
    {
        $html = file_get_contents($this->fixtures.'/post_malicious_escape.html');
        $r = $this->parser->parseDownloadPage($html);
        $this->assertSame(SvrsNfceTransportOutcome::ResponseContractChanged, $r->outcome);
    }

    public function test_js_decoder_escapes(): void
    {
        $this->assertSame("a\nb", $this->parser->decodeJsStringLiteral('a\\nb'));
        $this->assertSame('a"b', $this->parser->decodeJsStringLiteral('a\\"b'));
        $this->assertSame("a'b", $this->parser->decodeJsStringLiteral("a\\'b"));
        $this->assertSame('A', $this->parser->decodeJsStringLiteral('\\u0041'));
        $this->assertNull($this->parser->decodeJsStringLiteral('\\q'));
        $this->assertNull($this->parser->decodeJsStringLiteral('\\u{41}'));
        $this->assertNull($this->parser->decodeJsStringLiteral('trail\\'));
    }

    public function test_html_size_limit(): void
    {
        config(['sefaz.svrs_nfce_xml.max_html_bytes' => 50]);
        $parser = new SvrsNfceDownloadResponseParser(new SvrsNfceConfig);
        $r = $parser->parseDownloadPage(str_repeat('x', 100));
        $this->assertSame(SvrsNfceTransportOutcome::PayloadTooLarge, $r->outcome);
    }
}
