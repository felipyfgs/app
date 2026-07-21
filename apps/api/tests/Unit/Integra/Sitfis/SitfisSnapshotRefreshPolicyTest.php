<?php

namespace Tests\Unit\Integra\Sitfis;

use App\Enums\FiscalCoverage;
use App\Enums\FiscalRunResult;
use App\Enums\FiscalSituation;
use App\Enums\FiscalSourceProvenance;
use App\Enums\FiscalVerificationState;
use App\Models\Client;
use App\Models\FiscalMonitoringRun;
use App\Models\FiscalSnapshot;
use App\Models\Office;
use App\Services\Integra\Sitfis\SitfisSnapshotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SitfisSnapshotRefreshPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_error_snapshot_within_ttl_still_enqueues(): void
    {
        $office = Office::factory()->create();
        $client = Client::factory()->create(['office_id' => $office->id]);
        $run = $this->makeRun($office, $client);

        FiscalSnapshot::query()->create([
            'office_id' => $office->id,
            'run_id' => $run->id,
            'client_id' => $client->id,
            'system_code' => 'INTEGRA_SITFIS',
            'service_code' => 'SITFIS',
            'operation_code' => 'MONITOR',
            'situation' => FiscalSituation::Error,
            'coverage' => FiscalCoverage::Full,
            'version' => 1,
            'is_current' => true,
            'normalized' => ['situation' => 'ERROR'],
            'observed_at' => now(),
            'created_at' => now(),
            'source_provenance' => FiscalSourceProvenance::SerproReal,
            'verification_state' => FiscalVerificationState::Unverified,
        ]);

        $result = app(SitfisSnapshotService::class)->refresh(
            office: $office,
            client: $client,
            force: false,
            actorId: null,
            dispatch: false,
        );

        $this->assertTrue($result['enqueued']);
        $this->assertNotSame('WITHIN_TTL', $result['reason']);
        $this->assertNotNull($result['run']);
    }

    public function test_force_enqueues_even_when_verified_within_ttl(): void
    {
        $office = Office::factory()->create();
        $client = Client::factory()->create(['office_id' => $office->id]);
        $run = $this->makeRun($office, $client);

        FiscalSnapshot::query()->create([
            'office_id' => $office->id,
            'run_id' => $run->id,
            'client_id' => $client->id,
            'system_code' => 'INTEGRA_SITFIS',
            'service_code' => 'SITFIS',
            'operation_code' => 'MONITOR',
            'situation' => FiscalSituation::UpToDate,
            'coverage' => FiscalCoverage::Full,
            'version' => 1,
            'is_current' => true,
            'normalized' => ['situation' => 'UP_TO_DATE'],
            'observed_at' => now(),
            'created_at' => now(),
            'source_provenance' => FiscalSourceProvenance::SerproReal,
            'verification_state' => FiscalVerificationState::Unverified,
        ]);

        // Sem evidência → display_only; force ainda deve enfileirar.
        $result = app(SitfisSnapshotService::class)->refresh(
            office: $office,
            client: $client,
            force: true,
            actorId: null,
            dispatch: false,
        );

        $this->assertTrue($result['enqueued']);
    }

    private function makeRun(Office $office, Client $client): FiscalMonitoringRun
    {
        return FiscalMonitoringRun::query()->create([
            'office_id' => $office->id,
            'client_id' => $client->id,
            'system_code' => 'INTEGRA_SITFIS',
            'service_code' => 'SITFIS',
            'operation_code' => 'MONITOR',
            'trigger' => 'MANUAL',
            'idempotency_key' => 'test-sitfis-'.uniqid(),
            'status' => 'FAILED',
            'result' => FiscalRunResult::Failed->value,
            'situation' => FiscalSituation::Error,
            'coverage' => FiscalCoverage::Full,
            'attempt' => 1,
            'correlation_id' => (string) Str::uuid(),
            'source_provenance' => FiscalSourceProvenance::SerproReal,
            'verification_state' => FiscalVerificationState::Unverified,
        ]);
    }
}
