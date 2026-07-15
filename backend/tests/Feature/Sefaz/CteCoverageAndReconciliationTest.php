<?php

namespace Tests\Feature\Sefaz;

use App\Enums\CteCoverageStatus;
use App\Enums\DocumentAcquisitionSource;
use App\Enums\QuarantineReason;
use App\Enums\QuarantineResolutionStatus;
use App\Models\Client;
use App\Models\CteDocument;
use App\Models\CteEvent;
use App\Models\DfeDocument;
use App\Models\Establishment;
use App\Models\FiscalDocumentQuarantine;
use App\Models\Office;
use App\Services\Import\OutboundXmlIngestionService;
use App\Services\Sefaz\CteCoverageService;
use App\Services\Sefaz\CteReconciliationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CteCoverageAndReconciliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_projeta_original_e_isola_cliente_e_office(): void
    {
        [$office, $client] = $this->importOriginal();
        $otherOffice = Office::factory()->create();
        $otherClient = Client::factory()->forOffice($otherOffice)->create();
        Establishment::factory()->forClient($otherClient)->create(['cnpj' => '11222333000181']);

        $snapshot = app(CteCoverageService::class)->recompute($office->id, $client->id, '2026-07');
        $other = app(CteCoverageService::class)->recompute($otherOffice->id, $otherClient->id, '2026-07');

        $this->assertSame(CteCoverageStatus::CapturedOriginal, $snapshot->status);
        $this->assertSame(1, $snapshot->documents_count);
        $this->assertSame(1, $snapshot->original_count);
        $this->assertSame(CteCoverageStatus::NoActivity, $other->status);
        $this->assertSame(0, $other->documents_count);
    }

    public function test_import_resolve_pendencia_e_evento_orfao_preservando_razao(): void
    {
        [$office, $client, $cte] = $this->importOriginal();
        $eventDfe = DfeDocument::query()->create([
            'office_id' => $office->id,
            'sha256' => str_repeat('a', 64),
            'document_type' => 'UNKNOWN',
            'schema_version' => 'procEventoCTe',
            'access_key' => $cte->access_key,
            'vault_object_id' => 'test-event-object',
            'byte_size' => 10,
            'parse_status' => 'OK',
        ]);
        $event = CteEvent::query()->create([
            'office_id' => $office->id,
            'dfe_document_id' => $eventDfe->id,
            'cte_document_id' => null,
            'access_key' => $cte->access_key,
            'event_type' => '110111',
            'sequence' => 1,
            'status' => 'CANCELLED',
        ]);
        $pending = FiscalDocumentQuarantine::query()->create([
            'office_id' => $office->id,
            'sha256' => str_repeat('b', 64),
            'vault_object_id' => 'test-pending-object',
            'byte_size' => 10,
            'access_key' => $cte->access_key,
            'issuer_cnpj' => '11222333000181',
            'model' => '57',
            'schema_family' => 'cteProc',
            'reason' => QuarantineReason::PendingImport,
            'source' => DocumentAcquisitionSource::ManualXml,
            'resolution_status' => QuarantineResolutionStatus::Open,
            'metadata' => ['origin' => 'AUTXML_REDACTED'],
        ]);

        $result = app(CteReconciliationService::class)->reconcileDocument($office->id, $cte->access_key);

        $this->assertSame(1, $result['events_linked']);
        $this->assertSame(1, $result['quarantines_resolved']);
        $this->assertSame($cte->id, $event->fresh()->cte_document_id);
        $this->assertSame(QuarantineResolutionStatus::Resolved, $pending->fresh()->resolution_status);
        $this->assertSame('PENDING_IMPORT', $pending->fresh()->metadata['previous_reason']);
        $this->assertSame('AUTXML_REDACTED', $pending->fresh()->metadata['origin']);
        $this->assertSame(CteCoverageStatus::CapturedOriginal, $cte->fresh()->coverage_status);
        $this->assertSame(CteCoverageStatus::CapturedOriginal, $client->refresh()->id
            ? app(CteCoverageService::class)->recompute($office->id, $client->id, '2026-07')->status
            : null);
    }

    public function test_reconcile_orphans_lote_escopado_por_office(): void
    {
        [$office, $client, $cte] = $this->importOriginal();
        $other = Office::factory()->create();

        CteEvent::query()->create([
            'office_id' => $office->id,
            'dfe_document_id' => $cte->dfe_document_id,
            'cte_document_id' => null,
            'access_key' => $cte->access_key,
            'event_type' => '110111',
            'sequence' => 2,
            'status' => 'EVENT',
        ]);
        // Evento de outro office não deve ser tocado
        CteEvent::query()->create([
            'office_id' => $other->id,
            'dfe_document_id' => $cte->dfe_document_id,
            'cte_document_id' => null,
            'access_key' => $cte->access_key,
            'event_type' => '110111',
            'sequence' => 9,
            'status' => 'EVENT',
        ]);

        $result = app(CteReconciliationService::class)->reconcileOrphans($office->id, 50);

        $this->assertGreaterThanOrEqual(1, $result['keys_processed']);
        $this->assertGreaterThanOrEqual(1, $result['events_linked']);
        $this->assertNull(
            CteEvent::query()->where('office_id', $other->id)->where('sequence', 9)->first()->cte_document_id
        );
    }

    /** @return array{0: Office, 1: Client, 2?: CteDocument} */
    private function importOriginal(): array
    {
        $office = Office::factory()->create();
        $client = Client::factory()->forOffice($office)->create();
        Establishment::factory()->forClient($client)->create(['cnpj' => '11222333000181']);
        $xml = (string) file_get_contents(base_path('tests/fixtures/cte/procCTe_57_roles_all.xml'));

        $result = app(OutboundXmlIngestionService::class)->ingestXmlBytes(
            $office->id,
            $client->id,
            $xml,
            'cte.xml',
        );
        $this->assertSame('imported', $result['status'], $result['message'] ?? '');

        return [$office, $client, CteDocument::query()->where('office_id', $office->id)->firstOrFail()];
    }
}
