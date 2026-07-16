<?php

namespace App\Http\Controllers\Api\V1\Work;

use App\Http\Controllers\Controller;
use App\Models\ProcessGenerationBatch;
use App\Models\ProcessGenerationItem;
use App\Models\ProcessTemplate;
use App\Services\Work\OperationalProcessGenerationService;
use App\Support\Work\RejectClientOfficeId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProcessGenerationController extends Controller
{
    public function preview(Request $request, ProcessTemplate $template, OperationalProcessGenerationService $service): JsonResponse
    {
        $this->authorize('generate', $template);
        RejectClientOfficeId::strip($request);

        $data = $request->validate([
            'competence' => ['required', 'string', 'regex:/^\d{4}-\d{2}$/'],
            'client_ids' => ['required', 'array', 'min:1', 'max:200'],
            'client_ids.*' => ['integer', 'min:1'],
            'overrides' => ['sometimes', 'array'],
            'overrides.due_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'overrides.target_due_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'overrides.subject_to_fine' => ['sometimes', 'boolean'],
            'idempotency_key' => ['sometimes', 'nullable', 'string', 'max:64'],
        ]);

        $batch = $service->preview(
            $template,
            $data['competence'],
            $data['client_ids'],
            $data['overrides'] ?? [],
            $data['idempotency_key'] ?? null,
        );

        return response()->json(['data' => $this->publicBatch($batch)], 201);
    }

    public function confirm(Request $request, ProcessGenerationBatch $batch, OperationalProcessGenerationService $service): JsonResponse
    {
        $this->authorize('viewAny', ProcessTemplate::class);
        RejectClientOfficeId::strip($request);

        $data = $request->validate([
            'idempotency_key' => ['sometimes', 'nullable', 'string', 'max:64'],
        ]);

        // Autorização via template do batch
        $template = ProcessTemplate::query()->findOrFail($batch->process_template_id);
        $this->authorize('generate', $template);

        $batch = $service->confirm($batch, $data['idempotency_key'] ?? null);

        return response()->json(['data' => $this->publicBatch($batch)]);
    }

    public function show(ProcessGenerationBatch $batch): JsonResponse
    {
        $this->authorize('viewAny', ProcessTemplate::class);
        if ((int) $batch->office_id !== (int) app(\App\Support\CurrentOffice::class)->id()) {
            abort(404);
        }

        $batch->load('items');

        return response()->json(['data' => $this->publicBatch($batch)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function publicBatch(ProcessGenerationBatch $batch): array
    {
        return [
            'id' => $batch->id,
            'process_template_id' => $batch->process_template_id,
            'template_lock_version' => $batch->template_lock_version,
            'competence' => $batch->competence,
            'status' => $batch->status->value,
            'payload_hash' => $batch->payload_hash,
            'idempotency_key' => $batch->idempotency_key,
            'preview_summary' => $batch->preview_summary,
            'expires_at' => $batch->expires_at?->toIso8601String(),
            'queued_at' => $batch->queued_at?->toIso8601String(),
            'completed_at' => $batch->completed_at?->toIso8601String(),
            'items' => $batch->relationLoaded('items')
                ? $batch->items->map(fn (ProcessGenerationItem $i) => [
                    'id' => $i->id,
                    'client_id' => $i->client_id,
                    'status' => $i->status->value,
                    'is_blocked' => $i->is_blocked,
                    'preview_payload' => $i->preview_payload,
                    'alerts' => $i->alerts,
                    'conflicts' => $i->conflicts,
                    'created_process_id' => $i->created_process_id,
                    'error_message' => $i->error_message,
                ])->values()
                : [],
        ];
    }
}
