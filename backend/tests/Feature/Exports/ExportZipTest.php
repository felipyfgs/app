<?php

namespace Tests\Feature\Exports;

use App\Contracts\SecureObjectStore;
use App\Enums\AdnDocumentType;
use App\Enums\DocumentDirection;
use App\Enums\FiscalRole;
use App\Enums\OfficeRole;
use App\Jobs\BuildExportZipJob;
use App\Models\Client;
use App\Models\DfeDocument;
use App\Models\DocumentInterest;
use App\Models\Establishment;
use App\Models\Export;
use App\Models\NfseNote;
use App\Models\Office;
use App\Models\User;
use App\Services\FiscalMonitoring\ModulePortfolio\ModulePortfolioQueryService;
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
            'filters' => ['competence' => '2026-07', 'direction' => 'OUT'],
            'include_events' => true,
        ])->assertStatus(202);

        Queue::assertPushed(BuildExportZipJob::class);
        $this->assertSame('OUT', Export::query()->latest('id')->first()?->filters['direction'] ?? null);
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

        (new BuildExportZipJob($export->id))->handle(
            app(SecureObjectStore::class),
            app(ModulePortfolioQueryService::class),
        );

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
        // saida|entrada / kind / cnpj / YYYYMM / chave.xml — sem pasta ISSUER/TAKER
        $this->assertStringContainsString('202607', $names[0]);
        $this->assertStringNotContainsString('2026-07', $names[0]);
        $this->assertStringContainsString('CHAVE1', $names[0]);
        $this->assertStringNotContainsString('/ISSUER/', $names[0]);
        $this->assertStringNotContainsString('/TAKER/', $names[0]);
        $this->assertStringNotContainsString('..', $names[0]);
        $this->assertMatchesRegularExpression(
            '#^(entrada|saida)/nfse/[A-Z0-9]+/202607/CHAVE1\.xml$#',
            $names[0]
        );
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

    public function test_export_por_access_keys_respeita_teto_e_isolamento(): void
    {
        Queue::fake();
        [$office, $user] = $this->seedOperator();
        $officeB = Office::factory()->create();
        $this->actingAs($user);
        app(CurrentOffice::class)->resolve($user);

        $this->seedNote($office->id, 'KEY-A', '2026-07', FiscalRole::Issuer, '<a/>');
        $this->seedNote($office->id, 'KEY-B', '2026-07', FiscalRole::Issuer, '<b/>');
        $this->seedNote($officeB->id, 'KEY-OTHER', '2026-07', FiscalRole::Issuer, '<c/>');

        $tooMany = array_map(fn (int $i) => 'K'.$i, range(1, BuildExportZipJob::MAX_ACCESS_KEYS + 1));
        $this->postJson('/api/v1/exports', [
            'filters' => ['access_keys' => $tooMany],
        ])->assertStatus(422);

        $this->postJson('/api/v1/exports', [
            'filters' => ['access_keys' => ['KEY-A', 'KEY-OTHER']],
        ])->assertStatus(202);

        $export = Export::query()->latest('id')->first();
        $this->assertNotNull($export);
        $this->assertSame(['KEY-A', 'KEY-OTHER'], $export->filters['access_keys'] ?? null);

        (new BuildExportZipJob($export->id))->handle(
            app(SecureObjectStore::class),
            app(ModulePortfolioQueryService::class),
        );
        $export->refresh();
        $this->assertSame('READY', $export->status);
        // KEY-OTHER de outro office não entra (scope + where office_id no job).
        $this->assertSame(1, $export->files_count);
    }

    public function test_export_filtra_por_client_id_via_interest(): void
    {
        [$office, $user] = $this->seedOperator();
        $this->actingAs($user);
        app(CurrentOffice::class)->resolve($user);

        $client = Client::factory()->forOffice($office)->create(['root_cnpj' => '11222333']);
        $est = Establishment::factory()->forClient($client)->create();
        $other = Client::factory()->forOffice($office)->create(['root_cnpj' => '99888777']);
        $estOther = Establishment::factory()->forClient($other)->create();

        $this->seedNoteWithInterest($office->id, 'WITH-C', '2026-07', FiscalRole::Issuer, '<a/>', $est->id);
        $this->seedNoteWithInterest($office->id, 'OTHER-C', '2026-07', FiscalRole::Issuer, '<b/>', $estOther->id);

        $export = Export::query()->create([
            'office_id' => $office->id,
            'user_id' => $user->id,
            'status' => 'PENDING',
            'filters' => ['client_id' => $client->id],
            'include_events' => false,
        ]);

        (new BuildExportZipJob($export->id))->handle(
            app(SecureObjectStore::class),
            app(ModulePortfolioQueryService::class),
        );
        $export->refresh();
        $this->assertSame('READY', $export->status);
        $this->assertSame(1, $export->files_count);
    }

    public function test_historico_de_exportacoes_e_paginado_e_isolado(): void
    {
        [$office, $user] = $this->seedOperator();
        $otherUser = User::factory()->forOffice($office, OfficeRole::Operator)->withTwoFactorConfirmed()->create();
        $this->actingAs($user);
        app(CurrentOffice::class)->resolve($user);

        foreach (range(1, 3) as $index) {
            Export::query()->create([
                'office_id' => $office->id,
                'user_id' => $user->id,
                'status' => 'PENDING',
                'filters' => ['competence' => "2026-0{$index}"],
                'include_events' => false,
            ]);
        }
        Export::query()->create([
            'office_id' => $office->id,
            'user_id' => $otherUser->id,
            'status' => 'PENDING',
            'filters' => [],
            'include_events' => false,
        ]);

        $first = $this->getJson('/api/v1/exports?per_page=2&page=1')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('meta.total', 3);

        $second = $this->getJson('/api/v1/exports?per_page=2&page=2')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $ids = collect($first->json('data'))->merge($second->json('data'))->pluck('id');
        $this->assertCount(3, $ids->unique());
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
        $this->seedNoteWithInterest($officeId, $key, $comp, $role, $xml, null);
    }

    private function seedNoteWithInterest(
        int $officeId,
        string $key,
        string $comp,
        FiscalRole $role,
        string $xml,
        ?int $establishmentId,
    ): void {
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
            'direction' => DocumentDirection::fromFiscalRole($role),
            'competence' => $comp,
            'issued_at' => $comp.'-01',
            'status' => 'ACTIVE',
            'service_amount' => '10.00',
        ]);
        if ($establishmentId !== null) {
            DocumentInterest::query()->create([
                'office_id' => $officeId,
                'dfe_document_id' => $doc->id,
                'establishment_id' => $establishmentId,
                'nsu' => 1,
                'environment' => 'production',
                'fiscal_role' => $role,
            ]);
        }
    }
}
