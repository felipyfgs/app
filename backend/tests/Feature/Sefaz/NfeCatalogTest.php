<?php

namespace Tests\Feature\Sefaz;

use App\Enums\AdnDocumentType;
use App\Enums\OfficeRole;
use App\Models\DfeDocument;
use App\Models\NfeDocument;
use App\Models\Office;
use App\Models\User;
use App\Support\CurrentOffice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NfeCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_documents_kind_nfe_lista_projecao(): void
    {
        [$office, $user] = $this->seedOfficeUser();
        $this->actingAs($user);
        app(CurrentOffice::class)->resolve($user);

        $this->seedNfe($office->id, '35240111222333000181550010000000011000000010', false);

        $this->getJson('/api/v1/documents?kind=NFE')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.kind', 'NFE')
            ->assertJsonPath('data.0.source', 'SEFAZ')
            ->assertJsonPath('data.0.access_key', '35240111222333000181550010000000011000000010');
    }

    public function test_documents_kind_cte_sem_captura_vazio(): void
    {
        [$office, $user] = $this->seedOfficeUser();
        $this->actingAs($user);
        app(CurrentOffice::class)->resolve($user);
        $this->seedNfe($office->id, '35240111222333000181550010000000011000000011', false);

        $this->getJson('/api/v1/documents?kind=CTE')
            ->assertOk()
            ->assertJsonCount(0, 'data');
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

    private function seedNfe(int $officeId, string $accessKey, bool $summary): NfeDocument
    {
        $xml = '<nfeProc><chNFe>'.$accessKey.'</chNFe></nfeProc>';
        $sha = hash('sha256', $xml.$accessKey);
        $doc = DfeDocument::query()->create([
            'office_id' => $officeId,
            'sha256' => $sha,
            'document_type' => AdnDocumentType::Nfe,
            'schema_version' => 'procNFe_v4.00.xsd',
            'access_key' => $accessKey,
            'vault_object_id' => '01TESTTESTTESTTESTTESTTESTTEST'.$officeId,
            'byte_size' => strlen($xml),
            'parse_status' => 'OK',
        ]);

        return NfeDocument::query()->create([
            'office_id' => $officeId,
            'dfe_document_id' => $doc->id,
            'access_key' => $accessKey,
            'number' => '1',
            'issuer_cnpj' => '11222333000181',
            'issuer_name' => 'Fornecedor',
            'recipient_cnpj' => '99888777000166',
            'recipient_name' => 'Cliente',
            'issued_at' => '2026-07-01',
            'total_amount' => '100.00',
            'status' => $summary ? 'SUMMARY' : 'ACTIVE',
            'is_summary' => $summary,
            'manifestation_status' => $summary ? 'PENDING_MANIFESTATION' : null,
        ]);
    }
}
