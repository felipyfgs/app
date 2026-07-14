<?php

namespace Tests\Unit\Audit;

use App\Services\Audit\AuditLogger;
use App\Support\CurrentOffice;
use PHPUnit\Framework\TestCase;

class AuditLoggerTest extends TestCase
{
    public function test_redact_remove_senha_pfx_e_pem(): void
    {
        $logger = new AuditLogger(new CurrentOffice);
        $safe = $logger->redact([
            'password' => 'super-secret',
            'pfx' => 'binary',
            'fingerprint' => 'ABC',
            'nested' => ['private_key' => '-----BEGIN PRIVATE KEY-----'],
            'pem_blob' => "-----BEGIN CERTIFICATE-----\nXYZ",
            'ok' => 1,
        ]);

        $this->assertSame('[redacted]', $safe['password']);
        $this->assertSame('[redacted]', $safe['pfx']);
        $this->assertSame('ABC', $safe['fingerprint']);
        $this->assertSame('[redacted]', $safe['nested']['private_key']);
        $this->assertSame('[redacted]', $safe['pem_blob']);
        $this->assertSame(1, $safe['ok']);
    }
}
