<?php

namespace Tests\Unit\Fiscal\Guides;

use App\Services\Fiscal\Guides\PagtowebPaymentCountCodec;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PagtowebPaymentCountCodecTest extends TestCase
{
    #[Test]
    public function it_normalizes_only_non_sensitive_official_filters_and_a_count(): void
    {
        $codec = new PagtowebPaymentCountCodec;
        $filters = $codec->normalizeFilters([
            'intervalo_data_arrecadacao' => ['data_inicial' => '2026-01-01', 'data_final' => '2026-01-31'],
            'codigo_receita_lista' => ['1082', '0561'],
        ]);

        $this->assertSame(['dataInicial' => '2026-01-01', 'dataFinal' => '2026-01-31'], $filters['business_data']['intervaloDataArrecadacao']);
        $this->assertSame(['1082', '0561'], $filters['filter_summary']['codigo_receita_lista']);
        $this->assertSame(['payment_count' => 12], $codec->decodeCount('12'));
    }

    #[Test]
    public function it_rejects_document_numbers_unknown_fields_and_ambiguous_response(): void
    {
        $codec = new PagtowebPaymentCountCodec;
        foreach ([['numero_documento' => '123'], ['segredo' => 'x'], []] as $filters) {
            try {
                $codec->normalizeFilters($filters);
                $this->fail('Filtro inválido foi aceito.');
            } catch (InvalidArgumentException) {
            }
        }
        $this->expectException(InvalidArgumentException::class);
        $codec->decodeCount('{"count":12}');
    }
}
