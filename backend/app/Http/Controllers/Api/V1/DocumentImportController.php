<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Services\Audit\AuditLogger;
use App\Services\Import\OutboundXmlIngestionService;
use App\Support\CurrentOffice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentImportController extends Controller
{
    public function store(
        Request $request,
        CurrentOffice $currentOffice,
        OutboundXmlIngestionService $ingestion,
        AuditLogger $audit,
    ): JsonResponse {
        $role = $currentOffice->role();
        if ($role === null || ! $role->canImportDocuments()) {
            return response()->json(['message' => 'Ação não autorizada para o perfil atual.'], 403);
        }

        $validated = $request->validate([
            'files' => ['required', 'array', 'min:1', 'max:50'],
            'files.*' => ['file', 'max:10240'], // 10 MB cada
            'client_id' => ['nullable', 'integer'],
        ]);

        $office = $currentOffice->office();
        $clientId = isset($validated['client_id']) ? (int) $validated['client_id'] : null;

        if ($clientId !== null) {
            $exists = Client::query()
                ->where('office_id', $office->id)
                ->where('id', $clientId)
                ->exists();
            if (! $exists) {
                return response()->json(['message' => 'Cliente não encontrado neste escritório.'], 422);
            }
        }

        /** @var list<\Illuminate\Http\UploadedFile> $files */
        $files = $request->file('files', []);
        if (! is_array($files)) {
            $files = [$files];
        }

        $report = $ingestion->ingestUploads($office->id, $clientId, array_values($files));

        $audit->record('documents.import', 'SUCCESS', null, [
            'imported' => $report['imported'],
            'skipped' => $report['skipped'],
            'errors' => $report['errors'],
            'client_id' => $clientId,
        ]);

        return response()->json([
            'data' => $report,
        ], $report['imported'] > 0 || $report['skipped'] > 0 ? 200 : 422);
    }
}
