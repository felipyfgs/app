<?php

namespace Tests\Unit\Serpro;

use App\Services\Serpro\SerproHttpTransport;
use PHPUnit\Framework\TestCase;

class SerproHttpTransportSanitizeTest extends TestCase
{
    public function test_erros_sanitizam_bearer_e_blobs(): void
    {
        $t = new SerproHttpTransport;
        $msg = $t->sanitizeTransportError(
            'Authorization Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.aaa.bbb password=supersecret ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789+/=='
        );

        // LogSanitizer omite a mensagem inteira quando há material sensível residual
        // (password=, blobs base64); Bearer já vira "Bearer [redacted]" antes do scrub.
        $this->assertStringNotContainsString('eyJhbGci', $msg);
        $this->assertStringNotContainsString('supersecret', $msg);
        $this->assertTrue(
            str_contains($msg, '[redacted]')
            || str_contains($msg, 'conteúdo sensível omitido')
            || str_contains($msg, 'sanitizada'),
            "Esperado marcador de redação ou omissão total; obtido: {$msg}"
        );
    }
}
