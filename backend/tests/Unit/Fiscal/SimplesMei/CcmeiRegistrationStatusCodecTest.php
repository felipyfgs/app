<?php

namespace Tests\Unit\Fiscal\SimplesMei;

use App\Enums\FiscalSituation;
use App\Services\Fiscal\SimplesMei\CcmeiRegistrationStatusCodec;
use InvalidArgumentException;
use Tests\TestCase;

class CcmeiRegistrationStatusCodecTest extends TestCase
{
    public function test_normaliza_sem_reter_cnpj(): void
    {
        $decoded = app(CcmeiRegistrationStatusCodec::class)->decode(['data' => [[
            'cnpj' => '00000000000000', 'situacao' => 'ATIVA', 'enquadradoMei' => true,
        ]]]);

        $this->assertSame('ATIVA', $decoded['status']);
        $this->assertTrue($decoded['enquadrado_mei']);
        $this->assertSame(FiscalSituation::UpToDate, $decoded['situation']);
        $this->assertArrayNotHasKey('cnpj', $decoded);
    }

    public function test_rejeita_retornos_ambiguous_sem_booleano(): void
    {
        $this->expectException(InvalidArgumentException::class);
        app(CcmeiRegistrationStatusCodec::class)->decode(['data' => [['situacao' => 'ATIVA', 'enquadradoMei' => 'true']]]);
    }
}
