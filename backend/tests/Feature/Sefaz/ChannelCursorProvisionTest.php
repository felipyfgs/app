<?php

namespace Tests\Feature\Sefaz;

use App\Enums\CaptureChannel;
use App\Enums\CredentialStatus;
use App\Enums\OfficeRole;
use App\Enums\SyncCursorStatus;
use App\Jobs\SyncSefazDistDfeJob;
use App\Models\ChannelSyncCursor;
use App\Models\Client;
use App\Models\ClientCredential;
use App\Models\Establishment;
use App\Models\Office;
use App\Models\User;
use App\Services\Sefaz\ChannelSyncCursorService;
use App\Support\CurrentOffice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ChannelCursorProvisionTest extends TestCase
{
    use RefreshDatabase;

    public function test_trigger_manual_cria_cursor_nfe_e_enfileira_job(): void
    {
        config(['sefaz.distdfe_enabled' => true, 'sefaz.cte_enabled' => false]);
        Queue::fake();

        [$office, $user] = $this->officeUser();
        $this->actingAs($user);
        app(CurrentOffice::class)->resolve($user);

        $client = Client::factory()->forOffice($office)->create(['is_active' => true]);
        $est = Establishment::factory()->forClient($client)->create([
            'is_active' => true,
            'capture_enabled' => true,
        ]);
        $this->seedActiveCredential($client);

        $this->assertSame(0, ChannelSyncCursor::query()->count());

        $this->postJson('/api/v1/sync-runs', ['establishment_id' => $est->id])
            ->assertStatus(202)
            ->assertJsonPath('data.adn_dispatched', true)
            ->assertJsonPath('data.sefaz_channels.0.channel', CaptureChannel::NfeDistDfe->value)
            ->assertJsonPath('data.sefaz_channels.0.dispatched', true);

        $cursor = ChannelSyncCursor::query()->first();
        $this->assertNotNull($cursor);
        $this->assertSame(CaptureChannel::NfeDistDfe, $cursor->channel);
        $this->assertSame($est->id, $cursor->establishment_id);
        $this->assertSame(0, $cursor->last_nsu);

        Queue::assertPushed(SyncSefazDistDfeJob::class, function (SyncSefazDistDfeJob $job) use ($cursor) {
            return $job->channelSyncCursorId === $cursor->id && $job->trigger === 'MANUAL';
        });
    }

    public function test_scheduler_provisiona_cursor_ausente_de_elegivel(): void
    {
        config(['sefaz.distdfe_enabled' => true, 'sefaz.cte_enabled' => false]);
        Queue::fake();

        $office = Office::factory()->create();
        $client = Client::factory()->forOffice($office)->create(['is_active' => true]);
        $est = Establishment::factory()->forClient($client)->create([
            'is_active' => true,
            'capture_enabled' => true,
        ]);
        $this->seedActiveCredential($client);

        $this->assertSame(0, ChannelSyncCursor::query()->count());

        $this->artisan('sefaz:dispatch-due-syncs')->assertSuccessful();

        $cursor = ChannelSyncCursor::query()
            ->where('establishment_id', $est->id)
            ->where('channel', CaptureChannel::NfeDistDfe->value)
            ->first();
        $this->assertNotNull($cursor);
        $this->assertSame(SyncCursorStatus::Idle, $cursor->status);

        // Cursor recém-criado com next_sync_at=now deve ser enfileirado no mesmo tick.
        Queue::assertPushed(SyncSefazDistDfeJob::class);
    }

    public function test_ensure_nao_duplica_cursor_existente(): void
    {
        config(['sefaz.distdfe_enabled' => true]);

        $office = Office::factory()->create();
        $client = Client::factory()->forOffice($office)->create(['is_active' => true]);
        $est = Establishment::factory()->forClient($client)->create([
            'is_active' => true,
            'capture_enabled' => true,
        ]);

        $service = app(ChannelSyncCursorService::class);
        $a = $service->ensure($est, CaptureChannel::NfeDistDfe);
        $b = $service->ensure($est, CaptureChannel::NfeDistDfe);

        $this->assertSame($a->id, $b->id);
        $this->assertSame(1, ChannelSyncCursor::query()->count());
    }

    /**
     * @return array{0: Office, 1: User}
     */
    private function officeUser(): array
    {
        $office = Office::factory()->create();
        $user = User::factory()->forOffice($office, OfficeRole::Operator)->withTwoFactorConfirmed()->create();

        return [$office, $user];
    }

    private function seedActiveCredential(Client $client): void
    {
        ClientCredential::query()->create([
            'office_id' => $client->office_id,
            'client_id' => $client->id,
            'status' => CredentialStatus::Active,
            'subject_name' => 'CN=TEST',
            'holder_cnpj' => str_pad(substr((string) $client->root_cnpj, 0, 8), 14, '0'),
            'fingerprint_sha256' => hash('sha256', 'provision-test-'.$client->id),
            'valid_from' => now()->subMonth(),
            'valid_to' => now()->addYear(),
            'vault_object_id' => '01TESTCREDPROVISION0000000001',
            'activated_at' => now(),
        ]);
    }
}
