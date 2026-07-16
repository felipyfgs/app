<?php

namespace App\Services\Serpro;

use App\Enums\SerproEnvironment;
use App\Models\SerproRolloutApproval;
use App\Services\Audit\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Aprovações de quatro olhos para ações sensíveis de plataforma SERPRO:
 * cutover de credencial, desligar kill switch, promover rollout.
 *
 * Persistente em serpro_rollout_approvals (não Redis).
 */
final class SerproRolloutApprovalService
{
    public const ACTION_KILL_SWITCH_OFF = 'KILL_SWITCH_OFF';

    public const ACTION_KILL_SWITCH_SOLUTION_OFF = 'KILL_SWITCH_SOLUTION_OFF';

    public const ACTION_CONTRACT_ACTIVATE = 'CONTRACT_ACTIVATE';

    public const ACTION_ROLLOUT_PROMOTE = 'ROLLOUT_PROMOTE';

    public const ACTION_CREDENTIAL_CUTOVER = 'CREDENTIAL_CUTOVER';

    /** Promoção FREE_SMOKE_OK (escada gratuita — sem canário faturável). */
    public const ACTION_FREE_SMOKE_PROMOTE = 'FREE_SMOKE_PROMOTE';

    /** Canário faturável delimitado (dual approval + teto unitário). */
    public const ACTION_BILLABLE_CANARY = 'BILLABLE_CANARY';

