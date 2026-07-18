<?php

namespace Tests\Feature\Serpro;

use App\Contracts\SecureObjectStore;
use App\Enums\AuthorCertificateMode;
use App\Enums\AuthorIdentityType;
use App\Enums\SerproAuthorizationStatus;
use App\Enums\SerproContractStatus;
use App\Enums\SerproCredentialVersionStatus;
use App\Enums\SerproDataSegregationClass;
use App\Enums\SerproEnvironment;
use App\Models\Client;
use App\Models\Office;
use App\Models\OfficeSerproAuthorization;
use App\Models\SerproContract;
use App\Models\SerproCredentialVersion;
use App\Models\User;
use App\Services\Auth\RecentPasswordConfirmationGate;
use App\Services\Serpro\SerproHttpTransport;
use App\Services\Vault\EnvelopeCrypto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class SerproProductionOnboardingApiTest extends TestCase
{
    use RefreshDatabase;

    private string $vaultRoot;

    private string $workDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vaultRoot = sys_get_temp_dir().'/serpro-prod-onboard-vault-'.uniqid('', true);
        $this->workDir = sys_get_temp_dir().'/serpro-prod-onboard-work-'.uniqid('', true);
        File::ensureDirectoryExists($this->vaultRoot, 0700);
        File::ensureDirectoryExists($this->workDir, 0700);

        config([
            'vault.disk_root' => $this->vaultRoot,
            'vault.master_key' => base64_encode(str_repeat('V', 32)),
            'vault.master_key_version' => 1,
            'features.platform_privileged_context.enabled' => true,
            'features.serpro_production_onboarding.enabled' => true,
            'features.serpro_production_onboarding.allow_all_offices' => true,
            'features.global_enabled' => true,
            'features.modules.mailbox.enabled' => true,
            'features.modules.mailbox.allow_all_offices' => true,
            'fiscal_monitoring.enabled' => true,
            'fiscal_monitoring.kill_switch' => false,
            'fiscal_monitoring.commercial.enabled' => false,
            'serpro.capabilities.mailbox' => 'real',
            'serpro.kill_switch' => false,
            'serpro.contractor_pfx.min_horizon_days' => 1,
            'serpro.contractor_pfx.require_chain' => false,
        ]);

        $this->app->forgetInstance(EnvelopeCrypto::class);
        $this->app->forgetInstance(SecureObjectStore::class);
        $this->app->instance(SerproHttpTransport::class, new class extends SerproHttpTransport
        {
            public array $requests = [];

            public function __construct() {}

            public function request(
                string $method,
                string $url,
                ?array $certificate,
                ?string $body,
                array $headers = [],
                ?string $correlationId = null,
            ): array {
                $this->requests[] = compact('method', 'url', 'headers', 'correlationId');

                return [
                    'status' => 200,
                    'body' => json_encode([
                        'access_token' => 'test-access-token',
                        'jwt_token' => 'test-jwt-token',
                        'expires_in' => 900,
                    ], JSON_THROW_ON_ERROR),
                    'headers' => [],
                    'retry_after' => null,
                    'latency_ms' => 1,
                ];
            }
        });
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

    public function test_flag_off_bloqueia_post_sem_persistir_segredos(): void
    {
        config(['features.serpro_production_onboarding.enabled' => false]);
        [$office, $admin] = $this->platformContext();
        app(RecentPasswordConfirmationGate::class)->markConfirmed($admin);
        [$file, $password] = $this->uploadedPfx('11222333000181');

        $response = $this->actingAs($admin)->post('/api/v1/platform/serpro/production-onboarding', [
            'consumer_key' => 'ck-prod',
            'consumer_secret' => 'secret-value',
            'certificate' => $file,
            'certificate_password' => $password,
            'consent_granted' => 'true',
        ], ['Idempotency-Key' => 'prod-onboard-flag-off']);

        $response->assertForbidden()->assertJsonPath('code', 'feature_disabled');
        $this->assertDatabaseCount('serpro_production_onboardings', 0);
    }

    public function test_rejeita_office_id_ambiente_e_senha_nao_recente(): void
    {
        [, $admin] = $this->platformContext();
        [$file, $password] = $this->uploadedPfx('11222333000181');

        $this->actingAs($admin)->post('/api/v1/platform/serpro/production-onboarding', [
            'consumer_key' => 'ck-prod',
            'consumer_secret' => 'secret-value',
            'certificate' => $file,
            'certificate_password' => $password,
            'consent_granted' => 'true',
        ])->assertForbidden()->assertJsonPath('code', 'password_confirmation_required');

        app(RecentPasswordConfirmationGate::class)->markConfirmed($admin);
        [$file, $password] = $this->uploadedPfx('11222333000181');
        $this->actingAs($admin)->post('/api/v1/platform/serpro/production-onboarding', [
            'office_id' => 999,
            'environment' => 'TRIAL',
            'consumer_key' => 'ck-prod',
            'consumer_secret' => 'secret-value',
            'certificate' => $file,
            'certificate_password' => $password,
            'consent_granted' => 'true',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['office_id', 'environment']);
    }

    public function test_happy_path_promove_credencial_autoriza_tenant_e_despacha_caixa_postal(): void
    {
        [$office, $admin] = $this->platformContext();
        $office->forceFill([
            'serpro_segregation_class' => SerproDataSegregationClass::Production->value,
        ])->save();
        Client::factory()->forOffice($office)->create();
        $this->createActiveAuthorization($office);
        app(RecentPasswordConfirmationGate::class)->markConfirmed($admin);
        [$file, $password] = $this->uploadedPfx('11222333000181');

        $response = $this->actingAs($admin)->post('/api/v1/platform/serpro/production-onboarding', [
            'consumer_key' => 'ck-prod-1234',
            'consumer_secret' => 'super-secret-value',
            'certificate' => $file,
            'certificate_password' => $password,
            'consent_granted' => 'true',
        ], ['Idempotency-Key' => 'prod-onboard-happy']);

        $response->assertCreated()
            ->assertJsonPath('data.enabled', true)
            ->assertJsonPath('data.onboarding.status', 'ACTIVE_SYNC_PENDING')
            ->assertJsonPath('data.onboarding.current_step', 'COMPLETED')
            ->assertJsonPath('data.onboarding.hints.consumer_key_hint', '****1234');

        $body = (string) $response->getContent();
        $this->assertStringNotContainsString('super-secret-value', $body);
        $this->assertStringNotContainsString($password, $body);
        $this->assertDatabaseHas('serpro_authorization_consents', [
            'office_id' => $office->id,
            'consent_type' => 'PRODUCTION_ONBOARDING',
        ]);
        $this->assertDatabaseHas('fiscal_monitoring_runs', [
            'office_id' => $office->id,
            'system_code' => 'INTEGRA_CAIXAPOSTAL',
            'service_code' => 'CAIXA_POSTAL',
            'operation_code' => 'LISTAR',
        ]);
    }

    public function test_retry_com_mesma_chave_idempotente_nao_duplica_credencial_consentimento_ou_sync(): void
    {
        [$office, $admin] = $this->platformContext();
        $office->forceFill([
            'serpro_segregation_class' => SerproDataSegregationClass::Production->value,
        ])->save();
        Client::factory()->forOffice($office)->create();
        $this->createActiveAuthorization($office);
        app(RecentPasswordConfirmationGate::class)->markConfirmed($admin);

        [$file, $password] = $this->uploadedPfx('11222333000181');
        $payload = [
            'consumer_key' => 'ck-prod-1234',
            'consumer_secret' => 'super-secret-value',
            'certificate' => $file,
            'certificate_password' => $password,
            'consent_granted' => 'true',
        ];

        $this->actingAs($admin)->post('/api/v1/platform/serpro/production-onboarding', $payload, [
            'Idempotency-Key' => 'prod-onboard-retry',
        ])->assertCreated()->assertJsonPath('data.onboarding.status', 'ACTIVE_SYNC_PENDING');

        [$file, $password] = $this->uploadedPfx('11222333000181');
        $payload['certificate'] = $file;
        $payload['certificate_password'] = $password;

        $this->actingAs($admin)->post('/api/v1/platform/serpro/production-onboarding', $payload, [
            'Idempotency-Key' => 'prod-onboard-retry',
        ])->assertCreated()->assertJsonPath('data.onboarding.status', 'ACTIVE_SYNC_PENDING');

        $this->assertDatabaseCount('serpro_production_onboardings', 1);
        $this->assertDatabaseCount('serpro_credential_versions', 1);
        $this->assertDatabaseCount('serpro_authorization_consents', 1);
        $this->assertDatabaseCount('fiscal_monitoring_runs', 1);
    }

    public function test_falha_oauth_aposenta_pendente_e_preserva_versao_ativa_anterior(): void
    {
        [$office, $admin] = $this->platformContext();
        $office->forceFill([
            'serpro_segregation_class' => SerproDataSegregationClass::Production->value,
        ])->save();
        $this->createActiveAuthorization($office);
        $active = $this->createActiveCredential();

        $this->app->instance(SerproHttpTransport::class, new class extends SerproHttpTransport
        {
            public function __construct() {}

            public function request(
                string $method,
                string $url,
                ?array $certificate,
                ?string $body,
                array $headers = [],
                ?string $correlationId = null,
            ): array {
                return [
                    'status' => 500,
                    'body' => json_encode([
                        'error' => 'invalid_client',
                        'consumer_secret' => 'super-secret-value',
                    ], JSON_THROW_ON_ERROR),
                    'headers' => [],
                    'retry_after' => null,
                    'latency_ms' => 1,
                ];
            }
        });

        app(RecentPasswordConfirmationGate::class)->markConfirmed($admin);
        [$file, $password] = $this->uploadedPfx('11222333000181');

        $response = $this->actingAs($admin)->post('/api/v1/platform/serpro/production-onboarding', [
            'consumer_key' => 'ck-prod-fail',
            'consumer_secret' => 'super-secret-value',
            'certificate' => $file,
            'certificate_password' => $password,
            'consent_granted' => 'true',
        ], ['Idempotency-Key' => 'prod-onboard-oauth-fail']);

        $response->assertStatus(422)->assertJsonPath('code', 'serpro_production_onboarding_failed');
        $this->assertStringNotContainsString('super-secret-value', (string) $response->getContent());
        $this->assertDatabaseHas('serpro_credential_versions', [
            'id' => $active->id,
            'status' => SerproCredentialVersionStatus::Active->value,
        ]);
        $this->assertDatabaseHas('serpro_credential_versions', [
            'environment' => SerproEnvironment::Production->value,
            'status' => SerproCredentialVersionStatus::Retired->value,
        ]);
        $this->assertDatabaseHas('serpro_production_onboardings', [
            'status' => 'FAILED',
            'error_code' => 'OAUTH_FAILED',
        ]);
    }

    public function test_kill_switch_de_monitoramento_bloqueia_sync_inicial_sem_criar_run(): void
    {
        config(['serpro.kill_switch' => true]);
        [$office, $admin] = $this->platformContext();
        $office->forceFill([
            'serpro_segregation_class' => SerproDataSegregationClass::Production->value,
        ])->save();
        Client::factory()->forOffice($office)->create();
        $this->createActiveAuthorization($office);
        app(RecentPasswordConfirmationGate::class)->markConfirmed($admin);
        [$file, $password] = $this->uploadedPfx('11222333000181');

        $response = $this->actingAs($admin)->post('/api/v1/platform/serpro/production-onboarding', [
            'consumer_key' => 'ck-prod-1234',
            'consumer_secret' => 'super-secret-value',
            'certificate' => $file,
            'certificate_password' => $password,
            'consent_granted' => 'true',
        ], ['Idempotency-Key' => 'prod-onboard-kill-switch']);

        $response->assertCreated()
            ->assertJsonPath('data.onboarding.status', 'ACTION_REQUIRED')
            ->assertJsonPath('data.onboarding.error.code', 'KILL_SWITCH');
        $this->assertDatabaseCount('fiscal_monitoring_runs', 0);
    }

    public function test_get_estado_nao_cria_versao_nem_chamada_externa(): void
    {
        [, $admin] = $this->platformContext();

        $this->actingAs($admin)
            ->getJson('/api/v1/platform/serpro/production-onboarding')
            ->assertOk()
            ->assertJsonPath('data.onboarding', null)
            ->assertJsonPath('data.enabled', true);

        $this->assertDatabaseCount('serpro_production_onboardings', 0);
        $this->assertDatabaseCount('serpro_credential_versions', 0);
    }

    /**
     * @return array{0: Office, 1: User}
     */
    private function platformContext(): array
    {
        $office = Office::factory()->create();
        $admin = User::factory()->asPlatformAdmin($office->id)->create();
        $this->actingAs($admin);

        return [$office, $admin];
    }

    private function createActiveAuthorization(Office $office): OfficeSerproAuthorization
    {
        return OfficeSerproAuthorization::query()->create([
            'office_id' => $office->id,
            'environment' => SerproEnvironment::Production,
            'status' => SerproAuthorizationStatus::TokenActive,
            'author_identity_type' => AuthorIdentityType::Cnpj,
            'author_identity' => '11222333000181',
            'certificate_mode' => AuthorCertificateMode::ExternalSignature,
            'termo_vault_object_id' => '01TERMO000000000000000000',
            'termo_sha256' => hash('sha256', 'termo'),
            'termo_valid_to' => now()->addYear(),
            'procurador_token_vault_object_id' => '01TOKEN000000000000000000',
            'procurador_token_expires_at' => now()->addHour(),
        ]);
    }

    private function createActiveCredential(): SerproCredentialVersion
    {
        $contract = SerproContract::query()->create([
            'environment' => SerproEnvironment::Production,
            'status' => SerproContractStatus::Active,
            'contractor_cnpj' => '11222333000181',
            'activated_at' => now(),
            'consumer_key_hint' => '****old1',
            'segregation_class' => SerproDataSegregationClass::Production,
        ]);

        $version = SerproCredentialVersion::query()->create([
            'serpro_contract_id' => $contract->id,
            'environment' => SerproEnvironment::Production,
            'version_number' => 1,
            'status' => SerproCredentialVersionStatus::Active,
            'consumer_key_hint' => '****old1',
            'fingerprint_sha256' => str_repeat('A', 64),
            'contractor_cnpj' => '11222333000181',
            'cert_valid_from' => now()->subDay(),
            'cert_valid_to' => now()->addYear(),
            'pfx_vault_object_id' => '01PFXOLD0000000000000000',
            'oauth_vault_object_id' => '01OAOLD00000000000000000',
            'activated_at' => now(),
            'segregation_class' => SerproDataSegregationClass::Production,
        ]);

        $contract->forceFill(['active_credential_version_id' => $version->id])->save();

        return $version;
    }

    /**
     * @return array{0: UploadedFile, 1: string}
     */
    private function uploadedPfx(string $cnpj): array
    {
        [$pfxPath, $password] = $this->makePfxFile($cnpj);

        return [
            new UploadedFile($pfxPath, 'contratante.pfx', 'application/x-pkcs12', null, true),
            $password,
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function makePfxFile(string $cnpj): array
    {
        $password = 'test-pass-'.bin2hex(random_bytes(3));
        $prefix = $this->workDir.'/'.bin2hex(random_bytes(4));
        $keyFile = $prefix.'-key.pem';
        $certFile = $prefix.'-cert.pem';
        $pfxFile = $prefix.'-cert.pfx';
        $cfg = $prefix.'-openssl.cnf';

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

        return [$pfxFile, $password];
    }
}
