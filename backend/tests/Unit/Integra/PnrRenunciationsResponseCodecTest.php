<?php

namespace Tests\Unit\Integra;

use App\Services\Integra\Registrations\PnrRenunciationReceiptCodec;
use App\Services\Integra\Registrations\PnrRenunciationsResponseCodec;
use RuntimeException;
use Tests\TestCase;

final class PnrRenunciationsResponseCodecTest extends TestCase
{
    public function test_decodifica_pagina_oficial_com_cnpj_interno_validado(): void
    {
        $decoded = app(PnrRenunciationsResponseCodec::class)->decodeHistory([
            'content' => [['id' => 42, 'dataRenuncia' => 1_700_000_000_000, 'cnpjRenunciada' => '11222333000181']],
            'number' => 0,
            'last' => true,
            'totalElements' => 1,
        ]);

        $this->assertSame([['id' => 42, 'contributor_cnpj' => '11222333000181', 'status' => 'RENOUNCED', 'occurred_at' => 1_700_000_000_000]], $decoded['rows']);
    }

    public function test_rejeita_pagina_sem_content(): void
    {
        $this->expectException(RuntimeException::class);
        app(PnrRenunciationsResponseCodec::class)->decodeHistory(['last' => true]);
    }

    public function test_decodifica_situacao_com_renuncia_nula(): void
    {
        $decoded = app(PnrRenunciationsResponseCodec::class)->decodeStatus([
            'resultado' => false,
            'mensagemRetorno' => 'Aguardando análise.',
            'renuncia' => null,
        ]);

        $this->assertFalse($decoded['approved']);
        $this->assertNull($decoded['renunciation']);
    }

    public function test_valida_pdf_base64_sem_persistir_conteudo_em_assertivas(): void
    {
        $decoded = app(PnrRenunciationReceiptCodec::class)->decode(base64_encode('%PDF-1.7\n'));

        $this->assertSame('application/pdf', $decoded['mime_type']);
        $this->assertSame(hash('sha256', '%PDF-1.7\n'), $decoded['sha256']);
    }

    public function test_rejeita_comprovante_que_nao_seja_pdf(): void
    {
        $this->expectException(RuntimeException::class);
        app(PnrRenunciationReceiptCodec::class)->decode(base64_encode('texto'));
    }
}
