<?php

namespace Tests\Feature\Platform;

use App\Enums\OfficeRole;
use App\Models\AuditLog;
use App\Models\Office;
use App\Models\User;
use App\Support\CurrentOffice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantSwitchTest extends TestCase
{
    use RefreshDatabase;

    public function test_usuario_com_dois_escritorios_troca_tenant(): void
    {
        $officeA = Office::factory()->create(['name' => 'Alpha']);
        $officeB = Office::factory()->create(['name' => 'Beta']);

        $user = User::factory()->withTwoFactorConfirmed()->create();
        $officeA->users()->attach($user->id, ['role' => OfficeRole::Admin->value, 'is_active' => true]);
        $officeB->users()->attach($user->id, ['role' => OfficeRole::Operator->value, 'is_active' => true]);

        $this->actingAs($user);

        // Default: primeira membership (orderBy id) = A
        $this->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.office.id', $officeA->id)
            ->assertJsonPath('data.role', 'ADMIN');

        $this->postJson('/api/v1/tenants/switch', ['office_id' => $officeB->id])
            ->assertOk()
            ->assertJsonPath('data.office.id', $officeB->id)
            ->assertJsonPath('data.role', 'OPERATOR');

        app(CurrentOffice::class)->clear();

        $this->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.office.id', $officeB->id)
            ->assertJsonPath('data.role', 'OPERATOR');

        $this->assertTrue(
            AuditLog::query()
                ->where('action', 'tenant.switched')
                ->where('user_id', $user->id)
                ->exists()
        );
    }

    public function test_troca_sem_membership_rejeita_e_mantem_tenant(): void
    {
        $officeA = Office::factory()->create();
        $officeB = Office::factory()->create();
        $user = User::factory()->forOffice($officeA, OfficeRole::Viewer)->create();

        $this->actingAs($user);

        $this->postJson('/api/v1/tenants/switch', ['office_id' => $officeB->id])
            ->assertNotFound();

        app(CurrentOffice::class)->clear();

        $this->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.office.id', $officeA->id);

        $this->assertTrue(
            AuditLog::query()->where('action', 'tenant.switch_denied')->exists()
        );
    }

    public function test_office_id_livre_no_request_e_ignorado(): void
    {
        $officeA = Office::factory()->create();
        $officeB = Office::factory()->create();
        $user = User::factory()->forOffice($officeA, OfficeRole::Admin)->withTwoFactorConfirmed()->create();

        $this->actingAs($user);

        $this->getJson('/api/v1/office/subscription?office_id='.$officeB->id)
            ->assertOk()
            ->assertJsonPath('data.office_id', $officeA->id);

        // Body office_id em rota tenant-scoped também não altera contexto
        $this->postJson('/api/v1/tenants/switch', ['office_id' => $officeA->id])->assertOk();

        app(CurrentOffice::class)->clear();
        $resolved = app(CurrentOffice::class)->resolve($user);
        $this->assertSame($officeA->id, $resolved?->id);
    }

    public function test_lista_memberships_somente_ativas(): void
    {
        $officeA = Office::factory()->create();
        $officeB = Office::factory()->create();
        $user = User::factory()->create();
        $officeA->users()->attach($user->id, ['role' => OfficeRole::Admin->value, 'is_active' => true]);
        $officeB->users()->attach($user->id, ['role' => OfficeRole::Viewer->value, 'is_active' => false]);

        $this->actingAs($user)
            ->getJson('/api/v1/tenants/memberships')
            ->assertOk()
            ->assertJsonCount(1, 'data.memberships')
            ->assertJsonPath('data.memberships.0.office_id', $officeA->id);
    }

    public function test_membership_inativa_nao_resolve_current_office(): void
    {
        $office = Office::factory()->create();
        $user = User::factory()->create();
        $office->users()->attach($user->id, ['role' => OfficeRole::Admin->value, 'is_active' => false]);

        $this->actingAs($user)
            ->getJson('/api/v1/clients')
            ->assertForbidden();
    }

    public function test_selected_office_id_nao_e_mass_assignable(): void
    {
        $officeA = Office::factory()->create();
        $officeB = Office::factory()->create();
        $user = User::factory()->forOffice($officeA, OfficeRole::Viewer)->create();
        $user->forceFill(['selected_office_id' => $officeA->id])->save();

        $user->fill(['selected_office_id' => $officeB->id, 'name' => 'Atualizado']);
        $user->save();

        $user->refresh();
        $this->assertSame($officeA->id, (int) $user->selected_office_id);
        $this->assertSame('Atualizado', $user->name);
    }
}
