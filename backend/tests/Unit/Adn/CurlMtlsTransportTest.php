<?php

namespace Tests\Unit\Adn;

use App\Services\Adn\CurlMtlsTransport;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;

class CurlMtlsTransportTest extends TestCase
{
    public function test_nao_permite_desativar_verificacao_tls(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Verificação TLS');
        new CurlMtlsTransport(verifyTls: false);
    }

    public function test_opcoes_forcam_tls12_e_blob_sem_pem_temporario(): void
    {
        $transport = new CurlMtlsTransport(verifyTls: true);
        $ref = new ReflectionClass($transport);
        $this->assertTrue($ref->hasMethod('get'));

        // Garante constantes de segurança disponíveis na plataforma alvo
        $this->assertTrue(defined('CURL_SSLVERSION_TLSv1_2'));
        $this->assertTrue(
            defined('CURLOPT_SSLCERT_BLOB'),
            'CURLOPT_SSLCERT_BLOB deve existir na imagem de produção; PEM em disco é proibido.'
        );

        $source = file_get_contents((new ReflectionClass(CurlMtlsTransport::class))->getFileName());
        $this->assertStringContainsString('CURLOPT_SSL_VERIFYPEER', $source);
        $this->assertStringContainsString('CURLOPT_SSL_VERIFYHOST', $source);
        $this->assertStringContainsString('CURLOPT_SSLCERT_BLOB', $source);
        $this->assertStringNotContainsString('tempnam', $source);
        $this->assertStringNotContainsString('sys_get_temp_dir', $source);
        $this->assertStringNotContainsString('.pem', $source);
    }
}
