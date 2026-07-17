<?php

namespace Tests\Feature\Serpro;

use App\Enums\OfficeRole;
use App\Enums\SerproApprovalPolicy;
use App\Enums\SerproEnvironment;
use App\Models\Office;
use App\Models\SerproRolloutApproval;
use App\Models\User;
use App\Services\Serpro\SerproRolloutApprovalService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

/**
 * Matriz de política OWNER_CONFIRMATION vs DUAL_ROLE + migration de approval_policy.
 */
class SerproOwnerApprovalPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_adiciona_policy_e_classifica_historico_como_dual(): void
    {
        $this->assertTrue(Schema::hasColumn('serpro_rollout_approvals', 'approval_policy'));
        $this->assertTrue(Schema::hasColumn('serpro_rollout_approvals', 'confirmation_phrase'));
        $this->assertTrue(Schema::hasColumn('serpro_rollout_approvals', 'change_window_start'));
        $this->assertTrue(Schema::hasColumn('serpro_rollout_approvals', 'change_window_end'));

        $row = SerproRolloutApproval::query()->create([
            'subject_type' => 'KILL_SWITCH',
            'subject_id' => null,
            'action' => 'KILL_SWITCH_OFF',
            'environment' => SerproEnvironment::Trial,
            'status' => 'PENDING',
            'reason' => 'legado',
            'requested_by_user_id' => 1,
            'expires_at' => now()->addDay(),
        ]);

        // Default da coluna / model: DUAL_ROLE se não setado explicitamente no create sem fill.
        $this->assertNotNull($row->fresh()->approval_policy);
    }

    public function test_allowlist_politica_por_acao(): void
    {
        $svc = app(SerproRolloutApprovalService::class);

        foreach ([
            'KILL_SWITCH_OFF',
            'KILL_SWITCH_SOLUTION_OFF',
            'CONTRACT_ACTIVATE',
            'CREDENTIAL_CUTOVER',
        ] as $action) {
            $this->assertSame(
                SerproApprovalPolicy::OwnerConfirmation,
                $svc->policyForAction($action),
                $action,
            );
        }

        foreach (['BILLABLE_CANARY', 'ROLLOUT_PROMOTE', 'FREE_SMOKE_PROMOTE'] as $action) {
            $this->assertSame(
                SerproApprovalPolicy::DualRole,
                $svc->policyForAction($action),
                $action,
            );
        }

        // Desconhecida: fail-closed dual (nunca OWNER por omissão).
        $this->assertSame(SerproApprovalPolicy::DualRole, $svc->policyForAction('UNKNOWN_ACTION'));
    }

    public function test_owner_confirmation_full_approve_e_is_fully_approved(): void
    {
        $owner = User::factory()->asPlatformAdmin()->create();
        $svc = app(SerproRolloutApprovalService::class);
        $start = CarbonImmutable::now()->subMinutes(5);
        $end = CarbonImmutable::now()->addHour();

        $approval = $svc->request(
            action: SerproRolloutApprovalService::ACTION_CREDENTIAL_CUTOVER,
            subjectType: 'CREDENTIAL_VERSION',
            subjectId: 42,
            reason: 'cutover v2',
            requestedByUserId: $owner->id,
            environment: SerproEnvironment::Trial,
            changeWindowStart: $start,
            changeWindowEnd: $end,
        );

        $this->assertSame(SerproApprovalPolicy::OwnerConfirmation, $approval->policy());
        $this->assertSame('CONFIRMO-CREDENTIAL_CUTOVER', $approval->confirmation_phrase);
        $this->assertFalse($approval->isFullyApproved());

        $result = $svc->approve(
            $approval,
            $owner->id,
            passwordRecentlyConfirmed: true,
            reason: 'cutover v2',
            confirmationPhrase: 'CONFIRMO-CREDENTIAL_CUTOVER',
            changeWindowStart: $start,
            changeWindowEnd: $end,
        );

        $approved = $result['approval'];
        $this->assertTrue($approved->isFullyApproved());
        $this->assertSame('APPROVED', $approved->status);
        $this->assertNull($approved->second_approver_user_id);
        $this->assertFalse($result['executed']); // cutover é consumido pelo caller
    }

    public function test_owner_rejeita_frase_motivo_janela_e_senha_sem_parcial(): void
    {
        $owner = User::factory()->asPlatformAdmin()->create();
        $svc = app(SerproRolloutApprovalService::class);
        $start = CarbonImmutable::now()->subMinutes(5);
        $end = CarbonImmutable::now()->addHour();

        $base = fn () => $svc->request(
            action: SerproRolloutApprovalService::ACTION_KILL_SWITCH_OFF,
            subjectType: 'KILL_SWITCH',
            subjectId: null,
            reason: 'reabrir',
            requestedByUserId: $owner->id,
            changeWindowStart: $start,
            changeWindowEnd: $end,
        );

        // Senha ausente
        $a1 = $base();
        try {
            $svc->approve($a1, $owner->id, passwordRecentlyConfirmed: false, confirmationPhrase: 'CONFIRMO-KILL_SWITCH_OFF', reason: 'x');
            $this->fail('senha');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('senha', mb_strtolower($e->getMessage()));
        }
        $this->assertSame('PENDING', $a1->fresh()->status);
        $this->assertNull($a1->fresh()->first_approver_user_id);

        // Frase errada
        $a2 = $base();
        try {
            $svc->approve($a2, $owner->id, true, reason: 'ok', confirmationPhrase: 'ERRADA');
            $this->fail('frase');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('frase', mb_strtolower($e->getMessage()));
        }
        $this->assertSame('PENDING', $a2->fresh()->status);

        // Motivo vazio
        $a3 = $base();
        try {
            $svc->approve($a3, $owner->id, true, reason: '   ', confirmationPhrase: 'CONFIRMO-KILL_SWITCH_OFF');
            $this->fail('motivo');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('motivo', mb_strtolower($e->getMessage()));
        }
        $this->assertSame('PENDING', $a3->fresh()->status);

        // Janela não vigente
        $a4 = $svc->request(
            action: SerproRolloutApprovalService::ACTION_KILL_SWITCH_OFF,
            subjectType: 'KILL_SWITCH',
            subjectId: null,
            reason: 'reabrir',
            requestedByUserId: $owner->id,
            changeWindowStart: CarbonImmutable::now()->addHour(),
            changeWindowEnd: CarbonImmutable::now()->addHours(2),
        );
        try {
            $svc->approve(
                $a4,
                $owner->id,
                true,
                reason: 'reabrir',
                confirmationPhrase: 'CONFIRMO-KILL_SWITCH_OFF',
            );
            $this->fail('janela');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('janela', mb_strtolower($e->getMessage()));
        }
        $this->assertSame('PENDING', $a4->fresh()->status);
    }

    public function test_cli_nao_fabrica_aprovacao(): void
    {
        $svc = app(SerproRolloutApprovalService::class);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/HTTP|fabric/i');
        $svc->request(
            action: SerproRolloutApprovalService::ACTION_KILL_SWITCH_OFF,
            subjectType: 'KILL_SWITCH',
            subjectId: null,
            reason: 'cli',
            requestedByUserId: 1,
            changeWindowStart: CarbonImmutable::now(),
            changeWindowEnd: CarbonImmutable::now()->addHour(),
            fromHttp: false,
        );
    }

    public function test_canario_dual_role_proprietario_e_office_admin(): void
    {
        $office = Office::factory()->create();
        $owner = User::factory()->asPlatformAdmin()->create();
        $officeAdmin = User::factory()->forOffice($office, OfficeRole::Admin)->create();
        $svc = app(SerproRolloutApprovalService::class);

        $approval = $svc->request(
            action: SerproRolloutApprovalService::ACTION_BILLABLE_CANARY,
            subjectType: 'READINESS',
            subjectId: null,
            reason: 'canario delimitado',
            requestedByUserId: $owner->id,
            officeId: $office->id,
            environment: SerproEnvironment::Trial,
        );

        $this->assertSame(SerproApprovalPolicy::DualRole, $approval->policy());

        $first = $svc->approve($approval, $owner->id, true, reason: 'olho global');
        $this->assertFalse($first['approval']->isFullyApproved());
        $this->assertSame('PARTIAL', $first['approval']->status);

        // Mesma conta dual / mesmo user
        try {
            $svc->approve($first['approval'], $owner->id, true, reason: 'mesmo');
            $this->fail('mesmo user');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('distinto', mb_strtolower($e->getMessage()));
        }

        $second = $svc->approve($first['approval']->fresh(), $officeAdmin->id, true, reason: 'olho office');
        $this->assertTrue($second['approval']->isFullyApproved());
        $this->assertSame('APPROVED', $second['approval']->status);
    }

    public function test_conta_dual_nao_preenche_ambos_os_papeis_no_canario(): void
    {
        $office = Office::factory()->create();
        // Conta dual: único proprietário PLATFORM_ADMIN também membership Office ADMIN.
        $dual = User::factory()->asPlatformAdmin()->create();
        $office->users()->attach($dual->id, ['role' => OfficeRole::Admin->value, 'is_active' => true]);

        $svc = app(SerproRolloutApprovalService::class);
        $approval = $svc->request(
            action: SerproRolloutApprovalService::ACTION_BILLABLE_CANARY,
            subjectType: 'READINESS',
            subjectId: null,
            reason: 'canario',
            requestedByUserId: $dual->id,
            officeId: $office->id,
        );

        $first = $svc->approve($approval, $dual->id, true, reason: 'owner dual');
        $this->assertSame('PARTIAL', $first['approval']->status);
        $this->assertSame('PLATFORM_ADMIN', $first['approval']->context['first_approver_role'] ?? null);

        // Mesma conta dual não fecha o segundo papel.
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/distinto|dual|Office ADMIN/i');
        $svc->approve($approval->fresh(), $dual->id, true, reason: 'mesmo dual');
    }

    public function test_owner_singleton_nao_satisfaz_is_fully_approved_dual(): void
    {
        $owner = User::factory()->asPlatformAdmin()->create();
        $svc = app(SerproRolloutApprovalService::class);
        $start = CarbonImmutable::now()->subMinute();
        $end = CarbonImmutable::now()->addHour();

        $ownerApproval = $svc->request(
            action: SerproRolloutApprovalService::ACTION_CREDENTIAL_CUTOVER,
            subjectType: 'CREDENTIAL_VERSION',
            subjectId: 1,
            reason: 'ok',
            requestedByUserId: $owner->id,
            changeWindowStart: $start,
            changeWindowEnd: $end,
        );
        $svc->approve(
            $ownerApproval,
            $owner->id,
            true,
            reason: 'ok',
            confirmationPhrase: 'CONFIRMO-CREDENTIAL_CUTOVER',
        );

        // Mesmo "um olho" em registro DUAL_ROLE permanece incompleto.
        $dual = $svc->request(
            action: SerproRolloutApprovalService::ACTION_ROLLOUT_PROMOTE,
            subjectType: 'READINESS',
            subjectId: null,
            reason: 'promote',
            requestedByUserId: $owner->id,
        );
        $svc->approve($dual, $owner->id, true, reason: 'so um');
        $this->assertFalse($dual->fresh()->isFullyApproved());
        $this->assertTrue($ownerApproval->fresh()->isFullyApproved());
    }

    public function test_to_sanitized_expoe_politica_e_frase_sem_segredo(): void
    {
        $owner = User::factory()->asPlatformAdmin()->create();
        $svc = app(SerproRolloutApprovalService::class);
        $approval = $svc->request(
            action: SerproRolloutApprovalService::ACTION_CONTRACT_ACTIVATE,
            subjectType: 'CONTRACT',
            subjectId: 9,
            reason: 'ativar prod',
            requestedByUserId: $owner->id,
            environment: SerproEnvironment::Production,
            changeWindowStart: CarbonImmutable::now()->subMinute(),
            changeWindowEnd: CarbonImmutable::now()->addHour(),
            context: ['password' => 'must-redact', 'consumer_secret' => 'x'],
        );

        $sanitized = $svc->toSanitized($approval);
        $this->assertSame('OWNER_CONFIRMATION', $sanitized['approval_policy']);
        $this->assertSame('CONFIRMO-CONTRACT_ACTIVATE', $sanitized['expected_confirmation_phrase']);
        $json = json_encode($sanitized) ?: '';
        $this->assertStringNotContainsString('must-redact', $json);
        $this->assertStringNotContainsString('"x"', $json);
        $this->assertSame('[redacted]', $sanitized['context']['password'] ?? null);
        $this->assertSame('[redacted]', $sanitized['context']['consumer_secret'] ?? null);
    }

    public function test_reuso_e_escopo_divergente_bloqueiam_consumo(): void
    {
        $owner = User::factory()->asPlatformAdmin()->create();
        $svc = app(SerproRolloutApprovalService::class);
        $start = CarbonImmutable::now()->subMinute();
        $end = CarbonImmutable::now()->addHour();

        $approval = $svc->request(
            action: SerproRolloutApprovalService::ACTION_CREDENTIAL_CUTOVER,
            subjectType: 'CREDENTIAL_VERSION',
            subjectId: 10,
            reason: 'ok',
            requestedByUserId: $owner->id,
            environment: SerproEnvironment::Trial,
            changeWindowStart: $start,
            changeWindowEnd: $end,
        );
        $svc->approve(
            $approval,
            $owner->id,
            true,
            reason: 'ok',
            confirmationPhrase: 'CONFIRMO-CREDENTIAL_CUTOVER',
        );

        // Escopo divergente
        try {
            $svc->claimOwnerApproval(
                SerproRolloutApprovalService::ACTION_CREDENTIAL_CUTOVER,
                'CREDENTIAL_VERSION',
                999,
                SerproEnvironment::Trial,
                $owner->id,
            );
            $this->fail('escopo');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('confirmação', mb_strtolower($e->getMessage()));
        }

        $claimed = $svc->claimOwnerApproval(
            SerproRolloutApprovalService::ACTION_CREDENTIAL_CUTOVER,
            'CREDENTIAL_VERSION',
            10,
            SerproEnvironment::Trial,
            $owner->id,
            $approval->id,
        );
        $svc->markOwnerApprovalExecuted($claimed, $owner->id);

        // Reuso
        $this->expectException(RuntimeException::class);
        $svc->claimOwnerApproval(
            SerproRolloutApprovalService::ACTION_CREDENTIAL_CUTOVER,
            'CREDENTIAL_VERSION',
            10,
            SerproEnvironment::Trial,
            $owner->id,
            $approval->id,
        );
    }
}
