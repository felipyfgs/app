<?php

namespace Tests\Feature\Serpro;

use App\Contracts\PfxReaderInterface;
use App\Enums\OfficeRole;
use App\Enums\SerproEnvironment;
use App\Models\Office;
use App\Models\SerproContract;
use App\Models\User;
use App\Services\Serpro\SerproContractService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Mockery;
use Tests\TestCase;

/**
 * Mutações legadas de contrato removidas (410).
 * Cadastro/ativação passa por credential-versions versionadas.
 */
class SerproContractApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_post_contrato_legado_retorna_410_sem_alterar_estado(): void
    {
        $admin = User::factory()->asPlatformAdmin()->create();
        $before = SerproContract::query()->count();

        $response = $this->actingAs($admin)->post('/api/v1/platform/serpro/contracts', [
            'environment' => 'TRIAL',
            'pfx' => UploadedFile::fake()->createWithContent('c.pfx', 'fake-pfx-binary-content'),
            'password' => 'secret-pfx-pass-xyz',
            'consumer_key' => 'ck-public-ish',
            'consumer_secret' => 'cs-super-secret-value',
            'contractor_name' => 'Software House',
            'activate' => true,
        ], ['Accept' => 'application/json']);

        $response->assertStatus(410)
            ->assertJsonPath('code', 'legacy_contract_mutation_removed');
        $this->assertSame($before, SerproContract::query()->count());
        $this->assertStringNotContainsString('cs-super-secret-value', (string) $response->getContent());
    }

    public function test_activate_deactivate_block_legados_retornam_410(): void
    {
        $admin = User::factory()->asPlatformAdmin()->create();
        $this->mockPfx('11222333000181');
        $contract = app(SerproContractService::class)->register(
            SerproEnvironment::Trial,
            'fake',
            'pass',
            'ck',
            'cs',
        );

        $this->actingAs($admin)
            ->postJson("/api/v1/platform/serpro/contracts/{$contract->id}/activate", ['replace' => true])
            ->assertStatus(410);

        $this->actingAs($admin)
            ->postJson("/api/v1/platform/serpro/contracts/{$contract->id}/deactivate", ['reason' => 'x'])
            ->assertStatus(410);

        $this->actingAs($admin)
            ->postJson("/api/v1/platform/serpro/contracts/{$contract->id}/block", ['reason' => 'x'])
            ->assertStatus(410);

        $this->assertSame($contract->status->value, $contract->fresh()->status->value);
    }

    public function test_leitura_historica_sanitizada_permanece(): void
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

        $list = $this->actingAs($admin)->getJson('/api/v1/platform/serpro/contracts?environment=TRIAL');
        $list->assertOk()->assertJsonCount(1, 'data');
        $payload = (string) $list->getContent();
        $this->assertStringNotContainsString('cs', $payload);
        $this->assertStringNotContainsString('pass', $payload);
        $this->assertArrayNotHasKey('pfx_vault_object_id', $list->json('data.0'));
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