    public function __construct(
        private readonly AuditLogger $audit,
        private readonly SerproKillSwitchService $killSwitch,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     */
    public function request(
        string $action,
        string $subjectType,
        ?int $subjectId,
        string $reason,
        int $requestedByUserId,
        ?SerproEnvironment $environment = null,
        ?int $officeId = null,
        array $context = [],
        int $ttlHours = 24,
    ): SerproRolloutApproval {
        $env = $environment
            ?? SerproEnvironment::tryFrom(strtoupper((string) config('serpro.default_environment', 'TRIAL')))
            ?? SerproEnvironment::Trial;

        $approval = SerproRolloutApproval::query()->create([
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'action' => strtoupper($action),
            'environment' => $env,
            'office_id' => $officeId,
            'status' => 'PENDING',
            'reason' => mb_substr($reason, 0, 500),
            'requested_by_user_id' => $requestedByUserId,
            'expires_at' => CarbonImmutable::now()->addHours(max(1, $ttlHours)),
            'context' => $this->audit->redact($context),
        ]);

        $this->audit->record('serpro.rollout.request', 'SUCCESS', $approval, [
            'action' => $approval->action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'environment' => $env->value,
        ], $requestedByUserId, $officeId);

        return $approval;
    }

    /**
     * Registra um dos dois olhos. O segundo aprovador distinto executa a ação.
     *
     * @return array{approval: SerproRolloutApproval, executed: bool}
     */
    public function approve(
        SerproRolloutApproval $approval,
        int $approverUserId,
        bool $totpVerified,
        ?string $reason = null,
    ): array {
        // $totpVerified legado: agora significa "senha recente confirmada" (PASSWORD).
        if (! $totpVerified) {
            throw new RuntimeException('Aprovação de rollout SERPRO exige reconfirmação de senha recente.');
        }

        if ($approval->status === 'EXECUTED' || $approval->status === 'REJECTED' || $approval->status === 'EXPIRED') {
            throw new RuntimeException('Aprovação em estado final: '.$approval->status);
        }

        if ($approval->expires_at !== null && $approval->expires_at->isPast()) {
            $approval->forceFill(['status' => 'EXPIRED'])->save();
            throw new RuntimeException('Aprovação expirada.');
        }

        return DB::transaction(function () use ($approval, $approverUserId, $reason): array {
            /** @var SerproRolloutApproval $locked */
            $locked = SerproRolloutApproval::query()->whereKey($approval->id)->lockForUpdate()->firstOrFail();

            if ($locked->first_approver_user_id === null) {
                $locked->forceFill([
                    'first_approver_user_id' => $approverUserId,
                    'first_approved_at' => now(),
                    'status' => 'PARTIAL',
                    'reason' => $reason !== null ? mb_substr($reason, 0, 500) : $locked->reason,
                ])->save();

                $this->audit->record('serpro.rollout.first_approve', 'SUCCESS', $locked, [
                    'action' => $locked->action,
                    'approver_user_id' => $approverUserId,
                ], $approverUserId, $locked->office_id);

                return ['approval' => $locked->refresh(), 'executed' => false];
            }

            if ($locked->first_approver_user_id === $approverUserId) {
                throw new RuntimeException('Quatro olhos exige segundo PLATFORM_ADMIN distinto.');
            }

            if ($locked->second_approver_user_id !== null) {
                throw new RuntimeException('Aprovação já possui dois olhos.');
            }

            $locked->forceFill([
                'second_approver_user_id' => $approverUserId,
                'second_approved_at' => now(),
                'status' => 'APPROVED',
                'reason' => $reason !== null ? mb_substr($reason, 0, 500) : $locked->reason,
            ])->save();

            $this->audit->record('serpro.rollout.second_approve', 'SUCCESS', $locked, [
                'action' => $locked->action,
                'approver_user_id' => $approverUserId,
            ], $approverUserId, $locked->office_id);

            $executed = $this->executeIfReady($locked, $approverUserId);

            return ['approval' => $locked->refresh(), 'executed' => $executed];
        });
    }

    public function reject(
        SerproRolloutApproval $approval,
        int $userId,
        string $reason,
    ): SerproRolloutApproval {
        $approval->forceFill([
            'status' => 'REJECTED',
            'reason' => mb_substr($reason, 0, 500),
        ])->save();

        $this->audit->record('serpro.rollout.reject', 'SUCCESS', $approval, [
            'action' => $approval->action,
            'reason' => mb_substr($reason, 0, 200),
        ], $userId, $approval->office_id);

        return $approval->refresh();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listSanitized(?string $status = null, int $limit = 50): array
    {
        $q = SerproRolloutApproval::query()->orderByDesc('id')->limit(min(100, max(1, $limit)));
        if ($status !== null && $status !== '') {
            $q->where('status', strtoupper($status));
        }

        return $q->get()->map(fn (SerproRolloutApproval $a) => $this->toSanitized($a))->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function toSanitized(SerproRolloutApproval $approval): array
    {
        return [
            'id' => $approval->id,
            'subject_type' => $approval->subject_type,
            'subject_id' => $approval->subject_id,
            'action' => $approval->action,
            'environment' => $approval->environment instanceof SerproEnvironment
                ? $approval->environment->value
                : (string) $approval->environment,
            'office_id' => $approval->office_id,
            'status' => $approval->status,
            'reason' => $approval->reason,
            'requested_by_user_id' => $approval->requested_by_user_id,
            'first_approver_user_id' => $approval->first_approver_user_id,
            'second_approver_user_id' => $approval->second_approver_user_id,
            'first_approved_at' => $approval->first_approved_at?->toIso8601String(),
            'second_approved_at' => $approval->second_approved_at?->toIso8601String(),
            'executed_at' => $approval->executed_at?->toIso8601String(),
            'expires_at' => $approval->expires_at?->toIso8601String(),
            'fully_approved' => $approval->isFullyApproved(),
            // context pode ter metadados já redacted; nunca vault/secret
            'context' => is_array($approval->context) ? $this->audit->redact($approval->context) : null,
        ];
    }

    private function executeIfReady(SerproRolloutApproval $approval, int $actorUserId): bool
    {
        if ($approval->first_approver_user_id === null
            || $approval->second_approver_user_id === null
            || $approval->first_approver_user_id === $approval->second_approver_user_id
        ) {
            return false;
        }

        $reason = (string) ($approval->reason ?? 'dual_approval');

        match ($approval->action) {
            self::ACTION_KILL_SWITCH_OFF => $this->killSwitch->deactivateGlobal($reason, $actorUserId),
            self::ACTION_KILL_SWITCH_SOLUTION_OFF => $this->executeSolutionOff($approval, $reason, $actorUserId),
            self::ACTION_ROLLOUT_PROMOTE,
            self::ACTION_CONTRACT_ACTIVATE,
            self::ACTION_CREDENTIAL_CUTOVER => null, // caller executa cutover/activate após APPROVED
            default => null,
        };

        $approval->forceFill([
            'status' => 'EXECUTED',
            'executed_at' => now(),
        ])->save();

        $this->audit->record('serpro.rollout.executed', 'SUCCESS', $approval, [
            'action' => $approval->action,
        ], $actorUserId, $approval->office_id);

        return true;
    }

    private function executeSolutionOff(SerproRolloutApproval $approval, string $reason, int $actorUserId): void
    {
        $solution = is_array($approval->context) ? ($approval->context['solution'] ?? null) : null;
        if (! is_string($solution) || $solution === '') {
            throw new RuntimeException('Aprovação de solution kill-off sem código de solução.');
        }
        $this->killSwitch->deactivateSolution($solution, $reason, $actorUserId);
    }
}
