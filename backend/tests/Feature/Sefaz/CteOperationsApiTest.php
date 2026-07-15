<?php

namespace Tests\Feature\Sefaz;

use App\Enums\CaptureChannel;
use App\Enums\OfficeFiscalIdentityStatus;
use App\Enums\OfficeRole;
use App\Enums\SyncCursorStatus;
use App\Jobs\RepairKnownCteNsuJob;
use App\Models\ChannelSyncCursor;
use App\Models\Client;
use App\Models\Establishment;
use App\Models\Office;
use App\Models\OfficeFiscalIdentity;
use App\Models\User;
use App\Support\CurrentOffice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CteOperationsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_onboarding_saude_e_cobertura_sao_tenant_safe(): void
    {
        [$officeA, $userA] = $this->officeUser();
        [$officeB] = $this->officeUser();
        $clientA = Client::factory()->forOffice($officeA)->create();
        $estA = Establishment::factory()->forClient($clientA)->create();
        $clientB = Client::factory()->forOffice($officeB)->create();
        $estB = Establishment::factory()->forClient($clientB)->create();
        ChannelSyncCursor::query()->create([
            'office_id' => $officeA->id,
            'establishment_id' => $estA->id,
            'environment' => 'production',
            'source' => 'SEFAZ',
            'channel' => CaptureChannel::CteDistDfe,
            'last_nsu' => 10,
            'status' => SyncCursorStatus::Idle,
        ]);
        ChannelSyncCursor::query()->create([
            'office_id' => $officeB->id,
            'establishment_id' => $estB->id,
            'environment' => 'production',
            'source' => 'SEFAZ',
            'channel' => CaptureChannel::CteDistDfe,
            'last_nsu' => 99,
            'status' => SyncCursorStatus::Blocked,
        ]);
        OfficeFiscalIdentity::query()->create([
            'office_id' => $officeA->id,
            'cnpj' => '12345678000190',
            'root_cnpj' => '12345678',
            'status' => OfficeFiscalIdentityStatus::Active,
            'legal_name' => 'Escritório A',
        ]);

        $this->actingAs($userA);
        app(CurrentOffice::class)->resolve($userA);

        $this->getJson('/api/v1/cte/onboarding')
            ->assertOk()
            ->assertJsonPath('data.office_cnpj', '12345678000190')
            ->assertJsonMissing(['vault_object_id']);
        $this->getJson('/api/v1/cte/health?office_id='.$officeB->id)
            ->assertOk()
            ->assertJsonPath('data.summary.client_streams', 1)
            ->assertJsonPath('data.metrics.channels.CTE_DISTDFE.streams', 1)
            ->assertJsonPath('data.channels.CTE_DISTDFE.0.last_nsu', 10)
            ->assertJsonMissing(['last_nsu' => 99]);
        $this->getJson('/api/v1/cte/coverage?period=2026-07&office_id='.$officeB->id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.client_id', $clientA->id)
            ->assertJsonPath('data.0.status', 'NO_ACTIVITY');
    }

    public function test_readiness_somente_leitura_nao_expoe_material_fiscal(): void
    {
        [$office] = $this->officeUser();
        $exit = Artisan::call('sefaz:cte-readiness', [
            '--office' => $office->id,
            '--period' => '2026-07',
            '--json' => true,
        ]);
        $output = Artisan::output();

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('CTE_DISTDFE', $output);
        $this->assertDoesNotMatchRegularExpression('/BEGIN |PRIVATE KEY|<cte|docZip|vault_object_id|password|Bearer/i', $output);
    }

    public function test_viewer_le_metadados_sem_acoes_de_escrita(): void
    {
        $office = Office::factory()->create();
        $viewer = User::factory()->forOffice($office, OfficeRole::Viewer)->withTwoFactorConfirmed()->create();
        $this->actingAs($viewer);
        app(CurrentOffice::class)->resolve($viewer);

        $this->getJson('/api/v1/cte/health')->assertOk();
        $this->getJson('/api/v1/cte/pending')->assertOk();
        $this->postJson('/api/v1/cte/repairs', ['cursor_id' => 1, 'nsu' => 10])
            ->assertForbidden();
    }

    public function test_operator_enfileira_reparo_conhecido_sem_injetar_office_e_quiet_bloqueia(): void
    {
        $office = Office::factory()->create();
        $operator = User::factory()->forOffice($office, OfficeRole::Operator)->withTwoFactorConfirmed()->create();
        $client = Client::factory()->forOffice($office)->create();
        $establishment = Establishment::factory()->forClient($client)->create();
        $cursor = ChannelSyncCursor::query()->create([
            'office_id' => $office->id,
            'establishment_id' => $establishment->id,
            'environment' => 'production',
            'source' => 'SEFAZ',
            'channel' => CaptureChannel::CteDistDfe,
            'last_nsu' => 90,
            'status' => SyncCursorStatus::Idle,
            'next_sync_at' => now()->subMinute(),
        ]);
        $otherOffice = Office::factory()->create();

        $this->actingAs($operator);
        app(CurrentOffice::class)->resolve($operator);
        Queue::fake();

        $this->postJson('/api/v1/cte/repairs', [
            'cursor_id' => $cursor->id,
            'nsu' => 42,
            'office_id' => $otherOffice->id,
        ])->assertAccepted()
            ->assertJsonPath('data.cursor_last_nsu', 90)
            ->assertJsonPath('data.nsu', 42);
        Queue::assertPushed(RepairKnownCteNsuJob::class, fn (RepairKnownCteNsuJob $job) => $job->channelSyncCursorId === $cursor->id && $job->knownNsu === 42
        );

        $cursor->update(['next_sync_at' => now()->addHour()]);
        $this->postJson('/api/v1/cte/repairs', [
            'cursor_id' => $cursor->id,
            'nsu' => 43,
        ])->assertStatus(422);
    }

    /** @return array{0: Office, 1: User} */
    private function officeUser(): array
    {
        $office = Office::factory()->create();
        $user = User::factory()->forOffice($office, OfficeRole::Admin)->withTwoFactorConfirmed()->create();

        return [$office, $user];
    }
}
