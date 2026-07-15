<?php

namespace Tests\Feature\FiscalMonitoring;

use App\DTO\Fiscal\FiscalAdapterRequest;
use App\Enums\FiscalRunResult;
use App\Enums\FiscalRunStatus;
use App\Enums\FiscalSituation;
use App\Enums\FiscalTrigger;
use App\Enums\OfficeRole;
use App\Enums\TaxGuideEmissionStatus;
use App\Enums\TaxGuidePaymentStatus;
use App\Enums\TaxInstallmentModality;
use App\Enums\TaxInstallmentParcelStatus;
use App\Enums\TaxInstallmentPaymentStatus;
use App\Enums\TaxProxyPowerSource;
use App\Enums\TaxProxyPowerStatus;
use App\Models\Client;
use App\Models\FiscalMonitoringRun;
use App\Models\Office;
use App\Models\TaxGuide;
use App\Models\TaxInstallmentOrder;
use App\Models\TaxInstallmentParcel;
use App\Models\TaxProxyPower;
use App\Models\User;
use App\Services\FiscalMonitoring\FiscalAdapterRegistry;
use App\Services\FiscalMonitoring\FiscalMonitoringRunService;
use App\Services\Integra\Parcelamento\FakeParcelamentoSource;
use App\Services\Integra\Parcelamento\ParcelamentoMutatingAdapter;
use App\Services\Integra\Parcelamento\ParcelamentoServiceCatalog;
use App\Services\Integra\Parcelamento\StubTaxGuideEnrollment;
use App\Support\CurrentOffice;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tasks 9.5–9.9 (parcelamentos): modalidades, projeção, guias, mutantes OFF, timeout.
 */
class TaxInstallmentMonitoringTest extends TestCase
{
    use RefreshDatabase;

    private Office $office;

    private Client $client;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        FakeParcelamentoSource::reset();
        ParcelamentoMutatingAdapter::$remoteCalls = 0;

        config([
            'features.global_enabled' => true,
            'features.kill_switch' => false,
            'features.mutating.enabled' => false,
            'features.modules.parcelamentos.enabled' => true,
            'features.modules.parcelamentos.allow_all_offices' => true,
            'features.modules.parcelamentos.mutating_enabled' => false,
            'fiscal_monitoring.enabled' => true,
            'fiscal_monitoring.kill_switch' => false,
            'fiscal_monitoring.mutating_enabled' => false,
        ]);

