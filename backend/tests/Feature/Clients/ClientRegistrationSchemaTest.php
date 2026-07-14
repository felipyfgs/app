<?php

namespace Tests\Feature\Clients;

use App\Enums\RegistrationSource;
use App\Enums\RegistrationStatus;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Establishment;
use App\Models\Office;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ClientRegistrationSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_schema_ampliado_existe_com_colunas_e_contatos(): void
    {
        $this->assertTrue(Schema::hasColumn('clients', 'legal_name'));
        $this->assertFalse(Schema::hasColumn('clients', 'name'));
        $this->assertTrue(Schema::hasColumn('clients', 'display_name'));
        $this->assertTrue(Schema::hasColumn('clients', 'registration_source'));
        $this->assertTrue(Schema::hasColumn('establishments', 'capture_enabled'));
        $this->assertTrue(Schema::hasColumn('establishments', 'registration_status'));
        $this->assertTrue(Schema::hasColumn('establishments', 'address_city'));
        $this->assertTrue(Schema::hasTable('client_contacts'));
    }

    public function test_factory_e_origem_legacy_padrao_em_seed_path(): void
    {
        $office = Office::factory()->create();
        $client = Client::factory()->forOffice($office)->legacy()->create();
        $est = Establishment::factory()->forClient($client)->create([
            'is_active' => true,
            'capture_enabled' => true,
            'registration_source' => RegistrationSource::Legacy,
            'registration_status' => RegistrationStatus::Unknown,
        ]);

        $this->assertSame(RegistrationSource::Legacy, $client->registration_source);
        $this->assertTrue($est->capture_enabled);
        $this->assertSame(RegistrationStatus::Unknown, $est->registration_status);
    }

    public function test_preflight_comando_somente_leitura_sem_bloqueios(): void
    {
        $office = Office::factory()->create();
        Client::factory()->forOffice($office)->create(['legal_name' => 'Ok', 'root_cnpj' => '11222333']);
        Establishment::factory()->forClient(
            Client::query()->first(),
            '11222333000181',
        )->create(['is_matrix' => true]);

        $exit = Artisan::call('clients:preflight-registration-expand', ['--json' => true]);
        $this->assertSame(0, $exit);
        $output = Artisan::output();
        $this->assertStringContainsString('"can_proceed": true', $output);
    }

    public function test_contato_soft_delete_e_relacoes(): void
    {
        $client = Client::factory()->create();
        $contact = ClientContact::factory()->forClient($client)->primary()->create([
            'email' => 'a@example.com',
        ]);

        $this->assertTrue($client->contacts()->whereKey($contact->id)->exists());
        $contact->delete();
        $this->assertSoftDeleted('client_contacts', ['id' => $contact->id]);
    }
}
