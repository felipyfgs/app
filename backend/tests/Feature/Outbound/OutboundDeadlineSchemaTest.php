<?php

namespace Tests\Feature\Outbound;

use App\Enums\OutboundUrgencyBand;
use App\Models\Office;
use App\Models\OutboundCapacitySnapshot;
use App\Models\OutboundMonthlyReadiness;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OutboundDeadlineSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_colunas_e_tabelas_de_prazo_existem(): void
    {
        $this->assertTrue(Schema::hasColumns('ma_outbound_retrieval_requests', [
            'due_at', 'target_at', 'deadline_source', 'urgency_band', 'deadline_status',
            'svrs_transaction_count', 'next_attempt_at', 'planned_at', 'dispatched_at',
            'accommodation_until', 'captured_at', 'capacity_at_risk', 'slot_key', 'root_cnpj',
        ]));
        $this->assertTrue(Schema::hasTable('outbound_capacity_snapshots'));
        $this->assertTrue(Schema::hasTable('outbound_monthly_readiness'));
        $this->assertTrue(Schema::hasColumn('offices', 'deadline_timezone'));
    }

    public function test_flags_default_off(): void
    {
        $this->assertFalse((bool) config('outbound_deadline.enabled'));
        $this->assertFalse((bool) config('outbound_deadline.planner_enabled'));
        $this->assertFalse((bool) config('outbound_deadline.dispatch_enabled'));
        $this->assertTrue((bool) config('outbound_deadline.shadow_mode'));
        $this->assertFalse((bool) config('outbound_deadline.deadline_retry_policy'));
        $this->assertSame(0.6, (float) config('outbound_deadline.auto_queue_capacity_fraction'));
        $this->assertGreaterThanOrEqual(24, (int) config('outbound_deadline.target_buffer_hours'));
    }

    public function test_model_casts_urgency_e_snapshot_publico(): void
    {
        $office = Office::factory()->create();
        $snap = OutboundCapacitySnapshot::query()->create([
            'office_id' => $office->id,
            'competence' => '2026-07',
            'scope' => 'OFFICE',
            'demand_exchanges' => 10,
            'safe_capacity_exchanges' => 30,
            'nominal_capacity_exchanges' => 50,
            'slack_exchanges' => 20,
            'at_risk' => false,
            'calculated_at' => now(),
        ]);
        $public = $snap->toPublicArray();
        $this->assertSame('2026-07', $public['competence']);
        $this->assertArrayNotHasKey('vault', $public);

        $ready = OutboundMonthlyReadiness::query()->create([
            'office_id' => $office->id,
            'competence' => '2026-07',
            'status' => 'NOT_READY',
            'known_total' => 5,
            'captured_total' => 1,
            'pending_total' => 4,
        ]);
        $this->assertSame('known_documents_only', $ready->toPublicArray()['completeness_scope']);
        $this->assertArrayNotHasKey('manifest_vault_object_id', $ready->toArray());

        $this->assertSame('PLANNED', OutboundUrgencyBand::Planned->value);
    }

    public function test_down_preserva_tabela_de_retrieval(): void
    {
        $this->assertTrue(Schema::hasTable('ma_outbound_retrieval_requests'));
        $migration = require database_path('migrations/2026_07_15_060000_add_outbound_deadline_scheduling_schema.php');
        $migration->down();
        $this->assertTrue(Schema::hasTable('ma_outbound_retrieval_requests'));
        $this->assertFalse(Schema::hasTable('outbound_capacity_snapshots'));
        $migration->up();
        $this->assertTrue(Schema::hasTable('outbound_capacity_snapshots'));
    }
}
