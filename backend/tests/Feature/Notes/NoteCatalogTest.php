<?php

namespace Tests\Feature\Notes;

use App\Contracts\SecureObjectStore;
use App\Enums\AdnDocumentType;
use App\Enums\FiscalRole;
use App\Enums\OfficeRole;
use App\Models\DfeDocument;
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

    /**
     * @return array{0: Office, 1: User}
     */
    private function seedOfficeUser(): array
    {
        $office = Office::factory()->create();
        $user = User::factory()->forOffice($office, OfficeRole::Operator)->withTwoFactorConfirmed()->create();

        return [$office, $user];
    }

    private function seedNote(
        int $officeId,
        string $accessKey,
        string $competence,
        string $issuedAt,
        FiscalRole $role,
        string $status,
        string $xml = '<NFSe/>',
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

        return NfseNote::query()->create([
            'office_id' => $officeId,
            'dfe_document_id' => $doc->id,
            'access_key' => $accessKey,
            'issuer_cnpj' => '11222333000181',
            'taker_cnpj' => '99888777000166',
            'fiscal_role' => $role,
            'competence' => $competence,
            'issued_at' => $issuedAt,
            'service_amount' => '10.00',
            'status' => $status,
        ]);
    }
}
