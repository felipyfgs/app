<?php

namespace Tests\Unit\Outbound;

use App\DTO\Outbound\SvrsEgressReserveRequest;
use App\Enums\SvrsEgressBlockCause;
use App\Models\SvrsEgressCohortState;
use App\Services\Audit\AuditLogger;
use App\Services\Outbound\RedisSvrsPortalEgressGovernor;
use App\Services\Outbound\SvrsPortalEgressConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SvrsPortalEgressGovernorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config([
            'sefaz.svrs_portal_egress.cohort_id' => 'test-cohort',
            'sefaz.svrs_portal_egress.min_interval_global_seconds' => 0,
            'sefaz.svrs_portal_egress.min_interval_root_seconds' => 0,
            'sefaz.svrs_portal_egress.max_exchanges_per_hour' => 10,
            'sefaz.svrs_portal_egress.max_exchanges_per_day' => 50,
            'sefaz.svrs_portal_egress.max_keys_per_root_per_day' => 6,
            'sefaz.svrs_portal_egress.max_inflight_transactions' => 1,
            'sefaz.svrs_portal_egress.exchanges_per_download' => 2,
            'sefaz.svrs_portal_egress.block_cooldown_ladder_seconds' => [60, 120, 240, 480],
        ]);
    }

    public function test_reserve_serializa_inflight(): void
    {
        $g = $this->governor();
        $a = $g->reserve($this->req('11111111000191'));
        $this->assertTrue($a->allowed);
        $b = $g->reserve($this->req('22222222000191'));
        $this->assertFalse($b->allowed);
        $this->assertSame('inflight', $b->reason);
        $g->release($a->reservation);
        $c = $g->reserve($this->req('22222222000191'));
        $this->assertTrue($c->allowed);
        $g->release($c->reservation);
    }

    public function test_global_interval_defensivo(): void
    {
        config(['sefaz.svrs_portal_egress.min_interval_global_seconds' => 120]);
        $g = $this->governor();
        $a = $g->reserve($this->req('11111111000191'));
        $this->assertTrue($a->allowed);
        $g->release($a->reservation);
        $b = $g->reserve($this->req('22222222000191'));
        $this->assertFalse($b->allowed);
        $this->assertSame('global_interval', $b->reason);
        $this->assertGreaterThan(0, $b->retryAfterSeconds);
    }

    public function test_breaker_multiplas_consultas_bloqueia_todos_canais(): void
    {
        $g = $this->governor();
        $g->openBreaker(SvrsEgressBlockCause::MultipleQueries, 'fp1');
        $this->assertFalse($g->isCallAllowed(false));
        $this->assertFalse($g->reserve($this->req('11111111000191'))->allowed);
        $health = $g->cohortHealth();
        $this->assertSame('open', $health['state']);
        $this->assertSame(SvrsEgressBlockCause::MultipleQueries->value, $health['cause']);
    }

    public function test_half_open_permite_um_unico_canario_entre_governadores_concorrentes(): void
    {
        $workerA = $this->governor();
        $workerB = $this->governor();

        $workerA->openBreaker(SvrsEgressBlockCause::MultipleQueries, 'fp-half-open');
        SvrsEgressCohortState::query()
            ->where('cohort_id', 'test-cohort')
            ->update([
                'state' => 'half_open',
                'next_probe_at' => now()->subSecond(),
            ]);

        $this->assertFalse($workerA->reserve($this->req('11111111000191'))->allowed);

        $first = $workerA->reserve($this->req('11111111000191', isCanary: true));
        $this->assertTrue($first->allowed);
        $this->assertNotNull($first->reservation);

        $concurrent = $workerB->reserve($this->req('22222222000191', isCanary: true));
        $this->assertFalse($concurrent->allowed);
        $this->assertSame('inflight', $concurrent->reason);

        $workerA->release($first->reservation);
    }

    public function test_hour_budget(): void
    {
        config([
            'sefaz.svrs_portal_egress.max_exchanges_per_hour' => 2,
            'sefaz.svrs_portal_egress.exchanges_per_download' => 2,
            'sefaz.svrs_portal_egress.max_inflight_transactions' => 5,
        ]);
        $g = $this->governor();
        $a = $g->reserve($this->req('11111111000191'));
        $this->assertTrue($a->allowed);
        $g->release($a->reservation);
        $b = $g->reserve($this->req('22222222000191'));
        $this->assertFalse($b->allowed);
        $this->assertSame('hour_budget', $b->reason);
    }

    public function test_normalize_root_alfanumerico_e_raiz_8(): void
    {
        config([
            'sefaz.svrs_portal_egress.min_interval_global_seconds' => 0,
            'sefaz.svrs_portal_egress.min_interval_root_seconds' => 900,
            'sefaz.svrs_portal_egress.max_inflight_transactions' => 5,
        ]);
        $g = $this->governor();

        // CNPJ alfanumérico válido (12ABC34501DE35) — letras não podem ser descartadas
        $a = $g->reserve($this->req('12abc34501de35'));
        $this->assertTrue($a->allowed);
        $this->assertNotNull($a->reservation);
        $this->assertSame('12ABC345', $a->reservation->rootCnpj);

        // Mesma raiz com outro estabelecimento (14 chars) compartilha intervalo por raiz
        $g->release($a->reservation);
        $b = $g->reserve($this->req('12ABC345000199')); // DV pode ser inválido; normaliza 8 primeiros
        // Se tryParse falhar, ainda substr(0,8) → 12ABC345 → root_interval
        $this->assertFalse($b->allowed);
        $this->assertSame('root_interval', $b->reason);
    }

    public function test_release_reduz_inflight_sob_mutex(): void
    {
        config([
            'sefaz.svrs_portal_egress.min_interval_global_seconds' => 0,
            'sefaz.svrs_portal_egress.min_interval_root_seconds' => 0,
            'sefaz.svrs_portal_egress.max_inflight_transactions' => 1,
        ]);
        $g = $this->governor();
        $a = $g->reserve($this->req('11111111000191'));
        $this->assertTrue($a->allowed);
        $this->assertSame(1, $g->cohortHealth()['inflight']);
        $g->release($a->reservation);
        $this->assertSame(0, $g->cohortHealth()['inflight']);

        $c = $g->reserve($this->req('22222222000191'));
        $this->assertTrue($c->allowed);
        $g->release($c->reservation);
    }

    public function test_hour_budget_burn_on_reserve_nao_reembolsa(): void
    {
        // Documenta política: budgets pré-debitados em reserve não voltam no release.
        config([
            'sefaz.svrs_portal_egress.max_exchanges_per_hour' => 2,
            'sefaz.svrs_portal_egress.exchanges_per_download' => 2,
            'sefaz.svrs_portal_egress.max_inflight_transactions' => 5,
            'sefaz.svrs_portal_egress.min_interval_global_seconds' => 0,
            'sefaz.svrs_portal_egress.min_interval_root_seconds' => 0,
        ]);
        $g = $this->governor();
        $a = $g->reserve($this->req('11111111000191'));
        $this->assertTrue($a->allowed);
        $this->assertSame(2, $g->cohortHealth()['exchanges_hour']);
        // release sem consumeExchange — orçamento NÃO é reembolsado
        $g->release($a->reservation, completed: false);
        $this->assertSame(2, $g->cohortHealth()['exchanges_hour']);
        $this->assertSame(0, $g->cohortHealth()['exchanges_hour_remaining']);
    }

    private function governor(): RedisSvrsPortalEgressGovernor
    {
        return new RedisSvrsPortalEgressGovernor(
            new SvrsPortalEgressConfig,
            app(AuditLogger::class),
        );
    }

    private function req(string $root, bool $isCanary = false): SvrsEgressReserveRequest
    {
        return new SvrsEgressReserveRequest(
            rootCnpj: $root,
            accessKeyMask: '2126…',
            channel: 'nfce65',
            officeId: 1,
            exchangesNeeded: 2,
            isCanary: $isCanary,
        );
    }
}
