<?php

namespace Tests\Unit\Serpro;

use App\Services\Serpro\Catalog\OperationCoverageMatrix;
use Tests\TestCase;

class OperationCoverageMatrixTest extends TestCase
{
    public function test_implemented_exige_todas_as_dimensoes(): void
    {
        $matrix = app(OperationCoverageMatrix::class);
        $this->assertSame(
            [
                'source',
                'coordinates',
                'auth',
                'power',
                'billing',
                'codec',
                'driver',
                'fixture',
                'tests',
            ],
            OperationCoverageMatrix::REQUIRED_FOR_IMPLEMENTED,
        );

        $summary = $matrix->summary();
        $this->assertArrayHasKey('total', $summary);
        $this->assertGreaterThan(0, $summary['total']);
        // Não promover inventariado sem evidência: implemented_eligible ≤ total
        $this->assertLessThanOrEqual($summary['total'], $summary['implemented_eligible'] + $summary['inventoried']);
    }

    public function test_evaluate_retorna_missing_quando_incompleto(): void
    {
        $matrix = app(OperationCoverageMatrix::class);
        // Chave sintéica inexistente — deve falhar coordinates/source
        $row = $matrix->evaluate('operacao.inexistente.xyz');
        $this->assertFalse($row['eligible_implemented']);
        $this->assertNotEmpty($row['missing']);
        $this->assertSame('INVENTORIED', $row['platform_support']);
    }
}
