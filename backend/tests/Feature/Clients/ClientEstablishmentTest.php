<?php

namespace Tests\Feature\Clients;

use App\Enums\OfficeRole;
use App\Models\Client;
use App\Models\Office;
use App\Models\User;
use App\Support\CurrentOffice;
use Database\Factories\EstablishmentFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientEstablishmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_operador_cria_cliente_com_cnpj_mascarado(): void
    {
        [$office, $user] = $this->officeUser(OfficeRole::Operator);

        $this->actingAs($user);
        app(CurrentOffice::class)->resolve($user);

        $response = $this->postJson('/api/v1/clients', [
            'name' => 'Cliente Alpha',
            'cnpj' => '11.222.333/0001-81',
            'office_id' => 9999, // deve ser ignorado
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.root_cnpj', '11222333')
            ->assertJsonPath('data.office_id', $office->id);
    }

    public function test_viewer_nao_cria_cliente(): void
    {
        [, $user] = $this->officeUser(OfficeRole::Viewer);
        $this->actingAs($user);
        app(CurrentOffice::class)->resolve($user);

        $this->postJson('/api/v1/clients', [
            'name' => 'X',
            'cnpj' => '11222333000181',
        ])->assertForbidden();
    }

    public function test_estabelecimento_raiz_incompativel(): void
    {
        [$office, $user] = $this->officeUser(OfficeRole::Operator);
        $this->actingAs($user);
        app(CurrentOffice::class)->resolve($user);

        $client = Client::factory()->forOffice($office)->create(['root_cnpj' => '11222333']);

        // CNPJ válido de outra raiz (04.252.011/0001-10)
        $this->postJson("/api/v1/clients/{$client->id}/establishments", [
            'cnpj' => '04.252.011/0001-10',
        ])->assertStatus(422)
            ->assertJsonPath('errors.cnpj.0', 'Raiz incompatível com o cliente.');
    }

    public function test_estabelecimento_duplicado_no_escritorio(): void
    {
        [$office, $user] = $this->officeUser(OfficeRole::Operator);
        $this->actingAs($user);
        app(CurrentOffice::class)->resolve($user);

        $client = Client::factory()->forOffice($office)->create(['root_cnpj' => '11222333']);
        $cnpj = EstablishmentFactory::cnpjWithRoot('11222333', '0001');

        $this->postJson("/api/v1/clients/{$client->id}/establishments", [
            'cnpj' => $cnpj,
            'is_matrix' => true,
        ])->assertCreated();

        $this->postJson("/api/v1/clients/{$client->id}/establishments", [
            'cnpj' => $cnpj,
        ])->assertStatus(422);
    }

    public function test_isolamento_entre_escritorios(): void
    {
        [$officeA, $userA] = $this->officeUser(OfficeRole::Operator);
        $officeB = Office::factory()->create();
        $clientB = Client::factory()->forOffice($officeB)->create();

        $this->actingAs($userA);
        app(CurrentOffice::class)->resolve($userA);

        $this->getJson("/api/v1/clients/{$clientB->id}")->assertNotFound();
    }

    /**
     * @return array{0: Office, 1: User}
     */
    private function officeUser(OfficeRole $role): array
    {
        $office = Office::factory()->create();
        $user = User::factory()
            ->forOffice($office, $role)
            ->when($role === OfficeRole::Admin, fn ($f) => $f->withTwoFactorConfirmed())
            ->create();

        return [$office, $user];
    }
}
