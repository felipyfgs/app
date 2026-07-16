<?php

namespace Tests\Unit\Certificates;

use App\Services\Certificates\ContractorPfxValidator;
use RuntimeException;
use Tests\TestCase;

class ContractorPfxValidatorTest extends TestCase
{
    private string $workDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->workDir = sys_get_temp_dir().'/pfx-val-'.uniqid('', true);
        mkdir($this->workDir, 0700, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->workDir)) {
            foreach (glob($this->workDir.'/*') ?: [] as $f) {
                @unlink($f);
            }
            @rmdir($this->workDir);
        }
        parent::tearDown();
    }

    public function test_valida_pfx_rsa_com_cnpj_e_retorna_so_metadados(): void
    {
        [$pfx, $password] = $this->makePfx(cnpj: '11222333000181', daysValid: 60);

        $meta = app(ContractorPfxValidator::class)->validate(
            $pfx,
            $password,
            expectedCnpj: '11222333000181',
            minHorizonDays: 7,
            requireChain: false,
        );

        $this->assertTrue($meta['has_private_key']);
        $this->assertSame('11222333000181', $meta['cnpj']);
        $this->assertSame('RSA', $meta['key_algorithm']);
        $this->assertGreaterThanOrEqual(2048, $meta['key_bits']);
        $this->assertNotEmpty($meta['fingerprint_sha256']);
        $this->assertTrue($meta['purpose_ok']);
        $this->assertTrue($meta['horizon_ok']);
        $this->assertTrue($meta['algorithm_ok']);

        $sanitized = app(ContractorPfxValidator::class)->toSanitizedMetadata($meta);
        $encoded = json_encode($sanitized) ?: '';
        $this->assertStringNotContainsString('BEGIN', $encoded);
        $this->assertStringNotContainsString($password, $encoded);
        $this->assertStringNotContainsString(base64_encode($pfx), $encoded);
        $this->assertArrayHasKey('fingerprint_sha256', $sanitized);
        $this->assertArrayHasKey('cnpj_masked', $sanitized);
    }

    public function test_rejeita_senha_errada(): void
    {
        [$pfx] = $this->makePfx(cnpj: '11222333000181', daysValid: 60);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/senha|abrir/i');
        app(ContractorPfxValidator::class)->validate($pfx, 'wrong-password');
    }

    public function test_rejeita_cnpj_divergente(): void
    {
        [$pfx, $password] = $this->makePfx(cnpj: '11222333000181', daysValid: 60);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/CNPJ.*diverge/i');
        app(ContractorPfxValidator::class)->validate(
            $pfx,
            $password,
            expectedCnpj: '12345678000195',
        );
    }

    public function test_rejeita_horizonte_insuficiente(): void
    {
        [$pfx, $password] = $this->makePfx(cnpj: '11222333000181', daysValid: 3);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Horizonte/i');
        app(ContractorPfxValidator::class)->validate(
            $pfx,
            $password,
            expectedCnpj: '11222333000181',
            minHorizonDays: 30,
        );
    }

    public function test_rejeita_pfx_vazio(): void
    {
        $this->expectException(RuntimeException::class);
        app(ContractorPfxValidator::class)->validate('', 'x');
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function makePfx(string $cnpj, int $daysValid): array
    {
        $password = 'test-pfx-pass-'.bin2hex(random_bytes(4));
        $keyFile = $this->workDir.'/key.pem';
        $certFile = $this->workDir.'/cert.pem';
        $pfxFile = $this->workDir.'/cert.pfx';
        $cfg = $this->workDir.'/openssl.cnf';

        file_put_contents($cfg, <<<CNF
[req]
distinguished_name = dn
x509_extensions = v3_req
prompt = no

[dn]
CN = {$cnpj}
O = Test Contractor {$cnpj}
C = BR

[v3_req]
basicConstraints = CA:FALSE
keyUsage = digitalSignature, keyEncipherment
extendedKeyUsage = clientAuth
subjectAltName = @alt

[alt]
dirName = dir_sect

[dir_sect]
CN = {$cnpj}
CNF);

        $days = max(1, $daysValid);
        $cmd = sprintf(
            'openssl req -x509 -newkey rsa:2048 -keyout %s -out %s -days %d -nodes -config %s 2>/dev/null && openssl pkcs12 -export -out %s -inkey %s -in %s -passout pass:%s 2>/dev/null',
            escapeshellarg($keyFile),
            escapeshellarg($certFile),
            $days,
            escapeshellarg($cfg),
            escapeshellarg($pfxFile),
            escapeshellarg($keyFile),
            escapeshellarg($certFile),
            escapeshellarg($password),
        );

        exec($cmd, $out, $code);
        if ($code !== 0 || ! is_file($pfxFile)) {
            $this->markTestSkipped('openssl indisponível para gerar PFX de teste');
        }

        $pfx = file_get_contents($pfxFile);
        $this->assertNotFalse($pfx);

        return [$pfx, $password];
    }
}
