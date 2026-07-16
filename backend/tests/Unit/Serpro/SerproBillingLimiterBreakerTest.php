<?php

namespace Tests\Unit\Serpro;

use App\Models\SerproCircuitBreakerState;
use App\Services\Serpro\SerproCircuitBreaker;
use App\Services\Serpro\SerproRateLimiter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Tests\TestCase;

final class SerproBillingLimiterBreakerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_rate_limit_zero_nao_e_ilimitado_em_egress_produtivo(): void
    {
        config([
            'serpro.rate_limit.global_per_minute' => 0,
            'serpro.rate_limit.per_office_per_minute' => 0,
            'serpro.rate_limit.default_operation_per_minute' => 0,
            'serpro_usage.productive_rate_limit_required' => true,
        ]);

        $limiter = app(SerproRateLimiter::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('RATE_LIMIT_NOT_CONFIGURED');
        $limiter->attempt(1, 'SITFIS.CONSULTAR', productiveEgress: true);
    }

    public function test_rate_limit_atomico_respeita_teto(): void
    {
        config([
            'serpro.rate_limit.global_per_minute' => 2,
            'serpro.rate_limit.per_office_per_minute' => 0,
            'serpro.rate_limit.default_operation_per_minute' => 0,
            'serpro_usage.rate_limit_version' => 'test-v1',
        ]);

        $limiter = app(SerproRateLimiter::class);
        $limiter->attempt(1, 'OP', productiveEgress: false);
        $limiter->attempt(1, 'OP', productiveEgress: false);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('limite global');
        $limiter->attempt(1, 'OP', productiveEgress: false);
    }

    public function test_breaker_half_open_limita_probes(): void
    {
        config([
            'serpro.circuit_breaker.failure_threshold' => 2,
            'serpro.circuit_breaker.open_seconds' => 60,
            'serpro.circuit_breaker.half_open_max_probes' => 1,
        ]);

        $breaker = app(SerproCircuitBreaker::class);
        $this->assertTrue($breaker->isCallAllowed('SITFIS'));

        $breaker->recordFailure('SITFIS', '5xx', technicalFailure: true);
        $breaker->recordFailure('SITFIS', '5xx', technicalFailure: true);
        $this->assertSame('open', $breaker->solutionStatus('SITFIS')['state']);
        $this->assertFalse($breaker->isCallAllowed('SITFIS'));

        // Força half-open no global e na solução (ambos abriram no threshold)
        foreach (['serpro.breaker.global', 'serpro.breaker.solution.SITFIS'] as $key) {
            Cache::put($key, [
                'state' => 'open',
                'open_until' => time() - 1,
                'failures' => 2,
                'half_open_probes' => 0,
            ], 600);
        }

        $this->assertTrue($breaker->isCallAllowed('SITFIS', isProbe: true));
        $this->assertFalse($breaker->isCallAllowed('SITFIS', isProbe: true));

        // 403 de negócio não conta
        $breaker->recordSuccess('SITFIS');
        $this->assertTrue($breaker->isTechnicalFailure(500));
        $this->assertFalse($breaker->isTechnicalFailure(403));
        $breaker->recordFailure('SITFIS', 'auth', technicalFailure: $breaker->isTechnicalFailure(403));
        $this->assertSame('closed', $breaker->solutionStatus('SITFIS')['state']);
    }

    public function test_breaker_persiste_estado_critico(): void
    {
        config([
            'serpro.circuit_breaker.failure_threshold' => 1,
            'serpro.circuit_breaker.open_seconds' => 120,
        ]);

        $breaker = app(SerproCircuitBreaker::class);
        $breaker->recordFailure('DCTFWEB', 'timeout', technicalFailure: true);

        $row = SerproCircuitBreakerState::query()
            ->where('scope_key', 'serpro.breaker.solution.DCTFWEB')
            ->first();
        $this->assertNotNull($row);
        $this->assertSame('open', $row->state);
        $this->assertSame('DCTFWEB', $row->solution_code);
    }
}
