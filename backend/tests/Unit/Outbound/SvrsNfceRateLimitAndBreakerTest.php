<?php

namespace Tests\Unit\Outbound;

use App\Enums\SvrsNfceFailureReason;
use App\Services\Audit\AuditLogger;
use App\Services\Outbound\SvrsNfceCircuitBreaker;
use App\Services\Outbound\SvrsNfceConfig;
use App\Services\Outbound\SvrsNfceRateLimiter;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SvrsNfceRateLimitAndBreakerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config([
            'sefaz.svrs_nfce_xml.min_interval_global_seconds' => 5,
            'sefaz.svrs_nfce_xml.min_interval_root_seconds' => 30,
            'sefaz.svrs_nfce_xml.max_inflight_global' => 1,
            'sefaz.svrs_nfce_xml.breaker_failure_threshold' => 3,
            'sefaz.svrs_nfce_xml.breaker_open_seconds' => 60,
        ]);
    }

    public function test_inflight_semaphore(): void
    {
        config([
            'sefaz.svrs_nfce_xml.min_interval_global_seconds' => 0,
            'sefaz.svrs_nfce_xml.min_interval_root_seconds' => 0,
        ]);
        $limiter = new SvrsNfceRateLimiter(new SvrsNfceConfig);
        $a = $limiter->acquire(10);
        $this->assertTrue($a['allowed']);
        $b = $limiter->acquire(11);
        $this->assertFalse($b['allowed']);
        $this->assertSame('inflight', $b['reason']);
        $limiter->release();
        $c = $limiter->acquire(11);
        $this->assertTrue($c['allowed']);
        $limiter->release();
    }

    public function test_global_interval(): void
    {
        config([
            'sefaz.svrs_nfce_xml.min_interval_global_seconds' => 5,
            'sefaz.svrs_nfce_xml.min_interval_root_seconds' => 0,
            'sefaz.svrs_nfce_xml.max_inflight_global' => 10,
        ]);
        $limiter = new SvrsNfceRateLimiter(new SvrsNfceConfig);
        $this->assertTrue($limiter->acquire(1)['allowed']);
        $limiter->release();
        $again = $limiter->acquire(2);
        $this->assertFalse($again['allowed']);
        $this->assertSame('global_interval', $again['reason']);
        $this->assertGreaterThan(0, $again['retry_after_seconds']);
    }

    public function test_breaker_opens_on_contract_changed(): void
    {
        $breaker = new SvrsNfceCircuitBreaker(new SvrsNfceConfig, app(AuditLogger::class));
        $this->assertTrue($breaker->isCallAllowed(5));
        $breaker->recordFailure(SvrsNfceFailureReason::ResponseContractChanged, 5);
        $this->assertSame('open', $breaker->globalStatus()['state']);
        $this->assertFalse($breaker->isCallAllowed(5));
        $this->assertFalse($breaker->isCallAllowed(5, isProbe: true)); // open, not half_open yet
    }

    public function test_auth_forbidden_respeita_threshold(): void
    {
        config(['sefaz.svrs_nfce_xml.breaker_failure_threshold' => 3]);
        $breaker = new SvrsNfceCircuitBreaker(new SvrsNfceConfig, app(AuditLogger::class));
        $breaker->recordFailure(SvrsNfceFailureReason::AuthForbidden, null);
        $this->assertSame('closed', $breaker->globalStatus()['state']);
        $breaker->recordFailure(SvrsNfceFailureReason::AuthForbidden, null);
        $this->assertSame('closed', $breaker->globalStatus()['state']);
        $breaker->recordFailure(SvrsNfceFailureReason::AuthForbidden, null);
        $this->assertSame('open', $breaker->globalStatus()['state']);
    }

    public function test_half_open_allows_single_probe(): void
    {
        $breaker = new SvrsNfceCircuitBreaker(new SvrsNfceConfig, app(AuditLogger::class));
        Cache::put('sefaz.svrs_nfce_xml.breaker.global', [
            'state' => 'open',
            'open_until' => time() - 1,
            'failures' => 3,
        ], 120);

        $st = $breaker->globalStatus();
        $this->assertSame('half_open', $st['state']);
        $this->assertFalse($breaker->isCallAllowed(null, isProbe: false));
        $this->assertTrue($breaker->isCallAllowed(null, isProbe: true));
        // segundo probe bloqueado pelo slot
        $this->assertFalse($breaker->isCallAllowed(null, isProbe: true));

        $breaker->recordSuccess();
        $this->assertSame('closed', $breaker->globalStatus()['state']);
    }

    public function test_root_breaker_identity_trip_imediato(): void
    {
        $breaker = new SvrsNfceCircuitBreaker(new SvrsNfceConfig, app(AuditLogger::class));
        $breaker->recordFailure(SvrsNfceFailureReason::IdentityMismatch, 99);
        $this->assertSame('open', $breaker->rootStatus(99)['state']);
        $this->assertTrue($breaker->isCallAllowed(98)); // other root ok if global closed
        $this->assertFalse($breaker->isCallAllowed(99));
    }
}
