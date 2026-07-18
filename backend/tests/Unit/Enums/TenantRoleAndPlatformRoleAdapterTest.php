<?php

namespace Tests\Unit\Enums;

use App\Enums\OfficeRole;
use App\Enums\PlatformRole;
use App\Enums\TenantRole;
use PHPUnit\Framework\TestCase;

class TenantRoleAndPlatformRoleAdapterTest extends TestCase
{
    public function test_tenant_role_valores_canonicos(): void
    {
        $this->assertSame('tenant_admin', TenantRole::TenantAdmin->value);
        $this->assertSame('tenant_user', TenantRole::TenantUser->value);
        $this->assertTrue(TenantRole::TenantAdmin->isAdmin());
        $this->assertFalse(TenantRole::TenantUser->isAdmin());
        $this->assertTrue(TenantRole::TenantUser->requiresPermissionProfile());
        $this->assertFalse(TenantRole::TenantAdmin->requiresPermissionProfile());
    }

    public function test_mapeamento_legado_e_sombra(): void
    {
        $this->assertSame(TenantRole::TenantAdmin, TenantRole::tryFromLegacyOfficeRole(OfficeRole::Admin));
        $this->assertSame(TenantRole::TenantUser, TenantRole::tryFromLegacyOfficeRole(OfficeRole::Operator));
        $this->assertSame(TenantRole::TenantUser, TenantRole::tryFromLegacyOfficeRole(OfficeRole::Viewer));

        $this->assertSame(OfficeRole::Admin, TenantRole::TenantAdmin->legacyOfficeRoleShadow());
        $this->assertSame(OfficeRole::Operator, TenantRole::TenantUser->legacyOfficeRoleShadow('legacy-operator'));
        $this->assertSame(OfficeRole::Viewer, TenantRole::TenantUser->legacyOfficeRoleShadow('legacy-viewer'));
        $this->assertSame(OfficeRole::Viewer, TenantRole::TenantUser->legacyOfficeRoleShadow('custom-x'));
    }

    public function test_platform_role_adapter_legado_e_canonico(): void
    {
        $this->assertSame('PLATFORM_ADMIN', PlatformRole::PlatformAdmin->value);
        $this->assertSame('platform_admin', PlatformRole::PlatformAdmin->canonicalValue());
        $this->assertSame('PLATFORM_ADMIN', PlatformRole::PlatformAdmin->legacyStorageValue());

        $this->assertSame(PlatformRole::PlatformAdmin, PlatformRole::tryFromStorage('PLATFORM_ADMIN'));
        $this->assertSame(PlatformRole::PlatformAdmin, PlatformRole::tryFromStorage('platform_admin'));
        $this->assertNull(PlatformRole::tryFromStorage('tenant_admin'));
        $this->assertNull(PlatformRole::tryFromStorage(null));
    }
}
