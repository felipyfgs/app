<?php

namespace Tests\Feature\Ops;

use App\Models\PlatformSetting;
use App\Models\User;
use App\Services\Ops\ProductionReadinessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;
use Mockery;
use Tests\TestCase;

class ProductionReadinessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('app.env', 'production');
        Config::set('app.debug', false);
        Config::set('mail.default', 'smtp');
        Config::set('mail.mailers.smtp.host', 'smtp.test.local');
        Config::set('mail.from.address', 'noreply@test.local');
        Config::set('features.global_enabled', false);
        Config::set('features.mutating.enabled', false);
        Config::set('serpro.trial.use_fake_clients', false);
        Config::set('serpro.kill_switch', true);
        Config::set('serpro.capabilities', [
            'sitfis' => 'disabled',
            'default' => 'disabled',
        ]);
        Config::set('sefaz.distdfe_enabled', false);
        Config::set('sefaz.manifest_enabled', false);
        Config::set('sefaz.cte_enabled', false);
        Config::set('sefaz.nfce_enabled', false);
        Config::set('sefaz.ma_outbound.enabled', false);
        Config::set('sefaz.autxml.enabled', false);
        Config::set('sefaz.cte_autxml.enabled', false);
        Config::set('onboarding.enabled', false);
        Config::set('onboarding.token', '');
        Config::set('ops.scheduler_heartbeat.max_age_seconds', 180);
        Config::set('ops.release_sha', 'abc123deadbeef');

        Cache::put(
            ProductionReadinessService::HEARTBEAT_CACHE_KEY,
            now()->utc()->toIso8601String(),
            now()->addHour()
        );

        $masters = [(object) ['name' => 'master-1']];
        $repo = Mockery::mock(MasterSupervisorRepository::class);
        $repo->shouldReceive('all')->andReturn($masters)->byDefault();
        $this->app->instance(MasterSupervisorRepository::class, $repo);
    }

    public function test_readiness_passes_with_healthy_stack(): void
    {
        $this->artisan('ops:production-readiness', ['--json' => true, '--no-persist' => true])
            ->assertSuccessful();

        Artisan::call('ops:production-readiness', ['--json' => true, '--no-persist' => true]);
        $payload = json_decode(Artisan::output(), true);

        $this->assertIsArray($payload);
        $this->assertTrue($payload['ok']);
        $this->assertSame('abc123deadbeef', $payload['release_sha']);
        $this->assertArrayHasKey('checks', $payload);
        $this->assertArrayHasKey('issues', $payload);
        $this->assertSame([], $payload['issues']);

        $ids = array_column($payload['checks'], 'id');
        $this->assertContains('environment', $ids);
        $this->assertContains('migrations', $ids);
        $this->assertContains('horizon', $ids);
        $this->assertContains('scheduler_heartbeat', $ids);
        $this->assertContains('fiscal_containment', $ids);
    }

    public function test_readiness_fails_when_debug_enabled(): void
    {
        Config::set('app.debug', true);

        $this->artisan('ops:production-readiness', ['--json' => true])
            ->assertFailed();
    }

    public function test_readiness_fails_when_horizon_has_no_masters(): void
    {
        $repo = Mockery::mock(MasterSupervisorRepository::class);
        $repo->shouldReceive('all')->andReturn([]);
        $this->app->instance(MasterSupervisorRepository::class, $repo);

        $this->artisan('ops:production-readiness', ['--json' => true])
            ->assertFailed();

        Artisan::call('ops:production-readiness', ['--json' => true]);
        $payload = json_decode(Artisan::output(), true);
        $horizon = collect($payload['checks'])->firstWhere('id', 'horizon');
        $this->assertFalse($horizon['ok']);
    }

    public function test_readiness_fails_when_heartbeat_missing(): void
    {
        Cache::forget(ProductionReadinessService::HEARTBEAT_CACHE_KEY);

        $this->artisan('ops:production-readiness', ['--json' => true])
            ->assertFailed();

        Artisan::call('ops:production-readiness', ['--json' => true]);
        $payload = json_decode(Artisan::output(), true);
        $hb = collect($payload['checks'])->firstWhere('id', 'scheduler_heartbeat');
        $this->assertFalse($hb['ok']);
        $this->assertSame('missing', $hb['detail']);
    }

    public function test_readiness_fails_when_heartbeat_stale(): void
    {
        $this->travel(-10)->minutes();
        Cache::put(
            ProductionReadinessService::HEARTBEAT_CACHE_KEY,
            now()->utc()->toIso8601String(),
            now()->addHour()
        );
        $this->travelBack();

        $this->artisan('ops:production-readiness', ['--json' => true])
            ->assertFailed();
    }

    public function test_scheduler_heartbeat_command_writes_cache(): void
    {
        Cache::forget(ProductionReadinessService::HEARTBEAT_CACHE_KEY);

        $this->artisan('ops:scheduler-heartbeat')->assertSuccessful();

        $this->assertNotEmpty(Cache::get(ProductionReadinessService::HEARTBEAT_CACHE_KEY));
    }

    public function test_readiness_fails_when_fiscal_flag_enabled(): void
    {
        Config::set('features.global_enabled', true);

        $this->artisan('ops:production-readiness', ['--json' => true])
            ->assertFailed();

        Artisan::call('ops:production-readiness', ['--json' => true]);
        $payload = json_decode(Artisan::output(), true);
        $containment = collect($payload['checks'])->firstWhere('id', 'fiscal_containment');
        $this->assertFalse($containment['ok']);
        $this->assertStringContainsString('features_global_enabled', $containment['detail']);
    }

    public function test_readiness_fails_when_serpro_driver_real(): void
    {
        Config::set('serpro.capabilities.sitfis', 'real');

        $this->artisan('ops:production-readiness', ['--json' => true])
            ->assertFailed();
    }

    public function test_readiness_fails_when_onboarding_open_after_bootstrap(): void
    {
        User::factory()->create();
        PlatformSetting::query()->create([
            'id' => PlatformSetting::SINGLETON_ID,
            'organization_name' => 'Org',
            'onboarding_completed_at' => now(),
            'onboarded_by_user_id' => 1,
        ]);
        Config::set('onboarding.enabled', true);
        Config::set('onboarding.token', str_repeat('a', 40));

        $this->artisan('ops:production-readiness', ['--json' => true])
            ->assertFailed();
    }

    public function test_json_output_does_not_leak_secrets(): void
    {
        Config::set('vault.master_key', 'super-secret-vault-key-value-xyz');
        Config::set('mail.mailers.smtp.password', 'smtp-password-secret');

        Artisan::call('ops:production-readiness', ['--json' => true]);
        $output = Artisan::output();

        $this->assertStringNotContainsString('super-secret-vault-key-value-xyz', $output);
        $this->assertStringNotContainsString('smtp-password-secret', $output);
        $this->assertStringNotContainsString('VAULT_MASTER_KEY', $output);
    }

    public function test_mail_smoke_uses_fake_mailer_and_sanitizes_output(): void
    {
        // mailer array: grava em memória, sem transporte externo (adequado a CI).
        Config::set('mail.default', 'array');

        $exit = Artisan::call('ops:mail-smoke', [
            '--to' => 'ops@example.org',
            '--json' => true,
        ]);
        $this->assertSame(0, $exit);
        $output = Artisan::output();
        $payload = json_decode($output, true);

        $this->assertIsArray($payload);
        $this->assertTrue($payload['ok']);
        $this->assertTrue($payload['sent']);
        $this->assertSame('example.org', $payload['to_domain']);
        $this->assertArrayNotHasKey('to', $payload);
        $this->assertStringNotContainsString('ops@example.org', $output);
        $this->assertStringNotContainsString('Smoke SMTP da plataforma', $output);
    }

    public function test_mail_smoke_rejects_invalid_recipient(): void
    {
        $this->artisan('ops:mail-smoke', ['--to' => 'not-an-email'])
            ->assertFailed();
    }
}
