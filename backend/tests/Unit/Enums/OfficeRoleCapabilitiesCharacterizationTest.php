<?php

namespace Tests\Unit\Enums;

use App\Enums\OfficeRole;
use App\Enums\TenantPermission;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Congela a matriz legada ADMIN/OPERATOR/VIEWER dos 15 métodos OfficeRole::can*.
 * Baseline W0 — não altera comportamento; serve de oráculo de paridade na migração.
 *
 * @see TenantPermission::officeRoleMethodMap()
 */
class OfficeRoleCapabilitiesCharacterizationTest extends TestCase
{
    /**
     * Matriz canônica congelada: method => [admin, operator, viewer].
     *
     * @return array<string, array{0: bool, 1: bool, 2: bool}>
     */
    public static function capabilityMatrix(): array
    {
        return [
            'canManageClients' => [true, true, false],
            'canManageCredentials' => [true, false, false],
            'canTriggerSync' => [true, true, false],
            'canManifestNfe' => [true, true, false],
            'canExport' => [true, true, false],
            'canShareListFilters' => [true, true, false],
            'canImportDocuments' => [true, true, false],
            'canMutateFiscal' => [true, false, false],
            'canManageWorkCatalog' => [true, false, false],
            'canCreateWorkProcesses' => [true, true, false],
            'canExecuteWorkTasks' => [true, true, false],
            'canAdministerWork' => [true, false, false],
            'canViewWork' => [true, true, true],
            'canDownloadWorkEvidence' => [true, true, false],
            'canExportWork' => [true, true, false],
        ];
    }

    public function test_matriz_completa_congela_quinze_metodos(): void
    {
        $matrix = self::capabilityMatrix();
        $this->assertCount(15, $matrix);

        $admin = OfficeRole::Admin;
        $operator = OfficeRole::Operator;
        $viewer = OfficeRole::Viewer;

        foreach ($matrix as $method => [$expectAdmin, $expectOp, $expectViewer]) {
            $this->assertTrue(method_exists(OfficeRole::class, $method), "método ausente: {$method}");
            $this->assertSame($expectAdmin, $admin->{$method}(), "ADMIN::{$method}");
            $this->assertSame($expectOp, $operator->{$method}(), "OPERATOR::{$method}");
            $this->assertSame($expectViewer, $viewer->{$method}(), "VIEWER::{$method}");
        }
    }

    public function test_valores_de_storage_legado(): void
    {
        $this->assertSame('ADMIN', OfficeRole::Admin->value);
        $this->assertSame('OPERATOR', OfficeRole::Operator->value);
        $this->assertSame('VIEWER', OfficeRole::Viewer->value);
        $this->assertTrue(OfficeRole::Admin->isAdmin());
        $this->assertFalse(OfficeRole::Operator->isAdmin());
        $this->assertFalse(OfficeRole::Viewer->isAdmin());
    }

    public function test_mapeamento_para_tenant_permission_cobre_os_quinze_metodos(): void
    {
        $map = TenantPermission::officeRoleMethodMap();
        $this->assertCount(15, $map);
        foreach (array_keys(self::capabilityMatrix()) as $method) {
            $this->assertArrayHasKey($method, $map, "método sem chave canônica: {$method}");
            $this->assertInstanceOf(TenantPermission::class, $map[$method]);
        }
    }

    #[DataProvider('legacyParityProvider')]
    public function test_paridade_perfil_sistema_com_matriz_can(string $method, bool $operator, bool $viewer): void
    {
        $permission = TenantPermission::officeRoleMethodMap()[$method];
        $opKeys = array_map(static fn (TenantPermission $p) => $p->value, TenantPermission::legacyOperatorSet());
        $viewerKeys = array_map(static fn (TenantPermission $p) => $p->value, TenantPermission::legacyViewerSet());

        $this->assertSame(
            $operator,
            in_array($permission->value, $opKeys, true),
            'legacy-operator deve '.($operator ? 'incluir' : 'excluir')." {$permission->value} ({$method})"
        );
        $this->assertSame(
            $viewer,
            in_array($permission->value, $viewerKeys, true),
            'legacy-viewer deve '.($viewer ? 'incluir' : 'excluir')." {$permission->value} ({$method})"
        );
    }

    /**
     * @return iterable<string, array{0: string, 1: bool, 2: bool}>
     */
    public static function legacyParityProvider(): iterable
    {
        foreach (self::capabilityMatrix() as $method => [, $op, $viewer]) {
            yield $method => [$method, $op, $viewer];
        }
    }
}
