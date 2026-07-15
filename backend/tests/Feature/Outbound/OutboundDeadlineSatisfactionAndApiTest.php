<?php

namespace Tests\Feature\Outbound;

use App\Enums\OfficeRole;
use App\Enums\OutboundCaptureMode;
use App\Enums\OutboundFiscalModel;
use App\Enums\OutboundProfileStatus;
use App\Enums\OutboundRetrievalOrigin;
use App\Enums\OutboundUrgencyBand;
use App\Enums\SvrsNfceRecoveryStatus;
use App\Models\Client;
use App\Models\DfeDocument;
use App\Models\Establishment;
use App\Models\MaOutboundRetrievalRequest;
use App\Models\Office;
use App\Models\OutboundCaptureProfile;
use App\Models\User;
use App\Services\Outbound\OutboundDeadlineSatisfactionService;
use App\Services\Outbound\OutboundMonthlyReadinessService;
use App\Support\CurrentOffice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OutboundDeadlineSatisfactionAndApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_mark_captured_cancela_svrs_e_preenche_prazo(): void
    {
        [$office, $profile, $est] = $this->seedProfile();
        $key = '35260799888777000166550010000000011234567999';

        $req = $this->makeRecovery($office, $profile, $est, $key, OutboundUrgencyBand::Planned, SvrsNfceRecoveryStatus::RetryScheduled, [
            'attempt_count' => 1,
            'svrs_transaction_count' => 1,
            'next_attempt_at' => now()->addDay(),
        ]);

        app(OutboundDeadlineSatisfactionService::class)->markCapturedBySource(
            $office->id,
            $key,
            'MANUAL_XML',
            hash('sha256', 'x'),
        );

        $req->refresh();
        $this->assertSame(SvrsNfceRecoveryStatus::ResolvedByOtherSource, $req->recovery_status);
        $this->assertSame(OutboundUrgencyBand::Captured, $req->urgency_band);
        $this->assertNotNull($req->captured_at);
        $this->assertNull($req->next_attempt_at);
        $this->assertSame('MANUAL_XML', $req->capture_source);
    }

    public function test_prefer_existing_source_encontra_dfe(): void
    {
        $office = Office::factory()->create();
        $key = '35260799888777000166550010000000011234567998';
        DfeDocument::query()->create([
            'office_id' => $office->id,
            'sha256' => hash('sha256', 'doc'),
            'document_type' => \App\Enums\AdnDocumentType::Nfe,
            'access_key' => $key,
            'vault_object_id' => '01TESTTESTTESTTESTTESTTESTTEST99',
            'byte_size' => 10,
            'parse_status' => 'OK',
        ]);

        $pref = app(OutboundDeadlineSatisfactionService::class)
            ->preferExistingSource($office->id, $key);

        $this->assertTrue($pref['has_full']);
        $this->assertSame('VAULT_DFE', $pref['source']);
    }

    public function test_contingency_batch_isolado_por_office(): void
    {
        [$officeA, $profileA, $estA] = $this->seedProfile();
        [$officeB, $profileB, $estB] = $this->seedProfile();

        $this->makeRecovery($officeA, $profileA, $estA, '35260711222333000181550010000000011234567001', OutboundUrgencyBand::Contingency, SvrsNfceRecoveryStatus::Eligible, [
            'root_cnpj' => '11222333',
            'due_at' => now()->addDay(),
            'competence' => '2026-07',
            'model' => OutboundFiscalModel::Nfe,
        ]);
        $this->makeRecovery($officeB, $profileB, $estB, '35260799888777000166550010000000011234567002', OutboundUrgencyBand::Overdue, SvrsNfceRecoveryStatus::Eligible, [
            'root_cnpj' => '99888777',
            'competence' => '2026-07',
        ]);

        $batch = app(OutboundDeadlineSatisfactionService::class)->contingencyBatch($officeA->id, '2026-07');
        $this->assertCount(1, $batch);
        $this->assertSame('ASSISTED_IMPORT_OR_PACKAGE', $batch[0]['recommended_action']);
        $this->assertNotNull($batch[0]['access_key_masked']);
    }

    public function test_api_competence_summary_tenant_e_viewer(): void
    {
        [$office, $profile, $est] = $this->seedProfile();
        $viewer = User::factory()->forOffice($office, OfficeRole::Viewer)->withTwoFactorConfirmed()->create();
        $this->actingAs($viewer);
        app(CurrentOffice::class)->resolve($viewer);

        $this->makeRecovery($office, $profile, $est, '35260799888777000166550010000000011234567003', OutboundUrgencyBand::Planned, SvrsNfceRecoveryStatus::Eligible, [
            'competence' => '2026-07',
        ]);

        $this->getJson('/api/v1/outbound/deadline/competence?competence=2026-07')
            ->assertOk()
            ->assertJsonPath('data.competence', '2026-07')
            ->assertJsonPath('data.completeness_scope', 'known_documents_only')
            ->assertJsonPath('data.known_total', 1);

        $this->getJson('/api/v1/outbound/deadline/capacity?competence=2026-07')
            ->assertOk()
            ->assertJsonStructure(['data' => ['projection' => ['safe_capacity_exchanges', 'auto_queue_fraction']]]);
    }

    public function test_viewer_nao_confirma_parcial_nem_avanca_meta(): void
    {
        $office = Office::factory()->create();
        $viewer = User::factory()->forOffice($office, OfficeRole::Viewer)->withTwoFactorConfirmed()->create();
        $this->actingAs($viewer);
        app(CurrentOffice::class)->resolve($viewer);

        $this->postJson('/api/v1/outbound/deadline/confirm-partial', [
            'competence' => '2026-07',
        ])->assertForbidden();

        $this->postJson('/api/v1/outbound/deadline/advance-target', [
            'competence' => '2026-07',
            'target_at' => now()->subDay()->toIso8601String(),
        ])->assertForbidden();
    }

    public function test_operator_confirma_parcial(): void
    {
        [$office, $profile, $est] = $this->seedProfile();
        $op = User::factory()->forOffice($office, OfficeRole::Operator)->withTwoFactorConfirmed()->create();
        $this->actingAs($op);
        app(CurrentOffice::class)->resolve($op);

        $this->makeRecovery($office, $profile, $est, '35260711222333000181550010000000011234567004', OutboundUrgencyBand::Contingency, SvrsNfceRecoveryStatus::Eligible, [
            'competence' => '2026-07',
            'model' => OutboundFiscalModel::Nfe,
        ]);

        $this->postJson('/api/v1/outbound/deadline/confirm-partial', [
            'competence' => '2026-07',
            'notes' => 'Export parcial autorizado',
        ])->assertOk()
            ->assertJsonPath('data.status', 'PARTIAL_CONFIRMED')
            ->assertJsonPath('data.completeness_scope', 'known_documents_only');
    }

    public function test_admin_nao_posterga_meta_alem_do_due(): void
    {
        $office = Office::factory()->create();
        $admin = User::factory()->forOffice($office, OfficeRole::Admin)->withTwoFactorConfirmed()->create();
        $this->actingAs($admin);
        app(CurrentOffice::class)->resolve($admin);

        $this->postJson('/api/v1/outbound/deadline/advance-target', [
            'competence' => '2026-07',
            'target_at' => '2030-01-01T00:00:00Z',
        ])->assertStatus(422);
    }

    public function test_readiness_complete_known_label(): void
    {
        $office = Office::factory()->create();
        $svc = app(OutboundMonthlyReadinessService::class);
        $row = $svc->refresh($office->id, '2026-07');
        $this->assertSame('known_documents_only', $row->toPublicArray()['completeness_scope']);
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
            'model' => OutboundFiscalModel::Nfce,
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
        SvrsNfceRecoveryStatus $status,
        array $extra = [],
    ): MaOutboundRetrievalRequest {
        return MaOutboundRetrievalRequest::query()->create(array_merge([
            'office_id' => $office->id,
            'outbound_capture_profile_id' => $profile->id,
            'establishment_id' => $est->id,
            'environment' => 'homologation',
            'model' => OutboundFiscalModel::Nfce,
            'direction' => 'OUT',
            'competence' => '2026-07',
            'status' => 'PENDING',
            'mode' => OutboundCaptureMode::Automatic,
            'origin' => OutboundRetrievalOrigin::SvrsPortalByKey,
            'access_key' => $key,
            'recovery_status' => $status,
            'urgency_band' => $band,
        ], $extra));
    }
}
