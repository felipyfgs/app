<?php

namespace Tests\Unit\Fiscal\Dctfweb;

use App\Enums\DctfwebCategory;
use App\Services\Fiscal\Dctfweb\DctfwebConsReciboCodec;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class DctfwebConsReciboCodecTest extends TestCase
{
    private DctfwebConsReciboCodec $codec;

    protected function setUp(): void
    {
        parent::setUp();
        $this->codec = new DctfwebConsReciboCodec;
    }

    #[Test]
    public function build_payload_uses_official_keys_and_geral_mensal(): void
    {
        $payload = $this->codec->buildPayload('2026', '2', DctfwebCategory::GeralMensal);

        $this->assertSame([
            'categoria' => '40',
            'anoPA' => '2026',
            'mesPA' => '02',
        ], $payload);
        $this->assertArrayNotHasKey('competencia', $payload);
        $this->assertArrayNotHasKey('periodo', $payload);
    }

    #[Test]
    public function decode_valid_pdf_base64(): void
    {
        $pdf = '%PDF-1.4 minimal content for test';
        $bytes = $this->codec->decodePdf(base64_encode($pdf));

        $this->assertSame($pdf, $bytes);
    }

    #[Test]
    public function reject_invalid_signature(): void
    {
        $this->expectException(RuntimeException::class);
        $this->codec->decodePdf(base64_encode('NOT_A_PDF'));
    }

    #[Test]
    public function reject_non_canonical_base64(): void
    {
        $this->expectException(RuntimeException::class);
        $this->codec->decodePdf('@@@@');
    }

    #[Test]
    public function extract_and_sanitize_removes_base64(): void
    {
        $pdf = '%PDF-1.4 x';
        $b64 = base64_encode($pdf);
        $dados = ['PDFByteArrayBase64' => $b64, 'status' => 200];
        $extracted = $this->codec->extractPdfField($dados);
        $this->assertSame($b64, $extracted['base64']);

        $sanitized = $this->codec->sanitizeDados($dados, [
            'sanitized' => true,
            'available' => true,
        ]);
        $this->assertIsArray($sanitized['PDFByteArrayBase64']);
        $this->assertTrue($sanitized['PDFByteArrayBase64']['sanitized']);
        $this->assertStringNotContainsString($b64, json_encode($sanitized));
    }
}
