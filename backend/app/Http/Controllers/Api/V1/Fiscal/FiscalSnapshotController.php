<?php

namespace App\Http\Controllers\Api\V1\Fiscal;

use App\Http\Controllers\Controller;
use App\Services\FiscalMonitoring\FiscalEvidenceStore;
use App\Services\FiscalMonitoring\FiscalQueryService;
use App\Support\CurrentOffice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FiscalSnapshotController extends Controller
{
    public function __construct(
        private readonly CurrentOffice $currentOffice,
        private readonly FiscalQueryService $queries,
        private readonly FiscalEvidenceStore $evidenceStore,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->assertCanRead();
        $office = $this->currentOffice->office();

        $perPage = min(100, max(1, (int) $request->query('per_page', 50)));
        $clientId = $request->query('client_id');
        $currentOnly = filter_var($request->query('current_only', true), FILTER_VALIDATE_BOOL);

        $page = $this->queries->snapshots(
            $office,
            $perPage,
            is_numeric($clientId) ? (int) $clientId : null,
            $currentOnly,
        );
        $page->getCollection()->transform(fn ($s) => $s->toPublicArray());

        return response()->json($page);
    }

    public function show(int $snapshot): JsonResponse
    {
        $this->assertCanRead();
        $office = $this->currentOffice->office();
        $model = $this->queries->snapshot($office, $snapshot);
        if ($model === null) {
            return response()->json(['message' => 'Snapshot não encontrado.'], 404);
        }

        return response()->json(['data' => $model->toPublicArray()]);
    }

    /**
     * Download autorizado de evidência — stream sem path interno/URL permanente.
     */
    public function downloadEvidence(int $evidence): StreamedResponse|JsonResponse
    {
        $this->assertCanRead();
        $office = $this->currentOffice->office();
        $artifact = $this->queries->evidence($office, $evidence);
        if ($artifact === null) {
            return response()->json(['message' => 'Evidência não encontrada.'], 404);
        }

        try {
            $bytes = $this->evidenceStore->readAuthorized($artifact, (int) $office->id);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }

        $filename = 'fiscal-evidence-'.$artifact->id.'.bin';
        $sha = $artifact->content_sha256;

        return response()->streamDownload(function () use ($bytes): void {
            echo $bytes;
        }, $filename, [
            'Content-Type' => $artifact->content_type,
            'X-Content-SHA256' => $sha,
            'Cache-Control' => 'no-store',
        ]);
    }

    public function findings(Request $request): JsonResponse
    {
        $this->assertCanRead();
        $office = $this->currentOffice->office();
        $perPage = min(100, max(1, (int) $request->query('per_page', 50)));
        $clientId = $request->query('client_id');
        $activeOnly = filter_var($request->query('active_only', true), FILTER_VALIDATE_BOOL);

        $page = $this->queries->findings(
            $office,
            $perPage,
            is_numeric($clientId) ? (int) $clientId : null,
            $activeOnly,
        );
        $page->getCollection()->transform(fn ($f) => $f->toPublicArray());

        return response()->json($page);
    }

    public function pending(Request $request): JsonResponse
    {
        $this->assertCanRead();
        $office = $this->currentOffice->office();
        $perPage = min(100, max(1, (int) $request->query('per_page', 50)));
        $clientId = $request->query('client_id');
        $status = $request->query('status', 'OPEN');

        $page = $this->queries->pendingItems(
            $office,
            $perPage,
            is_numeric($clientId) ? (int) $clientId : null,
            is_string($status) ? $status : 'OPEN',
        );
        $page->getCollection()->transform(fn ($p) => $p->toPublicArray());

        return response()->json($page);
    }

    private function assertCanRead(): void
    {
        if ($this->currentOffice->role() === null) {
            abort(403, 'Perfil não resolvido.');
        }
    }
}
