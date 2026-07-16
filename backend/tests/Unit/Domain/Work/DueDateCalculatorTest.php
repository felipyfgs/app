<?php

namespace Tests\Unit\Domain\Work;

use App\Domain\Work\CompetenceMonth;
use App\Domain\Work\DueDateCalculator;
use App\Domain\Work\DueRule;
use App\Enums\Work\DueRuleType;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class DueDateCalculatorTest extends TestCase
{
    private DueDateCalculator $calc;

    protected function setUp(): void
    {
        $this->calc = new DueDateCalculator;
    }

    public function test_dia_fixo_com_clamp_fevereiro(): void
    {
        $rule = new DueRule(DueRuleType::FixedDayOfCompetence, 31);
        $c = CompetenceMonth::fromString('2026-02');
        $this->assertSame('2026-02-28', $this->calc->calculate($rule, $c, 'America/Sao_Paulo'));
    }

    public function test_dia_fixo_mes_com_31(): void
    {
        $rule = new DueRule(DueRuleType::FixedDayOfCompetence, 31);
        $c = CompetenceMonth::fromString('2026-01');
        $this->assertSame('2026-01-31', $this->calc->calculate($rule, $c, 'America/Sao_Paulo'));
    }

    public function test_dias_apos_competencia(): void
    {
        $rule = new DueRule(DueRuleType::DaysAfterCompetenceStart, 10);
        $c = CompetenceMonth::fromString('2026-03');
        $this->assertSame('2026-03-11', $this->calc->calculate($rule, $c, 'America/Sao_Paulo'));
    }

    public function test_dias_antes_do_prazo_processo(): void
    {
        $rule = new DueRule(DueRuleType::DaysBeforeProcessDue, 5);
        $c = CompetenceMonth::fromString('2026-06');
        $this->assertSame(
            '2026-06-10',
            $this->calc->calculate($rule, $c, 'America/Sao_Paulo', '2026-06-15'),
        );
    }

    public function test_regra_dependente_sem_prazo_falha(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $rule = new DueRule(DueRuleType::DaysBeforeProcessDue, 3);
        $this->calc->calculate($rule, CompetenceMonth::fromString('2026-01'), 'America/Sao_Paulo');
    }

    public function test_virada_ano(): void
    {
        $rule = new DueRule(DueRuleType::DaysAfterCompetenceStart, 40);
        $c = CompetenceMonth::fromString('2026-12');
        $this->assertSame('2027-01-10', $this->calc->calculate($rule, $c, 'America/Sao_Paulo'));
    }

    public function test_today_respeita_timezone_escritorio(): void
    {
        // 2026-07-16 02:00 UTC = ainda 15/07 em America/Sao_Paulo (-03)
        $utc = new DateTimeImmutable('2026-07-16 02:00:00', new DateTimeZone('UTC'));
        $this->assertSame('2026-07-15', $this->calc->todayInOffice('America/Sao_Paulo', $utc));
        $this->assertSame('2026-07-16', $this->calc->todayInOffice('UTC', $utc));
    }

    public function test_timezone_invalido(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->calc->assertTimezone('Not/A_Zone');
    }
}
