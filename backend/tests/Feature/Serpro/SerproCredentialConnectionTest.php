<?php

namespace Tests\Feature\Serpro;

use App\Contracts\SecureObjectStore;
use App\Enums\SerproCredentialVersionStatus;
use App\Enums\SerproEnvironment;
use App\Models\SerproCredentialConnectionEvidence;
use App\Services\Serpro\SerproCredentialVersionService;
use App\Services\Vault\EnvelopeCrypto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Tests\TestCase;

class SerproCredentialConnectionTest extends TestCase
{
    use RefreshDatabase;

    private string $vaultRoot;

    private string $workDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->vaultRoot = sys_get_temp_dir().'/serpro-conn-vault-'.uniqid('', true);
        $this->workDir = sys_get_temp_dir().'/serpro-conn-work-'.uniqid('', true);
        File::ensureDirectoryExists($this->vaultRoot, 0700);
        File::ensureDirectoryExists($this->workDir, 0700);
        config([
            'vault.disk_root' => $this->vaultRoot,
            'vault.master_key' => base64_encode(str_repeat('V', 32)),
            'vault.master_key_version' => 1,
            'serpro.contractor_pfx.min_horizon_days' => 1,
            'serpro.contractor_pfx.require_chain' => false,
            'serpro.oauth.token_url' => 'https://autenticacao.sapi.serpro.gov.br/authenticate',
            'serpro.credential_connection_test_ttl_minutes' => 15,
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

    public function test_test_connection_oauth_sucesso_registra_evidencia_sem_promover(): void
    {
        [$pfx, $password] = $this->makePfx('11222333000181');
        $service = app(SerproCredentialVersionService::class);
        $version = $service->registerPending(
            SerproEnvironment::Trial,
            $pfx,
            $password,
            'ck-test-key-9999',
            'cs-secret',
        );
        $service->verifyPending($version);

        $evidence = $service->testConnection(
            $version->fresh(),
            actorUserId: 1,
            oauthTransportProbe: fn () => ['status' => 200, 'body' => json_encode([
                'access_token' => 'tok',
                'jwt_token' => 'jwt',
            ])],
        );

        $this->assertTrue($evidence->success);
        $this->assertSame($version->id, $evidence->serpro_credential_version_id);
        $this->assertSame($version->fingerprint_sha256, $evidence->fingerprint_sha256);
        $this->assertFalse($evidence->invalidated);
        $this->assertSame(SerproCredentialVersionStatus::Verified, $version->fresh()->status);

        $sanitized = $evidence->toSanitizedArray();
        $json = json_encode($sanitized) ?: '';
        $this->assertStringNotContainsString('cs-secret', $json);
        $this->assertStringNotContainsString($password, $json);
        $this->assertStringNotContainsString('tok', $json);
    }

    public function test_test_connection_falha_nao_grava_sucesso(): void
    {
        [$pfx, $password] = $this->makePfx('11222333000181');
        $service = app(SerproCredentialVersionService::class);
        $version = $service->registerPending(
            SerproEnvironment::Trial,
            $pfx,
            $password,
            'ck',
            'cs',
        );
        $service->verifyPending($version);

        try {
            $service->testConnection(
                $version->fresh(),
                oauthTransportProbe: fn () => ['status' => 401, 'body' => '{}'],
            );
            $this->fail('deveria falhar');
        } catch (RuntimeException) {
            // expected
        }

        $this->assertSame(0, SerproCredentialConnectionEvidence::query()
            ->where('serpro_credential_version_id', $version->id)
            ->where('success', true)
            ->count());
        $this->assertSame(SerproCredentialVersionStatus::Verified, $version->fresh()->status);
    }

    public function test_endpoint_oauth_com_host_malicioso_bloqueia_antes_do_transporte_e_nao_expoe_segredos(): void
    {
        [$pfx, $password] = $this->makePfx('11222333000181');
        $consumerSecret = 'consumer-secret-nao-pode-vazar';
        $service = app(SerproCredentialVersionService::class);
        $version = $service->registerPending(
            SerproEnvironment::Trial,
            $pfx,
            $password,
            'consumer-key-nao-pode-vazar',
            $consumerSecret,
        );
        $service->verifyPending($version);
        config([
            'serpro.oauth.token_url' => 'https://evil.example/?x=autenticacao.sapi.serpro.gov.br/authenticate',
        ]);

        $transportCalls = 0;
        try {
            $service->testConnection(
                $version->fresh(),
                oauthTransportProbe: function () use (&$transportCalls): array {
                    $transportCalls++;

                    return ['status' => 200];
                },
            );
            $this->fail('endpoint não oficial deveria ser bloqueado');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('oficial SERPRO', $e->getMessage());
            $this->assertStringNotContainsString($password, $e->getMessage());
            $this->assertStringNotContainsString($consumerSecret, $e->getMessage());
            $this->assertStringNotContainsString($pfx, $e->getMessage());
        }

        $this->assertSame(0, $transportCalls);
        $this->assertDatabaseCount('serpro_credential_connection_evidences', 0);
        $this->assertSame(SerproCredentialVersionStatus::Verified, $version->fresh()->status);
    }

    public function test_evidencia_expirada_e_cross_version_nao_validam(): void
    {
        [$pfx, $password] = $this->makePfx('11222333000181');
        $service = app(SerproCredentialVersionService::class);
        $v1 = $service->registerPending(SerproEnvironment::Trial, $pfx, $password, 'ck1', 'cs1');
        $service->verifyPending($v1);
        $v2 = $service->registerPending(SerproEnvironment::Trial, $pfx, $password, 'ck2', 'cs2');
        $service->verifyPending($v2);

        $expired = SerproCredentialConnectionEvidence::query()->create([
            'serpro_credential_version_id' => $v2->id,
            'environment' => SerproEnvironment::Trial,
            'fingerprint_sha256' => $v2->fingerprint_sha256,
            'success' => true,
            'tested_at' => now()->subHour(),
            'expires_at' => now()->subMinute(),
            'invalidated' => false,
        ]);
        $this->assertFalse($expired->isValidFor($v2->fresh()));

        $cross = SerproCredentialConnectionEvidence::query()->create([
            'serpro_credential_version_id' => $v1->id,
            'environment' => SerproEnvironment::Trial,
            'fingerprint_sha256' => $v1->fingerprint_sha256,
            'success' => true,
            'tested_at' => now(),
            'expires_at' => now()->addMinutes(15),
            'invalidated' => false,
        ]);
        $this->assertFalse($cross->isValidFor($v2->fresh()));
    }

    public function test_compromisso_invalida_evidencia(): void
    {
        [$pfx, $password] = $this->makePfx('11222333000181');
        $service = app(SerproCredentialVersionService::class);
        $version = $service->registerPending(SerproEnvironment::Trial, $pfx, $password, 'ck', 'cs');
        $service->verifyPending($version);
        $service->testConnection(
            $version->fresh(),
            oauthTransportProbe: fn () => ['status' => 200, 'body' => json_encode([
                'access_token' => 'a',
                'jwt_token' => 'j',
            ])],
        );

        $this->assertNotNull($version->fresh()->latestValidConnectionEvidence());
        $service->markCompromised($version->fresh(), 'vazamento simulado');
        $this->assertNull($version->fresh()->latestValidConnectionEvidence());
        $this->assertSame(SerproCredentialVersionStatus::Compromised, $version->fresh()->status);
    }

    public function test_pending_nao_permite_test_connection(): void
    {
        [$pfx, $password] = $this->makePfx('11222333000181');
        $service = app(SerproCredentialVersionService::class);
        $version = $service->registerPending(SerproEnvironment::Trial, $pfx, $password, 'ck', 'cs');

        $this->expectException(RuntimeException::class);
        $service->testConnection(
            $version,
            oauthTransportProbe: fn () => ['status' => 200],
        );
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
