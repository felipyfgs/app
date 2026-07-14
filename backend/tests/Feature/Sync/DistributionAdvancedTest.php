<?php

namespace Tests\Feature\Sync;

use App\Domain\Adn\DistributionDocumentDto;
use App\Domain\Adn\DistributionPageDto;
use App\Enums\AdnDocumentType;
use App\Enums\SyncCursorStatus;
use App\Models\Client;
use App\Models\DfeDocument;
use App\Models\Establishment;
use App\Models\NfseNote;
use App\Models\Office;
use App\Models\SyncCursor;
use App\Services\Adn\DistributionPageProcessor;
use Database\Factories\EstablishmentFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DistributionAdvancedTest extends TestCase
{
    use RefreshDatabase;

    public function test_schema_desconhecido_marca_review_e_avanca_cursor(): void
    {
        [$cursor, $establishment] = $this->seedCursor();
        $xml = file_get_contents(base_path('tests/fixtures/adn/nfse_issuer.xml'));
        $b64 = base64_encode(gzencode($xml));

        $page = new DistributionPageDto(
            status: 'DOCUMENTOS_LOCALIZADOS',
            maxNsu: 5,
            ultimoNsu: 1,
            documents: [
                new DistributionDocumentDto(1, AdnDocumentType::Nfse, 'NFSe_v9.99.xsd', $b64),
            ],
            hasMore: false,
        );

        $result = app(DistributionPageProcessor::class)->process($cursor, $establishment, $page);
        $this->assertSame(1, $result['advanced_to']);

        $doc = DfeDocument::query()->first();
        $this->assertSame('REVIEW', $doc->parse_status);
        $this->assertStringContainsString('desconhecido', (string) $doc->parse_alert);
        $this->assertSame(1, $cursor->fresh()->last_nsu);
    }

    public function test_idempotencia_mesmo_sha_nao_duplica_nota(): void
    {
        [$cursor, $establishment] = $this->seedCursor();
        $xml = file_get_contents(base_path('tests/fixtures/adn/nfse_issuer.xml'));
        $b64 = base64_encode(gzencode($xml));

        $page1 = new DistributionPageDto(
            status: 'DOCUMENTOS_LOCALIZADOS',
            maxNsu: 10,
            ultimoNsu: 1,
            documents: [new DistributionDocumentDto(1, AdnDocumentType::Nfse, 'NFSe_v1.00.xsd', $b64)],
            hasMore: true,
        );
        app(DistributionPageProcessor::class)->process($cursor, $establishment, $page1);

        $client2 = Client::factory()->forOffice(Office::query()->find($cursor->office_id))->create(['root_cnpj' => '99888777']);
        // mesmo escritório, outro estabelecimento com mesmo CNPJ de tomador não aplica; reprocessa mesmo doc via NSU diferente
        $page2 = new DistributionPageDto(
            status: 'DOCUMENTOS_LOCALIZADOS',
            maxNsu: 10,
            ultimoNsu: 2,
            documents: [new DistributionDocumentDto(2, AdnDocumentType::Nfse, 'NFSe_v1.00.xsd', $b64)],
            hasMore: false,
        );
        app(DistributionPageProcessor::class)->process($cursor->fresh(), $establishment, $page2);

        $this->assertSame(1, DfeDocument::query()->count());
        $this->assertSame(1, NfseNote::query()->count());
        $this->assertSame(2, $cursor->fresh()->last_nsu);
        unset($client2);
    }

    public function test_evento_atualiza_situacao_derivada_sem_alterar_sha(): void
    {
        [$cursor, $establishment] = $this->seedCursor('11222333000181');
        $noteXml = file_get_contents(base_path('tests/fixtures/adn/nfse_issuer.xml'));
        $eventXml = file_get_contents(base_path('tests/fixtures/adn/event_cancel.xml'));

        $page = new DistributionPageDto(
            status: 'DOCUMENTOS_LOCALIZADOS',
            maxNsu: 10,
            ultimoNsu: 2,
            documents: [
                new DistributionDocumentDto(1, AdnDocumentType::Nfse, 'NFSe_v1.00.xsd', base64_encode(gzencode($noteXml))),
                new DistributionDocumentDto(2, AdnDocumentType::Event, 'evento_v1.00.xsd', base64_encode(gzencode($eventXml))),
            ],
            hasMore: false,
        );

        app(DistributionPageProcessor::class)->process($cursor, $establishment, $page);

        $note = NfseNote::query()->first();
        $this->assertSame('CANCELLED', $note->status);
        $doc = DfeDocument::query()->where('document_type', AdnDocumentType::Nfse)->first();
        $this->assertSame(hash('sha256', $noteXml), $doc->sha256);
    }

    public function test_fim_distribuicao_nenhum_documento_nao_avanca_e_nao_exige_docs(): void
    {
        [$cursor, $establishment] = $this->seedCursor();
        $cursor->last_nsu = 5;
        $cursor->save();

        $page = new DistributionPageDto(
            status: 'NENHUM_DOCUMENTO_LOCALIZADO',
            maxNsu: 5,
            ultimoNsu: 5,
            documents: [],
            hasMore: false,
        );

        $result = app(DistributionPageProcessor::class)->process($cursor, $establishment, $page);
        $this->assertSame(5, $result['advanced_to']);
        $this->assertSame(0, $result['documents']);
    }

    public function test_cinco_falhas_decode_bloqueiam_cursor(): void
    {
        [$cursor, $establishment] = $this->seedCursor();
        config(['adn.decode_failure_threshold' => 5]);

        for ($i = 1; $i <= 5; $i++) {
            $page = new DistributionPageDto(
                status: 'DOCUMENTOS_LOCALIZADOS',
                maxNsu: 100,
                ultimoNsu: $cursor->fresh()->last_nsu + 1,
                documents: [
                    new DistributionDocumentDto($cursor->fresh()->last_nsu + 1, AdnDocumentType::Nfse, 'NFSe_v1.00.xsd', '@@@'),
                ],
                hasMore: true,
            );
            try {
                app(DistributionPageProcessor::class)->process($cursor->fresh(), $establishment, $page);
            } catch (\Throwable) {
                // expected
            }
        }

        $fresh = $cursor->fresh();
        $this->assertSame(0, $fresh->last_nsu);
        $this->assertGreaterThanOrEqual(5, $fresh->consecutive_decode_failures);
        $this->assertSame(SyncCursorStatus::Blocked, $fresh->status);
    }

    /**
     * @return array{0: SyncCursor, 1: Establishment}
     */
    private function seedCursor(string $cnpj = ''): array
    {
        $office = Office::factory()->create();
        $client = Client::factory()->forOffice($office)->create(['root_cnpj' => '11222333']);
        $establishmentCnpj = $cnpj !== '' ? $cnpj : EstablishmentFactory::cnpjWithRoot('11222333');
        $establishment = Establishment::factory()->forClient($client, $establishmentCnpj)->create();
        $cursor = SyncCursor::query()->create([
            'office_id' => $office->id,
            'establishment_id' => $establishment->id,
            'environment' => 'test',
            'last_nsu' => 0,
            'status' => SyncCursorStatus::Idle,
        ]);

        return [$cursor, $establishment];
    }
}
