<?php

namespace Tests\Feature\AutXml;

use App\Contracts\SefazDistDfeClient;
use App\Enums\CaptureChannel;
use App\Enums\SyncCursorStatus;
use App\Jobs\SyncOfficeAutXmlDistDfeJob;
use App\Models\Office;
use App\Models\OfficeDistributionCursor;
use App\Models\OfficeDistributionRun;
use App\Models\OfficeFiscalIdentity;
use App\Services\Certificates\OfficeCredentialResolver;
use App\Services\Sefaz\OfficeAutXmlPageProcessor;
use App\Services\Sefaz\OfficeDistributionCursorService;
use App\Support\AutXmlFeature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Task 12.3 — dois workers e uma raiz: lock único; duas “filiais” não abrem cursores paralelos.
 */
class OfficeAutXmlConcurrentWorkersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config([
            'sefaz.autxml.enabled' => true,
            'sefaz.autxml.kill_switch' => false,
            'sefaz.autxml.allow_all_offices' => true,
            'sefaz.autxml.office_allowlist' => [],
            'sefaz.autxml.lock_ttl_seconds' => 60,
        ]);
    }

    public function test_dois_workers_apenas_um_obtem_lock_da_raiz(): void
    {
        $office = Office::factory()->create();
        $identity = OfficeFiscalIdentity::factory()->forOffice($office)->withCnpj('11222333000181')->create();
        $cursor = app(OfficeDistributionCursorService::class)->ensureForIdentity($identity, 'production');
        $cursor->forceFill(['last_nsu' => 42, 'status' => SyncCursorStatus::Idle])->save();

        $lockKey = 'sefaz:autxml:root:'.$cursor->office_id.':'.$cursor->interested_root_cnpj.':'.$cursor->environment;

        // Worker A segura o lock (simula job em voo em outra instância)
        $lockA = Cache::lock($lockKey, 60);
        $this->assertTrue($lockA->get());

        // Worker B: handle retorna sem avançar NSU / sem segundo run
        $jobB = new SyncOfficeAutXmlDistDfeJob($cursor->id, 'MANUAL');
        $jobB->handle(
            app(SefazDistDfeClient::class),
            app(OfficeAutXmlPageProcessor::class),
            app(OfficeCredentialResolver::class),
        );

        $cursor->refresh();
        $this->assertSame(42, (int) $cursor->last_nsu);
        $this->assertSame(SyncCursorStatus::Idle, $cursor->status);
        $this->assertSame(0, OfficeDistributionRun::query()
            ->where('office_distribution_cursor_id', $cursor->id)
            ->count());

        $lockA->release();
    }

    public function test_duas_filiais_cliente_nao_duplicam_cursor_escritorio(): void
    {
        $office = Office::factory()->create();
        // Escritório: uma identidade / uma raiz DistDFe
        $identity = OfficeFiscalIdentity::factory()->forOffice($office)->withCnpj('11222333000181')->create();

        $svc = app(OfficeDistributionCursorService::class);
        $cProd = $svc->ensureForIdentity($identity, 'production');

        // “Filiais” de clientes do mesmo office não geram cursor de escritório extra
        $this->assertSame(1, OfficeDistributionCursor::query()
            ->where('office_id', $office->id)
            ->where('channel', CaptureChannel::NfeAutXmlDistDfe->value)
            ->where('environment', 'production')
            ->count());

        // Reentrada idempotente
        $again = $svc->ensureForIdentity($identity, 'production');
        $this->assertSame($cProd->id, $again->id);
        $this->assertTrue(AutXmlFeature::isOfficeAllowed((int) $office->id));
    }

    public function test_kill_switch_impede_dispatch_sem_apagar_nsu(): void
    {
        $office = Office::factory()->create();
        $identity = OfficeFiscalIdentity::factory()->forOffice($office)->withCnpj('11222333000181')->create();
        $cursor = app(OfficeDistributionCursorService::class)->ensureForIdentity($identity, 'production');
        $cursor->forceFill(['last_nsu' => 99, 'status' => SyncCursorStatus::Idle])->save();

        config(['sefaz.autxml.kill_switch' => true]);
        $this->assertFalse(AutXmlFeature::isGloballyEnabled());

        $job = new SyncOfficeAutXmlDistDfeJob($cursor->id, 'SCHEDULED');
        $job->handle(
            app(SefazDistDfeClient::class),
            app(OfficeAutXmlPageProcessor::class),
            app(OfficeCredentialResolver::class),
        );

        $cursor->refresh();
        $this->assertSame(99, (int) $cursor->last_nsu);
        $this->assertSame(SyncCursorStatus::Idle, $cursor->status);
    }
}
