<?php

namespace Tests\Unit\Serpro;

use App\Enums\SerproEnvironment;
use App\Enums\SerproReadinessGate;
use App\Models\Office;
use App\Models\SerproRolloutApproval;
use App\Services\Serpro\SerproKillSwitchService;
use App\Services\Serpro\SerproReadinessPromotionService;
use App\Services\Serpro\SerproSmokeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Tests\TestCase;

final class SerproSmokeAndPromotionTest extends TestCase
{
    use RefreshDatabase;

    public function test_smoke_disabled_by_default_blocks_live_tls(): void
    {
        config(['serpro.smoke.enabled' => false]);
        putenv('CI=');
        $_ENV['CI'] = false;

        $smoke = app(SerproSmokeService::class);

        $this->assertFalse($smoke->isEnabled());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('SERPRO_SMOKE_ENABLED');
        $smoke->tlsHandshake(confirmLive: true);
    }

    public function test_smoke_live_blocked_in_ci_even_when_enabled(): void
    {
        config(['serpro.smoke.enabled' => true]);
        putenv('CI=true');
        $_ENV['CI'] = true;

        try {
            $smoke = app(SerproSmokeService::class);
            $this->assertTrue($smoke->isCiEnvironment());

            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('CI');
            $smoke->tlsHandshake(confirmLive: true);
        } finally {
            putenv('CI=');
            $_ENV['CI'] = false;
            unset($_ENV['CI']);
        }
    }

    public function test_smoke_requires_confirm_phrase(): void
    {
        config(['serpro.smoke.enabled' => true]);
        putenv('CI=');
        $_ENV['CI'] = false;

        $smoke = app(SerproSmokeService::class);
        $this->assertFalse($smoke->confirmMatches('yes'));
        $this->assertTrue($smoke->confirmMatches(SerproSmokeService::CONFIRM_PHRASE));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Confirmação');
        $smoke->tlsHandshake(confirmLive: false);
    }

    public function test_smoke_status_and_checklist_offline_no_network(): void
    {
        config([
            'serpro.smoke.enabled' => false,
            'serpro.trial.use_fake_clients' => true,
            'serpro.capabilities' => [
                'sitfis' => 'simulated',
                'autentica_procurador' => 'simulated',
                'authorization' => 'simulated',
                'mailbox' => 'simulated',
                'dctfweb' => 'simulated',
                'simples_mei' => 'simulated',
                'installments' => 'simulated',
                'guides' => 'simulated',
                'registrations' => 'simulated',
                'tax_processes' => 'simulated',
                'default' => 'disabled',
            ],
        ]);
        $smoke = app(SerproSmokeService::class);

        $status = $smoke->status(SerproEnvironment::Trial);
        $this->assertFalse($status['smoke_enabled']);
        $this->assertArrayHasKey('forbidden_routes', $status);
        $this->assertContains('/Consultar', $status['forbidden_routes']);

        $checklist = $smoke->cleanDeployChecklist();
        $this->assertArrayHasKey('checks', $checklist);
        $this->assertTrue($checklist['ok']);
        $ids = array_column($checklist['checks'], 'id');
        $this->assertContains('smoke_default_off', $ids);
        $this->assertContains('drivers_not_real', $ids);
    }

    public function test_artisan_smoke_status_success(): void
    {
        $this->artisan('serpro:smoke', ['mode' => 'status', '--json' => true])
            ->assertSuccessful();
    }

    public function test_artisan_smoke_tls_fails_when_disabled(): void
    {
        config(['serpro.smoke.enabled' => false]);
        $this->artisan('serpro:smoke', [
            'mode' => 'tls',
            '--confirm' => SerproSmokeService::CONFIRM_PHRASE,
        ])->assertFailed();
    }

