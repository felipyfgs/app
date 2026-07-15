<?php

namespace Tests\Feature\Outbound;

use App\Contracts\SecureObjectStore;
use App\Enums\AdnDocumentType;
use App\Enums\FiscalRole;
use App\Enums\OfficeRole;
use App\Enums\OutboundCaptureMode;
use App\Enums\OutboundFiscalModel;
use App\Enums\OutboundProfileStatus;
use App\Enums\OutboundRetrievalOrigin;
use App\Enums\OutboundUrgencyBand;
use App\Enums\SvrsNfceRecoveryStatus;
use App\Jobs\BuildExportZipJob;
use App\Models\Client;
use App\Models\DfeDocument;
use App\Models\Establishment;
use App\Models\Export;
use App\Models\MaOutboundRetrievalRequest;
use App\Models\NfeDocument;
use App\Models\Office;
use App\Models\OutboundCaptureProfile;
use App\Models\User;
use App\Services\FiscalMonitoring\ModulePortfolio\ModulePortfolioQueryService;
use App\Services\Outbound\OutboundMonthlyExportService;
use App\Support\CurrentOffice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;
use ZipArchive;

class OutboundMonthlyExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_not_ready_recusa_export_mensal(): void
    {
        [$office, $profile, $est] = $this->seedProfile();
        $op = User::factory()->forOffice($office, OfficeRole::Operator)->withTwoFactorConfirmed()->create();
        $this->actingAs($op);
        app(CurrentOffice::class)->resolve($op);

        $this->makeRecovery($office, $profile, $est, '35260799888777000166550010000000011234567011', OutboundUrgencyBand::Planned);

        $this->postJson('/api/v1/outbound/deadline/export', [
            'competence' => '2026-07',
        ])->assertStatus(422);
    }

    public function test_viewer_nao_exporta_mensal(): void
    {
        $office = Office::factory()->create();
        $viewer = User::factory()->forOffice($office, OfficeRole::Viewer)->withTwoFactorConfirmed()->create();
        $this->actingAs($viewer);
        app(CurrentOffice::class)->resolve($viewer);

        $this->postJson('/api/v1/outbound/deadline/export', [
            'competence' => '2026-07',
        ])->assertForbidden();
    }

    public function test_partial_confirm_gera_export_com_manifesto_e_zip_privado(): void
    {
        Queue::fake();
        [$office, $profile, $est] = $this->seedProfile();
        $op = User::factory()->forOffice($office, OfficeRole::Operator)->withTwoFactorConfirmed()->create();
        $this->actingAs($op);
        app(CurrentOffice::class)->resolve($op);

        $keyPending = '35260799888777000166550010000000011234567012';
        $keyCaptured = '35260799888777000166550010000000011234567013';
        $this->makeRecovery($office, $profile, $est, $keyPending, OutboundUrgencyBand::Contingency);
        $this->makeRecovery($office, $profile, $est, $keyCaptured, OutboundUrgencyBand::Captured, SvrsNfceRecoveryStatus::Captured, [
            'captured_at' => now(),
            'capture_source' => 'MANUAL_XML',
        ]);
        $this->seedNfeOut($office->id, $keyCaptured, '2026-07');

        $this->postJson('/api/v1/outbound/deadline/confirm-partial', [
            'competence' => '2026-07',
            'notes' => 'Cliente pediu parcial',
        ])->assertOk()->assertJsonPath('data.status', 'PARTIAL_CONFIRMED');

        $this->postJson('/api/v1/outbound/deadline/export', [
            'competence' => '2026-07',
        ])->assertStatus(202)
            ->assertJsonPath('data.has_manifest', true)
            ->assertJsonPath('data.completeness_scope', 'known_documents_only');

        Queue::assertPushed(BuildExportZipJob::class);
        $export = Export::query()->latest('id')->first();
        $this->assertNotNull($export);
        $this->assertSame($office->id, $export->office_id);
        $manifest = $export->filters['absence_manifest_path'] ?? null;
        $this->assertIsString($manifest);
        $this->assertFileExists($manifest);
        $json = json_decode((string) file_get_contents($manifest), true);
        $this->assertSame('known_documents_only', $json['completeness_scope']);
        $this->assertNotEmpty($json['absences']);
        $this->assertStringContainsString('****', $json['absences'][0]['access_key_masked']);
        $this->assertStringNotContainsString($keyPending, json_encode($json));

        // Gera ZIP com manifesto embutido
        (new BuildExportZipJob($export->id))->handle(
            app(SecureObjectStore::class),
            app(ModulePortfolioQueryService::class),
        );
        $export->refresh();
        $this->assertSame('READY', $export->status);
        $this->assertStringContainsString('/private/exports/'.$office->id.'/', $export->storage_path);
        $this->assertFileExists($export->storage_path);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($export->storage_path) === true);
        $names = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $names[] = $zip->getNameIndex($i);
        }
        $zip->close();
        $this->assertTrue(collect($names)->contains(fn ($n) => str_contains($n, 'manifesto-ausencias')));
    }

    public function test_download_respeita_office_id(): void
    {
        $officeA = Office::factory()->create();
        $officeB = Office::factory()->create();
        $userA = User::factory()->forOffice($officeA, OfficeRole::Operator)->withTwoFactorConfirmed()->create();
        $userB = User::factory()->forOffice($officeB, OfficeRole::Operator)->withTwoFactorConfirmed()->create();

        $dir = storage_path('app/private/exports/'.$officeA->id);
        if (! is_dir($dir)) {
            mkdir($dir, 0700, true);
        }
        $path = $dir.'/export-test.zip';
        file_put_contents($path, 'PK');

        $export = Export::query()->create([
            'office_id' => $officeA->id,
            'user_id' => $userA->id,
            'status' => 'READY',
            'filters' => [],
            'storage_path' => $path,
            'expires_at' => now()->addHour(),
            'files_count' => 1,
            'byte_size' => 2,
        ]);

        $this->actingAs($userB);
        app(CurrentOffice::class)->resolve($userB);
        $this->get("/api/v1/exports/{$export->id}/download")->assertNotFound();

        $this->actingAs($userA);
        app(CurrentOffice::class)->resolve($userA);
        // path válido sob office A
        $this->get("/api/v1/exports/{$export->id}/download")->assertOk();
    }

    public function test_manifesto_nao_vaza_outro_tenant(): void
    {
        [$officeA, $profileA, $estA] = $this->seedProfile();
        [$officeB, $profileB, $estB] = $this->seedProfile();
        $this->makeRecovery($officeA, $profileA, $estA, '35260711222333000181550010000000011234567021', OutboundUrgencyBand::Overdue);
        $this->makeRecovery($officeB, $profileB, $estB, '35260799888777000166550010000000011234567022', OutboundUrgencyBand::Overdue);

        $manifest = app(OutboundMonthlyExportService::class)
            ->buildAbsenceManifest($officeA->id, '2026-07');

        $this->assertSame($officeA->id, $manifest['office_id']);
        $this->assertCount(1, $manifest['absences']);
        $encoded = json_encode($manifest);
        $this->assertStringNotContainsString('99888777', $encoded);
    }

    /**
     * @return array{0: Office, 1: OutboundCaptureProfile, 2: Establishment}
     */
    private function seedProfile(): array
    {
        $office = Office::factory()->create();
        $client = Client::factory()->forOffice($office)->create();
        $est = Establishment::factory()->forClient($client)->create([
            'office_id' => $office->id,
            'address_state' => 'MA',
        ]);
        $profile = OutboundCaptureProfile::query()->create([
            'office_id' => $office->id,
            'client_id' => $client->id,
            'establishment_id' => $est->id,
            'uf' => 'MA',
            'environment' => 'homologation',
            'model' => OutboundFiscalModel::Nfe,
            'mode' => OutboundCaptureMode::Assisted,
            'status' => OutboundProfileStatus::SeedReady,
        ]);

        return [$office, $profile, $est];
    }

    private function makeRecovery(
        Office $office,
        OutboundCaptureProfile $profile,
        Establishment $est,
        string $key,
        OutboundUrgencyBand $band,
        SvrsNfceRecoveryStatus $status = SvrsNfceRecoveryStatus::Eligible,
        array $extra = [],
    ): MaOutboundRetrievalRequest {
        return MaOutboundRetrievalRequest::query()->create(array_merge([
            'office_id' => $office->id,
            'outbound_capture_profile_id' => $profile->id,
            'establishment_id' => $est->id,
            'environment' => 'homologation',
            'model' => OutboundFiscalModel::Nfe,
            'direction' => 'OUT',
            'competence' => '2026-07',
            'status' => 'PENDING',
            'mode' => OutboundCaptureMode::Automatic,
            'origin' => OutboundRetrievalOrigin::SvrsPortalByKey,
            'access_key' => $key,
            'root_cnpj' => substr($key, 6, 8),
            'recovery_status' => $status,
            'urgency_band' => $band,
            'due_at' => now()->addDay(),
            'target_at' => now(),
        ], $extra));
    }

    private function seedNfeOut(int $officeId, string $key, string $competence): void
    {
        $xml = '<nfeProc><NFe><infNFe Id="NFe'.$key.'"/></NFe></nfeProc>';
        $sha = hash('sha256', $xml);
        $store = app(SecureObjectStore::class);
        $objectId = $store->put($xml, ['office_id' => $officeId, 'sha256' => $sha]);
        $doc = DfeDocument::query()->create([
            'office_id' => $officeId,
            'sha256' => $sha,
            'document_type' => AdnDocumentType::Nfe,
            'access_key' => $key,
            'vault_object_id' => $objectId,
            'byte_size' => strlen($xml),
            'parse_status' => 'OK',
        ]);
        NfeDocument::query()->create([
            'office_id' => $officeId,
            'dfe_document_id' => $doc->id,
            'access_key' => $key,
            'issuer_cnpj' => substr($key, 6, 14),
            'model' => '55',
            'series' => 1,
            'number' => 1,
            'direction' => 'OUT',
            'fiscal_role' => FiscalRole::Issuer,
            'status' => 'AUTHORIZED',
            'is_summary' => false,
            'issued_at' => $competence.'-15 12:00:00',
        ]);
    }
}
