<?php

namespace Tests\Feature\Ops;

use App\Models\Office;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
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
            ->assertSuccessful();

        Artisan::call('ops:preflight-tenant-isolation', ['--json' => true]);
        $payload = json_decode(Artisan::output(), true);

        $this->assertIsArray($payload);
        $this->assertArrayHasKey('can_proceed', $payload);
        $this->assertArrayHasKey('blockers', $payload);
        $this->assertArrayHasKey('warnings', $payload);
        $this->assertArrayHasKey('details', $payload);
    }

    public function test_preflight_detecta_role_invalido_e_falha_com_flag(): void
    {
        $office = Office::query()->create([
            'name' => 'Escritório A',
            'slug' => 'escritorio-a',
            'is_active' => true,
        ]);

        $user = User::factory()->create(['is_active' => true]);

        DB::table('office_user')->insert([
            'office_id' => $office->id,
            'user_id' => $user->id,
            'role' => 'SUPERUSER',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('ops:preflight-tenant-isolation', [
            '--json' => true,
            '--fail-on-issues' => true,
        ])->assertFailed();
    }

    public function test_preflight_com_membership_valida_nao_bloqueia(): void
    {
        $office = Office::query()->create([
            'name' => 'Escritório B',
            'slug' => 'escritorio-b',
            'is_active' => true,
        ]);

        $user = User::factory()->create(['is_active' => true]);
        DB::table('office_user')->insert([
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
