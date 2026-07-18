<?php

namespace Tests\Feature\Platform;

use App\Enums\OfficeRole;
use App\Enums\PlatformRole;
use App\Enums\TenantPermission;
use App\Enums\TenantRole;
use App\Models\Office;
use App\Models\OfficeMembership;
use App\Models\PlatformMembership;
use App\Models\TenantPermissionProfile;
use App\Models\User;
use App\Support\CurrentOffice;
use App\Support\FiscalDataModel\PrivilegedOfficeContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Task 3.2 — contrato HTTP aditivo de /me sem mudar autoridade.
 */
class MeCanonicalContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_me_tenant_user_retorna_campos_canonicos_e_aliases(): void
    {
        $office = Office::factory()->create();
        $user = User::factory()->create();
        $profile = PrivilegedOfficeContext::run('test', fn () => TenantPermissionProfile::factory()
            ->forOffice($office)
            ->systemViewer()
            ->create());

        $membership = OfficeMembership::factory()->tenantUser($profile)->create([
            'user_id' => $user->id,
        ]);

        $this->actingAs($user);
        app(CurrentOffice::class)->bind($user, $membership);

        $response = $this->getJson('/api/v1/me')->assertOk();

        $response->assertJsonPath('data.tenant_role', 'tenant_user');
        $response->assertJsonPath('data.real_tenant_role', 'tenant_user');
        $response->assertJsonPath('data.platform_role', null);
        $response->assertJsonPath('data.role', 'VIEWER');
        $response->assertJsonPath('data.real_office_role', 'VIEWER');
        $response->assertJsonPath('data.is_platform_admin', false);
        $response->assertJsonPath('data.access_mode', 'membership');
        $response->assertJsonPath('data.permission_profile.key', TenantPermissionProfile::SYSTEM_LEGACY_VIEWER);
        $response->assertJsonPath('data.current_office.id', $office->id);
        $response->assertJsonPath('data.office.id', $office->id);

        $perms = $response->json('data.effective_permissions');
        $this->assertIsArray($perms);
        $this->assertContains(TenantPermission::ClientsView->value, $perms);
        $this->assertNotContains(TenantPermission::ClientsManage->value, $perms);
        $sorted = $perms;
        sort($sorted, SORT_STRING);
        $this->assertSame($sorted, $perms);
    }

    public function test_me_tenant_admin_lista_todas_permissoes_ordenadas(): void
    {
        $office = Office::factory()->create();
        $user = User::factory()->create();
        $membership = OfficeMembership::factory()->tenantAdmin()->create([
            'office_id' => $office->id,
            'user_id' => $user->id,
        ]);

        $this->actingAs($user);
        app(CurrentOffice::class)->bind($user, $membership);

        $response = $this->getJson('/api/v1/me')->assertOk();
        $response->assertJsonPath('data.tenant_role', 'tenant_admin');
        $response->assertJsonPath('data.role', 'ADMIN');
        $response->assertJsonPath('data.permission_profile', null);

        $this->assertSame(
            TenantPermission::orderedValues(),
            $response->json('data.effective_permissions')
        );
    }

    public function test_me_platform_sem_contexto_tem_permissoes_vazias(): void
    {
        $user = User::factory()->create();
        PlatformMembership::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user);
        app(CurrentOffice::class)->clear();

        $response = $this->getJson('/api/v1/me')->assertOk();
        $response->assertJsonPath('data.platform_role', PlatformRole::CANONICAL_PLATFORM_ADMIN);
        $response->assertJsonPath('data.is_platform_admin', true);
        $response->assertJsonPath('data.tenant_role', null);
        $response->assertJsonPath('data.effective_permissions', []);
        $response->assertJsonPath('data.current_office', null);
        $response->assertJsonPath('data.office', null);
    }

    public function test_me_legado_sem_backfill_deriva_tenant_role(): void
    {
        $office = Office::factory()->create();
        $user = User::factory()->create();
        $membership = OfficeMembership::factory()->legacyOnly(OfficeRole::Operator)->create([
            'office_id' => $office->id,
            'user_id' => $user->id,
        ]);

        $this->actingAs($user);
        app(CurrentOffice::class)->bind($user, $membership);

        $response = $this->getJson('/api/v1/me')->assertOk();
        $response->assertJsonPath('data.tenant_role', TenantRole::TenantUser->value);
        $response->assertJsonPath('data.role', 'OPERATOR');
        $this->assertContains(
            TenantPermission::ClientsManage->value,
            $response->json('data.effective_permissions')
        );
    }

    public function test_me_flag_off_effective_permissions_segue_sombra_legada(): void
    {
        config([
            'features.canonical_multitenant_rbac.enabled' => false,
            'features.kill_switch' => false,
        ]);

        $office = Office::factory()->create();
        $user = User::factory()->create();
        $profile = PrivilegedOfficeContext::run('test', fn () => TenantPermissionProfile::factory()
            ->forOffice($office)
            ->create());
        PrivilegedOfficeContext::run('test', function () use ($profile): void {
            $profile->syncPermissionKeys([
                TenantPermission::ExportsCreate,
                TenantPermission::ClientsView,
            ]);
        });

        // Perfil canônico tem exports; sombra legada de custom = VIEWER (sem exports).
        $membership = OfficeMembership::factory()->tenantUser($profile)->create([
            'user_id' => $user->id,
            'role' => OfficeRole::Viewer,
        ]);

        $this->actingAs($user);
        app(CurrentOffice::class)->bind($user, $membership);

        $response = $this->getJson('/api/v1/me')->assertOk();
        $perms = $response->json('data.effective_permissions');

        $this->assertContains(TenantPermission::ClientsView->value, $perms);
        $this->assertNotContains(
            TenantPermission::ExportsCreate->value,
            $perms,
            'Com flag OFF, effective_permissions deve espelhar sombra VIEWER, não o perfil custom.'
        );
    }
}
