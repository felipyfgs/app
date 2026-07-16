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

/**
 * TOTP/2FA descontinuado: EnsureAdminTwoFactor é no-op.
 * Login e navegação não exigem desafio adicional.
 */
class AdminTwoFactorTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sem_2fa_navega_sem_desafio(): void
    {
        $office = Office::factory()->create();
        $admin = User::factory()->forOffice($office, OfficeRole::Admin)->create([
            'two_factor_secret' => null,
            'two_factor_confirmed_at' => null,
        ]);

        $this->actingAs($admin)
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.requires_two_factor_setup', false)
            ->assertJsonPath('data.two_factor_required', false)
            ->assertJsonPath('data.role', OfficeRole::Admin->value);
    }

    public function test_admin_two_factor_middleware_e_noop(): void
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
            ->assertOk()
            ->assertJsonPath('ok', true);
    }

    public function test_operador_acessa_rotas_comuns(): void
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

    public function test_me_nunca_exige_setup_totp(): void
    {
        $office = Office::factory()->create();
        $admin = User::factory()->forOffice($office, OfficeRole::Admin)->create();

        $this->actingAs($admin)
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.requires_two_factor_setup', false)
            ->assertJsonPath('data.two_factor_required', false);
    }
}
