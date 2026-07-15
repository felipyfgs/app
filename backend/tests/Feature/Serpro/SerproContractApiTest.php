<?php

namespace Tests\Feature\Serpro;

use App\Contracts\PfxReaderInterface;
use App\Enums\OfficeRole;
use App\Enums\SerproContractStatus;
use App\Enums\SerproEnvironment;
use App\Models\AuditLog;
use App\Models\Office;
use App\Models\SerproContract;
use App\Models\User;
use App\Services\Serpro\SerproContractService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Mockery;
use Tests\TestCase;

class SerproContractApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_admin_cadastra_e_lista_sem_segredos(): void
    {
        $admin = User::factory()->asPlatformAdmin()->create();
        $this->mockPfx('11222333000181');

        $response = $this->actingAs($admin)->post('/api/v1/platform/serpro/contracts', [
            'environment' => 'TRIAL',
            'pfx' => UploadedFile::fake()->createWithContent('c.pfx', 'fake-pfx-binary-content'),
            'password' => 'secret-pfx-pass-xyz',
            'consumer_key' => 'ck-public-ish',
            'consumer_secret' => 'cs-super-secret-value',
            'contractor_name' => 'Software House',
            'activate' => true,
        ], ['Accept' => 'application/json']);

        $response->assertCreated();
        $json = $response->json('data');
        $this->assertSame('ACTIVE', $json['status']);
        $this->assertTrue($json['has_pfx']);
        $this->assertTrue($json['has_oauth']);
        $this->assertArrayNotHasKey('pfx_vault_object_id', $json);
        $this->assertArrayNotHasKey('oauth_vault_object_id', $json);
        $this->assertArrayNotHasKey('password', $json);
        $this->assertArrayNotHasKey('consumer_secret', $json);
        $content = (string) $response->getContent();
        $this->assertStringNotContainsString('cs-super-secret-value', $content);
        $this->assertStringNotContainsString('secret-pfx-pass-xyz', $content);
        $this->assertStringNotContainsString('BEGIN ', $content);

        $list = $this->actingAs($admin)->getJson('/api/v1/platform/serpro/contracts?environment=TRIAL');
        $list->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_segundo_active_exige_replace(): void
    {
        $admin = User::factory()->asPlatformAdmin()->create();
        $this->mockPfx('11222333000181');

        $this->actingAs($admin)->post('/api/v1/platform/serpro/contracts', [
            'environment' => 'TRIAL',
            'pfx' => UploadedFile::fake()->createWithContent('c.pfx', 'fake-pfx-1'),
            'password' => 'p1',
            'consumer_key' => 'ck1',
            'consumer_secret' => 'cs1',
            'activate' => true,
        ], ['Accept' => 'application/json'])->assertCreated();

        $this->mockPfx('11222333000181', fingerprint: str_repeat('B', 64));

        $second = $this->actingAs($admin)->post('/api/v1/platform/serpro/contracts', [
            'environment' => 'TRIAL',
            'pfx' => UploadedFile::fake()->createWithContent('c2.pfx', 'fake-pfx-2'),
            'password' => 'p2',
            'consumer_key' => 'ck2',
            'consumer_secret' => 'cs2',
            'activate' => true,
            'replace' => false,
        ], ['Accept' => 'application/json']);

        $second->assertStatus(422);
        $this->assertStringContainsString('ACTIVE', (string) $second->json('message'));

        $this->mockPfx('11222333000181', fingerprint: str_repeat('C', 64));
        $this->actingAs($admin)->post('/api/v1/platform/serpro/contracts', [
            'environment' => 'TRIAL',
            'pfx' => UploadedFile::fake()->createWithContent('c3.pfx', 'fake-pfx-3'),
            'password' => 'p3',
            'consumer_key' => 'ck3',
            'consumer_secret' => 'cs3',
            'activate' => true,
            'replace' => true,
        ], ['Accept' => 'application/json'])->assertCreated();

        $active = SerproContract::query()
            ->where('environment', SerproEnvironment::Trial->value)
            ->where('status', SerproContractStatus::Active->value)
            ->count();
        $this->assertSame(1, $active);
        $this->assertSame(1, SerproContract::query()->where('status', SerproContractStatus::Superseded->value)->count());
    }

    public function test_tenant_admin_nao_acessa_contrato_global(): void
    {
        $office = Office::factory()->create();
        $user = User::factory()->forOffice($office, OfficeRole::Admin)->withTwoFactorConfirmed()->create();

        $this->actingAs($user)
            ->getJson('/api/v1/platform/serpro/contracts')
            ->assertForbidden();
    }

    public function test_health_global_sanitizada(): void
    {
        $admin = User::factory()->asPlatformAdmin()->create();
        $this->mockPfx('11222333000181');
        app(SerproContractService::class)->register(
            SerproEnvironment::Trial,
            'fake',
            'pass',
            'ck',
            'cs',
        );

        $response = $this->actingAs($admin)->getJson('/api/v1/platform/serpro/health?environment=TRIAL');
        $response->assertOk();
        $payload = (string) $response->getContent();
        $this->assertStringNotContainsString('consumer_secret', $payload);
        $this->assertStringNotContainsString('"cs"', $payload);
        $this->assertArrayHasKey('kill_switch', $response->json('data'));
    }

    public function test_catalogo_seed_presente(): void
    {
        $admin = User::factory()->asPlatformAdmin()->create();
        $response = $this->actingAs($admin)->getJson('/api/v1/platform/serpro/catalog?environment=TRIAL');
        $response->assertOk();
        $this->assertNotEmpty($response->json('data'));
        $this->assertArrayHasKey('billable_class', $response->json('data.0'));
    }

    public function test_audit_nao_grava_segredo_em_falha(): void
    {
        $admin = User::factory()->asPlatformAdmin()->create();
        $reader = Mockery::mock(PfxReaderInterface::class);
        $reader->shouldReceive('read')->andThrow(new \RuntimeException('senha inválida'));
        $this->app->instance(PfxReaderInterface::class, $reader);

        $secret = 'ultra-secret-password-should-not-log';
        $this->actingAs($admin)->post('/api/v1/platform/serpro/contracts', [
            'environment' => 'TRIAL',
            'pfx' => UploadedFile::fake()->createWithContent('c.pfx', 'fake-pfx-fail'),
            'password' => $secret,
            'consumer_key' => 'ck',
            'consumer_secret' => 'cs-should-not-appear',
        ], ['Accept' => 'application/json'])->assertStatus(422);

        $logs = AuditLog::query()->where('action', 'like', 'serpro.%')->get();
        foreach ($logs as $log) {
            $encoded = json_encode($log->context ?? []);
            $this->assertStringNotContainsString($secret, (string) $encoded);
            $this->assertStringNotContainsString('cs-should-not-appear', (string) $encoded);
        }
    }

    private function mockPfx(string $cnpj, string $fingerprint = ''): void
    {
        $fingerprint = $fingerprint !== '' ? $fingerprint : str_repeat('A', 64);
        $reader = Mockery::mock(PfxReaderInterface::class);
        $reader->shouldReceive('read')->andReturn([
            'pfx' => 'fake-pfx-bytes-not-for-api',
            'password' => 'pass',
            'subject_name' => 'SOFTWARE HOUSE LTDA',
            'cnpj' => $cnpj,
            'fingerprint_sha256' => $fingerprint,
            'valid_from' => CarbonImmutable::now()->subYear(),
            'valid_to' => CarbonImmutable::now()->addYear(),
        ]);
        $this->app->instance(PfxReaderInterface::class, $reader);
    }
}
