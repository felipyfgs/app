<?php

namespace Tests\Unit\Support;

use App\Support\LogSanitizer;
use PHPUnit\Framework\TestCase;

class LogSanitizerTest extends TestCase
{
    public function test_redact_chaves_sensiveis_e_preserva_metadados_publicos(): void
    {
        $safe = LogSanitizer::redact([
            'password' => 'x',
            'termo_xml' => '<Termo/>',
            'termo_sha256' => 'abc',
            'has_termo' => true,
            'nested' => ['access_token' => 'tok'],
            'ok' => 1,
        ]);

        $this->assertSame('[redacted]', $safe['password']);
        $this->assertSame('[redacted]', $safe['termo_xml']);
        $this->assertSame('abc', $safe['termo_sha256']);
        $this->assertTrue($safe['has_termo']);
        $this->assertSame('[redacted]', $safe['nested']['access_token']);
        $this->assertSame(1, $safe['ok']);
    }

    public function test_metric_labels_rejeitam_cnpj_e_chaves_fora_allowlist(): void
    {
        $labels = LogSanitizer::metricLabels([
            'channel' => 'serpro_http',
            'result' => 'SUCCESS',
            'cnpj' => '11222333000181',
            'access_key' => '21260712345678000190650010000000011234567892',
            'office_name' => 'Escritorio X',
            'http_class' => '2xx',
        ]);

        $this->assertSame('serpro_http', $labels['channel']);
        $this->assertSame('SUCCESS', $labels['result']);
        $this->assertSame('2xx', $labels['http_class']);
        $this->assertArrayNotHasKey('cnpj', $labels);
        $this->assertArrayNotHasKey('access_key', $labels);
        $this->assertArrayNotHasKey('office_name', $labels);
    }

    public function test_looks_like_fiscal_identifier(): void
    {
        $this->assertTrue(LogSanitizer::looksLikeFiscalIdentifier('11222333000181'));
        $this->assertTrue(LogSanitizer::looksLikeFiscalIdentifier('21260712345678000190650010000000011234567892'));
        $this->assertFalse(LogSanitizer::looksLikeFiscalIdentifier('SUCCESS'));
        $this->assertFalse(LogSanitizer::looksLikeFiscalIdentifier('2xx'));
    }

    public function test_scrub_remove_xml_base64_pfx_e_cabecalhos_sensiveis(): void
    {
        $this->assertStringContainsString('omitido', LogSanitizer::scrubString('<cteProc versao="4.00"><CTe/></cteProc>'));
        $this->assertStringNotContainsString(str_repeat('A', 100), LogSanitizer::scrubString('docZip='.str_repeat('A', 100)));
        $this->assertStringContainsString('[redacted]', LogSanitizer::scrubString('Authorization: Bearer-secreto'));
        $this->assertStringContainsString('omitido', LogSanitizer::scrubString('arquivo segredo.p12'));
    }
}
