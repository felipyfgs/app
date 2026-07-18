<?php

namespace Tests\Feature\Platform;

use App\Enums\OfficeRole;
use App\Enums\PlatformRole;
use App\Enums\TenantPermission;
use App\Enums\TenantRole;
use App\Models\Office;
use App\Models\OfficeMembership;
use App\Models\PlatformMembership;
use App\Models\PlatformSetting;
use App\Models\TenantPermissionProfile;
use App\Models\User;
use App\Services\Platform\MultitenantRbacMigrateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Task 3.1 — dry-run/apply idempotente com paridade.
 */
class MultitenantRbacMigrateCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_nao_escreve_e_inventaria(): void
    {
        $office = Office::factory()->create();
        $admin = User::factory()->create();
        OfficeMembership::factory()->legacyOnly(OfficeRole::Admin)->create([
            'office_id' => $office->id,
            'user_id' => $admin->id,
        ]);
        PlatformMembership::factory()->create([
            'user_id' => User::factory()->create()->id,
        ]);

        $beforeProfiles = DB::table('tenant_permission_profiles')->count();

        // Dry-run sempre exit 0; blockers aparecem no relatório (pendência de primary).
        $this->artisan('app:multitenant-rbac:migrate', ['--dry-run' => true])
            ->assertSuccessful(); // dry-run reporta blockers mas não escreve

        $this->assertSame($beforeProfiles, DB::table('tenant_permission_profiles')->count());
        $this->assertNull(
            OfficeMembership::query()->where('office_id', $office->id)->value('tenant_role')
        );

        $report = app(MultitenantRbacMigrateService::class)->inventory(null);
        $this->assertTrue($report['blocked']);
    }

    public function test_apply_mapeia_papeis_e_e_idempotente(): void
    {
        $office = Office::factory()->create();
        $admin = User::factory()->create();
        $operator = User::factory()->create();
        $viewer = User::factory()->create();

        OfficeMembership::factory()->legacyOnly(OfficeRole::Admin)->create([
            'office_id' => $office->id,
            'user_id' => $admin->id,
        ]);
        OfficeMembership::factory()->legacyOnly(OfficeRole::Operator)->create([
            'office_id' => $office->id,
            'user_id' => $operator->id,
        ]);
        OfficeMembership::factory()->legacyOnly(OfficeRole::Viewer)->create([
            'office_id' => $office->id,
            'user_id' => $viewer->id,
        ]);
        PlatformMembership::factory()->create([
            'user_id' => User::factory()->create()->id,
        ]);

        $this->artisan('app:multitenant-rbac:migrate', [
            '--apply' => true,
            '--primary-office' => $office->id,
            '--confirm' => true,
        ])->assertSuccessful();

        $adminM = OfficeMembership::query()->where('user_id', $admin->id)->firstOrFail();
        $this->assertSame(TenantRole::TenantAdmin, $adminM->tenant_role);
        $this->assertNull($adminM->permission_profile_id);

        $opM = OfficeMembership::query()->where('user_id', $operator->id)->firstOrFail();
        $this->assertSame(TenantRole::TenantUser, $opM->tenant_role);
        $opProfile = TenantPermissionProfile::query()->findOrFail($opM->permission_profile_id);
        $this->assertSame(TenantPermissionProfile::SYSTEM_LEGACY_OPERATOR, $opProfile->key);
        $expectedOp = array_map(
            static fn (TenantPermission $p) => $p->value,
            TenantPermission::legacyOperatorSet()
        );
        sort($expectedOp, SORT_STRING);
        $this->assertSame($expectedOp, $opProfile->permissionKeys());

        $viewerM = OfficeMembership::query()->where('user_id', $viewer->id)->firstOrFail();
        $this->assertSame(TenantRole::TenantUser, $viewerM->tenant_role);
        $viewerProfile = TenantPermissionProfile::query()->findOrFail($viewerM->permission_profile_id);
        $this->assertSame(TenantPermissionProfile::SYSTEM_LEGACY_VIEWER, $viewerProfile->key);

        $pm = PlatformMembership::query()->firstOrFail();
        $this->assertSame(PlatformRole::PlatformAdmin, $pm->platform_role);
        $this->assertSame(
            PlatformRole::CANONICAL_PLATFORM_ADMIN,
            $pm->getRawOriginal('platform_role')
        );

        $this->assertSame(
            $office->id,
            (int) PlatformSetting::query()->whereKey(1)->value('primary_office_id')
        );

        $profilesBefore = DB::table('tenant_permission_profiles')->count();
        $permBefore = DB::table('tenant_permission_profile_permissions')->count();

        $this->artisan('app:multitenant-rbac:migrate', [
            '--apply' => true,
            '--primary-office' => $office->id,
            '--confirm' => true,
        ])->assertSuccessful();

        $this->assertSame($profilesBefore, DB::table('tenant_permission_profiles')->count());
        $this->assertSame($permBefore, DB::table('tenant_permission_profile_permissions')->count());
        $this->assertSame(3, OfficeMembership::query()->whereNotNull('tenant_role')->count());
    }

    public function test_papel_desconhecido_aborta(): void
    {
        $office = Office::factory()->create();
        $user = User::factory()->create();
        $admin = User::factory()->create();
        DB::table('office_user')->insert([
            'office_id' => $office->id,
            'user_id' => $user->id,
            'role' => 'SUPERUSER',
            'is_active' => true,
            'authorization_version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        OfficeMembership::factory()->legacyOnly(OfficeRole::Admin)->create([
            'office_id' => $office->id,
            'user_id' => $admin->id,
        ]);
        PlatformMembership::factory()->create([
            'user_id' => User::factory()->create()->id,
        ]);

        $this->artisan('app:multitenant-rbac:migrate', [
            '--apply' => true,
            '--primary-office' => $office->id,
            '--confirm' => true,
        ])->assertFailed();

        $this->assertNull(
            DB::table('office_user')->where('user_id', $user->id)->value('tenant_role')
        );
        $this->assertNull(
            DB::table('office_user')->where('user_id', $admin->id)->value('tenant_role')
        );
    }

    public function test_multiplos_tenants_exigem_primary_office(): void
    {
        $a = Office::factory()->create();
        $b = Office::factory()->create();
        foreach ([$a, $b] as $office) {
            OfficeMembership::factory()->legacyOnly(OfficeRole::Admin)->create([
                'office_id' => $office->id,
                'user_id' => User::factory()->create()->id,
            ]);
        }
        PlatformMembership::factory()->create([
            'user_id' => User::factory()->create()->id,
        ]);

        $this->artisan('app:multitenant-rbac:migrate', [
            '--apply' => true,
            '--confirm' => true,
        ])->assertFailed();

        $service = app(MultitenantRbacMigrateService::class);
        $report = $service->run(apply: false, primaryOfficeId: null, confirm: false);
        $this->assertTrue($report['blocked']);
        $this->assertNotEmpty($report['blockers']);
    }

    public function test_sem_platform_admin_bloqueia(): void
    {
        $office = Office::factory()->create();
        OfficeMembership::factory()->legacyOnly(OfficeRole::Admin)->create([
            'office_id' => $office->id,
            'user_id' => User::factory()->create()->id,
        ]);

        $this->artisan('app:multitenant-rbac:migrate', [
            '--apply' => true,
            '--primary-office' => $office->id,
            '--confirm' => true,
        ])->assertFailed();
    }
}
