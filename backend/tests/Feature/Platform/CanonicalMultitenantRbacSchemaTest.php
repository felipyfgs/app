<?php

namespace Tests\Feature\Platform;

use App\Enums\OfficeLifecycleStatus;
use App\Enums\OfficeRole;
use App\Enums\PlatformRole;
use App\Models\Office;
use App\Models\OfficeMembership;
use App\Models\PlatformMembership;
use App\Models\PlatformSetting;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Task 2.1 — schema aditivo RBAC canônico, tenant principal e lifecycle.
 */
class CanonicalMultitenantRbacSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_tabelas_e_colunas_canônicas_existem(): void
    {
        $this->assertTrue(Schema::hasTable('tenant_permission_profiles'));
        $this->assertTrue(Schema::hasTable('tenant_permission_profile_permissions'));

        $this->assertTrue(Schema::hasColumns('tenant_permission_profiles', [
            'id',
            'office_id',
            'key',
            'name',
            'description',
            'is_system',
            'is_active',
            'authorization_version',
            'created_at',
            'updated_at',
        ]));

        $this->assertTrue(Schema::hasColumns('tenant_permission_profile_permissions', [
            'id',
            'permission_profile_id',
            'permission_key',
            'created_at',
            'updated_at',
        ]));

        $this->assertTrue(Schema::hasColumns('office_user', [
            'role',
            'tenant_role',
            'permission_profile_id',
            'authorization_version',
        ]));

        $this->assertTrue(Schema::hasColumns('platform_memberships', [
            'role',
            'platform_role',
        ]));

        $this->assertTrue(Schema::hasColumn('platform_settings', 'primary_office_id'));
    }

    public function test_sem_soft_delete_em_perfis_e_colunas_legadas_preservadas(): void
    {
        $this->assertFalse(Schema::hasColumn('tenant_permission_profiles', 'deleted_at'));
        $this->assertFalse(Schema::hasColumn('tenant_permission_profile_permissions', 'deleted_at'));
        $this->assertTrue(Schema::hasColumn('office_user', 'role'));
        $this->assertTrue(Schema::hasColumn('platform_memberships', 'role'));
        $this->assertTrue($this->indexExists('platform_memberships', 'platform_memberships_one_platform_admin'));
    }

    public function test_upgrade_de_fixture_legada_permanece_legivel(): void
    {
        $office = Office::factory()->create(['lifecycle_status' => OfficeLifecycleStatus::Active]);
        $user = User::factory()->create();

        $membershipId = DB::table('office_user')->insertGetId([
            'office_id' => $office->id,
            'user_id' => $user->id,
            'role' => OfficeRole::Admin->value,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $row = DB::table('office_user')->where('id', $membershipId)->first();
        $this->assertNotNull($row);
        $this->assertSame(OfficeRole::Admin->value, $row->role);
        $this->assertNull($row->tenant_role);
        $this->assertNull($row->permission_profile_id);
        $this->assertSame(1, (int) $row->authorization_version);

        $platformUser = User::factory()->create();
        PlatformMembership::query()->create([
            'user_id' => $platformUser->id,
            'role' => PlatformRole::PlatformAdmin,
            'is_active' => true,
        ]);

        $pm = DB::table('platform_memberships')->where('user_id', $platformUser->id)->first();
        $this->assertSame(PlatformRole::PlatformAdmin->value, $pm->role);
        $this->assertNull($pm->platform_role);
    }

    public function test_perfil_e_permissoes_com_uniques_por_tenant(): void
    {
        $officeA = Office::factory()->create();
        $officeB = Office::factory()->create();

        $profileA = DB::table('tenant_permission_profiles')->insertGetId([
            'office_id' => $officeA->id,
            'key' => 'legacy-operator',
            'name' => 'Operador',
            'description' => null,
            'is_system' => true,
            'is_active' => true,
            'authorization_version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Mesma key em outro tenant é permitida.
        $profileB = DB::table('tenant_permission_profiles')->insertGetId([
            'office_id' => $officeB->id,
            'key' => 'legacy-operator',
            'name' => 'Operador',
            'description' => null,
            'is_system' => true,
            'is_active' => true,
            'authorization_version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertNotSame($profileA, $profileB);

        DB::table('tenant_permission_profile_permissions')->insert([
            'permission_profile_id' => $profileA,
            'permission_key' => 'clients.view',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(QueryException::class);
        DB::table('tenant_permission_profile_permissions')->insert([
            'permission_profile_id' => $profileA,
            'permission_key' => 'clients.view',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_fk_composta_impede_perfil_de_outro_office(): void
    {
        $officeA = Office::factory()->create();
        $officeB = Office::factory()->create();
        $user = User::factory()->create();

        $profileB = DB::table('tenant_permission_profiles')->insertGetId([
            'office_id' => $officeB->id,
            'key' => 'legacy-viewer',
            'name' => 'Visualizador',
            'is_system' => true,
            'is_active' => true,
            'authorization_version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        DB::table('office_user')->insert([
            'office_id' => $officeA->id,
            'user_id' => $user->id,
            'role' => OfficeRole::Viewer->value,
            'tenant_role' => 'tenant_user',
            'permission_profile_id' => $profileB,
            'authorization_version' => 1,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_primary_office_id_e_lifecycle_enum(): void
    {
        $office = Office::factory()->create([
            'lifecycle_status' => OfficeLifecycleStatus::Active,
        ]);

        DB::table('platform_settings')->insert([
            'id' => PlatformSetting::SINGLETON_ID,
            'organization_name' => 'Org Teste',
            'primary_office_id' => $office->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $settings = DB::table('platform_settings')->where('id', PlatformSetting::SINGLETON_ID)->first();
        $this->assertSame($office->id, (int) $settings->primary_office_id);

        $this->assertTrue(OfficeLifecycleStatus::Active->isOperational());
        $this->assertFalse(OfficeLifecycleStatus::Suspended->isOperational());
        $this->assertFalse(OfficeLifecycleStatus::Deprovisioned->isOperational());
        $this->assertTrue(OfficeLifecycleStatus::Active->canTransitionTo(OfficeLifecycleStatus::Suspended));
        $this->assertFalse(OfficeLifecycleStatus::Active->canTransitionTo(OfficeLifecycleStatus::Deprovisioned));
        $this->assertTrue(OfficeLifecycleStatus::Suspended->canTransitionTo(OfficeLifecycleStatus::Deprovisioned));
        $this->assertTrue(OfficeLifecycleStatus::Deprovisioned->isTerminal());
    }

    public function test_membership_legado_via_eloquent_ainda_funciona(): void
    {
        $office = Office::factory()->create();
        $user = User::factory()->create();

        $membership = OfficeMembership::query()->create([
            'office_id' => $office->id,
            'user_id' => $user->id,
            'role' => OfficeRole::Operator,
            'is_active' => true,
        ]);

        $this->assertSame(OfficeRole::Operator, $membership->fresh()->role);
        $this->assertNull($membership->fresh()->getAttribute('tenant_role'));
    }

    private function indexExists(string $table, string $index): bool
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            $row = DB::selectOne(
                'SELECT 1 AS ok FROM pg_indexes WHERE tablename = ? AND indexname = ? LIMIT 1',
                [$table, $index],
            );

            return $row !== null;
        }

        $indexes = DB::select("PRAGMA index_list('{$table}')");
        foreach ($indexes as $idx) {
            $name = $idx->name ?? $idx->Name ?? null;
            if ($name === $index) {
                return true;
            }
        }

        return false;
    }
}
