<?php

namespace Tests\Architecture;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Inventário estático: tenancy BelongsToOffice, vault 26, CNPJ canônico.
 * Fail em desvios novos; allowlist só para legados documentados.
 *
 * @see docs/ops/schema-conventions.md
 * @see config/schema_conventions.php
 */
class SchemaConventionsTest extends TestCase
{
    private string $appRoot;

    /** @var array<string, mixed> */
    private array $conventions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->appRoot = dirname(__DIR__, 2);
        $this->conventions = require $this->appRoot.'/config/schema_conventions.php';
    }

    #[Test]
    public function models_tenant_com_office_id_usam_belongs_to_office(): void
    {
        $exceptions = array_flip($this->conventions['belongs_to_office_exceptions']);
        $notTenant = array_flip($this->conventions['office_id_not_tenant_column']);
        $violations = [];

        $modelsRoot = $this->appRoot.'/app/Models';
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($modelsRoot, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            if (str_contains($file->getPathname(), '/Concerns/')) {
                continue;
            }

            $class = $file->getBasename('.php');
            $src = (string) file_get_contents($file->getPathname());

            if (! $this->modelDeclaresOfficeIdColumn($src)) {
                continue;
            }

            if (isset($notTenant[$class])) {
                continue;
            }

            $hasTrait = $this->usesBelongsToOfficeTrait($src);
            if ($hasTrait) {
                continue;
            }

            if (isset($exceptions[$class])) {
                continue;
            }

            $violations[] = $class;
        }

        $this->assertSame(
            [],
            $violations,
            "Models de tenant com office_id devem usar BelongsToOffice (ou allowlist):\n"
            .implode("\n", $violations)
        );
    }

    #[Test]
    public function exceptions_allowlist_only_lists_existing_models_without_trait(): void
    {
        $missing = [];
        $stillHasTrait = [];

        foreach ($this->conventions['belongs_to_office_exceptions'] as $class) {
            $path = $this->appRoot.'/app/Models/'.$class.'.php';
            if (! is_file($path)) {
                $missing[] = $class;

                continue;
            }
            $src = (string) file_get_contents($path);
            if ($this->usesBelongsToOfficeTrait($src)) {
                $stillHasTrait[] = $class;
            }
        }

        $this->assertSame([], $missing, 'Allowlist referencia model inexistente: '.implode(', ', $missing));
        $this->assertSame(
            [],
            $stillHasTrait,
            'Remover da allowlist models que já usam BelongsToOffice: '.implode(', ', $stillHasTrait)
        );
    }

    #[Test]
    public function migrations_vault_object_id_length_is_26_or_allowlisted(): void
    {
        $canonical = (int) $this->conventions['canonical']['vault_object_id_length'];
        $allowlist = $this->conventions['vault_length_allowlist'];
        $violations = [];

        foreach ($this->migrationStringColumns() as $row) {
            [$file, $column, $length] = $row;
            if (! str_contains(strtolower($column), 'vault_object_id')) {
                continue;
            }
            if ($length === $canonical) {
                continue;
            }
            $key = $file.':'.$column;
            if (isset($allowlist[$key]) && (int) $allowlist[$key] === $length) {
                continue;
            }
            $violations[] = "{$key} length={$length} (esperado {$canonical} ou allowlist)";
        }

        $this->assertSame(
            [],
            $violations,
            "Colunas *vault_object_id* devem ser string(26):\n".implode("\n", $violations)
        );
    }

    #[Test]
    public function migrations_cnpj_length_is_canonical_or_allowlisted(): void
    {
        $canonical = (int) $this->conventions['canonical']['cnpj_length'];
        $allowlist = $this->conventions['cnpj_length_allowlist'];
        $violations = [];

        foreach ($this->migrationStringColumns() as $row) {
            [$file, $column, $length] = $row;
            $low = strtolower($column);
            $isCnpj = $low === 'cnpj'
                || str_ends_with($low, '_cnpj')
                || $low === 'root_cnpj';
            if (! $isCnpj) {
                continue;
            }
            if ($length === $canonical) {
                continue;
            }
            $key = $file.':'.$column;
            if (isset($allowlist[$key]) && (int) $allowlist[$key] === $length) {
                continue;
            }
            $violations[] = "{$key} length={$length} (esperado {$canonical} ou allowlist raiz/alfa)";
        }

        $this->assertSame(
            [],
            $violations,
            "Colunas *cnpj* fora do canônico:\n".implode("\n", $violations)
        );
    }

    #[Test]
    public function new_environment_columns_prefer_length_20_or_allowlisted(): void
    {
        $canonical = (int) $this->conventions['canonical']['environment_length'];
        $allowlist = $this->conventions['environment_length_allowlist'];
        $violations = [];

        foreach ($this->migrationStringColumns() as $row) {
            [$file, $column, $length] = $row;
            if (strtolower($column) !== 'environment') {
                continue;
            }
            if ($length === $canonical) {
                continue;
            }
            $key = $file.':'.$column;
            if (isset($allowlist[$key]) && (int) $allowlist[$key] === $length) {
                continue;
            }
            // length > canonical (ex.: 32) em arquivo allowlisted parcialmente: exigir allowlist
            $violations[] = "{$key} length={$length} (canônico {$canonical} ou allowlist)";
        }

        $this->assertSame(
            [],
            $violations,
            "Colunas environment fora do canônico/allowlist:\n".implode("\n", $violations)
        );
    }

    private function usesBelongsToOfficeTrait(string $src): bool
    {
        return (bool) preg_match(
            '/\buse\s+(?:\\\\?App\\\\Models\\\\Concerns\\\\)?BelongsToOffice\b/',
            $src
        );
    }

    /**
     * Heurística: office_id em fillable / Fillable attribute / $casts.
     */
    private function modelDeclaresOfficeIdColumn(string $src): bool
    {
        if (preg_match("/['\"]office_id['\"]/", $src) !== 1) {
            return false;
        }

        // Fillable attribute or property
        if (preg_match('/#\[Fillable\s*\([^\]]*office_id/s', $src) === 1) {
            return true;
        }
        if (preg_match('/\$fillable\s*=\s*\[[^\]]*office_id/s', $src) === 1) {
            return true;
        }

        // casts / guarded patterns
        if (preg_match('/\$casts[^;]*office_id/s', $src) === 1) {
            return true;
        }

        return preg_match("/['\"]office_id['\"]/", $src) === 1
            && preg_match('/function\s+office\s*\(/', $src) === 1;
    }

    /**
     * @return list<array{0: string, 1: string, 2: int}>
     */
    private function migrationStringColumns(): array
    {
        $dir = $this->appRoot.'/database/migrations';
        $this->assertDirectoryExists($dir);

        $rows = [];
        $pattern = '/\$table->(?:string|char)\(\s*[\'"]([^\'"]+)[\'"]\s*(?:,\s*(\d+))?\s*\)/';

        foreach (glob($dir.'/*.php') ?: [] as $path) {
            $file = basename($path);
            $src = (string) file_get_contents($path);
            if (preg_match_all($pattern, $src, $matches, PREG_SET_ORDER) === false) {
                continue;
            }
            foreach ($matches as $m) {
                $column = $m[1];
                $length = isset($m[2]) && $m[2] !== '' ? (int) $m[2] : 255;
                $rows[] = [$file, $column, $length];
            }
        }

        return $rows;
    }
}
