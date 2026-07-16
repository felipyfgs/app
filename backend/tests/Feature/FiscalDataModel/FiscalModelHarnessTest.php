<?php

namespace Tests\Feature\FiscalDataModel;

use App\Models\Client;
use App\Models\Office;
use App\Support\FiscalDataModel\FiscalModelAggregates;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FiscalModelHarnessTest extends TestCase
{
    use RefreshDatabase;

    public function test_harness_tables_exist_with_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('fiscal_model_migration_maps'));
        $this->assertTrue(Schema::hasTable('fiscal_model_backfill_checkpoints'));
        $this->assertTrue(Schema::hasColumn('fiscal_model_migration_maps', 'aggregate'));
        $this->assertTrue(Schema::hasColumn('fiscal_model_migration_maps', 'correlation_id'));
        $this->assertTrue(Schema::hasColumn('fiscal_model_migration_maps', 'status'));
        $this->assertTrue(Schema::hasColumn('fiscal_model_backfill_checkpoints', 'cursor_key'));
    }

    public function test_backfill_tenancy_dry_run_and_idempotent_apply(): void
    {
        $office = Office::factory()->create();
        Client::factory()->forOffice($office)->count(3)->create();

        $exit = Artisan::call('fiscal-model:backfill', [
            'aggregate' => FiscalModelAggregates::TENANCY_CADASTRO,
            '--dry-run' => true,
            '--json' => true,
        ]);
        $this->assertSame(0, $exit);
        $dry = json_decode(Artisan::output(), true);
        $this->assertSame(3, $dry['processed']);
        $this->assertSame(3, $dry['mapped']);
        $this->assertSame(0, (int) DB::table('fiscal_model_migration_maps')->count());

        $exit = Artisan::call('fiscal-model:backfill', [
            'aggregate' => FiscalModelAggregates::TENANCY_CADASTRO,
            '--json' => true,
        ]);
        $this->assertSame(0, $exit);
        $this->assertSame(3, (int) DB::table('fiscal_model_migration_maps')->count());

        $exit = Artisan::call('fiscal-model:backfill', [
            'aggregate' => FiscalModelAggregates::TENANCY_CADASTRO,
            '--json' => true,
        ]);
        $this->assertSame(0, $exit);
        $second = json_decode(Artisan::output(), true);
        $this->assertSame(3, $second['skipped']);
        $this->assertSame(0, $second['mapped']);
        $this->assertSame(3, (int) DB::table('fiscal_model_migration_maps')->count());
    }

    public function test_reconcile_passes_on_clean_fixture(): void
    {
        $office = Office::factory()->create();
        Client::factory()->forOffice($office)->create();

        $exit = Artisan::call('fiscal-model:reconcile', [
            'aggregate' => FiscalModelAggregates::TENANCY_CADASTRO,
            '--json' => true,
        ]);
        $this->assertSame(0, $exit);
        $report = json_decode(Artisan::output(), true);
        $this->assertTrue($report['passed']);
    }

    public function test_unknown_aggregate_fails(): void
    {
        $exit = Artisan::call('fiscal-model:backfill', [
            'aggregate' => 'not_real',
        ]);
        $this->assertSame(1, $exit);
    }
}
