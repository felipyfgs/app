<?php

namespace Tests\Unit\Serpro;

use App\Contracts\SecureObjectStore;
use App\Contracts\SerproContractAuthenticator;
use App\DTO\Serpro\IntegraRequest;
use App\DTO\Serpro\SerproAuthToken;
use App\Enums\SecureObjectPurpose;
use App\Enums\SerproAuthorizationStatus;
use App\Enums\SerproContractStatus;
use App\Enums\SerproEnvironment;
use App\Models\Office;
use App\Models\OfficeSerproAuthorization;
use App\Models\SerproContract;
use App\Services\Integra\HttpIntegraContadorClient;
use App\Services\Serpro\SerproCircuitBreaker;
use App\Services\Serpro\SerproContractService;
use App\Services\Serpro\SerproHttpTransport;
use App\Services\Serpro\SerproKillSwitchService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * Garante que o client HTTP anexa jwt_token do procurador (cadeia Autor).
 */
class HttpIntegraContadorClientProcuradorTest extends TestCase
{
    use RefreshDatabase;

    public function test_anexa_jwt_token_do_procurador_no_transporte(): void
    {
        config([
            'serpro.api.base_url' => 'https://example.test/integra',
            'serpro.kill_switch' => false,
        ]);

        $office = Office::factory()->create();
        $contract = SerproContract::query()->create([
            'environment' => SerproEnvironment::Trial,
            'status' => SerproContractStatus::Active,
            'contractor_cnpj' => '11222333000181',
            'health_status' => 'OK',
            'activated_at' => now(),
        ]);

        $store = app(SecureObjectStore::class);
        $aad = SecureObjectPurpose::SerproProcuradorToken->aadBase([
            'office_id' => $office->id,
            'environment' => SerproEnvironment::Trial->value,
            'author_identity' => '12345678901',
        ]);
        $tokenPayload = json_encode([
            'token' => 'jwt-procurador-secret-test',
            'expires_at' => now()->addHour()->toIso8601String(),
        ], JSON_THROW_ON_ERROR);
        $vaultId = $store->put($tokenPayload, $aad);

        OfficeSerproAuthorization::query()->create([
            'office_id' => $office->id,
            'environment' => SerproEnvironment::Trial,
            'status' => SerproAuthorizationStatus::TokenActive,
            'author_identity_type' => 'CPF',
            'author_identity' => '12345678901',
            'termo_vault_object_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            'termo_valid_to' => now()->addYear(),
            'procurador_token_vault_object_id' => $vaultId,
            'procurador_token_expires_at' => now()->addHours(6),
        ]);

        $transport = new class extends SerproHttpTransport
        {
            /** @var list<string>|null */
            public ?array $capturedHeaders = null;

            public function request(
                string $method,
                string $url,
                ?array $certificate,
                ?string $body,
                array $headers = [],
                ?string $correlationId = null,
            ): array {
                $this->capturedHeaders = $headers;

                return [
                    'status' => 200,
                    'body' => json_encode(['ok' => true], JSON_THROW_ON_ERROR),
                    'headers' => [],
                    'retry_after' => null,
                    'latency_ms' => 12,
                ];
            }
        };

        $authenticator = Mockery::mock(SerproContractAuthenticator::class);
        $authenticator->shouldReceive('authenticate')
            ->once()
            ->andReturn(new SerproAuthToken(
                accessToken: 'contractor-bearer',
                tokenType: 'Bearer',
                expiresAt: CarbonImmutable::now()->addHour(),
            ));

        $client = new HttpIntegraContadorClient(
            contracts: app(SerproContractService::class),
            authenticator: $authenticator,
            transport: $transport,
            killSwitch: app(SerproKillSwitchService::class),
            breaker: app(SerproCircuitBreaker::class),
            store: $store,
        );

        $response = $client->execute(new IntegraRequest(
            officeId: (int) $office->id,
            clientId: 1,
            environment: SerproEnvironment::Trial->value,
            solutionCode: 'INTEGRA_SN',
            serviceCode: 'PGDASD',
            operationCode: 'CONSULTAR_DECLARACAO',
            contractorCnpj: $contract->contractor_cnpj,
            authorIdentity: '12345678901',
            contributorCnpj: '11222333000181',
        ));

        $this->assertTrue($response->success);
        $this->assertIsArray($transport->capturedHeaders);
        $joined = implode("\n", $transport->capturedHeaders);
        $this->assertStringContainsString('Authorization: Bearer contractor-bearer', $joined);
        $this->assertStringContainsString('jwt_token: jwt-procurador-secret-test', $joined);
    }

    public function test_falha_closed_sem_token_procurador(): void
    {
        $office = Office::factory()->create();
        SerproContract::query()->create([
            'environment' => SerproEnvironment::Trial,
            'status' => SerproContractStatus::Active,
            'contractor_cnpj' => '11222333000181',
            'health_status' => 'OK',
            'activated_at' => now(),
        ]);

        OfficeSerproAuthorization::query()->create([
            'office_id' => $office->id,
            'environment' => SerproEnvironment::Trial,
            'status' => SerproAuthorizationStatus::TermValid,
            'author_identity_type' => 'CPF',
            'author_identity' => '12345678901',
            'termo_vault_object_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            'termo_valid_to' => now()->addYear(),
            'procurador_token_vault_object_id' => null,
            'procurador_token_expires_at' => null,
        ]);

        $transport = new class extends SerproHttpTransport
        {
            public int $calls = 0;

            public function request(
                string $method,
                string $url,
                ?array $certificate,
                ?string $body,
                array $headers = [],
                ?string $correlationId = null,
            ): array {
                $this->calls++;

                return [
                    'status' => 500,
                    'body' => '',
                    'headers' => [],
                    'retry_after' => null,
                    'latency_ms' => 1,
                ];
            }
        };

        $client = new HttpIntegraContadorClient(
            contracts: app(SerproContractService::class),
            authenticator: Mockery::mock(SerproContractAuthenticator::class),
            transport: $transport,
            killSwitch: app(SerproKillSwitchService::class),
            breaker: app(SerproCircuitBreaker::class),
            store: app(SecureObjectStore::class),
        );

        $response = $client->execute(new IntegraRequest(
            officeId: (int) $office->id,
            clientId: 1,
            environment: SerproEnvironment::Trial->value,
            solutionCode: 'INTEGRA_SN',
            serviceCode: 'PGDASD',
            operationCode: 'CONSULTAR_DECLARACAO',
            contractorCnpj: '11222333000181',
            authorIdentity: '12345678901',
            contributorCnpj: '11222333000181',
        ));

        $this->assertFalse($response->success);
        $this->assertSame('PROCURADOR_TOKEN_MISSING', $response->errorCode);
        $this->assertSame(0, $transport->calls);
    }
}
