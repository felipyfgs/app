<?php

namespace Tests\Feature\FiscalMonitoring;

use App\Contracts\EsocialEventClient;
use App\DTO\Fiscal\FiscalAdapterRequest;
use App\Enums\FiscalRunResult;
use App\Enums\FiscalTrigger;
use App\Enums\OfficeRole;
use App\Jobs\Fiscal\SyncFgtsEsocialCompetenceJob;
use App\Models\Client;
use App\Models\FiscalMonitoringRun;
use App\Models\Office;
use App\Models\User;
use App\Services\Esocial\DisabledEsocialEventClient;
use App\Services\Esocial\FgtsEsocialMonitoringService;
use App\Services\Esocial\FgtsEsocialSourceAdapter;
use App\Support\CurrentOffice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class FgtsEsocialDisabledRuntimeTest extends TestCase
{
    use RefreshDatabase;

    public function test_runtime_inclusive_testing_bloqueia_rota_job_e_adapter_sem_criar_run(): void
    {
        $office = Office::factory()->create();
        $client = Client::factory()->forOffice($office)->create();
        $admin = User::factory()->forOffice($office, OfficeRole::Admin)->withTwoFactorConfirmed()->create();

        $this->assertInstanceOf(DisabledEsocialEventClient::class, app(EsocialEventClient::class));
        $this->assertFalse(app(FgtsEsocialMonitoringService::class)->isSourceAvailable());

        $this->actingAs($admin);
        app(CurrentOffice::class)->resolve($admin);

        $payload = [
            'client_id' => $client->id,
            'competence_period_key' => '2026-07',
        ];

        $this->postJson('/api/v1/fiscal/fgts/sync', $payload)
            ->assertStatus(503)
            ->assertJsonPath('code', 'ESOCIAL_SOURCE_UNAVAILABLE');
        $this->postJson('/api/v1/fiscal/fgts/sync-now', $payload)
            ->assertStatus(503)
            ->assertJsonPath('code', 'ESOCIAL_SOURCE_UNAVAILABLE');

        $this->assertSame(0, FiscalMonitoringRun::query()->withoutGlobalScopes()->count());

        $result = app(FgtsEsocialSourceAdapter::class)->execute(new FiscalAdapterRequest(
            office: $office,
            client: $client,
            run: new FiscalMonitoringRun,
            systemCode: 'ESOCIAL',
            serviceCode: 'FGTS',
            operationCode: 'MONITOR',
            trigger: FiscalTrigger::Manual,
        ));
        $this->assertSame(FiscalRunResult::Blocked, $result->result);
        $this->assertSame('ESOCIAL_SOURCE_UNAVAILABLE', $result->errorCode);
        $this->assertNull($result->evidenceBytes);

        Log::spy();
        (new SyncFgtsEsocialCompetenceJob(
            officeId: (int) $office->id,
            clientId: (int) $client->id,
            competencePeriodKey: '2026-07',
        ))->handle(app(FgtsEsocialMonitoringService::class));
        Log::shouldHaveReceived('info')->once()->with(
            'fgts_esocial.job_skipped_source_unavailable',
            \Mockery::on(static fn (array $context): bool => $context['reason'] === 'ESOCIAL_SOURCE_UNAVAILABLE'),
        );
    }
}
