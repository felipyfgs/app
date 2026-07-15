<?php

namespace Tests\Unit\Serpro\Usage;

use App\Enums\SerproConsumptionClass;
use App\Enums\SerproUsageReservationStatus;
use App\Enums\SerproUsageResult;
use App\Models\Office;
use App\Models\OfficeSubscription;
use App\Models\SerproApiUsageEntry;
use App\Models\SerproApiUsageReservation;
use App\Models\SerproPriceTier;
use App\Models\SerproPriceVersion;
use App\Services\Serpro\Usage\UsageLedgerService;
use App\Services\Serpro\Usage\UsageReserveRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class UsageLedgerServiceTest extends TestCase
{
    use RefreshDatabase;

    private UsageLedgerService $ledger;

    private Office $office;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ledger = app(UsageLedgerService::class);
        $this->office = Office::factory()->create();
    }

    public function test_retry_idempotente_nao_duplica_consumo(): void
    {
        $req = new UsageReserveRequest(
            officeId: $this->office->id,
            idempotencyKey: 'op-idem-1',
            systemCode: 'INTEGRA_CONTADOR',
            serviceCode: 'SITFIS',
            operationCode: 'CONSULTAR_SITUACAO',
        );

        $first = $this->ledger->reserve($req);
        $this->assertFalse($first->replayed);
        $this->assertTrue($first->allowed);

        $second = $this->ledger->reserve($req);
        $this->assertTrue($second->replayed);
        $this->assertSame($first->reservation->id, $second->reservation->id);

        $entryA = $this->ledger->finalize($first->reservation, SerproUsageResult::Success, latencyMs: 12);
        $entryB = $this->ledger->finalize($first->reservation, SerproUsageResult::Success, latencyMs: 99);

        $this->assertSame($entryA->id, $entryB->id);
        $this->assertSame(1, SerproApiUsageEntry::query()->withoutGlobalScopes()->count());
        $this->assertSame(1, SerproApiUsageReservation::query()->withoutGlobalScopes()->count());
        // Custo histórico preservado na 1ª finalização (não recalcula latência em entry)
        $this->assertSame(12, $entryB->latency_ms);
    }

    public function test_preco_por_vigencia_preserva_estimativa_historica(): void
    {
        $old = SerproPriceVersion::query()->where('version_code', 'v1-shadow-2026')->firstOrFail();

        // Nova versão a partir de amanhã com preço 10x
        $new = SerproPriceVersion::query()->create([
            'version_code' => 'v2-future',
            'name' => 'Tabela futura',
            'effective_from' => now()->addDay(),
            'effective_to' => null,
            'is_active' => true,
            'currency' => 'BRL',
        ]);
        SerproPriceTier::query()->create([
            'price_version_id' => $new->id,
            'consumption_class' => SerproConsumptionClass::Consulta,
            'min_quantity' => 1,
            'max_quantity' => null,
            'unit_cost_micros' => 1_000_000,
            'sort_order' => 0,
        ]);

        // Encerra vigência da v1 "agora+1s" e cria v2 efetiva agora (simula troca)
        // Em vez disso: reserva com v1, depois altera tiers da v1 — entry deve manter micros da reserva
        $outcome = $this->ledger->reserve(new UsageReserveRequest(
            officeId: $this->office->id,
            idempotencyKey: 'price-hist-1',
            systemCode: 'INTEGRA_CONTADOR',
            serviceCode: 'SITFIS',
            operationCode: 'CONSULTAR_SITUACAO',
        ));

        $originalCost = $outcome->reservation->estimated_cost_micros;
        $this->assertNotNull($originalCost);
        $this->assertSame($old->id, $outcome->reservation->price_version_id);

        // Muda preço da versão vigente (não deve afetar entrada já reservada)
        SerproPriceTier::query()
            ->where('price_version_id', $old->id)
            ->where('consumption_class', SerproConsumptionClass::Consulta->value)
            ->update(['unit_cost_micros' => 9_999_999]);

        $entry = $this->ledger->finalize($outcome->reservation, SerproUsageResult::Success);

        $this->assertSame($originalCost, $entry->estimated_cost_micros);
        $this->assertSame($old->id, $entry->price_version_id);
    }

    public function test_classe_desconhecida_nao_inventa_custo_zero(): void
    {
        Log::spy();

        $outcome = $this->ledger->reserve(new UsageReserveRequest(
            officeId: $this->office->id,
            idempotencyKey: 'unknown-op-1',
            systemCode: 'INTEGRA_CONTADOR',
            serviceCode: 'NOVO_SERVICO',
            operationCode: 'OPERACAO_X',
        ));

        $this->assertTrue($outcome->allowed);
        $this->assertSame(
            SerproConsumptionClass::Desconhecida,
            $outcome->reservation->consumption_class,
        );
        $this->assertNull($outcome->reservation->estimated_cost_micros);

        $entry = $this->ledger->finalize($outcome->reservation, SerproUsageResult::Success);
        $this->assertNull($entry->estimated_cost_micros);
        $this->assertTrue($entry->is_billable_attempt);

        Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $msg) => str_contains($msg, 'unknown_operation_class'))
            ->atLeast()
            ->once();
    }

    public function test_falha_possivelmente_faturavel_gera_entrada(): void
    {
        $outcome = $this->ledger->reserve(new UsageReserveRequest(
            officeId: $this->office->id,
            idempotencyKey: 'fail-billable-1',
            systemCode: 'INTEGRA_CONTADOR',
            serviceCode: 'SITFIS',
            operationCode: 'CONSULTAR_SITUACAO',
        ));

        $entry = $this->ledger->finalize(
            $outcome->reservation,
            SerproUsageResult::Timeout,
            latencyMs: 30_000,
            possiblyBillable: true,
        );

        $this->assertTrue($entry->is_billable_attempt);
        $this->assertSame(SerproUsageResult::Timeout, $entry->result);
        $this->assertSame(
            SerproUsageReservationStatus::Finalized,
            $outcome->reservation->fresh()->status,
        );
    }

    public function test_release_nao_cria_entrada_faturavel(): void
    {
        $outcome = $this->ledger->reserve(new UsageReserveRequest(
            officeId: $this->office->id,
            idempotencyKey: 'release-1',
            systemCode: 'INTEGRA_CONTADOR',
            serviceCode: 'SITFIS',
            operationCode: 'CONSULTAR_SITUACAO',
        ));

        $this->ledger->release($outcome->reservation);

        $this->assertSame(0, SerproApiUsageEntry::query()->withoutGlobalScopes()->count());
        $this->assertSame(
            SerproUsageReservationStatus::Released,
            $outcome->reservation->fresh()->status,
        );
    }

    public function test_entrada_imutavel_rejeita_update_de_valores(): void
    {
        $outcome = $this->ledger->reserve(new UsageReserveRequest(
            officeId: $this->office->id,
            idempotencyKey: 'immut-1',
            systemCode: 'INTEGRA_CONTADOR',
            serviceCode: 'SITFIS',
            operationCode: 'CONSULTAR_SITUACAO',
        ));
        $entry = $this->ledger->finalize($outcome->reservation, SerproUsageResult::Success);

        $this->expectException(\LogicException::class);
        $entry->estimated_cost_micros = 1;
        $entry->save();
    }

    public function test_shadow_mode_registra_mas_nao_bloqueia_comercialmente(): void
    {
        config([
            'serpro_usage.shadow_mode' => true,
            'serpro_usage.commercial_blocking_enabled' => true,
        ]);

        OfficeSubscription::query()->where('office_id', $this->office->id)->update([
            'monthly_api_quota' => 1,
        ]);

        // Consome a franquia
        $a = $this->ledger->reserve(new UsageReserveRequest(
            officeId: $this->office->id,
            idempotencyKey: 'shadow-a',
            systemCode: 'INTEGRA_CONTADOR',
            serviceCode: 'SITFIS',
            operationCode: 'CONSULTAR_SITUACAO',
        ));
        $this->ledger->finalize($a->reservation, SerproUsageResult::Success);

        // Segunda (não essencial) — em shadow deve permitir com would_block
        $b = $this->ledger->reserve(new UsageReserveRequest(
            officeId: $this->office->id,
            idempotencyKey: 'shadow-b',
            systemCode: 'INTEGRA_CONTADOR',
            serviceCode: 'GUIAS',
            operationCode: 'EMITIR_GUIA',
        ));

        $this->assertTrue($b->allowed);
        $this->assertTrue($b->reservation->would_block);
        $this->assertSame(SerproUsageReservationStatus::Reserved, $b->reservation->status);
    }

    public function test_bloqueio_comercial_impede_nao_essencial(): void
    {
        config([
            'serpro_usage.shadow_mode' => false,
            'serpro_usage.commercial_blocking_enabled' => true,
        ]);

        OfficeSubscription::query()->where('office_id', $this->office->id)->update([
            'monthly_api_quota' => 1,
        ]);

        $a = $this->ledger->reserve(new UsageReserveRequest(
            officeId: $this->office->id,
            idempotencyKey: 'block-a',
            systemCode: 'INTEGRA_CONTADOR',
            serviceCode: 'SITFIS',
            operationCode: 'CONSULTAR_SITUACAO',
        ));
        $this->ledger->finalize($a->reservation, SerproUsageResult::Success);

        $b = $this->ledger->reserve(new UsageReserveRequest(
            officeId: $this->office->id,
            idempotencyKey: 'block-b',
            systemCode: 'INTEGRA_CONTADOR',
            serviceCode: 'GUIAS',
            operationCode: 'EMITIR_GUIA',
        ));

        $this->assertFalse($b->allowed);
        $this->assertSame(SerproUsageReservationStatus::Blocked, $b->reservation->status);
        $this->assertSame(0, SerproApiUsageEntry::query()->withoutGlobalScopes()
            ->where('idempotency_key', 'block-b')->count());
    }

    public function test_protecao_tenant_ruidoso_via_share_global(): void
    {
        config([
            'serpro_usage.shadow_mode' => false,
            'serpro_usage.commercial_blocking_enabled' => true,
            'serpro_usage.global_monthly_budget' => 10,
            'serpro_usage.max_tenant_share_of_global' => 0.3, // max 3
        ]);

        OfficeSubscription::query()->where('office_id', $this->office->id)->update([
            'monthly_api_quota' => 1000,
        ]);

        for ($i = 0; $i < 3; $i++) {
            $o = $this->ledger->reserve(new UsageReserveRequest(
                officeId: $this->office->id,
                idempotencyKey: "noisy-{$i}",
                systemCode: 'INTEGRA_CONTADOR',
                serviceCode: 'SITFIS',
                operationCode: 'CONSULTAR_SITUACAO',
            ));
            $this->assertTrue($o->allowed, "iter {$i}");
            $this->ledger->finalize($o->reservation, SerproUsageResult::Success);
        }

        $blocked = $this->ledger->reserve(new UsageReserveRequest(
            officeId: $this->office->id,
            idempotencyKey: 'noisy-over',
            systemCode: 'INTEGRA_CONTADOR',
            serviceCode: 'GUIAS',
            operationCode: 'EMITIR_GUIA',
        ));

        $this->assertFalse($blocked->allowed);
        $this->assertSame('NOISY_TENANT_SHARE', $blocked->reservation->block_reason);
    }

    public function test_idempotency_key_cross_office_rejeita_isolamento(): void
    {
        $other = Office::factory()->create();

        $this->ledger->reserve(new UsageReserveRequest(
            officeId: $this->office->id,
            idempotencyKey: 'shared-key-x',
            systemCode: 'INTEGRA_CONTADOR',
            serviceCode: 'SITFIS',
            operationCode: 'CONSULTAR_SITUACAO',
        ));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('outro office_id');

        $this->ledger->reserve(new UsageReserveRequest(
            officeId: $other->id,
            idempotencyKey: 'shared-key-x',
            systemCode: 'INTEGRA_CONTADOR',
            serviceCode: 'SITFIS',
            operationCode: 'CONSULTAR_SITUACAO',
        ));
    }

    public function test_franquia_nao_oversubscreve_com_reservas_abertas(): void
    {
        config([
            'serpro_usage.shadow_mode' => false,
            'serpro_usage.commercial_blocking_enabled' => true,
        ]);

        OfficeSubscription::query()->where('office_id', $this->office->id)->update([
            'monthly_api_quota' => 2,
        ]);

        $a = $this->ledger->reserve(new UsageReserveRequest(
            officeId: $this->office->id,
            idempotencyKey: 'open-a',
            systemCode: 'INTEGRA_CONTADOR',
            serviceCode: 'GUIAS',
            operationCode: 'EMITIR_GUIA',
        ));
        $b = $this->ledger->reserve(new UsageReserveRequest(
            officeId: $this->office->id,
            idempotencyKey: 'open-b',
            systemCode: 'INTEGRA_CONTADOR',
            serviceCode: 'GUIAS',
            operationCode: 'EMITIR_GUIA',
        ));
        $this->assertTrue($a->allowed);
        $this->assertTrue($b->allowed);

        $c = $this->ledger->reserve(new UsageReserveRequest(
            officeId: $this->office->id,
            idempotencyKey: 'open-c',
            systemCode: 'INTEGRA_CONTADOR',
            serviceCode: 'GUIAS',
            operationCode: 'EMITIR_GUIA',
        ));

        $this->assertFalse($c->allowed);
        $this->assertSame(SerproUsageReservationStatus::Blocked, $c->reservation->status);
    }

    public function test_release_apos_finalize_eh_noop_seguro(): void
    {
        $outcome = $this->ledger->reserve(new UsageReserveRequest(
            officeId: $this->office->id,
            idempotencyKey: 'rel-fin-1',
            systemCode: 'INTEGRA_CONTADOR',
            serviceCode: 'SITFIS',
            operationCode: 'CONSULTAR_SITUACAO',
        ));

        $entry = $this->ledger->finalize($outcome->reservation, SerproUsageResult::Success);
        $released = $this->ledger->release($outcome->reservation);

        $this->assertSame(SerproUsageReservationStatus::Finalized, $released->status);
        $this->assertSame(1, SerproApiUsageEntry::query()->withoutGlobalScopes()->count());
        $this->assertSame($entry->id, SerproApiUsageEntry::query()->withoutGlobalScopes()->first()->id);
    }

    public function test_around_mapeia_integra_response_falha_http(): void
    {
        $response = new \App\DTO\Serpro\IntegraResponse(
            success: false,
            httpStatus: 422,
            body: [],
            errorCode: 'REQUEST_FAILED',
            errorMessage: 'rejeitada',
        );

        $pack = $this->ledger->around(
            new UsageReserveRequest(
                officeId: $this->office->id,
                idempotencyKey: 'around-fail-1',
                systemCode: 'INTEGRA_CONTADOR',
                serviceCode: 'SITFIS',
                operationCode: 'CONSULTAR_SITUACAO',
            ),
            fn () => $response,
        );

        $this->assertNotNull($pack['entry']);
        $this->assertSame(SerproUsageResult::ClientError, $pack['entry']->result);
        $this->assertSame(422, $pack['entry']->http_status);
        $this->assertSame($response, $pack['result']);
        $this->assertNull($pack['error']);
    }
}
