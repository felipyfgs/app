<?php

namespace Tests\Unit\Fiscal\SimplesMei\Pgdasd;

use App\Enums\PgdasdDocumentKind;
use App\Services\Fiscal\SimplesMei\Pgdasd\PgdasdDocumentCodecs;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class PgdasdDocumentCodecsTest extends TestCase
{
    #[Test]
    public function extracts_all_nested_documents_from_services_14_and_15(): void
    {
        $codec = new PgdasdDocumentCodecs;
        foreach ([14, 15] as $service) {
            $fixture = $this->fixture($service);
            $documents = $codec->extractDocumentFields(
                $fixture['response_dados'],
                $fixture['operation_key'],
            );

            $this->assertSame([
                PgdasdDocumentKind::Declaracao,
                PgdasdDocumentKind::Recibo,
                PgdasdDocumentKind::NotificacaoMaed,
                PgdasdDocumentKind::DarfMaed,
            ], array_column($documents, 'kind'));
            $this->assertSame([
                'declaracao.pdf',
                'recibo.pdf',
                'maed.pdfNotificacao',
                'maed.pdfDarf',
            ], array_column($documents, 'path'));
        }
    }

    #[Test]
    public function extracts_only_nested_extrato_from_service_16(): void
    {
        $fixture = $this->fixture(16);
        $documents = (new PgdasdDocumentCodecs)->extractDocumentFields(
            $fixture['response_dados'],
            $fixture['operation_key'],
        );

        $this->assertCount(1, $documents);
        $this->assertSame(PgdasdDocumentKind::Extrato, $documents[0]['kind']);
        $this->assertSame('extrato.pdf', $documents[0]['path']);
        $this->assertSame('20260600000000002', $documents[0]['numero_das']);
    }

    #[Test]
    public function sanitization_is_contextual_for_repeated_pdf_field_names(): void
    {
        $fixture = $this->fixture(14);
        $sanitized = (new PgdasdDocumentCodecs)->sanitizeDados($fixture['response_dados'], [
            'declaracao.pdf' => ['artifact_id' => 10],
            'recibo.pdf' => ['artifact_id' => 11],
            'maed.pdfNotificacao' => ['artifact_id' => 12],
            'maed.pdfDarf' => ['artifact_id' => 13],
        ]);

        $this->assertSame(10, $sanitized['declaracao']['pdf']['artifact_id']);
        $this->assertSame(11, $sanitized['recibo']['pdf']['artifact_id']);
        $this->assertSame(12, $sanitized['maed']['pdfNotificacao']['artifact_id']);
        $this->assertSame(13, $sanitized['maed']['pdfDarf']['artifact_id']);
        $this->assertStringNotContainsString('JVBER', json_encode($sanitized, JSON_THROW_ON_ERROR));
    }

    /** @return array<string, mixed> */
    private function fixture(int $service): array
    {
        $path = dirname(__DIR__, 4).'/fixtures/serpro/pgdasd/'.$service.'.json';
        $decoded = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

        return $decoded;
    }
}
