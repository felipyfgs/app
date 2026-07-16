<?php

namespace Tests\Unit\Backup;

use App\Services\Backup\BackupPackageCrypto;
use RuntimeException;
use Tests\TestCase;

class BackupPackageCryptoTest extends TestCase
{
    public function test_seal_open_roundtrip(): void
    {
        $key = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES);
        $crypto = new BackupPackageCrypto($key);
        $plain = 'postgres+vault+private-bundle-'.bin2hex(random_bytes(8));
        $sealed = $crypto->seal($plain);

        $this->assertTrue(BackupPackageCrypto::isSealedPackage($sealed));
        $this->assertSame($plain, $crypto->open($sealed));
    }

    public function test_wrong_key_fails_closed(): void
    {
        $crypto = new BackupPackageCrypto(random_bytes(32));
        $sealed = $crypto->seal('secret-bundle');
        $other = new BackupPackageCrypto(random_bytes(32));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/chave incorreta|adulteração/i');
        $other->open($sealed);
    }

    public function test_tamper_fails(): void
    {
        $crypto = new BackupPackageCrypto(random_bytes(32));
        $sealed = $crypto->seal('payload');
        $sealed[strlen($sealed) - 1] = $sealed[strlen($sealed) - 1] === "\x00" ? "\x01" : "\x00";

        $this->expectException(RuntimeException::class);
        $crypto->open($sealed);
    }
}
