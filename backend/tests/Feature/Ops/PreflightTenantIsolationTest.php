<?php

namespace Tests\Feature\Ops;

use App\Models\Office;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PreflightTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_preflight_sucede_em_base_limpa(): void
    {
        $this->artisan('ops:preflight-tenant-isolation', ['--json' => true])
            ->assertSuccessful();
    }

    public function test_preflight_json_contem_chaves_esperadas(): void
    {
        $this->artisan('ops:preflight-tenant-isolation', ['--json' => true])
            ->expectsOutputToContain('"can_proceed"')
            ->expectsOutputToContain('"blockers"')
            ->assertSuccessful();
    }

    public function test_preflight_detecta_office_id_nulo_em_tabela_de_negocio(): void
    {
        $office = Office::query()->create([
            'name' => 'Escritório A',
            'slug' => 'escritorio-a',
            'is_active' => true,
        ]);

        // clients exige office_id em app normal; forçamos nulo via DB para o preflight.
        \DB::table('clients')->insert([
            'office_id' => null,
            'root_cnpj' => '12345678',
            'legal_name' => 'Cliente sem office',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // SQLite/migrations podem rejeitar null se NOT NULL — nesse caso o teste não se aplica.
        $nullCount = (int) \DB::table('clients')->whereNull('office_id')->count();
        if ($nullCount === 0) {
            $this->markTestSkipped('Schema rejeita office_id nulo em clients (esperado em PG).');
        }

        $this->artisan('ops:preflight-tenant-isolation', [
            '--json' => true,
            '--fail-on-issues' => true,
        ])->assertFailed();

        // limpeza simbólica (RefreshDatabase)
        unset($office);
    }

    public function test_preflight_com_membership_valida_nao_bloqueia(): void
    {
        $office = Office::query()->create([
            'name' => 'Escritório B',
            'slug' => 'escritorio-b',
            'is_active' => true,
        ]);

        $user = User::factory()->create(['is_active' => true]);
        \DB::table('office_user')->insert([
            'office_id' => $office->id,
            'user_id' => $user->id,
            'role' => 'ADMIN',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('ops:preflight-tenant-isolation', ['--fail-on-issues' => true])
            ->assertSuccessful();
    }
}
