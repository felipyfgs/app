<?php

namespace Tests\Feature\Platform;

use App\Enums\OfficeRole;
use App\Models\Client;
use App\Models\Office;
use App\Models\PlatformMembership;
use App\Models\User;
use App\Services\Auth\RecentPasswordConfirmationGate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformOwnerApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_owner_retorna_identidade_sanitizada(): void
    {
        $office = Office::factory()->create(['name' => 'Escritório Padrão']);
        $owner = User::factory()->asPlatformAdmin($office->id)->create([
            'name' => 'Proprietário',
            'email' => 'owner@platform.example',
        ]);

        $this->actingAs($owner)
            ->getJson('/api/v1/platform/owner')
            ->assertOk()
            ->assertJsonPath('data.user_id', $owner->id)
            ->assertJsonPath('data.name', 'Proprietário')
            ->assertJsonPath('data.email', 'owner@platform.example')
            ->assertJsonPath('data.default_office.id', $office->id)
            ->assertJsonMissingPath('data.password')
            ->assertJsonMissingPath('data.activation');
    }

    public function test_patch_owner_exige_senha_recente_e_atualiza(): void
    {
        $office = Office::factory()->create();
        $owner = User::factory()->asPlatformAdmin($office->id)->create([
            'name' => 'Antes',
            'email' => 'antes@platform.example',
            'password' => bcrypt('admin-secret-12'),
        ]);

        $this->actingAs($owner)
            ->patchJson('/api/v1/platform/owner', [
                'name' => 'Depois',
            ])
            ->assertStatus(403);

        app(RecentPasswordConfirmationGate::class)->markConfirmed($owner);

        $this->actingAs($owner)
            ->patchJson('/api/v1/platform/owner', [
                'name' => 'Depois',
                'email' => 'depois@platform.example',
                'default_office_id' => $office->id,
            ])
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('data.name', 'Depois')
            ->assertJsonPath('data.email', 'depois@platform.example');

        $this->assertSame(1, PlatformMembership::query()->count());
        $this->assertSame($owner->id, PlatformMembership::query()->first()->user_id);
    }

    public function test_rotas_plurais_legadas_rejeitadas(): void
    {
        $office = Office::factory()->create();
        $owner = User::factory()->asPlatformAdmin($office->id)->create([
            'password' => bcrypt('admin-secret-12'),
        ]);
        app(RecentPasswordConfirmationGate::class)->markConfirmed($owner);

        $this->actingAs($owner)
            ->getJson('/api/v1/platform/admins')
            ->assertNotFound();

        $this->actingAs($owner)
            ->postJson('/api/v1/platform/admins', [
                'name' => 'Segundo',
                'email' => 'segundo@platform.example',
                'method' => 'MANUAL_LINK',
            ])
            ->assertNotFound();

        $this->assertSame(1, PlatformMembership::query()->count());
        $this->assertSame(0, User::query()->where('email', 'segundo@platform.example')->count());
    }

    public function test_owner_sem_membership_nao_acessa_fiscal_implicito(): void
    {
        $office = Office::factory()->create();
        Client::factory()->forOffice($office)->create();
        $owner = User::factory()->asPlatformAdmin()->create();

        $this->assertNull($owner->activeMembership());

        $this->actingAs($owner)
            ->getJson('/api/v1/clients')
            ->assertStatus(409)
            ->assertJsonPath('code', 'office_context_required');
    }

    public function test_office_admin_nao_acessa_owner(): void
    {
        $office = Office::factory()->create();
        $user = User::factory()->forOffice($office, OfficeRole::Admin)->create();

        $this->actingAs($user)
            ->getJson('/api/v1/platform/owner')
            ->assertForbidden();
    }
}
