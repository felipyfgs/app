<?php

namespace Tests\Unit\Fiscal\SimplesMei;

use App\Services\Fiscal\SimplesMei\RegimeOptionCodec;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class RegimeOptionCodecTest extends TestCase
{
    public function test_decodifica_opcao_oficial_sem_reter_campos_sensiveis(): void
    {
        $decoded = (new RegimeOptionCodec)->decode([
            'dados' => json_encode([
                'anoCalendario' => 2025,
                'regimeEscolhido' => 'CAIXA',
                'cnpjMatriz' => '00000000000000',
                'demonstrativoPdf' => base64_encode('nao-expor'),
                'textoResolucao' => base64_encode('nao-expor'),
            ], JSON_THROW_ON_ERROR),
        ], 2025);

        $this->assertSame(['calendar_year' => 2025, 'regime_apuracao' => 'CAIXA'], $decoded);
    }

    public function test_rejeita_ano_divergente_regime_invalido_e_ano_malformado(): void
    {
        $codec = new RegimeOptionCodec;

        foreach ([
            [['anoCalendario' => 2024, 'regimeEscolhido' => 'CAIXA'], 2025],
            [['anoCalendario' => 2025, 'regimeEscolhido' => 'OUTRO'], 2025],
            [['anoCalendario' => '2025x', 'regimeEscolhido' => 'CAIXA'], 2025],
        ] as [$body, $year]) {
            try {
                $codec->decode($body, $year);
                $this->fail('Resposta inválida deveria falhar fechada.');
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
