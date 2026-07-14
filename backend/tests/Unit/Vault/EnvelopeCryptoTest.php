<?php

namespace Tests\Unit\Vault;

use App\Services\Vault\EnvelopeCrypto;
use App\Services\Vault\FilesystemSecureObjectStore;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class EnvelopeCryptoTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = sys_get_temp_dir().'/vault-test-'.bin2hex(random_bytes(4));
        mkdir($this->root, 0700, true);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->root.'/*/*') ?: []);
        array_map('rmdir', glob($this->root.'/*') ?: []);
        @rmdir($this->root);
        parent::tearDown();
    }

    public function test_roundtrip_e_aad(): void
    {
        $key = random_bytes(32);
        $crypto = new EnvelopeCrypto($key, 1);
        $store = new FilesystemSecureObjectStore($crypto, $this->root);

        $id = $store->put('secret-payload', ['office_id' => 1, 'client_id' => 2]);
        $this->assertTrue($store->exists($id));
        $this->assertSame('secret-payload', $store->get($id, ['office_id' => 1, 'client_id' => 2]));
    }

    public function test_adulteracao_falha(): void
    {
        $key = random_bytes(32);
        $crypto = new EnvelopeCrypto($key, 1);
        $store = new FilesystemSecureObjectStore($crypto, $this->root);
        $id = $store->put('secret', ['k' => 'v']);

        $path = $this->root.'/'.strtolower(substr($id, 0, 2)).'/'.$id.'.json';
        $json = json_decode(file_get_contents($path), true);
        $json['ciphertext'] = base64_encode(random_bytes(64));
        file_put_contents($path, json_encode($json));

        $this->expectException(RuntimeException::class);
        $store->get($id, ['k' => 'v']);
    }

    public function test_metadata_divergente_falha(): void
    {
        $key = random_bytes(32);
        $crypto = new EnvelopeCrypto($key, 1);
        $store = new FilesystemSecureObjectStore($crypto, $this->root);
        $id = $store->put('secret', ['office_id' => 1]);

        $this->expectException(RuntimeException::class);
        $store->get($id, ['office_id' => 2]);
    }

    public function test_arquivo_nao_contem_plaintext(): void
    {
        $key = random_bytes(32);
        $crypto = new EnvelopeCrypto($key, 1);
        $store = new FilesystemSecureObjectStore($crypto, $this->root);
        $id = $store->put('PFX-PLAINTEXT-MARKER', ['a' => 1]);
        $path = $this->root.'/'.strtolower(substr($id, 0, 2)).'/'.$id.'.json';
        $raw = file_get_contents($path);
        $this->assertStringNotContainsString('PFX-PLAINTEXT-MARKER', $raw);
    }
}
