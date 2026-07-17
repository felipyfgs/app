<?php

namespace Tests\Unit\Fiscal\SimplesMei\Pgdasd;

use App\Services\Fiscal\SimplesMei\Pgdasd\PgdasdDocumentSanitizer;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class PgdasdDocumentSanitizerTest extends TestCase
{
    private PgdasdDocumentSanitizer $sanitizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sanitizer = app(PgdasdDocumentSanitizer::class);
    }

    #[Test]
    public function rejects_invalid_base64(): void
    {
        $this->expectException(RuntimeException::class);
        $this->sanitizer->decodeStrictBase64('@@@not-base64@@@');
    }

    #[Test]
    public function rejects_non_pdf(): void
    {
        $bytes = base64_decode(base64_encode('HELLO WORLD'), true);
        $this->expectException(RuntimeException::class);
        $this->sanitizer->assertPdf($bytes ?: '');
    }

    #[Test]
    public function accepts_pdf_signature(): void
    {
        $pdf = '%PDF-1.4 minimal';
        $this->sanitizer->assertPdf($pdf);
        $this->assertSame($pdf, $this->sanitizer->decodeStrictBase64(base64_encode($pdf)));
    }

    #[Test]
    public function accepts_exactly_ten_mib_and_rejects_one_byte_over_limit(): void
    {
        $exact = '%PDF-'.str_repeat('0', PgdasdDocumentSanitizer::MAX_PDF_BYTES - 5);
        $this->assertSame(PgdasdDocumentSanitizer::MAX_PDF_BYTES, strlen(
            $this->sanitizer->decodeStrictBase64(base64_encode($exact)),
        ));

        $this->expectException(RuntimeException::class);
        $this->sanitizer->decodeStrictBase64(base64_encode($exact.'0'));
    }
}
