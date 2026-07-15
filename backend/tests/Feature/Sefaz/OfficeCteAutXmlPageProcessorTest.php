<?php

namespace Tests\Feature\Sefaz;

use App\Domain\Sefaz\DistDfeDocumentDto;
use App\Domain\Sefaz\DistDfePageDto;
use App\Enums\CaptureChannel;
use App\Enums\DocumentArtifactQuality;
use App\Enums\DocumentDirection;
use App\Enums\FiscalRole;
use App\Enums\QuarantineReason;
use App\Enums\SyncCursorStatus;
use App\Models\CteDocument;
use App\Models\DocumentAcquisition;
use App\Models\DocumentInterest;
use App\Models\FiscalDocumentQuarantine;
use App\Models\Office;
use App\Models\OfficeDistributionCursor;
use App\Models\OfficeFiscalIdentity;
use App\Services\Sefaz\OfficeCteAutXmlPageProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfficeCteAutXmlPageProcessorTest extends TestCase
{
    use RefreshDatabase;

    public function test_roteia_por_emit_e_cria_issuer_out_com_qualidade_autxml(): void
    {
        [$cursor, $issuerEst] = $this->seedCursorWithIssuer(
            officeCnpj: '55666777000155',
            issuerCnpj: '11222333000181',
        );

        $xml = file_get_contents(base_path('tests/fixtures/cte/procCTe_57_autxml_redacted.xml'));
        $page = $this->page($xml, 11, 11, 20);

        $result = app(OfficeCteAutXmlPageProcessor::class)->process($cursor, $page);

        $this->assertSame(1, $result['documents']);
        $this->assertSame(11, $result['advanced_to']);

        $interest = DocumentInterest::query()
            ->where('establishment_id', $issuerEst->id)
            ->where('fiscal_role', FiscalRole::Issuer)
            ->first();
        $this->assertNotNull($interest);
        $this->assertSame(DocumentDirection::Out, $interest->direction);
        $this->assertSame(CaptureChannel::CteAutXmlDistDfe->value, $interest->channel);

        $acq = DocumentAcquisition::query()->first();
        $this->assertNotNull($acq);
        $this->assertSame(DocumentArtifactQuality::AutXmlRedacted, $acq->artifact_quality);

        $proj = CteDocument::query()->first();
        $this->assertNotNull($proj);
        $this->assertSame(FiscalRole::Issuer, $proj->fiscal_role);
        $this->assertSame(DocumentDirection::Out, $proj->direction);
    }

    public function test_sem_autxml_do_escritorio_quarentena(): void
    {
        [$cursor] = $this->seedCursorWithIssuer(
            officeCnpj: '99999999000199',
            issuerCnpj: '11222333000181',
        );

        $xml = file_get_contents(base_path('tests/fixtures/cte/procCTe_57_autxml_original.xml'));
        $page = $this->page($xml, 1, 1, 1);

        $result = app(OfficeCteAutXmlPageProcessor::class)->process($cursor, $page);

        $this->assertSame(0, $result['documents']);
        $this->assertSame(1, $result['quarantined']);
        $this->assertSame(
            QuarantineReason::AutXmlTagDivergent,
            FiscalDocumentQuarantine::query()->firstOrFail()->reason
        );
    }

    public function test_emitente_desconhecido_quarentena(): void
    {
        $office = Office::factory()->create();
        $identity = OfficeFiscalIdentity::factory()->forOffice($office)->withCnpj('55666777000155')->create();
        $cursor = OfficeDistributionCursor::factory()->forIdentity($identity)->create([
            'channel' => CaptureChannel::CteAutXmlDistDfe,
            'query_cnpj' => '55666777000155',
            'interested_root_cnpj' => '55666777',
            'status' => SyncCursorStatus::Idle,
            'last_nsu' => 0,
        ]);

        $xml = file_get_contents(base_path('tests/fixtures/cte/procCTe_57_autxml_original.xml'));
        $page = $this->page($xml, 2, 2, 2);

        $result = app(OfficeCteAutXmlPageProcessor::class)->process($cursor, $page);

        $this->assertSame(0, $result['documents']);
        $this->assertSame(
            QuarantineReason::UnmatchedIssuer,
            FiscalDocumentQuarantine::query()->firstOrFail()->reason
        );
    }

    public function test_emitente_inativo_nao_promove_full(): void
    {
        [$cursor, $issuerEst] = $this->seedCursorWithIssuer(
            officeCnpj: '55666777000155',
            issuerCnpj: '11222333000181',
        );
        $issuerEst->forceFill(['is_active' => false])->save();

        $xml = file_get_contents(base_path('tests/fixtures/cte/procCTe_57_autxml_redacted.xml'));
        $page = $this->page($xml, 12, 12, 20);

        $result = app(OfficeCteAutXmlPageProcessor::class)->process($cursor, $page);

        $this->assertSame(0, $result['documents']);
        $this->assertSame(1, $result['quarantined']);
        $this->assertSame(0, CteDocument::query()->count());
        $this->assertSame(0, DocumentInterest::query()->count());
        $this->assertSame(
            QuarantineReason::UnmatchedIssuer,
            FiscalDocumentQuarantine::query()->firstOrFail()->reason
        );
    }

    /**
     * @return array{0: OfficeDistributionCursor, 1: \App\Models\Establishment}
     */
    private function seedCursorWithIssuer(string $officeCnpj, string $issuerCnpj): array
    {
        $office = Office::factory()->create();
        $identity = OfficeFiscalIdentity::factory()->forOffice($office)->withCnpj($officeCnpj)->create();
        $client = \App\Models\Client::factory()->forOffice($office)->create();
        $issuerEst = \App\Models\Establishment::factory()->forClient($client)->create([
            'cnpj' => $issuerCnpj,
            'is_active' => true,
        ]);

        $cursor = OfficeDistributionCursor::factory()->forIdentity($identity)->create([
            'channel' => CaptureChannel::CteAutXmlDistDfe,
            'query_cnpj' => strtoupper($officeCnpj),
            'interested_root_cnpj' => substr(strtoupper($officeCnpj), 0, 8),
            'status' => SyncCursorStatus::Idle,
            'last_nsu' => 0,
            'external_consumer_status' => 'DECLARED_CLEAR',
        ]);

        return [$cursor, $issuerEst];
    }

    private function page(string $xml, int $nsu, int $ult, int $max): DistDfePageDto
    {
        return new DistDfePageDto(
            '138',
            'Documento(s) localizado(s)',
            $ult,
            $max,
            [
                new DistDfeDocumentDto(
                    nsu: $nsu,
                    schema: 'procCTe_v4.00.xsd',
                    contentBase64: base64_encode(gzencode($xml, 9)),
                    schemaFamily: 'procCTe',
                ),
            ],
        );
    }
}
