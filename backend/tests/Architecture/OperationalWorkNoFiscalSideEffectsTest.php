<?php

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

/**
 * Controllers e jobs do módulo operacional NÃO podem chamar SERPRO/ADN/SEFAZ
 * nem escrever cursores NSU/nNF.
 */
class OperationalWorkNoFiscalSideEffectsTest extends TestCase
{
    /** @var list<string> */
    private const SCAN_GLOBS = [
        'Http/Controllers/Api/V1/Work',
        'Jobs/Work',
        'Services/Work',
        'Domain/Work',
    ];

    /** @var list<string> */
    private const FORBIDDEN = [
        '/\buse\s+App\\\\Contracts\\\\IntegraContadorClient\b/',
        '/\buse\s+App\\\\Contracts\\\\AdnContributorClient\b/',
        '/\buse\s+App\\\\Contracts\\\\SefazDistDfeClient\b/',
        '/\buse\s+App\\\\Contracts\\\\SefazCteDistDfeClient\b/',
        '/\buse\s+App\\\\Services\\\\Serpro\\\\/',
        '/\buse\s+App\\\\Services\\\\Adn\\\\/',
        '/\buse\s+App\\\\Services\\\\Sefaz\\\\/',
        '/\buse\s+App\\\\Services\\\\Integra\\\\/',
        '/\bIntegraContadorClient\b/',
        '/\bAdnContributorClient\b/',
        '/\bSefazDistDfeClient\b/',
        '/\blast_nsu\b/i',
        '/\bnext_nsu\b/i',
        '/\bcurrent_nNF\b/',
        '/\bSyncCursor\b/',
        '/\bChannelSyncCursor\b/',
        '/gateway\.apiserpro\.serpro\.gov\.br/i',
        '/nfe\.fazenda\.gov\.br/i',
    ];

    public function test_modulo_operacional_nao_toca_canais_fiscais(): void
    {
        $appRoot = dirname(__DIR__, 2).'/app';
        $hits = [];

        foreach (self::SCAN_GLOBS as $rel) {
            $dir = $appRoot.'/'.$rel;
            if (! is_dir($dir)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
            foreach ($iterator as $file) {
                if (! $file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }
                $content = file_get_contents($file->getPathname());
                if ($content === false) {
                    continue;
                }
                foreach (self::FORBIDDEN as $pattern) {
                    if (preg_match($pattern, $content)) {
                        $hits[] = substr($file->getPathname(), strlen($appRoot) + 1).' ≈ '.$pattern;
                    }
                }
            }
        }

        $this->assertSame([], $hits, "Módulo Work não pode chamar SERPRO/ADN/SEFAZ nem escrever NSU/nNF:\n".implode("\n", $hits));
    }

    public function test_nenhuma_tabela_global_de_processos(): void
    {
        $migrations = dirname(__DIR__, 2).'/database/migrations';
        $this->assertDirectoryExists($migrations);

        $iterator = new \DirectoryIterator($migrations);
        foreach ($iterator as $file) {
            if (! $file->isFile() || ! str_contains($file->getFilename(), 'operational')
                && ! str_contains($file->getFilename(), 'work_')
                && ! str_contains($file->getFilename(), 'process_')) {
                continue;
            }
            $content = (string) file_get_contents($file->getPathname());
            if (! str_contains($content, 'work_departments')
                && ! str_contains($content, 'process_templates')
                && ! str_contains($content, 'operational_processes')
                && ! str_contains($content, 'operational_tasks')
                && ! str_contains($content, 'operational_exports')
                && ! str_contains($content, 'process_generation')) {
                continue;
            }

            // Toda tabela operacional criada nesta change deve ter office_id
            if (preg_match_all("/Schema::create\\('([^']+)'/", $content, $m)) {
                foreach ($m[1] as $table) {
                    if (in_array($table, ['migrations'], true)) {
                        continue;
                    }
                    $this->assertStringContainsString(
                        'office_id',
                        $content,
                        "Tabela {$table} deve ter office_id (plano de dados).",
                    );
                }
            }
        }
    }
}
