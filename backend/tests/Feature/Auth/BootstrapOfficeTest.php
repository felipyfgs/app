<?php

namespace Tests\Feature\Auth;

use App\Enums\OfficeRole;
use App\Models\Office;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BootstrapOfficeTest extends TestCase
{
    use RefreshDatabase;

    public function test_bootstrap_cria_escritorio_e_admin(): void
    {
        $this->artisan('app:bootstrap-office', [
            '--name' => 'Escritório Demo',
            '--slug' => 'escritorio-demo',
            '--admin-name' => 'Admin',
            '--admin-email' => 'admin@example.com',
        ])
            ->expectsQuestion('Senha do administrador', 'senha-segura-12')
            ->assertSuccessful();

        $this->assertDatabaseHas('offices', ['slug' => 'escritorio-demo']);
        $this->assertDatabaseHas('users', ['email' => 'admin@example.com']);

        $user = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $this->assertSame(OfficeRole::Admin, $user->roleIn(Office::query()->first()));
    }

    public function test_bootstrap_recusa_se_ja_existe_escritorio(): void
    {
        Office::factory()->create();

        $this->artisan('app:bootstrap-office', [
            '--name' => 'Outro',
            '--slug' => 'outro',
            '--admin-name' => 'Admin',
            '--admin-email' => 'admin2@example.com',
        ])->assertFailed();

        $this->assertSame(1, Office::query()->count());
    }
}
