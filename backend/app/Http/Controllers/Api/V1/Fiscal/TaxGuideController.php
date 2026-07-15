<?php

namespace App\Http\Controllers\Api\V1\Fiscal;

use App\Enums\OfficeRole;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\User;
use App\Services\Fiscal\Guides\Exceptions\GuideException;
use App\Services\Fiscal\Guides\GuideDownloadService;
use App\Services\Fiscal\Guides\GuideHighRiskGate;
use App\Services\Fiscal\Guides\GuideIssuanceService;
use App\Services\Fiscal\Guides\GuidePaymentService;
use App\Services\Fiscal\Guides\GuideQueryService;
use App\Services\Fiscal\Guides\GuideReconciliationService;
use App\Support\CurrentOffice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Central de guias — tenant-scoped; mutações OFF por default.
 */
class TaxGuideController extends Controller
{
    public function __construct(
        private readonly CurrentOffice $currentOffice,
        private readonly GuideQueryService $queries,
        private readonly GuideIssuanceService $issuance,
        private readonly GuideDownloadService $downloads,
        private readonly GuidePaymentService $payments,
        private readonly GuideReconciliationService $reconciliation,
        private readonly GuideHighRiskGate $highRisk,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->assertCanRead();
        $office = $this->currentOffice->office();

        $perPage = min(100, max(1, (int) $request->query('per_page', 50)));
        $clientId = $request->query('client_id');
        $paymentStatus = $request->query('payment_status');

        $page = $this->queries->paginate(
            $office,
            $perPage,
            is_numeric($clientId) ? (int) $clientId : null,
            is_string($paymentStatus) ? $paymentStatus : null,
        );
        $page->getCollection()->transform(fn ($g) => $g->toPublicArray());

        return response()->json($page);
    }

    public function show(int $guide): JsonResponse
    {
        $this->assertCanRead();
        $office = $this->currentOffice->office();

        try {
            $model = $this->queries->find($office, $guide);
        } catch (GuideException $e) {
            return $this->guideError($e);
        }

        $data = $model->toPublicArray(withVersions: true);
        $data['payment_confirmations'] = $model->paymentConfirmations
            ->map(fn ($c) => $c->toPublicArray())
            ->all();

        return response()->json(['data' => $data]);
    }

    public function preflight(Request $request): JsonResponse
    {
        $this->assertCanWrite();
        $office = $this->currentOffice->office();
        $data = $request->validate([
            'client_id' => ['required', 'integer'],
            'system_code' => ['required', 'string', 'max:40'],
            'service_code' => ['required', 'string', 'max:80'],
            'operation_code' => ['sometimes', 'string', 'max:80'],
            'competence_period_key' => ['sometimes', 'nullable', 'string', 'max:20'],
            'debit_ref' => ['sometimes', 'nullable', 'string', 'max:120'],
            'amount_cents' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ]);

        $client = $this->resolveClient($office->id, (int) $data['client_id']);
        if ($client === null) {
            return response()->json(['message' => 'Cliente não encontrado.'], 404);
        }

        try {
            $preflight = $this->issuance->preflight(
                office: $office,
                client: $client,
                systemCode: $data['system_code'],
                serviceCode: $data['service_code'],
                operationCode: $data['operation_code'] ?? 'EMITIR_GUIA',
                competencePeriodKey: $data['competence_period_key'] ?? null,
                debitRef: $data['debit_ref'] ?? null,
                amountCents: isset($data['amount_cents']) ? (int) $data['amount_cents'] : null,
                user: $request->user(),
            );
        } catch (GuideException $e) {
            return $this->guideError($e);
        }

        return response()->json(['data' => $preflight]);
    }

