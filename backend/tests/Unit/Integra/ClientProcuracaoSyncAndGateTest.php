<?php

namespace Tests\Unit\Integra;

use App\Enums\AuthorIdentityType;
use App\Enums\ClientProcuracaoSyncStatus;
use App\Enums\SerproAuthorizationStatus;
use App\Enums\SerproEnvironment;
use App\Enums\TaxProxyPowerSource;
use App\Enums\TaxProxyPowerStatus;
use App\Models\Client;
use App\Models\ClientProcuracaoSnapshot;
use App\Models\Establishment;
use App\Models\Office;
use App\Models\OfficeSerproAuthorization;
use App\Models\TaxProxyPower;
use App\Services\Integra\ClientProcuracaoSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Support\SerproTestDoubleServiceProvider;
use Tests\TestCase;

class ClientProcuracaoSyncAndGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_override_is_rejected(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('proibido');

        app(ClientProcuracaoSyncService::class)->rejectManualOverride();
    }

    public function test_operation_without_required_power_continues(): void
    {
        $office = Office::factory()->create();
        $client = Client::factory()->create(['office_id' => $office->id, 'root_cnpj' => '11222333000181']);

        ClientProcuracaoSnapshot::query()->create([
            'office_id' => $office->id,
            'client_id' => $client->id,
            'environment' => SerproEnvironment::Trial,
            'status' => ClientProcuracaoSyncStatus::Missing,
        ]);

        $gate = app(ClientProcuracaoSyncService::class)->gateForOperation(
            $office,
            $client,
            SerproEnvironment::Trial,
            requiredPowers: [],
            proxyRule: 'NOT_APPLICABLE',
        );

        $this->assertTrue($gate['allowed']);
    }

    public function test_expired_blocks_only_when_power_required(): void
    {
        $office = Office::factory()->create();
        $client = Client::factory()->create(['office_id' => $office->id, 'root_cnpj' => '11222333000181']);

        ClientProcuracaoSnapshot::query()->create([
            'office_id' => $office->id,
            'client_id' => $client->id,
            'environment' => SerproEnvironment::Trial,
            'status' => ClientProcuracaoSyncStatus::Expired,
            'valid_to' => now()->subDay(),
            'last_verified_at' => now(),
        ]);

        $service = app(ClientProcuracaoSyncService::class);

        $noPower = $service->gateForOperation(
            $office,
            $client,
            SerproEnvironment::Trial,
            [],
            'NOT_APPLICABLE',
        );
        $this->assertTrue($noPower['allowed']);

        $withPower = $service->gateForOperation(
            $office,
            $client,
            SerproEnvironment::Trial,
            ['00002'],
            'REQUIRED',
        );
        $this->assertFalse($withPower['allowed']);
        $this->assertSame('PROXY_POWER_EXPIRED', $withPower['code']);
    }

    public function test_official_sync_projects_four_statuses(): void
    {
        $this->app->register(SerproTestDoubleServiceProvider::class);

        $office = Office::factory()->create();
        $client = Client::factory()->create(['office_id' => $office->id, 'root_cnpj' => '11222333']);
        Establishment::factory()->create([
            'office_id' => $office->id,
            'client_id' => $client->id,
            'cnpj' => '11222333000181',
            'is_active' => true,
            'is_matrix' => true,
        ]);
        $auth = OfficeSerproAuthorization::query()->create([
            'office_id' => $office->id,
            'environment' => SerproEnvironment::Trial,
            'status' => SerproAuthorizationStatus::TokenActive,
            'author_identity_type' => AuthorIdentityType::Cnpj,
            'author_identity' => '11222333000181',
        ]);

        $service = app(ClientProcuracaoSyncService::class);

        // Double offline explícito não pode criar poderes nem alegar sync oficial.
        try {
            $service->syncOfficial($office, $client, SerproEnvironment::Trial);
            $this->fail('O double sintético não pode concluir sync oficial.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Resposta sintética', $e->getMessage());
        }

        // O restante da projeção testa estados persistidos por fonte externa,
        // sem promover o double local a evidência oficial.
        $snap = $service->getOrCreateSnapshot($office, $client, SerproEnvironment::Trial);

        // Force Expired projection
        $power = TaxProxyPower::query()->create([
            'office_id' => $office->id,
            'client_id' => $client->id,
            'office_serpro_authorization_id' => $auth->id,
            'environment' => SerproEnvironment::Trial->value,
            'author_identity' => $auth->author_identity,
            'contributor_cnpj' => '11222333000181',
            'system_code' => 'SITFIS',
            'power_code' => '00002',
            'source' => TaxProxyPowerSource::IntegraProcuracoes,
            'status' => TaxProxyPowerStatus::Expired,
            'valid_to' => now()->subDay(),
            'evidence_ref' => 'test-exp',
        ]);
        $projected = $service->projectFromPowers($snap, $office, $client, SerproEnvironment::Trial, $auth, [$power]);
        $this->assertSame(ClientProcuracaoSyncStatus::Expired, $projected->status);

        // Active official → Authorized
        $power->status = TaxProxyPowerStatus::Active;
        $power->valid_to = now()->addYear();
        $power->save();
        $projected = $service->projectFromPowers($snap, $office, $client, SerproEnvironment::Trial, $auth, [$power->fresh()]);
        $this->assertSame(ClientProcuracaoSyncStatus::Authorized, $projected->status);

        // Empty → Missing
        $projected = $service->projectFromPowers($snap, $office, $client, SerproEnvironment::Trial, $auth, []);
        $this->assertSame(ClientProcuracaoSyncStatus::Missing, $projected->status);

        $projection = $service->projectForClient($office, $client, SerproEnvironment::Trial);
        $this->assertArrayHasKey('label', $projection);
        $this->assertArrayHasKey('status', $projection);
    }

    public function test_manual_power_does_not_authorize_projection(): void
    {
        $office = Office::factory()->create();
        $client = Client::factory()->create(['office_id' => $office->id, 'root_cnpj' => '11222333000181']);
        $auth = OfficeSerproAuthorization::query()->create([
            'office_id' => $office->id,
            'environment' => SerproEnvironment::Trial,
            'status' => SerproAuthorizationStatus::TokenActive,
            'author_identity_type' => AuthorIdentityType::Cnpj,
            'author_identity' => '11222333000181',
        ]);

        $manual = TaxProxyPower::query()->create([
            'office_id' => $office->id,
            'client_id' => $client->id,
            'office_serpro_authorization_id' => $auth->id,
            'environment' => SerproEnvironment::Trial->value,
            'author_identity' => $auth->author_identity,
            'contributor_cnpj' => '11222333000181',
            'system_code' => 'SITFIS',
            'power_code' => '00002',
            'source' => TaxProxyPowerSource::ManualOfficialEvidence,
            'status' => TaxProxyPowerStatus::Active,
            'valid_to' => now()->addYear(),
            'evidence_ref' => 'manual',
        ]);

        $service = app(ClientProcuracaoSyncService::class);
        $snap = $service->getOrCreateSnapshot($office, $client, SerproEnvironment::Trial);
        $projected = $service->projectFromPowers($snap, $office, $client, SerproEnvironment::Trial, $auth, [$manual]);

        $this->assertNotSame(ClientProcuracaoSyncStatus::Authorized, $projected->status);
    }
}
