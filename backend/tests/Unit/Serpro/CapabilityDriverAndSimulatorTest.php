<?php

namespace Tests\Unit\Serpro;

use App\Contracts\IntegraContadorClient;
use App\DTO\Serpro\IntegraRequest;
use App\Enums\SerproCapabilityDriver;
use App\Services\Integra\CapabilityAwareIntegraContadorClient;
use App\Services\Serpro\CapabilityDriverResolver;
use Illuminate\Support\Facades\Http;
use ReflectionClass;
use RuntimeException;
use Tests\TestCase;

final class CapabilityDriverAndSimulatorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        config(['serpro.capabilities.default' => 'disabled']);
    }

    public function test_resolver_reads_disabled_config(): void
    {
        config(['serpro.capabilities.sitfis' => 'disabled']);

        $this->assertSame(
            SerproCapabilityDriver::Disabled,
            app(CapabilityDriverResolver::class)->forCapability('sitfis'),
        );
    }

    public function test_simulated_e_rejeitado_tambem_fora_de_production(): void
    {
        config(['serpro.capabilities.sitfis' => 'simulated']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Driver simulated não é executável');

        app(CapabilityDriverResolver::class)->forCapability('sitfis');
    }

    public function test_preflight_detecta_simulated_em_testing(): void
    {
        $this->assertTrue(app()->environment('testing'));
        config(['serpro.capabilities.authorization' => 'simulated']);

        $problems = app(CapabilityDriverResolver::class)->preflightProduction();

        $this->assertNotEmpty($problems);
        $this->assertStringContainsString(
            'serpro.capabilities.authorization=simulated não é executável',
            implode('; ', $problems),
        );
    }

    public function test_gateway_nao_injeta_simulador_e_rejeita_config_legada(): void
    {
        $constructor = (new ReflectionClass(CapabilityAwareIntegraContadorClient::class))->getConstructor();
        $parameterTypes = array_map(
            static fn ($parameter): string => (string) $parameter->getType(),
            $constructor?->getParameters() ?? [],
        );

        $this->assertNotContains('Tests\\Support\\Fakes\\SimulatedIntegraContadorClient', $parameterTypes);

        config(['serpro.capabilities.sitfis' => 'simulated']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Driver simulated não é executável');

        app(IntegraContadorClient::class)->execute(new IntegraRequest(
            officeId: 1,
            clientId: 1,
            environment: 'TRIAL',
            contractorCnpj: '11222333000181',
            authorIdentity: '52998224725',
            contributorCnpj: '11222333000181',
            operationKey: 'sitfis.solicitar_protocolo',
        ));
    }
}
