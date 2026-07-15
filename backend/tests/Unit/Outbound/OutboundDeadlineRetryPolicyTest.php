<?php

namespace Tests\Unit\Outbound;

use App\Services\Outbound\OutboundXmlRecoveryOrchestrator;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Política de no máximo 2 tentativas / 24h quando OUTBOUND_DEADLINE_RETRY_POLICY=true.
 */
class OutboundDeadlineRetryPolicyTest extends TestCase
{
    public function test_backoff_deadline_policy_minimo_24h(): void
    {
        config([
            'outbound_deadline.deadline_retry_policy' => true,
            'outbound_deadline.min_hours_between_svrs_attempts' => 24,
        ]);

        $orch = app(OutboundXmlRecoveryOrchestrator::class);
        $m = new ReflectionMethod($orch, 'backoffSeconds');
        $m->setAccessible(true);
        $this->assertSame(86400, $m->invoke($orch, 1));
        $this->assertSame(86400, $m->invoke($orch, 2));
    }

    public function test_max_attempts_deadline_policy_e_dois(): void
    {
        config([
            'outbound_deadline.deadline_retry_policy' => true,
            'outbound_deadline.max_svrs_transactions_per_key' => 2,
        ]);

        $orch = app(OutboundXmlRecoveryOrchestrator::class);
        $m = new ReflectionMethod($orch, 'maxRecoverableAttempts');
        $m->setAccessible(true);
        $this->assertSame(2, $m->invoke($orch));
    }

    public function test_sem_flag_mantem_backoff_legado(): void
    {
        config([
            'outbound_deadline.deadline_retry_policy' => false,
            'sefaz.svrs_nfce_xml.retry_backoff_seconds' => [900, 3600, 21600, 43200],
            'sefaz.svrs_nfce_xml.retry_jitter_ratio' => 0,
        ]);

        $orch = app(OutboundXmlRecoveryOrchestrator::class);
        $m = new ReflectionMethod($orch, 'backoffSeconds');
        $m->setAccessible(true);
        $this->assertSame(900, $m->invoke($orch, 1));
        $this->assertSame(3600, $m->invoke($orch, 2));
    }
}
