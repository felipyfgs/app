<?php

namespace Tests\Unit\Models;

use App\Enums\OfficeRole;
use App\Enums\PlatformRole;
use App\Enums\TenantPermission;
use App\Enums\TenantRole;
use App\Models\Office;
use App\Models\OfficeMembership;
use App\Models\PlatformMembership;
use App\Models\TenantPermissionProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

class TenantPermissionProfileDomainTest extends TestCase
{
    use RefreshDatabase;

    public function test_perfil_sistema_imutavel(): void
    {
        $office = Office::factory()->create();
        $profile = TenantPermissionProfile::factory()->forOffice($office)->systemOperator()->create();

        $this->assertTrue($profile->is_system);
        $this->assertTrue($profile->has(TenantPermission::ClientsManage));
        $this->assertFalse($profile->has(TenantPermission::CredentialsManage));

        $this->expectException(RuntimeException::class);
        $profile->syncPermissionKeys([TenantPermission::ClientsView]);
    }

    public function test_perfil_cross_tenant_falha_invariante_membership(): void
    {
        $officeA = Office::factory()->create();
        $officeB = Office::factory()->create();
        $profileB = TenantPermissionProfile::factory()->forOffice($officeB)->systemViewer()->create();
        $user = User::factory()->create();

        $membership = new OfficeMembership([
            'office_id' => $officeA->id,
            'user_id' => $user->id,
            'role' => OfficeRole::Viewer,
            'tenant_role' => TenantRole::TenantUser,
            'permission_profile_id' => $profileB->id,
            'is_active' => true,
            'authorization_version' => 1,
        ]);
        $membership->setRelation('permissionProfile', $profileB);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('outro tenant');
        $membership->assertCanonicalInvariants();
    }

    public function test_tenant_user_com_perfil_inativo_falha(): void
    {
        $office = Office::factory()->create();
        $profile = TenantPermissionProfile::factory()->forOffice($office)->systemViewer()->inactive()->create();
        $user = User::factory()->create();

        $membership = new OfficeMembership([
            'office_id' => $office->id,
            'user_id' => $user->id,
            'role' => OfficeRole::Viewer,
            'tenant_role' => TenantRole::TenantUser,
            'permission_profile_id' => $profile->id,
            'is_active' => true,
            'authorization_version' => 1,
        ]);
        $membership->setRelation('permissionProfile', $profile);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('inativo');
        $membership->assertCanonicalInvariants();
    }

    public function test_tenant_admin_com_perfil_falha(): void
    {
        $office = Office::factory()->create();
        $profile = TenantPermissionProfile::factory()->forOffice($office)->systemOperator()->create();

        $membership = new OfficeMembership([
            'office_id' => $office->id,
            'user_id' => User::factory()->create()->id,
            'role' => OfficeRole::Admin,
            'tenant_role' => TenantRole::TenantAdmin,
            'permission_profile_id' => $profile->id,
            'is_active' => true,
            'authorization_version' => 1,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $membership->assertCanonicalInvariants();
    }

    public function test_version_bump_monotonico(): void
    {
        $office = Office::factory()->create();
        $profile = TenantPermissionProfile::factory()->forOffice($office)->create([
            'authorization_version' => 3,
        ]);

        $profile->syncPermissionKeys([TenantPermission::ClientsView, TenantPermission::WorkView]);
        $this->assertSame(4, (int) $profile->fresh()->authorization_version);
        $this->assertSame(
            ['clients.view', 'work.view'],
            $profile->fresh()->permissionKeys()
        );
    }

    public function test_leitura_de_linhas_legadas_e_canônicas(): void
    {
        $office = Office::factory()->create();
        $user = User::factory()->create();

        $legacy = OfficeMembership::factory()->legacyOnly(OfficeRole::Admin)->create([
            'office_id' => $office->id,
            'user_id' => $user->id,
        ]);
        $this->assertNull($legacy->fresh()->tenant_role);
        $this->assertSame(OfficeRole::Admin, $legacy->fresh()->role);
        $this->assertSame(TenantRole::TenantAdmin, $legacy->fresh()->resolvedTenantRole());

        $canonical = OfficeMembership::factory()->tenantAdmin()->create([
            'office_id' => $office->id,
            'user_id' => User::factory()->create()->id,
        ]);
        $this->assertSame(TenantRole::TenantAdmin, $canonical->fresh()->tenant_role);
        $this->assertNull($canonical->fresh()->permission_profile_id);
        $canonical->assertCanonicalInvariants();
    }

    public function test_platform_role_cast_le_legado_e_canonico(): void
    {
        $user = User::factory()->create();
        $m = PlatformMembership::factory()->create([
            'user_id' => $user->id,
            'platform_role' => null,
        ]);
        $this->assertTrue($m->fresh()->isPlatformAdmin());
        $this->assertSame('PLATFORM_ADMIN', $m->fresh()->getRawOriginal('role'));

        // Atualiza a mesma membership (singleton) com dual-write canônico.
        $m->platform_role = PlatformRole::PlatformAdmin;
        $m->save();
        $this->assertSame(
            PlatformRole::CANONICAL_PLATFORM_ADMIN,
            $m->fresh()->getRawOriginal('platform_role')
        );
        $this->assertSame(
            PlatformRole::PlatformAdmin,
            $m->fresh()->platform_role
        );
        $this->assertTrue($m->fresh()->isPlatformAdmin());
    }
}
