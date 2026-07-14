<?php

namespace Tests\Feature\Sync;

use App\Contracts\AdnContributorClient;
use App\Contracts\SecureObjectStore;
use App\Domain\Adn\DistributionDocumentDto;
use App\Domain\Adn\DistributionPageDto;
use App\Enums\AdnDocumentType;
use App\Enums\CredentialStatus;
use App\Enums\SyncCursorStatus;
use App\Jobs\SyncEstablishmentDistributionJob;
use App\Models\Client;
use App\Models\ClientCredential;
use App\Models\Establishment;
use App\Models\Office;
use App\Models\SyncCursor;
use App\Models\SyncRun;
use App\Services\Adn\DistributionPageProcessor;
use App\Services\Certificates\CredentialService;
use Database\Factories\EstablishmentFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class SyncLeaseAndEligibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_recupera_lease_waiting_expirado_sem_avancar_nsu(): void
    {
        config([
            'adn.job_timeout_seconds' => 60,
            'adn.lock_ttl_seconds' => 60,
            'adn.stale_lease_seconds' => 60,
        ]);

        $office = Office::factory()->create();
        $client = Client::factory()->forOffice($office)->create(['is_active' => true]);
        $est = Establishment::factory()->forClient($client)->create([
            'is_active' => true,
            'capture_enabled' => false,
        ]);

        $cursor = SyncCursor::query()->create([
            'office_id' => $office->id,
            'establishment_id' => $est->id,
            'environment' => config('adn.environment', 'restricted_production'),
            'last_nsu' => 77,
            'status' => SyncCursorStatus::Waiting,
            'locked_at' => now()->subSeconds(200),
            'lock_owner' => 'dead-worker-lease',
            'next_sync_at' => now()->subMinute(),
            'attempts' => 0,
        ]);

        Queue::fake();

        $this->artisan('adn:dispatch-due-syncs')->assertSuccessful();

        $cursor->refresh();
        $this->assertSame(SyncCursorStatus::Error, $cursor->status);
        $this->assertNull($cursor->lock_owner);
        $this->assertNull($cursor->locked_at);
        $this->assertSame(77, $cursor->last_nsu);
        $this->assertSame(1, $cursor->attempts);
        $this->assertStringContainsString('Lease', (string) $cursor->last_error);
        Queue::assertNothingPushed();
    }

    public function test_recupera_lease_running_expirado_e_falha_sync_run(): void
    {
        config([
            'adn.job_timeout_seconds' => 60,
            'adn.lock_ttl_seconds' => 60,
            'adn.stale_lease_seconds' => 60,
        ]);

        $office = Office::factory()->create();
        $client = Client::factory()->forOffice($office)->create(['is_active' => true]);
        $est = Establishment::factory()->forClient($client)->create([
            'is_active' => true,
            'capture_enabled' => false,
        ]);

        $cursor = SyncCursor::query()->create([
            'office_id' => $office->id,
            'establishment_id' => $est->id,
            'environment' => config('adn.environment', 'restricted_production'),
            'last_nsu' => 12,
            'status' => SyncCursorStatus::Running,
            'locked_at' => now()->subSeconds(200),
            'lock_owner' => 'running-owner',
            'next_sync_at' => now()->subMinute(),
            'attempts' => 0,
        ]);

        $run = SyncRun::query()->create([
            'office_id' => $office->id,
            'sync_cursor_id' => $cursor->id,
            'status' => 'RUNNING',
            'trigger' => 'SCHEDULED',
            'from_nsu' => 12,
            'started_at' => now()->subMinutes(5),
        ]);

        $this->artisan('adn:dispatch-due-syncs')->assertSuccessful();

        $cursor->refresh();
        $run->refresh();
        $this->assertSame(SyncCursorStatus::Error, $cursor->status);
        $this->assertNull($cursor->lock_owner);
        $this->assertSame(12, $cursor->last_nsu);
        $this->assertSame('FAILED', $run->status);
        $this->assertNotNull($run->finished_at);
        $this->assertStringContainsString('Lease', (string) $run->error_message);
    }

    public function test_job_inelegivel_libera_lease_sem_chamar_adn_nem_alterar_nsu(): void
    {
        $office = Office::factory()->create();
        $client = Client::factory()->forOffice($office)->create(['is_active' => true]);
        $est = Establishment::factory()->forClient($client)->create([
            'is_active' => true,
            'capture_enabled' => false,
        ]);
        $this->seedCredential($client, $est);

        $leaseOwner = (string) Str::uuid();
        $cursor = SyncCursor::query()->create([
            'office_id' => $office->id,
            'establishment_id' => $est->id,
            'environment' => config('adn.environment', 'restricted_production'),
            'last_nsu' => 55,
            'status' => SyncCursorStatus::Waiting,
            'locked_at' => now(),
            'lock_owner' => $leaseOwner,
            'next_sync_at' => now(),
        ]);

        $adn = Mockery::mock(AdnContributorClient::class);
        $adn->shouldNotReceive('distribution');
        $this->app->instance(AdnContributorClient::class, $adn);

        (new SyncEstablishmentDistributionJob($cursor->id, 'SCHEDULED', null, $leaseOwner))
            ->handle(
                $adn,
                app(DistributionPageProcessor::class),
                app(CredentialService::class),
            );

        $cursor->refresh();
        $this->assertSame(55, $cursor->last_nsu);
        $this->assertNull($cursor->lock_owner);
        $this->assertNull($cursor->locked_at);
        $this->assertSame(SyncCursorStatus::Idle, $cursor->status);
        $this->assertStringContainsString('Captura desabilitada', (string) $cursor->last_error);
        $this->assertSame(0, SyncRun::query()->count());
    }

    public function test_has_more_nao_reenfileira_quando_fica_inelegivel(): void
    {
        config(['adn.max_pages_per_job' => 1]);

        $office = Office::factory()->create();
        $client = Client::factory()->forOffice($office)->create(['is_active' => true, 'root_cnpj' => '11222333']);
        $est = Establishment::factory()
            ->forClient($client, EstablishmentFactory::cnpjWithRoot('11222333'))
            ->create([
                'is_active' => true,
                'capture_enabled' => true,
            ]);
        $this->seedCredential($client, $est);

        $leaseOwner = (string) Str::uuid();
        $cursor = SyncCursor::query()->create([
            'office_id' => $office->id,
            'establishment_id' => $est->id,
            'environment' => config('adn.environment', 'restricted_production'),
            'last_nsu' => 0,
            'status' => SyncCursorStatus::Waiting,
            'locked_at' => now(),
            'lock_owner' => $leaseOwner,
            'next_sync_at' => now(),
        ]);

        $payloadB64 = base64_encode(gzencode(
            file_get_contents(base_path('tests/fixtures/adn/nfse_issuer.xml'))
        ));

        $adn = Mockery::mock(AdnContributorClient::class);
        $adn->shouldReceive('distribution')
            ->once()
            ->andReturnUsing(function () use ($est, $payloadB64) {
                $est->capture_enabled = false;
                $est->save();

                return new DistributionPageDto(
                    status: '138',
                    maxNsu: 100,
                    ultimoNsu: 1,
                    documents: [
                        new DistributionDocumentDto(1, AdnDocumentType::Nfse, 'NFSe_v1.00.xsd', $payloadB64),
                    ],
                    hasMore: true,
                );
            });
        $this->app->instance(AdnContributorClient::class, $adn);

        Queue::fake();

        (new SyncEstablishmentDistributionJob($cursor->id, 'SCHEDULED', null, $leaseOwner))
            ->handle(
                $adn,
                app(DistributionPageProcessor::class),
                app(CredentialService::class),
            );

        $cursor->refresh();
        $this->assertSame(1, $cursor->last_nsu);
        $this->assertSame(SyncCursorStatus::Idle, $cursor->status);
        $this->assertNull($cursor->lock_owner);
        $this->assertStringContainsString('Captura desabilitada', (string) $cursor->last_error);
        Queue::assertNotPushed(SyncEstablishmentDistributionJob::class);
    }

    private function seedCredential(Client $client, Establishment $est): void
    {
        $store = app(SecureObjectStore::class);
        $vaultId = $store->put(json_encode([
            'pfx' => base64_encode('test-pfx-bytes'),
            'password' => 'secret',
        ], JSON_THROW_ON_ERROR), [
            'office_id' => $client->office_id,
            'client_id' => $client->id,
            'fingerprint' => hash('sha256', 'job-elig-'.$client->id),
        ]);

        ClientCredential::query()->create([
            'office_id' => $client->office_id,
            'client_id' => $client->id,
            'status' => CredentialStatus::Active,
            'subject_name' => $client->legal_name,
            'holder_cnpj' => $est->cnpj,
            'fingerprint_sha256' => hash('sha256', 'job-elig-'.$client->id),
            'valid_from' => now()->subYear(),
            'valid_to' => now()->addYear(),
            'vault_object_id' => $vaultId,
            'activated_at' => now(),
        ]);
    }
}
