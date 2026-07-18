<?php

namespace Tests\Feature\Fiscal\Declarations;

use App\Enums\FiscalSituation;
use App\Enums\OfficeRole;
use App\Enums\TaxDeliveryEvidenceKind;
use App\Enums\TaxObligationApplicability;
use App\Models\Client;
use App\Models\Office;
use App\Models\TaxDeadlineCalendarVersion;
use App\Models\TaxObligationDefinition;
use App\Models\TaxObligationProjection;
use App\Models\User;
use App\Services\Fiscal\Declarations\TaxDeadlineCalendarService;
use App\Services\Fiscal\Declarations\TaxDeliveryEvidenceService;
use App\Services\Fiscal\Declarations\TaxObligationCatalogService;
use App\Services\Fiscal\Declarations\TaxObligationProjectionService;
use App\Support\CurrentOffice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tasks 11.1–11.5 e 11.11 (parte declarações): catálogo, aplicabilidade, recibo, prorrogação, tenant.
 */
class TaxDeclarationMonitoringTest extends TestCase
{
    use RefreshDatabase;

    private Office $office;

    private Client $clientSn;

    private Client $clientUnknownRegime;

    private User $admin;

    private TaxObligationProjectionService $projections;

    private TaxDeliveryEvidenceService $evidences;

    private TaxDeadlineCalendarService $deadlines;

    private TaxObligationCatalogService $catalog;

    protected function setUp(): void
    {
        parent::setUp();

        $this->office = Office::factory()->create();
        $this->clientSn = Client::factory()->forOffice($this->office)->create([
            'tax_regime' => 'SIMPLES_NACIONAL',
        ]);
        $this->clientUnknownRegime = Client::factory()->forOffice($this->office)->create([
            'tax_regime' => null,
        ]);
        $this->admin = User::factory()->forOffice($this->office, OfficeRole::Admin)->withTwoFactorConfirmed()->create();

        $this->projections = app(TaxObligationProjectionService::class);
        $this->evidences = app(TaxDeliveryEvidenceService::class);
        $this->deadlines = app(TaxDeadlineCalendarService::class);
        $this->catalog = app(TaxObligationCatalogService::class);
    }

    public function test_catalogo_seed_possui_obrigacoes_versoes_regimes_e_calendario(): void
    {
        $this->assertGreaterThanOrEqual(5, TaxObligationDefinition::query()->count());

        $pgdas = $this->catalog->findByCode('PGDAS_D');
        $this->assertNotNull($pgdas);
        $this->assertSame('America/Sao_Paulo', $pgdas->default_timezone);
        $this->assertContains('CONSULTAR_RECIBO', $pgdas->supported_operations ?? []);

        $version = $this->catalog->currentVersion($pgdas);
        $this->assertNotNull($version);
        $this->assertTrue($version->is_current);
        $this->assertNotEmpty($version->source_ref);

        $this->assertGreaterThanOrEqual(4, $version->regimeRules()->count());

        $calendar = $this->catalog->currentCalendar();
        $this->assertNotNull($calendar);
        $this->assertSame('RFB_NATIONAL', $calendar->code);
        $this->assertTrue($calendar->is_current);
        $this->assertGreaterThanOrEqual(1, $calendar->rules()->count());
    }

    public function test_aplicabilidade_sn_pgdas_aplicavel_e_dasn_nao_aplicavel(): void
    {
        $pgdas = $this->catalog->findByCode('PGDAS_D');
        $dasn = $this->catalog->findByCode('DASN_SIMEI');

        $pgdasProj = $this->projections->project(
            $this->office,
            $this->clientSn,
            $pgdas,
            '2026-01',
        );
        $dasnProj = $this->projections->project(
            $this->office,
            $this->clientSn,
            $dasn,
            '2025',
        );

        $this->assertSame(TaxObligationApplicability::Applicable, $pgdasProj->applicability);
        $this->assertSame(FiscalSituation::Pending, $pgdasProj->delivery_status);
        $this->assertTrue($pgdasProj->is_open);
        $this->assertNotNull($pgdasProj->due_at);
        $this->assertStringContainsString('rule_version=', (string) $pgdasProj->applicability_basis);

        $this->assertSame(TaxObligationApplicability::NotApplicable, $dasnProj->applicability);
        $this->assertSame(FiscalSituation::NotApplicable, $dasnProj->situation);
        $this->assertFalse($dasnProj->is_open);
    }

