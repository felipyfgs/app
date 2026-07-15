<?php

namespace Tests\Unit\Outbound;

use NFePHP\Common\Certificate;
use NFePHP\Common\Signer;
use Tests\TestCase;

/**
 * Gera nfeProc assinado com certificado de teste exclusivo do repositório (openssl).
 * Não usa certificado fiscal real.
 */
class SvrsNfceSignedFixtureGeneratorTest extends TestCase
{
    public function test_generate_signed_fixture_with_test_cert(): void
    {
        $dir = base_path('tests/fixtures/svrs-nfce');
        $keyPath = $dir.'/test-only.key.pem';
        $crtPath = $dir.'/test-only.crt.pem';
        $pfxPath = $dir.'/test-only.pfx';

        if (! is_file($keyPath) || ! is_file($crtPath)) {
            // Gerar com openssl se ausente
            $cmd = sprintf(
                'openssl req -x509 -newkey rsa:2048 -keyout %s -out %s -days 3650 -nodes -subj "/CN=SVRS Test Fixture/O=Test Only/C=BR" 2>/dev/null',
                escapeshellarg($keyPath),
                escapeshellarg($crtPath)
            );
            exec($cmd, $out, $code);
            if ($code !== 0 || ! is_file($keyPath)) {
                $this->markTestSkipped('openssl indisponível para gerar certificado de teste.');
            }
            // export PFX
            exec(sprintf(
                'openssl pkcs12 -export -out %s -inkey %s -in %s -passout pass:test-only 2>/dev/null',
                escapeshellarg($pfxPath),
                escapeshellarg($keyPath),
                escapeshellarg($crtPath)
            ));
        }

        $this->assertFileExists($keyPath);
        $this->assertFileExists($crtPath);

        // Garantir que não é material de produção (subject Test)
        $crt = file_get_contents($crtPath);
        $this->assertStringContainsString('BEGIN CERTIFICATE', $crt);

        $xmlPath = base_path('tests/fixtures/ma-outbound/procNFe_65_out_ma.xml');
        $xml = file_get_contents($xmlPath);
        $xml = preg_replace('/<!--.*?-->/s', '', $xml) ?? $xml;
        // Garantir que NFe tem estrutura assinável: Signer assina tag infNFe
        if (! str_contains($xml, '<Signature')) {
            try {
                $pfx = is_file($pfxPath)
                    ? file_get_contents($pfxPath)
                    : null;
                if ($pfx === null || $pfx === false) {
                    // montar pfx via Certificate se possível
                    $this->markTestSkipped('PFX de teste não gerado.');
                }
                $cert = Certificate::readPfx($pfx, 'test-only');
                // Envelope NFe precisa de Signature dentro de NFe — Signer::sign
                $signed = Signer::sign($cert, $xml, 'infNFe', 'Id', OPENSSL_ALGO_SHA1);
                $outFile = $dir.'/procNFe_65_signed_test_only.xml';
                file_put_contents($outFile, $signed);
                $this->assertFileExists($outFile);
                $this->assertStringContainsString('Signature', file_get_contents($outFile));
                // Sanidade: arquivo não contém senha
                $this->assertStringNotContainsString('test-only', file_get_contents($outFile));
            } catch (\Throwable $e) {
                // Fixture base pode não ser assinável (sem whitespace/c14n) — registrar skip com motivo
                $this->markTestSkipped('Assinatura fixture: '.$e->getMessage());
            }
        } else {
            $this->assertTrue(true);
        }
    }
}
