<?php

namespace Tests\Unit\Fiscal\SimplesMei;

use App\Services\Fiscal\SimplesMei\DefisLatestDeclarationCodec;
use InvalidArgumentException;
use Tests\TestCase;

class DefisLatestDeclarationCodecTest extends TestCase
{
    public function test_decodifica_documentos_sem_reter_identificador_defis(): void
    {
        $pdf = base64_encode('%PDF-1.7 exemplo');
        $decoded = app(DefisLatestDeclarationCodec::class)->decode(json_encode([
            'ano' => 2025,
            'idDefis' => 'NAO_DEVE_SAIR',
            'recibo' => $pdf,
            'declaracao' => $pdf,
        ], JSON_THROW_ON_ERROR), 2025);

        $this->assertSame(2025, $decoded['calendar_year']);
        $this->assertSame(['RECIBO', 'DECLARACAO'], array_column($decoded['documents'], 'kind'));
        $this->assertStringNotContainsString('idDefis', json_encode($decoded, JSON_THROW_ON_ERROR));
    }

    public function test_rejeita_pdf_invalido(): void
    {
        $this->expectException(InvalidArgumentException::class);
        app(DefisLatestDeclarationCodec::class)->decode(['recibo' => 'abc=', 'declaracao' => 'abc='], 2025);
    }
}
