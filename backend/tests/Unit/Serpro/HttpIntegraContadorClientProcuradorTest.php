<?php

namespace Tests\Unit\Serpro;

use App\Contracts\SecureObjectStore;
use App\Contracts\SerproContractAuthenticator;
use App\DTO\Serpro\IntegraRequest;
use App\DTO\Serpro\SerproAuthToken;
use App\Enums\FiscalSourceProvenance;
use App\Enums\SecureObjectPurpose;
use App\Enums\SerproAuthorizationStatus;
use App\Enums\SerproContractStatus;
use App\Enums\SerproEnvironment;
use App\Enums\TaxProxyPowerSource;
use App\Enums\TaxProxyPowerStatus;
use App\Models\Client;
use App\Models\Office;
use App\Models\OfficeSerproAuthorization;
use App\Models\SerproContract;
use App\Models\TaxProxyPower;
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
 * Garante os headers distintos do contratante e do procurador (cadeia Autor).
 */
class HttpIntegraContadorClientProcuradorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'serpro.environments.TRIAL.base_url' => 'https://trial.example.test/integra-contador/v1',
        ]);
    }

    public function test_anexa_jwt_token_do_procurador_no_transporte(): void
    {
        config([
            'serpro.api.base_url' => 'https://example.test/integra',
            'serpro.kill_switch' => false,
            'serpro.rate_limit.global_per_minute' => 0,
            'serpro.rate_limit.per_office_per_minute' => 0,
        ]);

        $office = Office::factory()->create();
        $client = Client::factory()->create(['office_id' => $office->id, 'root_cnpj' => '11222333000181']);
        $contract = SerproContract::query()->create([
            'environment' => SerproEnvironment::Production,
            'status' => SerproContractStatus::Active,
            'contractor_cnpj' => '11222333000181',
            'health_status' => 'OK',
            'activated_at' => now(),
        ]);

        $store = app(SecureObjectStore::class);
        $aad = SecureObjectPurpose::SerproProcuradorToken->aadBase([
            'office_id' => $office->id,
            'environment' => SerproEnvironment::Production->value,
            'author_identity' => '52998224725',
        ]);
        $tokenPayload = json_encode([
            'token' => 'jwt-procurador-secret-test',
            'expires_at' => now()->addHour()->toIso8601String(),
        ], JSON_THROW_ON_ERROR);
        $vaultId = $store->put($tokenPayload, $aad);

        $auth = OfficeSerproAuthorization::query()->create([
            'office_id' => $office->id,
            'environment' => SerproEnvironment::Production,
            'status' => SerproAuthorizationStatus::TokenActive,
            'author_identity_type' => 'CPF',
            'author_identity' => '52998224725',
            'termo_vault_object_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            'termo_valid_to' => now()->addYear(),
            'procurador_token_vault_object_id' => $vaultId,
            'procurador_token_expires_at' => now()->addHours(6),
        ]);

        TaxProxyPower::query()->create([
            'office_id' => $office->id,
            'client_id' => $client->id,
            'office_serpro_authorization_id' => $auth->id,
            'author_identity' => '52998224725',
            'contributor_cnpj' => '11222333000181',
            'system_code' => 'SITFIS',
            'service_code' => 'SOLICITARPROTOCOLO91',
            'power_code' => '00002',
            'source' => TaxProxyPowerSource::ManualOfficialEvidence,
            'status' => TaxProxyPowerStatus::Active,
            'valid_from' => now()->subDay(),
            'valid_to' => now()->addYear(),
            'evidence_ref' => 'test',
            'verified_at' => now(),
        ]);

        $transport = new class extends SerproHttpTransport
        {
            /** @var list<string>|null */
            public ?array $capturedHeaders = null;

            public string $capturedBody = '';

            public function request(
                string $method,
                string $url,
                ?array $certificate,
                ?string $body,
                array $headers = [],
                ?string $correlationId = null,
            ): array {
                $this->capturedHeaders = $headers;
                $this->capturedBody = (string) $body;

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
        $authenticator->shouldReceive('authenticate')->once()->andReturn(new SerproAuthToken(
            accessToken: 'contractor-bearer',
            tokenType: 'Bearer',
            expiresAt: CarbonImmutable::now()->addHour(),
            jwtToken: 'contractor-jwt',
        ));

        $clientHttp = new HttpIntegraContadorClient(
            contracts: app(SerproContractService::class),
            authenticator: $authenticator,
            transport: $transport,
            killSwitch: app(SerproKillSwitchService::class),
            breaker: app(SerproCircuitBreaker::class),
            store: $store,
        );

        $response = $clientHttp->execute(new IntegraRequest(
            officeId: (int) $office->id,
            clientId: (int) $client->id,
            environment: SerproEnvironment::Production->value,
            contractorCnpj: $contract->contractor_cnpj,
            authorIdentity: '52998224725',
            contributorCnpj: '11222333000181',
            operationKey: 'sitfis.solicitar_protocolo',
        ));

        $this->assertTrue($response->success);
        $this->assertIsArray($transport->capturedHeaders);
        $joined = implode("\n", $transport->capturedHeaders);
        $this->assertStringContainsString('Authorization: Bearer contractor-bearer', $joined);
        $this->assertStringContainsString('jwt_token: contractor-jwt', $joined);
        $this->assertStringContainsString('autenticar_procurador_token: jwt-procurador-secret-test', $joined);
        $this->assertStringContainsString('X-Request-Tag: ', $joined);
        $this->assertLessThanOrEqual(32, strlen($response->requestTag ?? ''));
        $this->assertFalse($response->simulated);
        $this->assertSame(FiscalSourceProvenance::SerproReal->value, $response->sourceProvenance);
        // dados EMPTY para solicitar protocolo
        $this->assertStringContainsString('"dados":""', $transport->capturedBody);
    }

    public function test_falha_closed_sem_token_procurador(): void
    {
        config([
            'serpro.rate_limit.global_per_minute' => 0,
            'serpro.rate_limit.per_office_per_minute' => 0,
        ]);

        $office = Office::factory()->create();
        $client = Client::factory()->create(['office_id' => $office->id, 'root_cnpj' => '11222333000181']);
        SerproContract::query()->create([
            'environment' => SerproEnvironment::Production,
            'status' => SerproContractStatus::Active,
            'contractor_cnpj' => '11222333000181',
            'health_status' => 'OK',
            'activated_at' => now(),
        ]);

        OfficeSerproAuthorization::query()->create([
            'office_id' => $office->id,
            'environment' => SerproEnvironment::Production,
            'status' => SerproAuthorizationStatus::TermValid,
            'author_identity_type' => 'CPF',
            'author_identity' => '52998224725',
            'termo_vault_object_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            'termo_valid_to' => now()->addYear(),
            'procurador_token_vault_object_id' => null,
            'procurador_token_expires_at' => null,
        ]);

        TaxProxyPower::query()->create([
            'office_id' => $office->id,
            'client_id' => $client->id,
            'author_identity' => '52998224725',
            'contributor_cnpj' => '11222333000181',
            'system_code' => 'SITFIS',
            'power_code' => '00002',
            'source' => TaxProxyPowerSource::ManualOfficialEvidence,
            'status' => TaxProxyPowerStatus::Active,
            'evidence_ref' => 'test',
            'verified_at' => now(),
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

        $clientHttp = new HttpIntegraContadorClient(
            contracts: app(SerproContractService::class),
            authenticator: Mockery::mock(SerproContractAuthenticator::class),
            transport: $transport,
            killSwitch: app(SerproKillSwitchService::class),
            breaker: app(SerproCircuitBreaker::class),
            store: app(SecureObjectStore::class),
        );

        $response = $clientHttp->execute(new IntegraRequest(
            officeId: (int) $office->id,
            clientId: (int) $client->id,
            environment: SerproEnvironment::Production->value,
            contractorCnpj: '11222333000181',
            authorIdentity: '52998224725',
            contributorCnpj: '11222333000181',
            operationKey: 'sitfis.solicitar_protocolo',
        ));

        $this->assertFalse($response->success);
        $this->assertSame('PROCURADOR_TOKEN_MISSING', $response->errorCode);
        $this->assertSame(0, $transport->calls);
    }

    public function test_descarta_header_arbitrario_e_preserva_cnpj_alfanumerico(): void
    {
        config([
            'serpro.api.base_url' => 'https://example.test/integra',
            'serpro.kill_switch' => false,
            'serpro.rate_limit.global_per_minute' => 0,
            'serpro.rate_limit.per_office_per_minute' => 0,
        ]);

        $office = Office::factory()->create();
        // Autor = contribuinte → sem token de procurador (mesma identidade)
        $alphaCnpj = '12ABC34501DE35';
        $client = Client::factory()->create(['office_id' => $office->id, 'root_cnpj' => $alphaCnpj]);

        $contract = SerproContract::query()->create([
            'environment' => SerproEnvironment::Trial,
            'status' => SerproContractStatus::Active,
            'contractor_cnpj' => '11222333000181',
            'health_status' => 'OK',
            'activated_at' => now(),
        ]);
        app(SerproContractService::class)->storeTrialGatewayBearer($contract, 'database-trial-bearer');

        $transport = new class extends SerproHttpTransport
        {
            /** @var list<string>|null */
            public ?array $capturedHeaders = null;

            public string $capturedBody = '';

            public function request(
                string $method,
                string $url,
                ?array $certificate,
                ?string $body,
                array $headers = [],
                ?string $correlationId = null,
            ): array {
                $this->capturedHeaders = $headers;
                $this->capturedBody = (string) $body;

                return [
                    'status' => 200,
                    'body' => json_encode(['status' => 'OK'], JSON_THROW_ON_ERROR),
                    'headers' => ['etag' => 'secret-etag-value'],
                    'retry_after' => null,
                    'latency_ms' => 5,
                ];
            }
        };

        $authenticator = Mockery::mock(SerproContractAuthenticator::class);
        $authenticator->shouldNotReceive('authenticate');

        $http = new HttpIntegraContadorClient(
            contracts: app(SerproContractService::class),
            authenticator: $authenticator,
            transport: $transport,
            killSwitch: app(SerproKillSwitchService::class),
            breaker: app(SerproCircuitBreaker::class),
            store: app(SecureObjectStore::class),
        );

        // Autentica procurador = CONTRACT_ONLY (sem token procurador)
        $response = $http->execute(new IntegraRequest(
            officeId: (int) $office->id,
            clientId: (int) $client->id,
            environment: SerproEnvironment::Trial->value,
            contractorCnpj: '11222333000181',
            authorIdentity: $alphaCnpj,
            contributorCnpj: $alphaCnpj,
            operationKey: 'autentica_procurador.envio_xml_assinado',
            businessData: ['xmlAssinado' => '<xml/>'],
            headers: [
                'X-Evil-Secret' => 'should-be-dropped',
                'If-None-Match' => '"abc"',
            ],
        ));

        $this->assertTrue($response->success);
        $joined = implode("\n", $transport->capturedHeaders ?? []);
        $this->assertStringNotContainsString('X-Evil-Secret', $joined);
        $this->assertStringContainsString('If-None-Match: "abc"', $joined);
        $this->assertStringContainsString($alphaCnpj, $transport->capturedBody);
        $this->assertStringNotContainsString('should-be-dropped', $joined);
        // ETag não vaza nos headers públicos da resposta
        $this->assertArrayNotHasKey('etag', $response->headers);
    }

    public function test_trial_usa_endpoint_de_demonstracao_e_credenciais_do_contrato_no_banco(): void
    {
        config([
            'serpro.environments.PRODUCTION.base_url' => 'https://production.example.test/integra-contador/v1',
            'serpro.kill_switch' => false,
            'serpro.rate_limit.global_per_minute' => 0,
            'serpro.rate_limit.per_office_per_minute' => 0,
        ]);

        $office = Office::factory()->create();
        $client = Client::factory()->create(['office_id' => $office->id, 'root_cnpj' => '11222333000181']);
        $contract = SerproContract::query()->create([
            'environment' => SerproEnvironment::Trial,
            'status' => SerproContractStatus::Active,
            'contractor_cnpj' => '11222333000181',
            'health_status' => 'OK',
            'activated_at' => now(),
        ]);
        app(SerproContractService::class)->storeTrialGatewayBearer($contract, 'database-trial-bearer');
        $transport = new class extends SerproHttpTransport
        {
            public ?string $url = null;

            public ?string $body = null;

            /** @var list<string> */
            public array $headers = [];

            public function request(string $method, string $url, ?array $certificate, ?string $body, array $headers = [], ?string $correlationId = null): array
            {
                $this->url = $url;
                $this->body = $body;
                $this->headers = $headers;

                return ['status' => 200, 'body' => '{"status":"OK"}', 'headers' => [], 'retry_after' => null, 'latency_ms' => 1];
            }
        };
        $authenticator = Mockery::mock(SerproContractAuthenticator::class);
        $authenticator->shouldNotReceive('authenticate');

        $http = new HttpIntegraContadorClient(
            contracts: app(SerproContractService::class),
            authenticator: $authenticator,
            transport: $transport,
            killSwitch: app(SerproKillSwitchService::class),
            breaker: app(SerproCircuitBreaker::class),
            store: app(SecureObjectStore::class),
        );

        $response = $http->execute(new IntegraRequest(
            officeId: (int) $office->id,
            clientId: (int) $client->id,
            environment: SerproEnvironment::Trial->value,
            contractorCnpj: '11222333000181',
            authorIdentity: '52998224725',
            contributorCnpj: '11222333000181',
            operationKey: 'sitfis.solicitar_protocolo',
        ));

        $this->assertTrue($response->success);
        $this->assertSame('https://trial.example.test/integra-contador/v1/Apoiar', $transport->url);
        $this->assertStringContainsString('Authorization: Bearer database-trial-bearer', implode("\n", $transport->headers));
        $this->assertStringNotContainsString('jwt_token:', implode("\n", $transport->headers));
        $this->assertStringNotContainsString('autenticar_procurador_token:', implode("\n", $transport->headers));
        $this->assertStringContainsString('"contratante":{"numero":"11222333000181"', (string) $transport->body);
        $this->assertFalse($response->isProductiveEvidence());
        $this->assertSame(FiscalSourceProvenance::SerproTrial->value, $response->sourceProvenance);
    }

    public function test_trial_falha_fechada_sem_contrato_ativo_no_banco(): void
    {
        config([
            'serpro.kill_switch' => false,
            'serpro.rate_limit.global_per_minute' => 0,
            'serpro.rate_limit.per_office_per_minute' => 0,
        ]);
        $office = Office::factory()->create();
        $client = Client::factory()->create(['office_id' => $office->id, 'root_cnpj' => '11222333000181']);
        $transport = Mockery::mock(SerproHttpTransport::class);
        $transport->shouldNotReceive('request');
        $authenticator = Mockery::mock(SerproContractAuthenticator::class);
        $authenticator->shouldNotReceive('authenticate');

        $http = new HttpIntegraContadorClient(
            contracts: app(SerproContractService::class),
            authenticator: $authenticator,
            transport: $transport,
            killSwitch: app(SerproKillSwitchService::class),
            breaker: app(SerproCircuitBreaker::class),
            store: app(SecureObjectStore::class),
        );
        $response = $http->execute(new IntegraRequest(
            officeId: (int) $office->id,
            clientId: (int) $client->id,
            environment: SerproEnvironment::Trial->value,
            contractorCnpj: '11222333000181',
            authorIdentity: '11222333000181',
            contributorCnpj: '11222333000181',
            operationKey: 'autentica_procurador.envio_xml_assinado',
        ));

        $this->assertSame('CONTRACT_UNAVAILABLE', $response->errorCode);
        $this->assertFalse($response->simulated);
        $this->assertSame(FiscalSourceProvenance::SerproTrial->value, $response->sourceProvenance);
    }

    public function test_dctfweb_trial_usa_cenario_oficial_e_detecta_erro_de_negocio_http_200(): void
    {
        config([
            'serpro.kill_switch' => false,
            'serpro.rate_limit.global_per_minute' => 0,
            'serpro.rate_limit.per_office_per_minute' => 0,
        ]);
        $office = Office::factory()->create();
        $client = Client::factory()->create(['office_id' => $office->id, 'root_cnpj' => '11222333000181']);
        $contract = SerproContract::query()->create([
            'environment' => SerproEnvironment::Trial,
            'status' => SerproContractStatus::Active,
            'contractor_cnpj' => '11222333000181',
            'health_status' => 'OK',
            'activated_at' => now(),
        ]);
        app(SerproContractService::class)->storeTrialGatewayBearer($contract, 'database-trial-bearer');

        $transport = new class extends SerproHttpTransport
        {
            public string $body = '';

            public function request(string $method, string $url, ?array $certificate, ?string $body, array $headers = [], ?string $correlationId = null): array
            {
                $this->body = (string) $body;

                return [
                    'status' => 200,
                    'body' => json_encode([
                        'mensagens' => [['codigo' => 'ERRO', 'texto' => 'Dados inválidos.']],
                    ], JSON_THROW_ON_ERROR),
                    'headers' => [],
                    'retry_after' => null,
                    'latency_ms' => 1,
                ];
            }
        };
        $authenticator = Mockery::mock(SerproContractAuthenticator::class);
        $authenticator->shouldNotReceive('authenticate');
        $http = new HttpIntegraContadorClient(
            contracts: app(SerproContractService::class),
            authenticator: $authenticator,
            transport: $transport,
            killSwitch: app(SerproKillSwitchService::class),
            breaker: app(SerproCircuitBreaker::class),
            store: app(SecureObjectStore::class),
        );

        $response = $http->execute(new IntegraRequest(
            officeId: (int) $office->id,
            clientId: (int) $client->id,
            environment: SerproEnvironment::Trial->value,
            contractorCnpj: '11222333000181',
            authorIdentity: '52998224725',
            contributorCnpj: '11222333000181',
            operationKey: 'dctfweb.consrecibo',
            businessData: ['anoPA' => '2026', 'mesPA' => '06'],
        ));

        $envelope = json_decode($transport->body, true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('00000000000000', $envelope['contratante']['numero']);
        $this->assertSame($envelope['contratante'], $envelope['autorPedidoDados']);
        $this->assertSame($envelope['contratante'], $envelope['contribuinte']);
        $this->assertSame([
            'categoria' => 40,
            'anoPA' => '2027',
            'mesPA' => '11',
            'numeroReciboEntrega' => 24573,
        ], json_decode($envelope['pedidoDados']['dados'], true, flags: JSON_THROW_ON_ERROR));
        $this->assertFalse($response->success);
        $this->assertSame('BUSINESS_ERROR', $response->errorCode);
        $this->assertSame(FiscalSourceProvenance::SerproTrial->value, $response->sourceProvenance);
    }

    public function test_trial_falha_fechada_sem_bearer_no_cofre(): void
    {
        config([
            'serpro.kill_switch' => false,
            'serpro.rate_limit.global_per_minute' => 0,
            'serpro.rate_limit.per_office_per_minute' => 0,
        ]);
        $office = Office::factory()->create();
        $client = Client::factory()->create(['office_id' => $office->id, 'root_cnpj' => '11222333000181']);
        SerproContract::query()->create([
            'environment' => SerproEnvironment::Trial,
            'status' => SerproContractStatus::Active,
            'contractor_cnpj' => '11222333000181',
            'health_status' => 'OK',
            'activated_at' => now(),
        ]);
        $transport = Mockery::mock(SerproHttpTransport::class);
        $transport->shouldNotReceive('request');
        $authenticator = Mockery::mock(SerproContractAuthenticator::class);
        $authenticator->shouldNotReceive('authenticate');

        $http = new HttpIntegraContadorClient(
            contracts: app(SerproContractService::class),
            authenticator: $authenticator,
            transport: $transport,
            killSwitch: app(SerproKillSwitchService::class),
            breaker: app(SerproCircuitBreaker::class),
            store: app(SecureObjectStore::class),
        );
        $response = $http->execute(new IntegraRequest(
            officeId: (int) $office->id,
            clientId: (int) $client->id,
            environment: SerproEnvironment::Trial->value,
            contractorCnpj: '11222333000181',
            authorIdentity: '11222333000181',
            contributorCnpj: '11222333000181',
            operationKey: 'autentica_procurador.envio_xml_assinado',
        ));

        $this->assertSame('TRIAL_CREDENTIALS_MISSING', $response->errorCode);
        $this->assertSame(FiscalSourceProvenance::SerproTrial->value, $response->sourceProvenance);
    }

    public function test_producao_preserva_oauth_mtls_e_nao_usa_tokens_trial(): void
    {
        config([
            'serpro.environments.PRODUCTION.base_url' => 'https://production.example.test/integra-contador/v1',
            'serpro.kill_switch' => false,
            'serpro.rate_limit.global_per_minute' => 0,
            'serpro.rate_limit.per_office_per_minute' => 0,
        ]);
        $office = Office::factory()->create();
        $client = Client::factory()->create(['office_id' => $office->id, 'root_cnpj' => '11222333000181']);
        $contract = SerproContract::query()->create([
            'environment' => SerproEnvironment::Production,
            'status' => SerproContractStatus::Active,
            'contractor_cnpj' => '11222333000181',
            'health_status' => 'OK',
            'activated_at' => now(),
        ]);
        $transport = new class extends SerproHttpTransport
        {
            public ?string $url = null;

            /** @var list<string> */
            public array $headers = [];

            public function request(string $method, string $url, ?array $certificate, ?string $body, array $headers = [], ?string $correlationId = null): array
            {
                $this->url = $url;
                $this->headers = $headers;

                return ['status' => 200, 'body' => '{"status":"OK"}', 'headers' => [], 'retry_after' => null, 'latency_ms' => 1];
            }
        };
        $authenticator = Mockery::mock(SerproContractAuthenticator::class);
        $authenticator->shouldReceive('authenticate')->once()->with(Mockery::on(
            fn (SerproContract $value): bool => $value->id === $contract->id,
        ))->andReturn(new SerproAuthToken(
            accessToken: 'production-oauth-bearer',
            tokenType: 'Bearer',
            expiresAt: CarbonImmutable::now()->addHour(),
            jwtToken: 'production-oauth-jwt',
        ));
        $http = new HttpIntegraContadorClient(
            contracts: app(SerproContractService::class),
            authenticator: $authenticator,
            transport: $transport,
            killSwitch: app(SerproKillSwitchService::class),
            breaker: app(SerproCircuitBreaker::class),
            store: app(SecureObjectStore::class),
        );

        $response = $http->execute(new IntegraRequest(
            officeId: (int) $office->id,
            clientId: (int) $client->id,
            environment: SerproEnvironment::Production->value,
            contractorCnpj: $contract->contractor_cnpj,
            authorIdentity: '11222333000181',
            contributorCnpj: '11222333000181',
            operationKey: 'autentica_procurador.envio_xml_assinado',
            businessData: ['xml' => 'base64-xml'],
        ));

        $joined = implode("\n", $transport->headers);
        $this->assertTrue($response->success);
        $this->assertSame('https://production.example.test/integra-contador/v1/Apoiar', $transport->url);
        $this->assertStringContainsString('Authorization: Bearer production-oauth-bearer', $joined);
        $this->assertStringContainsString('jwt_token: production-oauth-jwt', $joined);
        $this->assertStringNotContainsString('database-oauth-bearer', $joined);
        $this->assertFalse($response->simulated);
        $this->assertSame(FiscalSourceProvenance::SerproReal->value, $response->sourceProvenance);
    }
}
