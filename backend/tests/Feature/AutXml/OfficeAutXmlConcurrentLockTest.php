<?php

namespace Tests\Feature\AutXml;

use App\Contracts\SecureObjectStore;
use App\Contracts\SefazDistDfeClient;
use App\Domain\Sefaz\DistDfePageDto;
use App\Enums\CaptureChannel;
use App\Enums\SyncCursorStatus;
use App\Jobs\SyncOfficeAutXmlDistDfeJob;
use App\Models\Client;
use App\Models\Establishment;
use App\Models\Office;
use App\Models\OfficeCredential;
use App\Models\OfficeDistributionCursor;
use App\Models\OfficeFiscalIdentity;
use App\Services\Certificates\OfficeCredentialResolver;
use App\Services\Sefaz\OfficeAutXmlPageProcessor;
use App\Services\Sefaz\OfficeDistributionCursorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

/**
 * Task 12.3 — dois workers e duas filiais da mesma raiz:
 * um cursor/lock e ausência de chamadas SEFAZ concorrentes.
 */
class OfficeAutXmlConcurrentLockTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_dois_workers_mesmo_cursor_so_um_chama_sefaz(): void
    {
        config([
            'sefaz.autxml.enabled' => true,
            'sefaz.autxml.kill_switch' => false,
            'sefaz.autxml.allow_all_offices' => true,
            'sefaz.autxml.office_allowlist' => [],
            'sefaz.autxml.page_sleep_seconds' => 0,
            'sefaz.autxml.max_pages_per_job' => 1,
        ]);

        $office = Office::factory()->create();
        $identity = OfficeFiscalIdentity::factory()->forOffice($office)->withCnpj('11222333000181')->create();
        $cursor = OfficeDistributionCursor::factory()->forIdentity($identity)->create([
            'environment' => 'production',
            'status' => SyncCursorStatus::Idle,
            'last_nsu' => 0,
            'next_sync_at' => now()->subMinute(),
            'external_consumer_status' => 'DECLARED_CLEAR',
        ]);
        $this->seedOfficeCredentialMaterial($identity);

        $lockKey = 'sefaz:autxml:root:'.$cursor->office_id.':'.$cursor->interested_root_cnpj.':'.$cursor->environment;
        $externalWorker = Cache::lock($lockKey, 120);
        $this->assertTrue($externalWorker->get(), 'Worker externo deve adquirir o lock do stream');

        $clientBlocked = Mockery::mock(SefazDistDfeClient::class);
        $clientBlocked->shouldReceive('distByNsu')->never();
        $this->app->instance(SefazDistDfeClient::class, $clientBlocked);

        // Worker B tenta o mesmo cursor enquanto o lock está com outro owner — sem chamada SEFAZ.
        (new SyncOfficeAutXmlDistDfeJob($cursor->id, 'TEST_WORKER_B'))->handle(
            app(SefazDistDfeClient::class),
            app(OfficeAutXmlPageProcessor::class),
            app(OfficeCredentialResolver::class),
        );

        $this->assertSame(0, $cursor->fresh()->last_nsu);
        $this->assertNull($cursor->fresh()->lock_owner);
        $this->assertSame(SyncCursorStatus::Idle, $cursor->fresh()->status);

        $externalWorker->release();

        $clientOk = Mockery::mock(SefazDistDfeClient::class);
        $clientOk->shouldReceive('distByNsu')
            ->once()
            ->andReturn(new DistDfePageDto(
                cStat: '137',
                xMotivo: 'Nenhum documento localizado',
                ultNsu: 0,
                maxNsu: 0,
                documents: [],
            ));
        $this->app->instance(SefazDistDfeClient::class, $clientOk);

        (new SyncOfficeAutXmlDistDfeJob($cursor->id, 'TEST_WORKER_A'))->handle(
            app(SefazDistDfeClient::class),
            app(OfficeAutXmlPageProcessor::class),
            app(OfficeCredentialResolver::class),
        );

        $fresh = $cursor->fresh();
        $this->assertNotNull($fresh->activated_at);
        $this->assertNull($fresh->lock_owner);
        $this->assertSame(SyncCursorStatus::Idle, $fresh->status);
    }

    public function test_kill_switch_impede_dispatch_sem_apagar_nsu(): void
    {
        config([
            'sefaz.autxml.enabled' => true,
            'sefaz.autxml.kill_switch' => true,
            'sefaz.autxml.allow_all_offices' => true,
            'sefaz.autxml.office_allowlist' => [],
            'sefaz.autxml.page_sleep_seconds' => 0,
        ]);

        $office = Office::factory()->create();
        $identity = OfficeFiscalIdentity::factory()->forOffice($office)->withCnpj('11222333000181')->create();
        $cursor = OfficeDistributionCursor::factory()->forIdentity($identity)->create([
            'environment' => 'production',
            'status' => SyncCursorStatus::Idle,
            'last_nsu' => 42,
            'next_sync_at' => now()->subMinute(),
            'external_consumer_status' => 'DECLARED_CLEAR',
        ]);
        $this->seedOfficeCredentialMaterial($identity);

        $client = Mockery::mock(SefazDistDfeClient::class);
        $client->shouldReceive('distByNsu')->never();
        $this->app->instance(SefazDistDfeClient::class, $client);

        (new SyncOfficeAutXmlDistDfeJob($cursor->id, 'KILL_SWITCH_DRILL'))->handle(
            app(SefazDistDfeClient::class),
            app(OfficeAutXmlPageProcessor::class),
            app(OfficeCredentialResolver::class),
        );

        $fresh = $cursor->fresh();
        $this->assertSame(42, $fresh->last_nsu);
        $this->assertNull($fresh->lock_owner);
        $this->assertSame(SyncCursorStatus::Idle, $fresh->status);

        Queue::fake();
        $this->artisan('sefaz:dispatch-due-autxml')->assertSuccessful();
        Queue::assertNothingPushed();
    }

    public function test_duas_filiais_mesma_raiz_um_cursor_e_um_job_enfileirado(): void
    {
        config([
            'sefaz.autxml.enabled' => true,
            'sefaz.autxml.kill_switch' => false,
            'sefaz.autxml.allow_all_offices' => true,
            'sefaz.autxml.office_allowlist' => [],
        ]);

        $office = Office::factory()->create();
        $identity = OfficeFiscalIdentity::factory()->forOffice($office)->withCnpj('11222333000181')->create();

        // Duas filiais (dois clientes/estabelecimentos) com a mesma raiz de emitente.
        // O cursor autXML do escritório permanece um por raiz/ambiente/canal do office.
        $client1 = Client::factory()->forOffice($office)->create(['root_cnpj' => '99888777']);
        Establishment::factory()->forClient($client1)->create([
            'office_id' => $office->id,
            'cnpj' => '99888777000166',
            'is_active' => true,
        ]);
        $client2 = Client::factory()->forOffice($office)->create(['root_cnpj' => '99888777']);
        Establishment::factory()->forClient($client2)->create([
            'office_id' => $office->id,
            'cnpj' => '99888777000247',
            'is_active' => true,
        ]);

        $service = app(OfficeDistributionCursorService::class);
        $c1 = $service->ensureForIdentity($identity, 'production');
        $c2 = $service->ensureForIdentity($identity, 'production');
        $this->assertSame($c1->id, $c2->id);

        $c1->forceFill([
            'status' => SyncCursorStatus::Idle,
            'next_sync_at' => now()->subMinute(),
            'external_consumer_status' => 'DECLARED_CLEAR',
        ])->save();

        $this->assertCount(1, $service->dueCursors(50));
        $this->assertSame(1, OfficeDistributionCursor::query()
            ->where('office_id', $office->id)
            ->where('channel', CaptureChannel::NfeAutXmlDistDfe->value)
            ->where('environment', 'production')
            ->count());

        Queue::fake();
        $this->artisan('sefaz:dispatch-due-autxml', ['--limit' => 20])->assertSuccessful();
        Queue::assertPushed(SyncOfficeAutXmlDistDfeJob::class, 1);
        Queue::assertPushed(SyncOfficeAutXmlDistDfeJob::class, function (SyncOfficeAutXmlDistDfeJob $job) use ($c1) {
            return $job->officeDistributionCursorId === $c1->id;
        });
    }

    public function test_due_cursors_exclui_external_consumer_conflict(): void
    {
        config([
            'sefaz.autxml.enabled' => true,
            'sefaz.autxml.kill_switch' => false,
            'sefaz.autxml.allow_all_offices' => true,
            'sefaz.autxml.office_allowlist' => [],
        ]);

        $office = Office::factory()->create();
        $identity = OfficeFiscalIdentity::factory()->forOffice($office)->withCnpj('11222333000181')->create();
        $service = app(OfficeDistributionCursorService::class);
        $cursor = $service->ensureForIdentity($identity, 'production');
        $cursor->forceFill([
            'status' => SyncCursorStatus::Idle,
            'next_sync_at' => now()->subMinute(),
            'external_consumer_status' => 'EXTERNAL_CONSUMER_CONFLICT',
        ])->save();

        $this->assertCount(0, $service->dueCursors(50));

        Queue::fake();
        $this->artisan('sefaz:dispatch-due-autxml', ['--limit' => 20])->assertSuccessful();
        Queue::assertNothingPushed();

        $cursor->forceFill(['external_consumer_status' => 'DECLARED_CLEAR'])->save();
        $this->assertCount(1, $service->dueCursors(50));
    }

    public function test_query_cnpj_divergente_da_identidade_falha_permanente(): void
    {
        config([
            'sefaz.autxml.enabled' => true,
            'sefaz.autxml.kill_switch' => false,
            'sefaz.autxml.allow_all_offices' => true,
            'sefaz.autxml.office_allowlist' => [],
            'sefaz.autxml.page_sleep_seconds' => 0,
        ]);

        $office = Office::factory()->create();
        $identity = OfficeFiscalIdentity::factory()->forOffice($office)->withCnpj('11222333000181')->create();
        $cursor = OfficeDistributionCursor::factory()->forIdentity($identity)->create([
            'environment' => 'production',
            'status' => SyncCursorStatus::Idle,
            'last_nsu' => 7,
            'query_cnpj' => '99888777000166', // raiz distinta do A1 do office
            'interested_root_cnpj' => '99888777',
            'external_consumer_status' => 'DECLARED_CLEAR',
        ]);
        $this->seedOfficeCredentialMaterial($identity);

        $client = Mockery::mock(SefazDistDfeClient::class);
        $client->shouldReceive('distByNsu')->never();
        $this->app->instance(SefazDistDfeClient::class, $client);

        (new SyncOfficeAutXmlDistDfeJob($cursor->id, 'TEST_DIVERGE'))->handle(
            app(SefazDistDfeClient::class),
            app(OfficeAutXmlPageProcessor::class),
            app(OfficeCredentialResolver::class),
        );

        $fresh = $cursor->fresh();
        $this->assertSame(7, (int) $fresh->last_nsu);
        $this->assertSame(SyncCursorStatus::Blocked, $fresh->status);
        $this->assertStringContainsString('diverge', (string) $fresh->last_error);
        $this->assertNull($fresh->lock_owner);
    }

    public function test_throwable_generico_grava_mensagem_sanitizada(): void
    {
        config([
            'sefaz.autxml.enabled' => true,
            'sefaz.autxml.kill_switch' => false,
            'sefaz.autxml.allow_all_offices' => true,
            'sefaz.autxml.office_allowlist' => [],
            'sefaz.autxml.page_sleep_seconds' => 0,
            'sefaz.autxml.max_pages_per_job' => 1,
        ]);

        $office = Office::factory()->create();
        $identity = OfficeFiscalIdentity::factory()->forOffice($office)->withCnpj('11222333000181')->create();
        $cursor = OfficeDistributionCursor::factory()->forIdentity($identity)->create([
            'environment' => 'production',
            'status' => SyncCursorStatus::Idle,
            'last_nsu' => 0,
            'external_consumer_status' => 'DECLARED_CLEAR',
        ]);
        $this->seedOfficeCredentialMaterial($identity);

        $sensitive = 'SOAP fault at /tmp/pfx-secret-path with password=leaked';
        $client = Mockery::mock(SefazDistDfeClient::class);
        $client->shouldReceive('distByNsu')
            ->once()
            ->andThrow(new \RuntimeException($sensitive));
        $this->app->instance(SefazDistDfeClient::class, $client);

        try {
            (new SyncOfficeAutXmlDistDfeJob($cursor->id, 'TEST_SANITIZE'))->handle(
                app(SefazDistDfeClient::class),
                app(OfficeAutXmlPageProcessor::class),
                app(OfficeCredentialResolver::class),
            );
            $this->fail('Esperava rethrow do Throwable genérico');
        } catch (\RuntimeException $e) {
            $this->assertSame($sensitive, $e->getMessage());
        }

        $fresh = $cursor->fresh();
        $this->assertSame('Falha interna no job NF-e autXML.', $fresh->last_error);
        $this->assertStringNotContainsString('pfx', (string) $fresh->last_error);
        $this->assertStringNotContainsString('password', (string) $fresh->last_error);
        $this->assertSame(SyncCursorStatus::Error, $fresh->status);
        $this->assertNull($fresh->lock_owner);
    }

    private function seedOfficeCredentialMaterial(OfficeFiscalIdentity $identity): OfficeCredential
    {
        $payload = json_encode([
            'pfx' => base64_encode('fake-pfx-bytes-for-test'),
            'password' => 'secret-test-only',
        ], JSON_THROW_ON_ERROR);

        $objectId = app(SecureObjectStore::class)->put($payload, [
            'office_id' => $identity->office_id,
            'office_fiscal_identity_id' => $identity->id,
            'purpose' => 'NFE_AUTXML_DISTDFE',
            'fingerprint' => str_repeat('a', 64),
        ]);

        return OfficeCredential::factory()->forIdentity($identity)->create([
            'vault_object_id' => $objectId,
            'fingerprint_sha256' => str_repeat('a', 64),
            'holder_cnpj' => $identity->cnpj,
            'valid_to' => now()->addYear(),
        ]);
    }
}
