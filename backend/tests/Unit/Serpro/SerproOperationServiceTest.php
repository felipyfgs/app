<?php

namespace Tests\Unit\Serpro;

use App\Contracts\IntegraContadorClient;
use App\Contracts\SecureObjectStore;
use App\DTO\Serpro\IntegraRequest;
use App\DTO\Serpro\IntegraResponse;
use App\DTO\Serpro\MutationAuthorization;
use App\DTO\Serpro\SerproOperationCommand;
use App\Enums\FiscalSourceProvenance;
use App\Enums\SerproAttemptState;
use App\Enums\SerproAuthorizationStatus;
use App\Enums\SerproContractStatus;
use App\Enums\SerproEnvironment;
use App\Enums\TaxProxyPowerSource;
use App\Enums\TaxProxyPowerStatus;
use App\Models\Client;
use App\Models\Establishment;
use App\Models\FiscalMonitoringRun;
use App\Models\Office;
use App\Models\OfficeSerproAuthorization;
use App\Models\SerproApiUsageReservation;
use App\Models\SerproContract;
use App\Models\SerproOperationAttempt;
use App\Models\TaxProxyPower;
use App\Services\Serpro\SerproOperationAttemptStore;
use App\Services\Serpro\SerproOperationService;
use App\Services\Serpro\SerproRequestTagGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SerproOperationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_request_tag_e_opaca_e_distinta_da_idempotency(): void
    {
        $gen = new SerproRequestTagGenerator;
        $tag = $gen->generate([
            'office' => '1',
            'env' => 'TRIAL',
            'op' => 'sitfis.solicitar_protocolo',
            'entity' => 'client:9',
            'idem' => hash('sha256', 'logical-key'),
        ]);

        $this->assertLessThanOrEqual(32, strlen($tag));
        $this->assertStringStartsWith('ic', $tag);
        $this->assertDoesNotMatchRegularExpression('/\d{11,}/', $tag);
        $gen->assertOpaque($tag);

        $idem = 'ic|TRIAL|1|sitfis.solicitar_protocolo|client:9|logical-key';
        $this->assertNotSame($tag, $idem);
    }

    public function test_mutation_authorization_tipada_bloqueia_mutantes(): void
    {
        $auth = MutationAuthorization::none();
        $this->assertFalse($auth->allowsMutatingOperation('sicalc.consolidargerardarf', true));
        $this->assertTrue($auth->allowsMutatingOperation('pagtoweb.pagamentos', false));
    }

    public function test_consulta_de_procuracao_nao_exige_modulo_feature_inexistente(): void
    {
        $service = $this->app->make(SerproOperationService::class);
        $method = new \ReflectionMethod($service, 'moduleForOperation');

        $this->assertNull($method->invoke($service, 'procuracoes.obter', [
            'monitoring_module' => 'authorization',
        ]));
        $this->assertSame('simples_mei', $method->invoke($service, 'pgdasd.consdeclaracao', [
            'monitoring_module' => 'simples_mei',
        ]));
    }

    public function test_executor_replay_retorna_resultado_persistido_sem_segundo_http(): void
    {
        config([
            'serpro.capabilities.sitfis' => 'real',
            'serpro.capabilities.default' => 'real',
            'serpro.default_environment' => 'TRIAL',
            'serpro.kill_switch' => false,
            'features.kill_switch' => false,
        ]);

        [$office, $client] = $this->seedOfficeClientContract();

        $httpCalls = 0;
        $fakeClient = new class($httpCalls) implements IntegraContadorClient
        {
            public function __construct(private int &$calls) {}

            public function execute(IntegraRequest $request): IntegraResponse
            {
                $this->calls++;

                return new IntegraResponse(
                    success: true,
                    httpStatus: 200,
                    body: ['ok' => true],
                    dados: ['protocolo' => 'P1'],
                    operationKey: $request->operationKey,
                    requestTag: $request->resolvedRequestTag(),
                    correlationId: $request->correlationId,
                    sourceProvenance: FiscalSourceProvenance::SerproReal->value,
                    simulated: false,
                );
            }
        };

        $this->app->instance(IntegraContadorClient::class, $fakeClient);
        // Forçar nova resolução do executor com o client de contagem
        $this->app->forgetInstance(SerproOperationService::class);

        /** @var SerproOperationService $service */
        $service = $this->app->make(SerproOperationService::class);

        $cmd = new SerproOperationCommand(
            office: $office,
            client: $client,
            operationKey: 'sitfis.solicitar_protocolo',
            businessData: ['x' => 1],
            idempotencyKey: 'replay-test-1',
            correlationId: 'corr-1',
        );

        $first = $service->run($cmd);
        $this->assertTrue($first->success, $first->errorCode.' '.$first->errorMessage);
        $this->assertSame(1, $httpCalls);
        $this->assertNotEmpty($first->requestTag);
        $this->assertLessThanOrEqual(32, strlen((string) $first->requestTag));

        $second = $service->run($cmd);
        $this->assertTrue($second->success, $second->errorCode.' '.$second->errorMessage);
        $this->assertSame(1, $httpCalls, 'replay não deve reenviar HTTP');
        $this->assertSame($first->requestTag, $second->requestTag);

        $attempt = SerproOperationAttempt::query()
            ->where('operation_key', 'sitfis.solicitar_protocolo')
            ->where('office_id', $office->id)
            ->first();
        $this->assertNotNull($attempt);
        $this->assertTrue($attempt->attempt_state->isTerminal());
    }

    public function test_chave_idempotencia_longa_cabe_no_ledger_tecnico(): void
    {
        config([
            'serpro.capabilities.sitfis' => 'real',
            'serpro.capabilities.default' => 'real',
            'serpro.default_environment' => 'TRIAL',
            'serpro.kill_switch' => false,
            'features.kill_switch' => false,
        ]);

        [$office, $client] = $this->seedOfficeClientContract();

        $this->app->instance(IntegraContadorClient::class, new class implements IntegraContadorClient
        {
            public function execute(IntegraRequest $request): IntegraResponse
            {
                return new IntegraResponse(
                    success: true,
                    httpStatus: 200,
                    body: ['ok' => true],
                    dados: ['protocolo' => 'P1'],
                    operationKey: $request->operationKey,
                    requestTag: $request->resolvedRequestTag(),
                    correlationId: $request->correlationId,
                    sourceProvenance: FiscalSourceProvenance::SerproReal->value,
                    simulated: false,
                );
            }
        });
        $this->app->forgetInstance(SerproOperationService::class);

        $response = $this->app->make(SerproOperationService::class)->run(new SerproOperationCommand(
            office: $office,
            client: $client,
            operationKey: 'sitfis.solicitar_protocolo',
            businessData: ['x' => 1],
            idempotencyKey: str_repeat('chave-logica-longa-', 12),
            correlationId: 'corr-chave-longa',
        ));

        $this->assertTrue($response->success, $response->errorCode.' '.$response->errorMessage);
        $reservation = SerproApiUsageReservation::query()->firstOrFail();
        $this->assertLessThanOrEqual(120, strlen($reservation->idempotency_key));
        $this->assertStringStartsWith('ic:', $reservation->idempotency_key);
    }

    public function test_concurrent_inflight_bloqueia_segundo_http(): void
    {
        $office = Office::factory()->create();
        $client = Client::factory()->create(['office_id' => $office->id]);

        $store = new SerproOperationAttemptStore;
        $tag = (new SerproRequestTagGenerator)->generate(['office' => (string) $office->id]);
        $key = 'ic|TRIAL|'.$office->id.'|sitfis.solicitar_protocolo|client:'.$client->id.'|inflight-1';

        SerproOperationAttempt::query()->create([
            'office_id' => $office->id,
            'environment' => 'TRIAL',
            'operation_key' => 'sitfis.solicitar_protocolo',
            'entity_key' => 'client:'.$client->id,
            'idempotency_key' => $key,
            'request_tag' => $tag,
            'correlation_id' => 'c-inf',
            'attempt_state' => SerproAttemptState::Dispatched,
            'client_id' => $client->id,
            'reserved_at' => now(),
            'dispatched_at' => now(),
        ]);

        $begin = $store->beginOrReplay(
            officeId: (int) $office->id,
            environment: 'TRIAL',
            operationKey: 'sitfis.solicitar_protocolo',
            entityKey: 'client:'.$client->id,
            idempotencyKey: $key,
            requestTag: $tag,
            correlationId: 'c-inf-2',
            clientId: (int) $client->id,
        );

        $this->assertSame('wait', $begin['action']);
        $this->assertNotNull($begin['response']);
        $this->assertSame('ATTEMPT_IN_FLIGHT', $begin['response']->errorCode);
    }

    public function test_replay_de_attempt_simulado_rejeita_payload_sintetico(): void
    {
        $office = Office::factory()->create();
        $client = Client::factory()->create(['office_id' => $office->id]);
        $key = 'ic|TRIAL|'.$office->id.'|sitfis.solicitar_protocolo|client:'.$client->id.'|sim-legacy';

        SerproOperationAttempt::query()->create([
            'office_id' => $office->id,
            'environment' => 'TRIAL',
            'operation_key' => 'sitfis.solicitar_protocolo',
            'entity_key' => 'client:'.$client->id,
            'idempotency_key' => $key,
            'request_tag' => 'ic-sim-legacy',
            'correlation_id' => 'c-sim',
            'attempt_state' => SerproAttemptState::Acknowledged,
            'client_id' => $client->id,
            'success' => true,
            'http_status' => 200,
            'body' => ['protocolo' => 'SINTETICO'],
            'dados' => ['protocolo' => 'SINTETICO'],
            'source_provenance' => 'SIMULATED',
            'reserved_at' => now(),
            'dispatched_at' => now(),
            'acknowledged_at' => now(),
        ]);

        $begin = (new SerproOperationAttemptStore)->beginOrReplay(
            officeId: (int) $office->id,
            environment: 'TRIAL',
            operationKey: 'sitfis.solicitar_protocolo',
            entityKey: 'client:'.$client->id,
            idempotencyKey: $key,
            requestTag: 'ic-sim-legacy-2',
            correlationId: 'c-sim-2',
            clientId: (int) $client->id,
        );

        $this->assertSame('replay', $begin['action']);
        $this->assertNotNull($begin['response']);
        $this->assertFalse($begin['response']->success);
        $this->assertSame('SIMULATED_SOURCE_REJECTED', $begin['response']->errorCode);
        $this->assertSame([], $begin['response']->body);
        $this->assertNull($begin['response']->dados);
    }

    public function test_attempt_store_nao_persiste_token_nem_xml_ecoado(): void
    {
        $office = Office::factory()->create();
        $attempt = SerproOperationAttempt::query()->create([
            'office_id' => $office->id,
            'environment' => 'TRIAL',
            'operation_key' => 'autentica_procurador.envio_xml_assinado',
            'entity_key' => 'office:'.$office->id,
            'idempotency_key' => 'attempt-redaction-'.$office->id,
            'request_tag' => 'ic-redaction-test',
            'attempt_state' => SerproAttemptState::Dispatched,
            'reserved_at' => now(),
            'dispatched_at' => now(),
        ]);

        (new SerproOperationAttemptStore)->acknowledge($attempt, new IntegraResponse(
            success: true,
            httpStatus: 200,
            body: [
                'autenticar_procurador_token' => 'token-confidencial-de-teste',
                'dados' => json_encode([
                    'xml' => base64_encode('<termo>sensivel</termo>'),
                    'status' => 'ok',
                ], JSON_THROW_ON_ERROR),
            ],
            dados: ['autenticar_procurador_token' => 'token-confidencial-de-teste'],
            headers: ['authorization' => 'Bearer token-confidencial-de-teste'],
        ));

        $stored = $attempt->fresh();
        $serialized = json_encode([
            'body' => $stored?->body,
            'dados' => $stored?->dados,
            'headers' => $stored?->headers,
        ], JSON_THROW_ON_ERROR);

        $this->assertStringNotContainsString('token-confidencial-de-teste', $serialized);
        $this->assertStringNotContainsString('<termo>sensivel</termo>', $serialized);
        $this->assertSame(true, $stored?->body['autenticar_procurador_token']['sanitized']);
        $this->assertSame(true, $stored?->body['dados']['xml']['sanitized']);
    }

    public function test_attempt_store_omite_pdf_base64_pagtoweb_72(): void
    {
        $office = Office::factory()->create();
        $attempt = SerproOperationAttempt::query()->create([
            'office_id' => $office->id,
            'environment' => 'TRIAL',
            'operation_key' => 'pagtoweb.comparrecadacao',
            'entity_key' => 'fiscal-run:1',
            'idempotency_key' => 'pagtoweb-attempt-redaction-'.$office->id,
            'request_tag' => 'ic-pagtoweb-redaction',
            'attempt_state' => SerproAttemptState::Dispatched,
            'reserved_at' => now(),
            'dispatched_at' => now(),
        ]);
        $pdf = base64_encode('%PDF-1.4 recibo sensível');

        (new SerproOperationAttemptStore)->acknowledge($attempt, new IntegraResponse(
            success: true,
            httpStatus: 200,
            body: ['dados' => $pdf],
            dados: $pdf,
        ));

        $stored = $attempt->fresh();
        $serialized = json_encode(['body' => $stored?->body, 'dados' => $stored?->dados], JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString($pdf, $serialized);
        $this->assertStringNotContainsString('JVBER', $serialized);
        $this->assertSame(true, $stored?->body['dados']['sanitized']);
        $this->assertSame(true, $stored?->dados['sanitized']);
    }

    public function test_pagtoweb_72_captures_before_ack_and_replays_without_second_http(): void
    {
        config([
            'serpro.capabilities.guides' => 'real',
            'serpro.capabilities.default' => 'real',
            'serpro.default_environment' => 'TRIAL',
            'serpro.kill_switch' => false,
            'features.global_enabled' => true,
            'features.kill_switch' => false,
            'features.modules.guias.enabled' => true,
            'features.modules.guias.allow_all_offices' => true,
        ]);
        [$office, $client] = $this->seedOfficeClientContract();
        $authorization = OfficeSerproAuthorization::query()->where('office_id', $office->id)->firstOrFail();
        TaxProxyPower::query()->create([
            'office_id' => $office->id,
            'client_id' => $client->id,
            'office_serpro_authorization_id' => $authorization->id,
            'environment' => SerproEnvironment::Trial->value,
            'author_identity' => '11222333000181',
            'contributor_cnpj' => '11222333000181',
            'system_code' => 'PAGTOWEB',
            'service_code' => 'COMPARRECADACAO72',
            'power_code' => '00004',
            'source' => TaxProxyPowerSource::ManualOfficialEvidence->value,
            'provenance' => FiscalSourceProvenance::SerproReal->value,
            'status' => TaxProxyPowerStatus::Active->value,
            'valid_from' => now()->subDay(),
            'valid_to' => now()->addDay(),
            'accepted_at' => now(),
            'freshness_checked_at' => now(),
            'verified_at' => now(),
            'last_check_result' => 'TEST_EVIDENCE',
        ]);
        $run = FiscalMonitoringRun::query()->create([
            'office_id' => $office->id,
            'client_id' => $client->id,
            'system_code' => 'PAGTOWEB',
            'service_code' => 'COMPARRECADACAO72',
            'operation_code' => 'EMITIR_COMPROVANTE_ARRECADACAO',
            'operation_key' => 'pagtoweb.comparrecadacao',
            'source_provenance' => FiscalSourceProvenance::SerproTrial->value,
            'trigger' => 'MANUAL',
            'idempotency_key' => 'pagtoweb-central-'.$client->id,
            'status' => 'QUEUED',
            'situation' => 'UNKNOWN',
            'coverage' => 'FULL',
            'mutability' => 'READ_ONLY',
        ]);
        $pdf = base64_encode('%PDF-1.4 comprovante central');
        $calls = 0;
        $this->app->instance(SecureObjectStore::class, new class implements SecureObjectStore
        {
            public function put(string $contents, array $aad = []): string
            {
                return '01JPAGTOWEBCENTRAL000001';
            }

            public function get(string $objectId, array $aad = []): string
            {
                return '';
            }

            public function delete(string $objectId): void {}

            public function exists(string $objectId): bool
            {
                return false;
            }
        });
        $this->app->instance(IntegraContadorClient::class, new class($calls, $pdf) implements IntegraContadorClient
        {
            public function __construct(private int &$calls, private readonly string $pdf) {}

            public function execute(IntegraRequest $request): IntegraResponse
            {
                $this->calls++;

                return new IntegraResponse(
                    success: true,
                    httpStatus: 200,
                    body: ['dados' => $this->pdf],
                    dados: $this->pdf,
                    sourceProvenance: FiscalSourceProvenance::SerproTrial->value,
                    operationKey: $request->operationKey,
                    requestTag: $request->resolvedRequestTag(),
                    correlationId: $request->correlationId,
                );
            }
        });
        $this->app->forgetInstance(SerproOperationService::class);

        $service = $this->app->make(SerproOperationService::class);
        $first = $service->execute($office, $client, 'pagtoweb.comparrecadacao', ['numeroDocumento' => '12345678901234567'], 'pagtoweb-central', 'corr-pagtoweb-central', null, 'fiscal-run:'.$run->id, 'guias');
        $second = $service->execute($office, $client, 'pagtoweb.comparrecadacao', ['numeroDocumento' => '12345678901234567'], 'pagtoweb-central', 'corr-pagtoweb-central', null, 'fiscal-run:'.$run->id, 'guias');

        $this->assertTrue($first->success, $first->errorCode.' '.$first->errorMessage);
        $this->assertSame($first->dados['receipt_id'], $second->dados['receipt_id']);
        $this->assertSame(1, $calls);
        $attempt = SerproOperationAttempt::query()->where('operation_key', 'pagtoweb.comparrecadacao')->firstOrFail();
        $serialized = json_encode(['body' => $attempt->body, 'dados' => $attempt->dados], JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('JVBER', $serialized);
        $this->assertStringNotContainsString('12345678901234567', $serialized);
        $this->assertDatabaseHas('pagtoweb_arrecadacao_receipts', ['office_id' => $office->id, 'client_id' => $client->id]);
    }

    public function test_mutante_bloqueado_por_autorizacao_tipada(): void
    {
        config([
            'serpro.capabilities.guides' => 'real',
            'serpro.capabilities.default' => 'real',
            'serpro.default_environment' => 'TRIAL',
        ]);

        [$office, $client] = $this->seedOfficeClientContract();

        $httpCalls = 0;
        $fakeClient = new class($httpCalls) implements IntegraContadorClient
        {
            public function __construct(private int &$calls) {}

            public function execute(IntegraRequest $request): IntegraResponse
            {
                $this->calls++;

                return new IntegraResponse(success: true, httpStatus: 200, body: []);
            }
        };
        $this->app->instance(IntegraContadorClient::class, $fakeClient);
        $this->app->forgetInstance(SerproOperationService::class);

        $service = $this->app->make(SerproOperationService::class);
        // sicalc consolidar é mutante no catálogo
        $response = $service->execute(
            $office,
            $client,
            'sicalc.consolidargerardarf',
            [],
            'mut-1',
            'corr-mut',
            MutationAuthorization::none(),
        );

        $this->assertFalse($response->success);
        $this->assertSame('MUTATION_DISABLED', $response->errorCode);
        $this->assertSame(0, $httpCalls);
    }

    /**
     * @return array{0: Office, 1: Client}
     */
    private function seedOfficeClientContract(): array
    {
        $office = Office::factory()->create();
        $client = Client::factory()->forOffice($office)->create();

        Establishment::factory()->create([
            'office_id' => $office->id,
            'client_id' => $client->id,
            'cnpj' => '11222333000181',
            'is_active' => true,
            'is_matrix' => true,
        ]);

        SerproContract::query()->create([
            'environment' => SerproEnvironment::Trial,
            'status' => SerproContractStatus::Active,
            'contractor_cnpj' => '11222333000181',
            'health_status' => 'OK',
            'credentials_exposed' => false,
        ]);

        $authorization = OfficeSerproAuthorization::query()->create([
            'office_id' => $office->id,
            'environment' => SerproEnvironment::Trial,
            'status' => SerproAuthorizationStatus::TokenActive,
            'author_identity_type' => 'CNPJ',
            'author_identity' => '11222333000181',
            'certificate_mode' => 'EXTERNAL_SIGNATURE',
            'procurador_token_expires_at' => now()->addDay(),
        ]);

        TaxProxyPower::query()->create([
            'office_id' => $office->id,
            'client_id' => $client->id,
            'office_serpro_authorization_id' => $authorization->id,
            'environment' => SerproEnvironment::Trial->value,
            'author_identity' => '11222333000181',
            'contributor_cnpj' => '11222333000181',
            'system_code' => 'SITFIS',
            'power_code' => '00002',
            'source' => TaxProxyPowerSource::ManualOfficialEvidence->value,
            'provenance' => FiscalSourceProvenance::SerproReal->value,
            'status' => TaxProxyPowerStatus::Active->value,
            'valid_from' => now()->subDay(),
            'valid_to' => now()->addDay(),
            'accepted_at' => now(),
            'freshness_checked_at' => now(),
            'verified_at' => now(),
            'last_check_result' => 'TEST_EVIDENCE',
        ]);

        return [$office, $client];
    }
}
