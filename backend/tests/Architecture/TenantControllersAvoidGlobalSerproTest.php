<?php

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

/**
 * Controllers tenant-scoped não devem depender diretamente de recursos globais SERPRO.
 *
 * Heurística estática (como SvrsPortalClientsUseGovernorTest):
 * - Escaneia app/Http/Controllers (exceto namespaces Platform / Admin global)
 * - Proíbe import/uso de contratos, clients e models SERPRO globais
 * - Services autorizados (allowlist) ficam fora do escopo deste teste
 *
 * No estado atual (sem classes SERPRO), o teste passa. Falha se no futuro um
 * controller tenant importar os padrões proibidos sem passar por service autorizado.
 */
class TenantControllersAvoidGlobalSerproTest extends TestCase
{
    /**
     * Paths relativos a app/Http/Controllers que podem orquestrar acesso global
     * (admin da plataforma). Ainda assim preferem services; a allowlist evita
     * falso positivo enquanto endpoints de plataforma não existem.
     *
     * @var list<string>
     */
    private const CONTROLLER_ALLOWLIST = [
        // Ex.: 'Api/V1/Platform/SerproContractController.php',
    ];

    /**
     * Diretórios sob Controllers excluídos (plano de controle global futuro).
     *
     * @var list<string>
     */
    private const EXCLUDED_NAMESPACE_PREFIXES = [
        'Platform/',
        'Admin/',
        'Api/V1/Platform/',
        'Api/V1/Admin/',
    ];

    /**
     * Padrões proibidos em controllers tenant-scoped (import, new, type-hint, FQCN).
     *
     * @var list<string>
     */
    private const FORBIDDEN_PATTERNS = [
        // Namespaces / classes de domínio SERPRO global
        '/\buse\s+App\\\\Services\\\\Serpro\\\\/i',
        '/\buse\s+App\\\\Contracts\\\\IntegraContadorClient\b/i',
        '/\buse\s+App\\\\Contracts\\\\SerproContractAuthenticator\b/i',
        '/\buse\s+App\\\\Models\\\\SerproContract\b/i',
        '/\buse\s+App\\\\Models\\\\SerproApiUsage/i',
        '/\\\\App\\\\Services\\\\Serpro\\\\/i',
        '/\\\\App\\\\Contracts\\\\IntegraContadorClient\b/i',
        '/\\\\App\\\\Contracts\\\\SerproContractAuthenticator\b/i',
        '/\bnew\s+\\\\?App\\\\Services\\\\Serpro\\\\/i',
        '/\bIntegraContadorClient\b/',
        '/\bSerproContractAuthenticator\b/',
        '/\bSerproContract\b/',
        '/\bSerproApiUsageLedger\b/',
        // Credenciais/contratos globais acessados direto do controller
        '/serpro_contracts\b/',
        '/Consumer\s*Secret/i',
        '/CONSUMER_SECRET/',
        '/SERPRO_CONSUMER/i',
        // Client HTTP SERPRO / Integra Contador montado no controller
        '/integra[-_]?contador/i',
        '/gateway\.apiserpro\.serpro\.gov\.br/i',
        '/autenticar[-_]?procurador/i',
    ];

    public function test_tenant_controllers_nao_dependem_diretamente_de_serpro_global(): void
    {
        $controllersRoot = dirname(__DIR__, 2).'/app/Http/Controllers';
        $this->assertDirectoryExists($controllersRoot, 'Diretório de controllers ausente');

        $hits = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($controllersRoot));

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $path = $file->getPathname();
            $rel = substr($path, strlen($controllersRoot) + 1);
            $rel = str_replace('\\', '/', $rel);

            if ($this->isExcluded($rel) || $this->isAllowlisted($rel)) {
                continue;
            }

            $content = file_get_contents($path);
            if ($content === false) {
                continue;
            }

            foreach (self::FORBIDDEN_PATTERNS as $pattern) {
                if (preg_match($pattern, $content) === 1) {
                    $hits[] = "{$rel} (padrão: {$pattern})";
                    break;
                }
            }
        }

        $this->assertSame(
            [],
            $hits,
            'Controllers tenant-scoped com dependência direta de recurso SERPRO global '
            .'(use service autorizado em App\\Services\\Serpro\\* ou similar): '
            .implode('; ', $hits)
        );
    }

    public function test_services_autorizados_podem_existir_fora_de_controllers(): void
    {
        // Documenta a allowlist conceitual: transporte/contrato SERPRO vive em Services,
        // não em Controllers. Este teste só garante que o diretório app existe.
        $servicesRoot = dirname(__DIR__, 2).'/app/Services';
        $this->assertDirectoryExists($servicesRoot);

        $authorizedPrefixes = [
            'Serpro/',
            'IntegraContador/',
            'FiscalMonitoring/',
        ];

        // Smoke: se algum service autorizado já existir, não deve estar vazio de PHP.
        foreach ($authorizedPrefixes as $prefix) {
            $dir = $servicesRoot.'/'.$prefix;
            if (! is_dir($dir)) {
                continue;
            }
            $php = glob($dir.'*.php') ?: [];
            $nested = glob($dir.'**/*.php') ?: [];
            $this->assertNotEmpty(
                array_merge($php, $nested),
                "Diretório autorizado {$prefix} existe mas sem PHP"
            );
        }

        $this->assertTrue(true);
    }

    private function isExcluded(string $rel): bool
    {
        foreach (self::EXCLUDED_NAMESPACE_PREFIXES as $prefix) {
            if (str_starts_with($rel, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private function isAllowlisted(string $rel): bool
    {
        foreach (self::CONTROLLER_ALLOWLIST as $allowed) {
            if ($rel === $allowed || str_ends_with($rel, $allowed)) {
                return true;
            }
        }

        return false;
    }
}
