<?php

namespace Tests\Unit\Serpro;

use App\Contracts\IntegraContadorClient;
use App\DTO\Serpro\IntegraRequest;
use Tests\Support\Fakes\FakeIntegraContadorClient;
use Tests\Support\SerproTestDoubleServiceProvider;
use Tests\TestCase;

class FakeIntegraClientTest extends TestCase
{
    public function test_resultado_simulado_nao_e_evidencia_produtiva(): void
    {
        $this->app->register(SerproTestDoubleServiceProvider::class);

        $client = app(IntegraContadorClient::class);
        $this->assertInstanceOf(FakeIntegraContadorClient::class, $client);
        $response = $client->execute(new IntegraRequest(
            officeId: 1,
            clientId: 2,
            environment: 'TRIAL',
            contractorCnpj: '11222333000181',
            authorIdentity: '52998224725',
            contributorCnpj: '11222333000181',
            operationKey: 'pgdasd.consdeclaracao',
            solutionCode: 'PGDASD',
            serviceCode: 'PGDASD',
            operationCode: 'CONSDECLARACAO13',
        ));

        $this->assertTrue($response->success);
        $this->assertTrue($response->simulated);
        $this->assertFalse($response->isProductiveEvidence());

        $sanitized = $response->toSanitizedArray();
        $this->assertArrayNotHasKey('access_token', $sanitized);
        $this->assertTrue($sanitized['simulated']);
    }
}
