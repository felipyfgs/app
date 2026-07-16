<?php

namespace Tests\Unit\Support;

use App\Enums\OfficeAccessMode;
use App\Enums\OfficeRole;
use App\Models\Office;
use App\Models\OfficeMembership;
use App\Models\User;
use App\Support\CurrentOffice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurrentOfficePrivilegedTest extends TestCase
{
    use RefreshDatabase;

    public function test_bind_platform_privileged_sem_membership(): void
    {
        $office = Office::factory()->create();
        $admin = User::factory()->asPlatformAdmin()->create();

        $ctx = app(CurrentOffice::class);
        $ctx->bindPlatformPrivileged($admin, $office);

        $this->assertTrue($ctx->isPlatformPrivileged());
        $this->assertSame(OfficeAccessMode::PlatformPrivileged, $ctx->accessMode());
        $this->assertSame(OfficeRole::Admin, $ctx->role());
        $this->assertNull($ctx->membership());
        $this->assertSame($admin->id, $ctx->actor()?->id);
        $this->assertSame($office->id, $ctx->id());
        $this->assertSame(0, OfficeMembership::query()->where('user_id', $admin->id)->count());
    }

    public function test_resolve_privilegiado_via_cache_quando_flag_on(): void
    {
        config([
            'features.kill_switch' => false,
            'features.platform_privileged_context.enabled' => true,
        ]);

        $office = Office::factory()->create();
        $admin = User::factory()->asPlatformAdmin()->create();

        $this->actingAs($admin);
        $ctx = app(CurrentOffice::class);
        $ctx->rememberPlatformSelection($admin, $office->id);
        $ctx->clear();
        $resolved = $ctx->resolve($admin);

        $this->assertNotNull($resolved);
        $this->assertSame($office->id, $resolved->id);
        $this->assertTrue($ctx->isPlatformPrivileged());
        $this->assertSame(OfficeRole::Admin, $ctx->role());
    }

    public function test_resolve_ignora_selecao_privilegiada_quando_flag_off(): void
    {
        config([
            'features.kill_switch' => false,
            'features.platform_privileged_context.enabled' => false,
        ]);

        $office = Office::factory()->create();
        $admin = User::factory()->asPlatformAdmin()->create();

        $this->actingAs($admin);
        $ctx = app(CurrentOffice::class);
        $ctx->rememberPlatformSelection($admin, $office->id);
        $ctx->clear();

        $this->assertNull($ctx->resolve($admin));
        $this->assertFalse($ctx->isPlatformPrivileged());
    }

    public function test_membership_retorna_apos_clear_privilegiado(): void
    {
        config([
            'features.kill_switch' => false,
            'features.platform_privileged_context.enabled' => true,
        ]);

        $officeA = Office::factory()->create();
        $officeB = Office::factory()->create();
        $admin = User::factory()
            ->asPlatformAdmin()
            ->forOffice($officeA, OfficeRole::Viewer)
            ->create();

        $this->actingAs($admin);
        $ctx = app(CurrentOffice::class);
        $ctx->rememberPlatformSelection($admin, $officeB->id);
        $ctx->clear();
        $this->assertSame($officeB->id, $ctx->resolve($admin)?->id);
        $this->assertTrue($ctx->isPlatformPrivileged());

        $ctx->forgetPlatformSelection($admin);
        $ctx->clear();
        $this->assertSame($officeA->id, $ctx->resolve($admin)?->id);
        $this->assertSame(OfficeAccessMode::Membership, $ctx->accessMode());
        $this->assertSame(OfficeRole::Viewer, $ctx->role());
    }
}
