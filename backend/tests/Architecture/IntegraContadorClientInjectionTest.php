<?php

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

/**
 * Garante que IntegraContadorClient / HttpIntegraContadorClient só existem
 * na camada de infraestrutura + executor central.
 *
 * Adapters de negócio (SITFIS, DCTF, mailbox, etc.) MUST usar SerproOperationExecutor.
 */
class IntegraContadorClientInjectionTest extends TestCase
{
    /**
     * Paths relativos a app/ permitidos a referenciar IntegraContadorClient.
     *
     * @var list<string>
     */
    private const ALLOWLIST = [
        // Contrato e implementações de transporte
        'Contracts/IntegraContadorClient.php',
        'Contracts/SerproOperationExecutor.php',
        'Services/Integra/HttpIntegraContadorClient.php',
        'Services/Integra/SimulatedIntegraContadorClient.php',
        'Services/Integra/FakeIntegraContadorClient.php',
        'Services/Integra/CapabilityAwareIntegraContadorClient.php',
        // Executor central (único consumidor de negócio)
        'Services/Serpro/SerproOperationService.php',
        // Container DI
        'Providers/AppServiceProvider.php',
    ];

    /**
     * @var list<string>
     */
    private const FORBIDDEN_PATTERNS = [
        '/\buse\s+App\\\\Contracts\\\\IntegraContadorClient\b/',
        '/\bIntegraContadorClient\b/',
        '/\bHttpIntegraContadorClient\b/',
        '/->execute\s*\(\s*\$[a-zA-Z_]*[Rr]equest/',
    ];

    public function test_negocio_nao_injeta_integra_contador_client_fora_da_allowlist(): void
    {
        $appRoot = dirname(__DIR__, 2).'/app';
        $this->assertDirectoryExists($appRoot);

        $hits = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($appRoot));

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $path = $file->getPathname();
            $rel = substr($path, strlen($appRoot) + 1);
            $rel = str_replace('\\', '/', $rel);

            if ($this->isAllowlisted($rel)) {
                continue;
            }

            // Infraestrutura de transporte / fakes auxiliares sob Integra/ já coberta pela allowlist.
            // DTOs e enums não devem injetar o client.
            $content = file_get_contents($path);
            if ($content === false) {
                continue;
            }

            $mentionsClient = preg_match('/\bIntegraContadorClient\b/', $content) === 1
                || preg_match('/\bHttpIntegraContadorClient\b/', $content) === 1
                || preg_match('/\buse\s+App\\\\Contracts\\\\IntegraContadorClient\b/', $content) === 1;

            if ($mentionsClient) {
                $hits[] = $rel;
            }
        }

        $this->assertSame(
            [],
            $hits,
            'Módulos fora da allowlist referenciam IntegraContadorClient/HttpIntegraContadorClient. '
            .'Use SerproOperationExecutor / SerproOperationService. Ofensores: '
            .implode('; ', $hits)
        );
    }

    public function test_executor_e_unico_consumidor_de_client_execute_no_dominio(): void
    {
        $appRoot = dirname(__DIR__, 2).'/app';
        $hits = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($appRoot));

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $rel = str_replace('\\', '/', substr($file->getPathname(), strlen($appRoot) + 1));
            if ($this->isAllowlisted($rel)) {
                continue;
            }
            // Implementações de client e fakes já allowlisted
            $content = file_get_contents($file->getPathname());
            if ($content === false) {
                continue;
            }
            // Detecção de chamada .execute em IntegraRequest via client
            if (preg_match('/\$this->(?:client|integra)->execute\s*\(/', $content) === 1) {
                $hits[] = $rel;
            }
            if (preg_match('/app\(\s*IntegraContadorClient::class\s*\)/', $content) === 1) {
                $hits[] = $rel.' (app resolve)';
            }
        }

        $this->assertSame(
            [],
            $hits,
            'Chamada direta a client->execute fora do executor: '.implode('; ', $hits)
        );
    }

    private function isAllowlisted(string $rel): bool
    {
        foreach (self::ALLOWLIST as $allowed) {
            if ($rel === $allowed || str_ends_with($rel, $allowed)) {
                return true;
            }
        }

        return false;
    }
}
