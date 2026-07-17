<?php

namespace Tests\Feature\Activation;

use App\Enums\OfficeRole;
use App\Models\Office;
use App\Models\OfficeMembership;
use App\Models\PlatformMembership;
use App\Models\User;
use App\Services\Auth\RecentPasswordConfirmationGate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cadastro plural de administradores globais foi removido.
 * Mantém cobertura de isolamento: PLATFORM_ADMIN não conta na equipe do Office.
 */
class PlatformAdminOnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_cliente_legado_nao_cria_segundo_admin_global(): void
    {
        $office = Office::factory()->create();
        $actor = User::factory()->asPlatformAdmin($office->id)->create([
            'password' => bcrypt('admin-secret-12'),
        ]);

        $this->actingAs($actor);
        app(RecentPasswordConfirmationGate::class)->markConfirmed($actor);

        $this->actingAs($actor)
            ->postJson('/api/v1/platform/admins', [
                'name' => 'Global Admin',
                'email' => 'global@platform.example',
                'method' => 'MANUAL_LINK',
            ])
            ->assertNotFound();

        $this->assertSame(1, PlatformMembership::query()->count());
        $this->assertSame(0, User::query()->where('email', 'global@platform.example')->count());
    }

    public function test_listagem_plural_legada_nao_existe(): void
    {
        $office = Office::factory()->create();
        $actor = User::factory()->asPlatformAdmin($office->id)->create();

        $this->actingAs($actor)
            ->getJson('/api/v1/platform/admins')
            ->assertNotFound();
    }

    public function test_platform_admin_nao_conta_em_seats_da_equipe(): void
    {
        $office = Office::factory()->create();
        $officeAdmin = User::factory()->forOffice($office, OfficeRole::Admin)->create([
            'password' => bcrypt('admin-secret-12'),
        ]);

        $platformOnly = User::factory()->asPlatformAdmin($office->id)->create([
            'is_active' => true,
            'password_change_required' => false,
        ]);

        $platformOnly->platformMemberships()->update(['is_active' => true]);

        $this->assertSame(0, OfficeMembership::query()->where('user_id', $platformOnly->id)->count());

        $this->actingAs($officeAdmin);
        app(RecentPasswordConfirmationGate::class)->markConfirmed($officeAdmin);

        $list = $this->actingAs($officeAdmin)
            ->getJson('/api/v1/office/members')
            ->assertOk();

        $emails = collect($list->json('data'))->pluck('email')->all();
        $this->assertNotContains($platformOnly->email, $emails);
    }
}
