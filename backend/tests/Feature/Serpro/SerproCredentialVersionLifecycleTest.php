<?php

namespace Tests\Feature\Serpro;

use App\Contracts\SecureObjectStore;
use App\Enums\SerproContractStatus;
use App\Enums\SerproCredentialVersionStatus;
use App\Enums\SerproEnvironment;
use App\Models\SerproContract;
use App\Models\SerproCredentialVersion;
use App\Services\Serpro\SerproCredentialVersionService;
use App\Services\Vault\EnvelopeCrypto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Tests\TestCase;

class SerproCredentialVersionLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private string $vaultRoot;

    private string $workDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->vaultRoot = sys_get_temp_dir().'/serpro-cred-vault-'.uniqid('', true);
        $this->workDir = sys_get_temp_dir().'/serpro-cred-work-'.uniqid('', true);
        File::ensureDirectoryExists($this->vaultRoot, 0700);
        File::ensureDirectoryExists($this->workDir, 0700);
        config([
            'vault.disk_root' => $this->vaultRoot,
            'vault.master_key' => base64_encode(str_repeat('V', 32)),
            'vault.master_key_version' => 1,
            'serpro.contractor_pfx.min_horizon_days' => 1,
            'serpro.contractor_pfx.require_chain' => false,
            'serpro.contractor_pfx.cutover_approvals_required' => 2,
            'serpro.trial.use_fake_clients' => true,
        ]);

        // Rebind vault after config change (singletons may already resolve).
        $this->app->forgetInstance(EnvelopeCrypto::class);
        $this->app->forgetInstance(SecureObjectStore::class);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->vaultRoot)) {
            File::deleteDirectory($this->vaultRoot);
        }
        if (is_dir($this->workDir)) {
            File::deleteDirectory($this->workDir);
        }
        parent::tearDown();
    }

    public function test_register_pending_e_verify_sem_expor_segredos(): void
    {
        [$pfx, $password] = $this->makePfx('11222333000181');

        $service = app(SerproCredentialVersionService::class);
        $version = $service->registerPending(
            SerproEnvironment::Trial,
            pfxBinary: $pfx,
            password: $password,
            consumerKey: 'ck-abcdef',
            consumerSecret: 'cs-super-secret-value',
        );

        $this->assertSame(SerproCredentialVersionStatus::Pending, $version->status);
        $this->assertNotNull($version->pfx_vault_object_id);
        $this->assertNotNull($version->oauth_vault_object_id);

        $sanitized = $version->toSanitizedArray();
        $json = json_encode($sanitized) ?: '';
        $this->assertStringNotContainsString('cs-super-secret-value', $json);
        $this->assertStringNotContainsString($password, $json);
        $this->assertArrayNotHasKey('pfx_vault_object_id', $sanitized);
        $this->assertTrue($sanitized['has_pfx']);
        $this->assertTrue($sanitized['has_oauth']);

        $verified = $service->verifyPending($version);
        $this->assertSame(SerproCredentialVersionStatus::Verified, $verified->status);
        $this->assertNotNull($verified->verified_at);
    }

    public function test_cutover_exige_dois_aprovadores(): void
    {
        [$pfx, $password] = $this->makePfx('11222333000181');
        $service = app(SerproCredentialVersionService::class);

        $contract = SerproContract::query()->create([
            'environment' => SerproEnvironment::Trial,
            'status' => SerproContractStatus::Active,
            'contractor_cnpj' => '11222333000181',
            'health_status' => 'OK',
        ]);

        $version = $service->registerPending(
            SerproEnvironment::Trial,
            $pfx,
            $password,
            'ck-1',
            'cs-1',
            $contract,
        );
        $service->verifyPending($version);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/aprovadores/i');
        $service->cutover($version->fresh(), $contract, skipOauth: true);
    }

    public function test_cutover_atomico_com_aprovacao_dupla_e_oauth_mock(): void
    {
        [$pfx, $password] = $this->makePfx('11222333000181');
        $service = app(SerproCredentialVersionService::class);

        $contract = SerproContract::query()->create([
            'environment' => SerproEnvironment::Trial,
            'status' => SerproContractStatus::Active,
            'contractor_cnpj' => '11222333000181',
            'health_status' => 'OK',
            'token_vault_object_id' => '01OLDTOKEN000000000000000',
            'token_expires_at' => now()->addHour(),
        ]);

        $oldActive = SerproCredentialVersion::query()->create([
            'serpro_contract_id' => $contract->id,
            'environment' => SerproEnvironment::Trial,
            'version_number' => 1,
            'status' => SerproCredentialVersionStatus::Active,
            'contractor_cnpj' => '11222333000181',
            'activated_at' => now()->subDay(),
        ]);
        $contract->forceFill(['active_credential_version_id' => $oldActive->id])->save();

        $version = $service->registerPending(
            SerproEnvironment::Trial,
            $pfx,
            $password,
            'ck-new',
            'cs-new',
            $contract,
        );
        $service->verifyPending($version);

        $service->recordApproval($version, 'CUTOVER', 10, true, 'APPROVE', 'eye1');
        $service->recordApproval($version, 'CUTOVER', 20, true, 'APPROVE', 'eye2');

        $oauthCalled = false;
        $active = $service->cutover(
            $version->fresh(),
            $contract->fresh(),
            actorUserId: null,
            oauthProbe: function (SerproContract $c) use (&$oauthCalled): void {
                $oauthCalled = true;
                $this->assertNotNull($c->pfx_vault_object_id);
                $this->assertNotNull($c->oauth_vault_object_id);
            },
        );

        $this->assertTrue($oauthCalled);
        $this->assertSame(SerproCredentialVersionStatus::Active, $active->status);
        $this->assertSame(SerproCredentialVersionStatus::Retired, $oldActive->fresh()->status);
        $this->assertSame($active->id, $contract->fresh()->active_credential_version_id);
        $this->assertNull($contract->fresh()->token_vault_object_id);
    }

    public function test_cutover_oauth_falha_nao_promove(): void
    {
        [$pfx, $password] = $this->makePfx('11222333000181');
        $service = app(SerproCredentialVersionService::class);

        $contract = SerproContract::query()->create([
            'environment' => SerproEnvironment::Trial,
            'status' => SerproContractStatus::Active,
            'contractor_cnpj' => '11222333000181',
            'health_status' => 'OK',
            'pfx_vault_object_id' => '01OLDPFX00000000000000000',
            'oauth_vault_object_id' => '01OLDOAUTH000000000000000',
        ]);

        $version = $service->registerPending(
            SerproEnvironment::Trial,
            $pfx,
            $password,
            'ck',
            'cs',
            $contract,
        );
        $service->verifyPending($version);
        $service->recordApproval($version, 'CUTOVER', 1, true, 'APPROVE');
        $service->recordApproval($version, 'CUTOVER', 2, true, 'APPROVE');

        try {
            $service->cutover(
                $version->fresh(),
                $contract->fresh(),
                oauthProbe: fn () => throw new RuntimeException('oauth down'),
            );
            $this->fail('deveria falhar');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('OAuth pré-cutover', $e->getMessage());
        }

        $this->assertSame(SerproCredentialVersionStatus::Verified, $version->fresh()->status);
        $this->assertSame('01OLDPFX00000000000000000', $contract->fresh()->pfx_vault_object_id);
    }

    public function test_approval_exige_senha_recente(): void
    {
        $version = SerproCredentialVersion::query()->create([
            'environment' => SerproEnvironment::Trial,
            'version_number' => 1,
            'status' => SerproCredentialVersionStatus::Verified,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/senha|password|reconfirm/i');
        app(SerproCredentialVersionService::class)->recordApproval(
            $version,
            'CUTOVER',
            1,
            totpVerified: false,
            decision: 'APPROVE',
        );
    }

    public function test_cli_recusa_segredo_por_argumento(): void
    {
        $argvBackup = $_SERVER['argv'] ?? null;
        $_SERVER['argv'] = ['artisan', 'serpro:credential-version', 'register-pending', '--consumer-secret=leaked'];

        try {
            $this->artisan('serpro:credential-version', [
                'action' => 'register-pending',
                '--serpro-env' => 'TRIAL',
                '--pfx-file' => '/tmp/does-not-matter.pfx',
            ])->assertFailed();
        } finally {
            if ($argvBackup === null) {
                unset($_SERVER['argv']);
            } else {
                $_SERVER['argv'] = $argvBackup;
            }
        }
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function makePfx(string $cnpj): array
    {
        $password = 'test-pass-'.bin2hex(random_bytes(3));
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
O = Contractor {$cnpj}
C = BR
[v3_req]
basicConstraints = CA:FALSE
keyUsage = digitalSignature, keyEncipherment
extendedKeyUsage = clientAuth
CNF);

        $cmd = sprintf(
            'openssl req -x509 -newkey rsa:2048 -keyout %s -out %s -days 90 -nodes -config %s 2>/dev/null && openssl pkcs12 -export -out %s -inkey %s -in %s -passout pass:%s 2>/dev/null',
            escapeshellarg($keyFile),
            escapeshellarg($certFile),
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
