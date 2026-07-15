<?php

namespace Tests\Feature\Import;

use App\Enums\DocumentArtifactQuality;
use App\Enums\FiscalRole;
use App\Enums\OfficeRole;
use App\Models\Client;
use App\Models\CteDocument;
use App\Models\DocumentAcquisition;
use App\Models\DocumentInterest;
use App\Models\Establishment;
use App\Models\Office;
use App\Models\User;
use App\Services\Import\OutboundXmlIngestionService;
use App\Support\CurrentOffice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CteXmlImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_importa_cteproc_como_saida_issuer(): void
    {
        $office = Office::factory()->create();
        $user = User::factory()->forOffice($office, OfficeRole::Operator)->withTwoFactorConfirmed()->create();
        $client = Client::factory()->forOffice($office)->create();
        Establishment::factory()->forClient($client)->create(['cnpj' => '11222333000181']);

        $xml = file_get_contents(base_path('tests/fixtures/cte/procCTe_57_roles_all.xml'));
        $this->assertNotFalse($xml);

        $report = app(OutboundXmlIngestionService::class)->ingestXmlBytes(
            $office->id,
            $client->id,
            $xml,
            'cte.xml',
        );

        $this->assertSame('imported', $report['status'], $report['message'] ?? '');
        $this->assertSame('CTE', $report['kind']);

        $cte = CteDocument::query()->where('office_id', $office->id)->first();
        $this->assertNotNull($cte);
        $this->assertSame(FiscalRole::Issuer, $cte->fiscal_role);
        $this->assertSame('OUT', $cte->direction->value);
        $this->assertSame('33333333000133', $cte->expeditor_cnpj);

        $interest = DocumentInterest::query()->where('fiscal_role', FiscalRole::Issuer)->first();
        $this->assertNotNull($interest);

        $acq = DocumentAcquisition::query()->first();
        $this->assertNotNull($acq);
        $this->assertSame(DocumentArtifactQuality::Original, $acq->artifact_quality);
    }

    public function test_modelo_67_rejeitado_como_unsupported(): void
    {
        $office = Office::factory()->create();
        $client = Client::factory()->forOffice($office)->create();
        Establishment::factory()->forClient($client)->create(['cnpj' => '11222333000181']);

        $xml = file_get_contents(base_path('tests/fixtures/cte/procCTe_57_roles_all.xml'));
        $xml = str_replace('<mod>57</mod>', '<mod>67</mod>', $xml);
        // força modelo 67 também na chave (posições 21-22, 0-based 20)
        // mantém tamanho; validador olha ide/mod

        $report = app(OutboundXmlIngestionService::class)->ingestXmlBytes(
            $office->id,
            null,
            $xml,
            'cte-os.xml',
        );

        $this->assertSame('error', $report['status']);
        $this->assertSame('UNSUPPORTED', $report['result_code'] ?? null);
        $this->assertSame(0, CteDocument::query()->count());
    }

    public function test_emitente_desconhecido_unmatched(): void
    {
        $office = Office::factory()->create();
        Client::factory()->forOffice($office)->create();
        // sem establishment do emitente 11222333000181

        $xml = file_get_contents(base_path('tests/fixtures/cte/procCTe_57_roles_all.xml'));
        $report = app(OutboundXmlIngestionService::class)->ingestXmlBytes(
            $office->id,
            null,
            $xml,
            'cte.xml',
        );

        $this->assertSame('error', $report['status']);
        $this->assertSame('UNMATCHED', $report['result_code'] ?? null);
    }

    public function test_classificador_reconhece_cte_por_conteudo(): void
    {
        $xml = file_get_contents(base_path('tests/fixtures/cte/procCTe_57_roles_all.xml'));
        $classified = app(\App\Services\Import\ImportXmlClassifier::class)->classify($xml);
        $this->assertSame('procCTe', $classified['kind']);
        $this->assertSame('57', $classified['model']);

        $evt = file_get_contents(base_path('tests/fixtures/cte/procEventoCTe_cancel.xml'));
        $c2 = app(\App\Services\Import\ImportXmlClassifier::class)->classify($evt);
        $this->assertSame('procEventoCTe', $c2['kind']);
    }

    public function test_api_documents_lista_cte_importado(): void
    {
        $office = Office::factory()->create();
        $user = User::factory()->forOffice($office, OfficeRole::Operator)->withTwoFactorConfirmed()->create();
        $client = Client::factory()->forOffice($office)->create();
        Establishment::factory()->forClient($client)->create(['cnpj' => '11222333000181']);
        $this->actingAs($user);
        app(CurrentOffice::class)->resolve($user);

        $xml = file_get_contents(base_path('tests/fixtures/cte/procCTe_57_roles_all.xml'));
        app(OutboundXmlIngestionService::class)->ingestXmlBytes($office->id, $client->id, $xml, 'cte.xml');

        $this->getJson('/api/v1/documents?kind=CTE')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.kind', 'CTE')
            ->assertJsonPath('data.0.direction', 'OUT');
    }
}
