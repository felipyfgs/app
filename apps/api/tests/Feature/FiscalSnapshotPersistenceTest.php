<?php

namespace Tests\Feature;

use App\DTO\Fiscal\FiscalPersistPayload;
use App\Enums\FiscalCoverage;
use App\Enums\FiscalRunResult;
use App\Enums\FiscalRunStatus;
use App\Enums\FiscalSituation;
use App\Enums\FiscalTrigger;
use App\Models\Client;
use App\Models\FiscalMonitoringRun;
use App\Models\FiscalSnapshot;
use App\Models\Office;
use App\Services\FiscalMonitoring\FiscalSnapshotPersistence;
use App\Support\CurrentOffice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FiscalSnapshotPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_worker_versions_only_the_target_office_snapshot_without_current_office(): void
    {
        config(['fiscal_data_model.fail_closed_scopes' => true]);

        $targetOffice = Office::factory()->create();
        $targetClient = Client::factory()->forOffice($targetOffice)->create();
        $targetPreviousRun = $this->createRun($targetOffice, $targetClient, 'target-previous');
        $targetCurrentSnapshot = $this->createCurrentSnapshot($targetPreviousRun, 4);
        $targetRun = $this->createRun($targetOffice, $targetClient, 'target');

        $otherOffice = Office::factory()->create();
        $otherClient = Client::factory()->forOffice($otherOffice)->create();
        $otherRun = $this->createRun($otherOffice, $otherClient, 'other');
        $otherCurrentSnapshot = $this->createCurrentSnapshot($otherRun, 7);

        app(CurrentOffice::class)->clear();

        self::assertNull(app(CurrentOffice::class)->id());
        self::assertNull(FiscalMonitoringRun::query()->find($targetRun->id));

        $persisted = app(FiscalSnapshotPersistence::class)->persist(new FiscalPersistPayload(
            run: $targetRun,
            result: FiscalRunResult::Failed,
            situation: FiscalSituation::Error,
            coverage: FiscalCoverage::Unknown,
            errorCode: 'WORKER_FAILURE',
            errorMessage: 'Falha controlada no worker.',
        ));

        self::assertSame($targetRun->id, $persisted['run']->id);
        self::assertSame(FiscalRunStatus::Failed, $persisted['run']->status);
        self::assertSame(FiscalRunResult::Failed, $persisted['run']->result);
        self::assertSame($targetOffice->id, $persisted['snapshot']?->office_id);
        self::assertSame($targetRun->id, $persisted['snapshot']?->run_id);
        self::assertSame(5, $persisted['snapshot']?->version);
        self::assertTrue((bool) $persisted['snapshot']?->is_current);

        $targetSnapshots = FiscalSnapshot::query()
            ->withoutGlobalScopes()
            ->where('office_id', $targetOffice->id)
            ->where('client_id', $targetClient->id)
            ->where('system_code', $targetRun->system_code)
            ->where('service_code', $targetRun->service_code)
            ->whereNull('competence_id');

        self::assertSame(2, (clone $targetSnapshots)->count());
        self::assertSame(1, (clone $targetSnapshots)->where('is_current', true)->count());
        self::assertFalse((bool) FiscalSnapshot::query()
            ->withoutGlobalScopes()
            ->findOrFail($targetCurrentSnapshot->id)
            ->is_current);

        $untouchedRun = FiscalMonitoringRun::query()->withoutGlobalScopes()->findOrFail($otherRun->id);
        $untouchedSnapshot = FiscalSnapshot::query()
            ->withoutGlobalScopes()
            ->findOrFail($otherCurrentSnapshot->id);

        self::assertSame(FiscalRunStatus::Running, $untouchedRun->status);
        self::assertNull($untouchedRun->result);
        self::assertSame(7, $untouchedSnapshot->version);
        self::assertTrue($untouchedSnapshot->is_current);
        self::assertSame($otherOffice->id, $untouchedSnapshot->office_id);
    }

    private function createRun(Office $office, Client $client, string $suffix): FiscalMonitoringRun
    {
        return FiscalMonitoringRun::query()->withoutGlobalScopes()->create([
            'office_id' => $office->id,
            'client_id' => $client->id,
            'system_code' => 'INTEGRA_MEI',
            'service_code' => 'DASN_SIMEI',
            'operation_code' => 'MONITORAR',
            'trigger' => FiscalTrigger::Scheduled,
            'idempotency_key' => "snapshot-persistence:{$suffix}",
            'status' => FiscalRunStatus::Running,
            'lease_owner' => "worker-{$suffix}",
            'locked_at' => now(),
        ]);
    }

    private function createCurrentSnapshot(FiscalMonitoringRun $run, int $version): FiscalSnapshot
    {
        return FiscalSnapshot::query()->withoutGlobalScopes()->create([
            'office_id' => $run->office_id,
            'run_id' => $run->id,
            'client_id' => $run->client_id,
            'competence_id' => $run->competence_id,
            'system_code' => $run->system_code,
            'service_code' => $run->service_code,
            'operation_code' => $run->operation_code,
            'situation' => FiscalSituation::Error,
            'coverage' => FiscalCoverage::Unknown,
            'version' => $version,
            'is_current' => true,
            'normalized' => [],
            'observed_at' => now(),
            'created_at' => now(),
        ]);
    }
}
