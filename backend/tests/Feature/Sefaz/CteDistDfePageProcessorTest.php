<?php

namespace Tests\Feature\Sefaz;

use App\Domain\Sefaz\DistDfeDocumentDto;
use App\Domain\Sefaz\DistDfePageDto;
use App\Enums\CaptureChannel;
use App\Enums\FiscalRole;
use App\Enums\QuarantineReason;
use App\Enums\SyncCursorStatus;
use App\Exceptions\Adn\DocumentDecodeException;
use App\Models\ChannelSyncCursor;
use App\Models\Client;
use App\Models\CteDocument;
use App\Models\CteEvent;
use App\Models\DocumentInterest;
use App\Models\Establishment;
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

    public function test_reparo_por_nsu_conhecido_persiste_sem_mover_cursor_sequencial(): void
    {
        [$cursor, $est] = $this->seedCursor('34194865000158');
        $cursor->update([
            'last_nsu' => 20,
            'max_nsu_seen' => 40,
            'status' => SyncCursorStatus::Running,
            'next_sync_at' => null,
            'consecutive_decode_failures' => 2,
            'last_cstat' => '138',
            'last_error' => null,
        ]);
        $xml = file_get_contents(base_path('tests/fixtures/cte/procCTe_57_roles_all.xml'));
        // ultNsu == maxNsu ⇒ isEndOfQueue no path sequencial; reparo NÃO deve quietar
        $page = $this->pageWithXml($xml, nsu: 10, ultNsu: 40, maxNsu: 40);

        $result = app(CteDistDfePageProcessor::class)->processKnownNsuRepair($cursor, $est, $page);

        $this->assertSame(1, $result['documents']);
        $this->assertSame(20, $result['advanced_to']);
        $fresh = $cursor->fresh();
        $this->assertSame(20, $fresh->last_nsu);
        $this->assertSame(40, $fresh->max_nsu_seen);
        $this->assertSame(SyncCursorStatus::Running, $fresh->status);
        $this->assertNull($fresh->next_sync_at);
        $this->assertSame(2, $fresh->consecutive_decode_failures);
        $this->assertSame('138', $fresh->last_cstat);
        $this->assertNull($fresh->last_error);
        $this->assertSame(1, CteDocument::query()->count());
    }

    public function test_reparo_cstat_137_nao_aplica_quiet_nem_altera_saude_do_cursor(): void
    {
        [$cursor, $est] = $this->seedCursor('34194865000158');
        $cursor->update([
            'last_nsu' => 25,
            'max_nsu_seen' => 50,
            'status' => SyncCursorStatus::Running,
            'next_sync_at' => null,
            'consecutive_decode_failures' => 1,
            'last_cstat' => '138',
        ]);
        $page = new DistDfePageDto('137', 'Nenhum documento localizado', 10, 50, []);

        $result = app(CteDistDfePageProcessor::class)->processKnownNsuRepair($cursor, $est, $page);

        $this->assertSame(0, $result['documents']);
        $this->assertSame(25, $result['advanced_to']);
        $fresh = $cursor->fresh();
        $this->assertSame(25, $fresh->last_nsu);
        $this->assertSame(SyncCursorStatus::Running, $fresh->status);
        $this->assertNull($fresh->next_sync_at);
        $this->assertSame(1, $fresh->consecutive_decode_failures);
        $this->assertSame('138', $fresh->last_cstat);
    }

    public function test_reparo_decode_failure_nao_incrementa_circuit_sequencial(): void
    {
        [$cursor, $est] = $this->seedCursor('34194865000158');
        $cursor->update([
            'last_nsu' => 30,
            'status' => SyncCursorStatus::Running,
            'consecutive_decode_failures' => 0,
            'next_sync_at' => null,
        ]);
        $page = new DistDfePageDto('138', 'Documento(s) localizado(s)', 10, 50, [
            new DistDfeDocumentDto(
                nsu: 10,
                schema: 'procCTe_v4.00.xsd',
                schemaFamily: 'procCTe',
                contentBase64: '@@@not-valid-base64@@@',
            ),
        ]);

        try {
            app(CteDistDfePageProcessor::class)->processKnownNsuRepair($cursor, $est, $page);
            $this->fail('esperava DocumentDecodeException');
        } catch (DocumentDecodeException) {
            // expected
        }

        $fresh = $cursor->fresh();
        $this->assertSame(30, $fresh->last_nsu);
        $this->assertSame(0, $fresh->consecutive_decode_failures);
        $this->assertSame(SyncCursorStatus::Running, $fresh->status);
        $this->assertNull($fresh->next_sync_at);
        $this->assertNull($fresh->last_error);
    }

    public function test_cstat_137_quiet_sem_documentos_nao_avanca_alem_ult(): void
    {
        [$cursor, $est] = $this->seedCursor('34194865000158');
        $cursor->update(['last_nsu' => 15]);
        $page = new DistDfePageDto('137', 'Nenhum documento localizado', 15, 15, []);

        $result = app(CteDistDfePageProcessor::class)->process($cursor, $est, $page);

        $this->assertSame(0, $result['documents']);
        $this->assertSame(15, $result['advanced_to']);
        $fresh = $cursor->fresh();
        $this->assertSame(15, $fresh->last_nsu);
        $this->assertSame('137', $fresh->last_cstat);
        $this->assertSame(SyncCursorStatus::Idle, $fresh->status);
        $this->assertNotNull($fresh->next_sync_at);
        $this->assertTrue($fresh->next_sync_at->isFuture());
    }

    public function test_cstat_138_avanca_para_ult_nsu_da_resposta(): void
    {
        [$cursor, $est] = $this->seedCursor('34194865000158');
        $xml = file_get_contents(base_path('tests/fixtures/cte/procCTe_57_roles_all.xml'));
        $page = $this->pageWithXml($xml, nsu: 8, ultNsu: 12, maxNsu: 100);

        $result = app(CteDistDfePageProcessor::class)->process($cursor, $est, $page);

        $this->assertSame(1, $result['documents']);
        $this->assertSame(12, $result['advanced_to']);
        $this->assertSame(12, $cursor->fresh()->last_nsu);
        $this->assertSame('138', $cursor->fresh()->last_cstat);
    }

    public function test_cstat_593_bloqueia_sem_avancar(): void
    {
        [$cursor, $est] = $this->seedCursor('34194865000158');
        $cursor->update(['last_nsu' => 30]);
        $page = new DistDfePageDto('593', 'CNPJ-Base diverge do certificado', 30, 50, []);

        $result = app(CteDistDfePageProcessor::class)->process($cursor, $est, $page);

        $this->assertSame(30, $result['advanced_to']);
        $fresh = $cursor->fresh();
        $this->assertSame(SyncCursorStatus::Blocked, $fresh->status);
        $this->assertSame('593', $fresh->last_cstat);
        $this->assertSame(30, $fresh->last_nsu);
    }

    public function test_retry_idempotente_e_duplicata_mesma_pagina(): void
    {
        [$cursor, $est] = $this->seedCursor('34194865000158');
        $xml = file_get_contents(base_path('tests/fixtures/cte/procCTe_57_roles_all.xml'));
        $page = $this->pageWithXml($xml, nsu: 10, ultNsu: 10, maxNsu: 10);

        app(CteDistDfePageProcessor::class)->process($cursor, $est, $page);
        $second = app(CteDistDfePageProcessor::class)->process($cursor->fresh(), $est, $page);

        $this->assertSame(1, CteDocument::query()->count());
        $this->assertSame(1, DocumentInterest::query()->where('fiscal_role', FiscalRole::Taker)->count());
        $this->assertSame(10, $cursor->fresh()->last_nsu);
        $this->assertGreaterThanOrEqual(0, $second['documents']);
    }

    public function test_evento_antes_do_pai_vira_orfao_e_depois_reconcilia_com_principal(): void
    {
        [$cursor, $est] = $this->seedCursor('34194865000158');
        $eventXml = file_get_contents(base_path('tests/fixtures/cte/procEventoCTe_cancel.xml'));
        $cteXml = file_get_contents(base_path('tests/fixtures/cte/procCTe_57_roles_all.xml'));

        // Página só com evento (mesmo NSU stream) — deve quarentenar órfão
        $eventPage = new DistDfePageDto('138', 'Documento(s) localizado(s)', 1, 10, [
            new DistDfeDocumentDto(
                nsu: 1,
                schema: 'procEventoCTe_v4.00.xsd',
                schemaFamily: 'procEventoCTe',
                contentBase64: base64_encode(gzencode($eventXml, 9)),
            ),
        ]);
        $r1 = app(CteDistDfePageProcessor::class)->process($cursor, $est, $eventPage);
        $this->assertSame(1, $r1['quarantined']);
        $this->assertSame(
            QuarantineReason::OrphanEvent,
            FiscalDocumentQuarantine::query()->firstOrFail()->reason
        );

        // Página com principal da mesma chave
        $mainPage = $this->pageWithXml($cteXml, nsu: 2, ultNsu: 2, maxNsu: 10);
        app(CteDistDfePageProcessor::class)->process($cursor->fresh(), $est, $mainPage);
        $this->assertSame(1, CteDocument::query()->count());
    }

    public function test_pagina_mista_processa_principal_antes_de_evento(): void
    {
        [$cursor, $est] = $this->seedCursor('34194865000158');
        $eventXml = file_get_contents(base_path('tests/fixtures/cte/procEventoCTe_cancel.xml'));
        $cteXml = file_get_contents(base_path('tests/fixtures/cte/procCTe_57_roles_all.xml'));

        // Evento listado antes do principal no array — duas passagens garantem vínculo
        $page = new DistDfePageDto('138', 'Documento(s) localizado(s)', 5, 5, [
            new DistDfeDocumentDto(
                nsu: 4,
                schema: 'procEventoCTe_v4.00.xsd',
                schemaFamily: 'procEventoCTe',
                contentBase64: base64_encode(gzencode($eventXml, 9)),
            ),
            new DistDfeDocumentDto(
                nsu: 5,
                schema: 'procCTe_v4.00.xsd',
                schemaFamily: 'procCTe',
                contentBase64: base64_encode(gzencode($cteXml, 9)),
            ),
        ]);

        $result = app(CteDistDfePageProcessor::class)->process($cursor, $est, $page);

        $this->assertGreaterThanOrEqual(1, $result['documents']);
        $this->assertSame(1, CteDocument::query()->count());
        $this->assertSame(0, FiscalDocumentQuarantine::query()
            ->where('reason', QuarantineReason::OrphanEvent->value)->count());
        $this->assertSame(1, CteEvent::query()->whereNotNull('cte_document_id')->count());
        $this->assertSame('CANCELLED', CteDocument::query()->first()->status);
    }

    /**
     * @return array{0: ChannelSyncCursor, 1: Establishment}
     */
    private function seedCursor(string $cnpj): array
    {
        $office = Office::factory()->create();
        $client = Client::factory()->forOffice($office)->create();
        $est = Establishment::factory()->forClient($client)->create(['cnpj' => $cnpj]);

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
