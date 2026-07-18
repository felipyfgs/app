<?php

namespace Tests\Unit\Fiscal\SimplesMei;

use App\Services\Fiscal\SimplesMei\DefisDeclarationsCodec;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class DefisDeclarationsCodecTest extends TestCase
{
    public function test_sanitiza_lista_oficial_sem_reter_id_defis(): void
    {
        $items = (new DefisDeclarationsCodec)->decode(['dados' => json_encode([[
            'anoCalendario' => 2025, 'idDefis' => 'NAO_RETER', 'tipo' => '2', 'dataHora' => 20250101000000,
        ]], JSON_THROW_ON_ERROR)]);

        $this->assertSame([['calendar_year' => 2025, 'type' => '2', 'transmitted_at' => null]], $items);
    }

    public function test_rejeita_formato_ambiguo(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new DefisDeclarationsCodec)->decode(['dados' => '{"foo":1}']);
    }
}
