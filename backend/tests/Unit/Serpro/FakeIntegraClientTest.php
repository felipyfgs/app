<?php

namespace Tests\Unit\Serpro;

use App\DTO\Serpro\IntegraRequest;
use App\Services\Integra\FakeIntegraContadorClient;
use PHPUnit\Framework\TestCase;

class FakeIntegraClientTest extends TestCase
{
    public function test_resultado_simulado_nao_e_evidencia_produtiva(): void
    {
        $client = new FakeIntegraContadorClient;
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
