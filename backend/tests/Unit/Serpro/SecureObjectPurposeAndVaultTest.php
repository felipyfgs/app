<?php

namespace Tests\Unit\Serpro;

use App\Contracts\SecureObjectStore;
use App\Enums\SecureObjectPurpose;
use App\Services\Vault\EnvelopeCrypto;
use App\Services\Vault\FilesystemSecureObjectStore;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class SecureObjectPurposeAndVaultTest extends TestCase
{
    public function test_finalidades_distintas_no_enum(): void
    {
        $values = array_map(fn (SecureObjectPurpose $p) => $p->value, SecureObjectPurpose::cases());
        $this->assertContains('SERPRO_CONTRACTOR_PFX', $values);
        $this->assertContains('SERPRO_OAUTH_SECRETS', $values);
        $this->assertContains('SERPRO_BEARER_TOKEN', $values);
        $this->assertContains('SERPRO_PROCURADOR_TOKEN', $values);
        $this->assertContains('SERPRO_TERMO_XML', $values);
        $this->assertContains('SERPRO_AUTHOR_PFX', $values);
        $this->assertCount(count($values), array_unique($values));
    }

    public function test_aad_purpose_impede_leitura_cruzada(): void
    {
        $root = sys_get_temp_dir().'/serpro-vault-'.bin2hex(random_bytes(4));
        $key = random_bytes(32);
        $crypto = new EnvelopeCrypto($key, 1);
        $store = new FilesystemSecureObjectStore($crypto, $root);

        $aadPfx = SecureObjectPurpose::SerproContractorPfx->aadBase(['contract_id' => 1]);
        $aadOauth = SecureObjectPurpose::SerproOauthSecrets->aadBase(['contract_id' => 1]);

        $id = $store->put('secret-material', $aadPfx);
        $this->assertSame('secret-material', $store->get($id, $aadPfx));

        $this->expectException(RuntimeException::class);
        try {
            $store->get($id, $aadOauth);
        } finally {
            $this->rmTree($root);
        }
    }

    public function test_restore_drill_master_key_correta_vs_errada(): void
    {
        $root = sys_get_temp_dir().'/serpro-vault-mk-'.bin2hex(random_bytes(4));
        $goodKey = random_bytes(32);
        $badKey = random_bytes(32);

        $good = new FilesystemSecureObjectStore(new EnvelopeCrypto($goodKey, 1), $root);
        $aad = SecureObjectPurpose::SerproTermoXml->aadBase([
            'office_id' => 9,
            'sha256' => str_repeat('a', 64),
        ]);
        $id = $good->put('<Termo>assinado</Termo>', $aad);

        $this->assertSame('<Termo>assinado</Termo>', $good->get($id, $aad));

        $wrong = new FilesystemSecureObjectStore(new EnvelopeCrypto($badKey, 1), $root);
        $failed = false;
        try {
            $wrong->get($id, $aad);
        } catch (RuntimeException) {
            $failed = true;
        }

        $this->assertTrue($failed, 'Master key errada deve impedir open do envelope SERPRO');
        $this->rmTree($root);
    }

    private function rmTree(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($it as $file) {
            $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
        }
        @rmdir($dir);
    }
}
