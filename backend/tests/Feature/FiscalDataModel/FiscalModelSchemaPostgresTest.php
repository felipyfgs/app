<?php

namespace Tests\Feature\FiscalDataModel;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Invariantes de schema da harness e do PostgreSQL de produto.
 * Em SQLite (phpunit default) valida o que for portável e marca o restante.
 * Execute com DB_CONNECTION=pgsql para a suíte estrutural completa (task 2.1/2.4).
 */
class FiscalModelSchemaPostgresTest extends TestCase
{
    use RefreshDatabase;

    public function test_harness_fk_to_offices_is_restrict_or_no_action_on_postgres(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->assertTrue(Schema::hasTable('fiscal_model_migration_maps'));
            $this->markTestSkipped('FK delete action inspection requer PostgreSQL.');
        }

        $rows = DB::select(<<<'SQL'
            SELECT rc.delete_rule
            FROM information_schema.table_constraints tc
            JOIN information_schema.referential_constraints rc
              ON rc.constraint_name = tc.constraint_name
             AND rc.constraint_schema = tc.table_schema
            JOIN information_schema.key_column_usage kcu
              ON kcu.constraint_name = tc.constraint_name
             AND kcu.table_schema = tc.table_schema
            WHERE tc.table_schema = 'public'
              AND tc.table_name = 'fiscal_model_migration_maps'
              AND tc.constraint_type = 'FOREIGN KEY'
              AND kcu.column_name = 'office_id'
            SQL);

        $this->assertNotEmpty($rows);
        $rule = strtoupper((string) $rows[0]->delete_rule);
        $this->assertContains($rule, ['RESTRICT', 'NO ACTION']);
    }

    public function test_timestamps_on_harness_tables_exist(): void
    {
        $this->assertTrue(Schema::hasColumn('fiscal_model_migration_maps', 'created_at'));
        $this->assertTrue(Schema::hasColumn('fiscal_model_migration_maps', 'updated_at'));
    }

    public function test_unique_source_map_enforced(): void
    {
        $officeId = DB::table('offices')->insertGetId([
            'name' => 'Schema Office',
            'slug' => 'schema-office-'.uniqid(),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $payload = [
            'aggregate' => 'tenancy_cadastro',
            'source_table' => 'clients',
            'source_id' => '1',
            'target_table' => 'clients',
            'target_id' => '1',
            'office_id' => $officeId,
            'correlation_id' => 'client:1',
            'status' => 'MAPPED',
            'created_at' => now(),
            'updated_at' => now(),
        ];

        DB::table('fiscal_model_migration_maps')->insert($payload);

        $this->expectException(\Throwable::class);
        DB::table('fiscal_model_migration_maps')->insert($payload);
    }

    public function test_fresh_migrate_includes_harness_when_schema_ready(): void
    {
        // RefreshDatabase já migrou do zero no connection de teste.
        $this->assertTrue(Schema::hasTable('fiscal_model_migration_maps'));
        $this->assertTrue(Schema::hasTable('fiscal_model_backfill_checkpoints'));
        $this->assertTrue(Schema::hasTable('clients'));
        $this->assertTrue(Schema::hasTable('dfe_documents'));
    }
}
