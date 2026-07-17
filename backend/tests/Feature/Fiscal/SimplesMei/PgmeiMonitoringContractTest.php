<?php

namespace Tests\Feature\Fiscal\SimplesMei;

use App\DTO\Fiscal\FiscalAdapterRequest;
use App\DTO\Fiscal\FiscalAdapterResult;
use App\DTO\Serpro\IntegraResponse;
use App\Enums\FiscalCoverage;
use App\Enums\FiscalRunResult;
use App\Enums\FiscalRunStatus;
use App\Enums\FiscalSituation;
use App\Enums\FiscalSourceProvenance;
use App\Enums\FiscalTrigger;
use App\Enums\OfficeRole;
use App\Models\Client;
use App\Models\ClientCommunicationPreference;
use App\Models\FiscalMonitoringRun;
use App\Models\FiscalMonitoringSchedule;
use App\Models\Office;
use App\Models\PgmeiDebtObservation;
use App\Models\PgmeiDebtProjection;
use App\Models\User;
use App\Services\Fiscal\SimplesMei\Pgmei\PgmeiDebtProjector;
use App\Services\Fiscal\SimplesMei\Pgmei\PgmeiDividaAtiva24Codec;
use App\Services\Fiscal\SimplesMei\Pgmei\PgmeiMonitoringQueryService;
use App\Services\Fiscal\SimplesMei\Pgmei\PgmeiPostConsultService;
use App\Services\Fiscal\SimplesMei\SimplesMeiCatalog;
use App\Services\Fiscal\SimplesMei\SimplesMeiResponseMapper;
use App\Services\FiscalMonitoring\FiscalMonitoringScheduler;
use App\Support\CurrentOffice;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

final class PgmeiMonitoringContractTest extends TestCase
{
    use RefreshDatabase;

    private Office $office;

