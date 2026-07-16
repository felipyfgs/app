<?php

namespace Tests\Feature\FiscalDataModel;

use App\Enums\CredentialStatus;
use App\Models\Client;
use App\Models\ClientCredential;
use App\Models\Establishment;
use App\Models\Office;
use App\Support\FiscalDataModel\FiscalModelAggregates;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CadastroCollapseTest extends TestCase
{
    use RefreshDatabase;

    public function test_colapsa_filial_no_cliente_raiz_movendo_estabelecimento(): void
    {
        $office = Office::factory()->create();
        $root = Client::factory()->forOffice($office)->create([
            'root_cnpj' => '11222333',
            'legal_name' => 'Raiz',
            'matrix_client_id' => null,
        ]);
        Establishment::factory()->forClient($root, '11222333000181')->create(['is_matrix' => true]);

        $branch = Client::factory()->forOffice($office)->create([
            'root_cnpj' => '11222333',
            'legal_name' => 'Filial',
            'matrix_client_id' => $root->id,
        ]);
        // Unique parcial só em matrix_client_id null — branch precisa bypass no sqlite
        DB::table('clients')->where('id', $branch->id)->update([
            'matrix_client_id' => $root->id,
            'root_cnpj' => '11222333',
        ]);
        $branchEst = Establishment::factory()
            ->forClient($branch->fresh(), '11222333000262')
            ->create(['is_matrix' => false]);

        $exit = Artisan::call('fiscal-model:backfill', [
            'aggregate' => FiscalModelAggregates::TENANCY_CADASTRO,
            '--json' => true,
        ]);
        $this->assertSame(0, $exit);

        $this->assertSoftDeleted('clients', ['id' => $branch->id]);
        $this->assertDatabaseHas('establishments', [
            'id' => $branchEst->id,
            'client_id' => $root->id,
        ]);
        $this->assertDatabaseHas('fiscal_model_migration_maps', [
            'source_table' => 'clients',
            'source_id' => (string) $branch->id,
            'target_id' => (string) $root->id,
            'status' => 'MAPPED',
        ]);
    }

    public function test_conflito_de_credencial_ativa_fica_ambiguo(): void
    {
        $office = Office::factory()->create();
        $root = Client::factory()->forOffice($office)->create([
            'root_cnpj' => '11222333',
            'matrix_client_id' => null,
        ]);
        $branch = Client::factory()->forOffice($office)->create([
            'root_cnpj' => '99999999',
            'matrix_client_id' => null,
        ]);
        DB::table('clients')->where('id', $branch->id)->update([
            'matrix_client_id' => $root->id,
            'root_cnpj' => '11222333',
        ]);

        ClientCredential::query()->create([
            'office_id' => $office->id,
            'client_id' => $root->id,
            'status' => CredentialStatus::Active,
            'subject_name' => 'Root',
            'holder_cnpj' => '11222333000181',
            'fingerprint_sha256' => str_repeat('a', 64),
            'vault_object_id' => '01ROOTVAULTID000000000000',
            'valid_from' => now()->subYear(),
            'valid_to' => now()->addYear(),
            'activated_at' => now()->subDay(),
        ]);
        ClientCredential::query()->create([
            'office_id' => $office->id,
            'client_id' => $branch->id,
            'status' => CredentialStatus::Active,
            'subject_name' => 'Branch',
            'holder_cnpj' => '11222333000262',
            'fingerprint_sha256' => str_repeat('b', 64),
            'vault_object_id' => '01BRANCHVAULTID0000000000',
            'valid_from' => now()->subYear(),
            'valid_to' => now()->addYear(),
            'activated_at' => now()->subDay(),
        ]);

        $exit = Artisan::call('fiscal-model:backfill', [
            'aggregate' => FiscalModelAggregates::TENANCY_CADASTRO,
            '--json' => true,
        ]);
        $this->assertSame(1, $exit);
        $out = json_decode(Artisan::output(), true);
        $this->assertGreaterThanOrEqual(1, $out['ambiguous']);
        $this->assertNotSoftDeleted('clients', ['id' => $branch->id]);
    }
}
