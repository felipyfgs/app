<?php

namespace Tests\Feature\Sefaz;

use App\Domain\Sefaz\DistDfeDocumentDto;
use App\Domain\Sefaz\DistDfePageDto;
use App\Enums\CaptureChannel;
use App\Enums\FiscalRole;
use App\Enums\QuarantineReason;
use App\Enums\SyncCursorStatus;
use App\Models\ChannelSyncCursor;
use App\Models\CteDocument;
use App\Models\DocumentInterest;
use App\Models\FiscalDocumentQuarantine;
use App\Models\Office;
use App\Services\Sefaz\CteDistDfePageProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CteDistDfePageProcessorTest extends TestCase
{
    use RefreshDatabase;

    public function test_cria_interesse_tomador_e_avanca_ult_nsu(): void
    {
        [$cursor, $est] = $this->seedCursor('34194865000158');
        $xml = file_get_contents(base_path('tests/fixtures/cte/procCTe_57_roles_all.xml'));
        $page = $this->pageWithXml($xml, nsu: 10, ultNsu: 10, maxNsu: 50);

        $result = app(CteDistDfePageProcessor::class)->process($cursor, $est, $page);

        $this->assertSame(1, $result['documents']);
        $this->assertSame(10, $result['advanced_to']);
        $this->assertSame(10, $cursor->fresh()->last_nsu);

        $interest = DocumentInterest::query()->first();
        $this->assertNotNull($interest);
        $this->assertSame(FiscalRole::Taker, $interest->fiscal_role);
        $this->assertSame(CaptureChannel::CteDistDfe->value, $interest->channel);

        $proj = CteDocument::query()->first();
        $this->assertNotNull($proj);
        $this->assertSame('33333333000133', $proj->expeditor_cnpj);
        $this->assertSame('44444444000144', $proj->receiver_cnpj);
    }

    public function test_emitente_proprio_vai_para_quarentena(): void
    {
        [$cursor, $est] = $this->seedCursor('99888777000166');
        $xml = file_get_contents(base_path('tests/fixtures/cte/procCTe_57_issuer_only.xml'));
        $page = $this->pageWithXml($xml, nsu: 5, ultNsu: 5, maxNsu: 5);

        $result = app(CteDistDfePageProcessor::class)->process($cursor, $est, $page);

        $this->assertSame(0, $result['documents']);
        $this->assertSame(1, $result['quarantined']);
        $this->assertSame(0, CteDocument::query()->count());
        $this->assertSame(0, DocumentInterest::query()->where('fiscal_role', FiscalRole::Issuer->value)->count());

        $q = FiscalDocumentQuarantine::query()->first();
        $this->assertNotNull($q);
        $this->assertSame(QuarantineReason::UnexpectedOwnIssuerDocument, $q->reason);
        // Cursor avança após custódia da quarentena
        $this->assertSame(5, $cursor->fresh()->last_nsu);
    }

    public function test_cnpj_sem_papel_nao_inventa_taker(): void
    {
        [$cursor, $est] = $this->seedCursor('00000000000000');
        $xml = file_get_contents(base_path('tests/fixtures/cte/procCTe_57_roles_all.xml'));
        $page = $this->pageWithXml($xml, nsu: 7, ultNsu: 7, maxNsu: 7);

        $result = app(CteDistDfePageProcessor::class)->process($cursor, $est, $page);

        $this->assertSame(0, $result['documents']);
        $this->assertSame(1, $result['quarantined']);
        $this->assertSame(0, DocumentInterest::query()->count());
        $this->assertSame(
            QuarantineReason::UnmatchedFiscalRole,
            FiscalDocumentQuarantine::query()->firstOrFail()->reason
        );
    }

    public function test_toma3_acumula_sender_e_taker(): void
    {
        [$cursor, $est] = $this->seedCursor('11111111000111');
        $xml = file_get_contents(base_path('tests/fixtures/cte/procCTe_57_toma3_rem.xml'));
        $page = $this->pageWithXml($xml, nsu: 3, ultNsu: 3, maxNsu: 3);

        app(CteDistDfePageProcessor::class)->process($cursor, $est, $page);

        $roles = DocumentInterest::query()->pluck('fiscal_role')->map(fn ($r) => $r->value)->sort()->values()->all();
        $this->assertSame(['SENDER', 'TAKER'], $roles);
    }

    public function test_cstat_656_bloqueia_sem_avancar(): void
    {
        [$cursor, $est] = $this->seedCursor('34194865000158');
        $cursor->update(['last_nsu' => 20]);
        $page = new DistDfePageDto('656', 'Consumo indevido', 20, 100, []);

        $result = app(CteDistDfePageProcessor::class)->process($cursor, $est, $page);

        $this->assertSame(20, $result['advanced_to']);
        $this->assertSame(SyncCursorStatus::Blocked, $cursor->fresh()->status);
    }

    /**
     * @return array{0: ChannelSyncCursor, 1: \App\Models\Establishment}
     */
    private function seedCursor(string $cnpj): array
    {
        $office = Office::factory()->create();
        $client = \App\Models\Client::factory()->forOffice($office)->create();
        $est = \App\Models\Establishment::factory()->forClient($client)->create(['cnpj' => $cnpj]);

        $cursor = ChannelSyncCursor::query()->create([
            'office_id' => $office->id,
            'establishment_id' => $est->id,
            'environment' => 'production',
            'source' => 'SEFAZ',
            'channel' => CaptureChannel::CteDistDfe,
            'last_nsu' => 0,
            'status' => SyncCursorStatus::Idle,
        ]);

        return [$cursor, $est];
    }

    private function pageWithXml(string $xml, int $nsu, int $ultNsu, int $maxNsu): DistDfePageDto
    {
        $b64 = base64_encode(gzencode($xml, 9));

        return new DistDfePageDto(
            '138',
            'Documento(s) localizado(s)',
            $ultNsu,
            $maxNsu,
            [
                new DistDfeDocumentDto(
                    nsu: $nsu,
                    schema: 'procCTe_v4.00.xsd',
                    schemaFamily: 'procCTe',
                    contentBase64: $b64,
                ),
            ],
        );
    }
}
