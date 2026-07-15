<?php

namespace Tests\Feature\Sefaz;

use App\Enums\AdnDocumentType;
use App\Enums\DocumentDirection;
use App\Enums\FiscalRole;
use App\Enums\OfficeRole;
use App\Models\DfeDocument;
use App\Models\NfeDocument;
use App\Models\Office;
use App\Models\User;
use App\Support\CurrentOffice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NfeXmlUnlockTest extends TestCase
{
    use RefreshDatabase;

    public function test_unlock_noop_quando_ja_tem_full(): void
    {
        [$office, $user] = $this->seedOfficeUser();
        $this->actingAs($user);
        app(CurrentOffice::class)->resolve($user);

        $key = '35260711222333000181550010000000019999999999';
        $this->seedNfe($office->id, $key, false);

        $this->postJson('/api/v1/documents/'.$key.'/unlock-xml')
            ->assertOk()
            ->assertJsonPath('data.status', 'already_full')
            ->assertJsonPath('data.has_full_xml', true);
    }

    public function test_unlock_flag_off_com_resumo(): void
    {
        config(['sefaz.manifest_enabled' => false]);
        [$office, $user] = $this->seedOfficeUser();
        $this->actingAs($user);
        app(CurrentOffice::class)->resolve($user);

        $key = '35260711222333000181550010000000018888888888';
        $this->seedNfe($office->id, $key, true);

        $this->postJson('/api/v1/documents/'.$key.'/unlock-xml')
            ->assertStatus(422)
            ->assertJsonPath('data.status', 'flag_off');
    }

    public function test_viewer_403(): void
    {
        $office = Office::factory()->create();
        $viewer = User::factory()->forOffice($office, OfficeRole::Viewer)->withTwoFactorConfirmed()->create();
        $this->actingAs($viewer);
        app(CurrentOffice::class)->resolve($viewer);

        $key = '35260711222333000181550010000000017777777777';
        $this->seedNfe($office->id, $key, true);

        $this->postJson('/api/v1/documents/'.$key.'/unlock-xml')->assertForbidden();
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

    private function seedNfe(int $officeId, string $accessKey, bool $summary): void
    {
        $xml = $summary ? '<resNFe/>' : '<nfeProc/>';
        $sha = hash('sha256', $xml.$accessKey.($summary ? 's' : 'f'));
        $store = app(\App\Contracts\SecureObjectStore::class);
        $objectId = $store->put($xml, ['office_id' => $officeId, 'sha256' => $sha]);

        $doc = DfeDocument::query()->create([
            'office_id' => $officeId,
            'sha256' => $sha,
            'document_type' => AdnDocumentType::Nfe,
            'schema_version' => 'x',
            'access_key' => $accessKey,
            'vault_object_id' => $objectId,
            'byte_size' => strlen($xml),
            'parse_status' => 'OK',
        ]);

        NfeDocument::query()->create([
            'office_id' => $officeId,
            'dfe_document_id' => $doc->id,
            'access_key' => $accessKey,
            'model' => '55',
            'fiscal_role' => FiscalRole::Taker,
            'direction' => DocumentDirection::In,
            'status' => $summary ? 'SUMMARY' : 'ACTIVE',
            'is_summary' => $summary,
        ]);
    }
}
