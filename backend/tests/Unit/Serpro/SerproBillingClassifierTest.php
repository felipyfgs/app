<?php

namespace Tests\Unit\Serpro;

use App\Enums\SerproBillabilityOutcome;
use App\Services\Serpro\IntegraBillingClassifier;
use Tests\TestCase;

final class SerproBillingClassifierTest extends TestCase
{
    private IntegraBillingClassifier $classifier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->classifier = new IntegraBillingClassifier;
    }

    public function test_apoiar_e_monitorar_nao_faturaveis(): void
    {
        $pre = $this->classifier->classifyPreTransport('Apoiar');
        $this->assertTrue($pre['allowed']);
        $this->assertSame(SerproBillabilityOutcome::NonBillable, $pre['outcome']);
        $this->assertFalse($pre['expects_billable']);

        $this->assertSame(
            SerproBillabilityOutcome::NonBillable,
            $this->classifier->classifyPostTransport('Apoiar', 200),
        );
        $this->assertSame(
            SerproBillabilityOutcome::NonBillable,
            $this->classifier->classifyPostTransport('Monitorar', 200),
        );
        $this->assertFalse($this->classifier->isBillableAttempt('Apoiar', 200));
    }

    public function test_status_oficiais_isentos(): void
    {
        foreach ([204, 304, 400, 401, 404, 429, 500, 503] as $status) {
            $this->assertSame(
                SerproBillabilityOutcome::NonBillable,
                $this->classifier->classifyPostTransport('Consultar', $status),
                "HTTP {$status}",
            );
        }
    }

    public function test_200_202_403_faturaveis_em_rota_faturavel(): void
    {
        foreach ([200, 202, 403] as $status) {
            $this->assertSame(
                SerproBillabilityOutcome::Billable,
                $this->classifier->classifyPostTransport('Consultar', $status),
                "HTTP {$status}",
            );
            $this->assertTrue($this->classifier->isBillableAttempt('Consultar', $status));
        }
    }

    public function test_status_desconhecido_possibly_billable_fail_closed(): void
    {
        $outcome = $this->classifier->classifyPostTransport('Consultar', 418);
        $this->assertSame(SerproBillabilityOutcome::PossiblyBillable, $outcome);
        $this->assertTrue($outcome->isBillableAttempt());
    }

    public function test_timeout_sem_status_possibly_billable(): void
    {
        $outcome = $this->classifier->classifyPostTransport('Consultar', null);
        $this->assertSame(SerproBillabilityOutcome::PossiblyBillable, $outcome);
    }

    public function test_rota_desconhecida_bloqueia_pre_transporte(): void
    {
        $pre = $this->classifier->classifyPreTransport(null);
        $this->assertFalse($pre['allowed']);
        $this->assertTrue($pre['outcome']->blocksProductiveEgress());

        $pre2 = $this->classifier->classifyPreTransport('RotaInexistente');
        $this->assertFalse($pre2['allowed']);

        $pre3 = $this->classifier->classifyPreTransport('Consultar', catalogKnown: false);
        $this->assertFalse($pre3['allowed']);
        $this->assertSame(SerproBillabilityOutcome::UnknownBlocked, $pre3['outcome']);
    }
}
