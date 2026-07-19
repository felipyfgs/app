<?php

namespace App\Http\Controllers\Api\V1\Work;

use App\Domain\Work\DueDateCalculator;
use App\Domain\Work\WorkRiskCalculator;
use App\Enums\Work\TaskStatus;
use App\Enums\Work\WorkRisk;
use App\Http\Controllers\Controller;
use App\Models\OperationalComment;
use App\Models\OperationalProcess;
use App\Models\OperationalTask;
use App\Services\Audit\AuditLogger;
use App\Services\Work\OperationalProcessService;
use App\Services\Work\OperationalTimelineQuery;
use App\Support\CurrentOffice;
use App\Support\Work\OfficeTimezone;
use App\Support\Work\RejectClientOfficeId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OperationalProcessController extends Controller
{
    public function index(Request $request, CurrentOffice $currentOffice): JsonResponse
    {
        $this->authorize('viewAny', OperationalProcess::class);
        RejectClientOfficeId::strip($request);

        $perPage = min(max((int) $request->input('per_page', 25), 1), 100);
        $q = OperationalProcess::query()
            ->with([
                'client:id,legal_name,display_name',
                'tasks',
                'department:id,name,code',
                'assigneeMembership:id,user_id,office_id',
                'assigneeMembership.user:id,name',
            ])
            ->where('office_id', $currentOffice->id());

        if ($request->filled('competence')) {
            $q->where('competence', $request->string('competence')->toString());
        }
        if ($request->filled('client_id')) {
            $q->where('client_id', (int) $request->input('client_id'));
        }
        if ($request->filled('status')) {
            $q->where('status', $request->string('status')->toString());
        }
        if ($request->filled('department_id')) {
            $q->where('work_department_id', (int) $request->input('department_id'));
        }
        if ($request->filled('assignee_membership_id')) {
            $q->where('assignee_membership_id', (int) $request->input('assignee_membership_id'));
        }
        if ($request->filled('q')) {
            $needle = '%'.mb_strtolower($request->string('q')->toString()).'%';
            $q->where(function ($search) use ($needle): void {
                $search->whereRaw('LOWER(title) LIKE ?', [$needle])
                    ->orWhereHas('client', function ($client) use ($needle): void {
                        $client->whereRaw('LOWER(legal_name) LIKE ?', [$needle])
                            ->orWhereRaw('LOWER(COALESCE(display_name, \'\')) LIKE ?', [$needle]);
                    });
            });
        }

        $sort = match ($request->string('sort')->toString()) {
            'title' => 'title',
            'competence' => 'competence',
            'status' => 'status',
            'due_date' => 'due_date',
            default => 'id',
        };
        $direction = $request->string('direction')->lower()->toString() === 'asc' ? 'asc' : 'desc';
        $q->orderBy($sort, $direction);
        if ($sort !== 'id') {
            $q->orderBy('id', $direction);
        }

        $paginator = $q->paginate($perPage);
        $today = (new DueDateCalculator)->todayInOffice(OfficeTimezone::for($currentOffice->office()));

        return response()->json([
            'data' => collect($paginator->items())->map(fn (OperationalProcess $p) => $this->public($p, false, $today)),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function show(OperationalProcess $process, CurrentOffice $currentOffice): JsonResponse
    {
        $this->authorize('view', $process);
        $process->load(['client', 'tasks.evidences', 'tasks.assigneeMembership.user', 'department', 'assigneeMembership.user', 'comments']);
        $today = (new DueDateCalculator)->todayInOffice(OfficeTimezone::for($currentOffice->office()));

        return response()->json(['data' => $this->public($process, detailed: true, today: $today)]);
    }

    public function store(Request $request, OperationalProcessService $service): JsonResponse
    {
        $this->authorize('create', OperationalProcess::class);
        RejectClientOfficeId::strip($request);

        $data = $request->validate([
            'client_id' => ['required', 'integer'],
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'competence' => ['required', 'string', 'regex:/^\d{4}-\d{2}$/'],
            'due_date' => ['nullable', 'date_format:Y-m-d'],
            'target_due_date' => ['nullable', 'date_format:Y-m-d'],
            'subject_to_fine' => ['sometimes', 'boolean'],
            'work_department_id' => ['nullable', 'integer'],
            'assignee_membership_id' => ['nullable', 'integer'],
            'tasks' => ['required', 'array', 'min:1'],
            'tasks.*.title' => ['required', 'string', 'max:200'],
            'tasks.*.description' => ['nullable', 'string'],
            'tasks.*.sort_order' => ['sometimes', 'integer', 'min:1'],
            'tasks.*.due_date' => ['nullable', 'date_format:Y-m-d'],
            'tasks.*.work_department_id' => ['nullable', 'integer'],
            'tasks.*.assignee_membership_id' => ['nullable', 'integer'],
            'tasks.*.is_required' => ['sometimes', 'boolean'],
            'tasks.*.is_critical' => ['sometimes', 'boolean'],
            'tasks.*.requires_evidence' => ['sometimes', 'boolean'],
        ]);

        $process = $service->createManual($data, $data['tasks']);

        return response()->json(['data' => $this->public($process, detailed: true)], 201);
    }

    public function update(Request $request, OperationalProcess $process, OperationalProcessService $service): JsonResponse
    {
        $this->authorize('update', $process);
        RejectClientOfficeId::strip($request);

        $data = $request->validate([
            'lock_version' => ['required', 'integer', 'min:1'],
            'title' => ['sometimes', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'due_date' => ['nullable', 'date_format:Y-m-d'],
            'target_due_date' => ['nullable', 'date_format:Y-m-d'],
            'subject_to_fine' => ['sometimes', 'boolean'],
            'work_department_id' => ['nullable', 'integer'],
            'assignee_membership_id' => ['nullable', 'integer'],
        ]);

        $process = $service->update($process, (int) $data['lock_version'], $data);

        return response()->json(['data' => $this->public($process, detailed: true)]);
    }

    public function archive(Request $request, OperationalProcess $process, OperationalProcessService $service): JsonResponse
    {
        $this->authorize('archive', $process);
        RejectClientOfficeId::strip($request);

        $data = $request->validate([
            'lock_version' => ['required', 'integer', 'min:1'],
        ]);

        $process = $service->archive($process, (int) $data['lock_version']);

        return response()->json(['data' => $this->public($process)]);
    }

    public function comment(Request $request, OperationalProcess $process, CurrentOffice $currentOffice, AuditLogger $audit): JsonResponse
    {
        $this->authorize('comment', $process);
        RejectClientOfficeId::strip($request);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $comment = OperationalComment::query()->create([
            'office_id' => $currentOffice->id(),
            'operational_process_id' => $process->id,
            'operational_task_id' => null,
            'author_membership_id' => $currentOffice->membership()?->id,
            'body' => $data['body'],
        ]);

        $audit->record('work.comment.create', 'SUCCESS', $comment, [
            'target' => 'process',
            'process_id' => $process->id,
        ]);

        return response()->json([
            'data' => [
                'id' => $comment->id,
                'body' => $comment->body,
                'created_at' => $comment->created_at?->toIso8601String(),
                'author_membership_id' => $comment->author_membership_id,
            ],
        ], 201);
    }

    public function timeline(OperationalProcess $process, OperationalTimelineQuery $timeline): JsonResponse
    {
        $this->authorize('view', $process);

        return response()->json([
            'data' => $timeline->forProcess($process),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function public(OperationalProcess $p, bool $detailed = false, ?string $today = null): array
    {
        $today ??= (new DueDateCalculator)->todayInOffice('America/Sao_Paulo');
        $riskCalc = new WorkRiskCalculator;

        $taskCount = $p->relationLoaded('tasks') ? $p->tasks->count() : null;
        $completedCount = null;
        $openCount = null;
        $progressPercent = null;
        $risks = [];

        if ($p->relationLoaded('tasks')) {
            $completedCount = $p->tasks->filter(
                fn (OperationalTask $t) => in_array($t->status, [TaskStatus::Concluida, TaskStatus::Dispensada], true)
            )->count();
            $openCount = $p->tasks->filter(fn (OperationalTask $t) => ! $t->status->isTerminal())->count();
            $progressPercent = $taskCount > 0 ? (int) round(($completedCount / $taskCount) * 100) : 0;

            foreach ($p->tasks as $t) {
                if ($t->status->isTerminal()) {
                    continue;
                }
                foreach ($riskCalc->forTask(
                    $t->status,
                    $t->due_date?->format('Y-m-d'),
                    $p->due_date?->format('Y-m-d'),
                    (bool) $p->subject_to_fine,
                    $t->assignee_membership_id,
                    $today,
                ) as $r) {
                    $risks[$r->value] = true;
                }
            }
        }

        $data = [
            'id' => $p->id,
            'title' => $p->title,
            'description' => $p->description,
            'competence' => $p->competence,
            'origin' => $p->origin->value,
            'status' => $p->status->value,
            'due_date' => $p->due_date?->format('Y-m-d'),
            'target_due_date' => $p->target_due_date?->format('Y-m-d'),
            'subject_to_fine' => $p->subject_to_fine,
            'work_department_id' => $p->work_department_id,
            'assignee_membership_id' => $p->assignee_membership_id,
            'client_id' => $p->client_id,
            'process_template_id' => $p->process_template_id,
            'lock_version' => $p->lock_version,
            'client' => $p->relationLoaded('client') && $p->client ? [
                'id' => $p->client->id,
                'name' => $p->client->display_name ?: $p->client->legal_name,
            ] : null,
            'department' => $p->relationLoaded('department') && $p->department ? [
                'id' => $p->department->id,
                'name' => $p->department->name,
                'code' => $p->department->code,
            ] : null,
            'assignee' => $p->relationLoaded('assigneeMembership') && $p->assigneeMembership?->user ? [
                'membership_id' => $p->assigneeMembership->id,
                'name' => $p->assigneeMembership->user->name,
            ] : null,
            'task_count' => $taskCount,
            'completed_task_count' => $completedCount,
            'open_task_count' => $openCount,
            'progress_percent' => $progressPercent,
            'risks' => array_keys($risks),
        ];

        if ($detailed && $p->relationLoaded('tasks')) {
            $data['tasks'] = $p->tasks->map(function (OperationalTask $t) use ($p, $riskCalc, $today) {
                $taskRisks = $riskCalc->forTask(
                    $t->status,
                    $t->due_date?->format('Y-m-d'),
                    $p->due_date?->format('Y-m-d'),
                    (bool) $p->subject_to_fine,
                    $t->assignee_membership_id,
                    $today,
                );

                return [
                    'id' => $t->id,
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
                    'risks' => array_map(fn (WorkRisk $r) => $r->value, $taskRisks),
                    'assignee' => $t->relationLoaded('assigneeMembership') && $t->assigneeMembership?->user ? [
                        'membership_id' => $t->assigneeMembership->id,
                        'name' => $t->assigneeMembership->user->name,
                    ] : null,
                    'evidence_count' => $t->relationLoaded('evidences') ? $t->evidences->count() : null,
                ];
            })->values();

            if ($p->relationLoaded('comments')) {
                $data['comments'] = $p->comments->map(fn (OperationalComment $c) => [
                    'id' => $c->id,
                    'body' => $c->body,
                    'author_membership_id' => $c->author_membership_id,
                    'created_at' => $c->created_at?->toIso8601String(),
                ])->values();
            }
        }

        return $data;
    }
}
