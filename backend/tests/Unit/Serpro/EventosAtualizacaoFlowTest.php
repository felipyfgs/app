<?php

namespace Tests\Unit\Serpro;

use App\Contracts\SerproOperationExecutor;
use App\DTO\Serpro\IntegraRequest;
use App\DTO\Serpro\IntegraResponse;
use App\DTO\Serpro\MutationAuthorization;
use App\DTO\Serpro\SerproOperationCommand;
use App\Models\Client;
use App\Models\Office;
use App\Models\SerproEventosRun;
use App\Services\Integra\Eventos\EventosAtualizacaoFlowService;
use App\Services\Serpro\CapabilityDriverResolver;
use App\Services\Serpro\EventosRateLimiter;
use App\Services\Serpro\SerproJobFlagGuard;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

final class EventosAtualizacaoFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_limits_versioned_1000_and_batch(): void
    {
        $limiter = app(EventosRateLimiter::class);
        $this->assertSame(1000, $limiter->pfPerDay());
        $this->assertSame(1000, $limiter->pjPerDay());
        $this->assertSame(1000, $limiter->contributorsPerBatch());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/EVENTOS_BATCH_TOO_LARGE/');
        $limiter->assertBatchSize(1001);
    }

    public function test_remote_429_blocks_until_window(): void
    {
        $limiter = app(EventosRateLimiter::class);
        $until = $limiter->markRemote429(1, 'PF', 3600);
        $this->assertTrue($limiter->isRemote429Cooling(1, 'PF'));
        $this->assertTrue($until->greaterThan(CarbonImmutable::now()->subMinute()));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/RATE_LIMIT_EVENTOS_REMOTE_429/');
        $limiter->attemptDaily(1, 'PF');
    }

    public function test_solicit_persists_protocol_and_official_wait_without_hardcoded_ttl(): void
    {
        $office = Office::factory()->create();
        $flow = $this->makeFlow(new IntegraResponse(
            success: true,
            httpStatus: 200,
            body: [
                'protocolo' => 'proto-abc-123',
                'TempoEsperaMedioEmMs' => 1500,
                'TempoLimiteEmMin' => 17,
            ],
            dados: [
                'protocolo' => 'proto-abc-123',
                'TempoEsperaMedioEmMs' => 1500,
                'TempoLimiteEmMin' => 17,
            ],
        ));

        $run = $flow->solicit(
            office: $office,
            personType: 'PF',
            evento: 'EVT_TEST',
            contributorIdentities: ['00000000000'],
        );

        $this->assertInstanceOf(SerproEventosRun::class, $run);
        $this->assertSame('proto-abc-123', $run->protocol);
        $this->assertSame(1500, $run->tempo_espera_medio_ms);
        $this->assertSame(17, $run->tempo_limite_em_min);
        $this->assertSame(SerproEventosRun::PHASE_WAITING, $run->phase);
        $this->assertNotNull($run->not_before_at);
        $this->assertNotNull($run->expires_at);
        $diffMin = CarbonImmutable::now()->diffInMinutes($run->expires_at, false);
        $this->assertGreaterThan(15, $diffMin);
        $this->assertLessThan(20, $diffMin);
    }

    public function test_missing_tempo_limite_fail_closed(): void
    {
        $office = Office::factory()->create();
        $flow = $this->makeFlow(new IntegraResponse(
            success: true,
            httpStatus: 200,
            body: ['protocolo' => 'p1', 'TempoEsperaMedioEmMs' => 100],
            dados: ['protocolo' => 'p1', 'TempoEsperaMedioEmMs' => 100],
        ));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/EVENTOS_TTL_MISSING/');
        $flow->solicit($office, 'PJ', 'EVT', contributorIdentities: ['11222333000181']);
    }

    public function test_one_shot_obtain_consumes_without_second_call(): void
    {
        $office = Office::factory()->create();
        $run = SerproEventosRun::query()->create([
            'office_id' => $office->id,
            'environment' => 'TRIAL',
            'person_type' => 'PF',
            'phase' => SerproEventosRun::PHASE_WAITING,
            'protocol' => 'proto-one',
            'tempo_espera_medio_ms' => 1,
            'tempo_limite_em_min' => 30,
            'not_before_at' => now()->subMinute(),
            'expires_at' => now()->addHour(),
            'status' => SerproEventosRun::STATUS_RUNNING,
            'operation_key_obter' => 'eventosatualizacao.obtereventospf',
            'correlation_id' => 'corr-1',
        ]);

        $calls = 0;
        $executor = new class($calls) implements SerproOperationExecutor
        {
            public function __construct(public int &$calls) {}

            public function run(SerproOperationCommand $command): IntegraResponse
            {
                $this->calls++;

                return new IntegraResponse(
                    success: true,
                    httpStatus: 200,
                    body: ['status' => 200],
                    dados: ['eventos' => []],
                    businessStatus: 'OK',
                );
            }

            public function execute(
                Office $office,
                Client $client,
                string $operationKey,
                array $businessData = [],
                ?string $idempotencyKey = null,
                ?string $correlationId = null,
                ?MutationAuthorization $mutationAuth = null,
                ?string $entityKey = null,
                ?string $module = null,
            ): IntegraResponse {
                return $this->run(new SerproOperationCommand(
                    office: $office,
                    client: $client,
                    operationKey: $operationKey,
                    businessData: $businessData,
                    idempotencyKey: $idempotencyKey,
                    correlationId: $correlationId,
                    entityKey: $entityKey,
                    mutationAuth: $mutationAuth,
                    module: $module,
                ));
            }

            public function executeRequest(
                IntegraRequest $request,
                ?MutationAuthorization $mutationAuth = null,
                ?string $module = null,
            ): IntegraResponse {
                return $this->run(new SerproOperationCommand(
                    office: new Office,
                    client: null,
                    operationKey: $request->operationKey,
                    businessData: $request->businessData,
                    idempotencyKey: $request->idempotencyKey,
                    correlationId: $request->correlationId,
                    mutationAuth: $mutationAuth,
                    module: $module,
                ));
            }
        };

        $flow = $this->makeFlowWithExecutor($executor);

        $first = $flow->obtain($run);
        $this->assertTrue($first->one_shot_complete);
        $this->assertTrue($first->result_consumed);
        $this->assertSame(SerproEventosRun::PHASE_CONSUMED, $first->phase);
        $this->assertSame(1, $calls);

        $second = $flow->obtain($first);
        $this->assertSame($first->id, $second->id);
        $this->assertTrue($second->one_shot_complete);
        $this->assertSame(1, $calls);
    }

    private function makeFlow(IntegraResponse $response): EventosAtualizacaoFlowService
    {
        $executor = new class($response) implements SerproOperationExecutor
        {
            public function __construct(private readonly IntegraResponse $response) {}

            public function run(SerproOperationCommand $command): IntegraResponse
            {
                return $this->response;
            }

            public function execute(
                Office $office,
                Client $client,
                string $operationKey,
                array $businessData = [],
                ?string $idempotencyKey = null,
                ?string $correlationId = null,
                ?MutationAuthorization $mutationAuth = null,
                ?string $entityKey = null,
                ?string $module = null,
            ): IntegraResponse {
                return $this->response;
            }

            public function executeRequest(
                IntegraRequest $request,
                ?MutationAuthorization $mutationAuth = null,
                ?string $module = null,
            ): IntegraResponse {
                return $this->response;
            }
        };

        return $this->makeFlowWithExecutor($executor);
    }

    private function makeFlowWithExecutor(SerproOperationExecutor $executor): EventosAtualizacaoFlowService
    {
        config([
            'serpro.capabilities.authorization' => 'simulated',
            'serpro.kill_switch' => false,
            'features.kill_switch' => false,
        ]);

        return new EventosAtualizacaoFlowService(
            $executor,
            app(EventosRateLimiter::class),
            app(CapabilityDriverResolver::class),
            app(SerproJobFlagGuard::class),
        );
    }
}