    public function test_regime_desconhecido_mantem_unknown_sem_pendencia_presumida(): void
    {
        $pgdas = $this->catalog->findByCode('PGDAS_D');

        $proj = $this->projections->project(
            $this->office,
            $this->clientUnknownRegime,
            $pgdas,
            '2026-02',
        );

        $this->assertSame(TaxObligationApplicability::Unknown, $proj->applicability);
        $this->assertSame(FiscalSituation::Unknown, $proj->situation);
        $this->assertSame(FiscalSituation::Unknown, $proj->delivery_status);
        // Não inventa PENDING
        $this->assertNotSame(FiscalSituation::Pending, $proj->delivery_status);
        $this->assertStringContainsString('regime=UNKNOWN', (string) $proj->applicability_basis);
    }

    public function test_recibo_oficial_marca_entrega_artefato_interno_nao_conclusivo(): void
    {
        $pgdas = $this->catalog->findByCode('PGDAS_D');
        $proj = $this->projections->project(
            $this->office,
            $this->clientSn,
            $pgdas,
            '2026-03',
        );

        // Artefato interno sem protocolo → não conclusivo
        $internal = $this->evidences->attach($this->office, $proj, [
            'kind' => TaxDeliveryEvidenceKind::InternalArtifact,
            'protocol_number' => 'FAKE-123', // mesmo com número, interno não é conclusivo
            'source' => 'USER_UPLOAD',
        ]);

        $proj->refresh();
        $this->assertFalse($internal->is_conclusive);
        $this->assertNull($proj->conclusive_evidence_id);
        $this->assertSame(FiscalSituation::Pending, $proj->delivery_status);
        $this->assertNotSame(FiscalSituation::UpToDate, $proj->situation);
        $this->assertTrue($proj->is_open);

        // Resposta oficial sem recibo/protocolo → não conclusivo
        $officialBare = $this->evidences->attach($this->office, $proj->fresh(), [
            'kind' => TaxDeliveryEvidenceKind::OfficialResponse,
            'source' => 'INTEGRA_SN',
            'source_version' => '1',
        ]);
        $proj->refresh();
        $this->assertFalse($officialBare->is_conclusive);
        $this->assertSame(FiscalSituation::Pending, $proj->delivery_status);

        // Recibo oficial com número → UP_TO_DATE
        $receipt = $this->evidences->attach($this->office, $proj->fresh(), [
            'kind' => TaxDeliveryEvidenceKind::OfficialReceipt,
            'receipt_number' => 'REC-PGDAS-2026-03-999',
            'source' => 'INTEGRA_SN',
            'source_version' => '1',
        ]);

        $proj->refresh();
        $this->assertTrue($receipt->is_conclusive);
        $this->assertSame($receipt->id, $proj->conclusive_evidence_id);
        $this->assertSame(FiscalSituation::UpToDate, $proj->delivery_status);
        $this->assertSame(FiscalSituation::UpToDate, $proj->situation);
        $this->assertFalse($proj->is_open);
        $this->assertNotNull($proj->closed_at);
    }

