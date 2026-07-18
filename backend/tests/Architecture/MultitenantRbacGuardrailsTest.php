<?php

namespace Tests\Architecture;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Guardrails W0 da migração RBAC multi-tenant:
 * - proíbe Gate::before irrestrito;
 * - proíbe novos literais de autenticação legados fora da allowlist temporária.
 *
 * @see config/multitenant_rbac.php
 * @see docs/ops/multitenant-rbac-inventory.md
 */
class MultitenantRbacGuardrailsTest extends TestCase
{
    private string $appRoot;

    /** @var list<string> */
    private array $allowlist;

    protected function setUp(): void
    {
        parent::setUp();
        $this->appRoot = dirname(__DIR__, 2);
        $config = require $this->appRoot.'/config/multitenant_rbac.php';
        $this->allowlist = array_values($config['legacy_auth_literal_allowlist'] ?? []);
    }

    #[Test]
    public function nao_permite_gate_before_irrestrito(): void
    {
        $violations = [];

        foreach ($this->phpFilesUnder($this->appRoot.'/app') as $rel => $src) {
            if ($this->containsUnrestrictedGateBefore($src)) {
                $violations[] = $rel;
            }
        }

        $this->assertSame(
            [],
            $violations,
            "Gate::before irrestrito é proibido (TAG-10). Encontrado em:\n".implode("\n", $violations)
        );
    }

    #[Test]
    public function detector_de_gate_before_acusa_fixture_proposital(): void
    {
        $fixture = <<<'PHP'
        <?php
        use Illuminate\Support\Facades\Gate;
        Gate::before(function ($user, $ability) {
            if ($user->isPlatformAdmin()) {
                return true;
            }
        });
        PHP;

        $this->assertTrue(
            $this->containsUnrestrictedGateBefore($fixture),
            'Detector deve acusar bypass global irrestrito para platform admin.'
        );

        $safe = <<<'PHP'
        <?php
        use Illuminate\Support\Facades\Gate;
        Gate::define('platform-admin', fn ($user) => $user->isPlatformAdmin());
        PHP;

        $this->assertFalse(
            $this->containsUnrestrictedGateBefore($safe),
            'Gate::define pontual não é Gate::before irrestrito.'
        );
    }

    #[Test]
    public function literais_legados_somente_na_allowlist(): void
    {
        $allow = array_flip($this->allowlist);
        $violations = [];

        foreach ($this->phpFilesUnder($this->appRoot.'/app') as $rel => $src) {
            if (! $this->containsLegacyAuthLiteral($src)) {
                continue;
            }
            if (isset($allow[$rel])) {
                continue;
            }
            $violations[] = $rel;
        }

        $this->assertSame(
            [],
            $violations,
            "Literais de autenticação legados fora da allowlist temporária (TAG-15):\n"
            .implode("\n", $violations)
            ."\n\nRemova o literal ou, se ainda for transição documentada, adicione o path em "
            .'config/multitenant_rbac.php → legacy_auth_literal_allowlist.'
        );
    }

    #[Test]
    public function detector_de_literal_legado_acusa_fixture_proposital(): void
    {
        $fixture = <<<'PHP'
        <?php
        namespace App\Http\Controllers\Api\V1;
        use App\Enums\OfficeRole;
        if ($role === OfficeRole::Admin) {
            return true;
        }
        PHP;

        $this->assertTrue(
            $this->containsLegacyAuthLiteral($fixture),
            'Detector deve acusar uso de OfficeRole legado.'
        );

        $nonRbac = <<<'PHP'
        <?php
        // OPERATOR_REVIEW é código de domínio fiscal, não papel de autenticação.
        $code = 'OPERATOR_REVIEW';
        $fiscal = \App\Enums\FiscalRole::Issuer;
        $html = '<div role="button">';
        PHP;

        $this->assertFalse(
            $this->containsLegacyAuthLiteral($nonRbac),
            'Códigos não-RBAC não devem acionar o detector de literais de autenticação.'
        );
    }

    #[Test]
    public function allowlist_nao_tem_entradas_duplicadas_nem_inexistentes(): void
    {
        $this->assertSame(
            $this->allowlist,
            array_values(array_unique($this->allowlist)),
            'Allowlist não deve conter paths duplicados.'
        );

        $missing = [];
        foreach ($this->allowlist as $rel) {
            $full = $this->appRoot.'/app/'.$rel;
            if (! is_file($full)) {
                $missing[] = $rel;
            }
        }

        $this->assertSame(
            [],
            $missing,
            "Paths da allowlist inexistentes em app/:\n".implode("\n", $missing)
        );
    }

    /**
     * @return \Generator<string, string> relative path under app/ => source
     */
    private function phpFilesUnder(string $root): \Generator
    {
        if (! is_dir($root)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $full = $file->getPathname();
            $rel = ltrim(str_replace($root, '', $full), DIRECTORY_SEPARATOR);
            $rel = str_replace('\\', '/', $rel);
            yield $rel => (string) file_get_contents($full);
        }
    }

    private function containsUnrestrictedGateBefore(string $src): bool
    {
        if (! preg_match('/Gate\s*::\s*before\s*\(/', $src)) {
            return false;
        }

        // Qualquer Gate::before no app é proibido nesta change (não há uso legítimo atual).
        // Fixture de bypass irrestrito: return true ligado a platform admin / isPlatformAdmin.
        if (preg_match('/return\s+true\b/', $src)) {
            return true;
        }

        // Mesmo sem return true literal, Gate::before é bloqueado por precaução.
        return true;
    }

    private function containsLegacyAuthLiteral(string $src): bool
    {
        // Strip block and line comments roughly to reduce noise from pure docs.
        $code = preg_replace('#/\*.*?\*/#s', '', $src) ?? $src;
        $code = preg_replace('#//.*$#m', '', $code) ?? $code;

        $highSignal = [
            '/\bOfficeRole\b/',
            '/\bPlatformRole\b/',
            '/\bPlatformOwner\w*\b/',
            '/[\'"]PLATFORM_ADMIN[\'"]/',
            '/Gate\s*::\s*before\s*\(/',
        ];

        foreach ($highSignal as $pattern) {
            if (preg_match($pattern, $code) === 1) {
                return true;
            }
        }

        // Literais ADMIN|OPERATOR|VIEWER apenas em contexto de papel RBAC.
        if (preg_match('/[\'"](?:ADMIN|OPERATOR|VIEWER)[\'"]/', $code) !== 1) {
            return false;
        }

        // Evita OPERATOR_REVIEW e similares (já removidos em parte com aspas fechadas,
        // mas reforça se o trecho ainda contiver o token de domínio).
        if (str_contains($code, 'OPERATOR_REVIEW')) {
            // Se o único match for dentro de OPERATOR_REVIEW, ignore.
            $stripped = str_replace('OPERATOR_REVIEW', '', $code);
            if (preg_match('/[\'"](?:ADMIN|OPERATOR|VIEWER)[\'"]/', $stripped) !== 1) {
                return false;
            }
        }

        return preg_match(
            '/\b(?:role|OfficeRole|required_role|approver_role|real_office_role|in_array\s*\(\s*\$role)\b/i',
            $code
        ) === 1;
    }
}
