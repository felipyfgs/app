<?php

namespace Tests\Unit\Serpro;

use App\Enums\SerproAttemptState;
use App\Models\Office;
use App\Models\SerproOperationAttempt;
use App\Services\Serpro\SerproAttemptReplayPolicy;
use App\Services\Serpro\SerproOperationAttemptStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SerproOperationAttemptStoreReplayTest extends TestCase
{
    use RefreshDatabase;

    public function test_policy_marks_token_missing_as_non_sticky(): void
    {
        $this->assertTrue(SerproAttemptReplayPolicy::isNonStickyError('PROCURADOR_TOKEN_MISSING'));
        $this->assertFalse(SerproAttemptReplayPolicy::isStickyReplay('PROCURADOR_TOKEN_MISSING', false));
        $this->assertTrue(SerproAttemptReplayPolicy::isStickyReplay('REQUEST_FAILED', false));
        $this->assertTrue(SerproAttemptReplayPolicy::isStickyReplay(null, true));
    }

    public function test_token_missing_terminal_is_reclaimed_for_dispatch(): void
    {
        $office = Office::factory()->create();
        $key = 'idem-token-missing-'.uniqid();

        SerproOperationAttempt::query()->create([
            'office_id' => $office->id,
            'environment' => 'PRODUCTION',
            'operation_key' => 'procuracoes.obter',
            'entity_key' => 'client:5',
            'idempotency_key' => $key,
            'request_tag' => str_repeat('a', 32),
            'correlation_id' => 'corr-old',
            'attempt_state' => SerproAttemptState::Acknowledged,
            'client_id' => 5,
            'success' => false,
            'http_status' => 422,
            'error_code' => 'PROCURADOR_TOKEN_MISSING',
            'error_message' => 'Token do procurador ausente ou expirado para o escritório.',
            'acknowledged_at' => now(),
        ]);

        $result = app(SerproOperationAttemptStore::class)->beginOrReplay(
            officeId: (int) $office->id,
            environment: 'PRODUCTION',
            operationKey: 'procuracoes.obter',
            entityKey: 'client:5',
            idempotencyKey: $key,
            requestTag: str_repeat('b', 32),
            correlationId: 'corr-new',
            clientId: 5,
        );

        $this->assertSame('dispatch', $result['action']);
        $this->assertNull($result['response']);
        $this->assertInstanceOf(SerproOperationAttempt::class, $result['attempt']);
        $this->assertSame(SerproAttemptState::Dispatched, $result['attempt']->attempt_state);
        $this->assertNull($result['attempt']->error_code);
        $this->assertSame('corr-new', $result['attempt']->correlation_id);
    }

    public function test_request_failed_terminal_stays_sticky_replay(): void
    {
        $office = Office::factory()->create();
        $key = 'idem-request-failed-'.uniqid();

        SerproOperationAttempt::query()->create([
            'office_id' => $office->id,
            'environment' => 'PRODUCTION',
            'operation_key' => 'procuracoes.obter',
            'entity_key' => 'client:5',
            'idempotency_key' => $key,
            'request_tag' => str_repeat('c', 32),
            'correlation_id' => 'corr-fail',
            'attempt_state' => SerproAttemptState::Acknowledged,
            'client_id' => 5,
            'success' => false,
            'http_status' => 400,
            'error_code' => 'REQUEST_FAILED',
            'error_message' => 'Chamada Integra Contador rejeitada.',
            'acknowledged_at' => now(),
        ]);

        $result = app(SerproOperationAttemptStore::class)->beginOrReplay(
            officeId: (int) $office->id,
            environment: 'PRODUCTION',
            operationKey: 'procuracoes.obter',
            entityKey: 'client:5',
            idempotencyKey: $key,
            requestTag: str_repeat('d', 32),
            correlationId: 'corr-retry',
            clientId: 5,
        );

        $this->assertSame('replay', $result['action']);
        $this->assertNotNull($result['response']);
        $this->assertSame('REQUEST_FAILED', $result['response']->errorCode);
        $this->assertFalse($result['response']->success);
    }

    public function test_rate_limit_local_is_reclaimed(): void
    {
        $office = Office::factory()->create();
        $key = 'idem-rate-limit-'.uniqid();

        SerproOperationAttempt::query()->create([
            'office_id' => $office->id,
            'environment' => 'PRODUCTION',
            'operation_key' => 'procuracoes.obter',
            'entity_key' => 'client:5',
            'idempotency_key' => $key,
            'request_tag' => str_repeat('e', 32),
            'attempt_state' => SerproAttemptState::Acknowledged,
            'success' => false,
            'http_status' => 429,
            'error_code' => 'RATE_LIMIT_LOCAL',
            'error_message' => 'RATE_LIMIT_LOCAL: limite da operação SERPRO atingido.',
            'acknowledged_at' => now(),
        ]);

        $result = app(SerproOperationAttemptStore::class)->beginOrReplay(
            officeId: (int) $office->id,
            environment: 'PRODUCTION',
            operationKey: 'procuracoes.obter',
            entityKey: 'client:5',
            idempotencyKey: $key,
            requestTag: str_repeat('f', 32),
            correlationId: null,
            clientId: 5,
        );

        $this->assertSame('dispatch', $result['action']);
    }

    public function test_cross_tenant_idempotency_still_blocked(): void
    {
        $owner = Office::factory()->create();
        $other = Office::factory()->create();
        $key = 'idem-cross-tenant-'.uniqid();

        SerproOperationAttempt::query()->create([
            'office_id' => $owner->id,
            'environment' => 'PRODUCTION',
            'operation_key' => 'procuracoes.obter',
            'entity_key' => 'client:5',
            'idempotency_key' => $key,
            'request_tag' => str_repeat('g', 32),
            'attempt_state' => SerproAttemptState::Acknowledged,
            'success' => false,
            'http_status' => 422,
            'error_code' => 'PROCURADOR_TOKEN_MISSING',
            'acknowledged_at' => now(),
        ]);

        $result = app(SerproOperationAttemptStore::class)->beginOrReplay(
            officeId: (int) $other->id,
            environment: 'PRODUCTION',
            operationKey: 'procuracoes.obter',
            entityKey: 'client:5',
            idempotencyKey: $key,
            requestTag: str_repeat('h', 32),
            correlationId: null,
            clientId: 5,
        );

        $this->assertSame('wait', $result['action']);
        $this->assertSame('IDEMPOTENCY_CROSS_TENANT', $result['response']?->errorCode);
    }
}
