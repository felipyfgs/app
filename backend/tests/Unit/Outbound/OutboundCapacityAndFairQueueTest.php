<?php

namespace Tests\Unit\Outbound;

use App\Contracts\SvrsPortalEgressGovernor;
use App\Domain\Outbound\Competence;
use App\Services\Outbound\OutboundDeadlineFairQueue;
use App\Services\Outbound\OutboundXmlCaptureCapacityPlanner;
use App\Services\Outbound\SvrsPortalEgressConfig;
use Mockery;
use Tests\TestCase;

class OutboundCapacityAndFairQueueTest extends TestCase
{
    public function test_safe_daily_e_60_por_cento_do_nominal(): void
    {
        config([
            'outbound_deadline.auto_queue_capacity_fraction' => 0.60,
            'sefaz.svrs_portal_egress.max_exchanges_per_day' => 50,
        ]);

        $gov = Mockery::mock(SvrsPortalEgressGovernor::class);
        $gov->shouldReceive('isCallAllowed')->andReturn(true);
        $gov->shouldReceive('cohortHealth')->andReturn([
            'state' => 'closed',
            'exchanges_day' => 0,
            'exchanges_day_remaining' => 50,
        ]);

        $cfg = app(SvrsPortalEgressConfig::class);
        $planner = new OutboundXmlCaptureCapacityPlanner($gov, $cfg);

        $this->assertSame(50, $planner->nominalDailyExchanges());
        $this->assertSame(30, $planner->safeDailyExchanges());
    }

    public function test_capacidade_zero_com_breaker_aberto(): void
    {
        config(['sefaz.svrs_portal_egress.max_exchanges_per_day' => 50]);

        $gov = Mockery::mock(SvrsPortalEgressGovernor::class);
        $gov->shouldReceive('isCallAllowed')->andReturn(false);
        $gov->shouldReceive('cohortHealth')->andReturn(['state' => 'open']);

        $planner = new OutboundXmlCaptureCapacityPlanner($gov, app(SvrsPortalEgressConfig::class));
        $this->assertSame(0, $planner->safeDailyExchanges());

        $proj = $planner->project(Competence::fromString('2026-07'), 10, 0);
        $this->assertTrue($proj['at_risk']);
        $this->assertSame(0, $proj['safe_capacity_exchanges']);
    }

    public function test_projecao_nao_acumula_burst_e_marca_risco(): void
    {
        config([
            'outbound_deadline.auto_queue_capacity_fraction' => 0.60,
            'sefaz.svrs_portal_egress.max_exchanges_per_day' => 50,
            'sefaz.svrs_portal_egress.exchanges_per_download' => 2,
        ]);

        $gov = Mockery::mock(SvrsPortalEgressGovernor::class);
        $gov->shouldReceive('isCallAllowed')->andReturn(true);
        $gov->shouldReceive('cohortHealth')->andReturn([
            'state' => 'closed',
            'exchanges_day' => 0,
            'exchanges_day_remaining' => 50,
        ]);

        $planner = new OutboundXmlCaptureCapacityPlanner($gov, app(SvrsPortalEgressConfig::class));
        // 40 primeiras tentativas * 2 exchanges = 80; 1 dia * 30 safe = 30 → risco
        $proj = $planner->project(Competence::fromString('2026-07'), 40, 0);
        $this->assertSame(80, $proj['demand_exchanges']);
        $this->assertSame(30, $proj['safe_capacity_exchanges']);
        $this->assertTrue($proj['at_risk']);
        $this->assertGreaterThan(0, $proj['items_capacity_at_risk']);
    }

    public function test_fair_queue_uma_por_raiz_por_rodada(): void
    {
        $q = new OutboundDeadlineFairQueue;
        $items = collect([
            (object) ['office_id' => 1, 'root_cnpj' => 'AAAAAAAA', 'model' => '55', 'due_at' => '2026-08-01', 'urgency_band' => 'PLANNED', 'access_key' => 'K1', 'svrs_transaction_count' => 0],
            (object) ['office_id' => 1, 'root_cnpj' => 'AAAAAAAA', 'model' => '55', 'due_at' => '2026-08-01', 'urgency_band' => 'PLANNED', 'access_key' => 'K2', 'svrs_transaction_count' => 0],
            (object) ['office_id' => 1, 'root_cnpj' => 'BBBBBBBB', 'model' => '65', 'due_at' => '2026-08-01', 'urgency_band' => 'ATTENTION', 'access_key' => 'K3', 'svrs_transaction_count' => 0],
            (object) ['office_id' => 2, 'root_cnpj' => 'CCCCCCCC', 'model' => '55', 'due_at' => '2026-08-02', 'urgency_band' => 'PLANNED', 'access_key' => 'K4', 'svrs_transaction_count' => 1],
        ]);

        $ordered = $q->order($items);
        $selected = $q->fairSelect($ordered, 3);

        $this->assertCount(3, $selected);
        $rootsRound1 = [];
        foreach (array_slice($selected, 0, 3) as $s) {
            $key = $s->root_cnpj.'|'.$s->office_id.'|'.$s->model;
            $this->assertArrayNotHasKey($key, $rootsRound1);
            $rootsRound1[$key] = true;
        }
        // ATTENTION (K3) deve vir antes de PLANNED
        $this->assertSame('K3', $selected[0]->access_key);
    }

    public function test_spread_deterministico(): void
    {
        $q = new OutboundDeadlineFairQueue;
        $a = $q->spreadSeconds('office|key|1', 3600);
        $b = $q->spreadSeconds('office|key|1', 3600);
        $this->assertSame($a, $b);
        $this->assertGreaterThanOrEqual(0, $a);
        $this->assertLessThan(3600, $a);
    }
}
