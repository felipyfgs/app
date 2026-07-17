<?php

namespace Tests\Feature\Fiscal\SimplesMei;

use App\Enums\OfficeRole;
use App\Enums\PgmeiDebtState;
use App\Models\Client;
use App\Models\Office;
use App\Models\PgmeiDebtProjection;
use App\Models\User;
use App\Services\Fiscal\SimplesMei\Pgmei\PgmeiDebtProjector;
use App\Services\Fiscal\SimplesMei\Pgmei\PgmeiDividaAtiva24Codec;
use App\Support\CurrentOffice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PgmeiDebtProjectionTest extends TestCase
{
    use RefreshDatabase;

    private Office $office;

    private User $admin;

    private Client $client;

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

        $this->office = Office::factory()->create();
        $this->client = Client::factory()->forOffice($this->office)->create();
        $this->admin = User::factory()
            ->forOffice($this->office, OfficeRole::Admin)
            ->withTwoFactorConfirmed()
            ->create();
        $this->actAs($this->admin);
    }

    #[Test]
    public function project_valid_response_and_idempotent_digest(): void
    {
        $codec = app(PgmeiDividaAtiva24Codec::class);
        $projector = app(PgmeiDebtProjector::class);

        $decoded = $codec->decodeDados([
            [
                'periodoApuracao' => '202601',
                'tributo' => 'INSS',
                'valor' => '10,50',
                'enteFederado' => 'União',
                'situacaoDebito' => 'Enviado à PFN',
            ],
        ], 2026);

        $first = $projector->projectValid($this->office, $this->client, $decoded, null);
        $this->assertTrue($first['created']);
        $this->assertSame(PgmeiDebtState::HasActiveDebt, $first['projection']->debt_state);
        $this->assertSame(1050, (int) $first['projection']->total_cents);
        $this->assertSame(1, (int) $first['projection']->items_count);

        $second = $projector->projectValid($this->office, $this->client, $decoded, null);
        $this->assertFalse($second['created']);
        $this->assertSame($first['observation']->id, $second['observation']->id);
        $this->assertSame(1, PgmeiDebtProjection::query()->where('office_id', $this->office->id)->count());
    }

    #[Test]
    public function empty_list_is_no_active_debt(): void
    {
        $codec = app(PgmeiDividaAtiva24Codec::class);
        $projector = app(PgmeiDebtProjector::class);

        $decoded = $codec->decodeDados([], 2025);
        $result = $projector->projectValid($this->office, $this->client, $decoded, null);

        $this->assertSame(PgmeiDebtState::NoActiveDebt, $result['projection']->debt_state);
        $this->assertSame(0, (int) $result['projection']->total_cents);
    }

    #[Test]
    public function manual_consult_rejects_over_limit_before_runs(): void
    {
        $ids = range(1, 101);
        $this->postJson('/api/v1/fiscal/simples-mei/pgmei/consult', [
            'client_ids' => $ids,
            'year' => 2026,
            'confirmed' => true,
        ])->assertStatus(422);
    }

    #[Test]
    public function history_is_local_only_and_tenant_scoped(): void
    {
        $codec = app(PgmeiDividaAtiva24Codec::class);
        $projector = app(PgmeiDebtProjector::class);
        $decoded = $codec->decodeDados([
            [
                'periodoApuracao' => '202603',
                'tributo' => 'ISS',
                'valor' => '1,00',
                'enteFederado' => 'Município',
                'situacaoDebito' => 'Ativa',
            ],
        ], 2026);
        $projector->projectValid($this->office, $this->client, $decoded, null);

        $this->getJson("/api/v1/fiscal/simples-mei/pgmei/clients/{$this->client->id}/history?year=2026")
            ->assertOk()
            ->assertJsonPath('data.year', 2026)
            ->assertJsonPath('data.provenance.serpro_called', false)
            ->assertJsonPath('data.current.debt_state', 'HAS_ACTIVE_DEBT')
            ->assertJsonPath('data.current.total_cents', 100);
    }

    #[Test]
    public function communication_preview_template_only(): void
    {
        $this->getJson("/api/v1/fiscal/simples-mei/pgmei/clients/{$this->client->id}/communication-preview")
            ->assertOk()
            ->assertJsonPath('data.can_send', false)
            ->assertJsonPath('data.execution_mode', 'TEMPLATE_ONLY')
            ->assertJsonPath('data.automatic_effective', false);
    }

    private function actAs(User $user): void
    {
        $this->actingAs($user);
        app(CurrentOffice::class)->clear();
        app(CurrentOffice::class)->resolve($user);
    }
}
