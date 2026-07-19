<?php

namespace App\Http\Controllers\Api\V1\Work;

use App\Enums\Work\DueRuleType;
use App\Http\Controllers\Controller;
use App\Models\ProcessTemplate;
use App\Models\ProcessTemplateTask;
use App\Services\Audit\AuditLogger;
use App\Services\Work\MembershipResolver;
use App\Support\CurrentOffice;
use App\Support\Work\OptimisticLock;
use App\Support\Work\RejectClientOfficeId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProcessTemplateController extends Controller
{
    public function index(Request $request, CurrentOffice $currentOffice): JsonResponse
    {
        $this->authorize('viewAny', ProcessTemplate::class);
        RejectClientOfficeId::strip($request);

        $perPage = min(max((int) $request->input('per_page', 25), 1), 100);
        $q = ProcessTemplate::query()
            ->with('tasks')
            ->where('office_id', $currentOffice->id());

        if ($request->filled('is_active')) {
            $q->where('is_active', $request->boolean('is_active'));
        }
        if ($request->filled('q')) {
            $needle = '%'.mb_strtolower($request->string('q')->toString()).'%';
            $q->where(function ($search) use ($needle): void {
                $search->whereRaw('LOWER(name) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(COALESCE(description, \'\')) LIKE ?', [$needle]);
            });
        }

        $sort = match ($request->string('sort')->toString()) {
            'is_active' => 'is_active',
            'id' => 'id',
            default => 'name',
        };
        $defaultDirection = $sort === 'name' ? 'asc' : 'desc';
        $requestedDirection = $request->string('direction')->lower()->toString();
        $direction = in_array($requestedDirection, ['asc', 'desc'], true)
            ? $requestedDirection
            : $defaultDirection;
        $q->orderBy($sort, $direction);
        if ($sort !== 'id') {
            $q->orderBy('id', $direction);
        }

        $paginator = $q->paginate($perPage);

        return response()->json([
            'data' => collect($paginator->items())->map(fn (ProcessTemplate $t) => $this->public($t)),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function show(ProcessTemplate $template): JsonResponse
    {
        $this->authorize('view', $template);
        $template->load('tasks');

        return response()->json(['data' => $this->public($template)]);
    }

    public function store(Request $request, CurrentOffice $currentOffice, MembershipResolver $memberships, AuditLogger $audit): JsonResponse
    {
        $this->authorize('create', ProcessTemplate::class);
        RejectClientOfficeId::strip($request);

        $data = $this->validated($request, $currentOffice);
        $this->validateRelations($data, $memberships);

        $template = DB::transaction(function () use ($data, $currentOffice): ProcessTemplate {
            $template = ProcessTemplate::query()->create([
                'office_id' => $currentOffice->id(),
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'default_department_id' => $data['default_department_id'] ?? null,
                'default_due_rule_type' => $data['default_due_rule_type'] ?? null,
                'default_due_rule_value' => $data['default_due_rule_value'] ?? null,
                'is_active' => $data['is_active'] ?? true,
                'lock_version' => 1,
                'created_by_membership_id' => $currentOffice->membership()?->id,
            ]);

            $this->syncTasks($template, $data['tasks'] ?? [], $currentOffice->id());

            return $template->load('tasks');
        });

        $audit->record('work.template.create', 'SUCCESS', $template);

        return response()->json(['data' => $this->public($template)], 201);
    }

    public function update(Request $request, ProcessTemplate $template, CurrentOffice $currentOffice, MembershipResolver $memberships, AuditLogger $audit): JsonResponse
    {
        $this->authorize('update', $template);
        RejectClientOfficeId::strip($request);

        $data = $this->validated($request, $currentOffice, $template->id);
        $lockVersion = (int) $request->input('lock_version', $template->lock_version);
        OptimisticLock::assert($template, $lockVersion, 'process_template');
        $this->validateRelations($data, $memberships);

        $template = DB::transaction(function () use ($template, $data, $lockVersion, $currentOffice): ProcessTemplate {
            $attrs = collect($data)->only([
                'name', 'description', 'default_department_id',
                'default_due_rule_type', 'default_due_rule_value', 'is_active',
            ])->all();

            OptimisticLock::updateOrConflict($template, $lockVersion, $attrs, 'process_template');
            $template->refresh();

            if (isset($data['tasks'])) {
                ProcessTemplateTask::query()
                    ->where('process_template_id', $template->id)
                    ->delete();
                $this->syncTasks($template, $data['tasks'], $currentOffice->id());
                // bump version again after tasks
                $template->forceFill(['lock_version' => $template->lock_version + 1])->save();
            }

            return $template->fresh('tasks');
        });

        $audit->record('work.template.update', 'SUCCESS', $template);

        return response()->json(['data' => $this->public($template)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, CurrentOffice $currentOffice, ?int $ignoreId = null): array
    {
        $nameRule = Rule::unique('process_templates', 'name')->where('office_id', $currentOffice->id());
        if ($ignoreId !== null) {
            $nameRule = $nameRule->ignore($ignoreId);
        }

        return $request->validate([
            'name' => ['required', 'string', 'max:160', $nameRule],
            'description' => ['nullable', 'string'],
            'default_department_id' => ['nullable', 'integer'],
            'default_due_rule_type' => ['nullable', 'string', Rule::enum(DueRuleType::class)],
            'default_due_rule_value' => ['nullable', 'integer', 'min:0', 'max:366'],
            'is_active' => ['sometimes', 'boolean'],
            'lock_version' => ['sometimes', 'integer', 'min:1'],
            'tasks' => ['sometimes', 'array', 'min:1'],
            'tasks.*.sort_order' => ['required_with:tasks', 'integer', 'min:1'],
            'tasks.*.title' => ['required_with:tasks', 'string', 'max:200'],
            'tasks.*.description' => ['nullable', 'string'],
            'tasks.*.due_rule_type' => ['nullable', 'string', Rule::enum(DueRuleType::class)],
            'tasks.*.due_rule_value' => ['nullable', 'integer', 'min:0', 'max:366'],
            'tasks.*.default_department_id' => ['nullable', 'integer'],
            'tasks.*.default_assignee_membership_id' => ['nullable', 'integer'],
            'tasks.*.is_required' => ['sometimes', 'boolean'],
            'tasks.*.is_critical' => ['sometimes', 'boolean'],
            'tasks.*.requires_evidence' => ['sometimes', 'boolean'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function validateRelations(array $data, MembershipResolver $memberships): void
    {
        if (! empty($data['default_department_id'])) {
            $memberships->requireActiveDepartment((int) $data['default_department_id']);
        }
        foreach ($data['tasks'] ?? [] as $t) {
            if (! empty($t['default_department_id'])) {
                $memberships->requireActiveDepartment((int) $t['default_department_id']);
            }
            if (! empty($t['default_assignee_membership_id'])) {
                $memberships->requireActiveMembership((int) $t['default_assignee_membership_id']);
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $tasks
     */
    private function syncTasks(ProcessTemplate $template, array $tasks, int $officeId): void
    {
        $orders = [];
        foreach ($tasks as $t) {
            $order = (int) $t['sort_order'];
            if (isset($orders[$order])) {
                throw ValidationException::withMessages([
                    'tasks' => ['Ordens de tarefa devem ser únicas.'],
                ]);
            }
            $orders[$order] = true;

            ProcessTemplateTask::query()->create([
                'office_id' => $officeId,
                'process_template_id' => $template->id,
                'sort_order' => $order,
                'title' => $t['title'],
                'description' => $t['description'] ?? null,
                'due_rule_type' => $t['due_rule_type'] ?? null,
                'due_rule_value' => $t['due_rule_value'] ?? null,
                'default_department_id' => $t['default_department_id'] ?? null,
                'default_assignee_membership_id' => $t['default_assignee_membership_id'] ?? null,
                'is_required' => $t['is_required'] ?? true,
                'is_critical' => $t['is_critical'] ?? false,
                'requires_evidence' => $t['requires_evidence'] ?? false,
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function public(ProcessTemplate $t): array
    {
        return [
            'id' => $t->id,
            'name' => $t->name,
            'description' => $t->description,
            'default_department_id' => $t->default_department_id,
            'default_due_rule_type' => $t->default_due_rule_type?->value,
            'default_due_rule_value' => $t->default_due_rule_value,
            'is_active' => $t->is_active,
            'lock_version' => $t->lock_version,
            'tasks' => $t->relationLoaded('tasks')
                ? $t->tasks->map(fn (ProcessTemplateTask $task) => [
                    'id' => $task->id,
                    'sort_order' => $task->sort_order,
                    'title' => $task->title,
                    'description' => $task->description,
                    'due_rule_type' => $task->due_rule_type?->value,
                    'due_rule_value' => $task->due_rule_value,
                    'default_department_id' => $task->default_department_id,
                    'default_assignee_membership_id' => $task->default_assignee_membership_id,
                    'is_required' => $task->is_required,
                    'is_critical' => $task->is_critical,
                    'requires_evidence' => $task->requires_evidence,
                ])->values()
                : [],
            'created_at' => $t->created_at?->toIso8601String(),
            'updated_at' => $t->updated_at?->toIso8601String(),
        ];
    }
}
