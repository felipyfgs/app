<?php

namespace Tests\Feature\Fiscal\ModulePortfolio;

use App\Enums\FiscalCoverage;
use App\Enums\FiscalLinkStatus;
use App\Enums\FiscalRunStatus;
use App\Enums\FiscalSituation;
use App\Enums\FiscalTrigger;
use App\Enums\OfficeRole;
use App\Models\Client;
use App\Models\Establishment;
use App\Models\FiscalCategory;
use App\Models\FiscalCompetence;
use App\Models\FiscalMonitoringRun;
use App\Models\FiscalSnapshot;
use App\Models\Office;
use App\Models\OfficeFiscalCategoryLink;
use App\Models\User;
use App\Support\CurrentOffice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Tasks 2.9–2.10 — contrato, paginação, busca CNPJ, filtros, contagens, isolamento.
 */
class ModulePortfolioApiTest extends TestCase
{
    use RefreshDatabase;

    private Office $office;

    private Office $otherOffice;

    private User $admin;

    private User $viewer;

    private User $operator;

    private FiscalCategory $sitfisCategory;

    /** @var list<Client> */
    private array $clients = [];

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'features.global_enabled' => true,
            'features.kill_switch' => false,
            'features.modules.sitfis.enabled' => true,
            'features.modules.sitfis.allow_all_offices' => true,
            'features.modules.simples_mei.enabled' => true,
            'features.modules.simples_mei.allow_all_offices' => true,
            'features.modules.dctfweb_mit.enabled' => true,
            'features.modules.dctfweb_mit.allow_all_offices' => true,
            'features.modules.parcelamentos.enabled' => true,
            'features.modules.parcelamentos.allow_all_offices' => true,
            'features.modules.mailbox.enabled' => true,
            'features.modules.mailbox.allow_all_offices' => true,
            'features.modules.declaracoes.enabled' => true,
            'features.modules.declaracoes.allow_all_offices' => true,
            'features.modules.guias.enabled' => true,
            'features.modules.guias.allow_all_offices' => true,
            'features.modules.fgts.enabled' => true,
            'features.modules.fgts.allow_all_offices' => true,
            'fiscal_monitoring.enabled' => true,
            'fiscal_monitoring.kill_switch' => false,
            'fiscal_monitoring.demo.office_slug' => 'demo',
        ]);

        $this->office = Office::factory()->create(['slug' => 'acme-main', 'name' => 'Acme']);
        $this->otherOffice = Office::factory()->create(['slug' => 'other-co', 'name' => 'Other']);

        $this->admin = User::factory()->forOffice($this->office, OfficeRole::Admin)->withTwoFactorConfirmed()->create();
        $this->viewer = User::factory()->forOffice($this->office, OfficeRole::Viewer)->withTwoFactorConfirmed()->create();
        $this->operator = User::factory()->forOffice($this->office, OfficeRole::Operator)->withTwoFactorConfirmed()->create();

        $this->sitfisCategory = FiscalCategory::query()->where('code', 'SITFIS')->firstOrFail();

        $this->seedPortfolio();
    }

    public function test_overview_contrato_e_contadores_no_escopo_completo(): void
    {
        $this->actingAsOffice($this->admin);

        $response = $this->getJson('/api/v1/fiscal/modules/sitfis/overview')
            ->assertOk()
            ->assertJsonPath('data.module_key', 'sitfis')
            ->assertJsonPath('data.data_origin', 'LIVE')
            ->assertJsonPath('data.total_clients', 6)
            ->assertJsonStructure([
                'data' => [
                    'module_key',
                    'module_label',
                    'data_origin',
                    'data_origin_label',
                    'is_synthetic',
                    'coverage',
                    'source_label',
                    'as_of',
                    'total_clients',
                    'counters' => [
                        'up_to_date',
                        'processing',
                        'pending',
                        'attention',
                        'error',
                        'blocked',
                        'unknown',
                        'unsupported',
                        'not_applicable',
                    ],
                    'agenda',
                    'categories',
                    'metrics',
                ],
            ]);

        $counters = $response->json('data.counters');
        $this->assertSame(2, $counters['up_to_date']);
        $this->assertSame(0, $counters['processing']);
        $this->assertSame(1, $counters['pending']);
        $this->assertSame(1, $counters['attention']);
        $this->assertSame(1, $counters['error']);
        $this->assertSame(0, $counters['blocked']);
        $this->assertSame(1, $counters['unknown']);
        $this->assertSame(0, $counters['unsupported']);
        $this->assertSame(0, $counters['not_applicable']);
        $this->assertSame(
            (int) $response->json('data.total_clients'),
            array_sum($counters),
            'Soma dos nove contadores deve igualar total_clients',
        );

        // Filtro de situação NÃO distorce overview: total e contadores no escopo sem situation;
        // somente a lista paginada aplica o eixo.
        $filtered = $this->getJson('/api/v1/fiscal/modules/sitfis/overview?situation=PENDING')
            ->assertOk();
        $this->assertSame(6, $filtered->json('data.total_clients'));
        $this->assertSame(2, $filtered->json('data.counters.up_to_date'));
        $this->assertSame(1, $filtered->json('data.counters.pending'));

        $list = $this->getJson('/api/v1/fiscal/modules/sitfis/clients?situation=PENDING')
            ->assertOk();
        $this->assertSame(1, $list->json('meta.total'));
        $this->assertSame('PENDING', $list->json('data.0.situation'));
    }

    public function test_carteira_integralmente_bloqueada(): void
    {
        // Substitui snapshots seed por BLOCKED em todos os 5 clientes
        foreach ($this->clients as $client) {
            FiscalSnapshot::query()
                ->withoutGlobalScopes()
                ->where('office_id', $this->office->id)
                ->where('client_id', $client->id)
                ->where('is_current', true)
                ->update(['situation' => FiscalSituation::Blocked->value]);
            FiscalCompetence::query()
                ->withoutGlobalScopes()
                ->where('office_id', $this->office->id)
                ->where('client_id', $client->id)
                ->update(['situation' => FiscalSituation::Blocked->value]);
        }

        $this->actingAsOffice($this->admin);

        $response = $this->getJson('/api/v1/fiscal/modules/sitfis/overview')->assertOk();
        $counters = $response->json('data.counters');

        $this->assertSame(6, $response->json('data.total_clients'));
        $this->assertSame(5, $counters['blocked']);
        $this->assertSame(0, $counters['up_to_date']);
        $this->assertSame(0, $counters['processing']);
        $this->assertSame(0, $counters['pending']);
        $this->assertSame(0, $counters['attention']);
        $this->assertSame(0, $counters['error']);
        $this->assertSame(1, $counters['unknown']);
        $this->assertSame(0, $counters['unsupported']);
        $this->assertSame(0, $counters['not_applicable']);
        $this->assertSame(6, array_sum($counters));
    }

    public function test_cliente_ativo_sem_vinculo_fiscal_aparece_por_padrao_como_nao_verificado(): void
    {
        $this->actingAsOffice($this->admin);

        $response = $this->getJson('/api/v1/fiscal/modules/sitfis/clients?q='.urlencode('Orphan Sem Link'))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.legal_name', 'Orphan Sem Link')
            ->assertJsonPath('data.0.situation', FiscalSituation::Unknown->value)
            ->assertJsonPath('data.0.coverage', FiscalCoverage::Unknown->value);

        $clientId = (int) $response->json('data.0.client_id');
        $this->assertFalse(OfficeFiscalCategoryLink::query()->withoutGlobalScopes()
            ->where('office_id', $this->office->id)
            ->where('client_id', $clientId)
            ->exists());
    }

    public function test_nove_estados_coexistem_e_soma_igual_ao_total(): void
    {
        // Seed base tem 5 snapshots + 1 cliente ainda não consultado; adiciona os
        // estados restantes para cobrir os 9 estados canônicos.
        $extra = [
            ['name' => 'Zeta Processando', 'situation' => FiscalSituation::Processing],
            ['name' => 'Eta Bloqueado', 'situation' => FiscalSituation::Blocked],
            ['name' => 'Theta Desconhecido', 'situation' => FiscalSituation::Unknown],
            ['name' => 'Iota Sem Suporte', 'situation' => FiscalSituation::Unsupported],
            ['name' => 'Kappa N/A', 'situation' => FiscalSituation::NotApplicable],
        ];

        foreach ($extra as $i => $row) {
            $client = Client::factory()->forOffice($this->office)->create([
                'legal_name' => $row['name'],
            ]);
            Establishment::factory()->forClient($client)->create();
            $this->linkClient($this->office, $client, $this->sitfisCategory);
            FiscalCompetence::query()->create([
                'office_id' => $this->office->id,
                'client_id' => $client->id,
                'fiscal_category_id' => $this->sitfisCategory->id,
                'period_key' => '2026-06',
                'period_year' => 2026,
                'period_month' => 6,
                'situation' => $row['situation'],
                'coverage' => FiscalCoverage::Full,
                'due_at' => now()->addDays(20 + $i),
            ]);
            $this->createSnapshot(
                $this->office,
                $client,
                'INTEGRA_SITFIS',
                'SITFIS',
                $row['situation'],
                'nine-'.$i,
            );
        }

        $this->actingAsOffice($this->admin);

        $response = $this->getJson('/api/v1/fiscal/modules/sitfis/overview')->assertOk();
        $counters = $response->json('data.counters');
        $total = (int) $response->json('data.total_clients');

        // Seed: 2 UP_TO_DATE + 1 PENDING + 1 ATTENTION + 1 ERROR + 1 UNKNOWN
        // (cliente sem vínculo/consulta) + 5 extras = 11.
        $this->assertSame(11, $total);
        $this->assertSame(2, $counters['up_to_date']);
        $this->assertSame(1, $counters['processing']);
        $this->assertSame(1, $counters['pending']);
        $this->assertSame(1, $counters['attention']);
        $this->assertSame(1, $counters['error']);
        $this->assertSame(1, $counters['blocked']);
        $this->assertSame(2, $counters['unknown']);
        $this->assertSame(1, $counters['unsupported']);
        $this->assertSame(1, $counters['not_applicable']);
        $this->assertSame($total, array_sum($counters));

        // Cada chave canônica presente exatamente uma vez no contrato
        foreach ([
            'up_to_date', 'processing', 'pending', 'attention', 'error',
            'blocked', 'unknown', 'unsupported', 'not_applicable',
        ] as $key) {
            $this->assertArrayHasKey($key, $counters);
            $this->assertIsInt($counters[$key]);
        }
    }

    public function test_clients_paginacao_contrato_e_cnpj_mascarado(): void
    {
        $this->actingAsOffice($this->admin);

        $page1 = $this->getJson('/api/v1/fiscal/modules/sitfis/clients?per_page=2&page=1&sort=legal_name')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 6)
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure([
                'data' => [[
                    'module_key',
                    'client_id',
                    'legal_name',
                    'cnpj_masked',
                    'root_cnpj_masked',
                    'situation',
                    'coverage',
                    'data_origin',
                    'detail',
                    'links',
                ]],
            ]);

        $row = $page1->json('data.0');
        $this->assertSame('sitfis', $row['module_key']);
        $this->assertStringContainsString('*', $row['cnpj_masked']);
        $this->assertStringNotContainsString('vault', strtolower(json_encode($row)));
        $this->assertArrayNotHasKey('pfx', $row);
        $this->assertArrayNotHasKey('private_key', $row);

        // Página 2
        $this->getJson('/api/v1/fiscal/modules/sitfis/clients?per_page=2&page=2')
            ->assertOk()
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonCount(2, 'data');
    }

    public function test_busca_por_cnpj_alfanumerico_e_razao_social(): void
    {
        $this->actingAsOffice($this->admin);

        $target = $this->clients[0];
        $est = Establishment::query()->withoutGlobalScopes()
            ->where('client_id', $target->id)
            ->firstOrFail();

        // CNPJ completo
        $this->getJson('/api/v1/fiscal/modules/sitfis/clients?q='.urlencode($est->cnpj))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.client_id', $target->id);

        // CNPJ mascarado parcial / root
        $this->getJson('/api/v1/fiscal/modules/sitfis/clients?q='.urlencode(substr($est->cnpj, 0, 8)))
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        // Razão social
        $this->getJson('/api/v1/fiscal/modules/sitfis/clients?q='.urlencode('Alpha Fiscal'))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.legal_name', 'Alpha Fiscal LTDA');
    }

    public function test_filtro_situacao_e_competencia_no_sql(): void
    {
        $this->actingAsOffice($this->admin);

        $this->getJson('/api/v1/fiscal/modules/sitfis/clients?situation=ERROR')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.situation', 'ERROR');

        $this->getJson('/api/v1/fiscal/modules/sitfis/clients?competence=2026-06')
            ->assertOk()
            ->assertJsonPath('meta.total', 5);

        $this->getJson('/api/v1/fiscal/modules/sitfis/clients?competence=1999-01')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }

    public function test_filtro_coverage_no_sql(): void
    {
        $this->actingAsOffice($this->admin);

        // Um cliente com cobertura parcial no snapshot corrente
        $target = $this->clients[0];
        FiscalSnapshot::query()
            ->withoutGlobalScopes()
            ->where('office_id', $this->office->id)
            ->where('client_id', $target->id)
            ->where('is_current', true)
            ->update(['coverage' => FiscalCoverage::Partial->value]);

        $this->getJson('/api/v1/fiscal/modules/sitfis/clients?coverage=PARTIAL')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.client_id', $target->id)
            ->assertJsonPath('data.0.coverage', 'PARTIAL');

        $this->getJson('/api/v1/fiscal/modules/sitfis/clients?coverage=FULL')
            ->assertOk()
            ->assertJsonPath('meta.total', 4);

        // Valor inválido é ignorado (sem filtro)
        $this->getJson('/api/v1/fiscal/modules/sitfis/clients?coverage=EVERYTHING')
            ->assertOk()
            ->assertJsonPath('meta.total', 6);
    }

    public function test_modulo_desconhecido_e_desabilitado(): void
    {
        $this->actingAsOffice($this->admin);

        $this->getJson('/api/v1/fiscal/modules/nao_existe/overview')
            ->assertNotFound();

        $this->getJson('/api/v1/fiscal/modules/dashboard/clients')
            ->assertNotFound();

        config(['features.modules.sitfis.enabled' => false]);

        $this->getJson('/api/v1/fiscal/modules/sitfis/overview')
            ->assertForbidden();
    }

    public function test_papeis_viewer_operator_admin_podem_ler(): void
    {
        foreach ([$this->viewer, $this->operator, $this->admin] as $user) {
            $this->actingAsOffice($user);
            $this->getJson('/api/v1/fiscal/modules/sitfis/overview')->assertOk();
            $this->getJson('/api/v1/fiscal/modules/sitfis/clients')->assertOk();
        }
    }

    public function test_isolamento_entre_offices_e_office_id_forjado(): void
    {
        // Mesmo CNPJ no office B com link SITFIS
        $sharedRoot = $this->clients[0]->root_cnpj;
        $clientB = Client::factory()->forOffice($this->otherOffice)->withRoot($sharedRoot)->create([
            'legal_name' => 'SEGREDO OUTRO OFFICE',
        ]);
        Establishment::factory()->forClient($clientB)->create();
        $this->linkClient($this->otherOffice, $clientB, $this->sitfisCategory);
        $this->createSnapshot(
            $this->otherOffice,
            $clientB,
            'INTEGRA_SITFIS',
            'SITFIS',
            FiscalSituation::Error,
            'iso-b',
        );

        $this->actingAsOffice($this->admin);

        $forged = $this->otherOffice->id;

        $overview = $this->getJson('/api/v1/fiscal/modules/sitfis/overview?office_id='.$forged)
            ->assertOk();
        $json = (string) json_encode($overview->json());
        $this->assertStringNotContainsString('SEGREDO OUTRO OFFICE', $json);
        $this->assertSame(6, $overview->json('data.total_clients'));

        $clients = $this->getJson('/api/v1/fiscal/modules/sitfis/clients?office_id='.$forged.'&q='.$sharedRoot)
            ->assertOk();
        $names = collect($clients->json('data'))->pluck('legal_name')->all();
        $this->assertNotContains('SEGREDO OUTRO OFFICE', $names);
        foreach ($clients->json('data') as $row) {
            $this->assertNotSame($clientB->id, $row['client_id']);
        }
    }

    public function test_platform_admin_sem_membership_nao_le_portfolio(): void
    {
        $platform = User::factory()->asPlatformAdmin()->create();
        $this->actingAs($platform);
        app(CurrentOffice::class)->clear();

        $this->getJson('/api/v1/fiscal/modules/sitfis/overview')
            ->assertStatus(409)
            ->assertJsonPath('code', 'office_context_required');

        $this->getJson('/api/v1/fiscal/modules/sitfis/clients')
            ->assertStatus(409)
            ->assertJsonPath('code', 'office_context_required');
    }

    public function test_contagem_de_queries_sem_n_plus_one_grave(): void
    {
        $this->actingAsOffice($this->admin);

        DB::enableQueryLog();
        DB::flushQueryLog();

        $this->getJson('/api/v1/fiscal/modules/sitfis/clients?per_page=5')->assertOk();

        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        // Limite folgado: paginação + snapshots + coverages + findings + pending + establishments
        $this->assertLessThan(
            40,
            $count,
            "Esperado < 40 queries na carteira; obteve {$count} (possível N+1).",
        );
    }

    public function test_data_origin_demo_quando_office_slug_demo(): void
    {
        $demo = Office::factory()->create(['slug' => 'demo', 'name' => 'Demo']);
        $demoAdmin = User::factory()->forOffice($demo, OfficeRole::Admin)->withTwoFactorConfirmed()->create();
        $client = Client::factory()->forOffice($demo)->create();
        Establishment::factory()->forClient($client)->create();
        $this->linkClient($demo, $client, $this->sitfisCategory);

        $this->actingAsOffice($demoAdmin);

        $this->getJson('/api/v1/fiscal/modules/sitfis/overview')
            ->assertOk()
            ->assertJsonPath('data.data_origin', 'DEMO')
            ->assertJsonPath('data.is_synthetic', true);
    }

    public function test_alias_feature_flag_module_route(): void
    {
        $this->actingAsOffice($this->admin);

        // Aliases aceitos: dctfweb_mit → dctfweb
        $this->getJson('/api/v1/fiscal/modules/dctfweb_mit/overview')
            ->assertOk()
            ->assertJsonPath('data.module_key', 'dctfweb');
    }

    private function seedPortfolio(): void
    {
        $situations = [
            ['name' => 'Alpha Fiscal LTDA', 'situation' => FiscalSituation::UpToDate],
            ['name' => 'Beta Contábil', 'situation' => FiscalSituation::UpToDate],
            ['name' => 'Gamma Pendente', 'situation' => FiscalSituation::Pending],
            ['name' => 'Delta Atenção', 'situation' => FiscalSituation::Attention],
            ['name' => 'Epsilon Erro', 'situation' => FiscalSituation::Error],
        ];

        foreach ($situations as $i => $row) {
            $client = Client::factory()->forOffice($this->office)->create([
                'legal_name' => $row['name'],
                'display_name' => null,
            ]);
            Establishment::factory()->forClient($client)->create();
            $this->linkClient($this->office, $client, $this->sitfisCategory);

            FiscalCompetence::query()->create([
                'office_id' => $this->office->id,
                'client_id' => $client->id,
                'fiscal_category_id' => $this->sitfisCategory->id,
                'period_key' => '2026-06',
                'period_year' => 2026,
                'period_month' => 6,
                'situation' => $row['situation'],
                'coverage' => FiscalCoverage::Full,
                'due_at' => now()->addDays(10 + $i),
            ]);

            $this->createSnapshot(
                $this->office,
                $client,
                'INTEGRA_SITFIS',
                'SITFIS',
                $row['situation'],
                'seed-'.$i,
            );

            $this->clients[] = $client;
        }

        // Cliente no mesmo office SEM vínculo SITFIS — entra por padrão como UNKNOWN.
        $orphan = Client::factory()->forOffice($this->office)->create(['legal_name' => 'Orphan Sem Link']);
        Establishment::factory()->forClient($orphan)->create();
    }

    private function linkClient(Office $office, Client $client, FiscalCategory $category): void
    {
        OfficeFiscalCategoryLink::query()->create([
            'office_id' => $office->id,
            'client_id' => $client->id,
            'fiscal_category_id' => $category->id,
            'status' => FiscalLinkStatus::Active,
            'coverage' => FiscalCoverage::Full,
            'activated_at' => now(),
        ]);
    }

    private function createSnapshot(
        Office $office,
        Client $client,
        string $system,
        string $service,
        FiscalSituation $situation,
        string $idempotency,
    ): FiscalSnapshot {
        $run = FiscalMonitoringRun::query()->create([
            'office_id' => $office->id,
            'client_id' => $client->id,
            'system_code' => $system,
            'service_code' => $service,
            'operation_code' => 'MONITOR',
            'trigger' => FiscalTrigger::Manual,
            'idempotency_key' => $idempotency.'-'.$office->id.'-'.$client->id,
            'status' => FiscalRunStatus::Completed,
            'situation' => $situation,
            'coverage' => FiscalCoverage::Full,
        ]);

        return FiscalSnapshot::query()->create([
            'office_id' => $office->id,
            'run_id' => $run->id,
            'client_id' => $client->id,
            'system_code' => $system,
            'service_code' => $service,
            'operation_code' => 'MONITOR',
            'situation' => $situation,
            'coverage' => FiscalCoverage::Full,
            'version' => 1,
            'is_current' => true,
            'normalized' => ['ok' => true],
            'observed_at' => now()->subMinutes(5),
            'created_at' => now(),
        ]);
    }

    private function actingAsOffice(User $user): void
    {
        $this->actingAs($user);
        app(CurrentOffice::class)->clear();
        app(CurrentOffice::class)->resolve($user);
    }
}
