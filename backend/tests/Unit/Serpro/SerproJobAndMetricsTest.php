<?php

namespace Tests\Unit\Serpro;

use App\Jobs\Fiscal\RefreshRegistrationLinksJob;
use App\Jobs\Fiscal\RefreshTaxProcessesJob;
use App\Models\Client;
use App\Models\Office;
use App\Models\SerproAsyncJobRun;
use App\Services\Serpro\SerproJobFlagGuard;
use App\Services\Serpro\SerproMetricsExporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class SerproJobAndMetricsTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatch_if_allowed_queues_on_fiscal_and_creates_run(): void
    {
        Queue::fake();
        config([
            'serpro.capabilities.registrations' => 'real',
            'serpro.kill_switch' => false,
            'features.kill_switch' => false,
        ]);

        $office = Office::factory()->create();
        $client = Client::factory()->forOffice($office)->create();

        $job = RefreshRegistrationLinksJob::dispatchIfAllowed($office->id, $client->id, 'corr-test');
        $this->assertNotNull($job);
        Queue::assertPushed(RefreshRegistrationLinksJob::class, function (RefreshRegistrationLinksJob $j) use ($office, $client): bool {
            return $j->officeId === $office->id
                && $j->clientId === $client->id
                && $j->flagCheckedAtDispatch === true
                && $j->queue === 'fiscal';
        });

        $this->assertTrue(
            SerproAsyncJobRun::query()
                ->where('job_type', 'RefreshRegistrationLinksJob')
                ->where('office_id', $office->id)
                ->exists()
        );
    }

    public function test_dispatch_blocked_when_capability_disabled(): void
    {
        Queue::fake();
        config(['serpro.capabilities.tax_processes' => 'disabled']);

        $office = Office::factory()->create();
        $client = Client::factory()->forOffice($office)->create();

        $job = RefreshTaxProcessesJob::dispatchIfAllowed($office->id, $client->id);
        $this->assertNull($job);
        Queue::assertNothingPushed();
    }

    public function test_flag_guard_blocks_kill_switch(): void
    {
        config(['serpro.kill_switch' => true]);
        $guard = app(SerproJobFlagGuard::class);
        $check = $guard->assertAllowed('RefreshRegistrationLinksJob', 1);
        $this->assertFalse($check['allowed']);
        $this->assertSame('KILL_SWITCH', $check['code']);
    }

    public function test_metrics_snapshot_has_no_pii_keys(): void
    {
        $snap = app(SerproMetricsExporter::class)->snapshot();
        $json = json_encode($snap);
        $this->assertIsArray($snap);
        $this->assertArrayHasKey('breaker', $snap);
        $this->assertArrayHasKey('queues', $snap);
        $this->assertStringNotContainsString('cnpj', strtolower((string) $json));
        $this->assertStringNotContainsString('password', strtolower((string) $json));
        $this->assertStringNotContainsString('BEGIN CERTIFICATE', (string) $json);
        $this->assertStringNotContainsString('consumer_secret', strtolower((string) $json));
    }

    public function test_job_backoff_and_tries_configured(): void
    {
        $job = new RefreshRegistrationLinksJob(1, 2);
        $this->assertGreaterThanOrEqual(2, $job->tries);
        $this->assertNotEmpty($job->backoff());
        $this->assertSame('fiscal', $job->queue);
    }
}
