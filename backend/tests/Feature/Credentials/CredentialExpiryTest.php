<?php

namespace Tests\Feature\Credentials;

use App\Contracts\PfxReaderInterface;
use App\Contracts\SecureObjectStore;
use App\Enums\CredentialStatus;
use App\Enums\OfficeRole;
use App\Enums\SyncCursorStatus;
use App\Models\Client;
use App\Models\ClientCredential;
use App\Models\Establishment;
use App\Models\Office;
use App\Models\SyncCursor;
use App\Models\User;
use App\Services\Certificates\CredentialService;
use App\Support\CurrentOffice;
use Carbon\CarbonImmutable;
use Database\Factories\EstablishmentFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class CredentialExpiryTest extends TestCase
{
    use RefreshDatabase;

    public function test_refresh_marca_alertas_30_7_1_e_expira(): void
    {
        $office = Office::factory()->create();
        $client = Client::factory()->forOffice($office)->create(['root_cnpj' => '11222333']);
        $est = Establishment::factory()->forClient($client, EstablishmentFactory::cnpjWithRoot('11222333'))->create();
        $cursor = SyncCursor::query()->create([
            'office_id' => $office->id,
            'establishment_id' => $est->id,
            'environment' => 'test',
            'last_nsu' => 0,
            'status' => SyncCursorStatus::Idle,
        ]);

        ClientCredential::query()->create([
            'office_id' => $office->id,
            'client_id' => $client->id,
            'status' => CredentialStatus::Active,
            'subject_name' => 'A',
            'holder_cnpj' => $est->cnpj,
            'fingerprint_sha256' => str_repeat('A', 64),
            'valid_from' => now()->subYear(),
            'valid_to' => now()->addDays(20),
            'vault_object_id' => 'vaultobj01abcdefghijklmn',
            'activated_at' => now()->subMonth(),
        ]);

        $client2 = Client::factory()->forOffice($office)->create(['root_cnpj' => '22333444']);
        $est2 = Establishment::factory()->forClient($client2, EstablishmentFactory::cnpjWithRoot('22333444'))->create();
        $cursor2 = SyncCursor::query()->create([
            'office_id' => $office->id,
            'establishment_id' => $est2->id,
            'environment' => 'test',
            'last_nsu' => 0,
            'status' => SyncCursorStatus::Idle,
        ]);
        ClientCredential::query()->create([
            'office_id' => $office->id,
            'client_id' => $client2->id,
            'status' => CredentialStatus::Active,
            'subject_name' => 'B',
            'holder_cnpj' => $est2->cnpj,
            'fingerprint_sha256' => str_repeat('B', 64),
            'valid_from' => now()->subYear(),
            'valid_to' => now()->subDay(),
            'vault_object_id' => 'vaultobj02abcdefghijklmn',
            'activated_at' => now()->subMonth(),
        ]);

        $result = app(CredentialService::class)->refreshExpiryAlerts();

        $this->assertGreaterThanOrEqual(2, $result['credentials']);
        $this->assertSame(1, $result['cursors_blocked']);

        $active = ClientCredential::query()->where('client_id', $client->id)->first();
        $this->assertTrue($active->expires_alert_30);
        $this->assertFalse($active->expires_alert_7);

        $expired = ClientCredential::query()->where('client_id', $client2->id)->first();
        $this->assertSame(CredentialStatus::Expired, $expired->status);
        $this->assertSame(SyncCursorStatus::Blocked, $cursor2->fresh()->status);
        $this->assertSame(SyncCursorStatus::Idle, $cursor->fresh()->status);
    }

    public function test_activate_rejeita_raiz_divergente_e_nao_persiste(): void
    {
        $office = Office::factory()->create();
        $admin = User::factory()->forOffice($office, OfficeRole::Admin)->withTwoFactorConfirmed()->create();
        $client = Client::factory()->forOffice($office)->create(['root_cnpj' => '11222333']);

        $this->actingAs($admin);
        app(CurrentOffice::class)->resolve($admin);

        $reader = Mockery::mock(PfxReaderInterface::class);
        $reader->shouldReceive('read')->once()->andReturn([
            'pfx' => 'fake-pfx-bytes',
            'password' => 'secret-pass',
            'subject_name' => 'OUTRA EMPRESA',
            'cnpj' => EstablishmentFactory::cnpjWithRoot('99888777'),
            'fingerprint_sha256' => str_repeat('C', 64),
            'valid_from' => CarbonImmutable::now()->subYear(),
            'valid_to' => CarbonImmutable::now()->addYear(),
        ]);
        $this->app->instance(PfxReaderInterface::class, $reader);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('raiz');
        app(CredentialService::class)->activate($client, 'bytes', 'secret-pass');
    }

    public function test_rotacao_invalida_objeto_anterior(): void
    {
        $office = Office::factory()->create();
        $admin = User::factory()->forOffice($office, OfficeRole::Admin)->withTwoFactorConfirmed()->create();
        $client = Client::factory()->forOffice($office)->create(['root_cnpj' => '11222333']);

        $this->actingAs($admin);
        app(CurrentOffice::class)->resolve($admin);

        $store = app(SecureObjectStore::class);
        $oldId = $store->put(json_encode([
            'pfx' => base64_encode('old-pfx'),
            'password' => 'old-pass',
        ]), [
            'office_id' => $office->id,
            'client_id' => $client->id,
            'fingerprint' => str_repeat('1', 64),
        ]);

        $old = ClientCredential::query()->create([
            'office_id' => $office->id,
            'client_id' => $client->id,
            'status' => CredentialStatus::Active,
            'subject_name' => 'OLD',
            'holder_cnpj' => '11222333000181',
            'fingerprint_sha256' => str_repeat('1', 64),
            'valid_from' => now()->subYear(),
            'valid_to' => now()->addYear(),
            'vault_object_id' => $oldId,
            'activated_at' => now()->subMonth(),
        ]);

        $reader = Mockery::mock(PfxReaderInterface::class);
        $reader->shouldReceive('read')->once()->andReturn([
            'pfx' => 'new-pfx-bytes',
            'password' => 'new-pass',
            'subject_name' => 'NEW',
            'cnpj' => '11222333000181',
            'fingerprint_sha256' => str_repeat('2', 64),
            'valid_from' => CarbonImmutable::now()->subMonth(),
            'valid_to' => CarbonImmutable::now()->addYear(),
        ]);
        $this->app->instance(PfxReaderInterface::class, $reader);

        $new = app(CredentialService::class)->activate($client, 'new-bytes', 'new-pass');

        $this->assertSame(CredentialStatus::Active, $new->status);
        $this->assertSame(CredentialStatus::Superseded, $old->fresh()->status);
        $this->assertSame('00000000000000000000000000', $old->fresh()->vault_object_id);
        $this->assertFalse($store->exists($oldId));

        $material = app(CredentialService::class)->loadPfxMaterial($new);
        $this->assertSame('new-pfx-bytes', $material['pfx']);
        $this->assertSame('new-pass', $material['password']);
    }

    public function test_resposta_publica_sem_segredos(): void
    {
        $cred = new ClientCredential([
            'client_id' => 1,
            'status' => CredentialStatus::Active,
            'subject_name' => 'X',
            'holder_cnpj' => '11222333000181',
            'fingerprint_sha256' => str_repeat('F', 64),
            'valid_from' => now(),
            'valid_to' => now()->addYear(),
            'activated_at' => now(),
            'vault_object_id' => 'secret-object-id',
            'expires_alert_30' => false,
            'expires_alert_7' => false,
            'expires_alert_1' => false,
        ]);
        $cred->id = 9;

        $public = $cred->toPublicArray();
        $json = json_encode($public);
        $this->assertStringNotContainsString('secret-object-id', $json);
        $this->assertStringNotContainsString('password', $json);
        $this->assertStringNotContainsString('pfx', $json);
        $this->assertArrayNotHasKey('vault_object_id', $public);
    }
}
