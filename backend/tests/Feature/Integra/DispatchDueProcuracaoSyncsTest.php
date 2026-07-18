<?php

namespace Tests\Feature\Integra;

use App\Enums\AuthorCertificateMode;
use App\Enums\AuthorIdentityType;
use App\Enums\ClientProcuracaoSyncStatus;
use App\Enums\SerproAuthorizationStatus;
use App\Enums\SerproEnvironment;
use App\Jobs\Serpro\SyncClientProcuracaoJob;
use App\Models\Client;
use App\Models\ClientProcuracaoSync;
use App\Models\Office;
use App\Models\OfficeSerproAuthorization;
use App\Services\Audit\AuditLogger;
use App\Services\Integra\ClientProcuracaoAutoSyncPolicy;
use App\Services\Integra\ClientProcuracaoSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class DispatchDueProcuracaoSyncsTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_configuration_is_a_no_op(): void
    {
        Queue::fake();
        $office = Office::factory()->create();
        Client::factory()->forOffice($office)->create();

        $this->artisan('serpro:dispatch-due-procuracao-syncs')
            ->expectsOutputToContain('SCHEDULER_DISABLED=1')
            ->assertSuccessful();

        Queue::assertNothingPushed();
    }

    public function test_allowlist_authorization_freshness_and_batch_limit_control_dispatch(): void
    {
        Queue::fake();
        config()->set('serpro.procuracoes_scheduler.enabled', true);
        config()->set('serpro.procuracoes_scheduler.environment', 'TRIAL');
        config()->set('serpro.procuracoes_scheduler.max_age_hours', 24);
        config()->set('serpro.procuracoes_scheduler.batch_size', 1);
        config()->set('serpro.capabilities.authorization', 'real');

        $allowedOffice = Office::factory()->create();
        $blockedOffice = Office::factory()->create();
        config()->set('serpro.procuracoes_scheduler.office_allowlist', [$allowedOffice->id]);
        $this->authorization($allowedOffice, SerproAuthorizationStatus::TokenActive);
        $this->authorization($blockedOffice, SerproAuthorizationStatus::TokenActive);

        $due = Client::factory()->forOffice($allowedOffice)->create();
        $recent = Client::factory()->forOffice($allowedOffice)->create();
        $notAllowed = Client::factory()->forOffice($blockedOffice)->create();
        $this->sync($recent, now()->subHour());

        $this->artisan('serpro:dispatch-due-procuracao-syncs')->assertSuccessful();

        Queue::assertPushed(SyncClientProcuracaoJob::class, function (SyncClientProcuracaoJob $job) use ($due): bool {
            return $job->officeId === $due->office_id
                && $job->clientId === $due->id
                && $job->environment === SerproEnvironment::Trial->value
                && $job->automatic;
        });
        Queue::assertPushed(SyncClientProcuracaoJob::class, 1);
        $this->assertDatabaseMissing('client_procuracao_syncs', ['client_id' => $notAllowed->id]);
    }

    public function test_inapt_authorization_never_dispatches_or_is_created(): void
    {
        Queue::fake();
        config()->set('serpro.procuracoes_scheduler.enabled', true);
        config()->set('serpro.procuracoes_scheduler.environment', 'TRIAL');
        config()->set('serpro.procuracoes_scheduler.max_age_hours', 24);
        config()->set('serpro.capabilities.authorization', 'real');

        $office = Office::factory()->create();
        config()->set('serpro.procuracoes_scheduler.office_allowlist', [$office->id]);
        Client::factory()->forOffice($office)->create();

        $this->artisan('serpro:dispatch-due-procuracao-syncs')
            ->expectsOutputToContain('AUTHORIZATION_NOT_READY=1')
            ->assertSuccessful();

        Queue::assertNothingPushed();
        $this->assertDatabaseMissing('office_serpro_authorizations', ['office_id' => $office->id]);
    }

    public function test_automatic_job_rechecks_scheduler_permission_before_sync(): void
    {
        $office = Office::factory()->create();
        $client = Client::factory()->forOffice($office)->create();
        $job = new SyncClientProcuracaoJob(
            officeId: $office->id,
            clientId: $client->id,
            environment: SerproEnvironment::Trial->value,
            automatic: true,
        );

        // Simula a retirada da autorização entre o despacho e o consumo do job.
        config()->set('serpro.procuracoes_scheduler.enabled', false);
        $job->handle(
            app(ClientProcuracaoSyncService::class),
            app(AuditLogger::class),
            app(ClientProcuracaoAutoSyncPolicy::class),
        );

        $this->assertDatabaseMissing('client_procuracao_snapshots', ['client_id' => $client->id]);
        $this->assertDatabaseMissing('office_serpro_authorizations', ['office_id' => $office->id]);
    }

    private function authorization(Office $office, SerproAuthorizationStatus $status): void
    {
        OfficeSerproAuthorization::query()->create([
            'office_id' => $office->id,
            'environment' => SerproEnvironment::Trial,
            'status' => $status,
            'author_identity_type' => AuthorIdentityType::Cnpj,
            'author_identity' => '11222333000181',
            'certificate_mode' => AuthorCertificateMode::ExternalSignature,
        ]);
    }

    private function sync(Client $client, mixed $verifiedAt): void
    {
        ClientProcuracaoSync::query()->create([
            'office_id' => $client->office_id,
            'client_id' => $client->id,
            'status' => ClientProcuracaoSyncStatus::Authorized,
            'last_verified_at' => $verifiedAt,
            'last_check_result' => 'AUTHORIZED',
            'source' => 'official_sync',
        ]);
    }
}
