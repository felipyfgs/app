<?php

namespace Tests\Unit\Fiscal;

use App\Models\Client;
use App\Models\FiscalMonitoringRun;
use App\Models\FiscalMonitoringSchedule;
use App\Models\Office;
use App\Services\FiscalMonitoring\FiscalMonitoringScheduler;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SchedulerDoesNotRunMutationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_scheduler_blocks_mutating_schedules(): void
    {
        $office = Office::factory()->create();
        $client = Client::factory()->create(['office_id' => $office->id]);

        $schedule = FiscalMonitoringSchedule::query()->create([
            'office_id' => $office->id,
            'client_id' => $client->id,
            'system_code' => 'PGDASD',
            'service_code' => 'PGDASD',
            'operation_code' => 'TRANSMITIR',
            'is_enabled' => true,
            'interval_minutes' => 60,
            'preferred_minute' => 0,
            'next_run_at' => CarbonImmutable::now()->subMinute(),
            'metadata' => ['mutability' => 'MUTATING'],
        ]);

        $result = app(FiscalMonitoringScheduler::class)->claimAndEnqueue(
            $schedule,
            CarbonImmutable::now(),
        );

        $this->assertSame('blocked', $result);
        $this->assertSame(0, FiscalMonitoringRun::query()->count());
        $this->assertSame(
            'MUTATING_NOT_SCHEDULED',
            $schedule->fresh()->last_skip_reason,
        );
    }

    public function test_mutating_enabled_config_still_blocks_adapter_path_by_default(): void
    {
        $this->assertFalse((bool) config('fiscal_monitoring.mutating_enabled', false));
    }
}
