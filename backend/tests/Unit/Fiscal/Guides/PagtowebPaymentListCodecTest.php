<?php

namespace Tests\Unit\Fiscal\Guides;

use App\Services\Fiscal\Guides\PagtowebPaymentListCodec;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PagtowebPaymentListCodecTest extends TestCase
{
    #[Test]
    public function it_normalizes_the_period_and_masks_each_payment_document(): void
    {
        $codec = new PagtowebPaymentListCodec;
        $filters = $codec->normalizeFilters(['intervalo_data_arrecadacao' => ['data_inicial' => '2026-01-01', 'data_final' => '2026-01-31'], 'page' => 2, 'per_page' => 25]);
        $items = $codec->decodePayments(['pagamentos' => [['numeroDocumento' => '12345678901234567', 'tipo' => ['descricaoAbreviada' => 'DARF'], 'receitaPrincipal' => ['codigo' => '1082'], 'dataArrecadacao' => '2026-01-10T00:00:00-03:00', 'valorTotal' => 10.5]]]);

        $this->assertSame(['dataInicial' => '2026-01-01', 'dataFinal' => '2026-01-31'], $filters['business_data']['intervaloDataArrecadacao']);
        $this->assertSame(25, $filters['business_data']['tamanhoDaPagina']);
        $this->assertSame(25, $filters['business_data']['primeiroDaPagina']);
        $this->assertSame('•••••••••••••4567', $items[0]['document_masked']);
        $this->assertArrayNotHasKey('numeroDocumento', $items[0]);
    }

    #[Test]
    public function it_rejects_document_filters_and_invalid_payment_rows(): void
    {
        $codec = new PagtowebPaymentListCodec;
        foreach ([['numero_documento' => '123'], [], ['intervalo_data_arrecadacao' => ['data_inicial' => '2026-02-01', 'data_final' => '2026-01-01']]] as $filters) {
            try {
                $codec->normalizeFilters($filters);
                $this->fail('Filtro inválido foi aceito.');
            } catch (InvalidArgumentException) {
            }
        }
        $this->expectException(InvalidArgumentException::class);
        $codec->decodePayments(['pagamentos' => [['valorTotal' => 10]]]);
    }
}
