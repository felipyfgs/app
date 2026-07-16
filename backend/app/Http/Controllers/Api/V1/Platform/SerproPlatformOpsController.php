<?php

namespace App\Http\Controllers\Api\V1\Platform;

use App\Enums\SerproEnvironment;
use App\Http\Controllers\Controller;
use App\Models\SerproCredentialVersion;
use App\Models\SerproRolloutApproval;
use App\Models\SerproUsageBudget;
use App\Services\Serpro\SerproCredentialVersionService;
use App\Services\Serpro\SerproKillSwitchService;
use App\Services\Serpro\SerproMetricsExporter;
use App\Services\Serpro\SerproReadinessService;
use App\Services\Serpro\SerproRolloutApprovalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * Console de plataforma SERPRO: credenciais, readiness, budgets e rollout.
 * PLATFORM_ADMIN + TOTP (middleware). Respostas sempre sanitizadas.
 */
class SerproPlatformOpsController extends Controller
{
    public function __construct(
        private readonly SerproCredentialVersionService $credentials,
        private readonly SerproReadinessService $readiness,
        private readonly SerproRolloutApprovalService $rollouts,
        private readonly SerproKillSwitchService $killSwitch,
        private readonly SerproMetricsExporter $metrics,
    ) {}

    public function listCredentialVersions(Request $request): JsonResponse
    {
        $env = $this->parseEnv($request->query('environment'));
        $q = SerproCredentialVersion::query()->orderByDesc('id')->limit(100);
        if ($env !== null) {
            $q->where('environment', $env->value);
        }

        return response()->json([
            'data' => $q->get()->map->toSanitizedArray()->all(),
        ]);
    }

    public function showCredentialVersion(SerproCredentialVersion $serproCredentialVersion): JsonResponse
    {
        return response()->json([
            'data' => $serproCredentialVersion->toSanitizedArray(),
        ]);
    }

    public function approveCredentialVersion(Request $request, SerproCredentialVersion $serproCredentialVersion): JsonResponse
    {
        $data = $request->validate([
            'action' => ['required', 'string', 'max:40'],
            'decision' => ['required', 'string', Rule::in(['APPROVE', 'REJECT'])],
            'reason' => ['nullable', 'string', 'max:500'],
            // TOTP é validado pelo middleware de plataforma; flag explícita reforça o contrato dual-approval
            'totp_verified' => ['sometimes', 'boolean'],
        ]);

        try {
            $approval = $this->credentials->recordApproval(
                $serproCredentialVersion,
                $data['action'],
                (int) $request->user()->id,
                totpVerified: true,
                decision: $data['decision'],
                reason: $data['reason'] ?? null,
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'data' => [
                'id' => $approval->id,
                'action' => $approval->action,
                'decision' => $approval->decision,
                'approver_user_id' => $approval->approver_user_id,
                'totp_verified' => (bool) $approval->totp_verified,
                'decided_at' => $approval->decided_at?->toIso8601String(),
                'credential_version' => $serproCredentialVersion->fresh()->toSanitizedArray(),
            ],
        ], 201);
    }