    /**
     * Desafio de 2FA recente para operações de alto risco.
     */
    public function challenge(Request $request): JsonResponse
    {
        $this->assertCanWrite();
        $user = $request->user();
        if (! $user instanceof User) {
            return response()->json(['message' => 'Não autenticado.'], 401);
        }

        $data = $request->validate([
            'totp_code' => ['required', 'string', 'max:12'],
        ]);

        try {
            $this->highRisk->verifyTotpAndMark($user, $data['totp_code']);
        } catch (GuideException $e) {
            return $this->guideError($e);
        }

        return response()->json([
            'data' => [
                'confirmed' => true,
                'window_seconds' => (int) config('tax_guides.high_risk.challenge_window_seconds', 300),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->assertCanWrite();
        $office = $this->currentOffice->office();
        $user = $request->user();
        if (! $user instanceof User) {
            return response()->json(['message' => 'Não autenticado.'], 401);
        }

        $data = $request->validate([
            'client_id' => ['required', 'integer'],
            'system_code' => ['required', 'string', 'max:40'],
            'service_code' => ['required', 'string', 'max:80'],
            'operation_code' => ['sometimes', 'string', 'max:80'],
            'competence_period_key' => ['sometimes', 'nullable', 'string', 'max:20'],
            'debit_ref' => ['sometimes', 'nullable', 'string', 'max:120'],
            'amount_cents' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'due_at' => ['sometimes', 'nullable', 'date'],
            'idempotency_key' => ['sometimes', 'nullable', 'string', 'max:160'],
            'correlation_id' => ['sometimes', 'nullable', 'string', 'max:64'],
            'force_reissue' => ['sometimes', 'boolean'],
            'confirmation' => ['required', 'boolean'],
            'confirmation_summary' => ['required', 'array'],
            'confirmation_summary.client_id' => ['sometimes'],
            'confirmation_summary.competence_period_key' => ['sometimes'],
            'confirmation_summary.amount_cents' => ['sometimes'],
            'confirmation_summary.effect' => ['sometimes', 'string', 'max:255'],
        ]);

        $client = $this->resolveClient($office->id, (int) $data['client_id']);
        if ($client === null) {
            return response()->json(['message' => 'Cliente não encontrado.'], 404);
        }

        try {
            $result = $this->issuance->issue(
                office: $office,
                client: $client,
                systemCode: $data['system_code'],
                serviceCode: $data['service_code'],
                operationCode: $data['operation_code'] ?? 'EMITIR_GUIA',
                competencePeriodKey: $data['competence_period_key'] ?? null,
                debitRef: $data['debit_ref'] ?? null,
                amountCents: isset($data['amount_cents']) ? (int) $data['amount_cents'] : null,
                dueAtIso: isset($data['due_at']) ? (string) $data['due_at'] : null,
                user: $user,
                explicitConfirmation: (bool) $data['confirmation'],
                confirmationSummary: $data['confirmation_summary'],
                idempotencyKey: $data['idempotency_key'] ?? null,
                correlationId: $data['correlation_id'] ?? null,
                forceReissue: (bool) ($data['force_reissue'] ?? false),
            );
        } catch (GuideException $e) {
            return $this->guideError($e);
        }

        $status = $result['version']->emission_status?->value;
        $http = match ($status) {
            'UNKNOWN_RESULT' => 202,
            default => $result['reused'] ? 200 : 201,
        };

        return response()->json([
            'data' => [
                'guide' => $result['guide']->toPublicArray(),
                'version' => $result['version']->toPublicArray(),
                'reused' => $result['reused'],
                'substituted' => $result['substituted'],
                'payment_status' => $result['guide']->payment_status?->value,
            ],
        ], $http);
    }

    public function issueDownloadToken(Request $request, int $guide): JsonResponse
    {
        $this->assertCanRead();
        $office = $this->currentOffice->office();
        $user = $request->user();
        if (! $user instanceof User) {
            return response()->json(['message' => 'Não autenticado.'], 401);
        }

        try {
            $model = $this->queries->find($office, $guide);
            $version = $model->currentVersion;
            if ($version === null) {
                return response()->json(['message' => 'Documento indisponível.'], 422);
            }
            $token = $this->downloads->issueToken($version, $user, (int) $office->id);
        } catch (GuideException $e) {
            return $this->guideError($e);
        }

        return response()->json([
            'data' => [
                'token' => $token['token'],
                'expires_at' => $token['expires_at'],
                'version_id' => $token['version_id'],
                'download_path' => '/api/v1/fiscal/guides/downloads/'.$token['token'],
            ],
        ]);
    }

    public function download(Request $request, string $token): StreamedResponse|JsonResponse
    {
        $this->assertCanRead();
        $office = $this->currentOffice->office();

        try {
            $payload = $this->downloads->consumeToken(
                $token,
                (int) $office->id,
                $request->user() instanceof User ? $request->user() : null,
            );
        } catch (GuideException $e) {
            return $this->guideError($e);
        }

        return response()->streamDownload(function () use ($payload): void {
            echo $payload['bytes'];
        }, $payload['filename'], [
            'Content-Type' => $payload['content_type'],
            'X-Content-SHA256' => $payload['sha256'],
            'Cache-Control' => 'no-store',
        ]);
    }

    public function confirmPayment(Request $request, int $guide): JsonResponse
    {
        $this->assertCanWrite();
        $office = $this->currentOffice->office();

        try {
            $model = $this->queries->find($office, $guide);
            $result = $this->payments->lookupAndConfirm(
                $office,
                $model,
                $request->user() instanceof User ? $request->user() : null,
            );
        } catch (GuideException $e) {
            return $this->guideError($e);
        }

        return response()->json([
            'data' => [
                'guide' => $result['guide']->toPublicArray(),
                'confirmation' => $result['confirmation']?->toPublicArray(),
                'lookup_status' => $result['status'],
            ],
        ]);
    }

    public function reconcile(Request $request, int $guide): JsonResponse
    {
        $this->assertCanWrite();
        $office = $this->currentOffice->office();

        try {
            $model = $this->queries->find($office, $guide);
            $version = $model->currentVersion;
            if ($version === null) {
                return response()->json(['message' => 'Versão não encontrada.'], 404);
            }
            $result = $this->reconciliation->reconcile($office, $version);
        } catch (GuideException $e) {
            return $this->guideError($e);
        }

        return response()->json([
            'data' => [
                'guide' => $result['guide']->toPublicArray(),
                'version' => $result['version']->toPublicArray(),
                'outcome' => $result['outcome'],
            ],
        ]);
    }

    private function resolveClient(int $officeId, int $clientId): ?Client
    {
        return Client::query()
            ->withoutGlobalScopes()
            ->where('office_id', $officeId)
            ->whereKey($clientId)
            ->first();
    }

    private function guideError(GuideException $e): JsonResponse
    {
        return response()->json([
            'message' => $e->getMessage(),
            'code' => $e->codeKey,
            'context' => $e->context,
        ], $e->httpStatus);
    }

    private function assertCanRead(): void
    {
        if ($this->currentOffice->role() === null) {
            abort(403, 'Perfil não resolvido.');
        }
    }

    private function assertCanWrite(): void
    {
        $role = $this->currentOffice->role();
        if ($role === null || $role === OfficeRole::Viewer) {
            abort(403, 'Sem permissão para operações de guias.');
        }
    }
}