        $this->office = Office::factory()->create();
        $this->client = Client::factory()->forOffice($this->office)->create();
        $this->admin = User::factory()->forOffice($this->office, OfficeRole::Admin)->withTwoFactorConfirmed()->create();
    }

    public function test_modalidades_catalogadas_nao_fundem_pedidos(): void
    {
        $svc = app(FiscalMonitoringRunService::class);

        $runSn = $svc->enqueueManual(
            $this->office,
            $this->client,
            ParcelamentoServiceCatalog::SOLUTION,
            TaxInstallmentModality::Parcsn->value,
            'MONITOR',
            dispatch: false,
        );
        $runMei = $svc->enqueueManual(
            $this->office,
            $this->client,
            ParcelamentoServiceCatalog::SOLUTION,
            TaxInstallmentModality::Parcmei->value,
            'MONITOR',
            dispatch: false,
        );

        $svc->execute($runSn->id);
        $svc->execute($runMei->id);

        $orders = TaxInstallmentOrder::query()
            ->withoutGlobalScopes()
            ->where('office_id', $this->office->id)
            ->where('client_id', $this->client->id)
            ->get();

        $this->assertCount(2, $orders);
        $modalities = $orders->pluck('modality')->map(fn ($m) => $m instanceof TaxInstallmentModality ? $m->value : $m)->all();
        $this->assertContains(TaxInstallmentModality::Parcsn->value, $modalities);
        $this->assertContains(TaxInstallmentModality::Parcmei->value, $modalities);

        $extIds = $orders->pluck('external_order_id')->all();
        $this->assertCount(2, array_unique($extIds), 'IDs externos de modalidades distintas não devem colidir');

        // Parcelas não misturam pedidos
        foreach ($orders as $order) {
            $parcels = TaxInstallmentParcel::query()
                ->withoutGlobalScopes()
                ->where('office_id', $this->office->id)
                ->where('order_id', $order->id)
                ->get();
            $this->assertNotEmpty($parcels);
            foreach ($parcels as $p) {
                $mod = $p->modality instanceof TaxInstallmentModality ? $p->modality->value : $p->modality;
                $orderMod = $order->modality instanceof TaxInstallmentModality ? $order->modality->value : $order->modality;
                $this->assertSame($orderMod, $mod);
            }
        }
    }

    public function test_parcela_vencida_sem_pagamento_e_attention_nao_inadimplente(): void
    {
        $svc = app(FiscalMonitoringRunService::class);
        $run = $svc->enqueueManual(
            $this->office,
            $this->client,
            ParcelamentoServiceCatalog::SOLUTION,
            TaxInstallmentModality::Parcsn->value,
            'MONITOR',
            dispatch: false,
        );
        $result = $svc->execute($run->id);

        $this->assertSame(FiscalRunStatus::Completed, $result->status);

        $overdue = TaxInstallmentParcel::query()
            ->withoutGlobalScopes()
            ->where('office_id', $this->office->id)
            ->where('status', TaxInstallmentParcelStatus::Attention->value)
            ->get();

        $this->assertNotEmpty($overdue, 'Deve existir parcela vencida sem confirmação → ATTENTION');
        foreach ($overdue as $p) {
            $this->assertNotSame('INADIMPLENTE', $p->source_status);
            $this->assertSame(TaxInstallmentPaymentStatus::None, $p->payment_status);
            $this->assertTrue((bool) ($p->metadata['overdue_without_payment_confirmation'] ?? false));
        }

        $paid = TaxInstallmentParcel::query()
            ->withoutGlobalScopes()
            ->where('office_id', $this->office->id)
            ->where('status', TaxInstallmentParcelStatus::Paid->value)
            ->get();
        $this->assertNotEmpty($paid, 'Parcela com confirmação da fonte deve ficar PAID');
    }

    public function test_poder_por_modalidade_sn_nao_libera_mei(): void
    {
        TaxProxyPower::query()->create([
            'office_id' => $this->office->id,
            'client_id' => $this->client->id,
            'author_identity' => '12345678901',
            'contributor_cnpj' => '11222333000181',
            'system_code' => ParcelamentoServiceCatalog::SOLUTION,
            'service_code' => TaxInstallmentModality::Parcsn->value,
            'power_code' => TaxInstallmentModality::Parcsn->value,
            'source' => TaxProxyPowerSource::ManualOfficialEvidence,
            'status' => TaxProxyPowerStatus::Active,
            'valid_from' => now()->subDay(),
            'valid_to' => now()->addYear(),
        ]);

        $registry = app(FiscalAdapterRegistry::class);
        $run = FiscalMonitoringRun::query()->create([
            'office_id' => $this->office->id,
            'client_id' => $this->client->id,
            'system_code' => ParcelamentoServiceCatalog::SOLUTION,
            'service_code' => TaxInstallmentModality::Parcmei->value,
            'operation_code' => 'MONITOR',
            'trigger' => FiscalTrigger::Manual,
            'idempotency_key' => 'test-mei-power-'.uniqid(),
            'status' => FiscalRunStatus::Running,
            'situation' => FiscalSituation::Processing,
            'coverage' => 'FULL',
            'mutability' => 'READ_ONLY',
            'correlation_id' => 'corr-mei-power',
        ]);

        $request = new FiscalAdapterRequest(
            office: $this->office,
            client: $this->client,
            run: $run,
            systemCode: ParcelamentoServiceCatalog::SOLUTION,
            serviceCode: TaxInstallmentModality::Parcmei->value,
            operationCode: 'MONITOR',
            trigger: FiscalTrigger::Manual,
            context: [
                'author_identity' => '12345678901',
                'require_proxy_power' => true,
            ],
        );

        $adapter = $registry->resolve($request);
        $result = $adapter->execute($request);

        $this->assertSame(FiscalRunResult::Blocked, $result->result);
        $this->assertSame('PROXY_POWER_MISSING', $result->errorCode);

        // SN com o mesmo poder deve passar
        $runSn = FiscalMonitoringRun::query()->create([
            'office_id' => $this->office->id,
            'client_id' => $this->client->id,
            'system_code' => ParcelamentoServiceCatalog::SOLUTION,
            'service_code' => TaxInstallmentModality::Parcsn->value,
            'operation_code' => 'MONITOR',
            'trigger' => FiscalTrigger::Manual,
            'idempotency_key' => 'test-sn-power-'.uniqid(),
            'status' => FiscalRunStatus::Running,
            'situation' => FiscalSituation::Processing,
            'coverage' => 'FULL',
            'mutability' => 'READ_ONLY',
            'correlation_id' => 'corr-sn-power',
        ]);

        $reqSn = new FiscalAdapterRequest(
            office: $this->office,
            client: $this->client,
            run: $runSn,
            systemCode: ParcelamentoServiceCatalog::SOLUTION,
            serviceCode: TaxInstallmentModality::Parcsn->value,
            operationCode: 'MONITOR',
            trigger: FiscalTrigger::Manual,
            context: [
                'author_identity' => '12345678901',
                'require_proxy_power' => true,
            ],
        );
        $ok = $registry->resolve($reqSn)->execute($reqSn);
        $this->assertSame(FiscalRunResult::Success, $ok->result);
    }

    public function test_emissao_guia_repetida_reutiliza_artefato_sem_marcar_pagamento(): void
    {
        $svc = app(FiscalMonitoringRunService::class);

        // Primeiro monitor para ter pedido/parcela
        $mon = $svc->enqueueManual(
            $this->office,
            $this->client,
            ParcelamentoServiceCatalog::SOLUTION,
            TaxInstallmentModality::Parcmei->value,
            'MONITOR',
            dispatch: false,
        );
        $svc->execute($mon->id);

        $parcelKey = CarbonImmutable::now()->format('Ym');
        $order = TaxInstallmentOrder::query()
            ->withoutGlobalScopes()
            ->where('office_id', $this->office->id)
            ->where('modality', TaxInstallmentModality::Parcmei->value)
            ->firstOrFail();

        $run1 = $svc->enqueueManual(
            $this->office,
            $this->client,
            ParcelamentoServiceCatalog::SOLUTION,
            TaxInstallmentModality::Parcmei->value,
            'EMITIR_DOCUMENTO',
            correlationId: 'emit-1-'.uniqid(),
            dispatch: false,
        );
        // context via progress/context não é passado no enqueue — executamos adapter direto com context
        $run1->forceFill(['status' => FiscalRunStatus::Running])->save();

        $registry = app(FiscalAdapterRegistry::class);
        $req = new FiscalAdapterRequest(
            office: $this->office,
            client: $this->client,
            run: $run1,
            systemCode: ParcelamentoServiceCatalog::SOLUTION,
            serviceCode: TaxInstallmentModality::Parcmei->value,
            operationCode: 'EMITIR_DOCUMENTO',
            trigger: FiscalTrigger::Manual,
            context: [
                'parcel_key' => $parcelKey,
                'order_external_id' => $order->external_order_id,
            ],
        );
        $r1 = $registry->resolve($req)->execute($req);
        $this->assertSame(FiscalRunResult::Success, $r1->result);
        $this->assertFalse((bool) ($r1->normalized['reused'] ?? true));

        $guideId = $r1->normalized['guide']['id'] ?? null;
        $this->assertNotNull($guideId);

        $guide = TaxGuide::query()->withoutGlobalScopes()->findOrFail($guideId);
        $this->assertNotSame(TaxGuidePaymentStatus::Confirmed, $guide->payment_status);
        $this->assertContains(
            $guide->payment_status,
            [TaxGuidePaymentStatus::Unknown, TaxGuidePaymentStatus::NotConfirmed],
        );

        // Segunda emissão — reutiliza
        $run2 = FiscalMonitoringRun::query()->create([
            'office_id' => $this->office->id,
            'client_id' => $this->client->id,
            'system_code' => ParcelamentoServiceCatalog::SOLUTION,
            'service_code' => TaxInstallmentModality::Parcmei->value,
            'operation_code' => 'EMITIR_DOCUMENTO',
            'trigger' => FiscalTrigger::Manual,
            'idempotency_key' => 'emit-2-'.uniqid(),
            'status' => FiscalRunStatus::Running,
            'situation' => FiscalSituation::Processing,
            'coverage' => 'FULL',
            'mutability' => 'READ_ONLY',
            'correlation_id' => 'emit-2',
        ]);

        $req2 = new FiscalAdapterRequest(
            office: $this->office,
            client: $this->client,
            run: $run2,
            systemCode: ParcelamentoServiceCatalog::SOLUTION,
            serviceCode: TaxInstallmentModality::Parcmei->value,
            operationCode: 'EMITIR_DOCUMENTO',
            trigger: FiscalTrigger::Manual,
            context: [
                'parcel_key' => $parcelKey,
                'order_external_id' => $order->external_order_id,
            ],
        );
        $r2 = $registry->resolve($req2)->execute($req2);
        $this->assertSame(FiscalRunResult::Success, $r2->result);
        $this->assertTrue((bool) ($r2->normalized['reused'] ?? false));
        $this->assertSame($guideId, $r2->normalized['guide']['id'] ?? null);

        $this->assertSame(
            1,
            TaxGuide::query()->withoutGlobalScopes()->where('office_id', $this->office->id)->count(),
        );
    }

    public function test_adesao_reparcelamento_desistencia_off_sem_chamada_remota(): void
    {
        $registry = app(FiscalAdapterRegistry::class);
        $before = FakeParcelamentoSource::$calls;
        $remoteBefore = ParcelamentoMutatingAdapter::$remoteCalls;

        foreach (['ADERIR', 'REPARCELAR', 'DESISTIR'] as $op) {
            $run = FiscalMonitoringRun::query()->create([
                'office_id' => $this->office->id,
                'client_id' => $this->client->id,
                'system_code' => ParcelamentoServiceCatalog::SOLUTION,
                'service_code' => TaxInstallmentModality::Parcsn->value,
                'operation_code' => $op,
                'trigger' => FiscalTrigger::Manual,
                'idempotency_key' => 'mut-'.$op.'-'.uniqid(),
                'status' => FiscalRunStatus::Running,
                'situation' => FiscalSituation::Processing,
                'coverage' => 'FULL',
                'mutability' => 'MUTATING',
                'correlation_id' => 'mut-'.$op,
            ]);

            $req = new FiscalAdapterRequest(
                office: $this->office,
                client: $this->client,
                run: $run,
                systemCode: ParcelamentoServiceCatalog::SOLUTION,
                serviceCode: TaxInstallmentModality::Parcsn->value,
                operationCode: $op,
                trigger: FiscalTrigger::Manual,
            );

            $result = $registry->resolve($req)->execute($req);
            $this->assertSame(FiscalRunResult::Blocked, $result->result, $op);
            $this->assertSame('MUTATING_DISABLED', $result->errorCode, $op);
            $this->assertFalse((bool) ($result->normalized['remote_called'] ?? true), $op);
        }

        $this->assertSame($before, FakeParcelamentoSource::$calls);
        $this->assertSame($remoteBefore, ParcelamentoMutatingAdapter::$remoteCalls);

        // API também recusa
        $this->actingAs($this->admin);
        app(CurrentOffice::class)->resolve($this->admin);

        $this->postJson('/api/v1/fiscal/installments/runs', [
            'client_id' => $this->client->id,
            'modality' => TaxInstallmentModality::Parcsn->value,
            'operation_code' => 'ADERIR',
        ])->assertForbidden()
            ->assertJsonPath('code', 'MUTATING_DISABLED');
    }

    public function test_timeout_apos_envio_marca_resultado_incerto(): void
    {
        FakeParcelamentoSource::$forceTimeoutAfterSend = true;

        $run = FiscalMonitoringRun::query()->create([
            'office_id' => $this->office->id,
            'client_id' => $this->client->id,
            'system_code' => ParcelamentoServiceCatalog::SOLUTION,
            'service_code' => TaxInstallmentModality::Parcsn->value,
            'operation_code' => 'EMITIR_DOCUMENTO',
            'trigger' => FiscalTrigger::Manual,
            'idempotency_key' => 'timeout-'.uniqid(),
            'status' => FiscalRunStatus::Running,
            'situation' => FiscalSituation::Processing,
            'coverage' => 'FULL',
            'mutability' => 'READ_ONLY',
            'correlation_id' => 'timeout-1',
        ]);

        $req = new FiscalAdapterRequest(
            office: $this->office,
            client: $this->client,
            run: $run,
            systemCode: ParcelamentoServiceCatalog::SOLUTION,
            serviceCode: TaxInstallmentModality::Parcsn->value,
            operationCode: 'EMITIR_DOCUMENTO',
            trigger: FiscalTrigger::Manual,
            context: [
                'parcel_key' => CarbonImmutable::now()->format('Ym'),
                'order_external_id' => 'PARCSN-PED-1001',
            ],
        );

        $result = app(FiscalAdapterRegistry::class)->resolve($req)->execute($req);

        $this->assertSame(FiscalRunResult::Failed, $result->result);
        $this->assertSame(FiscalSituation::Unknown, $result->situation);
        $this->assertSame('TIMEOUT_AFTER_SEND', $result->errorCode);
        $this->assertTrue((bool) ($result->normalized['uncertain'] ?? false));
        $this->assertTrue((bool) ($result->normalized['retry_blocked'] ?? false));
        $this->assertSame(
            TaxGuideEmissionStatus::UnknownResult->value,
            $result->normalized['emission_status'] ?? null,
        );
    }

    public function test_api_lista_modalidades_e_pedidos_tenant_scoped(): void
    {
        $this->actingAs($this->admin);
        app(CurrentOffice::class)->resolve($this->admin);

        $this->getJson('/api/v1/fiscal/installments/modalities')
            ->assertOk()
            ->assertJsonCount(8, 'data');

        app(FiscalMonitoringRunService::class)->execute(
            app(FiscalMonitoringRunService::class)->enqueueManual(
                $this->office,
                $this->client,
                ParcelamentoServiceCatalog::SOLUTION,
                TaxInstallmentModality::Parcsn->value,
                'MONITOR',
                dispatch: false,
            )->id,
        );

        $this->getJson('/api/v1/fiscal/installments/orders?client_id='.$this->client->id)
            ->assertOk()
            ->assertJsonPath('data.0.modality', TaxInstallmentModality::Parcsn->value);

        $other = Office::factory()->create();
        $otherAdmin = User::factory()->forOffice($other, OfficeRole::Admin)->withTwoFactorConfirmed()->create();
        $this->actingAs($otherAdmin);
        app(CurrentOffice::class)->clear();
        app(CurrentOffice::class)->resolve($otherAdmin);

        $this->getJson('/api/v1/fiscal/installments/orders')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_enrollment_idempotency_key_estavel(): void
    {
        $enroll = app(StubTaxGuideEnrollment::class);
        $a = $enroll->idempotencyKey('PARCMEI', 'X-1', '202601');
        $b = $enroll->idempotencyKey('PARCMEI', 'X-1', '202601');
        $c = $enroll->idempotencyKey('PARCSN', 'X-1', '202601');

        $this->assertSame($a, $b);
        $this->assertNotSame($a, $c);
    }
}
