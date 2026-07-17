<?php

namespace Tests\Unit\Fiscal\SimplesMei\Pgdasd;

use App\Enums\PgdasdOperationKind;
use App\Models\PgdasdOperation;
use App\Services\Fiscal\SimplesMei\Pgdasd\PgdasdOperationProjector;
use App\Services\Fiscal\SimplesMei\Pgdasd\PgdasdPeriod;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PgdasdOperationKindResolutionTest extends TestCase
{
    #[Test]
    public function operation_kind_resolves_from_enum_cast(): void
    {
        $op = new PgdasdOperation(['kind' => PgdasdOperationKind::Declaration]);

        $this->assertSame(PgdasdOperationKind::Declaration, $op->operationKind());
    }

    #[Test]
    public function operation_kind_resolves_from_string_value(): void
    {
        $op = new PgdasdOperation;
        $op->setRawAttributes(['kind' => 'DAS'], true);

        $this->assertSame(PgdasdOperationKind::Das, $op->operationKind());
    }

    #[Test]
    public function operation_kind_falls_back_to_normalized_type(): void
    {
        $op = new PgdasdOperation([
            'kind' => null,
            'normalized_operation_type' => 'RECTIFIER',
        ]);

        $this->assertSame(PgdasdOperationKind::Declaration, $op->operationKind());

        $das = new PgdasdOperation([
            'kind' => null,
            'normalized_operation_type' => 'DAS_AVULSO',
        ]);

        $this->assertSame(PgdasdOperationKind::Das, $das->operationKind());
    }

    #[Test]
    public function choose_latest_declaration_ignores_das_and_picks_newest(): void
    {
        $projector = $this->app->make(PgdasdOperationProjector::class);

        $older = new PgdasdOperation([
            'kind' => PgdasdOperationKind::Declaration,
            'declaration_number' => '111',
            'transmitted_at' => CarbonImmutable::parse('2026-06-01 10:00:00'),
        ]);
        $newer = new PgdasdOperation([
            'kind' => PgdasdOperationKind::Declaration,
            'declaration_number' => '222',
            'transmitted_at' => CarbonImmutable::parse('2026-06-15 12:00:00'),
        ]);
        $das = new PgdasdOperation([
            'kind' => PgdasdOperationKind::Das,
            'das_number' => '333',
            'transmitted_at' => CarbonImmutable::parse('2026-06-20 09:00:00'),
        ]);

        $chosen = $projector->chooseLatestDeclaration([$older, $das, $newer]);

        $this->assertSame($newer, $chosen);
    }

    #[Test]
    public function period_key_from_periodo_apuracao_converts_yyyymm(): void
    {
        $this->assertSame('2026-05', PgdasdPeriod::periodKeyFromPeriodoApuracao('202605'));
        $this->assertSame('202605', PgdasdPeriod::periodoApuracaoFromPeriodKey('2026-05'));
    }
}
