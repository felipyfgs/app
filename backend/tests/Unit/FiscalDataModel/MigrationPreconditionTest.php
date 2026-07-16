<?php

namespace Tests\Unit\FiscalDataModel;

use App\Support\FiscalDataModel\MigrationPrecondition;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class MigrationPreconditionTest extends TestCase
{
    public function test_table_exists_and_missing(): void
    {
        Schema::create('fdm_precond_tmp', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
        });

        MigrationPrecondition::tableExists('fdm_precond_tmp', 'test');
        MigrationPrecondition::columnExists('fdm_precond_tmp', 'name', 'test');
        MigrationPrecondition::columnMissing('fdm_precond_tmp', 'absent', 'test');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Coluna já existe');
        MigrationPrecondition::columnMissing('fdm_precond_tmp', 'name', 'test');
    }

    public function test_missing_table_fails_with_diagnostic(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Tabela obrigatória ausente');
        MigrationPrecondition::tableExists('fdm_definitely_missing_xyz', 'diag');
    }
}
