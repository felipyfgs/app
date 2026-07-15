<?php

namespace Tests\Unit\Sefaz;

use App\Enums\SignatureVerificationResult;
use App\Services\Sefaz\SpedCommonCteXmlSignatureValidator;
use NFePHP\Common\Certificate;
use NFePHP\Common\Signer;
use Tests\TestCase;

class CteXmlSignatureValidatorTest extends TestCase
{
    public function test_valida_digest_e_assinatura_e_rejeita_conteudo_alterado(): void
    {
        config(['sefaz.cte.require_signature' => true]);
        $fixture = (string) file_get_contents(base_path('tests/fixtures/cte/procCTe_57_toma3_rem.xml'));
        $fixture = preg_replace('/<!--.*?-->/s', '', $fixture) ?? $fixture;
        $signed = $this->signWithEphemeralTestCertificate($fixture);

        $validator = new SpedCommonCteXmlSignatureValidator;
        $this->assertSame(SignatureVerificationResult::Valid, $validator->validate($signed));

        $tampered = preg_replace('/<vTPrest>([^<]+)<\/vTPrest>/', '<vTPrest>999.99</vTPrest>', $signed, 1);
        $this->assertIsString($tampered);
        $this->assertSame(SignatureVerificationResult::Invalid, $validator->validate($tampered));
    }

    public function test_rejeita_assinatura_ausente_quando_gate_esta_ativo(): void
    {
        config(['sefaz.cte.require_signature' => true]);
        $fixture = (string) file_get_contents(base_path('tests/fixtures/cte/procCTe_57_toma3_rem.xml'));

        $this->assertSame(
            SignatureVerificationResult::Invalid,
            (new SpedCommonCteXmlSignatureValidator)->validate($fixture),
        );
    }

    private function signWithEphemeralTestCertificate(string $xml): string
    {
        $dir = sys_get_temp_dir().'/cte-signature-'.bin2hex(random_bytes(8));
        mkdir($dir, 0700, true);
        $key = $dir.'/test.key.pem';
        $crt = $dir.'/test.crt.pem';
        $pfx = $dir.'/test.pfx';

        try {
            exec(sprintf(
                'openssl req -x509 -newkey rsa:2048 -keyout %s -out %s -days 2 -nodes -subj %s 2>/dev/null',
                escapeshellarg($key),
                escapeshellarg($crt),
                escapeshellarg('/CN=CTe Fixture/O=Test Only/C=BR'),
            ), $output, $code);
            if ($code !== 0) {
                $this->markTestSkipped('OpenSSL indisponível para a fixture efêmera.');
            }

            exec(sprintf(
                'openssl pkcs12 -export -out %s -inkey %s -in %s -passout pass:test-only 2>/dev/null',
                escapeshellarg($pfx),
                escapeshellarg($key),
                escapeshellarg($crt),
            ), $output, $code);
            if ($code !== 0) {
                $this->markTestSkipped('OpenSSL não gerou o PFX efêmero.');
            }

            $certificate = Certificate::readPfx((string) file_get_contents($pfx), 'test-only');

            return Signer::sign($certificate, $xml, 'infCte', 'Id', OPENSSL_ALGO_SHA1, rootname: 'CTe');
        } finally {
            foreach ([$pfx, $crt, $key] as $path) {
                if (is_file($path)) {
                    unlink($path);
                }
            }
            if (is_dir($dir)) {
                rmdir($dir);
            }
        }
    }
}
