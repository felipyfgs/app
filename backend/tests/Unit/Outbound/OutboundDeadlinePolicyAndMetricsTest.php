<?php

namespace Tests\Unit\Outbound;

use App\Domain\Outbound\Competence;
use App\Enums\OutboundCaptureMode;
use App\Enums\OutboundFiscalModel;
use App\Enums\OutboundProfileStatus;
use App\Enums\OutboundRetrievalOrigin;
use App\Enums\OutboundUrgencyBand;
use App\Enums\SvrsNfceRecoveryStatus;
use App\Jobs\RecoverSvrsNfceXmlJob;
use App\Models\Client;
use App\Models\Establishment;
use App\Models\MaOutboundRetrievalRequest;
use App\Models\Office;
use App\Models\OutboundCaptureProfile;
use App\Models\OutboundCapacitySnapshot;
use App\Services\Outbound\OutboundDeadlineCalculator;
use App\Services\Outbound\OutboundDeadlineFairQueue;
use App\Services\Outbound\OutboundDeadlinePlannerService;
use App\Services\Outbound\OutboundDeadlineSatisfactionService;
use App\Services\Outbound\OutboundMetrics;
use App\Services\Outbound\OutboundXmlCaptureCapacityPlanner;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * 13.4–13.8, 13.3 — relógio falso, 60%, 2 tentativas, urgência≠governor, cancelamento, mascaramento.
 */
class OutboundDeadlinePolicyAndMetricsTest extends TestCase
{
    use RefreshDatabase;

    public function test_faixas_com_relogio_falso_virada_mes_e_fevereiro(): void
    {
        $calc = new OutboundDeadlineCalculator;
        // Fevereiro não-bissexto
        $plan = $calc->planFromAuthorizationDate(
            CarbonImmutable::parse('2026-02-28 15:00:00', 'America/Sao_Paulo'),
            null,
            CarbonImmutable::parse('2026-03-01 00:00:00', 'UTC'),
        );
        $this->assertSame('2026-02', $plan->competence->value());
        $this->assertSame('2026-03-02T02:59:59+00:00', $plan->dueAt->toIso8601String());

        // Virada de ano
        $plan2 = $calc->planFromAuthorizationDate(
            CarbonImmutable::parse('2026-12-31 10:00:00', 'America/Sao_Paulo'),
            null,
            CarbonImmutable::parse('2026-12-31 12:00:00', 'UTC'),
        );
        $this->assertSame('2026-12', $plan2->competence->value());
        $this->assertSame('2027-01-02T02:59:59+00:00', $plan2->dueAt->toIso8601String());
    }

    public function test_capacidade_60_porcento_sem_burst_e_determinismo_spread(): void
    {
        config([
            'outbound_deadline.auto_queue_capacity_fraction' => 0.60,
            'sefaz.svrs_portal_egress.max_exchanges_per_day' => 50,
            'sefaz.svrs_portal_egress.exchanges_per_download' => 2,
        ]);
        $gov = \Mockery::mock(\App\Contracts\SvrsPortalEgressGovernor::class);
        $gov->shouldReceive('isCallAllowed')->andReturn(true);
        $gov->shouldReceive('cohortHealth')->andReturn([
            'state' => 'closed',
            'exchanges_day' => 0,
            'exchanges_day_remaining' => 50,
        ]);
        $planner = new OutboundXmlCaptureCapacityPlanner($gov, app(\App\Services\Outbound\SvrsPortalEgressConfig::class));
        $comp = Competence::fromString('2026-07');
        $now = CarbonImmutable::parse('2026-07-01 12:00:00', 'UTC');
        $target = CarbonImmutable::parse('2026-07-31 02:59:59', 'UTC');

        $a = $planner->project($comp, 10, 0, $now, $target, null);
        $b = $planner->project($comp, 10, 0, $now, $target, null);

        $this->assertSame(30, $a['safe_daily_exchanges']);
        $this->assertSame($a['safe_capacity_exchanges'], $b['safe_capacity_exchanges']);
        $this->assertSame($a['demand_exchanges'], $b['demand_exchanges']);
        $this->assertSame(20, $a['demand_exchanges']); // 10 * 2

        $fq = app(OutboundDeadlineFairQueue::class);
        $s1 = $fq->spreadSeconds('1|KEY|0', 3600);
        $s2 = $fq->spreadSeconds('1|KEY|0', 3600);
        $this->assertSame($s1, $s2);
        $this->assertGreaterThanOrEqual(0, $s1);
        $this->assertLessThan(3600, $s1);
    }

