<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\BuildExportZipJob;
use App\Models\Export;
use App\Support\CurrentOffice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportController extends Controller
{
    public function index(): JsonResponse
    {
        $items = Export::query()->where('user_id', auth()->id())->orderByDesc('id')->limit(50)->get();

        return response()->json(['data' => $items->map(fn (Export $e) => $this->public($e))]);
    }

    public function store(Request $request, CurrentOffice $currentOffice): JsonResponse
    {
        if (! $currentOffice->role()?->canExport()) {
            abort(403);
        }

        $data = $request->validate([
            'filters' => ['nullable', 'array'],
            'include_events' => ['sometimes', 'boolean'],
        ]);

        $export = Export::query()->create([
            'office_id' => $currentOffice->office()->id,
            'user_id' => auth()->id(),
            'status' => 'PENDING',
            'filters' => $data['filters'] ?? [],
            'include_events' => $data['include_events'] ?? false,
        ]);

        BuildExportZipJob::dispatch($export->id);

        return response()->json(['data' => $this->public($export)], 202);
    }

    public function download(Export $export): BinaryFileResponse
    {
        if ($export->user_id !== auth()->id()) {
            abort(404);
        }
        if ($export->status !== 'READY' || ! $export->storage_path || ! is_file($export->storage_path)) {
            abort(404);
        }
        if ($export->expires_at && $export->expires_at->isPast()) {
            abort(410, 'Exportação expirada.');
        }

        return response()->download($export->storage_path, 'export-'.$export->id.'.zip');
    }

    /**
     * @return array<string, mixed>
     */
    private function public(Export $export): array
    {
        return [
            'id' => $export->id,
            'status' => $export->status,
            'filters' => $export->filters,
            'include_events' => $export->include_events,
            'files_count' => $export->files_count,
            'byte_size' => $export->byte_size,
            'expires_at' => $export->expires_at?->toIso8601String(),
            'completed_at' => $export->completed_at?->toIso8601String(),
            'created_at' => $export->created_at?->toIso8601String(),
            'error_message' => $export->status === 'FAILED' ? $export->error_message : null,
        ];
    }
}
