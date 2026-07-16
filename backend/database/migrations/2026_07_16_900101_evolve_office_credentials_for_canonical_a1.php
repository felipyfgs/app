<?php

use App\Enums\OfficeCredentialPurpose;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Evolui office_credentials para a credencial canônica e-CNPJ A1 (purpose CANONICAL_ECNPJ_A1):
 * - office_fiscal_identity_id torna-se opcional (canônico é office-scoped)
 * - índice office+purpose+status
 * - no máximo uma ACTIVE canônica por office (partial unique no Postgres)
 *
 * @see OfficeCredentialPurpose::CanonicalECnpjA1
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('office_credentials')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement(
                'ALTER TABLE office_credentials ALTER COLUMN office_fiscal_identity_id DROP NOT NULL'
            );
        } elseif ($driver === 'sqlite') {
            Schema::table('office_credentials', function (Blueprint $table) {
                $table->unsignedBigInteger('office_fiscal_identity_id')->nullable()->change();
            });
        }

        Schema::table('office_credentials', function (Blueprint $table) {
            $table->index(
                ['office_id', 'purpose', 'status'],
                'office_credentials_office_purpose_status'
            );
        });

        if ($driver === 'pgsql') {
            DB::statement(
                "CREATE UNIQUE INDEX office_credentials_one_active_canonical
                 ON office_credentials (office_id)
                 WHERE purpose = 'CANONICAL_ECNPJ_A1' AND status = 'ACTIVE'"
            );
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('office_credentials')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS office_credentials_one_active_canonical');
        }

        Schema::table('office_credentials', function (Blueprint $table) {
            $table->dropIndex('office_credentials_office_purpose_status');
        });

        // Não reintroduz NOT NULL: linhas canônicas podem ter identity nula.
    }
};
