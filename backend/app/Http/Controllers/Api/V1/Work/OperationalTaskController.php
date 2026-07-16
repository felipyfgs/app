<?php

namespace App\Http\Controllers\Api\V1\Work;

use App\Http\Controllers\Controller;
use App\Models\OperationalComment;
use App\Models\OperationalTask;
use App\Models\OperationalTaskEvidence;
use App\Services\Audit\AuditLogger;
use App\Services\Work\OperationalEvidenceService;
use App\Services\Work\OperationalProcessService;
use App\Services\Work\OperationalQueueQuery;
use App\Services\Work\OperationalTaskTransitionService;
use App\Services\Work\OperationalWorkBulkService;
use App\Support\CurrentOffice;
use App\Support\Work\OptimisticLock;
use App\Support\Work\RejectClientOfficeId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OperationalTaskController extends Controller
{
    public function queue(Request $request, OperationalQueueQuery $query): JsonResponse
    {
        $this->authorize('viewAny', OperationalTask::class);
        RejectClientOfficeId::strip($request);

        $paginator = $query->paginate([
            'tab' => $request->string('tab')->toString() ?: 'open',
            'department_id' => $request->input('department_id'),
            'assignee_membership_id' => $request->input('assignee_membership_id'),
            'client_id' => $request->input('client_id'),
            'q' => $request->input('q'),
            'per_page' => $request->input('per_page', 25),
            'page' => $request->input('page', 1),
            'scope' => $request->input('scope', 'default'),
        ]);

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function show(OperationalTask $task): JsonResponse
    {
        $this->authorize('view', $task);
        $task->load([
            'process.client',
            'department',
            'assigneeMembership.user',
            'evidences',
            'comments',
        ]);

        return response()->json(['data' => $this->public($task, detailed: true)]);
    }

    public function start(Request $request, OperationalTask $task, OperationalTaskTransitionService $service): JsonResponse
    {
        $this->authorize('transition', $task);
        $lock = (int) $request->input('lock_version');
        $task = $service->start($task, $lock);

        return response()->json(['data' => $this->public($task)]);
    }

    public function block(Request $request, OperationalTask $task, OperationalTaskTransitionService $service): JsonResponse
    {
        $this->authorize('transition', $task);
        $data = $request->validate([
            'lock_version' => ['required', 'integer'],
            'reason' => ['required', 'string', 'max:2000'],
        ]);
        $task = $service->block($task, (int) $data['lock_version'], $data['reason']);

        return response()->json(['data' => $this->public($task)]);
    }

    public function resume(Request $request, OperationalTask $task, OperationalTaskTransitionService $service): JsonResponse
    {
        $this->authorize('transition', $task);
        $task = $service->resume($task, (int) $request->input('lock_version'));

        return response()->json(['data' => $this->public($task)]);
    }

    public function complete(Request $request, OperationalTask $task, OperationalTaskTransitionService $service): JsonResponse
    {
        $this->authorize('transition', $task);
        $task = $service->complete($task, (int) $request->input('lock_version'));

        return response()->json(['data' => $this->public($task)]);
    }

    public function dispense(Request $request, OperationalTask $task, OperationalTaskTransitionService $service): JsonResponse
    {
        $this->authorize('dispense', $task);
        $data = $request->validate([
            'lock_version' => ['required', 'integer'],
            'justification' => ['required', 'string', 'max:2000'],
        ]);
        $task = $service->dispense($task, (int) $data['lock_version'], $data['justification']);

        return response()->json(['data' => $this->public($task)]);
    }

    public function reopen(Request $request, OperationalTask $task, OperationalTaskTransitionService $service): JsonResponse
    {
        $this->authorize('reopen', $task);
        $data = $request->validate([
            'lock_version' => ['required', 'integer'],
            'justification' => ['required', 'string', 'max:2000'],
        ]);
        $task = $service->reopen($task, (int) $data['lock_version'], $data['justification']);

        return response()->json(['data' => $this->public($task)]);
    }

    public function claim(Request $request, OperationalTask $task, OperationalProcessService $service): JsonResponse
    {
        $this->authorize('claim', $task);
        $task = $service->claimTask($task, (int) $request->input('lock_version'));

        return response()->json(['data' => $this->public($task)]);
    }

    public function assign(Request $request, OperationalTask $task, CurrentOffice $currentOffice, AuditLogger $audit): JsonResponse
    {
        $this->authorize('assign', $task);
        RejectClientOfficeId::strip($request);

        $data = $request->validate([
            'lock_version' => ['required', 'integer'],
            'assignee_membership_id' => ['nullable', 'integer'],
            'work_department_id' => ['nullable', 'integer'],
        ]);

        OptimisticLock::assert($task, (int) $data['lock_version'], 'operational_task');
        $attrs = [];
        if (array_key_exists('assignee_membership_id', $data)) {
            $attrs['assignee_membership_id'] = $data['assignee_membership_id'];
        }
        if (array_key_exists('work_department_id', $data)) {
            $attrs['work_department_id'] = $data['work_department_id'];
        }
        OptimisticLock::updateOrConflict($task, (int) $data['lock_version'], $attrs, 'operational_task');
        $audit->record('work.task.assign', 'SUCCESS', $task, $attrs);

        return response()->json(['data' => $this->public($task->fresh())]);
    }

    public function bulk(Request $request, OperationalWorkBulkService $service): JsonResponse
    {
        $this->authorize('bulk', OperationalTask::class);
        RejectClientOfficeId::strip($request);

        $data = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.id' => ['required', 'integer'],
            'items.*.lock_version' => ['required', 'integer'],
            'changes' => ['required', 'array'],
            'changes.assignee_membership_id' => ['sometimes', 'nullable', 'integer'],
            'changes.work_department_id' => ['sometimes', 'nullable', 'integer'],
            'changes.due_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'changes.status' => ['sometimes', 'nullable', 'string'],
        ]);

        $updated = $service->apply($data['items'], $data['changes']);

        return response()->json([
            'data' => collect($updated)->map(fn (OperationalTask $t) => $this->public($t))->values(),
        ]);
    }

    public function comment(Request $request, OperationalTask $task, CurrentOffice $currentOffice, AuditLogger $audit): JsonResponse
    {
        $this->authorize('comment', $task);
        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);

        $comment = OperationalComment::query()->create([
            'office_id' => $currentOffice->id(),
            'operational_process_id' => $task->operational_process_id,
            'operational_task_id' => $task->id,
            'author_membership_id' => $currentOffice->membership()?->id,
            'body' => $data['body'],
        ]);

        $audit->record('work.comment.create', 'SUCCESS', $comment, [
            'target' => 'task',
            'task_id' => $task->id,
        ]);

        return response()->json([
            'data' => [
                'id' => $comment->id,
                'body' => $comment->body,
                'created_at' => $comment->created_at?->toIso8601String(),
            ],
        ], 201);
    }

    public function uploadEvidence(Request $request, OperationalTask $task, OperationalEvidenceService $service): JsonResponse
    {
        $this->authorize('uploadEvidence', $task);
        $request->validate(['file' => ['required', 'file', 'max:20480']]);

        $evidence = $service->upload($task, $request->file('file'));

        return response()->json(['data' => $this->publicEvidence($evidence)], 201);
    }

    public function downloadEvidence(OperationalTask $task, OperationalTaskEvidence $evidence, OperationalEvidenceService $service): StreamedResponse
    {
        $this->authorize('downloadEvidence', $task);
        if ((int) $evidence->operational_task_id !== (int) $task->id) {
            abort(404);
        }

        return $service->download($evidence);
    }

    public function removeEvidence(Request $request, OperationalTask $task, OperationalTaskEvidence $evidence, OperationalEvidenceService $service): JsonResponse
    {
        $this->authorize('uploadEvidence', $task);
        if ((int) $evidence->operational_task_id !== (int) $task->id) {
            abort(404);
        }
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        $service->remove($evidence, $data['reason']);

        return response()->json(['data' => ['removed' => true]]);
    }

    /**
     * @return array<string, mixed>
     */
    private function public(OperationalTask $t, bool $detailed = false): array
    {
        $data = [
            'id' => $t->id,
            'operational_process_id' => $t->operational_process_id,
            'sort_order' => $t->sort_order,
            'title' => $t->title,
            'description' => $t->description,
            'status' => $t->status->value,
            'due_date' => $t->due_date?->format('Y-m-d'),
            'is_required' => $t->is_required,
            'is_critical' => $t->is_critical,
            'requires_evidence' => $t->requires_evidence,
            'block_reason' => $t->block_reason,
            'assignee_membership_id' => $t->assignee_membership_id,
            'work_department_id' => $t->work_department_id,
            'lock_version' => $t->lock_version,
            'started_at' => $t->started_at?->toIso8601String(),
            'completed_at' => $t->completed_at?->toIso8601String(),
        ];

        if ($detailed) {
            $data['evidences'] = $t->relationLoaded('evidences')
                ? $t->evidences->map(fn (OperationalTaskEvidence $e) => $this->publicEvidence($e))->values()
                : [];
            $data['comments'] = $t->relationLoaded('comments')
                ? $t->comments->map(fn (OperationalComment $c) => [
                    'id' => $c->id,
                    'body' => $c->body,
                    'author_membership_id' => $c->author_membership_id,
                    'created_at' => $c->created_at?->toIso8601String(),
                ])->values()
                : [];
            if ($t->relationLoaded('process') && $t->process) {
                $data['process'] = [
                    'id' => $t->process->id,
                    'title' => $t->process->title,
                    'competence' => $t->process->competence,
                    'status' => $t->process->status->value,
                    'client' => $t->process->client ? [
                        'id' => $t->process->client->id,
                        'name' => $t->process->client->display_name ?: $t->process->client->legal_name,
                    ] : null,
                ];
            }
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function publicEvidence(OperationalTaskEvidence $e): array
    {
        return [
            'id' => $e->id,
            'original_filename' => $e->original_filename,
            'mime_type' => $e->mime_type,
            'byte_size' => $e->byte_size,
            'sha256' => $e->sha256,
            'created_at' => $e->created_at?->toIso8601String(),
            // vault_object_id intencionalmente omitido
        ];
    }
}
