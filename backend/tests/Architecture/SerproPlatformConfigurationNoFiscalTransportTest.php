<?php

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

/**
 * Controller global de configuração SERPRO não deve orquestrar transporte fiscal
 * (/Apoiar, /Consultar, /Emitir, /Declarar, /Monitorar) nem Integra Contador de negócio.
 */
class SerproPlatformConfigurationNoFiscalTransportTest extends TestCase
{
    private const CONTROLLER = 'Http/Controllers/Api/V1/Platform/SerproPlatformConfigurationController.php';

    private const SERVICE = 'Services/Serpro/SerproPlatformConfigurationService.php';

    /** @var list<string> */
    private const FORBIDDEN = [
        '/Apoiar',
        '/Consultar',
        '/Emitir',
        '/Declarar',
        '/Monitorar',
        'IntegraContadorClient',
        'SerproOperationService',
        'SerproHttpTransport',
    ];

    public function test_configuration_surface_nao_faz_transporte_fiscal(): void
    {
        $root = dirname(__DIR__, 2).'/app';

        foreach ([self::CONTROLLER, self::SERVICE] as $rel) {
            $path = $root.'/'.$rel;
            $this->assertFileExists($path, "Arquivo ausente: {$rel}");
            $src = (string) file_get_contents($path);
            foreach (self::FORBIDDEN as $needle) {
                $this->assertStringNotContainsString(
                    $needle,
                    $src,
                    "{$rel} não deve conter transporte/fiscal: {$needle}",
                );
            }
        }

        // test-connection OAuth fica no CredentialVersionService (endpoint /authenticate apenas).
        $cred = (string) file_get_contents($root.'/Services/Serpro/SerproCredentialVersionService.php');
        $this->assertStringContainsString('testConnection', $cred);
        $this->assertStringNotContainsString('/Apoiar', $cred);
        $this->assertStringNotContainsString('/Consultar', $cred);
        $this->assertStringNotContainsString('/Emitir', $cred);
        $this->assertStringNotContainsString('/Declarar', $cred);
    }
}