    public function test_maximo_duas_tentativas_e_intervalo_24h(): void
    {
        config([
            'outbound_deadline.enabled' => true,
            'outbound_deadline.planner_enabled' => true,
            'outbound_deadline.max_svrs_transactions_per_key' => 2,
            'outbound_deadline.min_hours_between_svrs_attempts' => 24,
            'outbound_deadline.accommodation_hours' => 0,
        ]);
        [$office, $profile, $est] = $this->seedOffice();
        $key = '35260799888777000166550010000000011234567901';
        $req = MaOutboundRetrievalRequest::query()->create([
            'office_id' => $office->id,
            'outbound_capture_profile_id' => $profile->id,
            'establishment_id' => $est->id,
            'environment' => 'homologation',
            'model' => OutboundFiscalModel::Nfe,
            'direction' => 'OUT',
            'competence' => '2026-07',
            'status' => 'PENDING',
            'mode' => OutboundCaptureMode::Automatic,
            'origin' => OutboundRetrievalOrigin::SvrsPortalByKey,
            'access_key' => $key,
            'recovery_status' => SvrsNfceRecoveryStatus::RetryScheduled,
            'urgency_band' => OutboundUrgencyBand::Planned,
            'svrs_transaction_count' => 2,
            'updated_at' => now()->subHours(48),
            'created_at' => now()->subDays(3),
        ]);

        $now = CarbonImmutable::parse('2026-07-15 12:00:00', 'UTC');
        app(OutboundDeadlinePlannerService::class)->plan($office->id, $now);
        $req->refresh();
        // Com 2 tx já consumidas, não agenda nova
        $this->assertNull($req->next_attempt_at);

        $req2 = MaOutboundRetrievalRequest::query()->create([
            'office_id' => $office->id,
            'outbound_capture_profile_id' => $profile->id,
            'establishment_id' => $est->id,
            'environment' => 'homologation',
            'model' => OutboundFiscalModel::Nfe,
            'direction' => 'OUT',
            'competence' => '2026-07',
            'status' => 'PENDING',
            'mode' => OutboundCaptureMode::Automatic,
            'origin' => OutboundRetrievalOrigin::SvrsPortalByKey,
            'access_key' => '35260799888777000166550010000000011234567902',
            'recovery_status' => SvrsNfceRecoveryStatus::Eligible,
            'urgency_band' => OutboundUrgencyBand::Planned,
            'svrs_transaction_count' => 1,
            'updated_at' => now()->subHours(2), // < 24h
            'created_at' => now()->subDays(3),
        ]);
        app(OutboundDeadlinePlannerService::class)->plan($office->id, $now);
        $req2->refresh();
        $this->assertNull($req2->next_attempt_at);
    }

    public function test_urgencia_nao_altera_budget_governor(): void
    {
        // Marcar OVERDUE e capacity_at_risk não deve mutar budgets de config nem abrir breaker
        $fractionBefore = (float) config('outbound_deadline.auto_queue_capacity_fraction');
        $nominalBefore = (int) config('outbound_deadline.nominal_daily_exchanges');
        $maxDay = (int) config('sefaz.svrs_portal_egress.max_exchanges_per_day', 50);
        $maxInf = (int) config('sefaz.svrs_portal_egress.max_inflight_transactions', 4);

        [$office, $profile, $est] = $this->seedOffice();
        MaOutboundRetrievalRequest::query()->create([
            'office_id' => $office->id,
            'outbound_capture_profile_id' => $profile->id,
            'establishment_id' => $est->id,
            'environment' => 'homologation',
            'model' => OutboundFiscalModel::Nfe,
            'direction' => 'OUT',
            'competence' => '2026-07',
            'status' => 'PENDING',
            'mode' => OutboundCaptureMode::Automatic,
            'origin' => OutboundRetrievalOrigin::SvrsPortalByKey,
            'access_key' => '35260799888777000166550010000000011234567903',
            'recovery_status' => SvrsNfceRecoveryStatus::Eligible,
            'urgency_band' => OutboundUrgencyBand::Overdue,
            'capacity_at_risk' => true,
            'created_at' => now()->subDays(2),
        ]);

        config(['outbound_deadline.enabled' => true, 'outbound_deadline.planner_enabled' => true]);
        app(OutboundDeadlinePlannerService::class)->plan($office->id, CarbonImmutable::now('UTC'));

        $this->assertSame($fractionBefore, (float) config('outbound_deadline.auto_queue_capacity_fraction'));
        $this->assertSame($nominalBefore, (int) config('outbound_deadline.nominal_daily_exchanges'));
        $this->assertSame($maxDay, (int) config('sefaz.svrs_portal_egress.max_exchanges_per_day', 50));
        $this->assertSame($maxInf, (int) config('sefaz.svrs_portal_egress.max_inflight_transactions', 4));
    }

