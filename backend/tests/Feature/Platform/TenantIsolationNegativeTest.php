<?php

namespace Tests\Feature\Platform;

use App\Enums\OfficeRole;
use App\Models\Client;
use App\Models\Office;
use App\Models\User;
use App\Support\CurrentOffice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Testes negativos de isolamento multi-tenant.
 *
 * Checklist ampliado (inventário — cobrir incrementalmente):
 * - [x] Dois offices com mesmo root_cnpj / legal identity: queries filtradas por office_id
 * - [x] API show de client de outro office → 404
 * - [x] office_id forjado em query/body ignorado
 * - [ ] Jobs com office_id no payload revalidam membership/subscription antes de chamada externa
 * - [ ] Cache keys incluem office_id
 * - [ ] Locks Redis incluem office_id
 * - [ ] Storage paths / vault object ids isolados por office
 * - [ ] Exports não vazam bytes de outro tenant
 * - [ ] Métricas/labels sem CNPJ completo cruzado
 */
class TenantIsolationNegativeTest extends TestCase
{
    use RefreshDatabase;

    public function test_mesmo_cnpj_raiz_em_dois_offices_nao_vaza_na_listagem(): void
    {
        $root = '11222333';
        $officeA = Office::factory()->create();
        $officeB = Office::factory()->create();

        $clientA = Client::factory()->forOffice($officeA)->create([
            'root_cnpj' => $root,
            'legal_name' => 'Cliente Office A',
        ]);
        $clientB = Client::factory()->forOffice($officeB)->create([
            'root_cnpj' => $root,
            'legal_name' => 'Cliente Office B',
        ]);

        $userA = User::factory()->forOffice($officeA, OfficeRole::Admin)->withTwoFactorConfirmed()->create();

        $this->actingAs($userA);
        app(CurrentOffice::class)->clear();

        $response = $this->getJson('/api/v1/clients')->assertOk();

        $names = collect($response->json('data'))->pluck('legal_name')->all();
        // Algumas listagens usam data.items — aceitar ambas
        if ($names === [] && is_array($response->json('data.items'))) {
            $names = collect($response->json('data.items'))->pluck('legal_name')->all();
        }

        $this->assertContains('Cliente Office A', $names);
        $this->assertNotContains('Cliente Office B', $names);

        // Query direta com scope
        app(CurrentOffice::class)->resolve($userA);
        $scoped = Client::query()->where('root_cnpj', $root)->get();
        $this->assertCount(1, $scoped);
        $this->assertSame($clientA->id, $scoped->first()->id);
        $this->assertNotSame($clientB->id, $scoped->first()->id);
    }

    public function test_show_client_de_outro_office_retorna_404(): void
    {
        $officeA = Office::factory()->create();
        $officeB = Office::factory()->create();
        $clientB = Client::factory()->forOffice($officeB)->create();

        $userA = User::factory()->forOffice($officeA, OfficeRole::Admin)->withTwoFactorConfirmed()->create();

        $this->actingAs($userA)
            ->getJson('/api/v1/clients/'.$clientB->id)
            ->assertNotFound();
    }

    public function test_usuario_de_a_nao_troca_para_b_sem_membership_mesmo_com_cnpj_igual(): void
    {
        $root = '44555666';
        $officeA = Office::factory()->create();
        $officeB = Office::factory()->create();
        Client::factory()->forOffice($officeA)->create(['root_cnpj' => $root]);
        Client::factory()->forOffice($officeB)->create(['root_cnpj' => $root]);

        $userA = User::factory()->forOffice($officeA, OfficeRole::Operator)->withTwoFactorConfirmed()->create();

        $this->actingAs($userA)
            ->postJson('/api/v1/tenants/switch', ['office_id' => $officeB->id])
            ->assertNotFound();

        app(CurrentOffice::class)->clear();
        $this->assertSame($officeA->id, app(CurrentOffice::class)->resolve($userA)?->id);
    }

    public function test_platform_memberships_nao_tem_office_id(): void
    {
        $this->assertFalse(
            \Illuminate\Support\Facades\Schema::hasColumn('platform_memberships', 'office_id'),
            'platform_memberships deve ser global (sem office_id)',
        );
        $this->assertTrue(
            \Illuminate\Support\Facades\Schema::hasColumn('office_subscriptions', 'office_id'),
            'office_subscriptions deve ser tenant-scoped (com office_id)',
        );
    }
}
