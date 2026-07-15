<?php

namespace Tests\Feature\Serpro\Usage;

use App\Enums\OfficeRole;
use App\Enums\SerproUsageResult;
use App\Models\Office;
use App\Models\User;
use App\Services\Serpro\Usage\UsageLedgerService;
use App\Services\Serpro\Usage\UsageReserveRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SerproUsageApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_ve_apenas_consumo_do_proprio_office(): void
    {
        $officeA = Office::factory()->create();
        $officeB = Office::factory()->create();
        $userA = User::factory()->forOffice($officeA, OfficeRole::Admin)->withTwoFactorConfirmed()->create();
        $userB = User::factory()->forOffice($officeB, OfficeRole::Admin)->withTwoFactorConfirmed()->create();

        $ledger = app(UsageLedgerService::class);

        $a = $ledger->reserve(new UsageReserveRequest(
            officeId: $officeA->id,
            idempotencyKey: 'api-a',
            systemCode: 'INTEGRA_CONTADOR',
            serviceCode: 'SITFIS',
            operationCode: 'CONSULTAR_SITUACAO',
        ));
        $ledger->finalize($a->reservation, SerproUsageResult::Success);

        $b = $ledger->reserve(new UsageReserveRequest(
            officeId: $officeB->id,
            idempotencyKey: 'api-b',
            systemCode: 'INTEGRA_CONTADOR',
            serviceCode: 'SITFIS',
            operationCode: 'CONSULTAR_SITUACAO',
        ));
        $ledger->finalize($b->reservation, SerproUsageResult::Success);

        $summaryA = $this->actingAs($userA)
            ->getJson('/api/v1/office/serpro-usage')
            ->assertOk()
            ->json('data');

        $this->assertSame($officeA->id, $summaryA['summary']['office_id']);
        $this->assertSame(1, $summaryA['summary']['used_quantity']);
        $this->assertArrayNotHasKey('global_monthly_budget', $summaryA);
        $this->assertArrayNotHasKey('by_tenant', $summaryA);

        $entriesA = $this->actingAs($userA)
            ->getJson('/api/v1/office/serpro-usage/entries')
            ->assertOk()
            ->json('data');

        $this->assertCount(1, $entriesA);
        $this->assertSame($officeA->id, $entriesA[0]['office_id']);
        $this->assertArrayNotHasKey('price_version_id', $entriesA[0]);

        // Office B não vê dados de A
        $entriesB = $this->actingAs($userB)
            ->getJson('/api/v1/office/serpro-usage/entries')
            ->assertOk()
            ->json('data');
        $this->assertCount(1, $entriesB);
        $this->assertSame($officeB->id, $entriesB[0]['office_id']);
    }

    public function test_platform_admin_consolida_e_concilia(): void
    {
        $office = Office::factory()->create();
        $admin = User::factory()->asPlatformAdmin()->create();
        $tenantUser = User::factory()->forOffice($office, OfficeRole::Admin)->withTwoFactorConfirmed()->create();

        $ledger = app(UsageLedgerService::class);
        $o = $ledger->reserve(new UsageReserveRequest(
            officeId: $office->id,
            idempotencyKey: 'plat-1',
            systemCode: 'INTEGRA_CONTADOR',
            serviceCode: 'SITFIS',
            operationCode: 'CONSULTAR_SITUACAO',
        ));
        $entry = $ledger->finalize($o->reservation, SerproUsageResult::Success);

        // Tenant não acessa platform
        $this->actingAs($tenantUser)
            ->getJson('/api/v1/platform/serpro-usage/consolidation')
            ->assertForbidden();

        $consol = $this->actingAs($admin)
            ->getJson('/api/v1/platform/serpro-usage/consolidation?recompute=1')
            ->assertOk()
            ->json('data');

        $this->assertArrayHasKey('global_aggregates', $consol);
        $this->assertArrayHasKey('by_tenant', $consol);
        $this->assertArrayHasKey('policy', $consol);
        $this->assertTrue($consol['policy']['shadow_mode']);
        $this->assertGreaterThanOrEqual(1, count($consol['by_tenant']));

        $official = (int) $entry->estimated_cost_micros + 10_000;
        $this->actingAs($admin)
            ->postJson('/api/v1/platform/serpro-usage/reconciliations', [
                'year' => (int) now()->year,
                'month' => (int) now()->month,
                'official_total_cost_micros' => $official,
                'official_reference' => 'FAT-API-1',
                'difference_cause' => 'PENDING_REVIEW',
                'adjustments' => [[
                    'office_id' => $office->id,
                    'amount_micros' => 10_000,
                    'reason' => 'AJUSTE_MANUAL',
                ]],
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'ADJUSTED')
            ->assertJsonPath('data.difference_micros', 10_000);

        // Ledger não reescrito
        $this->assertSame($entry->estimated_cost_micros, $entry->fresh()->estimated_cost_micros);
    }

    public function test_viewer_pode_ler_consumo_do_office(): void
    {
        $office = Office::factory()->create();
        $viewer = User::factory()->forOffice($office, OfficeRole::Viewer)->create();

        $this->actingAs($viewer)
            ->getJson('/api/v1/office/serpro-usage')
            ->assertOk()
            ->assertJsonPath('data.summary.office_id', $office->id);
    }
}