    public function test_cancelamento_por_upload_enquanto_slot_aguarda(): void
    {
        [$office, $profile, $est] = $this->seedOffice();
        $key = '35260799888777000166550010000000011234567904';
        $req = MaOutboundRetrievalRequest::query()->create([
            'office_id' => $office->id,
            'outbound_capture_profile_id' => $profile->id,
            'establishment_id' => $est->id,
            'environment' => 'homologation',
            'model' => OutboundFiscalModel::Nfe,
            'direction' => 'OUT',
            'competence' => '2026-07',
            'status' => 'PENDING',
            'mode' => OutboundCaptureMode::Automatic,
            'origin' => OutboundRetrievalOrigin::SvrsPortalByKey,
            'access_key' => $key,
            'recovery_status' => SvrsNfceRecoveryStatus::RetryScheduled,
            'urgency_band' => OutboundUrgencyBand::Attention,
            'next_attempt_at' => now()->addHours(6),
            'svrs_transaction_count' => 0,
        ]);

        app(OutboundDeadlineSatisfactionService::class)->markCapturedBySource(
            $office->id,
            $key,
            'MANUAL_ZIP',
            hash('sha256', 'zip'),
        );

        $req->refresh();
        $this->assertSame(SvrsNfceRecoveryStatus::ResolvedByOtherSource, $req->recovery_status);
        $this->assertNull($req->next_attempt_at);
        $this->assertSame(OutboundUrgencyBand::Captured, $req->urgency_band);
    }

    public function test_metricas_baixa_cardinalidade_sem_chave(): void
    {
        Log::spy();
        [$office, $profile, $est] = $this->seedOffice();
        MaOutboundRetrievalRequest::query()->create([
            'office_id' => $office->id,
            'outbound_capture_profile_id' => $profile->id,
            'establishment_id' => $est->id,
            'environment' => 'homologation',
            'model' => OutboundFiscalModel::Nfe,
            'direction' => 'OUT',
            'competence' => '2026-07',
            'status' => 'PENDING',
            'mode' => OutboundCaptureMode::Automatic,
            'origin' => OutboundRetrievalOrigin::SvrsPortalByKey,
            'access_key' => '35260799888777000166550010000000011234567905',
            'recovery_status' => SvrsNfceRecoveryStatus::Eligible,
            'urgency_band' => OutboundUrgencyBand::Overdue,
        ]);

        $snap = app(OutboundMetrics::class)->deadlineSnapshot($office->id, '2026-07');
        $this->assertSame(1, $snap['overdue']);
        $this->assertNotEmpty($snap['alerts']);
        $this->assertSame('known_documents_only', $snap['completeness_scope']);

        app(OutboundMetrics::class)->increment('outbound.deadline.test', 1, [
            'channel' => 'deadline',
            'access_key' => 'SHOULD_NOT_APPEAR',
            'cnpj' => '123',
        ]);

        Log::shouldHaveReceived('info')->withArgs(function ($msg, $ctx = null) {
            if ($msg !== 'metrics.counter') {
                return true;
            }
            $encoded = json_encode($ctx);

            return ! str_contains($encoded, 'SHOULD_NOT_APPEAR') && ! str_contains($encoded, '"cnpj"');
        })->atLeast()->once();
    }

