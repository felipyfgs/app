<?php

namespace Tests\Unit\Fiscal\Guides;

use App\DTO\Serpro\IntegraResponse;
use App\Services\Fiscal\Guides\PagtowebEphemeralResponseRedactor;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class PagtowebEphemeralResponseRedactorTest extends TestCase
{
    #[Test]
    public function removes_document_number_from_all_remote_echo_surfaces(): void
    {
        $number = '12345678901234567';
        $response = (new PagtowebEphemeralResponseRedactor)->redact(new IntegraResponse(
            success: false,
            httpStatus: 400,
            body: ['dados' => "erro {$number}", 'nested' => ['value' => $number]],
            headers: ['x-remote-message' => "documento={$number}"],
            errorMessage: "Número {$number} rejeitado",
            mensagens: [['texto' => "Documento {$number} inválido"]],
            dados: ['numeroDocumento' => $number],
        ), $number);
        $serialized = json_encode([
            'body' => $response->body,
            'headers' => $response->headers,
            'error' => $response->errorMessage,
            'mensagens' => $response->mensagens,
            'dados' => $response->dados,
        ], JSON_THROW_ON_ERROR);

        $this->assertStringNotContainsString($number, $serialized);
        $this->assertSame('Número [número de documento omitido] rejeitado', $response->errorMessage);
    }
}
