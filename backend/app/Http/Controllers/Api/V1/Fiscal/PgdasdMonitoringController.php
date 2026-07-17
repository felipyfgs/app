<?php

namespace App\Http\Controllers\Api\V1\Fiscal;

use App\Enums\OfficeRole;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Services\Fiscal\SimplesMei\Pgdasd\PgdasdCommunicationService;
use App\Services\Fiscal\SimplesMei\Pgdasd\PgdasdMonitoringQueryService;
use App\Services\FiscalMonitoring\FiscalEvidenceStore;
use App\Support\CurrentOffice;
use App\Support\FeatureFlags;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PgdasdMonitoringController extends Controller
{
    public function __construct(
        private readonly CurrentOffice $currentOffice,
        private readonly PgdasdMonitoringQueryService $queries,
        private readonly PgdasdCommunicationService $communication,
        private readonly FiscalEvidenceStore $evidenceStore,
    ) {}

    public function history(Request $request, int $client): JsonResponse
    {
        $this->assertCanRead();
        $office = $this->currentOffice->office();
        $model = $this->findClient($office->id, $client);
        if ($model === null) {
            // 404 sem revelar existência em outro tenant
            return response()->json([
                'message' => 'Cliente não encontrado no escritório atual.',
                'code' => 'CLIENT_NOT_FOUND',
            ], 404);
        }

        $year = $request->query('year');
        $yearInt = is_numeric($year) ? (int) $year : null;

        try {
            $data = $this->queries->history($office, $model, $yearInt);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'HISTORY_ERROR'], 422);
        }

        return response()->json(['data' => $data]);
    }

    public function collectDocuments(Request $request, int $client): JsonResponse
    {
        $this->assertCanWrite();
        $this->assertModuleEnabled();
        $office = $this->currentOffice->office();
        $model = $this->findClient($office->id, $client);
        if ($model === null) {
            return response()->json(['message' => 'Cliente não encontrado.'], 404);
        }

        $data = $request->validate([
            'operation' => ['sometimes', 'string', 'max:40'],
            'period_key' => ['sometimes', 'string', 'max:20'],
            'periodoApuracao' => ['sometimes', 'string', 'size:6'],
            'declaration_number' => ['sometimes', 'nullable', 'string', 'max:17'],
            'numeroDeclaracao' => ['sometimes', 'string', 'max:17'],
            'numeroDas' => ['sometimes', 'string', 'max:17'],
        ]);

        // SPA envia period_key + declaration_number; resolve operação 14 (PA) ou 15 (nº).
        $operation = (string) ($data['operation'] ?? '');
        if ($operation === '') {
            $numero = (string) ($data['declaration_number'] ?? $data['numeroDeclaracao'] ?? '');
            $operation = $numero !== '' ? 'CONSULTAR_RECIBO' : 'CONSULTAR_ULTIMA_DECLARACAO_RECIBO';
        }

        $params = $data;
        if (isset($data['period_key']) && ! isset($data['periodoApuracao'])) {
            $pk = (string) $data['period_key'];
            $params['periodoApuracao'] = str_contains($pk, '-')
                ? str_replace('-', '', $pk)
                : $pk;
            $params['period_key'] = $pk;
        }
        if (isset($data['declaration_number']) && ! isset($data['numeroDeclaracao'])) {
            $params['numeroDeclaracao'] = (string) $data['declaration_number'];
        }

        try {
            $run = $this->queries->enqueueDocumentCollect(
                $office,
                $model,
                $operation,
                $params,
                $request->user()?->id,
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $run->toPublicArray()], 201);
    }

    public function downloadArtifact(int $client, int $artifact): Response|JsonResponse
    {
        $this->assertCanRead();
        $office = $this->currentOffice->office();
        $model = $this->findClient($office->id, $client);
        if ($model === null) {
            return response()->json(['message' => 'Cliente não encontrado.'], 404);
        }

        $pgArtifact = $this->queries->findArtifact($office, $model, $artifact);

        return $this->streamArtifact($office->id, $pgArtifact);
    }

    /**
     * Download por id do artefato (contrato SPA: /simples-mei/pgdasd/artifacts/{id}/download).
     */
    public function downloadArtifactById(int $artifact): Response|JsonResponse
    {
        $this->assertCanRead();
        $office = $this->currentOffice->office();
        $pgArtifact = $this->queries->findArtifactForOffice($office, $artifact);

        return $this->streamArtifact((int) $office->id, $pgArtifact);
    }

    private function streamArtifact(int $officeId, ?\App\Models\PgdasdArtifact $pgArtifact): Response|JsonResponse
    {
        if ($pgArtifact === null) {
            return response()->json(['message' => 'Artefato não encontrado.'], 404);
        }

        $pgArtifact->loadMissing('evidenceArtifact');
        if ($pgArtifact->evidenceArtifact === null) {
            return response()->json(['message' => 'Artefato não encontrado.'], 404);
        }

        try {
            $bytes = $this->evidenceStore->readAuthorized(
                $pgArtifact->evidenceArtifact,
                $officeId,
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }

        $filename = $this->sanitizeDownloadFilename(
            $pgArtifact->filename,
            (string) $pgArtifact->kind,
            (int) $pgArtifact->id,
        );

        return response($bytes, 200, [
            'Content-Type' => $pgArtifact->content_type ?: 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Nome seguro para Content-Disposition (sem path traversal / caracteres de header).
     */
    private function sanitizeDownloadFilename(?string $filename, string $kind, int $id): string
    {
        $fallback = 'pgdasd-'.$this->safeToken($kind, 'doc').'-'.$id.'.pdf';
        if ($filename === null || trim($filename) === '') {
            return $fallback;
        }

        $base = basename(str_replace(["\0", '\\'], ['', '/'], $filename));
        $base = preg_replace('/[^\w.\-]+/u', '_', $base) ?? '';
        $base = trim($base, '._');

        if ($base === '' || $base === '.' || $base === '..') {
            return $fallback;
        }

        return mb_substr($base, 0, 180);
    }

    private function safeToken(string $value, string $default): string
    {
        $token = preg_replace('/[^\w\-]+/u', '_', $value) ?? '';
        $token = trim($token, '_');

        return $token !== '' ? mb_substr($token, 0, 40) : $default;
    }

    public function updatePreferences(Request $request, int $client): JsonResponse
    {
        // Papel antes da validação — VIEWER deve receber 403, não 422 de campos.
        $this->assertCanWrite();
        $office = $this->currentOffice->office();
        $role = $this->currentOffice->role();
        if ($role === null) {
            abort(403, 'Perfil não resolvido.');
        }

        $model = $this->findClient($office->id, $client);
        if ($model === null) {
            return response()->json([
                'message' => 'Cliente não encontrado no escritório atual.',
                'code' => 'CLIENT_NOT_FOUND',
            ], 404);
        }

        $data = $request->validate([
            'email_enabled' => ['required', 'boolean'],
            'whatsapp_enabled' => ['required', 'boolean'],
            'automatic_requested' => ['required', 'boolean'],
            'lock_version' => ['required', 'integer', 'min:0'],
        ]);

        $user = $request->user();
        if ($user === null) {
            abort(401);
        }

        try {
            $this->communication->updatePreferences(
                $office,
                $model,
                $user,
                $role,
                $data,
            );
        } catch (ConflictHttpException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'CONFLICT'], 409);
        } catch (HttpException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getStatusCode());
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'data' => $this->communication->summary($office, $model),
        ]);
    }

    public function batchPreferences(Request $request): JsonResponse
    {
        $office = $this->currentOffice->office();
        $role = $this->currentOffice->role();
        if ($role === null) {
            abort(403, 'Perfil não resolvido.');
        }

        $data = $request->validate([
            'client_ids' => ['required', 'array', 'min:1', 'max:100'],
            'client_ids.*' => ['integer', 'distinct'],
            'automatic_requested' => ['required', 'boolean'],
        ]);

        $user = $request->user();
        if ($user === null) {
            abort(401);
        }

        try {
            $prefs = $this->communication->batchSetAutomatic(
                $office,
                $user,
                $role,
                $data['client_ids'],
                (bool) $data['automatic_requested'],
            );
        } catch (HttpException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getStatusCode());
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $summaries = $this->communication->summariesForClients(
            $office,
            array_map(static fn ($preference): int => (int) $preference->client_id, $prefs),
        );

        return response()->json([
            'data' => array_values($summaries),
            'updated_count' => count($summaries),
        ]);
    }

    public function preview(int $client): JsonResponse
    {
        $this->assertCanRead();
        $office = $this->currentOffice->office();
        $model = $this->findClient($office->id, $client);
        if ($model === null) {
            return response()->json(['message' => 'Cliente não encontrado.'], 404);
        }

        return response()->json([
            'data' => $this->communication->preview($office, $model),
        ]);
    }

    public function tracking(int $client): JsonResponse
    {
        $this->assertCanRead();
        $office = $this->currentOffice->office();
        $model = $this->findClient($office->id, $client);
        if ($model === null) {
            return response()->json(['message' => 'Cliente não encontrado.'], 404);
        }

        return response()->json([
            'data' => $this->communication->tracking($office, $model),
        ]);
    }

    private function findClient(int $officeId, int $clientId): ?Client
    {
        return Client::query()
            ->withoutGlobalScopes()
            ->where('office_id', $officeId)
            ->whereKey($clientId)
            ->first();
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
        if ($role === null) {
            abort(403, 'Perfil não resolvido.');
        }
        if (! in_array($role, [OfficeRole::Admin, OfficeRole::Operator], true)) {
            abort(403, 'Sem permissão de sincronização.');
        }
    }

    private function assertModuleEnabled(): void
    {
        $office = $this->currentOffice->office();
        if (! FeatureFlags::isModuleEnabled('simples_mei', $office->id)
            && ! (bool) config('fiscal_monitoring.enabled', false)) {
            abort(403, 'Módulo simples_mei desabilitado.');
        }
    }
}