    public function test_prorrogacao_recalcula_apenas_competencias_abertas_e_preserva_historico(): void
    {
        $pgdas = $this->catalog->findByCode('PGDAS_D');
        $previousCalendarVersion = (int) TaxDeadlineCalendarVersion::query()
            ->where('code', 'RFB_NATIONAL')
            ->max('version');

        $open = $this->projections->project(
            $this->office,
            $this->clientSn,
            $pgdas,
            '2026-04',
        );
        $originalDue = $open->due_at;
        $this->assertNotNull($originalDue);

        // Fecha outra competência com recibo (não deve ser recalculada como aberta)
        $closed = $this->projections->project(
            $this->office,
            $this->clientSn,
            $pgdas,
            '2026-05',
        );
        $this->evidences->attach($this->office, $closed, [
            'kind' => TaxDeliveryEvidenceKind::OfficialProtocol,
            'protocol_number' => 'PROT-CLOSED-1',
            'source' => 'INTEGRA_SN',
        ]);
        $closed->refresh();
        $closedDueBefore = $closed->due_at;
        $this->assertFalse($closed->is_open);

        // Prorroga PGDAS para dia 25 (era 20)
        $result = $this->deadlines->publishCalendarVersion(
            code: 'RFB_NATIONAL',
            label: 'Prorrogação oficial PGDAS dia 25',
            rules: [
                [
                    'obligation_code' => 'PGDAS_D',
                    'period_granularity' => 'MONTHLY',
                    'due_day' => 25,
                    'due_month_offset' => 1,
                ],
                [
                    'obligation_code' => 'DEFIS',
                    'period_granularity' => 'ANNUAL',
                    'fixed_due_month' => 3,
                    'fixed_due_day' => 31,
                ],
                [
                    'obligation_code' => 'DASN_SIMEI',
                    'period_granularity' => 'ANNUAL',
                    'fixed_due_month' => 5,
                    'fixed_due_day' => 31,
                ],
                [
                    'obligation_code' => 'DCTFWEB',
                    'period_granularity' => 'MONTHLY',
                    'due_day' => 15,
                    'due_month_offset' => 1,
                ],
                [
                    'obligation_code' => 'MIT',
                    'period_granularity' => 'MONTHLY',
                    'due_day' => 15,
                    'due_month_offset' => 1,
                ],
            ],
            sourceRef: 'RFB/ATO-PRORROGACAO-TEST',
            notes: 'Prorrogação de teste',
            recalculateOpen: true,
        );

        $this->assertTrue($result['calendar']->is_current);
        $this->assertSame($previousCalendarVersion + 1, $result['calendar']->version);
        $this->assertGreaterThanOrEqual(1, $result['recalculated']);

        $open->refresh();
        $closed->refresh();

        $this->assertNotNull($open->due_history);
        $this->assertNotEmpty($open->due_history);
        $this->assertSame(
            $originalDue->toIso8601String(),
            $open->due_history[0]['previous_due_at'] ?? null,
        );
        $this->assertTrue($open->due_at->greaterThan($originalDue));
        $this->assertSame(25, (int) $open->due_at->day);

        // Fechada mantém due original (não está is_open)
        $this->assertSame(
            $closedDueBefore?->toIso8601String(),
            $closed->due_at?->toIso8601String(),
        );

        // Versão anterior deixa de ser corrente
        $this->assertSame(
            1,
            TaxDeadlineCalendarVersion::query()->where('code', 'RFB_NATIONAL')->where('is_current', true)->count(),
        );
    }

