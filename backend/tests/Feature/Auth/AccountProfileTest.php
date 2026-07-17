<?php

namespace Tests\Feature\Auth;

use App\Enums\OfficeRole;
use App\Models\Office;
use App\Models\User;
use App\Services\Auth\RecentPasswordConfirmationGate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_viewer_atualiza_a_propria_identidade_sem_alterar_papel(): void
    {
        $office = Office::factory()->create();
        $viewer = User::factory()->forOffice($office, OfficeRole::Viewer)->create([
            'name' => 'Antes',
            'email' => 'antes@example.com',
        ]);

        $this->actingAs($viewer)
            ->patchJson('/api/v1/account', [
                'name' => 'Depois',
                'email' => 'depois@example.com',
            ])
            ->assertForbidden()
            ->assertJsonPath('code', 'password_confirmation_required');

        app(RecentPasswordConfirmationGate::class)->markConfirmed($viewer);

        $this->actingAs($viewer)
            ->patchJson('/api/v1/account', [
                'name' => 'Depois',
                'email' => 'DEPOIS@example.com',
                'role' => OfficeRole::Admin->value,
                'office_id' => 999,
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Depois')
            ->assertJsonPath('data.email', 'depois@example.com')
            ->assertJsonMissingPath('data.role')
            ->assertJsonMissingPath('data.office_id');

        $this->assertSame(OfficeRole::Viewer, $viewer->fresh()->roleIn($office));
    }

    public function test_email_de_outra_conta_retorna_erro_de_campo(): void
    {
        $office = Office::factory()->create();
        User::factory()->forOffice($office, OfficeRole::Operator)->create([
            'email' => 'ocupado@example.com',
        ]);
        $viewer = User::factory()->forOffice($office, OfficeRole::Viewer)->create();
        app(RecentPasswordConfirmationGate::class)->markConfirmed($viewer);

        $this->actingAs($viewer)
            ->patchJson('/api/v1/account', [
                'name' => 'Viewer',
                'email' => 'ocupado@example.com',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_platform_admin_atualiza_perfil_pessoal_sem_alterar_escritorio(): void
    {
        $office = Office::factory()->create(['name' => 'Escritório original']);
        $owner = User::factory()->asPlatformAdmin($office->id)->create([
            'name' => 'Admin original',
            'email' => 'admin-original@example.com',
        ]);
        app(RecentPasswordConfirmationGate::class)->markConfirmed($owner);

        $this->actingAs($owner)
            ->patchJson('/api/v1/account', [
                'name' => 'Outro nome',
                'email' => 'outro@example.com',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Outro nome')
            ->assertJsonPath('data.email', 'outro@example.com');

        $this->assertSame('Outro nome', $owner->fresh()->name);
        $this->assertTrue($owner->fresh()->isPlatformAdmin());
        $this->assertSame('Escritório original', $office->fresh()->name);
    }
}
