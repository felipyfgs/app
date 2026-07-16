<?php

namespace Tests\Feature\Auth;

use App\Models\Office;
use App\Models\User;
use App\Services\Auth\RecentPasswordConfirmationGate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PasswordConfirmationGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirm_password_abre_janela_de_quinze_minutos(): void
    {
        $office = Office::factory()->create();
        $user = User::factory()->forOffice($office)->create([
            'password' => bcrypt('senha-correta-12'),
        ]);

        $this->actingAs($user)
            ->postJson('/api/v1/auth/confirm-password', ['password' => 'senha-correta-12'])
            ->assertOk()
            ->assertJsonPath('data.confirmed', true)
            ->assertJsonPath('data.window_minutes', 15);

        $gate = app(RecentPasswordConfirmationGate::class);
        $this->assertTrue($gate->isRecentlyConfirmed($user));
    }

    public function test_senha_incorreta_nao_abre_janela(): void
    {
        $office = Office::factory()->create();
        $user = User::factory()->forOffice($office)->create([
            'password' => bcrypt('senha-correta-12'),
        ]);

        $this->actingAs($user)
            ->postJson('/api/v1/auth/confirm-password', ['password' => 'errada'])
            ->assertStatus(422)
            ->assertJsonPath('code', 'PASSWORD_INVALID');

        $this->assertFalse(app(RecentPasswordConfirmationGate::class)->isRecentlyConfirmed($user));
    }

    public function test_expiracao_invalida_janela(): void
    {
        $office = Office::factory()->create();
        $user = User::factory()->forOffice($office)->create();

        $this->actingAs($user);
        $gate = app(RecentPasswordConfirmationGate::class);
        $gate->markConfirmed($user);
        $this->assertTrue($gate->isRecentlyConfirmed($user));

        $gate->expire(null, $user);
        $this->assertFalse($gate->isRecentlyConfirmed($user));
    }

    public function test_clear_invalida_janela(): void
    {
        $office = Office::factory()->create();
        $user = User::factory()->forOffice($office)->create();

        $this->actingAs($user);
        $gate = app(RecentPasswordConfirmationGate::class);
        $gate->markConfirmed($user);
        $gate->clear(null, $user);
        $this->assertFalse($gate->isRecentlyConfirmed($user));
    }

    public function test_login_nao_exige_totp(): void
    {
        $office = Office::factory()->create();
        $user = User::factory()->forOffice($office)->create([
            'email' => 'office@example.com',
            'password' => bcrypt('senha-segura-12'),
            'two_factor_secret' => null,
            'two_factor_confirmed_at' => null,
        ]);

        $this->postJson('/login', [
            'email' => 'office@example.com',
            'password' => 'senha-segura-12',
        ])->assertOk();

        $this->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.two_factor_required', false)
            ->assertJsonPath('data.requires_two_factor_setup', false);
    }
}