    public function test_api_central_declaracoes_filtros_e_deep_links(): void
    {
        $this->actingAs($this->admin);
        app(CurrentOffice::class)->resolve($this->admin);

        $this->getJson('/api/v1/fiscal/declarations/catalog')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'obligations' => [
                        ['code', 'name', 'supported_operations', 'current_version', 'regime_rules'],
                    ],
                    'calendar' => ['code', 'version', 'timezone'],
                ],
            ]);

        $this->postJson('/api/v1/fiscal/declarations/project', [
            'client_id' => $this->clientSn->id,
            'period_key' => '2026-06',
            'all' => true,
        ])->assertCreated()
            ->assertJsonStructure(['data' => [['id', 'obligation_code', 'applicability', 'deep_links']]]);

        $list = $this->getJson('/api/v1/fiscal/declarations?client_id='.$this->clientSn->id.'&period_key=2026-06')
            ->assertOk();

        $items = $list->json('data');
        $this->assertNotEmpty($items);
        $this->assertArrayHasKey('deep_links', $items[0]);
        $this->assertArrayHasKey('self', $items[0]['deep_links']);
        $this->assertStringContainsString('/api/v1/fiscal/declarations/', $items[0]['deep_links']['self']);

        // Filtro por obrigação
        $this->getJson('/api/v1/fiscal/declarations?obligation_code=PGDAS_D&client_id='.$this->clientSn->id)
            ->assertOk()
            ->assertJsonPath('data.0.obligation_code', 'PGDAS_D');

        $pgdasId = TaxObligationProjection::query()
            ->withoutGlobalScopes()
            ->where('office_id', $this->office->id)
            ->where('client_id', $this->clientSn->id)
            ->where('period_key', '2026-06')
            ->whereHas('obligation', fn ($q) => $q->where('code', 'PGDAS_D'))
            ->value('id');

        $this->assertNotNull($pgdasId);

        $this->getJson('/api/v1/fiscal/declarations/'.$pgdasId)
            ->assertOk()
            ->assertJsonPath('data.id', $pgdasId)
            ->assertJsonStructure(['data' => ['deep_links', 'evidences', 'due_rule_snapshot']]);
    }

    public function test_tenant_cruzado_nao_ve_projecao_nem_evidencia(): void
    {
        $pgdas = $this->catalog->findByCode('PGDAS_D');
        $proj = $this->projections->project(
            $this->office,
            $this->clientSn,
            $pgdas,
            '2026-07',
        );
        $this->evidences->attach($this->office, $proj, [
            'kind' => TaxDeliveryEvidenceKind::OfficialReceipt,
            'receipt_number' => 'REC-TENANT-A',
            'source' => 'INTEGRA_SN',
        ]);

        $other = Office::factory()->create();
        $otherAdmin = User::factory()->forOffice($other, OfficeRole::Admin)->withTwoFactorConfirmed()->create();

        $this->actingAs($otherAdmin);
        app(CurrentOffice::class)->clear();
        app(CurrentOffice::class)->resolve($otherAdmin);

        $this->getJson('/api/v1/fiscal/declarations')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->getJson('/api/v1/fiscal/declarations/'.$proj->id)
            ->assertNotFound();

        // Client de outro office não projeta
        $this->postJson('/api/v1/fiscal/declarations/project', [
            'client_id' => $this->clientSn->id,
            'period_key' => '2026-08',
            'obligation_code' => 'PGDAS_D',
        ])->assertNotFound();
    }

    public function test_attach_evidence_via_api_e_isolamento(): void
    {
        $this->actingAs($this->admin);
        app(CurrentOffice::class)->resolve($this->admin);

        $created = $this->postJson('/api/v1/fiscal/declarations/project', [
            'client_id' => $this->clientSn->id,
            'period_key' => '2026-09',
            'obligation_code' => 'PGDAS_D',
        ])->assertCreated();

        $id = $created->json('data.id');

        $this->postJson('/api/v1/fiscal/declarations/'.$id.'/evidences', [
            'kind' => 'INTERNAL_ARTIFACT',
            'source' => 'USER_UPLOAD',
        ])->assertCreated()
            ->assertJsonPath('data.evidence.is_conclusive', false)
            ->assertJsonPath('data.projection.delivery_status', 'PENDING');

        $this->postJson('/api/v1/fiscal/declarations/'.$id.'/evidences', [
            'kind' => 'OFFICIAL_RECEIPT',
            'receipt_number' => 'R-API-1',
            'source' => 'INTEGRA_SN',
        ])->assertCreated()
            ->assertJsonPath('data.evidence.is_conclusive', true)
            ->assertJsonPath('data.projection.delivery_status', 'UP_TO_DATE')
            ->assertJsonPath('data.projection.situation', 'UP_TO_DATE');
    }
}
