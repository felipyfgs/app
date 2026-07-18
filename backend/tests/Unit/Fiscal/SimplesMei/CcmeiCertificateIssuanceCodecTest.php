<?php

namespace Tests\Unit\Fiscal\SimplesMei;

use App\DTO\Fiscal\SimplesMei\CcmeiCertificateIssuanceDto;
use App\Services\Fiscal\SimplesMei\CcmeiCertificateIssuanceCodec;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class CcmeiCertificateIssuanceCodecTest extends TestCase
{
    #[Test]
    public function decodes_official_shape_without_exposing_document_in_public_dto(): void
    {
        $dto = CcmeiCertificateIssuanceDto::fromDados([
            'cnpj' => '00000000000000',
            'pdf' => base64_encode('%PDF-1.7 synthetic'),
        ]);

        $this->assertSame('application/pdf', $dto->mimeType);
        $this->assertSame(hash('sha256', '%PDF-1.7 synthetic'), $dto->sha256);
    }

    #[Test]
    public function accepts_escaped_dados_json_from_official_envelope(): void
    {
        $decoded = app(CcmeiCertificateIssuanceCodec::class)->decode(json_encode([
            'cnpj' => '00000000000000',
            'pdf' => base64_encode('%PDF-1.4'),
        ], JSON_THROW_ON_ERROR));

        $this->assertSame('00000000000000', $decoded['contributor_cnpj']);
    }

    #[Test]
    public function rejects_invalid_layout_and_non_pdf_base64(): void
    {
        foreach ([[], ['cnpj' => '00000000000000', 'pdf' => base64_encode('not-pdf')], ['cnpj' => 'invalid', 'pdf' => base64_encode('%PDF-1.4')]] as $payload) {
            try {
                app(CcmeiCertificateIssuanceCodec::class)->decode($payload);
                $this->fail('O layout inválido deveria ser rejeitado.');
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    #[Test]
    public function rejects_document_larger_than_limit(): void
    {
        $this->expectException(InvalidArgumentException::class);
        app(CcmeiCertificateIssuanceCodec::class)->decode([
            'cnpj' => '00000000000000',
            'pdf' => base64_encode('%PDF-'.str_repeat('x', CcmeiCertificateIssuanceCodec::MAX_PDF_BYTES)),
        ]);
    }
}
