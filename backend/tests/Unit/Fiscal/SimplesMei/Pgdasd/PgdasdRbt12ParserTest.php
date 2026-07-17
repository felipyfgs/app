<?php

namespace Tests\Unit\Fiscal\SimplesMei\Pgdasd;

use App\Enums\PgdasdRbt12Status;
use App\Services\Fiscal\SimplesMei\Pgdasd\PgdasdRbt12Parser;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PgdasdRbt12ParserTest extends TestCase
{
    private PgdasdRbt12Parser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new PgdasdRbt12Parser;
    }

    #[Test]
    public function extracts_rbt12_and_ignores_proporcionalizado(): void
    {
        $text = <<<'TXT'
Extrato DAS PA 202605
RBT12 proporcionalizado 9.999,99
Receita Bruta
RBT12 1.234.567,89
Outros valores
TXT;
        $result = $this->parser->parse($text, '202605');
        $this->assertSame(PgdasdRbt12Status::Parsed, $result['status']);
        $this->assertSame(123456789, $result['total_cents']);
    }

    #[Test]
    public function not_found_when_missing_label(): void
    {
        // Sem PA esperado no texto → fail-closed Ambiguous (PERIOD_MISMATCH), não NotFound.
        $result = $this->parser->parse('sem rotulo de receita', '202605');
        $this->assertSame(PgdasdRbt12Status::Ambiguous, $result['status']);
        $this->assertSame('PERIOD_MISMATCH', $result['reason']);

        $result = $this->parser->parse("Extrato PA 202605\nsem rotulo de receita", '202605');
        $this->assertSame(PgdasdRbt12Status::NotFound, $result['status']);
    }

    #[Test]
    public function ambiguous_when_conflicting_values(): void
    {
        $text = "RBT12 100,00\nRBT12 200,00";
        $result = $this->parser->parse($text, '202605');
        $this->assertSame(PgdasdRbt12Status::Ambiguous, $result['status']);
    }
}