    public function test_isolamento_metricas_por_office(): void
    {
        [$officeA, $profileA, $estA] = $this->seedOffice();
        [$officeB, $profileB, $estB] = $this->seedOffice();
        MaOutboundRetrievalRequest::query()->create([
            'office_id' => $officeA->id,
            'outbound_capture_profile_id' => $profileA->id,
            'establishment_id' => $estA->id,
            'environment' => 'homologation',
            'model' => OutboundFiscalModel::Nfe,
            'direction' => 'OUT',
            'competence' => '2026-07',
            'status' => 'PENDING',
            'mode' => OutboundCaptureMode::Automatic,
            'origin' => OutboundRetrievalOrigin::SvrsPortalByKey,
            'access_key' => '35260711222333000181550010000000011234567906',
            'recovery_status' => SvrsNfceRecoveryStatus::Eligible,
            'urgency_band' => OutboundUrgencyBand::Overdue,
        ]);
        MaOutboundRetrievalRequest::query()->create([
            'office_id' => $officeB->id,
            'outbound_capture_profile_id' => $profileB->id,
            'establishment_id' => $estB->id,
            'environment' => 'homologation',
            'model' => OutboundFiscalModel::Nfe,
            'direction' => 'OUT',
            'competence' => '2026-07',
            'status' => 'PENDING',
            'mode' => OutboundCaptureMode::Automatic,
            'origin' => OutboundRetrievalOrigin::SvrsPortalByKey,
            'access_key' => '35260799888777000166550010000000011234567907',
            'recovery_status' => SvrsNfceRecoveryStatus::Eligible,
            'urgency_band' => OutboundUrgencyBand::Planned,
        ]);

        $a = app(OutboundMetrics::class)->deadlineSnapshot($officeA->id, '2026-07');
        $b = app(OutboundMetrics::class)->deadlineSnapshot($officeB->id, '2026-07');
        $this->assertSame(1, $a['known_total']);
        $this->assertSame(1, $a['overdue']);
        $this->assertSame(1, $b['known_total']);
        $this->assertSame(0, $b['overdue']);
    }

