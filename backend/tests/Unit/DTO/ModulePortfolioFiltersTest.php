<?php

namespace Tests\Unit\DTO;

use App\DTO\Fiscal\Module\ModulePortfolioFilters;
use PHPUnit\Framework\TestCase;

class ModulePortfolioFiltersTest extends TestCase
{
    public function test_from_request_normaliza_coverage_e_modality(): void
    {
        $f = ModulePortfolioFilters::fromRequest([
            'coverage' => 'partial',
            'modality' => 'parcsn',
            'q' => '  acme  ',
        ]);

        $this->assertSame('PARTIAL', $f->coverage);
        $this->assertSame('PARCSN', $f->modality);
        $this->assertSame('acme', $f->q);
    }

    public function test_from_request_descarta_coverage_e_modality_invalidos(): void
    {
        $f = ModulePortfolioFilters::fromRequest([
            'coverage' => 'EVERYTHING',
            'modality' => 'NOT-A-MODALITY',
            'coverage_empty' => '',
        ]);

        $this->assertNull($f->coverage);
        $this->assertNull($f->modality);
    }

    public function test_from_request_null_quando_ausente(): void
    {
        $f = ModulePortfolioFilters::fromRequest([]);

        $this->assertNull($f->coverage);
        $this->assertNull($f->modality);
        $this->assertNull($f->q);
    }

    public function test_with_page_preserva_coverage_e_modality(): void
    {
        $base = ModulePortfolioFilters::fromRequest([
            'coverage' => 'FULL',
            'modality' => 'PARCMEI',
            'page' => 1,
            'per_page' => 15,
        ]);

        $next = $base->withPage(2, 50);

        $this->assertSame(2, $next->page);
        $this->assertSame(50, $next->perPage);
        $this->assertSame('FULL', $next->coverage);
        $this->assertSame('PARCMEI', $next->modality);
    }

    public function test_from_request_aceita_listas_csv_e_array(): void
    {
        $csv = ModulePortfolioFilters::fromRequest([
            'situation' => 'pending,ATTENTION,pending',
            'modality' => 'parcsn,PARCMEI',
            'delivery_status' => 'DELIVERED,PENDING',
        ]);

        $this->assertSame('ATTENTION,PENDING', $csv->situation);
        $this->assertSame(['ATTENTION', 'PENDING'], $csv->situationList());
        $this->assertSame('PARCMEI,PARCSN', $csv->modality);
        $this->assertSame(['PARCMEI', 'PARCSN'], $csv->modalityList());
        $this->assertSame(['DELIVERED', 'PENDING'], $csv->deliveryStatusList());

        $array = ModulePortfolioFilters::fromRequest([
            'situation' => ['ERROR', 'PENDING'],
            'coverage' => ['partial', 'full'],
        ]);

        $this->assertSame('ERROR,PENDING', $array->situation);
        $this->assertSame('FULL,PARTIAL', $array->coverage);
    }

    public function test_from_request_descarta_tokens_invalidos_em_lista(): void
    {
        $f = ModulePortfolioFilters::fromRequest([
            'situation' => 'PENDING,NOT_A_REAL_SITUATION',
            'modality' => 'PARCSN,FAKE',
        ]);

        $this->assertSame('PENDING', $f->situation);
        $this->assertSame('PARCSN', $f->modality);
    }
}
