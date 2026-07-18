<?php

namespace Tests\Unit\Fiscal\Guides;

use App\DTO\Fiscal\Guides\PagtowebArrecadacaoReceiptDto;
use App\Services\Fiscal\Guides\PagtowebArrecadacaoReceiptCodec;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class PagtowebArrecadacaoReceiptCodecTest extends TestCase
{
    #[Test]
    public function normalizes_only_the_ephemeral_document_number(): void
    {
        $request = app(PagtowebArrecadacaoReceiptCodec::class)->normalizeRequest('12345678901234567');

        $this->assertSame(['numeroDocumento' => '12345678901234567'], $request);
        $this->assertArrayNotHasKey('office_id', $request);
    }

    #[Test]
    public function decodes_the_official_pdf_shape_without_exposing_contents_in_the_dto_contract(): void
    {
        $fixture = json_decode((string) file_get_contents(base_path('tests/fixtures/serpro/pagtoweb/comparrecadacao72.json')), true, flags: JSON_THROW_ON_ERROR);
        $dto = PagtowebArrecadacaoReceiptDto::fromDados($fixture['dados']);

        $this->assertSame('application/pdf', $dto->mimeType);
        $this->assertSame(hash('sha256', "%PDF-1.4\n%sintetico-pagtoweb\n%%EOF\n"), $dto->sha256);
    }

    #[Test]
    public function rejects_invalid_document_number_and_pdf_payload(): void
    {
        $codec = app(PagtowebArrecadacaoReceiptCodec::class);

        foreach (['', str_repeat('1', 18), "123\n456"] as $number) {
            try {
                $codec->normalizeRequest($number);
                $this->fail('Número de documento inválido foi aceito.');
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }

        foreach (['not-base64', base64_encode('not-a-pdf'), null] as $payload) {
            try {
                $codec->decodePdf($payload);
                $this->fail('Documento inválido foi aceito.');
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    #[Test]
    public function rejects_document_larger_than_limit(): void
    {
        $this->expectException(InvalidArgumentException::class);
        app(PagtowebArrecadacaoReceiptCodec::class)->decodePdf(base64_encode('%PDF-'.str_repeat('x', PagtowebArrecadacaoReceiptCodec::MAX_PDF_BYTES)));
    }
}