    public function test_planner_global_snapshot_e_capacity_at_risk_por_office(): void
    {
        config([
            'outbound_deadline.enabled' => true,
            'outbound_deadline.planner_enabled' => true,
            'outbound_deadline.accommodation_hours' => 0,
            // safe/dia = floor(10*0.6)=6; janela 1d → B (2 exch) ok; A (42 exch) at_risk.
            'outbound_deadline.auto_queue_capacity_fraction' => 0.60,
            'sefaz.svrs_portal_egress.max_exchanges_per_day' => 10,
            'sefaz.svrs_portal_egress.exchanges_per_download' => 2,
        ]);

        [$officeA, $profileA, $estA] = $this->seedOffice();
        [$officeB, $profileB, $estB] = $this->seedOffice();

        // Office A: muitas 1ªs tentativas + uma 2ª (mesmo competência) → overflow de capacidade
        for ($i = 0; $i < 20; $i++) {
            $suffix = str_pad((string) (30 + $i), 2, '0', STR_PAD_LEFT);
            MaOutboundRetrievalRequest::query()->create([
                'office_id' => $officeA->id,
                'outbound_capture_profile_id' => $profileA->id,
                'establishment_id' => $estA->id,
                'environment' => 'homologation',
                'model' => OutboundFiscalModel::Nfe,
                'direction' => 'OUT',
                'competence' => '2026-07',
                'status' => 'PENDING',
                'mode' => OutboundCaptureMode::Automatic,
                'origin' => OutboundRetrievalOrigin::SvrsPortalByKey,
                'access_key' => '352607112223330001815500100000000112345679'.$suffix,
                'recovery_status' => SvrsNfceRecoveryStatus::Eligible,
                'urgency_band' => OutboundUrgencyBand::Planned,
                'svrs_transaction_count' => 0,
                'created_at' => now()->subDays(3),
            ]);
        }
        $reqASecond = MaOutboundRetrievalRequest::query()->create([
            'office_id' => $officeA->id,
            'outbound_capture_profile_id' => $profileA->id,
            'establishment_id' => $estA->id,
            'environment' => 'homologation',
            'model' => OutboundFiscalModel::Nfe,
            'direction' => 'OUT',
            'competence' => '2026-07',
            'status' => 'PENDING',
            'mode' => OutboundCaptureMode::Automatic,
            'origin' => OutboundRetrievalOrigin::SvrsPortalByKey,
            'access_key' => '35260711222333000181550010000000011234567950',
            'recovery_status' => SvrsNfceRecoveryStatus::RetryScheduled,
            'urgency_band' => OutboundUrgencyBand::Planned,
            'svrs_transaction_count' => 1,
            'capacity_at_risk' => false,
            'updated_at' => now()->subHours(48),
            'created_at' => now()->subDays(3),
        ]);

        // Office B: mesma competência, só 1 item 2ª tentativa (demanda baixa) —
        // não pode herdar contagens/office_id de A nem ser marcado por projeção alheia.
        $reqBSecond = MaOutboundRetrievalRequest::query()->create([
            'office_id' => $officeB->id,
            'outbound_capture_profile_id' => $profileB->id,
            'establishment_id' => $estB->id,
            'environment' => 'homologation',
            'model' => OutboundFiscalModel::Nfe,
            'direction' => 'OUT',
            'competence' => '2026-07',
            'status' => 'PENDING',
            'mode' => OutboundCaptureMode::Automatic,
            'origin' => OutboundRetrievalOrigin::SvrsPortalByKey,
            'access_key' => '35260799888777000166550010000000011234567951',
            'recovery_status' => SvrsNfceRecoveryStatus::RetryScheduled,
            'urgency_band' => OutboundUrgencyBand::Planned,
            'svrs_transaction_count' => 1,
            'capacity_at_risk' => false,
            'updated_at' => now()->subHours(48),
            'created_at' => now()->subDays(3),
        ]);

        // Janela curta (após target): days_window=1 → safe ~1; A com 21 itens estoura, B com 1 não.
        $now = CarbonImmutable::parse('2026-08-05 12:00:00', 'UTC');
        $result = app(OutboundDeadlinePlannerService::class)->plan(null, $now);

        $this->assertSame(22, $result['planned']);
        $this->assertSame(2, $result['snapshots']);

        $snapA = OutboundCapacitySnapshot::query()
            ->where('office_id', $officeA->id)
            ->where('competence', '2026-07')
            ->first();
        $snapB = OutboundCapacitySnapshot::query()
            ->where('office_id', $officeB->id)
            ->where('competence', '2026-07')
            ->first();

        $this->assertNotNull($snapA);
        $this->assertNotNull($snapB);
        $this->assertSame($officeA->id, (int) $snapA->office_id);
        $this->assertSame($officeB->id, (int) $snapB->office_id);
        $this->assertSame(21, (int) $snapA->items_total);
        $this->assertSame(1, (int) $snapB->items_total);
        // Contagem misturada (22 em um snapshot / um único office residual) = bug antigo
        $this->assertNotSame(22, (int) $snapA->items_total);
        $this->assertNotSame(22, (int) $snapB->items_total);

        $this->assertTrue((bool) $snapA->at_risk);
        $this->assertFalse((bool) $snapB->at_risk);

        $reqASecond->refresh();
        $reqBSecond->refresh();
        // Mark-at-risk escopado por office: A (em risco) marca 2ª tentativa; B (sem risco) intacto
        $this->assertTrue((bool) $reqASecond->capacity_at_risk);
        $this->assertFalse((bool) $reqBSecond->capacity_at_risk);
        $this->assertSame(
            0,
            MaOutboundRetrievalRequest::query()
                ->where('office_id', $officeB->id)
                ->where('capacity_at_risk', true)
                ->count(),
        );
    }