    public function test_free_smoke_promote_requires_full_ladder(): void
    {
        $promotion = app(SerproReadinessPromotionService::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('termo_local');
        $promotion->promoteFreeSmokeOk(
            operatorConfirmsLadder: true,
            ladder: [
                'termo_local' => false,
                'apoiar_ok' => true,
                'powers_verified' => true,
                'monitorar_ok' => true,
                'zero_consultar_emitir_declarar' => true,
            ],
        );
    }

    public function test_free_smoke_promote_success_and_not_canary(): void
    {
        $office = Office::factory()->create(['slug' => 'real-office-'.uniqid()]);
        $promotion = app(SerproReadinessPromotionService::class);

        $run = $promotion->promoteFreeSmokeOk(
            operatorConfirmsLadder: true,
            ladder: [
                'termo_local' => true,
                'apoiar_ok' => true,
                'powers_verified' => true,
                'monitorar_ok' => true,
                'zero_consultar_emitir_declarar' => true,
                'kill_switch_tested' => true,
            ],
            office: $office,
            environment: SerproEnvironment::Trial,
        );

        $this->assertSame(SerproReadinessGate::FreeSmokeOk, $run->highest_gate);
        $this->assertTrue($run->live_evidence);
        $payload = $run->toSanitizedArray();
        $json = json_encode($payload) ?: '';
        $this->assertStringNotContainsString('consumer_secret', $json);
        $this->assertStringNotContainsString('BEGIN CERTIFICATE', $json);
        $this->assertNotSame(SerproReadinessGate::CanaryReady->value, $payload['highest_gate']);
    }

    public function test_demo_office_cannot_promote_free_smoke(): void
    {
        $office = Office::factory()->create(['slug' => 'demo-piloto']);
        $promotion = app(SerproReadinessPromotionService::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('demo');
        $promotion->promoteFreeSmokeOk(
            operatorConfirmsLadder: true,
            ladder: [
                'termo_local' => true,
                'apoiar_ok' => true,
                'powers_verified' => true,
                'monitorar_ok' => true,
                'zero_consultar_emitir_declarar' => true,
            ],
            office: $office,
        );
    }

    public function test_canary_ready_blocked_without_dual_approval_and_ceiling(): void
    {
        $promotion = app(SerproReadinessPromotionService::class);
        $approval = new SerproRolloutApproval([
            'action' => SerproReadinessPromotionService::ACTION_BILLABLE_CANARY,
            'status' => 'PENDING',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('DUAL_ROLE');
        $promotion->promoteCanaryReady($approval, [
            'office_id' => 1,
            'operation_key' => 'SITFIS.CONSULTAR',
            'max_unit_cost_micros' => 1000,
            'max_quantity' => 1,
        ]);
    }

    public function test_canary_ready_blocked_without_unit_cost_ceiling(): void
    {
        $promotion = app(SerproReadinessPromotionService::class);
        $approval = SerproRolloutApproval::query()->create([
            'subject_type' => 'office',
            'subject_id' => 1,
            'action' => SerproReadinessPromotionService::ACTION_BILLABLE_CANARY,
            'environment' => SerproEnvironment::Trial,
            'status' => 'APPROVED',
            'reason' => 'test',
            'requested_by_user_id' => 1,
            'first_approver_user_id' => 10,
            'second_approver_user_id' => 20,
            'first_approved_at' => now(),
            'second_approved_at' => now(),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('max_unit_cost_micros');
        $promotion->promoteCanaryReady($approval, [
            'office_id' => 1,
            'operation_key' => 'SITFIS.SOMEREADONLY',
            'max_unit_cost_micros' => 0,
            'max_quantity' => 1,
        ]);
    }

    public function test_canary_ready_blocked_for_emitir(): void
    {
        $promotion = app(SerproReadinessPromotionService::class);
        $approval = SerproRolloutApproval::query()->create([
            'subject_type' => 'office',
            'subject_id' => 1,
            'action' => SerproReadinessPromotionService::ACTION_BILLABLE_CANARY,
            'environment' => SerproEnvironment::Trial,
            'status' => 'APPROVED',
            'reason' => 'test',
            'requested_by_user_id' => 1,
            'first_approver_user_id' => 10,
            'second_approver_user_id' => 20,
            'first_approved_at' => now(),
            'second_approved_at' => now(),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('mutante');
        $promotion->promoteCanaryReady($approval, [
            'office_id' => 1,
            'operation_key' => 'DCTFWEB.EMITIR',
            'max_unit_cost_micros' => 5000,
            'max_quantity' => 1,
        ]);
    }

    public function test_assert_not_auto_promoting_beyond_free_smoke(): void
    {
        $promotion = app(SerproReadinessPromotionService::class);
        $promotion->assertNotAutoPromotingBeyondFreeSmoke(SerproReadinessGate::FreeSmokeOk);

        $this->expectException(RuntimeException::class);
        $promotion->assertNotAutoPromotingBeyondFreeSmoke(SerproReadinessGate::CanaryReady);
    }

    public function test_kill_switch_survives_cache_flush_when_durable(): void
    {
        $ks = app(SerproKillSwitchService::class);
        $ks->activateGlobal('drill-durable', userId: null);
        $this->assertTrue($ks->isGlobalActive());

        Cache::flush();
        // After flush, durable DB row should still win once re-read / hydrate
        $this->assertTrue($ks->isGlobalActive(), 'DB durable deve prevalecer mesmo sem cache');

        Cache::flush();
        $ks->hydrateCacheFromDurable();
        $this->assertTrue($ks->isGlobalActive());

        $status = $ks->status();
        $this->assertTrue($status['global']['active']);
        $this->assertTrue($status['global']['durable']);
    }

    public function test_go_live_canary_blocked_check_command(): void
    {
        $this->artisan('serpro:go-live', ['action' => 'canary-blocked-check', '--json' => true])
            ->assertSuccessful();
    }

    public function test_go_live_ledger_dry_run_no_write(): void
    {
        $this->artisan('serpro:go-live', [
            'action' => 'ledger-dry-run',
            '--year' => 2026,
            '--month' => 7,
            '--json' => true,
        ])->assertSuccessful();
    }

    public function test_go_live_free_smoke_via_artisan(): void
    {
        $office = Office::factory()->create(['slug' => 'ops-office-'.uniqid()]);

        $this->artisan('serpro:go-live', [
            'action' => 'free-smoke-promote',
            '--office' => $office->id,
            '--confirm-ladder' => true,
            '--termo-local' => true,
            '--apoiar-ok' => true,
            '--powers-verified' => true,
            '--monitorar-ok' => true,
            '--zero-billable' => true,
            '--kill-switch-tested' => true,
        ])->assertSuccessful();
    }

    public function test_record_live_gate_tls_ok(): void
    {
        $promotion = app(SerproReadinessPromotionService::class);
        $run = $promotion->recordLiveGate(
            SerproReadinessGate::TlsOk,
            'PASS',
            'test tls evidence',
            environment: SerproEnvironment::Trial,
            live: true,
            fingerprint: str_repeat('ab', 32),
        );

        $this->assertSame(SerproReadinessGate::TlsOk, $run->highest_gate);
        $this->assertTrue($run->live_evidence);
        $this->assertCount(1, $run->evidences);
    }
}
