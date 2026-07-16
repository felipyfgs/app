<?php

namespace Tests\Architecture;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Garante que a trilha platform_privileged_audit_events não vaze para
 * controllers tenant-facing (fora de Platform/* e services de plataforma).
 */
class PlatformPrivilegedAuditIsolationTest extends TestCase
{
    #[Test]
    public function controllers_tenant_nao_consultam_trilha_privilegiada(): void
    {
        $controllersRoot = dirname(__DIR__, 2).'/app/Http/Controllers/Api/V1';
        $forbidden = [
            'PlatformPrivilegedAuditEvent',
            'platform_privileged_audit_events',
        ];

        $violations = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($controllersRoot, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $path = $file->getPathname();
            // Área da plataforma e seletor privilegiado podem usar a trilha.
            if (str_contains($path, '/Platform/')) {
                continue;
            }

            $contents = (string) file_get_contents($path);
            foreach ($forbidden as $needle) {
                if (str_contains($contents, $needle)) {
                    $violations[] = str_replace(dirname(__DIR__, 2).'/', '', $path).' → '.$needle;
                }
            }
        }

        // OfficeFiscalCredentialController grava via PlatformPrivilegedAuditor (ok),
        // mas não deve ler/expor a tabela diretamente.
        $this->assertSame(
            [],
            array_values(array_filter(
                $violations,
                fn (string $v) => ! str_contains($v, 'PlatformPrivilegedAuditor')
                    && ! str_contains($v, 'OfficeFiscalCredentialController')
            )),
            "Controllers tenant não devem referenciar a trilha privilegiada:\n".implode("\n", $violations)
        );
    }

    #[Test]
    public function export_controller_nao_referencia_auditoria_privilegiada(): void
    {
        $path = dirname(__DIR__, 2).'/app/Http/Controllers/Api/V1/ExportController.php';
        $this->assertFileExists($path);
        $contents = (string) file_get_contents($path);
        $this->assertStringNotContainsString('PlatformPrivilegedAuditEvent', $contents);
        $this->assertStringNotContainsString('platform_privileged', $contents);
    }
}