    public function test_dispatch_com_office_nao_enfileira_outros_escritorios(): void
    {
        config([
            'outbound_deadline.enabled' => true,
            'outbound_deadline.planner_enabled' => true,
            'outbound_deadline.dispatch_enabled' => true,
            'outbound_deadline.shadow_mode' => false,
            'outbound_deadline.accommodation_hours' => 0,
        ]);

        [$officeA, $profileA, $estA] = $this->seedOffice();
        [$officeB, $profileB, $estB] = $this->seedOffice();

        $reqA = MaOutboundRetrievalRequest::query()->create([
            'office_id' => $officeA->id,
            'outbound_capture_profile_id' => $profileA->id,
            'establishment_id' => $estA->id,
            'environment' => 'homologation',
            'model' => OutboundFiscalModel::Nfe,
            'direction' => 'OUT',
            'competence' => '2026-07',
            'status' => 'PENDING',
            'mode' => OutboundCaptureMode::Automatic,
            'origin' => OutboundRetrievalOrigin::SvrsPortalByKey,
            'access_key' => '35260711222333000181550010000000011234567920',
            'recovery_status' => SvrsNfceRecoveryStatus::Eligible,
            'urgency_band' => OutboundUrgencyBand::Planned,
            'svrs_transaction_count' => 0,
            'next_attempt_at' => now()->subMinute(),
            'accommodation_until' => now()->subMinute(),
            'created_at' => now()->subDays(2),
        ]);
        $reqRisk = $reqA->replicate();
        $reqRisk->forceFill([
            'access_key' => '35260711222333000181550010000000011234567922',
            'capacity_at_risk' => true,
            'next_attempt_at' => now()->subMinute(),
            'accommodation_until' => now()->subMinute(),
        ])->save();
        $reqB = MaOutboundRetrievalRequest::query()->create([
            'office_id' => $officeB->id,
            'outbound_capture_profile_id' => $profileB->id,
            'establishment_id' => $estB->id,
            'environment' => 'homologation',
            'model' => OutboundFiscalModel::Nfe,
            'direction' => 'OUT',
            'competence' => '2026-07',
            'status' => 'PENDING',
            'mode' => OutboundCaptureMode::Automatic,
            'origin' => OutboundRetrievalOrigin::SvrsPortalByKey,
            'access_key' => '35260799888777000166550010000000011234567921',
            'recovery_status' => SvrsNfceRecoveryStatus::Eligible,
            'urgency_band' => OutboundUrgencyBand::Planned,
            'svrs_transaction_count' => 0,
            'next_attempt_at' => now()->subMinute(),
            'accommodation_until' => now()->subMinute(),
            'created_at' => now()->subDays(2),
        ]);

        Queue::fake();
        Artisan::call('outbound:deadline-plan', [
            '--dispatch' => true,
            '--office' => $officeA->id,
        ]);

        Queue::assertPushed(RecoverSvrsNfceXmlJob::class, function (RecoverSvrsNfceXmlJob $job) use ($reqA) {
            return $job->retrievalRequestId === $reqA->id;
        });
        Queue::assertNotPushed(RecoverSvrsNfceXmlJob::class, function (RecoverSvrsNfceXmlJob $job) use ($reqB) {
            return $job->retrievalRequestId === $reqB->id;
        });
        Queue::assertNotPushed(RecoverSvrsNfceXmlJob::class, function (RecoverSvrsNfceXmlJob $job) use ($reqRisk) {
            return $job->retrievalRequestId === $reqRisk->id;
        });
        Queue::assertPushed(RecoverSvrsNfceXmlJob::class, 1);
    }

    /**
     * @return array{0: Office, 1: OutboundCaptureProfile, 2: Establishment}
     */
    /**
     * @return array{0: Office, 1: OutboundCaptureProfile, 2: Establishment}
     */
    private function seedOffice(): array
    {
        $office = Office::factory()->create();
        $client = Client::factory()->forOffice($office)->create();
        $est = Establishment::factory()->forClient($client)->create([
            'office_id' => $office->id,
            'address_state' => 'MA',
        ]);
        $profile = OutboundCaptureProfile::query()->create([
            'office_id' => $office->id,
            'client_id' => $client->id,
            'establishment_id' => $est->id,
            'uf' => 'MA',
            'environment' => 'homologation',
            'model' => OutboundFiscalModel::Nfe,
            'mode' => OutboundCaptureMode::Assisted,
            'status' => OutboundProfileStatus::SeedReady,
        ]);

        return [$office, $profile, $est];
    }
}
