<?php

namespace Tests\Unit\Fiscal;

use App\Models\Client;
use App\Models\FiscalMonitoringRun;
use App\Models\FiscalMonitoringSchedule;
use App\Models\Office;
use App\Services\Fiscal\SimplesMei\Pgmei\PgmeiYear;
use App\Services\FiscalMonitoring\FiscalMonitoringScheduler;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
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

    public function test_scheduler_blocks_pgmei_operations_21_to_23(): void
    {
        $office = Office::factory()->create();
        $client = Client::factory()->create(['office_id' => $office->id]);
        $scheduler = app(FiscalMonitoringScheduler::class);

        foreach (['GERARDASPDF21', 'GERARDASCODBARRA22', 'ATUBENEFICIO23'] as $operation) {
            $schedule = FiscalMonitoringSchedule::query()->create([
                'office_id' => $office->id,
                'client_id' => $client->id,
                'system_code' => 'INTEGRA_MEI',
                'service_code' => 'PGMEI',
                'operation_code' => $operation,
                'is_enabled' => true,
                'interval_minutes' => 60,
                'preferred_minute' => 0,
                'next_run_at' => CarbonImmutable::now()->subMinute(),
            ]);

            $this->assertSame('blocked', $scheduler->claimAndEnqueue($schedule, CarbonImmutable::now()));
        }

        $this->assertSame(0, FiscalMonitoringRun::query()->count());
    }

    public function test_pgmei_freezes_one_recent_year_and_is_idempotent_per_local_day(): void
    {
        Bus::fake();
        $office = Office::factory()->create(['timezone' => 'America/Sao_Paulo']);
        $client = Client::factory()->create(['office_id' => $office->id, 'tax_regime' => 'MEI']);
        $now = CarbonImmutable::parse('2026-07-17 09:00:00', 'America/Sao_Paulo')->utc();
        $schedule = FiscalMonitoringSchedule::query()->create([
            'office_id' => $office->id,
            'client_id' => $client->id,
            'system_code' => 'INTEGRA_MEI',
            'service_code' => 'PGMEI',
            'operation_code' => 'MONITOR',
            'is_enabled' => true,
            'interval_minutes' => 60,
            'preferred_minute' => 0,
            'next_run_at' => $now->subMinute(),
        ]);

        $scheduler = app(FiscalMonitoringScheduler::class);
        $this->assertSame('dispatched', $scheduler->claimAndEnqueue($schedule, $now));

        $run = FiscalMonitoringRun::query()->sole();
        $expectedYear = PgmeiYear::yearForDailyCycle($now, 'America/Sao_Paulo');
        $this->assertSame((string) $expectedYear, $run->progress['ano_calendario']);
        $this->assertSame($expectedYear, $run->progress['query_year']);

        $schedule->forceFill(['next_run_at' => $now->subMinute()])->save();
        $this->assertSame('skipped', $scheduler->claimAndEnqueue($schedule->fresh(), $now->addHours(2)));
        $this->assertSame(1, FiscalMonitoringRun::query()->count());
    }
}
