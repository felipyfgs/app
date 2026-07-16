<?php

namespace Tests\Feature\Auth;

use App\Enums\OfficeRole;
use App\Enums\PlatformRole;
use App\Models\Office;
use App\Models\PlatformMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BootstrapOfficeTest extends TestCase
{
    use RefreshDatabase;

    public function test_bootstrap_cria_escritorio_e_admin_dual(): void
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
        $office = Office::query()->firstOrFail();

        $this->assertSame(OfficeRole::Admin, $user->roleIn($office));
        $this->assertTrue($user->isPlatformAdmin());

        $pm = PlatformMembership::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame(PlatformRole::PlatformAdmin, $pm->role);
        $this->assertSame($office->id, (int) $pm->default_office_id);
        $this->assertTrue($pm->is_active);
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
        $this->assertSame(0, User::query()->where('email', 'admin2@example.com')->count());
        $this->assertSame(0, PlatformMembership::query()->count());
    }

    public function test_bootstrap_repetido_nao_cria_parcial(): void
    {
        $this->artisan('app:bootstrap-office', [
            '--name' => 'Primeiro',
            '--slug' => 'primeiro',
            '--admin-name' => 'Admin',
            '--admin-email' => 'admin@example.com',
        ])
            ->expectsQuestion('Senha do administrador', 'senha-segura-12')
            ->assertSuccessful();

        $users = User::query()->count();
        $offices = Office::query()->count();
        $pms = PlatformMembership::query()->count();

        $this->artisan('app:bootstrap-office', [
            '--name' => 'Segundo',
            '--slug' => 'segundo',
            '--admin-name' => 'Admin2',
            '--admin-email' => 'admin2@example.com',
        ])->assertFailed();

        $this->assertSame($users, User::query()->count());
        $this->assertSame($offices, Office::query()->count());
        $this->assertSame($pms, PlatformMembership::query()->count());
    }
}
