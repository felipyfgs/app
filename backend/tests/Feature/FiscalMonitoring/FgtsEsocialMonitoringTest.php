<?php

namespace Tests\Feature\FiscalMonitoring;

use App\DTO\Esocial\EsocialEventDto;
use App\Enums\EsocialEventCode;
use App\Enums\FgtsIndependentState;
use App\Enums\FiscalCoverage;
use App\Enums\FiscalRunStatus;
use App\Enums\FiscalSituation;
use App\Enums\OfficeRole;
use App\Models\Client;
use App\Models\EsocialEventEvidence;
use App\Models\Establishment;
use App\Models\FgtsCompetenceStatus;
use App\Models\FiscalSnapshot;
use App\Models\Office;
use App\Models\User;
use App\Services\Esocial\FakeEsocialEventClient;
use App\Services\Esocial\FgtsEsocialDivergenceAnalyzer;
use App\Services\Esocial\FgtsEsocialMonitoringService;
use App\Services\Esocial\FgtsIndependentStateProjector;
use App\Services\FiscalMonitoring\FiscalMonitoringRunService;
use App\Support\CurrentOffice;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FgtsEsocialMonitoringTest extends TestCase
{
    use RefreshDatabase;

    private Office $office;

    private Client $client;

    private User $admin;

    private FakeEsocialEventClient $fakeClient;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'features.global_enabled' => true,
            'features.kill_switch' => false,
            'features.modules.fgts.enabled' => true,
            'features.modules.fgts.allow_all_offices' => true,
            'fiscal_monitoring.enabled' => true,
            'fiscal_monitoring.kill_switch' => false,
            'fgts_esocial.kill_switch' => false,
            'fgts_esocial.totalizer_absence_window_hours' => 72,
        ]);

        $this->office = Office::factory()->create();
        $this->client = Client::factory()->forOffice($this->office)->create();
        $this->admin = User::factory()->forOffice($this->office, OfficeRole::Admin)->withTwoFactorConfirmed()->create();
        $this->fakeClient = app(FakeEsocialEventClient::class);
        $this->fakeClient->clear();
    }

    public function test_totalizador_persiste_evidencia_e_estados_independentes(): void
    {
        $competence = '2026-05';
        $this->fakeClient->seed(FakeEsocialEventClient::sampleTotalizer($competence, EsocialEventCode::S5003));

        $out = app(FgtsEsocialMonitoringService::class)->syncCompetence(
            $this->office,
            $this->client,
            $competence,
        );

        $this->assertSame(1, $out['events_count']);
        $this->assertSame(FgtsIndependentState::Present, $out['projection']->totalizationStatus);
        $this->assertSame(FgtsIndependentState::Unknown, $out['projection']->closureStatus);
        $this->assertSame(FgtsIndependentState::Unsupported, $out['projection']->guideStatus);
        $this->assertSame(FgtsIndependentState::Unsupported, $out['projection']->paymentStatus);
        $this->assertSame(FiscalCoverage::Partial, $out['projection']->coverage);
        $this->assertNotSame(FiscalSituation::UpToDate, $out['projection']->situation);
        $this->assertFalse($out['projection']->normalized['declares_fgts_digital_debt']);
        $this->assertFalse($out['projection']->normalized['guide_consulted']);
        $this->assertFalse($out['projection']->normalized['payment_consulted']);

        $ev = EsocialEventEvidence::query()->withoutGlobalScopes()
            ->where('office_id', $this->office->id)
            ->where('event_code', EsocialEventCode::S5003->value)
            ->first();
        $this->assertNotNull($ev);
        $this->assertSame($competence, $ev->competence_period_key);
        $this->assertNotEmpty($ev->content_sha256);
        $this->assertNotEmpty($ev->vault_object_id);

        $status = $out['status'];
        $this->assertSame(FgtsIndependentState::Present, $status->totalization_status);
        $this->assertSame(FgtsIndependentState::Unsupported, $status->guide_status);
        $this->assertNotEmpty($status->limitations);
    }

    public function test_fechamento_sem_guia_mantem_guia_e_pagamento_unsupported(): void
    {
        $competence = '2026-04';
        $this->fakeClient->seed(FakeEsocialEventClient::sampleClosure($competence));
        $this->fakeClient->seed(FakeEsocialEventClient::sampleTotalizer($competence, EsocialEventCode::S5013));

        $out = app(FgtsEsocialMonitoringService::class)->syncCompetence(
            $this->office,
            $this->client,
            $competence,
            now: CarbonImmutable::now(),
        );

        $p = $out['projection'];
        $this->assertSame(FgtsIndependentState::Confirmed, $p->closureStatus);
        $this->assertSame(FgtsIndependentState::Present, $p->totalizationStatus);
        $this->assertSame(FgtsIndependentState::Unsupported, $p->guideStatus);
        $this->assertSame(FgtsIndependentState::Unsupported, $p->paymentStatus);

        // Nenhum finding de débito portal
        foreach ($p->findings as $f) {
            $this->assertFalse(
                app(FgtsEsocialDivergenceAnalyzer::class)->declaresFgtsDigitalDebt($f),
                'Finding não deve declarar débito FGTS Digital: '.json_encode($f),
            );
            $this->assertNotSame('FGTS_DIGITAL_DEBT', $f['code']);
        }

        $codes = EsocialEventEvidence::query()->withoutGlobalScopes()
            ->where('office_id', $this->office->id)
            ->pluck('event_code')
            ->map(fn ($c) => $c instanceof EsocialEventCode ? $c->value : (string) $c)
            ->all();
        $this->assertContains(EsocialEventCode::S1299->value, $codes);
        $this->assertContains(EsocialEventCode::S5013->value, $codes);
    }

    public function test_ausencia_totalizador_apos_janela_gera_attention_sem_debito(): void
    {
        $competence = '2026-03';
        $closureAt = CarbonImmutable::parse('2026-04-01 10:00:00');
        $now = $closureAt->addHours(80); // > 72h

        $payload = json_encode([
            'evento' => 'S-1299',
            'competencia' => $competence,
            'simulated' => true,
        ], JSON_THROW_ON_ERROR);

        $this->fakeClient->seed(new EsocialEventDto(
            eventCode: EsocialEventCode::S1299,
            competencePeriodKey: $competence,
            payloadBytes: $payload,
            eventVersion: '1.0',
            receiptNumber: 'REC-OLD',
            occurredAt: $closureAt,
            observedAt: $closureAt,
        ));

        $out = app(FgtsEsocialMonitoringService::class)->syncCompetence(
            $this->office,
            $this->client,
            $competence,
            now: $now,
        );

        $this->assertSame(FgtsIndependentState::Confirmed, $out['projection']->closureStatus);
        $this->assertSame(FgtsIndependentState::Absent, $out['projection']->totalizationStatus);
        $this->assertSame(FiscalSituation::Attention, $out['projection']->situation);
        $this->assertSame(FgtsIndependentState::Unsupported, $out['projection']->guideStatus);

        $codes = array_column($out['projection']->findings, 'code');
        $this->assertContains('ESOCIAL_TOTALIZER_MISSING_AFTER_CLOSURE', $codes);

        foreach ($out['projection']->findings as $f) {
            $this->assertStringNotContainsStringIgnoringCase('débito do portal FGTS Digital em aberto', $f['detail'] ?? '');
            $this->assertFalse(app(FgtsEsocialDivergenceAnalyzer::class)->declaresFgtsDigitalDebt($f));
        }
    }

    public function test_dentro_da_janela_totalizador_permanece_unknown(): void
    {
        $competence = '2026-03';
        $closureAt = CarbonImmutable::parse('2026-04-01 10:00:00');
        $now = $closureAt->addHours(10); // < 72h

        $this->fakeClient->seed(new EsocialEventDto(
            eventCode: EsocialEventCode::S1299,
            competencePeriodKey: $competence,
            payloadBytes: json_encode(['evento' => 'S-1299', 'competencia' => $competence], JSON_THROW_ON_ERROR),
            occurredAt: $closureAt,
            observedAt: $closureAt,
        ));

        $out = app(FgtsEsocialMonitoringService::class)->syncCompetence(
            $this->office,
            $this->client,
            $competence,
            now: $now,
        );

        $this->assertSame(FgtsIndependentState::Confirmed, $out['projection']->closureStatus);
        $this->assertSame(FgtsIndependentState::Unknown, $out['projection']->totalizationStatus);
        $codes = array_column($out['projection']->findings, 'code');
        $this->assertNotContains('ESOCIAL_TOTALIZER_MISSING_AFTER_CLOSURE', $codes);
    }

    public function test_fonte_unsupported_projeta_estados_honestos(): void
    {
        $this->fakeClient->markUnsupported(true);

        $out = app(FgtsEsocialMonitoringService::class)->syncCompetence(
            $this->office,
            $this->client,
            '2026-01',
        );

        $this->assertSame(FiscalCoverage::Unsupported, $out['projection']->coverage);
        $this->assertSame(FiscalSituation::Unsupported, $out['projection']->situation);
        $this->assertSame(FgtsIndependentState::Unsupported, $out['projection']->guideStatus);
        $this->assertSame(FgtsIndependentState::Unsupported, $out['projection']->paymentStatus);
        $this->assertSame(0, EsocialEventEvidence::query()->withoutGlobalScopes()
            ->where('office_id', $this->office->id)->count());
    }

    public function test_isolamento_tenant_em_evidencias_e_status(): void
    {
        $competence = '2026-02';
        $this->fakeClient->seed(FakeEsocialEventClient::sampleTotalizer($competence));

        app(FgtsEsocialMonitoringService::class)->syncCompetence(
            $this->office,
            $this->client,
            $competence,
        );

        $officeB = Office::factory()->create();
        $clientB = Client::factory()->forOffice($officeB)->create();

        $this->fakeClient->clear();
        $this->fakeClient->seed(FakeEsocialEventClient::sampleClosure($competence));
        app(FgtsEsocialMonitoringService::class)->syncCompetence($officeB, $clientB, $competence);

        $this->assertSame(1, EsocialEventEvidence::query()->withoutGlobalScopes()
            ->where('office_id', $this->office->id)->count());
        $this->assertSame(1, EsocialEventEvidence::query()->withoutGlobalScopes()
            ->where('office_id', $officeB->id)->count());

        // Office B não vê dados de A
        $this->assertNull(
            FgtsCompetenceStatus::query()->withoutGlobalScopes()
                ->where('office_id', $officeB->id)
                ->where('client_id', $this->client->id)
                ->first()
        );

        $statusA = FgtsCompetenceStatus::query()->withoutGlobalScopes()
            ->where('office_id', $this->office->id)->first();
        $statusB = FgtsCompetenceStatus::query()->withoutGlobalScopes()
            ->where('office_id', $officeB->id)->first();
        $this->assertNotNull($statusA);
        $this->assertNotNull($statusB);
        $this->assertSame(FgtsIndependentState::Present, $statusA->totalization_status);
        $this->assertSame(FgtsIndependentState::Confirmed, $statusB->closure_status);
        $this->assertNotSame($statusA->id, $statusB->id);
    }

    public function test_adapter_fiscal_run_persiste_snapshot_parcial(): void
    {
        $competence = '2026-06';
        $this->fakeClient->seed(FakeEsocialEventClient::sampleTotalizer($competence));
        $this->fakeClient->seed(FakeEsocialEventClient::sampleClosure($competence));

        $svc = app(FiscalMonitoringRunService::class);
        $run = $svc->enqueueManual(
            $this->office,
            $this->client,
            'ESOCIAL',
            'FGTS',
            competence: null,
            correlationId: 'fgts-run-1',
            dispatch: false,
        );
        $run->forceFill([
            'progress' => ['competence_period_key' => $competence],
        ])->save();

        $done = $svc->execute($run->id);

        $this->assertSame(FiscalRunStatus::Completed, $done->status);
        $this->assertSame(FiscalCoverage::Partial, $done->coverage);
        $this->assertNotSame(FiscalSituation::UpToDate, $done->situation);

        $snapshot = FiscalSnapshot::query()->withoutGlobalScopes()
            ->where('run_id', $done->id)->first();
        $this->assertNotNull($snapshot);
        $this->assertSame(FiscalCoverage::Partial, $snapshot->coverage);
        $this->assertArrayHasKey('limitations', $snapshot->normalized ?? []);
        $this->assertFalse($snapshot->normalized['declares_fgts_digital_debt'] ?? true);

        $this->assertGreaterThanOrEqual(1, EsocialEventEvidence::query()->withoutGlobalScopes()
            ->where('office_id', $this->office->id)->count());
    }

    public function test_api_coverage_e_sync_now_tenant_scoped(): void
    {
        $this->actingAs($this->admin);
        app(CurrentOffice::class)->resolve($this->admin);

        $this->getJson('/api/v1/fiscal/fgts/coverage')
            ->assertOk()
            ->assertJsonPath('data.coverage', 'PARTIAL')
            ->assertJsonPath('data.declares_fgts_digital_debt', false)
            ->assertJsonPath('data.scraping_allowed', false)
            ->assertJsonStructure(['data' => ['limitations', 'supported_events', 'independent_states']]);

        $competence = '2026-07';
        $this->fakeClient->seed(FakeEsocialEventClient::sampleTotalizer($competence, EsocialEventCode::S5003));

        $this->postJson('/api/v1/fiscal/fgts/sync-now', [
            'client_id' => $this->client->id,
            'competence_period_key' => $competence,
        ])
            ->assertOk()
            ->assertJsonPath('data.status.totalization_status', 'PRESENT')
            ->assertJsonPath('data.status.guide_status', 'UNSUPPORTED')
            ->assertJsonPath('data.status.payment_status', 'UNSUPPORTED')
            ->assertJsonPath('data.status.declares_fgts_digital_debt', false)
            ->assertJsonPath('data.coverage.coverage', 'PARTIAL');

        $this->getJson('/api/v1/fiscal/fgts/competences?client_id='.$this->client->id)
            ->assertOk()
            ->assertJsonPath('data.0.competence_period_key', $competence);

        $this->getJson('/api/v1/fiscal/fgts/events?client_id='.$this->client->id)
            ->assertOk()
            ->assertJsonPath('coverage.declares_fgts_digital_debt', false);

        // Isolamento: outro office não vê
        $other = Office::factory()->create();
        $otherAdmin = User::factory()->forOffice($other, OfficeRole::Admin)->withTwoFactorConfirmed()->create();
        $this->actingAs($otherAdmin);
        app(CurrentOffice::class)->clear();
        app(CurrentOffice::class)->resolve($otherAdmin);

        $this->getJson('/api/v1/fiscal/fgts/competences')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->getJson('/api/v1/fiscal/fgts/events')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_api_nao_aceita_client_de_outro_tenant(): void
    {
        $other = Office::factory()->create();
        $otherClient = Client::factory()->forOffice($other)->create();

        $this->actingAs($this->admin);
        app(CurrentOffice::class)->resolve($this->admin);

        $this->postJson('/api/v1/fiscal/fgts/sync-now', [
            'client_id' => $otherClient->id,
            'competence_period_key' => '2026-01',
        ])->assertNotFound();
    }

    public function test_evidencia_por_estabelecimento(): void
    {
        $est = Establishment::factory()->forClient($this->client)->create([
            'cnpj' => '12345678000199',
        ]);
        $competence = '2026-08';

        $this->fakeClient->seed(FakeEsocialEventClient::sampleTotalizer(
            $competence,
            EsocialEventCode::S5003,
            '12345678000199',
        ));

        $out = app(FgtsEsocialMonitoringService::class)->syncCompetence(
            $this->office,
            $this->client,
            $competence,
            $est,
        );

        $ev = EsocialEventEvidence::query()->withoutGlobalScopes()
            ->where('office_id', $this->office->id)
            ->first();
        $this->assertNotNull($ev);
        $this->assertSame($est->id, $ev->establishment_id);
        $this->assertSame($est->id, $out['status']->establishment_id);
    }

    public function test_divergence_analyzer_rejeita_finding_de_debito_portal(): void
    {
        $analyzer = app(FgtsEsocialDivergenceAnalyzer::class);
        $this->assertTrue($analyzer->declaresFgtsDigitalDebt([
            'code' => 'FGTS_DIGITAL_DEBT',
            'title' => 'Débito',
            'detail' => 'x',
        ]));
        $this->assertTrue($analyzer->declaresFgtsDigitalDebt([
            'code' => 'X',
            'title' => 'Pendência',
            'detail' => 'Débito FGTS Digital em aberto no portal',
        ]));
        $this->assertFalse($analyzer->declaresFgtsDigitalDebt([
            'code' => 'ESOCIAL_TOTALIZER_MISSING_AFTER_CLOSURE',
            'title' => 'Ausente',
            'detail' => 'Revisão operacional — não declara débito do portal FGTS Digital.',
        ]));
    }

    public function test_projector_limitations_sempre_presentes(): void
    {
        $p = app(FgtsIndependentStateProjector::class)->project('2026-01', []);
        $this->assertNotEmpty($p->limitations);
        $this->assertSame(FgtsIndependentState::Unsupported, $p->guideStatus);
        $this->assertSame(FgtsIndependentState::Unsupported, $p->paymentStatus);
        $this->assertSame(FiscalSituation::Unknown, $p->situation);
    }
}
