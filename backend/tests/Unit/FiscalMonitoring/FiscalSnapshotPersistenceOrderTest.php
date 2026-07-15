<?php

namespace Tests\Unit\FiscalMonitoring;

use App\DTO\Fiscal\FiscalPersistPayload;
use App\Enums\FiscalCoverage;
use App\Enums\FiscalRunResult;
use App\Enums\FiscalRunStatus;
use App\Enums\FiscalSituation;
use App\Enums\FiscalTrigger;
use App\Models\Client;
use App\Models\FiscalEvidenceArtifact;
use App\Models\FiscalMonitoringRun;
use App\Models\FiscalSnapshot;
use App\Models\Office;
use App\Services\FiscalMonitoring\FiscalSnapshotPersistence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Garante ordem atômica: evidência+snapshot antes de projeções.
 */
class FiscalSnapshotPersistenceOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_evidencia_e_snapshot_existem_mesmo_com_findings_invalidos_parciais(): void
    {
        $office = Office::factory()->create();
        $client = Client::factory()->forOffice($office)->create();

        $run = FiscalMonitoringRun::query()->create([
            'office_id' => $office->id,
            'client_id' => $client->id,
            'system_code' => 'SYS',
            'service_code' => 'SVC',
            'operation_code' => 'MONITOR',
            'trigger' => FiscalTrigger::Manual,
            'idempotency_key' => 'order-test-1',
            'status' => FiscalRunStatus::Running,
            'situation' => FiscalSituation::Processing,
            'coverage' => FiscalCoverage::Full,
        ]);

        $payload = new FiscalPersistPayload(
            run: $run,
            result: FiscalRunResult::Success,
            situation: FiscalSituation::Pending,
            coverage: FiscalCoverage::Full,
            evidenceBytes: json_encode(['ok' => true], JSON_THROW_ON_ERROR),
            findings: [
                [
                    'code' => 'F1',
                    'title' => 'Finding 1',
                    'creates_pending' => true,
                ],
                // segundo finding com mesmo code no mesmo snapshot → updateOrCreate (não quebra)
                [
                    'code' => 'F1',
                    'title' => 'Finding 1b',
                    'creates_pending' => true,
                ],
            ],
        );

        $out = app(FiscalSnapshotPersistence::class)->persist($payload);

        $this->assertNotNull($out['evidence_id']);
        $this->assertNotNull($out['snapshot']);
        $this->assertSame(1, FiscalEvidenceArtifact::query()->withoutGlobalScopes()
            ->where('run_id', $run->id)->count());
        $this->assertSame(1, FiscalSnapshot::query()->withoutGlobalScopes()
            ->where('run_id', $run->id)->count());
        $this->assertSame(FiscalRunStatus::Completed, $out['run']->status);
    }
}
