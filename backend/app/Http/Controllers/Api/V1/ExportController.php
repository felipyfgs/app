<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\BuildExportZipJob;
use App\Models\Export;
use App\Services\Audit\AuditLogger;
use App\Support\CurrentOffice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportController extends Controller
{
    public function index(Request $request, CurrentOffice $currentOffice): JsonResponse
    {
        $perPage = min(max((int) $request->input('per_page', 20), 1), 100);
        $paginator = Export::query()
            ->where('office_id', $currentOffice->id())
            ->where('user_id', auth()->id())
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json([
            'data' => collect($paginator->items())->map(fn (Export $e) => $this->public($e)),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function store(Request $request, CurrentOffice $currentOffice, AuditLogger $audit): JsonResponse
    {
        if (! $currentOffice->role()?->canExport()) {
            abort(403);
        }

        $data = $request->validate([
            'filters' => ['nullable', 'array'],
            'filters.competence' => ['sometimes', 'nullable', 'string', 'max:7'],
            'filters.access_key' => ['sometimes', 'nullable', 'string', 'max:64'],
            'filters.access_keys' => ['sometimes', 'nullable', 'array', 'max:'.BuildExportZipJob::MAX_ACCESS_KEYS],
            'filters.access_keys.*' => ['string', 'max:64'],
            'filters.issuer_cnpj' => ['sometimes', 'nullable', 'string', 'max:20'],
            'filters.taker_cnpj' => ['sometimes', 'nullable', 'string', 'max:20'],
            'filters.fiscal_role' => ['sometimes', 'nullable', 'string', 'max:32'],
            'filters.direction' => ['sometimes', 'nullable', 'in:IN,OUT,UNKNOWN'],
            'filters.status' => ['sometimes', 'nullable', 'string', 'max:32'],
            'filters.issued_from' => ['sometimes', 'nullable', 'date'],
            'filters.issued_to' => ['sometimes', 'nullable', 'date'],
            'filters.client_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'filters.establishment_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'include_events' => ['sometimes', 'boolean'],
        ]);

        $filters = $this->normalizeFilters($data['filters'] ?? []);

        if (isset($filters['access_keys']) && count($filters['access_keys']) > BuildExportZipJob::MAX_ACCESS_KEYS) {
            throw ValidationException::withMessages([
                'filters.access_keys' => ['No máximo '.BuildExportZipJob::MAX_ACCESS_KEYS.' chaves por exportação.'],
            ]);
        }

        $export = Export::query()->create([
            'office_id' => $currentOffice->office()->id,
            'user_id' => auth()->id(),
            'status' => 'PENDING',
            'filters' => $filters,
            'include_events' => $data['include_events'] ?? false,
        ]);

        BuildExportZipJob::dispatch($export->id);

        $audit->record('export.create', 'SUCCESS', $export, [
            'filters' => $export->filters,
            'include_events' => $export->include_events,
        ]);

        return response()->json(['data' => $this->public($export)], 202);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function normalizeFilters(array $filters): array
    {
        $out = [];

        foreach (['competence', 'access_key', 'fiscal_role', 'direction', 'status', 'issued_from', 'issued_to'] as $key) {
            if (! empty($filters[$key]) && is_string($filters[$key])) {
                $out[$key] = trim($filters[$key]);
            }
        }

        foreach (['issuer_cnpj', 'taker_cnpj'] as $key) {
            if (! empty($filters[$key]) && is_string($filters[$key])) {
                $out[$key] = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $filters[$key]) ?? '');
            }
        }

        if (! empty($filters['client_id'])) {
            $out['client_id'] = (int) $filters['client_id'];
        }
        if (! empty($filters['establishment_id'])) {
            $out['establishment_id'] = (int) $filters['establishment_id'];
        }

        if (! empty($filters['access_keys']) && is_array($filters['access_keys'])) {
            $keys = array_values(array_unique(array_filter(array_map(
                static fn ($k) => is_string($k) ? trim($k) : '',
                $filters['access_keys'],
            ))));
            if ($keys !== []) {
                $out['access_keys'] = $keys;
            }
        }

        return $out;
    }

    public function download(Export $export, AuditLogger $audit, CurrentOffice $currentOffice): BinaryFileResponse
    {
        // Isolamento por escritório + dono — paths privados sob storage/app/private/exports/{office_id}
        if ((int) $export->office_id !== (int) $currentOffice->id() || $export->user_id !== auth()->id()) {
            abort(404);
        }
        if ($export->status !== 'READY' || ! $export->storage_path || ! is_file($export->storage_path)) {
            abort(404);
        }
        if ($export->expires_at && $export->expires_at->isPast()) {
            abort(410, 'Exportação expirada.');
        }

        // Recusa path fora do diretório privado do office
        $root = realpath(storage_path('app/private/exports/'.$export->office_id));
        $real = realpath($export->storage_path);
        if ($root === false || $real === false
            || (! str_starts_with($real, $root.DIRECTORY_SEPARATOR) && $real !== $root)) {
            abort(404);
        }

        $audit->record('export.download', 'SUCCESS', $export, [
            'files_count' => $export->files_count,
            'byte_size' => $export->byte_size,
        ]);

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
