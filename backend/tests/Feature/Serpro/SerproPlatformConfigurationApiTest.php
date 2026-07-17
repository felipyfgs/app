<?php

namespace Tests\Feature\Serpro;

use App\Contracts\SecureObjectStore;
use App\Enums\OfficeRole;
use App\Enums\SerproCredentialVersionStatus;
use App\Enums\SerproEnvironment;
use App\Enums\SerproExternalGateKind;
use App\Models\Office;
use App\Models\SerproCredentialVersion;
use App\Models\SerproQuantityUsageLimit;
use App\Models\User;
use App\Services\Auth\RecentPasswordConfirmationGate;
use App\Services\Serpro\SerproCredentialVersionService;
use App\Services\Serpro\SerproKillSwitchService;
use App\Services\Serpro\SerproQuantityUsageLimitService;
use App\Services\Vault\EnvelopeCrypto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class SerproPlatformConfigurationApiTest extends TestCase
{
    use RefreshDatabase;

    private string $vaultRoot;

    private string $workDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->vaultRoot = sys_get_temp_dir().'/serpro-cfg-vault-'.uniqid('', true);
        $this->workDir = sys_get_temp_dir().'/serpro-cfg-work-'.uniqid('', true);
        File::ensureDirectoryExists($this->vaultRoot, 0700);
        File::ensureDirectoryExists($this->workDir, 0700);
        config([
            'vault.disk_root' => $this->vaultRoot,
            'vault.master_key' => base64_encode(str_repeat('V', 32)),
            'vault.master_key_version' => 1,
            'serpro.contractor_pfx.min_horizon_days' => 1,
            'serpro.contractor_pfx.require_chain' => false,
            'serpro.kill_switch' => false,
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

    public function test_configuration_get_sanitized_e_isolado_por_ambiente(): void
    {
        $admin = User::factory()->asPlatformAdmin()->create();
        [$pfx, $password] = $this->makePfx('11222333000181');

        $svc = app(SerproCredentialVersionService::class);
        $svc->registerPending(
            SerproEnvironment::Trial,
            $pfx,
            $password,
            'ck-trial-key-abcd',
            'cs-trial-secret',
        );
        $svc->registerPending(
            SerproEnvironment::Production,
            $pfx,
            $password,
            'ck-prod-key-wxyz',
            'cs-prod-secret',
        );

        $trial = $this->actingAs($admin)->getJson('/api/v1/platform/serpro/configuration?environment=TRIAL');
        $trial->assertOk();
        $data = $trial->json('data');
        $this->assertSame('TRIAL', $data['environment']);
        $this->assertArrayHasKey('endpoints', $data);
        $this->assertArrayHasKey('oauth_token_url', $data['endpoints']);
        $payload = (string) $trial->getContent();
        $this->assertStringNotContainsString('cs-trial-secret', $payload);
        $this->assertStringNotContainsString('cs-prod-secret', $payload);
        $this->assertStringNotContainsString($password, $payload);

        $versions = collect($data['pending_credential_versions'] ?? []);
        $this->assertTrue($versions->every(fn ($v) => ($v['environment'] ?? '') === 'TRIAL'));
    }

    public function test_nao_admin_nao_acessa_configuration(): void
    {
        $office = Office::factory()->create();
        $user = User::factory()->forOffice($office, OfficeRole::Admin)->create();

        $this->actingAs($user)
            ->getJson('/api/v1/platform/serpro/configuration')
            ->assertForbidden();
    }

    public function test_register_credential_version_exige_senha_recente(): void
    {
        $admin = User::factory()->asPlatformAdmin()->create();
        [$pfx, $password] = $this->makePfx('11222333000181');

        $this->actingAs($admin)->post('/api/v1/platform/serpro/credential-versions', [
            'environment' => 'TRIAL',
            'pfx' => UploadedFile::fake()->createWithContent('c.pfx', $pfx),
            'password' => $password,
            'consumer_key' => 'ck-abcdefg',
            'consumer_secret' => 'cs-super-secret',
        ], ['Accept' => 'application/json'])->assertStatus(403);

        app(RecentPasswordConfirmationGate::class)->markConfirmed($admin);

        $ok = $this->actingAs($admin)->post('/api/v1/platform/serpro/credential-versions', [
            'environment' => 'TRIAL',
            'pfx' => UploadedFile::fake()->createWithContent('c.pfx', $pfx),
            'password' => $password,
            'consumer_key' => 'ck-abcdefg',
            'consumer_secret' => 'cs-super-secret',
        ], ['Accept' => 'application/json']);

        $ok->assertCreated();
        $json = $ok->json('data');
        $this->assertSame('PENDING', $json['status']);
        $this->assertSame('efg', substr((string) $json['consumer_key_last4'], -3));
        $this->assertStringNotContainsString('cs-super-secret', (string) $ok->getContent());
        $this->assertStringNotContainsString($password, (string) $ok->getContent());
    }

    public function test_external_gate_aceite_exige_campos_completos(): void
    {
        $admin = User::factory()->asPlatformAdmin()->create();
        app(RecentPasswordConfirmationGate::class)->markConfirmed($admin);

        $gate = SerproExternalGateKind::OauthEndpointDivergence->value;

        $this->actingAs($admin)->patchJson("/api/v1/platform/serpro/external-gates/{$gate}", [
            'ticket_ref' => 'TKT-1',
            'answer_summary' => 'ok',
        ])->assertStatus(422);

        $ok = $this->actingAs($admin)->patchJson("/api/v1/platform/serpro/external-gates/{$gate}", [
            'ticket_ref' => 'TKT-1',
            'answer_summary' => 'Resumo formal aceito',
            'responsible_name' => 'Proprietário',
            'reference_date' => '2026-07-01',
        ]);
        $ok->assertOk();
        $this->assertSame('ACCEPTED', $ok->json('data.status'));
        $this->assertTrue($ok->json('data.is_complete'));
    }

    public function test_usage_limits_put_e_fail_closed(): void
    {
        $admin = User::factory()->asPlatformAdmin()->create();
        app(RecentPasswordConfirmationGate::class)->markConfirmed($admin);
        $office = Office::factory()->create();

        $this->actingAs($admin)->putJson('/api/v1/platform/serpro/usage-limits', [
            'environment' => 'TRIAL',
            'cycle_start_day' => 1,
            'alert_percent' => 80,
            'global_limit_quantity' => 10,
            'office_limits' => [
                ['office_id' => $office->id, 'limit_quantity' => 10],
            ],
        ])->assertOk();

        $row = SerproQuantityUsageLimit::query()->where('environment', 'TRIAL')->first();
        $this->assertNotNull($row);
        $this->assertSame(10, (int) $row->global_limit_quantity);

        $eval = app(SerproQuantityUsageLimitService::class)
            ->evaluate(SerproEnvironment::Trial, null, 0);
        $this->assertTrue($eval['allowed']);

        SerproQuantityUsageLimit::query()->where('environment', 'TRIAL')->update([
            'global_limit_quantity' => null,
        ]);
        $blocked = app(SerproQuantityUsageLimitService::class)
            ->evaluate(SerproEnvironment::Trial, null, 1);
        $this->assertFalse($blocked['allowed']);
    }

    public function test_kill_switch_env_true_prevalece_false_nao_promove(): void
    {
        $admin = User::factory()->asPlatformAdmin()->create();
        $ks = app(SerproKillSwitchService::class);

        config(['serpro.kill_switch' => false]);
        $this->assertFalse($ks->isGlobalActive());

        config(['serpro.kill_switch' => true]);
        $this->assertTrue($ks->isGlobalActive());

        $ks->deactivateGlobal('test', $admin->id);
        $this->assertTrue($ks->isGlobalActive());

        config(['serpro.kill_switch' => false]);
        $this->assertFalse($ks->isGlobalActive());
        $this->assertSame(0, SerproCredentialVersion::query()
            ->where('status', SerproCredentialVersionStatus::Active->value)
            ->count());
    }

    public function test_legacy_post_contrato_nao_altera_vault(): void
    {
        $admin = User::factory()->asPlatformAdmin()->create();
        $beforeVersions = SerproCredentialVersion::query()->count();

        $this->actingAs($admin)->post('/api/v1/platform/serpro/contracts', [
            'environment' => 'TRIAL',
            'pfx' => UploadedFile::fake()->createWithContent('c.pfx', 'x'),
            'password' => 'p',
            'consumer_key' => 'ck',
            'consumer_secret' => 'cs',
            'activate' => true,
        ], ['Accept' => 'application/json'])->assertStatus(410);

        $this->assertSame($beforeVersions, SerproCredentialVersion::query()->count());
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
