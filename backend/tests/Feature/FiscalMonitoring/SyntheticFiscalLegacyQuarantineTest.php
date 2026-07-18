<?php

namespace Tests\Feature\FiscalMonitoring;

use App\Models\Client;
use App\Models\EsocialEventEvidence;
use App\Models\FgtsCompetenceStatus;
use App\Models\FiscalEvidenceArtifact;
use App\Models\FiscalGuideStub;
use App\Models\FiscalMonitoringRun;
use App\Models\Office;
use App\Services\Esocial\EsocialEvidencePersistence;
use App\Services\Esocial\FgtsEsocialMonitoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SyntheticFiscalLegacyQuarantineTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_preserva_mas_quarentena_legado_esocial_e_guias_sinteticas(): void
    {
        $office = Office::factory()->create();
        $client = Client::factory()->forOffice($office)->create();
        $run = $this->legacyRun($office, $client);

        $artifact = FiscalEvidenceArtifact::query()->create([
            'office_id' => $office->id,
            'run_id' => $run->id,
            'vault_object_id' => '01J00000000000000000000000',
            'content_sha256' => str_repeat('a', 64),
            'content_type' => 'application/json',
            'byte_size' => 12,
            'source' => 'esocial',
            'source_version' => 'fake-1',
            'observed_at' => now(),
            'is_immutable' => true,
        ]);

        $event = EsocialEventEvidence::query()->create([
            'office_id' => $office->id,
            'client_id' => $client->id,
            'run_id' => $run->id,
            'fiscal_evidence_artifact_id' => $artifact->id,
            'competence_period_key' => '2026-06',
            'event_code' => 'S-5003',
            'content_sha256' => str_repeat('b', 64),
            'content_type' => 'application/json',
            'byte_size' => 12,
            'source' => 'esocial',
            'source_version' => 'fake-1',
            'observed_at' => now(),
            'metadata' => ['simulated' => true],
        ]);

        $status = FgtsCompetenceStatus::query()->create([
            'office_id' => $office->id,
            'client_id' => $client->id,
            'competence_period_key' => '2026-06',
            'closure_status' => 'CONFIRMED',
            'totalization_status' => 'PRESENT',
            'guide_status' => 'UNSUPPORTED',
            'payment_status' => 'UNSUPPORTED',
            'coverage' => 'PARTIAL',
            'situation' => 'ATTENTION',
            'last_synced_at' => now(),
        ]);

        $guide = FiscalGuideStub::query()->create([
            'office_id' => $office->id,
            'client_id' => $client->id,
            'system_code' => 'INTEGRA_SN',
            'service_code' => 'PGDASD',
            'operation_code' => 'GERAR_DAS',
            'regime_family' => 'SIMPLES_NACIONAL',
            'period_key' => '2026-06',
            'document_number' => 'STUB-202606-001',
            'emission_status' => 'STUB',
            'payment_status' => 'UNKNOWN',
            'is_external_call' => false,
        ]);

        $migration = require database_path('migrations/2026_07_18_103000_quarantine_synthetic_fiscal_legacy.php');
        $migration->up();

        $event->refresh();
        $status->refresh();
        $guide->refresh();
        $artifact->refresh();
        $run->refresh();

        $this->assertTrue($event->is_quarantined);
        $this->assertSame('SYNTHETIC_ESOCIAL_LEGACY', $event->quarantine_reason);
        $this->assertTrue($status->is_quarantined);
        $this->assertTrue($guide->is_quarantined);
        $this->assertSame('SYNTHETIC_GUIDE_LEGACY', $guide->quarantine_reason);
        $this->assertSame('REJECTED', $artifact->verification_state?->value);
        $this->assertSame('REJECTED', $run->verification_state?->value);

        // O dado continua acessível por id para reconciliação, mas não é elegível.
        $this->assertNotNull(EsocialEventEvidence::query()->withoutGlobalScopes()->find($event->id));
        $this->assertSame([], app(EsocialEvidencePersistence::class)->listForCompetence(
            $office,
            $client,
            '2026-06',
        ));
        $this->assertSame(0, app(FgtsEsocialMonitoringService::class)->paginateStatuses($office)->total());
        $this->assertSame(0, FiscalGuideStub::query()->operationallyEligible()->count());
        $this->assertSame(0, FiscalEvidenceArtifact::query()->operationallyEligible()->count());
        $this->assertTrue($guide->toPublicArray()['is_quarantined']);
    }

    private function legacyRun(Office $office, Client $client): FiscalMonitoringRun
    {
        return FiscalMonitoringRun::query()->create([
            'office_id' => $office->id,
            'client_id' => $client->id,
            'system_code' => 'ESOCIAL',
            'service_code' => 'FGTS',
            'operation_code' => 'MONITOR',
            'trigger' => 'MANUAL',
            'idempotency_key' => 'legacy-quarantine-'.$office->id.'-'.$client->id,
            'status' => 'COMPLETED',
            'result' => 'SUCCESS',
            'situation' => 'ATTENTION',
            'coverage' => 'PARTIAL',
            'mutability' => 'READ_ONLY',
        ]);
    }
}
