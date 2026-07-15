<?php

namespace Tests\Unit\Serpro;

use App\DTO\Serpro\IntegraRequest;
use App\Enums\FiscalSourceProvenance;
use App\Enums\SerproCapabilityDriver;
use App\Services\Integra\SimulatedIntegraContadorClient;
use App\Services\Serpro\CapabilityDriverResolver;
use Tests\TestCase;

final class CapabilityDriverAndSimulatorTest extends TestCase
{
    public function test_resolver_reads_config(): void
    {
        config(['serpro.capabilities.sitfis' => 'disabled']);
        $resolver = app(CapabilityDriverResolver::class);
        $this->assertSame(SerproCapabilityDriver::Disabled, $resolver->forCapability('sitfis'));
    }

    public function test_simulator_sitfis_success_is_simulated_provenance(): void
    {
        $client = app(SimulatedIntegraContadorClient::class);
        $request = new IntegraRequest(
            officeId: 1,
            clientId: 1,
            environment: 'TRIAL',
            contractorCnpj: '12345678000199',
            authorIdentity: '12345678000199',
            contributorCnpj: '12345678000199',
            operationKey: 'sitfis.solicitar_protocolo',
            businessData: ['__scenario' => 'success'],
        );

        $response = $client->execute($request);
        $this->assertTrue($response->success);
        $this->assertTrue($response->simulated);
        $this->assertSame(FiscalSourceProvenance::Simulated->value, $response->sourceProvenance);
        $this->assertNotEmpty($response->body['protocolo'] ?? null);
    }

    public function test_simulator_processing_scenario(): void
    {
        $client = app(SimulatedIntegraContadorClient::class);
        $request = new IntegraRequest(
            officeId: 1,
            clientId: 1,
            environment: 'TRIAL',
            contractorCnpj: '12345678000199',
            authorIdentity: '12345678000199',
            contributorCnpj: '12345678000199',
            operationKey: 'sitfis.emitir_relatorio',
            businessData: ['__scenario' => 'processing', 'protocolo' => 'X'],
        );

        $response = $client->execute($request);
        $this->assertTrue($response->isStillProcessing());
        $this->assertSame(204, $response->httpStatus);
        $this->assertNotNull($response->waitSeconds());
    }
}
