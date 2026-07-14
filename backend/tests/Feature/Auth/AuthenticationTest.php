<?php

namespace Tests\Feature\Auth;

use App\Enums\OfficeRole;
use App\Models\Office;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Features;
use Tests\Concerns\InteractsWithSpaAuth;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use InteractsWithSpaAuth;
    use RefreshDatabase;

    public function test_login_valido_cria_sessao(): void
    {
        $office = Office::factory()->create();
        $user = User::factory()->forOffice($office, OfficeRole::Operator)->create([
            'email' => 'op@example.com',
            'password' => 'password',
        ]);

        $response = $this->asSpa()->postJson('/login', [
            'email' => 'op@example.com',
            'password' => 'password',
        ]);

        $response->assertOk();
        $this->assertAuthenticatedAs($user);

        $me = $this->asSpa()->getJson('/api/v1/me');
        $me->assertOk()
            ->assertJsonPath('data.email', 'op@example.com')
            ->assertJsonPath('data.office.id', $office->id)
            ->assertJsonPath('data.role', 'OPERATOR');
    }

    public function test_login_admin_ignora_desafio_quando_2fa_esta_desativado(): void
    {
        config()->set('fortify.two_factor_required', false);
        config()->set('fortify.features', array_values(array_diff(
            config('fortify.features'),
            [Features::twoFactorAuthentication()],
        )));

        $office = Office::factory()->create();
        $admin = User::factory()
            ->forOffice($office, OfficeRole::Admin)
            ->withTwoFactorConfirmed()
            ->create([
                'email' => 'admin-dev@example.com',
                'password' => 'password',
            ]);

        $this->asSpa()->postJson('/login', [
            'email' => 'admin-dev@example.com',
            'password' => 'password',
        ])->assertOk()
            ->assertJsonMissing(['two_factor' => true]);

        $this->assertAuthenticatedAs($admin);
    }

    public function test_me_sem_sessao_retorna_401_json(): void
    {
        $this->asSpa()->getJson('/api/v1/me')->assertUnauthorized();

        // Sem Accept: application/json ainda deve ser 401 (não 500 por route login ausente)
        $this->get('/api/v1/me')->assertUnauthorized();
    }

    public function test_usuario_inativo_nao_consegue_fazer_login(): void
    {
        $office = Office::factory()->create();
        User::factory()->forOffice($office)->create([
            'email' => 'inactive@example.com',
            'password' => 'password',
            'is_active' => false,
        ]);

        $this->asSpa()->postJson('/login', [
            'email' => 'inactive@example.com',
            'password' => 'password',
        ])->assertUnprocessable();

        $this->assertGuest();
    }

    public function test_sessao_existente_e_encerrada_quando_usuario_e_desativado(): void
    {
        $office = Office::factory()->create();
        $user = User::factory()->forOffice($office, OfficeRole::Operator)->create();

        $this->actingAs($user);
        $user->update(['is_active' => false]);

        $this->asSpa()->getJson('/api/v1/me')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Não autenticado.');

        $this->assertGuest();
    }

    public function test_logout_encerra_sessao(): void
    {
        $office = Office::factory()->create();
        User::factory()->forOffice($office)->create([
            'email' => 'out@example.com',
            'password' => 'password',
        ]);

        $this->asSpa()->postJson('/login', [
            'email' => 'out@example.com',
            'password' => 'password',
        ])->assertOk();

        $this->assertAuthenticated();
        $this->asSpa()->postJson('/logout')->assertNoContent();
        $this->asSpa()->getJson('/api/v1/me')->assertUnauthorized();
    }
}
