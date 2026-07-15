<?php

namespace Tests\Feature\Clients;

use App\Enums\CredentialStatus;
use App\Enums\OfficeRole;
use App\Enums\SyncCursorStatus;
use App\Models\Client;
use App\Models\ClientCredential;
use App\Models\Establishment;
use App\Models\Office;
use App\Models\SyncCursor;
use App\Models\User;
use App\Support\CurrentOffice;
use Database\Factories\EstablishmentFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientListOperationalTest extends TestCase
{
    use RefreshDatabase;

    public function test_listagem_inclui_resumo_a1_captura_sync_e_kpis(): void
    {
        $office = Office::factory()->create();
        $user = User::factory()->forOffice($office, OfficeRole::Operator)->withTwoFactorConfirmed()->create();

        $withA1 = Client::factory()->forOffice($office)->create([
            'legal_name' => 'Com Certificado',
            'root_cnpj' => '11222333',
            'is_active' => true,
        ]);
        $estA1 = Establishment::factory()->forClient($withA1, EstablishmentFactory::cnpjWithRoot('11222333'))->create([
            'capture_enabled' => true,
            'is_active' => true,
        ]);
        ClientCredential::query()->create([
            'office_id' => $office->id,
            'client_id' => $withA1->id,
            'status' => CredentialStatus::Active,
            'subject_name' => 'CN=Com Certificado',
            'holder_cnpj' => $estA1->cnpj,
            'fingerprint_sha256' => str_repeat('a', 64),
            'valid_from' => now()->subYear(),
            'valid_to' => now()->addMonths(6),
            'vault_object_id' => 'vaultobj-com-certificado-xx',
            'activated_at' => now()->subMonth(),
            'expires_alert_30' => false,
            'expires_alert_7' => false,
            'expires_alert_1' => false,
        ]);
        SyncCursor::query()->create([
            'office_id' => $office->id,
            'establishment_id' => $estA1->id,
            'environment' => 'production',
            'last_nsu' => 10,
            'status' => SyncCursorStatus::Idle,
            'last_success_at' => now()->subHour(),
        ]);

        $withoutA1 = Client::factory()->forOffice($office)->create([
            'legal_name' => 'Sem Certificado',
            'root_cnpj' => '99888777',
            'is_active' => true,
        ]);
        $estOff = Establishment::factory()->forClient($withoutA1, EstablishmentFactory::cnpjWithRoot('99888777'))->create([
            'capture_enabled' => false,
            'is_active' => true,
        ]);
        SyncCursor::query()->create([
            'office_id' => $office->id,
            'establishment_id' => $estOff->id,
            'environment' => 'production',
            'last_nsu' => 0,
            'status' => SyncCursorStatus::Blocked,
            'last_error' => 'decode',
        ]);

        $this->actingAs($user);
        app(CurrentOffice::class)->resolve($user);

        $response = $this->getJson('/api/v1/clients?per_page=50')->assertOk();
        $response->assertJsonPath('meta.stats.total', 2);
        $response->assertJsonPath('meta.stats.with_credential', 1);
        $response->assertJsonPath('meta.stats.without_credential', 1);
        $response->assertJsonPath('meta.stats.capture_problem', 1);

        $items = collect($response->json('data'));
        $com = $items->firstWhere('legal_name', 'Com Certificado');
        $sem = $items->firstWhere('legal_name', 'Sem Certificado');

        $this->assertNotNull($com);
        $this->assertNotNull($sem);
        $this->assertSame('ACTIVE', $com['credential_summary']['status'] ?? null);
        $this->assertNull($sem['credential_summary']);
        $this->assertSame('ON', $com['capture_summary']['status'] ?? null);
        $this->assertSame('OFF', $sem['capture_summary']['status'] ?? null);
        $this->assertSame('IDLE', $com['sync_summary']['status'] ?? null);
        $this->assertSame('BLOCKED', $sem['sync_summary']['status'] ?? null);

        $body = (string) $response->getContent();
        $this->assertStringNotContainsString('vault_object_id', $body);
        $this->assertStringNotContainsString('vaultobj', $body);
        $this->assertStringNotContainsString('BEGIN CERTIFICATE', $body);
        $this->assertStringNotContainsString('private_key', $body);
        $this->assertStringNotContainsString('password', $body);
    }

    public function test_isolamento_entre_escritorios_na_listagem(): void
    {
        $officeA = Office::factory()->create();
        $officeB = Office::factory()->create();
        $userA = User::factory()->forOffice($officeA, OfficeRole::Operator)->withTwoFactorConfirmed()->create();

        Client::factory()->forOffice($officeA)->create(['legal_name' => 'Escritorio A', 'root_cnpj' => '11222333']);
        Client::factory()->forOffice($officeB)->create(['legal_name' => 'Escritorio B', 'root_cnpj' => '99888777']);

        $this->actingAs($userA);
        app(CurrentOffice::class)->resolve($userA);

        $this->getJson('/api/v1/clients')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.legal_name', 'Escritorio A')
            ->assertJsonPath('meta.stats.total', 1);
    }

    public function test_busca_unica_por_nome_e_cnpj(): void
    {
        $office = Office::factory()->create();
        $user = User::factory()->forOffice($office, OfficeRole::Viewer)->withTwoFactorConfirmed()->create();

        $client = Client::factory()->forOffice($office)->create([
            'legal_name' => 'Empresa Buscavel LTDA',
            'root_cnpj' => '11222333',
        ]);
        Establishment::factory()->forClient($client, EstablishmentFactory::cnpjWithRoot('11222333'))->create();
        Client::factory()->forOffice($office)->create([
            'legal_name' => 'Outra Empresa',
            'root_cnpj' => '99888777',
        ]);

        $this->actingAs($user);
        app(CurrentOffice::class)->resolve($user);

        $this->getJson('/api/v1/clients?q=Buscavel')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.legal_name', 'Empresa Buscavel LTDA');

        $this->getJson('/api/v1/clients?q=11222333')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_paginacao_filtros_e_ordenacao_sao_aplicados_no_servidor(): void
    {
        $office = Office::factory()->create();
        $user = User::factory()->forOffice($office, OfficeRole::Operator)->withTwoFactorConfirmed()->create();

        Client::factory()->forOffice($office)->create(['legal_name' => 'Alfa', 'root_cnpj' => '11111111', 'is_active' => true]);
        Client::factory()->forOffice($office)->create(['legal_name' => 'Beta', 'root_cnpj' => '22222222', 'is_active' => true]);
        Client::factory()->forOffice($office)->create(['legal_name' => 'Gama', 'root_cnpj' => '33333333', 'is_active' => false]);

        $this->actingAs($user);
        app(CurrentOffice::class)->resolve($user);

        $first = $this->getJson('/api/v1/clients?is_active=1&sort=legal_name&direction=desc&per_page=1&page=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.legal_name', 'Beta')
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('meta.total', 2);

        $this->getJson('/api/v1/clients?is_active=1&sort=legal_name&direction=desc&per_page=1&page=2')
            ->assertOk()
            ->assertJsonPath('data.0.legal_name', 'Alfa');

        $this->getJson('/api/v1/clients?operational_filter=without_credential&per_page=2')
            ->assertOk()
            ->assertJsonPath('meta.total', 3);

        $this->assertSame(2, $first->json('meta.stats.active'));
    }
}
