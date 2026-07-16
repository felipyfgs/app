<?php

namespace Tests\Unit\Enums;

use App\Enums\OfficeRole;
use PHPUnit\Framework\TestCase;

/**
 * Congela a matriz ADMIN/OPERATOR/VIEWER do módulo operacional.
 */
class OfficeRoleWorkCapabilitiesTest extends TestCase
{
    public function test_matriz_operacional(): void
    {
        $admin = OfficeRole::Admin;
        $op = OfficeRole::Operator;
        $viewer = OfficeRole::Viewer;

        // Catálogo (departamentos/modelos)
        $this->assertTrue($admin->canManageWorkCatalog());
        $this->assertFalse($op->canManageWorkCatalog());
        $this->assertFalse($viewer->canManageWorkCatalog());

        // Criar processos
        $this->assertTrue($admin->canCreateWorkProcesses());
        $this->assertTrue($op->canCreateWorkProcesses());
        $this->assertFalse($viewer->canCreateWorkProcesses());

        // Executar tarefas
        $this->assertTrue($admin->canExecuteWorkTasks());
        $this->assertTrue($op->canExecuteWorkTasks());
        $this->assertFalse($viewer->canExecuteWorkTasks());

        // Administrar (lote, dispensa, reabrir)
        $this->assertTrue($admin->canAdministerWork());
        $this->assertFalse($op->canAdministerWork());
        $this->assertFalse($viewer->canAdministerWork());

        // Consulta
        $this->assertTrue($admin->canViewWork());
        $this->assertTrue($op->canViewWork());
        $this->assertTrue($viewer->canViewWork());

        // Evidências
        $this->assertTrue($admin->canDownloadWorkEvidence());
        $this->assertTrue($op->canDownloadWorkEvidence());
        $this->assertFalse($viewer->canDownloadWorkEvidence());

        // Export
        $this->assertTrue($admin->canExportWork());
        $this->assertTrue($op->canExportWork());
        $this->assertFalse($viewer->canExportWork());
    }
}