    private Client $client;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'features.global_enabled' => true,
            'features.kill_switch' => false,
            'features.modules.simples_mei.enabled' => true,
            'features.modules.simples_mei.allow_all_offices' => true,
            'fiscal_monitoring.enabled' => true,
        ]);

        $this->office = Office::factory()->create(['timezone' => 'America/Sao_Paulo']);
        $this->client = Client::factory()->forOffice($this->office)->create(['tax_regime' => 'MEI']);
        $this->admin = User::factory()
            ->forOffice($this->office, OfficeRole::Admin)
            ->withTwoFactorConfirmed()
            ->create();

        $this->actingAs($this->admin);
        app(CurrentOffice::class)->clear();
        app(CurrentOffice::class)->resolve($this->admin);
    }

    public function test_portfolio_and_get_endpoints_reject_client_office_id(): void
    {
        foreach (['PGDASD', 'PGMEI'] as $submodule) {
            $this->getJson(
                "/api/v1/fiscal/modules/simples_mei/clients?submodule={$submodule}&office_id=999"
            )
                ->assertStatus(422)
                ->assertJsonPath('code', 'CLIENT_OFFICE_ID_REJECTED');
        }

        $base = "/api/v1/fiscal/simples-mei/pgmei/clients/{$this->client->id}";
        foreach ([
            "{$base}/history?year=2026&office_id=999",
            "{$base}/communication-preview?office_id=999",
            "{$base}/communications?office_id=999",
        ] as $url) {
            $this->getJson($url)
                ->assertStatus(422)
                ->assertJsonPath('code', 'CLIENT_OFFICE_ID_REJECTED');
        }
    }

    public function test_navigation_is_local_template_only_and_never_enqueues(): void
    {
        Queue::fake();
        $base = "/api/v1/fiscal/simples-mei/pgmei/clients/{$this->client->id}";

        $this->getJson("{$base}/history?year=2026")
            ->assertOk()
            ->assertJsonPath('data.provenance.serpro_called', false);
        $this->getJson("{$base}/communication-preview")
            ->assertOk()
            ->assertJsonPath('data.execution_mode', 'TEMPLATE_ONLY')
            ->assertJsonPath('data.can_send', false);
        $this->getJson("{$base}/communications")
            ->assertOk()
            ->assertJsonPath('data.execution_mode', 'TEMPLATE_ONLY');

        $this->postJson('/api/v1/fiscal/simples-mei/pgmei/consult', [
            'client_ids' => [$this->client->id],
            'year' => 2026,
            'confirmed' => false,
        ])->assertStatus(422);

        $this->assertSame(0, FiscalMonitoringRun::query()->withoutGlobalScopes()->count());
        Queue::assertNothingPushed();
    }

    public function test_projection_is_tenant_scoped_by_year_and_communication_is_isolated(): void
    {
        $codec = app(PgmeiDividaAtiva24Codec::class);
        $projector = app(PgmeiDebtProjector::class);
        $projector->projectValid($this->office, $this->client, $this->debt($codec, 2025, '10,50'), null);
        $projector->projectValid($this->office, $this->client, $codec->decodeDados([], 2026), null);

        ClientCommunicationPreference::query()->create([
            'office_id' => $this->office->id,
            'client_id' => $this->client->id,
            'module_key' => 'simples_mei',
            'submodule_key' => 'pgdasd',
            'automatic_requested' => true,
            'email_enabled' => true,
            'whatsapp_enabled' => false,
            'lock_version' => 3,
            'updated_by_user_id' => $this->admin->id,
        ]);

        $queries = app(PgmeiMonitoringQueryService::class);
        $year2025 = $queries->portfolioDetails($this->office, [$this->client->id], 2025);
        $year2026 = $queries->portfolioDetails($this->office, [$this->client->id], 2026);

        $this->assertSame(2025, $year2025[$this->client->id]['pgmei']['calendar_year']);
        $this->assertSame(1050, $year2025[$this->client->id]['pgmei']['total_cents']);
        $this->assertSame('HAS_ACTIVE_DEBT', $year2025[$this->client->id]['pgmei']['debt_state']);
        $this->assertSame('NO_ACTIVE_DEBT', $year2026[$this->client->id]['pgmei']['debt_state']);
        $this->assertFalse($year2025[$this->client->id]['pgmei']['communication']['automatic_requested']);
        $this->assertSame(0, $year2025[$this->client->id]['pgmei']['communication']['lock_version']);
        $this->assertSame('TEMPLATE_ONLY', $year2025[$this->client->id]['pgmei']['communication']['execution_mode']);

        $otherOffice = Office::factory()->create();
        $otherClient = Client::factory()->forOffice($otherOffice)->create();
        $this->expectException(RuntimeException::class);
        $projector->projectValid($this->office, $otherClient, $this->debt($codec, 2025, '1,00'), null);
    }

    public function test_each_valid_run_has_immutable_observation_and_replay_is_idempotent(): void
    {
        $codec = app(PgmeiDividaAtiva24Codec::class);
        $decoded = $this->debt($codec, 2026, '2,00');
        $projector = app(PgmeiDebtProjector::class);
        $firstRun = $this->makeRun('first');
        $secondRun = $this->makeRun('second');

        $first = $projector->projectValid($this->office, $this->client, $decoded, $firstRun->id);
        $second = $projector->projectValid($this->office, $this->client, $decoded, $secondRun->id);
        $replay = $projector->projectValid($this->office, $this->client, $decoded, $secondRun->id);

        $this->assertTrue($first['created']);
        $this->assertTrue($second['created']);
        $this->assertFalse($replay['created']);
        $this->assertSame($second['observation']->id, $replay['observation']->id);
        $this->assertSame(2, PgmeiDebtObservation::query()->withoutGlobalScopes()->count());
    }

    public function test_only_real_productive_response_promotes_and_empty_dados_is_valid(): void
    {
        $definition = SimplesMeiCatalog::find('INTEGRA_MEI', 'PGMEI', 'CONSULTAR');
        $this->assertNotNull($definition);
        $mapper = app(SimplesMeiResponseMapper::class);
        $mapped = $mapper->map($definition, new IntegraResponse(
            success: true,
            httpStatus: 200,
            body: [],
            dados: [],
        ), '2026');
        $this->assertSame(FiscalSituation::UpToDate, $mapped->situation);
        $this->assertSame('NO_ACTIVE_DEBT', $mapped->normalized['debt_state']);

        $run = $this->makeRun('provenance');
        $request = new FiscalAdapterRequest(
            office: $this->office,
            client: $this->client,
            run: $run,
            systemCode: 'INTEGRA_MEI',
            serviceCode: 'PGMEI',
            operationCode: 'CONSULTAR',
            trigger: FiscalTrigger::Manual,
            progress: ['ano_calendario' => '2026'],
        );
        $result = new FiscalAdapterResult(
            result: FiscalRunResult::Success,
            situation: FiscalSituation::UpToDate,
            coverage: FiscalCoverage::Full,
            evidenceBytes: '{}',
            normalized: [],
        );
        $post = app(PgmeiPostConsultService::class);

        $unverified = $post->handle($request, new IntegraResponse(
            success: true,
            httpStatus: 200,
            body: [],
            dados: [],
        ), $result, 'pgmei.dividaativa');
        $this->assertFalse($unverified['result']->normalized['pgmei']['promoted']);
        $this->assertSame(0, PgmeiDebtProjection::query()->withoutGlobalScopes()->count());

        $real = $post->handle($request, new IntegraResponse(
            success: true,
            httpStatus: 200,
            body: [],
            dados: [],
            sourceProvenance: FiscalSourceProvenance::SerproReal->value,
        ), $result, 'pgmei.dividaativa');
        $this->assertTrue($real['result']->normalized['pgmei']['promoted']);
        $this->assertSame(1, PgmeiDebtProjection::query()->withoutGlobalScopes()->count());
    }

    public function test_scheduler_deduplicates_pgmei_aliases_in_same_daily_cycle(): void
    {
        Bus::fake();
        $now = CarbonImmutable::parse('2026-07-17 09:00:00', 'America/Sao_Paulo')->utc();
        $first = $this->schedule('INTEGRA_MEI', 'PGMEI', 'MONITOR', $now);
        $alias = $this->schedule('PGMEI', 'PGMEI', 'CONSULTAR', $now);
        $scheduler = app(FiscalMonitoringScheduler::class);

        $this->assertSame('dispatched', $scheduler->claimAndEnqueue($first, $now));
        $this->assertSame('skipped', $scheduler->claimAndEnqueue($alias, $now));
        $this->assertSame(1, FiscalMonitoringRun::query()->withoutGlobalScopes()->count());
    }

    /** @return array<string, mixed> */
    private function debt(PgmeiDividaAtiva24Codec $codec, int $year, string $amount): array
    {
        return $codec->decodeDados([[
            'periodoApuracao' => "{$year}01",
            'tributo' => 'INSS',
            'valor' => $amount,
            'enteFederado' => 'União',
            'situacaoDebito' => 'Ativa',
        ]], $year);
    }

    private function makeRun(string $suffix): FiscalMonitoringRun
    {
        return FiscalMonitoringRun::query()->create([
            'office_id' => $this->office->id,
            'client_id' => $this->client->id,
            'system_code' => 'INTEGRA_MEI',
            'service_code' => 'PGMEI',
            'operation_code' => 'MONITOR',
            'trigger' => FiscalTrigger::Manual,
            'idempotency_key' => "pgmei-contract-{$suffix}",
            'status' => FiscalRunStatus::Queued,
            'situation' => FiscalSituation::Unknown,
            'coverage' => FiscalCoverage::Full,
            'mutability' => 'READ_ONLY',
        ]);
    }

    private function schedule(
        string $system,
        string $service,
        string $operation,
        CarbonImmutable $now,
    ): FiscalMonitoringSchedule {
        return FiscalMonitoringSchedule::query()->create([
            'office_id' => $this->office->id,
            'client_id' => $this->client->id,
            'system_code' => $system,
            'service_code' => $service,
            'operation_code' => $operation,
            'is_enabled' => true,
            'interval_minutes' => 1440,
            'preferred_minute' => 0,
            'next_run_at' => $now->subMinute(),
        ]);
    }
}
