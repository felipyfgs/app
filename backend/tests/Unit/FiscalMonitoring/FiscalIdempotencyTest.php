<?php

namespace Tests\Unit\FiscalMonitoring;

use App\Enums\FiscalTrigger;
use App\Services\FiscalMonitoring\FiscalIdempotency;
use Tests\TestCase;

class FiscalIdempotencyTest extends TestCase
{
    public function test_run_key_estavel_e_tenant_aware(): void
    {
        $a = FiscalIdempotency::runKey(1, 10, 'SYS', 'SVC', 'OP', '2026-01', FiscalTrigger::Scheduled, 'slot1');
        $b = FiscalIdempotency::runKey(1, 10, 'sys', 'svc', 'op', '2026-01', FiscalTrigger::Scheduled, 'slot1');
        $c = FiscalIdempotency::runKey(2, 10, 'SYS', 'SVC', 'OP', '2026-01', FiscalTrigger::Scheduled, 'slot1');

        $this->assertSame($a, $b);
        $this->assertNotSame($a, $c);
        $this->assertStringStartsWith('1|10|', $a);
    }

    public function test_cache_key_inclui_office_id(): void
    {
        $key = FiscalIdempotency::cacheKey(42, 'snap', 'SITFIS');
        $this->assertStringContainsString(':42:', $key);
        $this->assertStringContainsString('snap', $key);
    }

    public function test_event_hash_dedupe(): void
    {
        $h1 = FiscalIdempotency::eventHash(1, 'SYS', 'UPDATE', 'ext-1', 'digest');
        $h2 = FiscalIdempotency::eventHash(1, 'SYS', 'UPDATE', 'ext-1', 'digest');
        $h3 = FiscalIdempotency::eventHash(1, 'SYS', 'UPDATE', 'ext-2', 'digest');

        $this->assertSame($h1, $h2);
        $this->assertNotSame($h1, $h3);
        $this->assertSame(64, strlen($h1));
    }
}
