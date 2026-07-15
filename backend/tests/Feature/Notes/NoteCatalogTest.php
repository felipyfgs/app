<?php

namespace Tests\Feature\Notes;

use App\Contracts\SecureObjectStore;
use App\Enums\AdnDocumentType;
use App\Enums\DocumentDirection;
use App\Enums\FiscalRole;
use App\Enums\OfficeRole;
use App\Models\Client;
use App\Models\DfeDocument;
use App\Models\DocumentInterest;
use App\Models\Establishment;
use App\Models\NfseNote;
use App\Models\Office;
use App\Models\User;
use App\Support\CurrentOffice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NoteCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_lista_filtra_competencia_emissão_e_papel(): void
    {
        [$office, $user] = $this->seedOfficeUser();
        $this->actingAs($user);
        app(CurrentOffice::class)->resolve($user);

        $this->seedNote($office->id, 'KEY1', '2026-07', '2026-07-10', FiscalRole::Issuer, 'ACTIVE');
        $this->seedNote($office->id, 'KEY2', '2026-06', '2026-06-01', FiscalRole::Taker, 'CANCELLED');

        $this->getJson('/api/v1/notes?competence=2026-07')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.access_key', 'KEY1');

        $this->getJson('/api/v1/notes?fiscal_role=TAKER')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.access_key', 'KEY2');

        $this->getJson('/api/v1/notes?issued_from=2026-07-01&issued_to=2026-07-31')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_detalhe_e_download_xml_auditado_sem_xml_em_json(): void
    {
        [$office, $user] = $this->seedOfficeUser();
        $this->actingAs($user);
        app(CurrentOffice::class)->resolve($user);

        $note = $this->seedNote($office->id, 'KEYXML', '2026-07', '2026-07-10', FiscalRole::Issuer, 'ACTIVE', '<root>xml-payload</root>');

        $detail = $this->getJson('/api/v1/notes/KEYXML')->assertOk();
        $detail->assertJsonPath('data.note.access_key', 'KEYXML');
        $detail->assertJsonMissingPath('data.note.document.vault_object_id');
        $this->assertStringNotContainsString('xml-payload', (string) $detail->getContent());

        $download = $this->get('/api/v1/notes/KEYXML/xml');
        $download->assertOk();
        $this->assertStringContainsString('xml-payload', $download->streamedContent());

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'xml.download',
            'result' => 'SUCCESS',
        ]);
    }

    public function test_isolamento_entre_escritorios(): void
    {
        [$officeA, $userA] = $this->seedOfficeUser();
        $officeB = Office::factory()->create();
        $this->seedNote($officeA->id, 'KEYA', '2026-07', '2026-07-01', FiscalRole::Issuer, 'ACTIVE');
        $this->seedNote($officeB->id, 'KEYB', '2026-07', '2026-07-01', FiscalRole::Issuer, 'ACTIVE');

        $this->actingAs($userA);
        app(CurrentOffice::class)->resolve($userA);

        $this->getJson('/api/v1/notes')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/notes/KEYB')->assertNotFound();
    }

    public function test_listagem_inclui_projecao_enriquecida_sem_xml(): void
    {
        [$office, $user] = $this->seedOfficeUser();
        $this->actingAs($user);
        app(CurrentOffice::class)->resolve($user);

        $this->seedNote(
            $office->id,
            'KEYPROJ',
            '2026-07',
            '2026-07-10',
            FiscalRole::Issuer,
            'ACTIVE',
            '<root>segredo-xml</root>',
            [
                'number' => '4242',
                'issuer_name' => 'Emitente ACME',
                'taker_name' => 'Tomador Beta',
                'issue_location' => 'SP',
                'official_status_code' => '100',
            ],
        );

        $response = $this->getJson('/api/v1/notes')->assertOk();
        $response->assertJsonPath('data.0.number', '4242');
        $response->assertJsonPath('data.0.issuer_name', 'Emitente ACME');
        $response->assertJsonPath('data.0.taker_name', 'Tomador Beta');
        $response->assertJsonPath('data.0.service_amount', '10.00');
        $response->assertJsonPath('data.0.competence', '2026-07');
        $response->assertJsonPath('data.0.fiscal_role', 'ISSUER');
        $response->assertJsonPath('data.0.official_status_code', '100');
        $response->assertJsonMissingPath('data.0.document');
        $this->assertStringNotContainsString('segredo-xml', (string) $response->getContent());
        $this->assertStringNotContainsString('vault_object_id', (string) $response->getContent());
    }

    public function test_busca_q_por_numero_e_nome(): void
    {
        [$office, $user] = $this->seedOfficeUser();
        $this->actingAs($user);
        app(CurrentOffice::class)->resolve($user);

        $this->seedNote($office->id, 'KEYA1', '2026-07', '2026-07-01', FiscalRole::Issuer, 'ACTIVE', '<NFSe/>', [
            'number' => '9001',
            'issuer_name' => 'Alpha Serviços',
        ]);
        $this->seedNote($office->id, 'KEYB2', '2026-07', '2026-07-02', FiscalRole::Taker, 'ACTIVE', '<NFSe/>', [
            'number' => '9002',
            'issuer_name' => 'Beta Outro',
        ]);

        $this->getJson('/api/v1/notes?q=9001')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.number', '9001');

        $this->getJson('/api/v1/notes?q=Alpha')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.issuer_name', 'Alpha Serviços');
    }

    public function test_cursor_percorre_paginas_sem_repetir_documentos(): void
    {
        [$office, $user] = $this->seedOfficeUser();
        $this->actingAs($user);
        app(CurrentOffice::class)->resolve($user);

        foreach (range(1, 5) as $index) {
            $this->seedNote(
                $office->id,
                'PAGE'.$index,
                '2026-07',
                '2026-07-'.str_pad((string) $index, 2, '0', STR_PAD_LEFT),
                FiscalRole::Issuer,
                'ACTIVE',
            );
        }

        $first = $this->getJson('/api/v1/documents?kind=NFSE&limit=2')->assertOk();
        $cursor = $first->json('meta.next_cursor');
        $this->assertIsString($cursor);
        $this->assertNotSame('', $cursor);
        $this->assertSame(5, $first->json('meta.total'));
        $this->assertSame(2, $first->json('meta.per_page'));

        $second = $this->getJson('/api/v1/documents?kind=NFSE&limit=2&cursor='.urlencode($cursor))->assertOk();
        $firstKeys = collect($first->json('data'))->pluck('access_key');
        $secondKeys = collect($second->json('data'))->pluck('access_key');

        $this->assertCount(2, $firstKeys);
        $this->assertCount(2, $secondKeys);
        $this->assertTrue($firstKeys->intersect($secondKeys)->isEmpty());
    }

    public function test_cursor_invalido_retorna_erro_de_validacao(): void
    {
        [$office, $user] = $this->seedOfficeUser();
        $this->actingAs($user);
        app(CurrentOffice::class)->resolve($user);

        $this->getJson('/api/v1/documents?cursor=nao-e-um-cursor')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('cursor');
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

    /**
     * @param  array<string, mixed>  $extra
     */
    private function seedNote(
        int $officeId,
        string $accessKey,
        string $competence,
        string $issuedAt,
        FiscalRole $role,
        string $status,
        string $xml = '<NFSe/>',
        array $extra = [],
    ): NfseNote {
        $sha = hash('sha256', $xml.$accessKey);
        $store = app(SecureObjectStore::class);
        $objectId = $store->put($xml, ['office_id' => $officeId, 'sha256' => $sha]);

        $doc = DfeDocument::query()->create([
            'office_id' => $officeId,
            'sha256' => $sha,
            'document_type' => AdnDocumentType::Nfse,
            'schema_version' => 'NFSe_v1.00.xsd',
            'access_key' => $accessKey,
            'vault_object_id' => $objectId,
            'byte_size' => strlen($xml),
            'parse_status' => 'OK',
        ]);

        return NfseNote::query()->create(array_merge([
            'office_id' => $officeId,
            'dfe_document_id' => $doc->id,
            'access_key' => $accessKey,
            'issuer_cnpj' => '11222333000181',
            'taker_cnpj' => '99888777000166',
            'fiscal_role' => $role,
            'direction' => DocumentDirection::fromFiscalRole($role),
            'competence' => $competence,
            'issued_at' => $issuedAt,
            'service_amount' => '10.00',
            'status' => $status,
        ], $extra));
    }

    public function test_filtro_e_serializacao_direction(): void
    {
        [$office, $user] = $this->seedOfficeUser();
        $this->actingAs($user);
        app(CurrentOffice::class)->resolve($user);

        $this->seedNote($office->id, 'KEYOUT', '2026-07', '2026-07-10', FiscalRole::Issuer, 'ACTIVE');
        $this->seedNote($office->id, 'KEYIN', '2026-07', '2026-07-11', FiscalRole::Taker, 'ACTIVE');
        $this->seedNote($office->id, 'KEYINT', '2026-07', '2026-07-12', FiscalRole::Intermediary, 'ACTIVE');

        $this->getJson('/api/v1/documents?direction=OUT')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.access_key', 'KEYOUT')
            ->assertJsonPath('data.0.direction', 'OUT')
            ->assertJsonPath('data.0.direction_label', 'Saída');

        $this->getJson('/api/v1/documents?direction=IN')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $detail = $this->getJson('/api/v1/documents/KEYOUT')->assertOk();
        $detail->assertJsonPath('data.note.direction', 'OUT');
        $detail->assertJsonPath('data.note.direction_label', 'Saída');
    }

    public function test_agregacao_by_client_e_isolamento(): void
    {
        [$office, $user] = $this->seedOfficeUser();
        $officeB = Office::factory()->create();
        $this->actingAs($user);
        app(CurrentOffice::class)->resolve($user);

        $client = Client::factory()->forOffice($office)->create([
            'legal_name' => 'Cliente Agregado',
            'root_cnpj' => '11222333',
        ]);
        $est = Establishment::factory()->forClient($client)->create();
        $clientB = Client::factory()->forOffice($officeB)->create([
            'legal_name' => 'Outro Office',
            'root_cnpj' => '99888777',
        ]);
        $estB = Establishment::factory()->forClient($clientB)->create();

        $n1 = $this->seedNote($office->id, 'AGG1', '2026-07', '2026-07-01', FiscalRole::Issuer, 'ACTIVE', '<a/>', [
            'service_amount' => '20.00',
        ]);
        DocumentInterest::query()->create([
            'office_id' => $office->id,
            'dfe_document_id' => $n1->dfe_document_id,
            'establishment_id' => $est->id,
            'nsu' => 1,
            'environment' => 'production',
            'fiscal_role' => FiscalRole::Issuer,
        ]);
        $n2 = $this->seedNote($office->id, 'AGG2', '2026-07', '2026-07-02', FiscalRole::Issuer, 'ACTIVE', '<b/>', [
            'service_amount' => '30.00',
        ]);
        DocumentInterest::query()->create([
            'office_id' => $office->id,
            'dfe_document_id' => $n2->dfe_document_id,
            'establishment_id' => $est->id,
            'nsu' => 2,
            'environment' => 'production',
            'fiscal_role' => FiscalRole::Issuer,
        ]);
        $nB = $this->seedNote($officeB->id, 'AGGB', '2026-07', '2026-07-01', FiscalRole::Issuer, 'ACTIVE', '<c/>');
        DocumentInterest::query()->create([
            'office_id' => $officeB->id,
            'dfe_document_id' => $nB->dfe_document_id,
            'establishment_id' => $estB->id,
            'nsu' => 1,
            'environment' => 'production',
            'fiscal_role' => FiscalRole::Issuer,
        ]);

        $clientSecond = Client::factory()->forOffice($office)->create([
            'legal_name' => 'Segundo Cliente',
            'root_cnpj' => '44555666',
        ]);
        $estSecond = Establishment::factory()->forClient($clientSecond)->create();
        $n3 = $this->seedNote($office->id, 'AGG3', '2026-07', '2026-07-03', FiscalRole::Issuer, 'ACTIVE', '<d/>');
        DocumentInterest::query()->create([
            'office_id' => $office->id,
            'dfe_document_id' => $n3->dfe_document_id,
            'establishment_id' => $estSecond->id,
            'nsu' => 3,
            'environment' => 'production',
            'fiscal_role' => FiscalRole::Issuer,
        ]);

        $response = $this->getJson('/api/v1/notes/by-client?competence=2026-07&per_page=1&page=1')->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.client_id', $client->id);
        $response->assertJsonPath('data.0.notes_count', 2);
        $response->assertJsonPath('data.0.service_amount_sum', '50.00');
        $response->assertJsonPath('meta.current_page', 1);
        $response->assertJsonPath('meta.last_page', 2);
        $response->assertJsonPath('meta.total', 2);
        $this->getJson('/api/v1/notes/by-client?competence=2026-07&per_page=1&page=2')
            ->assertOk()
            ->assertJsonPath('data.0.client_id', $clientSecond->id);
        $this->assertStringNotContainsString('vault', (string) $response->getContent());
        $body = strtolower((string) $response->getContent());
        $this->assertStringNotContainsString('vault_object', $body);
        $this->assertStringNotContainsString('<?xml', $body);
    }

    public function test_documents_api_inclui_kind_e_filtro_sem_captura(): void
    {
        [$office, $user] = $this->seedOfficeUser();
        $this->actingAs($user);
        app(CurrentOffice::class)->resolve($user);

        $this->seedNote($office->id, 'KEYDOC', '2026-07', '2026-07-10', FiscalRole::Issuer, 'ACTIVE');

        $this->getJson('/api/v1/documents')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.kind', 'NFSE')
            ->assertJsonPath('data.0.kind_label', 'NFS-e')
            ->assertJsonPath('data.0.source', 'ADN')
            ->assertJsonPath('data.0.access_key', 'KEYDOC');

        $this->getJson('/api/v1/documents?kind=NFSE')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        // Tipos comuns sem captura ainda: lista vazia, sem erro
        $this->getJson('/api/v1/documents?kind=NFE')
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.next_cursor', null);

        $this->getJson('/api/v1/documents?kind=CTE')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        // Alias notes continua
        $this->getJson('/api/v1/notes')
            ->assertOk()
            ->assertJsonPath('data.0.kind', 'NFSE');
    }

    public function test_insights_de_triagem_reais(): void
    {
        [$office, $user] = $this->seedOfficeUser();
        $this->actingAs($user);
        app(CurrentOffice::class)->resolve($user);

        $this->seedNote($office->id, 'INS1', '2026-07', '2026-07-01', FiscalRole::Issuer, 'ACTIVE', '<a/>', [
            'issuer_name' => 'A',
            'taker_name' => 'B',
            'service_amount' => '10.00',
        ]);
        $this->seedNote($office->id, 'INS2', '2026-07', '2026-07-02', FiscalRole::Issuer, 'CANCELLED', '<b/>', [
            'issuer_name' => 'A',
            'taker_name' => null,
            'service_amount' => '20.00',
        ]);
        $this->seedNote($office->id, 'INS3', '2026-06', '2026-06-01', FiscalRole::Taker, 'UNKNOWN', '<c/>', [
            'issuer_name' => null,
            'taker_name' => 'C',
        ]);

        $r = $this->getJson('/api/v1/notes/insights')->assertOk();
        $r->assertJsonPath('data.total', 3);
        $r->assertJsonPath('data.cancelled', 1);
        $r->assertJsonPath('data.review', 1);
        $this->assertGreaterThanOrEqual(1, (int) $r->json('data.missing_party_name'));
        $this->assertArrayHasKey('competence_current_label', $r->json('data'));
        $this->assertStringNotContainsString('vault', (string) $r->getContent());
    }
}
