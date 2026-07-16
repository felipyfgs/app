<?php

namespace App\Http\Controllers\Api\V1\Work;

use App\Http\Controllers\Controller;
use App\Models\OperationalExport;
use App\Models\OperationalProcess;
use App\Models\OperationalTask;
use App\Services\Work\OperationalExportService;
use App\Services\Work\OperationalKpiQuery;
use App\Support\CurrentOffice;
use App\Support\Work\OfficeTimezone;
use App\Support\Work\RejectClientOfficeId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OperationalDashboardController extends Controller
{
    public function kpis(OperationalKpiQuery $query): JsonResponse
    {
        $this->authorize('viewAny', OperationalTask::class);

        return response()->json(['data' => $query->build()]);
    }

    public function calendar(Request $request, CurrentOffice $currentOffice): JsonResponse
    {
        $this->authorize('viewAny', OperationalTask::class);
        RejectClientOfficeId::strip($request);

        $data = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d'],
        ]);

        $office = $currentOffice->office();
        $tz = OfficeTimezone::for($office);

        $rows = OperationalTask::query()
            ->selectRaw('due_date, count(*) as total')
            ->where('office_id', $office->id)
            ->whereNotNull('due_date')
            ->whereBetween('due_date', [$data['from'], $data['to']])
            ->groupBy('due_date')
            ->orderBy('due_date')
            ->get()
            ->map(fn ($r) => [
                'date' => $r->due_date instanceof \DateTimeInterface
                    ? $r->due_date->format('Y-m-d')
                    : (string) $r->due_date,
                'total' => (int) $r->total,
            ]);

        return response()->json([
            'data' => [
                'office_timezone' => $tz,
                'from' => $data['from'],
                'to' => $data['to'],
                'days' => $rows,
            ],
        ]);
    }

    public function calendarDay(Request $request, CurrentOffice $currentOffice): JsonResponse
    {
        $this->authorize('viewAny', OperationalTask::class);
        RejectClientOfficeId::strip($request);

        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = (int) ($data['per_page'] ?? 25);
        $paginator = OperationalTask::query()
            ->with(['process.client'])
            ->where('office_id', $currentOffice->id())
            ->whereDate('due_date', $data['date'])
            ->orderBy('id')
            ->paginate($perPage);

        return response()->json([
            'data' => collect($paginator->items())->map(fn (OperationalTask $t) => [
                'id' => $t->id,
                'title' => $t->title,
                'status' => $t->status->value,
                'process_id' => $t->operational_process_id,
                'process_title' => $t->process?->title,
                'client_name' => $t->process?->client?->display_name
                    ?: $t->process?->client?->legal_name,
            ]),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function createExport(Request $request, OperationalExportService $service): JsonResponse
    {
        $this->authorize('create', OperationalExport::class);
        RejectClientOfficeId::strip($request);

        $data = $request->validate([
            'filters' => ['sometimes', 'array'],
            'filters.status' => ['sometimes', 'nullable', 'string'],
            'filters.department_id' => ['sometimes', 'nullable', 'integer'],
            'filters.client_id' => ['sometimes', 'nullable', 'integer'],
        ]);

        $export = $service->create($data['filters'] ?? []);

        return response()->json(['data' => $this->publicExport($export)], 201);
    }

    public function showExport(OperationalExport $export): JsonResponse
    {
        $this->authorize('view', $export);

        return response()->json(['data' => $this->publicExport($export)]);
    }

    public function downloadExport(OperationalExport $export): StreamedResponse
    {
        $this->authorize('download', $export);

        if ($export->status->value !== 'READY' || ! $export->storage_path) {
            abort(404);
        }

        $path = Storage::disk('local')->path($export->storage_path);

        return response()->streamDownload(function () use ($path): void {
            echo file_get_contents($path);
        }, 'operational-export-'.$export->id.'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function publicExport(OperationalExport $e): array
    {
        return [
            'id' => $e->id,
            'status' => $e->status->value,
            'filters_snapshot' => $e->filters_snapshot,
            'byte_size' => $e->byte_size,
            'row_count' => $e->row_count,
            'error_message' => $e->error_message,
            'expires_at' => $e->expires_at?->toIso8601String(),
            'completed_at' => $e->completed_at?->toIso8601String(),
            // storage_path omitido
        ];
    }
}
