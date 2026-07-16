<?php

namespace Tests\Feature\Platform;

use App\Enums\OfficeAccessMode;
use App\Enums\OfficeRole;
use App\Enums\PlatformRole;
use App\Models\Office;
use App\Models\OfficeMembership;
use App\Models\PlatformMembership;
use App\Models\User;
use App\Support\CurrentOffice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PlatformDefaultOfficeTest extends TestCase
{
    use RefreshDatabase;

    private function enablePrivileged(): void
    {
        config([
            'features.kill_switch' => false,
            'features.platform_privileged_context.enabled' => true,
        ]);
    }

    public function test_migration_adiciona_default_office_id(): void
    {
        $this->assertTrue(Schema::hasColumn('platform_memberships', 'default_office_id'));
    }

    public function test_backfill_usa_office_ativo_mais_antigo_sem_criar_membership(): void
    {
        $older = Office::factory()->create(['is_active' => true, 'name' => 'Alpha']);
        $newer = Office::factory()->create(['is_active' => true, 'name' => 'Beta']);
        $inactive = Office::factory()->create(['is_active' => false, 'name' => 'Inativo']);

        $admin = User::factory()->create();
        PlatformMembership::query()->create([
            'user_id' => $admin->id,
            'role' => PlatformRole::PlatformAdmin->value,
            'is_active' => true,
            'default_office_id' => null,
        ]);

        // Simula backfill determinístico (mesma regra da migration).
        $defaultId = Office::query()->where('is_active', true)->orderBy('id')->value('id');
        PlatformMembership::query()
            ->whereNull('default_office_id')
            ->update(['default_office_id' => $defaultId]);

        $pm = PlatformMembership::query()->where('user_id', $admin->id)->firstOrFail();
        $this->assertSame($older->id, (int) $pm->default_office_id);
        $this->assertNotSame($newer->id, (int) $pm->default_office_id);
        $this->assertNotSame($inactive->id, (int) $pm->default_office_id);
        $this->assertSame(0, OfficeMembership::query()->where('user_id', $admin->id)->count());
    }

    public function test_default_office_inativo_nao_resolve_contexto(): void
    {
        $this->enablePrivileged();

        $inactive = Office::factory()->create(['is_active' => false]);
        $admin = User::factory()->asPlatformAdmin($inactive->id)->create();

        $this->actingAs($admin);
        app(CurrentOffice::class)->clear();

        $this->assertNull(app(CurrentOffice::class)->resolve($admin));
        $this->assertSame(
            CurrentOffice::CONTEXT_STATUS_REQUIRED,
            app(CurrentOffice::class)->contextStatus(),
        );

        $this->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.current_office', null)
            ->assertJsonPath('data.context_status', 'office_context_required');

        $this->getJson('/api/v1/clients')
            ->assertStatus(409)
            ->assertJsonPath('code', 'office_context_required');

        // Rotas globais continuam acessíveis
        $this->getJson('/api/v1/platform/offices')->assertOk();
    }

    public function test_selecao_persiste_default_office_id(): void
    {
        $this->enablePrivileged();

        $officeA = Office::factory()->create();
        $officeB = Office::factory()->create();
        $admin = User::factory()->asPlatformAdmin($officeA->id)->create([
            'selected_office_id' => null,
        ]);

        $this->actingAs($admin)
            ->postJson('/api/v1/platform/offices/select', ['office_id' => $officeB->id])
            ->assertOk()
            ->assertJsonPath('data.default_office_id', $officeB->id);

        $pm = PlatformMembership::query()->where('user_id', $admin->id)->firstOrFail();
        $this->assertSame($officeB->id, (int) $pm->default_office_id);
        $this->assertNull($admin->fresh()->selected_office_id);
        $this->assertSame(0, OfficeMembership::query()->where('user_id', $admin->id)->count());
    }

    public function test_login_resolve_default_sem_selecao_de_sessao(): void
    {
        $this->enablePrivileged();

        $office = Office::factory()->create(['name' => 'Padrão']);
        $admin = User::factory()->asPlatformAdmin($office->id)->create();

        $this->actingAs($admin);
        app(CurrentOffice::class)->clear();

        $resolved = app(CurrentOffice::class)->resolve($admin);
        $this->assertNotNull($resolved);
        $this->assertSame($office->id, $resolved->id);
        $this->assertTrue(app(CurrentOffice::class)->isPlatformPrivileged());
        $this->assertSame(OfficeAccessMode::PlatformPrivileged, app(CurrentOffice::class)->accessMode());
        $this->assertNull(app(CurrentOffice::class)->realMembership());
    }

    public function test_conta_dual_preserva_real_membership(): void
    {
        $this->enablePrivileged();

        $office = Office::factory()->create();
        $admin = User::factory()
            ->asPlatformAdmin($office->id)
            ->forOffice($office, OfficeRole::Operator)
            ->create();

        $this->actingAs($admin)
            ->postJson('/api/v1/platform/offices/select', ['office_id' => $office->id])
            ->assertOk();

        app(CurrentOffice::class)->clear();
        $this->actingAs($admin)
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.access_mode', OfficeAccessMode::PlatformPrivileged->value)
            ->assertJsonPath('data.role', OfficeRole::Admin->value)
            ->assertJsonPath('data.real_office_role', OfficeRole::Operator->value)
            ->assertJsonPath('data.has_real_membership', true);
    }

    public function test_platform_offices_envelope_canonico(): void
    {
        $this->enablePrivileged();

        $active = Office::factory()->create(['is_active' => true]);
        $inactive = Office::factory()->create(['is_active' => false]);
        $admin = User::factory()->asPlatformAdmin($active->id)->create();

        $this->actingAs($admin)
            ->getJson('/api/v1/platform/offices')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'offices' => [
                        ['id', 'name', 'slug', 'is_active', 'status', 'selectable'],
                    ],
                    'selected_office_id',
                    'default_office_id',
                ],
            ])
            ->assertJsonPath('data.default_office_id', $active->id)
            ->assertJsonFragment(['id' => $inactive->id, 'selectable' => false])
            ->assertJsonFragment(['id' => $active->id, 'selectable' => true]);
    }

    public function test_office_id_client_e_ignorado_em_tenant(): void
    {
        $this->enablePrivileged();

        $officeA = Office::factory()->create();
        $officeB = Office::factory()->create();
        $admin = User::factory()->asPlatformAdmin($officeA->id)->create();

        $this->actingAs($admin)
            ->postJson('/api/v1/platform/offices/select', ['office_id' => $officeA->id])
            ->assertOk();

        app(CurrentOffice::class)->clear();

        // body office_id de outro office deve ser removido — contexto A prevalece
        $this->actingAs($admin)
            ->getJson('/api/v1/me?office_id='.$officeB->id)
            ->assertOk()
            ->assertJsonPath('data.office.id', $officeA->id);
    }
}
