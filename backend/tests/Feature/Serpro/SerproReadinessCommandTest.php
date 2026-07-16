<?php

namespace Tests\Feature\Serpro;

use App\Models\Office;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SerproReadinessCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_serpro_readiness_command_json_offline(): void
    {
        $this->artisan('serpro:readiness', ['--json' => true, '--no-persist' => true])
            ->assertSuccessful();
    }

    public function test_serpro_readiness_office_persist(): void
    {
        $office = Office::factory()->create(['slug' => 'ops-office-'.uniqid()]);

        $this->artisan('serpro:readiness', [
            '--office' => $office->id,
            '--json' => true,
        ])->assertExitCode(0);

        $this->assertDatabaseHas('serpro_readiness_runs', [
            'scope' => 'OFFICE',
            'office_id' => $office->id,
        ]);
    }

    public function test_ops_scan_command_runs(): void
    {
        $this->artisan('serpro:ops-scan', ['--json' => true])
            ->assertSuccessful();
    }
}
