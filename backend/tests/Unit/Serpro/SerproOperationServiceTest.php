<?php

namespace Tests\Unit\Serpro;

use App\Contracts\IntegraContadorClient;
use App\DTO\Serpro\IntegraRequest;
use App\DTO\Serpro\IntegraResponse;
use App\DTO\Serpro\MutationAuthorization;
use App\DTO\Serpro\SerproOperationCommand;
use App\Enums\FiscalSourceProvenance;
use App\Enums\SerproAttemptState;
use App\Enums\SerproAuthorizationStatus;
use App\Enums\SerproContractStatus;
use App\Enums\SerproEnvironment;
use App\Models\Client;
use App\Models\Establishment;
use App\Models\Office;
use App\Models\OfficeSerproAuthorization;
use App\Models\SerproContract;
use App\Models\SerproOperationAttempt;
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

    public function test_executor_replay_retorna_resultado_persistido_sem_segundo_http(): void
    {
        config([
            'serpro.capabilities.sitfis' => 'simulated',
            'serpro.capabilities.default' => 'simulated',
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
                    sourceProvenance: FiscalSourceProvenance::Simulated->value,
                    simulated: true,
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

    public function test_mutante_bloqueado_por_autorizacao_tipada(): void
    {
        config([
            'serpro.capabilities.guides' => 'simulated',
            'serpro.capabilities.default' => 'simulated',
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
        ]);

        OfficeSerproAuthorization::query()->create([
            'office_id' => $office->id,
            'environment' => SerproEnvironment::Trial,
            'status' => SerproAuthorizationStatus::TokenActive,
            'author_identity_type' => 'CNPJ',
            'author_identity' => '11222333000181',
            'certificate_mode' => 'EXTERNAL_SIGNATURE',
            'procurador_token_expires_at' => now()->addDay(),
        ]);

        return [$office, $client];
    }
}
