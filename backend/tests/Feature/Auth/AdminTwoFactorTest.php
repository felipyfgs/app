<?php

namespace Tests\Feature\Auth;

use App\Enums\OfficeRole;
use App\Http\Middleware\EnsureAdminTwoFactor;
use App\Http\Middleware\EnsureOfficeContext;
use App\Models\Office;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AdminTwoFactorTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sem_2fa_e_bloqueado_em_rota_protegida(): void
    {
        $office = Office::factory()->create();
        $admin = User::factory()->forOffice($office, OfficeRole::Admin)->create();

        $this->actingAs($admin);

        $this->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.requires_two_factor_setup', true);

        // Rota de negócio fictícia sob o grupo com EnsureAdminTwoFactor
        $this->getJson('/api/v1/admin-probe')
            ->assertStatus(404); // ainda não existe; validamos middleware em probe abaixo
    }

    public function test_admin_sem_2fa_recebe_403_em_acao_administrativa(): void
    {
        $office = Office::factory()->create();
        $admin = User::factory()->forOffice($office, OfficeRole::Admin)->create();

        Route::middleware([
            'web',
            'auth:sanctum',
            EnsureOfficeContext::class,
            EnsureAdminTwoFactor::class,
        ])->get('/api/v1/__admin_probe', fn () => response()->json(['ok' => true]));

        $this->actingAs($admin)
            ->getJson('/api/v1/__admin_probe')
            ->assertForbidden()
            ->assertJsonPath('code', 'two_factor_required');
    }

    public function test_admin_com_2fa_confirmado_acessa_acao_administrativa(): void
    {
        $office = Office::factory()->create();
        $admin = User::factory()
            ->forOffice($office, OfficeRole::Admin)
            ->withTwoFactorConfirmed()
            ->create();

        Route::middleware([
            'web',
            'auth:sanctum',
            EnsureOfficeContext::class,
            EnsureAdminTwoFactor::class,
        ])->get('/api/v1/__admin_probe2', fn () => response()->json(['ok' => true]));

        $this->actingAs($admin)
            ->getJson('/api/v1/__admin_probe2')
            ->assertOk()
            ->assertJsonPath('ok', true);
    }

    public function test_operador_nao_precisa_2fa_para_rotas_comuns(): void
    {
        $office = Office::factory()->create();
        $operator = User::factory()->forOffice($office, OfficeRole::Operator)->create();

        Route::middleware([
            'web',
            'auth:sanctum',
            EnsureOfficeContext::class,
            EnsureAdminTwoFactor::class,
        ])->get('/api/v1/__op_probe', fn () => response()->json(['ok' => true]));

        $this->actingAs($operator)
            ->getJson('/api/v1/__op_probe')
            ->assertOk();
    }
}
