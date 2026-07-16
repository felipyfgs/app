<?php

namespace Tests\Feature\Clients;

use App\Contracts\SecureObjectStore;
use App\Enums\OfficeRole;
use App\Enums\RegistrationStatus;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\ClientCustomField;
use App\Models\Establishment;
use App\Models\Office;
use App\Models\User;
use App\Support\CurrentOffice;
use Database\Factories\EstablishmentFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientEstablishmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_operador_cria_cliente_e_primeiro_estabelecimento_transacionalmente(): void
    {
        [$office, $user] = $this->officeUser(OfficeRole::Operator);

        $this->actingAs($user);
        app(CurrentOffice::class)->resolve($user);

        $response = $this->postJson('/api/v1/clients', [
            'legal_name' => 'Cliente Alpha LTDA',
            'cnpj' => '11.222.333/0001-81',
            'trade_name' => 'Alpha',
            'is_matrix' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.client.root_cnpj', '11222333')
            ->assertJsonPath('data.client.office_id', $office->id)
            ->assertJsonPath('data.client.legal_name', 'Cliente Alpha LTDA')
            ->assertJsonPath('data.establishment.cnpj', '11222333000181')
            ->assertJsonPath('data.establishment.is_matrix', true)
            ->assertJsonPath('data.establishment.capture_enabled', true);

        $this->assertDatabaseCount('clients', 1);
        $this->assertDatabaseCount('establishments', 1);
    }

    public function test_office_id_no_payload_e_ignorado_pelo_middleware(): void
    {
        [$office, $user] = $this->officeUser(OfficeRole::Operator);
        $this->actingAs($user);
        app(CurrentOffice::class)->resolve($user);

        // Middleware remove office_id do JSON antes do FormRequest; cadastro usa o office da sessão.
        // StoreClientRequest também declara office_id prohibited (defesa se o strip falhar).
        $this->postJson('/api/v1/clients', [
            'legal_name' => 'Cliente com office forjado',
            'cnpj' => '11222333000181',
            'office_id' => 9999,
        ])->assertCreated()
            ->assertJsonPath('data.client.office_id', $office->id);

        $this->assertDatabaseHas('clients', [
            'legal_name' => 'Cliente com office forjado',
            'office_id' => $office->id,
        ]);
        $this->assertDatabaseMissing('clients', ['office_id' => 9999]);
    }

    public function test_viewer_nao_cria_cliente(): void
    {
        [, $user] = $this->officeUser(OfficeRole::Viewer);
        $this->actingAs($user);
        app(CurrentOffice::class)->resolve($user);

        $this->postJson('/api/v1/clients', [
            'legal_name' => 'X',
            'cnpj' => '11222333000181',
        ])->assertForbidden();
    }

    public function test_criacao_inclui_contato_responsavel_na_mesma_transacao(): void
    {
        [$office, $user] = $this->officeUser(OfficeRole::Operator);
        $this->actingAs($user);
        app(CurrentOffice::class)->resolve($user);

        $response = $this->postJson('/api/v1/clients', [
            'legal_name' => 'Cliente com responsável LTDA',
            'cnpj' => '11.222.333/0001-81',
            'public_email' => 'publico@example.com',
            'initial_contact' => [
                'name' => 'Ana Responsável',
                'role' => 'Financeiro',
                'email' => 'ana@example.com',
                'is_whatsapp' => false,
                'is_primary' => true,
                'receives_alerts' => true,
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.contact.name', 'Ana Responsável')
            ->assertJsonPath('data.contact.email', 'ana@example.com')
            ->assertJsonPath('data.contact.is_primary', true);

        $this->assertDatabaseHas('client_contacts', [
            'office_id' => $office->id,
            'client_id' => $response->json('data.client.id'),
            'name' => 'Ana Responsável',
            'email' => 'ana@example.com',
        ]);
        $this->assertDatabaseMissing('client_contacts', ['email' => 'publico@example.com']);
    }

    public function test_contato_responsavel_exige_um_canal_e_nao_cria_cadastro_parcial(): void
    {
        [, $user] = $this->officeUser(OfficeRole::Operator);
        $this->actingAs($user);
        app(CurrentOffice::class)->resolve($user);

        $this->postJson('/api/v1/clients', [
            'legal_name' => 'Cadastro inválido LTDA',
            'cnpj' => '11.222.333/0001-81',
            'initial_contact' => ['name' => 'Sem canal'],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('initial_contact.email');

        $this->assertDatabaseCount('clients', 0);
        $this->assertDatabaseCount('establishments', 0);
        $this->assertDatabaseCount('client_contacts', 0);
    }

    public function test_admin_cria_campos_texto_e_segredo_sem_expor_valor_secreto(): void
    {
        [$office, $user] = $this->officeUser(OfficeRole::Admin);
        $this->actingAs($user);
        app(CurrentOffice::class)->resolve($user);

        $response = $this->postJson('/api/v1/clients', [
            'legal_name' => 'Cliente com campos LTDA',
            'cnpj' => '11.222.333/0001-81',
            'custom_fields' => [
                ['label' => 'Sistema municipal', 'type' => 'TEXT', 'value' => 'Portal NFSe'],
                ['label' => 'Senha do portal', 'type' => 'SECRET', 'value' => 'segredo-sintetico-123'],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.custom_fields.0.value', 'Portal NFSe')
            ->assertJsonPath('data.custom_fields.1.value', null)
            ->assertJsonPath('data.custom_fields.1.has_value', true);
        $this->assertStringNotContainsString('segredo-sintetico-123', (string) $response->getContent());

        $secret = ClientCustomField::query()->where('type', 'SECRET')->firstOrFail();
        $this->assertNull($secret->value_text);
        $this->assertNotNull($secret->vault_object_id);
        $this->assertTrue(app(SecureObjectStore::class)->exists($secret->vault_object_id));
        $this->assertSame($office->id, $secret->office_id);
    }

    public function test_operador_nao_pode_criar_campo_secreto(): void
    {
        [, $user] = $this->officeUser(OfficeRole::Operator);
        $this->actingAs($user);
        app(CurrentOffice::class)->resolve($user);

        $this->postJson('/api/v1/clients', [
            'legal_name' => 'Cliente bloqueado LTDA',
            'cnpj' => '11.222.333/0001-81',
            'custom_fields' => [
                ['label' => 'Senha', 'type' => 'SECRET', 'value' => 'nao-persistir'],
            ],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('custom_fields');

        $this->assertDatabaseCount('clients', 0);
        $this->assertDatabaseCount('client_custom_fields', 0);
    }

    public function test_cnpj_completo_duplicado_retorna_conflito_acionavel(): void
    {
        [$office, $user] = $this->officeUser(OfficeRole::Operator);
        $this->actingAs($user);
        app(CurrentOffice::class)->resolve($user);

        $existing = Client::factory()->forOffice($office)->create(['root_cnpj' => '11222333']);
        Establishment::factory()->forClient($existing, '11222333000181')->create();

        $this->postJson('/api/v1/clients', [
            'legal_name' => 'Outro',
            'cnpj' => '11222333000181',
        ])
            ->assertStatus(409)
            ->assertJsonPath('data.existing_client_id', $existing->id);
    }

    public function test_filial_mesma_raiz_anexa_estabelecimento_ao_cliente_raiz(): void
    {
        [$office, $user] = $this->officeUser(OfficeRole::Operator);
        $this->actingAs($user);
        app(CurrentOffice::class)->resolve($user);

        $matrix = Client::factory()->forOffice($office)->create([
            'legal_name' => 'Matriz LTDA',
            'root_cnpj' => '11222333',
        ]);
        Establishment::factory()->forClient($matrix, '11222333000181')->create(['is_matrix' => true]);

        // Mesma raiz, CNPJ completo diferente → mesmo Cliente canônico, novo Estabelecimento
        $response = $this->postJson('/api/v1/clients', [
            'legal_name' => 'Filial LTDA',
            'cnpj' => EstablishmentFactory::cnpjWithRoot('11222333', '0002'),
            'is_matrix' => false,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.client.root_cnpj', '11222333')
            ->assertJsonPath('data.client.legal_name', 'Matriz LTDA')
            ->assertJsonPath('data.client.id', $matrix->id);

        $this->assertDatabaseCount('clients', 1);
        $this->assertDatabaseCount('establishments', 2);
        $this->assertFalse((bool) $response->json('data.establishment.is_matrix'));
    }

    public function test_filial_vinculada_vira_estabelecimento_do_cliente_raiz(): void
    {
        [$office, $user] = $this->officeUser(OfficeRole::Operator);
        $this->actingAs($user);
        app(CurrentOffice::class)->resolve($user);

        $matrix = Client::factory()->forOffice($office)->create([
            'legal_name' => 'Matriz LTDA',
            'root_cnpj' => '11222333',
        ]);
        Establishment::factory()->forClient($matrix, '11222333000181')->create(['is_matrix' => true]);

        $branchCnpj = EstablishmentFactory::cnpjWithRoot('11222333', '0002');
        $created = $this->postJson('/api/v1/clients', [
            'legal_name' => 'Filial Vinculada LTDA',
            'cnpj' => $branchCnpj,
            'matrix_client_id' => $matrix->id,
        ])->assertCreated();

        $this->assertSame($matrix->id, $created->json('data.client.id'));
        $this->assertNull($created->json('data.client.matrix_client_id'));
        $this->assertFalse((bool) $created->json('data.establishment.is_matrix'));
        $this->assertSame($branchCnpj, $created->json('data.establishment.cnpj'));

        $detail = $this->getJson("/api/v1/clients/{$matrix->id}")->assertOk();
        $detailId = $detail->json('data.client.id') ?? $detail->json('data.id');
        $this->assertSame($matrix->id, $detailId);

        $this->assertSame(
            2,
            Establishment::query()->where('client_id', $matrix->id)->count(),
        );
    }

    public function test_filial_exige_mesma_raiz_da_matriz(): void
    {
        [$office, $user] = $this->officeUser(OfficeRole::Operator);
        $this->actingAs($user);
        app(CurrentOffice::class)->resolve($user);

        $matrix = Client::factory()->forOffice($office)->create([
            'legal_name' => 'Matriz LTDA',
            'root_cnpj' => '11222333',
        ]);
        Establishment::factory()->forClient($matrix, '11222333000181')->create(['is_matrix' => true]);

        $this->postJson('/api/v1/clients', [
            'legal_name' => 'Outra raiz',
            'cnpj' => '04252011000110',
            'matrix_client_id' => $matrix->id,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['matrix_client_id', 'cnpj']);
    }

    public function test_nao_adiciona_estabelecimento_sob_cliente_existente(): void
    {
        [$office, $user] = $this->officeUser(OfficeRole::Operator);
        $this->actingAs($user);
        app(CurrentOffice::class)->resolve($user);

        $client = Client::factory()->forOffice($office)->create(['root_cnpj' => '11222333']);

        $this->postJson("/api/v1/clients/{$client->id}/establishments", [
            'cnpj' => EstablishmentFactory::cnpjWithRoot('11222333', '0002'),
            'is_matrix' => false,
        ])->assertStatus(422)
            ->assertJsonPath(
                'errors.cnpj.0',
                'Use “Novo cliente” com o CNPJ completo da filial. Não se adicionam filiais sob o perfil da matriz.'
            );
    }

    public function test_contato_principal_unico_e_isolamento(): void
    {
        [$office, $user] = $this->officeUser(OfficeRole::Operator);
        $this->actingAs($user);
        app(CurrentOffice::class)->resolve($user);

        $client = Client::factory()->forOffice($office)->create();

        $first = $this->postJson("/api/v1/clients/{$client->id}/contacts", [
            'name' => 'Ana',
            'email' => 'ana@example.com',
            'is_primary' => true,
        ])->assertCreated();

        $second = $this->postJson("/api/v1/clients/{$client->id}/contacts", [
            'name' => 'Bruno',
            'phone' => '11999999999',
            'is_primary' => true,
        ])->assertCreated();

        $this->assertFalse(ClientContact::query()->find($first->json('data.id'))->is_primary);
        $this->assertTrue(ClientContact::query()->find($second->json('data.id'))->is_primary);

        $officeB = Office::factory()->create();
        $clientB = Client::factory()->forOffice($officeB)->create();
        $this->getJson("/api/v1/clients/{$clientB->id}")->assertNotFound();
    }

    public function test_situacao_nao_ativa_cria_com_captura_desabilitada(): void
    {
        [$office, $user] = $this->officeUser(OfficeRole::Operator);
        $this->actingAs($user);
        app(CurrentOffice::class)->resolve($user);

        $response = $this->postJson('/api/v1/clients', [
            'legal_name' => 'Baixada SA',
            'cnpj' => '11222333000181',
            'registration_status' => RegistrationStatus::Closed->value,
        ])->assertCreated();

        $this->assertFalse($response->json('data.establishment.capture_enabled'));
        $this->assertSame(RegistrationStatus::Closed->value, $response->json('data.establishment.registration_status'));
    }

    /** BUG-API-001: payload capture_enabled=true com status conhecido não-ativo é forçado para false. */
    public function test_create_ignora_capture_enabled_true_quando_status_nao_ativo(): void
    {
        [, $user] = $this->officeUser(OfficeRole::Operator);
        $this->actingAs($user);
        app(CurrentOffice::class)->resolve($user);

        foreach ([RegistrationStatus::Closed, RegistrationStatus::Suspended] as $status) {
            // CNPJs distintos válidos no mesmo root sintético de teste
            $cnpj = $status === RegistrationStatus::Closed
                ? '11222333000181'
                : EstablishmentFactory::cnpjWithRoot('11222333', '0002');

            $response = $this->postJson('/api/v1/clients', [
                'legal_name' => "Cliente {$status->value}",
                'cnpj' => $cnpj,
                'registration_status' => $status->value,
                'capture_enabled' => true,
            ])->assertCreated();

            $this->assertFalse(
                $response->json('data.establishment.capture_enabled'),
                "capture_enabled deveria ser false para status {$status->value}"
            );
            $this->assertSame($status->value, $response->json('data.establishment.registration_status'));
        }
    }

    /** BUG-API-002: soft-delete de establishment ainda ocupa unique → 409, não 500. */
    public function test_recriar_cnpj_apos_soft_delete_retorna_409(): void
    {
        [$office, $user] = $this->officeUser(OfficeRole::Operator);
        $this->actingAs($user);
        app(CurrentOffice::class)->resolve($user);

        $existing = Client::factory()->forOffice($office)->create(['root_cnpj' => '11222333']);
        $est = Establishment::factory()->forClient($existing, '11222333000181')->create();
        $est->delete();

        $this->assertSoftDeleted('establishments', ['id' => $est->id]);

        $this->postJson('/api/v1/clients', [
            'legal_name' => 'Tentativa recriação',
            'cnpj' => '11222333000181',
        ])
            ->assertStatus(409)
            ->assertJsonPath('data.existing_client_id', $existing->id)
            ->assertJsonPath('errors.cnpj.0', 'CNPJ já cadastrado neste escritório.');
    }

    /** BUG-API-003: is_primary=true + is_active=false → 422; principal anterior intacto. */
    public function test_contato_principal_inativo_retorna_422_e_preserva_principal(): void
    {
        [$office, $user] = $this->officeUser(OfficeRole::Operator);
        $this->actingAs($user);
        app(CurrentOffice::class)->resolve($user);

        $client = Client::factory()->forOffice($office)->create();

        $primary = $this->postJson("/api/v1/clients/{$client->id}/contacts", [
            'name' => 'Ana Principal',
            'email' => 'ana@example.com',
            'is_primary' => true,
        ])->assertCreated();

        $primaryId = $primary->json('data.id');

        $this->postJson("/api/v1/clients/{$client->id}/contacts", [
            'name' => 'Bruno Inativo',
            'email' => 'bruno@example.com',
            'is_primary' => true,
            'is_active' => false,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['is_primary', 'is_active']);

        $this->assertTrue(ClientContact::query()->find($primaryId)->is_primary);
        $this->assertTrue(ClientContact::query()->find($primaryId)->is_active);
        $this->assertDatabaseMissing('client_contacts', ['email' => 'bruno@example.com']);

        // Update: promover contato inativo a principal também 422
        $other = $this->postJson("/api/v1/clients/{$client->id}/contacts", [
            'name' => 'Carla',
            'email' => 'carla@example.com',
            'is_primary' => false,
            'is_active' => true,
        ])->assertCreated();

        $this->patchJson("/api/v1/clients/{$client->id}/contacts/{$other->json('data.id')}", [
            'is_primary' => true,
            'is_active' => false,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['is_primary', 'is_active']);

        $this->assertTrue(ClientContact::query()->find($primaryId)->fresh()->is_primary);
    }

    /** BUG-API-005: filial (matrix_client_id set) não pode virar is_matrix via PATCH. */
    public function test_filial_nao_pode_marcar_estabelecimento_como_matriz(): void
    {
        [$office, $user] = $this->officeUser(OfficeRole::Operator);
        $this->actingAs($user);
        app(CurrentOffice::class)->resolve($user);

        $matrix = Client::factory()->forOffice($office)->create([
            'legal_name' => 'Matriz LTDA',
            'root_cnpj' => '11222333',
        ]);
        Establishment::factory()->forClient($matrix, '11222333000181')->create(['is_matrix' => true]);

        $branchCnpj = EstablishmentFactory::cnpjWithRoot('11222333', '0002');
        $created = $this->postJson('/api/v1/clients', [
            'legal_name' => 'Filial LTDA',
            'cnpj' => $branchCnpj,
            'matrix_client_id' => $matrix->id,
        ])->assertCreated();

        $estId = $created->json('data.establishment.id');
        $this->assertFalse((bool) $created->json('data.establishment.is_matrix'));

        $this->patchJson("/api/v1/establishments/{$estId}", [
            'is_matrix' => true,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['is_matrix']);

        $this->assertFalse(Establishment::query()->find($estId)->is_matrix);
    }

    /** BUG-API-008: matrix_client_id proibido no update do cliente. */
    public function test_matrix_client_id_proibido_no_patch_cliente(): void
    {
        [$office, $user] = $this->officeUser(OfficeRole::Operator);
        $this->actingAs($user);
        app(CurrentOffice::class)->resolve($user);

        $matrix = Client::factory()->forOffice($office)->create(['root_cnpj' => '11222333']);
        // Segundo cliente com raiz distinta (unique parcial por root canônico).
        $client = Client::factory()->forOffice($office)->create([
            'root_cnpj' => '04252011',
            'matrix_client_id' => null,
        ]);

        $this->patchJson("/api/v1/clients/{$client->id}", [
            'matrix_client_id' => $matrix->id,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['matrix_client_id']);
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

    public function test_raiz_e_cnpj_imutaveis_no_patch(): void
    {
        [$office, $user] = $this->officeUser(OfficeRole::Operator);
        $this->actingAs($user);
        app(CurrentOffice::class)->resolve($user);

        $client = Client::factory()->forOffice($office)->create(['legal_name' => 'Antes']);
        $est = Establishment::factory()->forClient($client)->create();

        $this->patchJson("/api/v1/clients/{$client->id}", [
            'legal_name' => 'Depois',
            'root_cnpj' => '99999999',
        ])->assertStatus(422);

        $this->patchJson("/api/v1/clients/{$client->id}", [
            'legal_name' => 'Depois',
        ])->assertOk()->assertJsonPath('data.legal_name', 'Depois');

        $this->patchJson("/api/v1/establishments/{$est->id}", [
            'cnpj' => '04252011000110',
            'trade_name' => 'Novo',
        ])->assertStatus(422);
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
