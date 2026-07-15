<?php

namespace Tests\Unit\Outbound;

use App\Domain\Outbound\Competence;
use App\Domain\Outbound\OperationalSla;
use App\Enums\OutboundDeadlineSource;
use App\Enums\OutboundUrgencyBand;
use App\Services\Outbound\OutboundDeadlineCalculator;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Tests\TestCase;

class OutboundDeadlineCalculatorTest extends TestCase
{
    public function test_competence_from_access_key_aamm(): void
    {
        // UF 35 + AAMM 2607 = 2026-07
        $key = '35260799888777000166550010000000011234567920';
        $c = Competence::tryFromAccessKey($key);
        $this->assertNotNull($c);
        $this->assertSame('2026-07', $c->value());
        $this->assertSame('2026-08', $c->nextMonth()->value());
    }

    public function test_sla_due_at_dia_1_mes_seguinte_timezone_sp(): void
    {
        $sla = new OperationalSla('America/Sao_Paulo', 1, '23:59:59', 48);
        $comp = Competence::fromString('2026-07');
        $d = $sla->deadlinesFor($comp);

        // 2026-08-01 23:59:59 America/Sao_Paulo = 2026-08-02 02:59:59 UTC (sem DST BR desde 2019)
        $this->assertSame('2026-08-02T02:59:59+00:00', $d['due_at']->toIso8601String());
        $this->assertTrue($d['target_at']->equalTo($d['due_at']->subHours(48)));
    }

    public function test_buffer_menor_que_24h_rejeitado(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new OperationalSla('America/Sao_Paulo', 1, '23:59:59', 12);
    }

    public function test_timezone_invalido(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new OperationalSla('Invalid/Zone', 1, '23:59:59', 48);
    }

    public function test_faixas_planned_attention_contingency_overdue_captured(): void
    {
        $calc = new OutboundDeadlineCalculator;
        $due = CarbonImmutable::parse('2026-08-02 02:59:59', 'UTC');
        $target = $due->subHours(48);

        $this->assertSame(
            OutboundUrgencyBand::Planned,
            $calc->band($due, $target, CarbonImmutable::parse('2026-07-10', 'UTC'))
        );
        $this->assertSame(
            OutboundUrgencyBand::Attention,
            $calc->band($due, $target, CarbonImmutable::parse('2026-07-28', 'UTC'))
        );
        $this->assertSame(
            OutboundUrgencyBand::Contingency,
            $calc->band($due, $target, CarbonImmutable::parse('2026-08-01 00:00:00', 'UTC'))
        );
        $this->assertSame(
            OutboundUrgencyBand::Overdue,
            $calc->band($due, $target, CarbonImmutable::parse('2026-08-03', 'UTC'))
        );
        $this->assertSame(
            OutboundUrgencyBand::Captured,
            $calc->band($due, $target, CarbonImmutable::parse('2026-08-03', 'UTC'), captured: true)
        );
    }

    public function test_plan_from_authorization_definitivo(): void
    {
        $calc = new OutboundDeadlineCalculator;
        $plan = $calc->planFromAuthorizationDate(
            CarbonImmutable::parse('2026-07-15 12:00:00', 'America/Sao_Paulo'),
            'America/Sao_Paulo',
            CarbonImmutable::parse('2026-07-16', 'UTC'),
        );
        $this->assertSame('2026-07', $plan->competence->value());
        $this->assertSame(OutboundDeadlineSource::Authorization, $plan->source);
        $this->assertFalse($plan->provisional);
        $this->assertSame(OutboundUrgencyBand::Planned, $plan->band);
    }

    public function test_plan_from_key_provisorio_e_overdue_se_descoberto_tarde(): void
    {
        $calc = new OutboundDeadlineCalculator;
        $key = '35260199888777000166550010000000011234567920'; // AAMM 2601 → jan/2026 → due ago 1? next month = 2026-02-01
        $plan = $calc->planFromAccessKey(
            $key,
            'America/Sao_Paulo',
            CarbonImmutable::parse('2026-07-01', 'UTC'),
        );
        $this->assertNotNull($plan);
        $this->assertTrue($plan->provisional);
        $this->assertSame(OutboundUrgencyBand::Overdue, $plan->band);
    }

    public function test_acomodacao_zero_em_contingencia(): void
    {
        $calc = new OutboundDeadlineCalculator;
        $target = CarbonImmutable::now('UTC')->addDay();
        $this->assertSame(0, $calc->accommodationHours(OutboundUrgencyBand::Contingency, $target));
        $this->assertSame(0, $calc->accommodationHours(OutboundUrgencyBand::Overdue, $target));
        $this->assertSame(24, $calc->accommodationHours(
            OutboundUrgencyBand::Planned,
            CarbonImmutable::now('UTC')->addDays(30),
        ));
    }

    public function test_fevereiro_e_virada_ano(): void
    {
        $sla = OperationalSla::fromConfig();
        $feb = $sla->deadlinesFor(Competence::fromString('2026-02'));
        $this->assertStringStartsWith('2026-03-0', $feb['due_at']->timezone('America/Sao_Paulo')->format('Y-m-d'));

        $dec = $sla->deadlinesFor(Competence::fromString('2026-12'));
        $this->assertStringStartsWith('2027-01-0', $dec['due_at']->timezone('America/Sao_Paulo')->format('Y-m-d'));
    }
}