    public function cutoverCredentialVersion(Request $request, SerproCredentialVersion $serproCredentialVersion): JsonResponse
    {
        $data = $request->validate([
            'skip_oauth' => ['sometimes', 'boolean'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $version = $this->credentials->cutover(
                $serproCredentialVersion,
                actorUserId: $request->user()?->id,
                skipOauth: (bool) ($data['skip_oauth'] ?? false),
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $version->toSanitizedArray()]);
    }

    public function readiness(Request $request): JsonResponse
    {
        $env = $this->parseEnv($request->query('environment'));
        $persist = filter_var($request->query('persist', true), FILTER_VALIDATE_BOOL);

        $run = $this->readiness->evaluateGlobal(
            $env,
            persist: $persist,
            actorUserId: $request->user()?->id,
            trigger: 'API',
        );

        if (is_array($run)) {
            return response()->json(['data' => $run]);
        }

        return response()->json(['data' => $run->toSanitizedArray()]);
    }

    /**
     * Snapshot de métricas SERPRO sem PII (OAuth/gateway, breaker, filas, reconciliação).
     */
    public function metrics(): JsonResponse
    {
        return response()->json(['data' => $this->metrics->snapshot()]);
    }

    public function listBudgets(Request $request): JsonResponse
    {
        $q = SerproUsageBudget::query()
            ->where('is_active', true)
            ->orderByDesc('id')
            ->limit(100);

        if ($scope = $request->query('scope')) {
            $q->where('scope', strtoupper((string) $scope));
        }

        $rows = $q->get()->map(function (SerproUsageBudget $b): array {
            return [
                'id' => $b->id,
                'scope' => $b->scope,
                'office_id' => $b->office_id,
                'environment' => $b->environment,
                'budget_kind' => $b->budget_kind,
                'limit_micros' => (int) $b->limit_micros,
                'reserved_micros' => (int) $b->reserved_micros,
                'consumed_micros' => (int) $b->consumed_micros,
                'remaining_micros' => $b->remainingMicros(),
                'cycle_code' => $b->cycle_code,
                'operation_key' => $b->operation_key,
                'is_canary' => (bool) $b->is_canary,
                'is_active' => (bool) $b->is_active,
                'effective_from' => $b->effective_from?->toIso8601String(),
                'effective_to' => $b->effective_to?->toIso8601String(),
            ];
        })->all();

        return response()->json(['data' => $rows]);
    }

    public function listRollouts(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->rollouts->listSanitized(
                status: is_string($request->query('status')) ? $request->query('status') : null,
            ),
        ]);
    }

    public function requestRollout(Request $request): JsonResponse
    {
        $data = $request->validate([
            'action' => ['required', 'string', 'max:40'],
            'subject_type' => ['required', 'string', 'max:40'],
            'subject_id' => ['nullable', 'integer'],
            'reason' => ['required', 'string', 'max:500'],
            'environment' => ['sometimes', 'string', Rule::enum(SerproEnvironment::class)],
            'context' => ['sometimes', 'array'],
            'ttl_hours' => ['sometimes', 'integer', 'min:1', 'max:168'],
        ]);

        $env = isset($data['environment'])
            ? SerproEnvironment::from($data['environment'])
            : null;

        $approval = $this->rollouts->request(
            action: $data['action'],
            subjectType: $data['subject_type'],
            subjectId: $data['subject_id'] ?? null,
            reason: $data['reason'],
            requestedByUserId: (int) $request->user()->id,
            environment: $env,
            context: $data['context'] ?? [],
            ttlHours: (int) ($data['ttl_hours'] ?? 24),
        );

        return response()->json([
            'data' => $this->rollouts->toSanitized($approval),
        ], 201);
    }

    public function approveRollout(Request $request, SerproRolloutApproval $serproRolloutApproval): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $result = $this->rollouts->approve(
                $serproRolloutApproval,
                (int) $request->user()->id,
                totpVerified: true,
                reason: $data['reason'] ?? null,
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'data' => $this->rollouts->toSanitized($result['approval']),
            'executed' => $result['executed'],
            'kill_switch' => $this->killSwitch->status(),
        ]);
    }

    public function rejectRollout(Request $request, SerproRolloutApproval $serproRolloutApproval): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $approval = $this->rollouts->reject(
            $serproRolloutApproval,
            (int) $request->user()->id,
            $data['reason'],
        );

        return response()->json([
            'data' => $this->rollouts->toSanitized($approval),
        ]);
    }

    private function parseEnv(mixed $raw): ?SerproEnvironment
    {
        if (! is_string($raw) || $raw === '') {
            return null;
        }

        return SerproEnvironment::tryFrom(strtoupper($raw));
    }
}
