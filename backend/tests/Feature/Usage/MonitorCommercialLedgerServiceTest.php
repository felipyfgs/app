<?php

namespace Tests\Feature\Usage;

use App\Enums\MonitorCommercialDispatchState;
use App\Enums\MonitorCommercialOrigin;
use App\Enums\SubscriptionPlan;
use App\Models\Client;
use App\Models\MonitorCommercialLedgerEntry;
use App\Models\Office;
use App\Models\OfficeSubscription;
use App\Services\Usage\CommercialMonitorCatalog;
use App\Services\Usage\MonitorCommercialLedgerService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Task 5.2 — ledger comercial separado, inaugural, débito no 1º despacho, manual/scheduled.
 */
class MonitorCommercialLedgerServiceTest extends TestCase
{
    use RefreshDatabase;

    private Office $office;

    private Client $client;

    private OfficeSubscription $subscription;

    private MonitorCommercialLedgerService $ledger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->office = Office::factory()->create();
        $this->client = Client::factory()->forOffice($this->office)->create();
        $this->subscription = $this->office->subscription;
        $this->subscription->forceFill([
            'plan' => SubscriptionPlan::Starter,
            'commercial_monitor_units' => 5,
            'current_period_starts_at' => CarbonImmutable::parse('2026-04-01 00:00:00'),
            'current_period_ends_at' => CarbonImmutable::parse('2026-04-30 23:59:59'),
        ])->save();
        $this->ledger = app(MonitorCommercialLedgerService::class);
    }

    public function test_inaugural_quota_zero_unica_sem_recriar_em_novo_periodo(): void
    {
        $r1 = $this->ledger->authorizeAndDebitBeforeRemoteDispatch(
            officeId: (int) $this->office->id,
            clientId: (int) $this->client->id,
            monitorKey: 'sitfis',
            origin: MonitorCommercialOrigin::Manual,
            idempotencyKey: 'manual-1',
            technicalCorrelationId: 'corr-1',
            subscription: $this->subscription,
        );

        $this->assertTrue($r1['allowed']);
        $this->assertTrue($r1['inaugural']);
        $this->assertTrue($r1['debited']);
        $this->assertSame(0, (int) $r1['entry']->quota_units);
        $this->assertSame(MonitorCommercialOrigin::Inaugural, $r1['entry']->origin);
        $this->assertSame(5, $r1['balance']['remaining']);

        // Novo período: não recria inaugural.
        $this->subscription->forceFill([
            'current_period_starts_at' => CarbonImmutable::parse('2026-05-01 00:00:00'),
            'current_period_ends_at' => CarbonImmutable::parse('2026-05-31 23:59:59'),
        ])->save();

        $r2 = $this->ledger->authorizeAndDebitBeforeRemoteDispatch(
            officeId: (int) $this->office->id,
            clientId: (int) $this->client->id,
            monitorKey: 'sitfis',
            origin: MonitorCommercialOrigin::Manual,
            idempotencyKey: 'manual-period2',
            technicalCorrelationId: 'corr-2',
            subscription: $this->subscription->fresh(),
        );

        $this->assertTrue($r2['allowed']);
        $this->assertFalse($r2['inaugural']);
        $this->assertSame(1, (int) $r2['entry']->quota_units);
        $this->assertSame(1, MonitorCommercialLedgerEntry::query()
            ->where('origin', MonitorCommercialOrigin::Inaugural)
            ->where('client_id', $this->client->id)
            ->count());
    }

    public function test_manual_e_scheduled_compartilham_saldo(): void
    {
        // Consome inaugural + 5 manuais (quota 1 cada após inaugural).
        $this->ledger->authorizeAndDebitBeforeRemoteDispatch(
            officeId: (int) $this->office->id,
            clientId: (int) $this->client->id,
            monitorKey: 'dctfweb',
            origin: MonitorCommercialOrigin::Manual,
            idempotencyKey: 'm-inaug',
            technicalCorrelationId: 'c-0',
            subscription: $this->subscription,
        );

        for ($i = 1; $i <= 5; $i++) {
            $r = $this->ledger->authorizeAndDebitBeforeRemoteDispatch(
                officeId: (int) $this->office->id,
                clientId: (int) $this->client->id,
                monitorKey: 'dctfweb',
                origin: MonitorCommercialOrigin::Manual,
                idempotencyKey: "m-{$i}",
                technicalCorrelationId: "c-{$i}",
                subscription: $this->subscription,
            );
            $this->assertTrue($r['allowed'], "manual {$i} should allow");
        }

        $balance = $this->ledger->balance(
            (int) $this->office->id,
            (int) $this->client->id,
            'dctfweb',
            $this->subscription,
        );
        $this->assertSame(0, $balance['remaining']);

        $scheduled = $this->ledger->ensureScheduledItem(
            (int) $this->office->id,
            (int) $this->client->id,
            'dctfweb',
            $this->subscription,
        );
        $blocked = $this->ledger->authorizeAndDebitBeforeRemoteDispatch(
            officeId: (int) $this->office->id,
            clientId: (int) $this->client->id,
            monitorKey: 'dctfweb',
            origin: MonitorCommercialOrigin::Scheduled,
            idempotencyKey: $scheduled->idempotency_key,
            technicalCorrelationId: 'c-sched',
            subscription: $this->subscription,
            existingEntryId: $scheduled->id,
        );

        $this->assertFalse($blocked['allowed']);
        $this->assertSame(MonitorCommercialLedgerService::BLOCK_QUOTA, $blocked['block_reason']);
        $this->assertSame(
            MonitorCommercialDispatchState::BlockedQuota,
            $blocked['entry']->dispatch_state,
        );
    }

    public function test_retry_e_polling_mesmo_correlation_nao_reconsume(): void
    {
        // inaugural
        $this->ledger->authorizeAndDebitBeforeRemoteDispatch(
            officeId: (int) $this->office->id,
            clientId: (int) $this->client->id,
            monitorKey: 'mailbox',
            origin: MonitorCommercialOrigin::Manual,
            idempotencyKey: 'mb-0',
            technicalCorrelationId: 'corr-mb-0',
            subscription: $this->subscription,
        );

        $first = $this->ledger->authorizeAndDebitBeforeRemoteDispatch(
            officeId: (int) $this->office->id,
            clientId: (int) $this->client->id,
            monitorKey: 'mailbox',
            origin: MonitorCommercialOrigin::Manual,
            idempotencyKey: 'mb-1',
            technicalCorrelationId: 'corr-poll',
            subscription: $this->subscription,
        );
        $this->assertTrue($first['debited']);
        $this->assertSame(1, (int) $first['entry']->quota_units);
        $remainingAfter = $first['balance']['remaining'];

        $retry = $this->ledger->authorizeAndDebitBeforeRemoteDispatch(
            officeId: (int) $this->office->id,
            clientId: (int) $this->client->id,
            monitorKey: 'mailbox',
            origin: MonitorCommercialOrigin::Manual,
            idempotencyKey: 'mb-1-retry',
            technicalCorrelationId: 'corr-poll',
            subscription: $this->subscription,
        );

        $this->assertTrue($retry['allowed']);
        $this->assertFalse($retry['debited']);
        $this->assertSame($first['entry']->id, $retry['entry']->id);
        $this->assertSame($remainingAfter, $retry['balance']['remaining']);
    }

    public function test_idempotency_key_igual_nao_redebita(): void
    {
        $this->ledger->authorizeAndDebitBeforeRemoteDispatch(
            officeId: (int) $this->office->id,
            clientId: (int) $this->client->id,
            monitorKey: 'fgts',
            origin: MonitorCommercialOrigin::Manual,
            idempotencyKey: 'fgts-inaug',
            technicalCorrelationId: 'x0',
            subscription: $this->subscription,
        );

        $a = $this->ledger->authorizeAndDebitBeforeRemoteDispatch(
            officeId: (int) $this->office->id,
            clientId: (int) $this->client->id,
            monitorKey: 'fgts',
            origin: MonitorCommercialOrigin::Manual,
            idempotencyKey: 'fgts-same',
            technicalCorrelationId: 'x1',
            subscription: $this->subscription,
        );
        $b = $this->ledger->authorizeAndDebitBeforeRemoteDispatch(
            officeId: (int) $this->office->id,
            clientId: (int) $this->client->id,
            monitorKey: 'fgts',
            origin: MonitorCommercialOrigin::Manual,
            idempotencyKey: 'fgts-same',
            technicalCorrelationId: 'x1',
            subscription: $this->subscription,
        );

        $this->assertSame($a['entry']->id, $b['entry']->id);
        $this->assertFalse($b['debited']);
        $this->assertSame($a['balance']['used'], $b['balance']['used']);
    }

    public function test_pre_transporte_failed_nao_consome_se_pending(): void
    {
        $entry = $this->ledger->ensureScheduledItem(
            (int) $this->office->id,
            (int) $this->client->id,
            'sitfis',
            $this->subscription,
        );
        $this->assertSame(MonitorCommercialDispatchState::Pending, $entry->dispatch_state);

        $failed = $this->ledger->markFailedPreTransport($entry, 'PROXY_MISSING');
        $this->assertSame(0, (int) $failed->quota_units);
        $this->assertSame(MonitorCommercialDispatchState::Failed, $failed->dispatch_state);

        $balance = $this->ledger->balance(
            (int) $this->office->id,
            (int) $this->client->id,
            'sitfis',
            $this->subscription,
        );
        $this->assertSame(0, $balance['used']);
    }

    public function test_canais_realtime_nao_sao_monitores_comerciais(): void
    {
        $this->assertTrue(CommercialMonitorCatalog::isRealtimeNonFranchiseChannel('ADN', 'NFSE'));
        $this->assertTrue(CommercialMonitorCatalog::isRealtimeNonFranchiseChannel('SEFAZ', 'DISTDFE'));
        $this->assertTrue(CommercialMonitorCatalog::isRealtimeNonFranchiseChannel('NFE', 'AUTXML'));
        $this->assertNull(CommercialMonitorCatalog::resolveMonitorKey('ADN', 'NFSE'));
        $this->assertNull(CommercialMonitorCatalog::resolveMonitorKey('SEFAZ', 'NFE_DIST'));

        $r = $this->ledger->authorizeAndDebitBeforeRemoteDispatch(
            officeId: (int) $this->office->id,
            clientId: (int) $this->client->id,
            monitorKey: 'nfse',
            origin: MonitorCommercialOrigin::Manual,
            idempotencyKey: 'nfse-1',
        );
        $this->assertTrue($r['allowed']);
        $this->assertNull($r['entry']);
        $this->assertFalse($r['debited']);
        $this->assertSame(0, MonitorCommercialLedgerEntry::query()->count());
    }

    public function test_monitores_independentes_no_mesmo_periodo(): void
    {
        foreach (['sitfis', 'dctfweb'] as $monitor) {
            $this->ledger->authorizeAndDebitBeforeRemoteDispatch(
                officeId: (int) $this->office->id,
                clientId: (int) $this->client->id,
                monitorKey: $monitor,
                origin: MonitorCommercialOrigin::Manual,
                idempotencyKey: "{$monitor}-1",
                technicalCorrelationId: "{$monitor}-c",
                subscription: $this->subscription,
            );
        }

        $sit = $this->ledger->balance((int) $this->office->id, (int) $this->client->id, 'sitfis', $this->subscription);
        $dctf = $this->ledger->balance((int) $this->office->id, (int) $this->client->id, 'dctfweb', $this->subscription);
        $this->assertSame(5, $sit['remaining']);
        $this->assertSame(5, $dctf['remaining']);
        $this->assertTrue($sit['inaugural_used']);
        $this->assertTrue($dctf['inaugural_used']);
    }

    public function test_concorrencia_nao_ultrapassa_entitlement(): void
    {
        // inaugural free
        $this->ledger->authorizeAndDebitBeforeRemoteDispatch(
            officeId: (int) $this->office->id,
            clientId: (int) $this->client->id,
            monitorKey: 'guides',
            origin: MonitorCommercialOrigin::Manual,
            idempotencyKey: 'g-inaug',
            technicalCorrelationId: 'g0',
            subscription: $this->subscription,
        );

        // 5 unidades restantes; 8 tentativas concorrentes (simuladas sequenciais sob transaction+lock)
        $allowed = 0;
        $blocked = 0;
        for ($i = 1; $i <= 8; $i++) {
            $r = $this->ledger->authorizeAndDebitBeforeRemoteDispatch(
                officeId: (int) $this->office->id,
                clientId: (int) $this->client->id,
                monitorKey: 'guides',
                origin: MonitorCommercialOrigin::Manual,
                idempotencyKey: "g-{$i}",
                technicalCorrelationId: "gc-{$i}",
                subscription: $this->subscription,
            );
            if ($r['allowed']) {
                $allowed++;
            } else {
                $blocked++;
            }
        }

        $this->assertSame(5, $allowed);
        $this->assertSame(3, $blocked);

        $balance = $this->ledger->balance(
            (int) $this->office->id,
            (int) $this->client->id,
            'guides',
            $this->subscription,
        );
        $this->assertSame(0, $balance['remaining']);
        $this->assertSame(5, $balance['used']);
    }

    public function test_ensure_scheduled_idempotente_por_periodo(): void
    {
        $a = $this->ledger->ensureScheduledItem(
            (int) $this->office->id,
            (int) $this->client->id,
            'sitfis',
            $this->subscription,
        );
        $b = $this->ledger->ensureScheduledItem(
            (int) $this->office->id,
            (int) $this->client->id,
            'sitfis',
            $this->subscription,
        );

        $this->assertSame($a->id, $b->id);
        // Primeira ativação → inaugural
        $this->assertSame(MonitorCommercialOrigin::Inaugural, $a->origin);

        // Após inaugural despachada, próximo período gera scheduled
        $this->ledger->authorizeAndDebitBeforeRemoteDispatch(
            officeId: (int) $this->office->id,
            clientId: (int) $this->client->id,
            monitorKey: 'sitfis',
            origin: MonitorCommercialOrigin::Inaugural,
            idempotencyKey: $a->idempotency_key,
            technicalCorrelationId: 'sched-1',
            subscription: $this->subscription,
            existingEntryId: $a->id,
        );

        $this->subscription->forceFill([
            'current_period_starts_at' => CarbonImmutable::parse('2026-05-01 00:00:00'),
            'current_period_ends_at' => CarbonImmutable::parse('2026-05-31 23:59:59'),
        ])->save();

        $c = $this->ledger->ensureScheduledItem(
            (int) $this->office->id,
            (int) $this->client->id,
            'sitfis',
            $this->subscription->fresh(),
        );
        $this->assertSame(MonitorCommercialOrigin::Scheduled, $c->origin);
        $this->assertNotSame($a->id, $c->id);
    }

    public function test_recent_snapshot_e_min_interval(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-04-10 12:00:00'));
        try {
            $this->ledger->authorizeAndDebitBeforeRemoteDispatch(
                officeId: (int) $this->office->id,
                clientId: (int) $this->client->id,
                monitorKey: 'sitfis',
                origin: MonitorCommercialOrigin::Manual,
                idempotencyKey: 'rs-1',
                technicalCorrelationId: 'rs-c',
                subscription: $this->subscription,
            );

            $status = $this->ledger->recentSnapshotStatus(
                (int) $this->office->id,
                (int) $this->client->id,
                'sitfis',
                CarbonImmutable::parse('2026-04-10 12:30:00'),
            );
            $this->assertTrue($status['is_recent']);
            $this->assertFalse($status['can_dispatch_without_interval_block']);
            $this->assertSame(
                MonitorCommercialLedgerService::BLOCK_INTERVAL,
                $this->ledger->assertMinIntervalOrBlock(
                    (int) $this->office->id,
                    (int) $this->client->id,
                    'sitfis',
                    CarbonImmutable::parse('2026-04-10 12:30:00'),
                ),
            );

            $later = $this->ledger->recentSnapshotStatus(
                (int) $this->office->id,
                (int) $this->client->id,
                'sitfis',
                CarbonImmutable::parse('2026-04-11 12:01:00'),
            );
            $this->assertTrue($later['can_dispatch_without_interval_block']);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_db_lock_path_nao_duplica_com_duas_transacoes_logicas(): void
    {
        $this->ledger->authorizeAndDebitBeforeRemoteDispatch(
            officeId: (int) $this->office->id,
            clientId: (int) $this->client->id,
            monitorKey: 'installments',
            origin: MonitorCommercialOrigin::Manual,
            idempotencyKey: 'inst-0',
            technicalCorrelationId: 'i0',
            subscription: $this->subscription,
        );

        $keys = ['inst-a', 'inst-a']; // same key twice
        $ids = [];
        foreach ($keys as $k) {
            $r = DB::transaction(function () use ($k) {
                return $this->ledger->authorizeAndDebitBeforeRemoteDispatch(
                    officeId: (int) $this->office->id,
                    clientId: (int) $this->client->id,
                    monitorKey: 'installments',
                    origin: MonitorCommercialOrigin::Manual,
                    idempotencyKey: $k,
                    technicalCorrelationId: 'same-corr',
                    subscription: $this->subscription,
                );
            });
            $ids[] = $r['entry']->id;
        }

        $this->assertSame($ids[0], $ids[1]);
        $this->assertSame(1, MonitorCommercialLedgerEntry::query()
            ->where('monitor_key', 'installments')
            ->where('quota_units', '>', 0)
            ->count());
    }
}
