<?php

namespace App\Http\Controllers\Api\V1\Fiscal;

use App\Enums\OfficeRole;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Services\FiscalMonitoring\FiscalMonitoringRunService;
use App\Services\Integra\Dctfweb\DctfwebCodes;
use App\Services\Integra\Dctfweb\DctfwebMutationGuard;
use App\Services\Integra\Dctfweb\MitApuracaoService;
use App\Support\CurrentOffice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class MitController extends Controller
{
    public function __construct(
        private readonly CurrentOffice $currentOffice,
        private readonly MitApuracaoService $mit,
        private readonly FiscalMonitoringRunService $runs,
        private readonly DctfwebMutationGuard $mutations,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->assertCanRead();
        $office = $this->currentOffice->office();
        $perPage = min(100, max(1, (int) $request->query('per_page', 50)));
        $clientId = $request->query('client_id');

        $page = $this->mit->paginate(
            $office,
            $perPage,
            is_numeric($clientId) ? (int) $clientId : null,
        );
        $page->getCollection()->transform(fn ($m) => $m->toPublicArray());

        return response()->json($page);
    }

    public function show(int $apuracao): JsonResponse
    {
        $this->assertCanRead();
        $office = $this->currentOffice->office();
        $model = $this->mit->findForOffice($office, $apuracao);
        if ($model === null) {
            return response()->json(['message' => 'Apuração MIT não encontrada.'], 404);
        }

        return response()->json(['data' => $model->toPublicArray()]);
    }

    public function enqueueConsult(Request $request): JsonResponse
    {
        $this->assertCanWrite();
        $office = $this->currentOffice->office();

        $data = $request->validate([
            'client_id' => ['required', 'integer'],
            'period_key' => ['required', 'string', 'max:20'],
            'operation_code' => ['sometimes', 'string', 'max:80'],
            'correlation_id' => ['sometimes', 'string', 'max:64'],
        ]);

        $operation = strtoupper($data['operation_code'] ?? DctfwebCodes::OP_MIT_SITUACAO);
        if ($operation === DctfwebCodes::OP_MIT_ENCERRAR) {
            return response()->json([
                'message' => 'Use o endpoint de encerramento para mutação MIT.',
                'code' => 'USE_MUTATION_ENDPOINT',
            ], 422);
        }

        $client = Client::query()
            ->withoutGlobalScopes()
            ->where('office_id', $office->id)
            ->whereKey($data['client_id'])
            ->first();

        if ($client === null) {
            return response()->json(['message' => 'Cliente não encontrado.'], 404);
        }

        $apuracao = $this->mit->findOrCreate($office, $client, $data['period_key']);

        try {
            $run = $this->runs->enqueueManual(
                office: $office,
                client: $client,
                systemCode: DctfwebCodes::SYSTEM_MIT,
                serviceCode: DctfwebCodes::SERVICE_MIT,
                operationCode: $operation,
                competence: $apuracao->competence,
                actorId: $request->user()?->id,
                correlationId: $data['correlation_id'] ?? null,
                dispatch: true,
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $run->toPublicArray()], 201);
    }

    /**
     * Encerramento MIT — rejeitado se flags mutantes OFF (9.8).
     */
    public function encerrar(Request $request): JsonResponse
    {
        $this->assertCanWrite();
        $office = $this->currentOffice->office();

        $data = $request->validate([
            'client_id' => ['required', 'integer'],
            'period_key' => ['required', 'string', 'max:20'],
            'correlation_id' => ['sometimes', 'string', 'max:64'],
            'confirmation' => ['required', 'accepted'],
        ]);

        $client = Client::query()
            ->withoutGlobalScopes()
            ->where('office_id', $office->id)
            ->whereKey($data['client_id'])
            ->first();

        if ($client === null) {
            return response()->json(['message' => 'Cliente não encontrado.'], 404);
        }

        $gate = $this->mutations->assertMayMutate(
            office: $office,
            client: $client,
            systemCode: DctfwebCodes::SYSTEM_MIT,
            serviceCode: DctfwebCodes::SERVICE_MIT,
            operationCode: DctfwebCodes::OP_MIT_ENCERRAR,
            periodKey: $data['period_key'],
            actor: $request->user(),
        );

        if (! $gate['allowed']) {
            return response()->json([
                'message' => $gate['message'],
                'code' => $gate['code'],
            ], 403);
        }

        $apuracao = $this->mit->findOrCreate($office, $client, $data['period_key']);

        try {
            $run = $this->runs->enqueueManual(
                office: $office,
                client: $client,
                systemCode: DctfwebCodes::SYSTEM_MIT,
                serviceCode: DctfwebCodes::SERVICE_MIT,
                operationCode: DctfwebCodes::OP_MIT_ENCERRAR,
                competence: $apuracao->competence,
                actorId: $request->user()?->id,
                correlationId: $data['correlation_id'] ?? null,
                dispatch: true,
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $run->toPublicArray()], 201);
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
        if ($role === null || ! in_array($role, [OfficeRole::Admin, OfficeRole::Operator], true)) {
            abort(403, 'Ação não autorizada para o perfil atual.');
        }
    }
}
