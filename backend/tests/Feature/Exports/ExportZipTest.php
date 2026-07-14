<?php

namespace Tests\Feature\Exports;

use App\Contracts\SecureObjectStore;
use App\Enums\AdnDocumentType;
use App\Enums\FiscalRole;
use App\Enums\OfficeRole;
use App\Jobs\BuildExportZipJob;
use App\Models\DfeDocument;
use App\Models\Export;
use App\Models\NfseNote;
use App\Models\Office;
use App\Models\User;
use App\Support\CurrentOffice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;
use ZipArchive;

class ExportZipTest extends TestCase
{
    use RefreshDatabase;

    public function test_cria_exportacao_assincrona_e_registra_auditoria(): void
    {
        Queue::fake();
        [$office, $user] = $this->seedOperator();
        $this->actingAs($user);
        app(CurrentOffice::class)->resolve($user);

        $this->postJson('/api/v1/exports', [
            'filters' => ['competence' => '2026-07'],
            'include_events' => true,
        ])->assertStatus(202);

        Queue::assertPushed(BuildExportZipJob::class);
        $this->assertDatabaseHas('audit_logs', ['action' => 'export.create']);
    }

    public function test_zip_paths_seguros_deduplica_e_aplica_filtros(): void
    {
        [$office, $user] = $this->seedOperator();
        $this->actingAs($user);
        app(CurrentOffice::class)->resolve($user);

        $this->seedNote($office->id, 'CHAVE1', '2026-07', FiscalRole::Issuer, '<a/>');
        $this->seedNote($office->id, 'CHAVE2', '2026-06', FiscalRole::Taker, '<b/>');

        $export = Export::query()->create([
            'office_id' => $office->id,
            'user_id' => $user->id,
            'status' => 'PENDING',
            'filters' => ['competence' => '2026-07'],
            'include_events' => false,
        ]);

        (new BuildExportZipJob($export->id))->handle(app(SecureObjectStore::class));

        $export->refresh();
        $this->assertSame('READY', $export->status);
        $this->assertSame(1, $export->files_count);
        $this->assertNotNull($export->storage_path);
        $this->assertFileExists($export->storage_path);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($export->storage_path) === true);
        $names = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $names[] = $zip->getNameIndex($i);
        }
        $zip->close();

        $this->assertCount(1, $names);
        $this->assertStringContainsString('2026-07', $names[0]);
        $this->assertStringContainsString('CHAVE1', $names[0]);
        $this->assertStringNotContainsString('..', $names[0]);
    }

    public function test_viewer_nao_exporta_e_outro_usuario_nao_baixa(): void
    {
        $office = Office::factory()->create();
        $viewer = User::factory()->forOffice($office, OfficeRole::Viewer)->withTwoFactorConfirmed()->create();
        $op = User::factory()->forOffice($office, OfficeRole::Operator)->withTwoFactorConfirmed()->create();

        $this->actingAs($viewer);
        app(CurrentOffice::class)->resolve($viewer);
        $this->postJson('/api/v1/exports', ['filters' => []])->assertForbidden();

        $this->actingAs($op);
        app(CurrentOffice::class)->resolve($op);
        $export = Export::query()->create([
            'office_id' => $office->id,
            'user_id' => $op->id,
            'status' => 'READY',
            'filters' => [],
            'storage_path' => storage_path('app/private/exports/x.zip'),
            'expires_at' => now()->addHour(),
        ]);

        $this->actingAs($viewer);
        app(CurrentOffice::class)->resolve($viewer);
        $this->get("/api/v1/exports/{$export->id}/download")->assertNotFound();
    }

    /**
     * @return array{0: Office, 1: User}
     */
    private function seedOperator(): array
    {
        $office = Office::factory()->create();
        $user = User::factory()->forOffice($office, OfficeRole::Operator)->withTwoFactorConfirmed()->create();

        return [$office, $user];
    }

    private function seedNote(int $officeId, string $key, string $comp, FiscalRole $role, string $xml): void
    {
        $sha = hash('sha256', $xml.$key);
        $objectId = app(SecureObjectStore::class)->put($xml, ['office_id' => $officeId, 'sha256' => $sha]);
        $doc = DfeDocument::query()->create([
            'office_id' => $officeId,
            'sha256' => $sha,
            'document_type' => AdnDocumentType::Nfse,
            'schema_version' => 'NFSe_v1.00.xsd',
            'access_key' => $key,
            'vault_object_id' => $objectId,
            'byte_size' => strlen($xml),
            'parse_status' => 'OK',
        ]);
        NfseNote::query()->create([
            'office_id' => $officeId,
            'dfe_document_id' => $doc->id,
            'access_key' => $key,
            'issuer_cnpj' => '11222333000181',
            'fiscal_role' => $role,
            'competence' => $comp,
            'issued_at' => $comp.'-01',
            'status' => 'ACTIVE',
        ]);
    }
}
