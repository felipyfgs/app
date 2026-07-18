<?php

namespace Tests\Unit\Enums;

use App\Enums\TenantPermission;
use PHPUnit\Framework\TestCase;

/**
 * Congela o catálogo TenantPermission (chave, módulo, risco, delegable).
 */
class TenantPermissionCatalogTest extends TestCase
{
    public function test_chaves_estaveis_e_unicas(): void
    {
        $values = array_map(static fn (TenantPermission $p) => $p->value, TenantPermission::cases());
        $this->assertSame($values, array_values(array_unique($values)));
        $this->assertSame(TenantPermission::orderedValues(), $sorted = [...TenantPermission::orderedValues()]);
        sort($sorted);
        $this->assertSame($sorted, TenantPermission::orderedValues());
    }

    public function test_metadata_obrigatoria_por_chave(): void
    {
        foreach (TenantPermission::cases() as $permission) {
            $this->assertNotSame('', $permission->label());
            $this->assertNotSame('', $permission->module());
            $this->assertContains($permission->risk(), ['low', 'medium', 'high']);
            $this->assertIsBool($permission->isDelegable());
            $this->assertTrue($permission->isActive());
            $this->assertMatchesRegularExpression(
                '/^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)+$/',
                $permission->value,
                "chave inválida: {$permission->value}"
            );
        }
    }

    public function test_reservadas_a_tenant_admin_nao_sao_delegaveis(): void
    {
        $this->assertFalse(TenantPermission::TenantPermissionProfilesManage->isDelegable());
        $this->assertFalse(TenantPermission::TenantRolesAssignAdmin->isDelegable());
        $this->assertTrue(TenantPermission::ClientsManage->isDelegable());
        $this->assertTrue(TenantPermission::TenantUsersCreate->isDelegable());
    }

    public function test_nenhuma_chave_platform(): void
    {
        foreach (TenantPermission::cases() as $permission) {
            $this->assertStringStartsNotWith('platform.', $permission->value);
        }
    }

    public function test_perfis_sistema_sao_subconjuntos_disjuntos_onde_aplicavel(): void
    {
        $op = array_map(static fn (TenantPermission $p) => $p->value, TenantPermission::legacyOperatorSet());
        $viewer = array_map(static fn (TenantPermission $p) => $p->value, TenantPermission::legacyViewerSet());

        $this->assertSame($op, array_values(array_unique($op)));
        $this->assertSame($viewer, array_values(array_unique($viewer)));

        foreach ($viewer as $key) {
            $this->assertContains(
                $key,
                $op,
                "legacy-viewer deve ser subconjunto de leitura de legacy-operator: {$key}"
            );
        }

        $this->assertNotContains(TenantPermission::ClientsManage->value, $viewer);
        $this->assertContains(TenantPermission::ClientsManage->value, $op);
        $this->assertNotContains(TenantPermission::CredentialsManage->value, $op);
        $this->assertNotContains(TenantPermission::FiscalMutationsExecute->value, $op);
        $this->assertNotContains(TenantPermission::WorkCatalogManage->value, $op);
        $this->assertNotContains(TenantPermission::WorkAdminister->value, $op);
    }

    public function test_catalogo_minimo_do_design_presente(): void
    {
        $required = [
            'tenant.dashboard.view',
            'tenant.settings.view',
            'tenant.settings.manage',
            'tenant.users.view',
            'tenant.users.create',
            'tenant.users.manage',
            'tenant.modules.manage',
            'tenant.permission_profiles.manage',
            'tenant.roles.assign_admin',
            'clients.view',
            'clients.manage',
            'credentials.status.view',
            'credentials.manage',
            'fiscal.documents.view',
            'fiscal.monitoring.view',
            'fiscal.sync.trigger',
            'fiscal.nfe.manifest',
            'documents.import',
            'exports.create',
            'filters.share',
            'fiscal.mutations.execute',
            'operations.view',
            'operations.triage',
            'work.view',
            'work.catalog.manage',
            'work.processes.create',
            'work.tasks.execute',
            'work.administer',
            'work.evidence.download',
            'work.exports.create',
        ];

        $values = array_map(static fn (TenantPermission $p) => $p->value, TenantPermission::cases());
        foreach ($required as $key) {
            $this->assertContains($key, $values, "chave do design ausente: {$key}");
        }
    }
}
