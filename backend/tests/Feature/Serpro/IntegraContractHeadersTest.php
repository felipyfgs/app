<?php

namespace Tests\Feature\Serpro;

use App\Contracts\IntegraContadorClient;
use App\Contracts\SerproContractAuthenticator;
use App\DTO\Serpro\IntegraRequest;
use App\Enums\SerproContractStatus;
use App\Enums\SerproEnvironment;
use App\Models\SerproContract;
use App\Services\Integra\FakeIntegraContadorClient;
use App\Services\Serpro\FakeSerproContractAuthenticator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Contract tests sem certificado real: bindings fake + envelopes de domínio.
 */
class IntegraContractHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_bindings_fake_em_testing(): void
    {
        $this->assertInstanceOf(FakeIntegraContadorClient::class, app(IntegraContadorClient::class));
        $this->assertInstanceOf(FakeSerproContractAuthenticator::class, app(SerproContractAuthenticator::class));
    }

    public function test_authenticator_fake_cacheia_token_sanitizado(): void
    {
        $contract = SerproContract::query()->create([
            'environment' => SerproEnvironment::Trial,
            'status' => SerproContractStatus::Active,
            'contractor_cnpj' => '11222333000181',
            'contractor_name' => 'SH',
            'fingerprint_sha256' => str_repeat('1', 64),
            'health_status' => 'OK',
        ]);

        $auth = app(SerproContractAuthenticator::class);
        $t1 = $auth->authenticate($contract);
        $t2 = $auth->authenticate($contract->refresh());

        $this->assertFalse($t1->fromCache);
        $this->assertTrue($t2->fromCache);
        $sanitized = $t1->toSanitizedArray();
        $this->assertArrayNotHasKey('access_token', $sanitized);
        $this->assertArrayHasKey('expires_at', $sanitized);
    }

    public function test_integra_request_response_envelope(): void
    {
        $client = app(IntegraContadorClient::class);
        $response = $client->execute(new IntegraRequest(
            officeId: 1,
            clientId: 1,
            environment: 'TRIAL',
            solutionCode: 'INTEGRA_SN',
            serviceCode: 'PGDASD',
            operationCode: 'CONSULTAR_DECLARACAO',
            contractorCnpj: '11222333000181',
            authorIdentity: '12345678901',
            contributorCnpj: '11222333000181',
            correlationId: 'corr-test-1',
        ));

        $this->assertTrue($response->success);
        $this->assertTrue($response->simulated);
        $this->assertSame('corr-test-1', $response->correlationId);
        $this->assertSame(200, $response->httpStatus);
    }
}
