<?php

namespace Tests\Feature\Platform;

use App\Enums\PlatformRole;
use App\Models\PlatformMembership;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PlatformOwnerSchemaTest extends TestCase
{
    use RefreshDatabase;

    private const INDEX = 'platform_memberships_one_platform_admin';

    public function test_indice_parcial_unico_existe_apos_migrations(): void
    {
        $this->assertTrue(Schema::hasTable('platform_memberships'));
        $this->assertTrue($this->indexExists());
    }

    public function test_segundo_platform_admin_falha_no_banco(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();

        PlatformMembership::query()->create([
            'user_id' => $a->id,
            'role' => PlatformRole::PlatformAdmin,
            'is_active' => true,
        ]);

        $this->expectException(QueryException::class);

        PlatformMembership::query()->create([
            'user_id' => $b->id,
            'role' => PlatformRole::PlatformAdmin,
            'is_active' => true,
        ]);
    }

    public function test_preflight_falha_com_duplicados_antes_do_indice(): void
    {
        $this->dropOwnerIndex();

        $a = User::factory()->create();
        $b = User::factory()->create();

        DB::table('platform_memberships')->insert([
            [
                'user_id' => $a->id,
                'role' => PlatformRole::PlatformAdmin->value,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => $b->id,
                'role' => PlatformRole::PlatformAdmin->value,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $migration = require database_path('migrations/2026_07_16_940000_add_unique_platform_admin_owner_index.php');

        try {
            $migration->up();
            $this->fail('Esperava RuntimeException no preflight de duplicidade.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('platform_owner_unique', $e->getMessage());
            $this->assertStringContainsString('consolidate', $e->getMessage());
        }

        // Dados não foram alterados pelo preflight.
        $this->assertSame(2, (int) DB::table('platform_memberships')->where('role', 'PLATFORM_ADMIN')->count());
    }

    public function test_rollback_remove_indice(): void
    {
        $this->assertTrue($this->indexExists());

        $migration = require database_path('migrations/2026_07_16_940000_add_unique_platform_admin_owner_index.php');
        $migration->down();

        $this->assertFalse($this->indexExists());

        $migration->up();
        $this->assertTrue($this->indexExists());
    }

    private function indexExists(): bool
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            $row = DB::selectOne(
                'SELECT 1 AS ok FROM pg_indexes WHERE indexname = ? LIMIT 1',
                [self::INDEX],
            );

            return $row !== null;
        }

        $indexes = DB::select("PRAGMA index_list('platform_memberships')");
        foreach ($indexes as $idx) {
            $name = $idx->name ?? null;
            if ($name === self::INDEX) {
                return true;
            }
        }

        return false;
    }

    private function dropOwnerIndex(): void
    {
        DB::statement('DROP INDEX IF EXISTS '.self::INDEX);
    }
}
