<?php

namespace Tests\Feature\Demo;

use App\Contracts\SecureObjectStore;
use App\Enums\FiscalSituation;
use App\Enums\SecureObjectPurpose;
use App\Enums\SyncCursorStatus;
use App\Models\Client;
use App\Models\DctfwebDeclaration;
use App\Models\FgtsCompetenceStatus;
use App\Models\FiscalCompetence;
use App\Models\FiscalEvidenceArtifact;
use App\Models\FiscalFinding;
use App\Models\FiscalGuideStub;
use App\Models\FiscalMonitoringRun;
use App\Models\FiscalPendingItem;
use App\Models\FiscalSnapshot;
use App\Models\MailboxMessage;
use App\Models\Office;
use App\Models\OfficeFiscalCategoryLink;
use App\Models\SerproApiUsageEntry;
use App\Models\SerproContract;
use App\Models\SyncCursor;
use App\Models\TaxGuide;
use App\Models\TaxGuideVersion;
use App\Models\TaxInstallmentOrder;
use App\Models\TaxInstallmentParcel;
use App\Models\TaxObligationProjection;
use App\Services\Fiscal\Demo\DemoEnvironmentGuard;
use App\Services\Fiscal\Demo\FiscalDataOriginResolver;
use Database\Seeders\FiscalMonitoringDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use LogicException;
use Tests\TestCase;

class FiscalMonitoringDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    private Office $demo;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'fiscal_demo.enabled' => true,
            'fiscal_demo.office_slug' => 'demo',
            'fiscal_demo.sentinel_office_slug' => 'demo-sentinel',
            'fiscal_demo.anchor_at' => '2026-06-15T12:00:00-03:00',
            'fiscal_demo.manifest_version' => '1.0.0',
        ]);

        $this->demo = Office::factory()->create([
            'slug' => 'demo',
            'name' => 'Escritório Demo',
        ]);
    }

    public function test_seeder_popula_clientes_e_situacoes(): void
    {
        $this->seed(FiscalMonitoringDemoSeeder::class);

        $marker = app(DemoEnvironmentGuard::class)->fixtureMarker();
        $clients = Client::query()->withoutGlobalScopes()
            ->where('office_id', $this->demo->id)
            ->where('notes', 'like', '%'.$marker.'%')
            ->get();

        $this->assertGreaterThanOrEqual(16, $clients->count());
        $this->assertLessThanOrEqual(20, $clients->count());

        $situations = FiscalCompetence::query()->withoutGlobalScopes()
            ->where('office_id', $this->demo->id)
            ->pluck('situation')
            ->map(fn ($s) => $s instanceof FiscalSituation ? $s->value : (string) $s)
            ->unique()
            ->all();

        foreach (FiscalSituation::cases() as $case) {
            $this->assertContains(
                $case->value,
                $situations,
                "Situação {$case->value} ausente no dataset demo",
            );
        }

        $this->assertGreaterThan(0, FiscalMonitoringRun::query()->withoutGlobalScopes()
            ->where('office_id', $this->demo->id)->count());
        $this->assertGreaterThan(0, FiscalSnapshot::query()->withoutGlobalScopes()
            ->where('office_id', $this->demo->id)->count());
        $this->assertGreaterThan(0, OfficeFiscalCategoryLink::query()->withoutGlobalScopes()
            ->where('office_id', $this->demo->id)->count());
        $this->assertGreaterThan(0, FiscalPendingItem::query()->withoutGlobalScopes()
            ->where('office_id', $this->demo->id)->count());
        $this->assertGreaterThan(0, FiscalFinding::query()->withoutGlobalScopes()
            ->where('office_id', $this->demo->id)->count());
    }

    public function test_idempotencia_mesma_versao(): void
    {
        $this->seed(FiscalMonitoringDemoSeeder::class);
        $marker = app(DemoEnvironmentGuard::class)->fixtureMarker();

        $clients1 = Client::query()->withoutGlobalScopes()
            ->where('office_id', $this->demo->id)
            ->where('notes', 'like', '%'.$marker.'%')
            ->count();
        $runs1 = FiscalMonitoringRun::query()->withoutGlobalScopes()
            ->where('office_id', $this->demo->id)
            ->where('correlation_id', 'like', 'DEMO_%')
            ->count();
        $guides1 = TaxGuide::query()->withoutGlobalScopes()
            ->where('office_id', $this->demo->id)->count();

        $this->seed(FiscalMonitoringDemoSeeder::class);

        $clients2 = Client::query()->withoutGlobalScopes()
            ->where('office_id', $this->demo->id)
            ->where('notes', 'like', '%'.$marker.'%')
            ->count();
        $runs2 = FiscalMonitoringRun::query()->withoutGlobalScopes()
            ->where('office_id', $this->demo->id)
            ->where('correlation_id', 'like', 'DEMO_%')
            ->count();
        $guides2 = TaxGuide::query()->withoutGlobalScopes()
            ->where('office_id', $this->demo->id)->count();

        $this->assertSame($clients1, $clients2);
        $this->assertSame($runs1, $runs2);
        $this->assertSame($guides1, $guides2);
    }

    public function test_nao_toca_outro_tenant(): void
    {
        $other = Office::factory()->create(['slug' => 'outro-escritorio']);
        $foreign = Client::factory()->forOffice($other)->create([
            'legal_name' => 'Cliente Real Outro Office',
            'notes' => 'nao-demo',
        ]);

        $this->seed(FiscalMonitoringDemoSeeder::class);

        $this->assertDatabaseHas('clients', [
            'id' => $foreign->id,
            'office_id' => $other->id,
            'legal_name' => 'Cliente Real Outro Office',
        ]);
        $this->assertSame(0, FiscalMonitoringRun::query()->withoutGlobalScopes()
            ->where('office_id', $other->id)->count());
    }

    public function test_isolamento_cnpj_sentinela(): void
    {
        $this->seed(FiscalMonitoringDemoSeeder::class);

        $sentinel = Office::query()->where('slug', 'demo-sentinel')->first();
        $this->assertNotNull($sentinel);

        $marker = app(DemoEnvironmentGuard::class)->fixtureMarker();
        $demoClient = Client::query()->withoutGlobalScopes()
            ->where('office_id', $this->demo->id)
            ->where('notes', 'like', '%C01%')
            ->first();
        $sentinelClient = Client::query()->withoutGlobalScopes()
            ->where('office_id', $sentinel->id)
            ->where('notes', 'like', '%'.$marker.'%')
            ->first();

        $this->assertNotNull($demoClient);
        $this->assertNotNull($sentinelClient);
        $this->assertSame($demoClient->root_cnpj, $sentinelClient->root_cnpj);
        $this->assertNotSame($demoClient->office_id, $sentinelClient->office_id);

        // Carteira demo não inclui sentinela
        $demoIds = Client::query()->withoutGlobalScopes()
            ->where('office_id', $this->demo->id)
            ->pluck('id');
        $this->assertFalse($demoIds->contains($sentinelClient->id));
    }

    public function test_modulos_populados_coerentes(): void
    {
        $this->seed(FiscalMonitoringDemoSeeder::class);

        $this->assertGreaterThan(0, FiscalGuideStub::query()->withoutGlobalScopes()
            ->where('office_id', $this->demo->id)->count());
        $this->assertGreaterThan(0, DctfwebDeclaration::query()->withoutGlobalScopes()
            ->where('office_id', $this->demo->id)->count());
        $this->assertGreaterThan(0, TaxInstallmentOrder::query()->withoutGlobalScopes()
            ->where('office_id', $this->demo->id)->count());
        $this->assertGreaterThan(0, TaxInstallmentParcel::query()->withoutGlobalScopes()
            ->where('office_id', $this->demo->id)->count());
        $this->assertGreaterThan(0, MailboxMessage::query()->withoutGlobalScopes()
            ->where('office_id', $this->demo->id)->count());
        $this->assertGreaterThan(0, TaxObligationProjection::query()->withoutGlobalScopes()
            ->where('office_id', $this->demo->id)->count());
        $this->assertGreaterThan(0, TaxGuide::query()->withoutGlobalScopes()
            ->where('office_id', $this->demo->id)->count());
        $this->assertGreaterThan(0, FgtsCompetenceStatus::query()->withoutGlobalScopes()
            ->where('office_id', $this->demo->id)->count());
        $this->assertGreaterThan(0, SerproApiUsageEntry::query()->withoutGlobalScopes()
            ->where('office_id', $this->demo->id)
            ->where('correlation_id', 'like', 'DEMO_%')
            ->count());

        // Sem contrato SERPRO sintético
        $this->assertSame(0, SerproContract::query()->count());

        // FGTS guia/pag UNSUPPORTED
        $fgts = FgtsCompetenceStatus::query()->withoutGlobalScopes()
            ->where('office_id', $this->demo->id)->get();
        foreach ($fgts as $row) {
            $this->assertSame('UNSUPPORTED', $row->guide_status?->value ?? $row->guide_status);
            $this->assertSame('UNSUPPORTED', $row->payment_status?->value ?? $row->payment_status);
        }
    }

    public function test_cursor_blocked_decode_preserva_nsu(): void
    {
        $this->seed(FiscalMonitoringDemoSeeder::class);

        $cursor = SyncCursor::query()->withoutGlobalScopes()
            ->where('office_id', $this->demo->id)
            ->where('status', SyncCursorStatus::Blocked)
            ->where('consecutive_decode_failures', '>=', 5)
            ->first();

        $this->assertNotNull($cursor);
        $this->assertSame(42, (int) $cursor->last_nsu);
        $this->assertNull($cursor->next_sync_at);
    }

    public function test_evidencias_no_cofre_sem_expor_em_public_array(): void
    {
        $this->seed(FiscalMonitoringDemoSeeder::class);

        $artifact = FiscalEvidenceArtifact::query()->withoutGlobalScopes()
            ->where('office_id', $this->demo->id)
            ->first();
        $this->assertNotNull($artifact);
        $this->assertNotEmpty($artifact->vault_object_id);

        $public = $artifact->toPublicArray();
        $this->assertArrayNotHasKey('vault_object_id', $public);

        $guideVer = TaxGuideVersion::query()->withoutGlobalScopes()
            ->where('office_id', $this->demo->id)
            ->whereNotNull('vault_object_id')
            ->first();
        $this->assertNotNull($guideVer);

        // Conteúdo com marca d'água
        $bytes = app(SecureObjectStore::class)->get(
            $artifact->vault_object_id,
            SecureObjectPurpose::FiscalEvidence->aadBase([
                'office_id' => (int) $artifact->office_id,
                'sha256' => $artifact->content_sha256,
                'demo' => true,
            ]),
        );
        $this->assertStringContainsString('DEMONSTRA', $bytes);
        $this->assertStringNotContainsString('BEGIN PRIVATE KEY', $bytes);
        $this->assertStringNotContainsString('Consumer Secret', $bytes);
    }

    public function test_correlation_id_prefix_demo(): void
    {
        $this->seed(FiscalMonitoringDemoSeeder::class);

        $run = FiscalMonitoringRun::query()->withoutGlobalScopes()
            ->where('office_id', $this->demo->id)
            ->where('correlation_id', 'like', 'DEMO_%')
            ->first();
        $this->assertNotNull($run);
    }

    public function test_relogio_deterministico_da_ancora(): void
    {
        $this->seed(FiscalMonitoringDemoSeeder::class);

        $comp = FiscalCompetence::query()->withoutGlobalScopes()
            ->where('office_id', $this->demo->id)
            ->where('period_key', '2026-05')
            ->first();
        $this->assertNotNull($comp, 'Competência relativa à âncora 2026-06 deve incluir 2026-05');
    }

    public function test_comando_fiscal_demo_seed(): void
    {
        $exit = Artisan::call('fiscal:demo-seed');
        $this->assertSame(0, $exit);

        $marker = app(DemoEnvironmentGuard::class)->fixtureMarker();
        $this->assertGreaterThanOrEqual(16, Client::query()->withoutGlobalScopes()
            ->where('office_id', $this->demo->id)
            ->where('notes', 'like', '%'.$marker.'%')
            ->count());
    }

    public function test_office_nao_autorizado_recusa(): void
    {
        $other = Office::factory()->create(['slug' => 'nao-demo']);

        $this->expectException(LogicException::class);
        app(DemoEnvironmentGuard::class)->assertCanSeed($other);
    }

    public function test_deep_link_coerencia_modulos(): void
    {
        $this->seed(FiscalMonitoringDemoSeeder::class);

        // Cada order de parcelamento tem parcelas no mesmo office/client
        $orders = TaxInstallmentOrder::query()->withoutGlobalScopes()
            ->where('office_id', $this->demo->id)->get();
        foreach ($orders as $order) {
            $parcels = TaxInstallmentParcel::query()->withoutGlobalScopes()
                ->where('order_id', $order->id)->get();
            $this->assertNotEmpty($parcels);
            foreach ($parcels as $p) {
                $this->assertSame($order->office_id, $p->office_id);
                $this->assertSame($order->client_id, $p->client_id);
            }
        }

        // Mensagens mailbox apontam para client do mesmo office
        $msgs = MailboxMessage::query()->withoutGlobalScopes()
            ->where('office_id', $this->demo->id)->get();
        foreach ($msgs as $msg) {
            $client = Client::query()->withoutGlobalScopes()->find($msg->client_id);
            $this->assertNotNull($client);
            $this->assertSame($this->demo->id, $client->office_id);
        }

        // Guias com versão corrente no mesmo office
        $guides = TaxGuide::query()->withoutGlobalScopes()
            ->where('office_id', $this->demo->id)->get();
        foreach ($guides as $g) {
            $this->assertNotNull($g->current_version_id);
            $ver = TaxGuideVersion::query()->withoutGlobalScopes()->find($g->current_version_id);
            $this->assertNotNull($ver);
            $this->assertSame($g->office_id, $ver->office_id);
            $this->assertSame($g->id, $ver->tax_guide_id);
        }

        // Declarações com client do office
        $decls = TaxObligationProjection::query()->withoutGlobalScopes()
            ->where('office_id', $this->demo->id)->limit(5)->get();
        foreach ($decls as $d) {
            $this->assertSame(
                $this->demo->id,
                (int) Client::query()->withoutGlobalScopes()->findOrFail($d->client_id)->office_id,
            );
        }

        // Runs com snapshot navegável
        $snap = FiscalSnapshot::query()->withoutGlobalScopes()
            ->where('office_id', $this->demo->id)
            ->where('is_current', true)
            ->first();
        $this->assertNotNull($snap);
        $run = FiscalMonitoringRun::query()->withoutGlobalScopes()->find($snap->run_id);
        $this->assertNotNull($run);
        $this->assertSame($snap->office_id, $run->office_id);
        $this->assertSame($snap->client_id, $run->client_id);
    }

    public function test_data_origin_resolver_no_office_demo(): void
    {
        $this->seed(FiscalMonitoringDemoSeeder::class);
        $resolver = app(FiscalDataOriginResolver::class);
        $meta = $resolver->toPublicMeta($this->demo, true);
        $this->assertSame('DEMO', $meta['origin']);
    }

    public function test_production_nao_carrega_seeder(): void
    {
        $this->app['env'] = 'production';

        $this->expectException(LogicException::class);
        $this->seed(FiscalMonitoringDemoSeeder::class);
    }
}
