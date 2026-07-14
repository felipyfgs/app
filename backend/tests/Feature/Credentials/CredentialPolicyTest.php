<?php

namespace Tests\Feature\Credentials;

use App\Contracts\PfxReaderInterface;
use App\Enums\CredentialStatus;
use App\Enums\OfficeRole;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\ClientCredential;
use App\Models\Office;
use App\Models\User;
use App\Support\CurrentOffice;
use Carbon\CarbonImmutable;
use Database\Factories\EstablishmentFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Mockery;
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

    /**
     * BUG-SEC-001: em falha de activate, context de audit_logs não deve conter a senha
     * (nem a chave password — segredo não entra no pipeline de auditoria).
     */
    public function test_activate_falha_nao_grava_password_no_audit(): void
    {
        $office = Office::factory()->create();
        $admin = User::factory()->forOffice($office, OfficeRole::Admin)->withTwoFactorConfirmed()->create();
        $client = Client::factory()->forOffice($office)->create(['root_cnpj' => '11222333']);

        $this->actingAs($admin);
        app(CurrentOffice::class)->resolve($admin);

        $secretPassword = 'super-secret-pfx-password-xyz-'.$client->id;

        $reader = Mockery::mock(PfxReaderInterface::class);
        $reader->shouldReceive('read')->once()->andReturn([
            'pfx' => 'fake-pfx-bytes',
            'password' => $secretPassword,
            'subject_name' => 'OUTRA EMPRESA',
            'cnpj' => EstablishmentFactory::cnpjWithRoot('99888777'),
            'fingerprint_sha256' => str_repeat('D', 64),
            'valid_from' => CarbonImmutable::now()->subYear(),
            'valid_to' => CarbonImmutable::now()->addYear(),
        ]);
        $this->app->instance(PfxReaderInterface::class, $reader);

        $response = $this->post("/api/v1/clients/{$client->id}/credential", [
            'pfx' => UploadedFile::fake()->create('cert.pfx', 32, 'application/x-pkcs12'),
            'password' => $secretPassword,
        ], ['Accept' => 'application/json']);

        $response->assertStatus(422);

        $log = AuditLog::query()
            ->where('action', 'credential.activate')
            ->where('result', 'FAILED')
            ->orderByDesc('id')
            ->first();

        $this->assertNotNull($log, 'Esperava audit_log de credential.activate FAILED');
        $context = $log->context ?? [];
        $this->assertIsArray($context);
        $this->assertArrayNotHasKey('password', $context);
        $this->assertArrayHasKey('message', $context);

        $contextJson = json_encode($context, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString($secretPassword, $contextJson);
        $this->assertStringNotContainsString('"password"', $contextJson);
    }

    /**
     * BUG-SEC-004: Client::credential() só devolve ACTIVE; histórico via credentials().
     */
    public function test_client_credential_relation_only_active(): void
    {
        $office = Office::factory()->create();
        $client = Client::factory()->forOffice($office)->create(['root_cnpj' => '11222333']);

        $superseded = ClientCredential::query()->create([
            'office_id' => $office->id,
            'client_id' => $client->id,
            'status' => CredentialStatus::Superseded,
            'subject_name' => 'OLD',
            'holder_cnpj' => '11222333000181',
            'fingerprint_sha256' => str_repeat('1', 64),
            'valid_from' => now()->subYears(2),
            'valid_to' => now()->addMonth(),
            'vault_object_id' => '00000000000000000000000000',
            'activated_at' => now()->subYear(),
            'superseded_at' => now()->subDay(),
        ]);

        $active = ClientCredential::query()->create([
            'office_id' => $office->id,
            'client_id' => $client->id,
            'status' => CredentialStatus::Active,
            'subject_name' => 'NEW',
            'holder_cnpj' => '11222333000181',
            'fingerprint_sha256' => str_repeat('2', 64),
            'valid_from' => now()->subMonth(),
            'valid_to' => now()->addYear(),
            'vault_object_id' => 'vaultobjactive01abcdefghij',
            'activated_at' => now()->subDay(),
        ]);

        $client->unsetRelation('credential');
        $client->unsetRelation('credentials');

        $this->assertNotNull($client->credential);
        $this->assertSame($active->id, $client->credential->id);
        $this->assertSame(CredentialStatus::Active, $client->credential->status);

        $this->assertSame(2, $client->credentials()->count());
        $this->assertTrue($client->credentials->contains('id', $superseded->id));

        $active->status = CredentialStatus::Expired;
        $active->save();
        $client->unsetRelation('credential');

        $this->assertNull($client->fresh()->credential);
    }
}
