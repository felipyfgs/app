<?php

namespace Tests\Feature\Fiscal\SimplesMei;

use App\Enums\OfficeRole;
use App\Jobs\Fiscal\ExecuteFiscalMonitoringRunJob;
use App\Models\Client;
use App\Models\Office;
use App\Models\User;
use App\Support\CurrentOffice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PgdasdDocumentCollectionControllerTest extends TestCase
{
    use RefreshDatabase;

    private const BASE = '/api/v1/fiscal/simples-mei/pgdasd';

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
            'fiscal_monitoring.kill_switch' => false,
        ]);

        $this->office = Office::factory()->create();
        $this->client = Client::factory()->forOffice($this->office)->create();
        $this->admin = User::factory()->forOffice($this->office, OfficeRole::Admin)->create();
        $this->actAsOffice($this->admin);
    }

    #[Test]
    public function coleta_exige_confirmacao_explicita_antes_de_criar_execucao(): void
    {
        Queue::fake();
        $url = self::BASE."/clients/{$this->client->id}/documents";

        $this->postJson($url, ['period_key' => '2026-06'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('confirmed');
        $this->assertDatabaseCount('fiscal_monitoring_runs', 0);
        Queue::assertNothingPushed();

        $this->postJson($url, ['period_key' => '2026-06', 'confirmed' => true])
            ->assertCreated()
            ->assertJsonPath('serpro_call', 'QUEUED');
        $this->assertDatabaseHas('fiscal_monitoring_runs', [
            'office_id' => $this->office->id,
            'client_id' => $this->client->id,
            'operation_code' => 'CONSULTAR_ULTIMA_DECLARACAO_RECIBO',
        ]);
        Queue::assertPushed(ExecuteFiscalMonitoringRunJob::class);
    }

    #[Test]
    public function coleta_e_tenant_scoped_rejeita_office_id_e_bloqueia_viewer(): void
    {
        Queue::fake();
        $url = self::BASE."/clients/{$this->client->id}/documents";

        $this->postJson($url, [
            'period_key' => '2026-06',
            'confirmed' => true,
            'filters' => ['office_id' => 999],
        ])->assertUnprocessable()->assertJsonPath('code', 'CLIENT_OFFICE_ID_REJECTED');
        Queue::assertNothingPushed();

        $viewer = User::factory()->forOffice($this->office, OfficeRole::Viewer)->create();
        $this->actAsOffice($viewer);
        $this->postJson($url, ['period_key' => '2026-06', 'confirmed' => true])->assertForbidden();
        Queue::assertNothingPushed();
    }

    #[Test]
    public function cliente_de_outro_escritorio_retorna_404_sem_enfileirar(): void
    {
        Queue::fake();
        $foreign = Client::factory()->forOffice(Office::factory()->create())->create();

        $this->postJson(self::BASE."/clients/{$foreign->id}/documents", [
            'period_key' => '2026-06',
            'confirmed' => true,
        ])->assertNotFound();
        Queue::assertNothingPushed();
        $this->assertDatabaseCount('fiscal_monitoring_runs', 0);
    }

    private function actAsOffice(User $user): void
    {
        $this->actingAs($user);
        app(CurrentOffice::class)->clear();
        app(CurrentOffice::class)->resolve($user);
    }
}
