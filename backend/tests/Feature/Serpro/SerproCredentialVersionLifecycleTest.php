<?php

namespace Tests\Feature\Serpro;

use App\Contracts\SecureObjectStore;
use App\Enums\SerproContractStatus;
use App\Enums\SerproCredentialVersionStatus;
use App\Enums\SerproEnvironment;
use App\Models\SerproContract;
use App\Models\SerproCredentialVersion;
use App\Models\SerproRolloutApproval;
use App\Models\User;
use App\Services\Serpro\SerproCredentialVersionService;
use App\Services\Serpro\SerproRolloutApprovalService;
use App\Services\Vault\EnvelopeCrypto;
use Carbon\CarbonImmutable;
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
            'serpro.trial.use_fake_clients' => true,
        ]);

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

    public function test_cutover_exige_confirmacao_owner_vinculada(): void
    {
        [$pfx, $password] = $this->makePfx('11222333000181');
        $service = app(SerproCredentialVersionService::class);
        $owner = User::factory()->asPlatformAdmin()->create();

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
        $this->expectExceptionMessageMatches('/confirmação|OWNER|aprovação/i');
        $service->cutover($version->fresh(), $contract, actorUserId: $owner->id, skipOauth: true);
    }

    public function test_cutover_atomico_com_owner_confirmation_e_oauth_mock(): void
    {
        [$pfx, $password] = $this->makePfx('11222333000181');
        $service = app(SerproCredentialVersionService::class);
        $owner = User::factory()->asPlatformAdmin()->create();

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

        $approval = $this->confirmOwnerCutover($owner, (int) $version->id);

        $oauthCalled = false;
        $active = $service->cutover(
            $version->fresh(),
            $contract->fresh(),
            actorUserId: $owner->id,
            oauthProbe: function (SerproContract $c) use (&$oauthCalled): void {
                $oauthCalled = true;
                $this->assertNotNull($c->pfx_vault_object_id);
                $this->assertNotNull($c->oauth_vault_object_id);
            },
            approvalId: $approval->id,
        );

        $this->assertTrue($oauthCalled);
        $this->assertSame(SerproCredentialVersionStatus::Active, $active->status);
        $this->assertSame(SerproCredentialVersionStatus::Retired, $oldActive->fresh()->status);
        $this->assertSame($active->id, $contract->fresh()->active_credential_version_id);
        $this->assertNull($contract->fresh()->token_vault_object_id);
        $this->assertSame('EXECUTED', $approval->fresh()->status);
    }

    public function test_cutover_oauth_falha_nao_promove_nem_consome_aprovacao(): void
    {
        [$pfx, $password] = $this->makePfx('11222333000181');
        $service = app(SerproCredentialVersionService::class);
        $owner = User::factory()->asPlatformAdmin()->create();

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
        $approval = $this->confirmOwnerCutover($owner, (int) $version->id);

        try {
            $service->cutover(
                $version->fresh(),
                $contract->fresh(),
                actorUserId: $owner->id,
                oauthProbe: fn () => throw new RuntimeException('oauth down'),
                approvalId: $approval->id,
            );
            $this->fail('deveria falhar');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('OAuth pré-cutover', $e->getMessage());
        }

        $this->assertSame(SerproCredentialVersionStatus::Verified, $version->fresh()->status);
        $this->assertSame('01OLDPFX00000000000000000', $contract->fresh()->pfx_vault_object_id);
        $this->assertSame('APPROVED', $approval->fresh()->status);
        $this->assertNull($approval->fresh()->executed_at);
    }

    public function test_approval_legado_cutover_recusado(): void
    {
        $version = SerproCredentialVersion::query()->create([
            'environment' => SerproEnvironment::Trial,
            'version_number' => 1,
            'status' => SerproCredentialVersionStatus::Verified,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/OWNER|CREDENTIAL_CUTOVER|legado/i');
        app(SerproCredentialVersionService::class)->recordApproval(
            $version,
            'CUTOVER',
            1,
            totpVerified: true,
            decision: 'APPROVE',
        );
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
            'RETIRE',
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

    public function test_cli_approve_cutover_nao_fabrica(): void
    {
        $this->artisan('serpro:credential-version', [
            'action' => 'approve-cutover',
            '--id' => 1,
            '--approver-user-id' => 1,
        ])->assertFailed();
    }

    private function confirmOwnerCutover(User $owner, int $versionId): SerproRolloutApproval
    {
        $svc = app(SerproRolloutApprovalService::class);
        $start = CarbonImmutable::now()->subMinutes(5);
        $end = CarbonImmutable::now()->addHour();
        $approval = $svc->request(
            action: SerproRolloutApprovalService::ACTION_CREDENTIAL_CUTOVER,
            subjectType: 'CREDENTIAL_VERSION',
            subjectId: $versionId,
            reason: 'cutover teste',
            requestedByUserId: $owner->id,
            environment: SerproEnvironment::Trial,
            changeWindowStart: $start,
            changeWindowEnd: $end,
        );
        $svc->approve(
            $approval,
            $owner->id,
            passwordRecentlyConfirmed: true,
            reason: 'cutover teste',
            confirmationPhrase: 'CONFIRMO-CREDENTIAL_CUTOVER',
            changeWindowStart: $start,
            changeWindowEnd: $end,
        );

        return $approval->fresh();
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
