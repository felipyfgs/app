<?php

namespace Tests\Feature\Sefaz;

use App\Enums\AdnDocumentType;
use App\Enums\CaptureChannel;
use App\Enums\DocumentDirection;
use App\Enums\FiscalRole;
use App\Enums\OfficeRole;
use App\Enums\SyncCursorStatus;
use App\Models\ChannelSyncCursor;
use App\Models\CteDocument;
use App\Models\DfeDocument;
use App\Models\Office;
use App\Models\User;
use App\Support\CurrentOffice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CteCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_documents_kind_cte_lista_projecao_e_direction(): void
    {
        config(['sefaz.cte_enabled' => true]);
        [$office, $user] = $this->seedOfficeUser();
        $this->actingAs($user);
        app(CurrentOffice::class)->resolve($user);

        $this->seedCte($office->id, '35260711222333000181570010000000011234567890', DocumentDirection::In);

        $this->getJson('/api/v1/documents?kind=CTE')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.kind', 'CTE')
            ->assertJsonPath('data.0.direction', 'IN')
            ->assertJsonPath('data.0.direction_label', 'Entrada');

        $this->getJson('/api/v1/documents?kind=CTE&direction=OUT')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_documents_kind_cte_flag_off_ainda_lista_existentes(): void
    {
        config(['sefaz.cte_enabled' => false]);
        [$office, $user] = $this->seedOfficeUser();
        $this->actingAs($user);
        app(CurrentOffice::class)->resolve($user);

        $this->seedCte($office->id, '35260711222333000181570010000000011234567891', DocumentDirection::In);

        // Flag off não apaga dados já capturados
        $this->getJson('/api/v1/documents?kind=CTE')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_inbox_inclui_channel_sync_cursor_cte_blocked(): void
    {
        [$office, $user] = $this->seedOfficeUser();
        $this->actingAs($user);
        app(CurrentOffice::class)->resolve($user);

        $client = \App\Models\Client::factory()->forOffice($office)->create(['legal_name' => 'Frete SA']);
        $est = \App\Models\Establishment::factory()->forClient($client)->create();

        ChannelSyncCursor::query()->create([
            'office_id' => $office->id,
            'establishment_id' => $est->id,
            'environment' => 'production',
            'source' => 'SEFAZ',
            'channel' => CaptureChannel::CteDistDfe,
            'last_nsu' => 10,
            'status' => SyncCursorStatus::Blocked,
            'last_cstat' => '656',
            'last_error' => 'Consumo indevido SEFAZ CT-e',
        ]);

        $response = $this->getJson('/api/v1/operations/inbox')->assertOk();
        $bodies = collect($response->json('data'))->pluck('body')->implode(' ');
        $types = collect($response->json('data'))->pluck('type')->all();
        $this->assertStringContainsString('CT-e', $bodies);
        $this->assertStringContainsString('656', $bodies);
        $this->assertTrue(
            in_array('cte_656', $types, true) || in_array('cursor_blocked', $types, true)
        );
    }

    public function test_catalogo_isola_office_e_filtra_por_client_via_interesse(): void
    {
        config(['sefaz.cte_enabled' => true]);
        [$officeA, $userA] = $this->seedOfficeUser();
        $officeB = Office::factory()->create();
        $clientA = \App\Models\Client::factory()->forOffice($officeA)->create();
        $clientB = \App\Models\Client::factory()->forOffice($officeA)->create();
        $estA = \App\Models\Establishment::factory()->forClient($clientA)->create(['cnpj' => '34194865000158']);
        $estB = \App\Models\Establishment::factory()->forClient($clientB)->create(['cnpj' => '22222222000122']);

        $cteA = $this->seedCte($officeA->id, '35260711222333000181570010000000011234567890', DocumentDirection::In);
        $cteB = $this->seedCte($officeA->id, '35260711222333000181570010000000011234567891', DocumentDirection::In);
        $this->seedCte($officeB->id, '35260711222333000181570010000000011234567892', DocumentDirection::In);

        \App\Models\DocumentInterest::query()->create([
            'office_id' => $officeA->id,
            'dfe_document_id' => $cteA->dfe_document_id,
            'establishment_id' => $estA->id,
            'fiscal_role' => FiscalRole::Taker,
            'direction' => DocumentDirection::In,
            'channel' => CaptureChannel::CteDistDfe,
            'environment' => 'production',
            'nsu' => 1,
        ]);
        \App\Models\DocumentInterest::query()->create([
            'office_id' => $officeA->id,
            'dfe_document_id' => $cteB->dfe_document_id,
            'establishment_id' => $estB->id,
            'fiscal_role' => FiscalRole::Recipient,
            'direction' => DocumentDirection::In,
            'channel' => CaptureChannel::CteDistDfe,
            'environment' => 'production',
            'nsu' => 2,
        ]);
        \App\Models\DocumentAcquisition::query()->create([
            'office_id' => $officeA->id,
            'dfe_document_id' => $cteA->dfe_document_id,
            'access_key' => $cteA->access_key,
            'source' => \App\Enums\DocumentAcquisitionSource::CteDistNsu,
            'channel' => CaptureChannel::CteDistDfe,
            'sha256' => $cteA->document->sha256,
            'artifact_quality' => \App\Enums\DocumentArtifactQuality::Original,
            'is_canonical' => true,
        ]);
        \App\Models\DocumentAcquisition::query()->create([
            'office_id' => $officeA->id,
            'dfe_document_id' => $cteB->dfe_document_id,
            'access_key' => $cteB->access_key,
            'source' => \App\Enums\DocumentAcquisitionSource::CteAutXmlDistNsu,
            'channel' => CaptureChannel::CteAutXmlDistDfe,
            'sha256' => $cteB->document->sha256,
            'artifact_quality' => \App\Enums\DocumentArtifactQuality::AutXmlRedacted,
            'is_canonical' => true,
        ]);

        $this->actingAs($userA);
        app(CurrentOffice::class)->resolve($userA);

        $this->getJson('/api/v1/documents?kind=CTE')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->getJson('/api/v1/documents?kind=CTE&client_id='.$clientA->id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.access_key', $cteA->access_key);

        $this->getJson('/api/v1/documents?kind=CTE&acquisition_source=CTE_AUTXML_DIST_NSU')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.access_key', $cteB->access_key);

        // office_id do cliente ignorado — só membership
        $this->getJson('/api/v1/documents?kind=CTE&office_id='.$officeB->id)
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_download_cte_nao_vaza_vault_e_expoe_qualidade(): void
    {
        config(['sefaz.cte_enabled' => true]);
        [$office, $user] = $this->seedOfficeUser();
        $this->actingAs($user);
        app(CurrentOffice::class)->resolve($user);

        $cte = $this->seedCte($office->id, '35260711222333000181570010000000011234567893', DocumentDirection::In);
        \App\Models\DocumentAcquisition::query()->create([
            'office_id' => $office->id,
            'dfe_document_id' => $cte->dfe_document_id,
            'access_key' => $cte->access_key,
            'source' => \App\Enums\DocumentAcquisitionSource::CteDistNsu,
            'channel' => CaptureChannel::CteDistDfe,
            'sha256' => $cte->document->sha256,
            'artifact_quality' => \App\Enums\DocumentArtifactQuality::Original,
            'is_canonical' => true,
        ]);

        $response = $this->get('/api/v1/documents/'.$cte->access_key.'/xml');
        $response->assertOk();
        $this->assertStringContainsString('<cteProc', $response->streamedContent());
        $this->assertSame('ORIGINAL', $response->headers->get('X-Artifact-Quality'));
        $this->assertStringNotContainsString('vault_object_id', $response->headers->get('Content-Disposition') ?? '');
    }

    /**
     * @return array{0: Office, 1: User}
     */
    private function seedOfficeUser(): array
    {
        $office = Office::factory()->create();
        $user = User::factory()->forOffice($office, OfficeRole::Operator)->withTwoFactorConfirmed()->create();

        return [$office, $user];
    }

    private function seedCte(int $officeId, string $accessKey, DocumentDirection $direction): CteDocument
    {
        $xml = '<cteProc><chCTe>'.$accessKey.'</chCTe></cteProc>';
        $sha = hash('sha256', $xml.$accessKey);
        $store = app(\App\Contracts\SecureObjectStore::class);
        $objectId = $store->put($xml, ['office_id' => $officeId, 'sha256' => $sha]);

        $doc = DfeDocument::query()->create([
            'office_id' => $officeId,
            'sha256' => $sha,
            'document_type' => AdnDocumentType::Cte,
            'schema_version' => 'procCTe_v4.00.xsd',
            'access_key' => $accessKey,
            'vault_object_id' => $objectId,
            'byte_size' => strlen($xml),
            'parse_status' => 'OK',
        ]);

        return CteDocument::query()->create([
            'office_id' => $officeId,
            'dfe_document_id' => $doc->id,
            'access_key' => $accessKey,
            'number' => '1',
            'model' => '57',
            'issuer_cnpj' => '11222333000181',
            'taker_cnpj' => '34194865000158',
            'fiscal_role' => FiscalRole::Taker,
            'direction' => $direction,
            'issued_at' => '2026-07-01',
            'total_amount' => '100.00',
            'status' => 'ACTIVE',
            'is_summary' => false,
        ]);
    }
}
