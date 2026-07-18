<?php

namespace Tests\Unit\Serpro;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class SerproRealOnlyDefaultsTest extends TestCase
{
    public function test_operational_defaults_are_fail_closed_without_trial_configuration(): void
    {
        Http::preventStrayRequests();

        $serpro = config('serpro');

        $this->assertIsArray($serpro);
        $this->assertSame('TRIAL', $serpro['default_environment'] ?? null);

        $environments = $serpro['environments'] ?? null;
        $this->assertIsArray($environments);
        $this->assertSame(
            'https://gateway.apiserpro.serpro.gov.br/integra-contador-trial/v1',
            $environments['TRIAL']['base_url'] ?? null,
        );
        $this->assertArrayNotHasKey('bearer_token', $environments['TRIAL']);
        $this->assertArrayNotHasKey('jwt_token', $environments['TRIAL']);
        $this->assertArrayHasKey('PRODUCTION', $environments);
        $this->assertArrayNotHasKey('HOMOLOGATION', $environments);

        $capabilities = $serpro['capabilities'] ?? null;
        $this->assertIsArray($capabilities);
        $this->assertSame([
            'sitfis',
            'autentica_procurador',
            'authorization',
            'mailbox',
            'dctfweb',
            'simples_mei',
            'installments',
            'guides',
            'registrations',
            'tax_processes',
            'default',
        ], array_keys($capabilities));
        $this->assertSame(['disabled'], array_values(array_unique($capabilities)));
        $this->assertNotContains('real', $capabilities, true);
        $this->assertNotContains('simulated', $capabilities, true);

        $provider = file_get_contents(app_path('Providers/AppServiceProvider.php'));
        $this->assertIsString($provider);
        $this->assertStringNotContainsString(
            'use_fake_clients',
            $provider,
        );

        Http::assertNothingSent();
    }

    public function test_versioned_operational_configuration_does_not_publish_fake_or_simulated_defaults(): void
    {
        $paths = [
            config_path('serpro.php'),
            base_path('.env.example'),
            base_path('phpunit.xml'),
        ];

        foreach ($paths as $path) {
            $contents = file_get_contents($path);

            $this->assertIsString($contents, "Não foi possível ler {$path}.");
            $this->assertStringNotContainsString('SERPRO_USE_FAKE_CLIENTS', $contents, $path);
            $this->assertStringNotContainsString('use_fake_clients', $contents, $path);
            $this->assertStringNotContainsString('=simulated', $contents, $path);
        }
    }

    public function test_legacy_homologation_environment_is_rejected_when_loading_configuration(): void
    {
        $key = 'SERPRO_DEFAULT_ENVIRONMENT';
        $original = [
            'env' => $_ENV[$key] ?? null,
            'server' => $_SERVER[$key] ?? null,
            'getenv' => getenv($key) === false ? null : getenv($key),
        ];

        $_ENV[$key] = 'HOMOLOGATION';
        $_SERVER[$key] = 'HOMOLOGATION';
        putenv("{$key}=HOMOLOGATION");

        try {
            require config_path('serpro.php');
            $this->fail('A configuração legada HOMOLOGATION deveria falhar fechada.');
        } catch (\InvalidArgumentException $exception) {
            $this->assertStringContainsString('TRIAL ou PRODUCTION', $exception->getMessage());
        } finally {
            if ($original['env'] === null) {
                unset($_ENV[$key]);
            } else {
                $_ENV[$key] = $original['env'];
            }

            if ($original['server'] === null) {
                unset($_SERVER[$key]);
            } else {
                $_SERVER[$key] = $original['server'];
            }

            $original['getenv'] === null
                ? putenv($key)
                : putenv("{$key}={$original['getenv']}");
        }
    }
}
