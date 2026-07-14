<?php

namespace Tests\Feature\Credentials;

use App\Enums\OfficeRole;
use App\Models\Client;
use App\Models\Office;
use App\Models\User;
use App\Support\CurrentOffice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CredentialPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_operador_nao_acessa_credencial(): void
    {
        $office = Office::factory()->create();
        $user = User::factory()->forOffice($office, OfficeRole::Operator)->create();
        $client = Client::factory()->forOffice($office)->create(['root_cnpj' => '11222333']);

        $this->actingAs($user);
        app(CurrentOffice::class)->resolve($user);

        $this->getJson("/api/v1/clients/{$client->id}/credential")->assertForbidden();
        $this->postJson("/api/v1/clients/{$client->id}/credential", [
            'password' => 'x',
        ])->assertForbidden();
    }

    public function test_resposta_nao_expoe_vault_object_id(): void
    {
        $office = Office::factory()->create();
        $admin = User::factory()->forOffice($office, OfficeRole::Admin)->withTwoFactorConfirmed()->create();
        $client = Client::factory()->forOffice($office)->create(['root_cnpj' => '11222333']);

        $this->actingAs($admin);
        app(CurrentOffice::class)->resolve($admin);

        $response = $this->getJson("/api/v1/clients/{$client->id}/credential");
        $response->assertOk();
        $this->assertArrayNotHasKey('vault_object_id', $response->json('data') ?? []);
        $content = $response->getContent();
        $this->assertStringNotContainsString('vault_object_id', (string) $content);
        $this->assertStringNotContainsString('private_key', (string) $content);
        $this->assertStringNotContainsString('BEGIN CERTIFICATE', (string) $content);
    }
}
