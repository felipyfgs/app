<?php

namespace Tests\Unit\Serpro;

use App\Enums\SerproConsumptionClass;
use App\Enums\SerproEnvironment;
use App\Enums\SerproUsageResult;
use App\Models\Office;
use App\Models\SerproApiUsageEntry;
use App\Models\SerproQuantityUsageLimit;
use App\Services\Serpro\SerproQuantityUsageLimitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SerproQuantityUsageLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_null_e_zero_bloqueiam(): void
    {
        $svc = app(SerproQuantityUsageLimitService::class);
        $env = SerproEnvironment::Trial;

        $eval = $svc->evaluate($env, null, 1);
        $this->assertFalse($eval['allowed']);
        $this->assertSame(SerproQuantityUsageLimitService::BLOCK_NOT_CONFIGURED, $eval['block_reason']);

        $svc->upsert($env, 1, 80, null);
        $eval2 = $svc->evaluate($env, null, 1);
        $this->assertFalse($eval2['allowed']);
    }

    public function test_alerta_80_e_bloqueio_100(): void
    {
        $svc = app(SerproQuantityUsageLimitService::class);
        $env = SerproEnvironment::Trial;
        $office = Office::factory()->create();

        $svc->upsert($env, 1, 80, 10, [
            ['office_id' => $office->id, 'limit_quantity' => 10],
        ]);

        // 8 de 10 = 80% alerta, ainda permite 1
        for ($i = 0; $i < 8; $i++) {
            SerproApiUsageEntry::query()->create([
                'office_id' => $office->id,
                'idempotency_key' => 'k-'.$i.'-'.uniqid(),
                'system_code' => 'S',
                'service_code' => 'V',
                'operation_code' => 'O',
                'operation_key' => 'op',
                'consumption_class' => SerproConsumptionClass::Consulta,
                'quantity' => 1,
                'result' => SerproUsageResult::Success,
                'is_simulated' => false,
                'is_billable_attempt' => true,
                'occurred_at' => now(),
                'created_at' => now(),
                'environment' => $env->value,
            ]);
        }

        $eval = $svc->evaluate($env, $office->id, 1);
        $this->assertTrue($eval['allowed']);
        $this->assertTrue($eval['alert_reached']);

        // +2 reservas projetadas estouram 10
        $evalFull = $svc->evaluate($env, $office->id, 3);
        $this->assertFalse($evalFull['allowed']);
    }

    public function test_ciclo_1_a_28(): void
    {
        $svc = app(SerproQuantityUsageLimitService::class);
        $this->expectException(\RuntimeException::class);
        $svc->upsert(SerproEnvironment::Trial, 29, 80, 10);
    }

    public function test_config_defaults_persistidos(): void
    {
        $svc = app(SerproQuantityUsageLimitService::class);
        $row = $svc->getOrDefault(SerproEnvironment::Production);
        $this->assertInstanceOf(SerproQuantityUsageLimit::class, $row);
        $this->assertSame(80, (int) $row->alert_percent);
        $this->assertNull($row->global_limit_quantity);
    }
}
