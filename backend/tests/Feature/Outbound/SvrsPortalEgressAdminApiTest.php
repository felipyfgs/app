<?php

namespace Tests\Feature\Outbound;

use App\Contracts\SvrsPortalEgressGovernor;
use App\Enums\OfficeRole;
use App\Enums\SvrsEgressBlockCause;
use App\Models\Office;
use App\Models\User;
use App\Support\CurrentOffice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SvrsPortalEgressAdminApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config(['sefaz.svrs_portal_egress.cohort_id' => 'test-admin-api']);
    }

    public function test_viewer_ve_saude_coorte(): void
    {
        $office = Office::factory()->create();
        $viewer = User::factory()->forOffice($office, OfficeRole::Viewer)->withTwoFactorConfirmed()->create();
        $this->actingAs($viewer);
        app(CurrentOffice::class)->resolve($viewer);

        $this->getJson('/api/v1/outbound/svrs-portal/egress')
            ->assertOk()
            ->assertJsonPath('data.budgets_are_preventive', true)
            ->assertJsonStructure(['data' => ['cohort_id', 'state', 'exchanges_hour_remaining']]);
    }

    public function test_operator_nao_estende_cooldown(): void
    {
        $office = Office::factory()->create();
        $op = User::factory()->forOffice($office, OfficeRole::Operator)->withTwoFactorConfirmed()->create();
        $this->actingAs($op);
        app(CurrentOffice::class)->resolve($op);

        $this->postJson('/api/v1/outbound/svrs-portal/egress/extend-cooldown', [
            'additional_seconds' => 3600,
            'reason' => 'teste',
        ])->assertForbidden();
    }

    public function test_admin_estende_cooldown_e_recusa_elevacao(): void
    {
        $office = Office::factory()->create();
        $admin = User::factory()->forOffice($office, OfficeRole::Admin)->withTwoFactorConfirmed()->create();
        $this->actingAs($admin);
        app(CurrentOffice::class)->resolve($admin);

        $gov = app(SvrsPortalEgressGovernor::class);
        $gov->openBreaker(SvrsEgressBlockCause::MultipleQueries);

        $this->postJson('/api/v1/outbound/svrs-portal/egress/extend-cooldown', [
            'additional_seconds' => 3600,
            'reason' => 'aguardar ofício SVRS',
        ])->assertOk()
            ->assertJsonPath('data.state', 'open');

        $this->postJson('/api/v1/outbound/svrs-portal/egress/elevate-budget', [
            'max_exchanges_per_hour' => 999,
        ])->assertStatus(422)
            ->assertJsonPath('data.allowed', false);
    }
}
