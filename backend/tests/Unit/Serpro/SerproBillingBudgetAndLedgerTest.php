<?php

namespace Tests\Unit\Serpro;

use App\Enums\SerproUsageReservationStatus;
use App\Enums\SerproUsageResult;
use App\Models\Office;
use App\Models\SerproUsageBudget;
use App\Services\Serpro\Usage\ContractPriceTableImporter;
use App\Services\Serpro\Usage\UsageLedgerService;
use App\Services\Serpro\Usage\UsageReserveRequest;
use App\Services\Serpro\Usage\UsageShadowSegregationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SerproBillingBudgetAndLedgerTest extends TestCase
{
    use RefreshDatabase;

    private Office $office;

    private UsageLedgerService $ledger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->office = Office::factory()->create();
        $this->ledger = app(UsageLedgerService::class);
        app(ContractPriceTableImporter::class)->importFromFile();
    }

    public function test_budget_monetario_positivo_obrigatorio_em_modo_produtivo(): void
    {
        config([
            'serpro_usage.shadow_mode' => false,
            'serpro_usage.commercial_blocking_enabled' => true,
            'serpro_usage.require_positive_monetary_budgets' => true,
            'serpro_usage.require_production_price_table' => true,
        ]);

        $blocked = $this->ledger->reserve(new UsageReserveRequest(
            officeId: $this->office->id,
            idempotencyKey: 'no-budget-1',
            systemCode: 'INTEGRA_CONTADOR',
            serviceCode: 'SITFIS',
            operationCode: 'CONSULTAR_SITUACAO',
            functionalRoute: 'Consultar',
            environment: 'PRODUCTION',
        ));

        $this->assertFalse($blocked->allowed);
        $this->assertSame(SerproUsageReservationStatus::Blocked, $blocked->reservation->status);
        $this->assertSame('BUDGET_NOT_CONFIGURED', $blocked->reservation->block_reason);
    }

    public function test_reserva_atomica_e_bloqueio_quando_saldo_insuficiente(): void
    {
        config([
            'serpro_usage.shadow_mode' => false,
            'serpro_usage.commercial_blocking_enabled' => true,
            'serpro_usage.require_positive_monetary_budgets' => true,
            'serpro_usage.require_production_price_table' => true,
        ]);

        $this->seedBudgets(globalMicros: 150_000, officeMicros: 150_000);

        $a = $this->ledger->reserve(new UsageReserveRequest(
            officeId: $this->office->id,
            idempotencyKey: 'budget-a',
            systemCode: 'INTEGRA_CONTADOR',
            serviceCode: 'SITFIS',
            operationCode: 'CONSULTAR_SITUACAO',
            functionalRoute: 'Consultar',
            environment: 'PRODUCTION',
            requestTag: 'tag-budget-a-aaaaaaaaaaaaaa',
        ));
        $this->assertTrue($a->allowed, (string) $a->reservation->block_reason);
        $this->assertNotNull($a->reservation->request_tag);
        $this->assertLessThanOrEqual(32, strlen((string) $a->reservation->request_tag));
        $this->assertSame('tag-budget-a-aaaaaaaaaaaaaa', $a->reservation->request_tag);

        // Segunda consulta (100k) estoura teto de 150k
        $b = $this->ledger->reserve(new UsageReserveRequest(
            officeId: $this->office->id,
            idempotencyKey: 'budget-b',
            systemCode: 'INTEGRA_CONTADOR',
            serviceCode: 'SITFIS',
            operationCode: 'CONSULTAR_SITUACAO',
            functionalRoute: 'Consultar',
            environment: 'PRODUCTION',
        ));
        $this->assertFalse($b->allowed);
        $this->assertContains(
            $b->reservation->block_reason,
            ['MONETARY_GLOBAL_BUDGET', 'MONETARY_OFFICE_BUDGET'],
        );
    }

    public function test_timeout_incerto_marca_possibly_billable(): void
    {
        config([
            'serpro_usage.shadow_mode' => true,
            'serpro_usage.commercial_blocking_enabled' => false,
        ]);

        $outcome = $this->ledger->reserve(new UsageReserveRequest(
            officeId: $this->office->id,
            idempotencyKey: 'timeout-1',
            systemCode: 'INTEGRA_CONTADOR',
            serviceCode: 'SITFIS',
            operationCode: 'CONSULTAR_SITUACAO',
            functionalRoute: 'Consultar',
            requestTag: 'tag-timeout-1-bbbbbbbbbbbbbb',
        ));
        $this->assertTrue($outcome->allowed);

        $entry = $this->ledger->finalize(
            $outcome->reservation,
            SerproUsageResult::Timeout,
            latencyMs: 30_000,
            // sem httpStatus → incerto
        );

        $this->assertTrue($entry->is_billable_attempt);
        $this->assertSame(SerproUsageResult::Timeout, $entry->result);
        $reservation = $outcome->reservation->fresh();
        $this->assertTrue((bool) $reservation->possibly_billable);
    }

    public function test_simulado_custa_zero_e_nao_e_faturavel(): void
    {
        $outcome = $this->ledger->reserve(new UsageReserveRequest(
            officeId: $this->office->id,
            idempotencyKey: 'sim-1',
            systemCode: 'INTEGRA_CONTADOR',
            serviceCode: 'SITFIS',
            operationCode: 'CONSULTAR_SITUACAO',
            isSimulated: true,
            functionalRoute: 'Consultar',
        ));
        $this->assertTrue($outcome->allowed);
        $this->assertSame(0, (int) $outcome->reservation->estimated_cost_micros);

        $entry = $this->ledger->finalize($outcome->reservation, SerproUsageResult::Success, httpStatus: 200);
        $this->assertFalse($entry->is_billable_attempt);
        $this->assertSame(0, (int) $entry->estimated_cost_micros);
    }

    public function test_segregacao_shadow_e_retencao_impede_purge(): void
    {
        $outcome = $this->ledger->reserve(new UsageReserveRequest(
            officeId: $this->office->id,
            idempotencyKey: 'seg-1',
            systemCode: 'INTEGRA_CONTADOR',
            serviceCode: 'SITFIS',
            operationCode: 'CONSULTAR_SITUACAO',
        ));
        $this->ledger->finalize($outcome->reservation, SerproUsageResult::Success, httpStatus: 200);

        $svc = app(UsageShadowSegregationService::class);
        $result = $svc->segregateLegacyShadow();
        $this->assertIsArray($result);

        $purge = $svc->assertLedgerRetentionAllowsPurge($this->office->id);
        $this->assertFalse($purge['allowed']);
        $this->assertSame('LEDGER_UNDER_RETENTION', $purge['reason']);
    }

    private function seedBudgets(int $globalMicros, int $officeMicros): void
    {
        $now = now()->subDay();
        SerproUsageBudget::query()->create([
            'scope' => 'GLOBAL',
            'office_id' => null,
            'environment' => 'PRODUCTION',
            'budget_kind' => 'MONETARY',
            'limit_micros' => $globalMicros,
            'reserved_micros' => 0,
            'consumed_micros' => 0,
            'is_canary' => false,
            'effective_from' => $now,
            'is_active' => true,
        ]);
        SerproUsageBudget::query()->create([
            'scope' => 'OFFICE',
            'office_id' => $this->office->id,
            'environment' => 'PRODUCTION',
            'budget_kind' => 'MONETARY',
            'limit_micros' => $officeMicros,
            'reserved_micros' => 0,
            'consumed_micros' => 0,
            'is_canary' => false,
            'effective_from' => $now,
            'is_active' => true,
        ]);
    }
}
