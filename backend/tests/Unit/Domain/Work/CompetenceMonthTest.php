<?php

namespace Tests\Unit\Domain\Work;

use App\Domain\Work\CompetenceMonth;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class CompetenceMonthTest extends TestCase
{
    public function test_parse_valido(): void
    {
        $c = CompetenceMonth::fromString('2026-02');
        $this->assertSame('2026-02', $c->value());
        $this->assertSame('2026-02-01', $c->startDate());
        $this->assertSame('2026-02-28', $c->endDate());
    }

    public function test_fevereiro_bissexto(): void
    {
        $c = CompetenceMonth::fromString('2024-02');
        $this->assertSame('2024-02-29', $c->endDate());
    }

    public function test_invalido(): void
    {
        $this->expectException(InvalidArgumentException::class);
        CompetenceMonth::fromString('2026-13');
    }

    public function test_formato_invalido(): void
    {
        $this->expectException(InvalidArgumentException::class);
        CompetenceMonth::fromString('02/2026');
    }

    public function test_ano_fora_do_limite(): void
    {
        $this->expectException(InvalidArgumentException::class);
        CompetenceMonth::fromString('1999-01');
    }
}
