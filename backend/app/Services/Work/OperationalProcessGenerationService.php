<?php

namespace App\Services\Work;

use App\Domain\Work\CompetenceMonth;
use App\Domain\Work\DueDateCalculator;
use App\Domain\Work\DueRule;
use App\Enums\Work\DueRuleType;
use App\Enums\Work\GenerationBatchStatus;
use App\Enums\Work\GenerationItemStatus;
use App\Enums\Work\ProcessOrigin;
use App\Enums\Work\ProcessStatus;
use App\Enums\Work\TaskStatus;
use App\Models\Client;
use App\Models\OperationalProcess;
use App\Models\OperationalTask;
use App\Models\ProcessGenerationBatch;
use App\Models\ProcessGenerationItem;
use App\Models\ProcessTemplate;
use App\Models\ProcessTemplateTask;
use App\Services\Audit\AuditLogger;
use App\Support\CurrentOffice;
use App\Support\Work\OfficeTimezone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Preview + confirmação idempotente + materialização de processos por modelo.
 */
final class OperationalProcessGenerationService
{
    public function __construct(
        private readonly CurrentOffice $currentOffice,
        private readonly DueDateCalculator $dueDates,
        private readonly MembershipResolver $memberships,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  list<int>  $clientIds
     * @param  array<string, mixed>  $overrides  due_date, target_due_date, subject_to_fine, etc.
     */
    public function preview(
        ProcessTemplate $template,
        string $competence,
        array $clientIds,
        array $overrides = [],
        ?string $idempotencyKey = null,
    ): ProcessGenerationBatch {
        $office = $this->currentOffice->office();
        $competenceMonth = CompetenceMonth::fromString($competence);
        $tz = OfficeTimezone::for($office);

        if (! $template->is_active || (int) $template->office_id !== (int) $office->id) {
            throw ValidationException::withMessages([
                'process_template_id' => ['Modelo inativo ou inexistente neste escritório.'],
            ]);
        }

        $template->load('tasks');
        if ($template->tasks->isEmpty()) {
            throw ValidationException::withMessages([
                'process_template_id' => ['Modelo sem tarefas padrão.'],
            ]);
        }

        $clientIds = array_values(array_unique(array_map('intval', $clientIds)));
        if ($clientIds === []) {
            throw ValidationException::withMessages([
                'client_ids' => ['Selecione ao menos um cliente.'],
            ]);
        }

        $clients = Client::query()
            ->where('office_id', $office->id)
            ->whereIn('id', $clientIds)
            ->get()
            ->keyBy('id');

        $processDue = $this->resolveProcessDue($template, $competenceMonth, $tz, $overrides);

        $itemsPayload = [];
        foreach ($clientIds as $clientId) {
            $client = $clients->get($clientId);
            $conflicts = [];
            $alerts = [];
            $blocked = false;

            if ($client === null) {
                $blocked = true;
                $conflicts[] = ['code' => 'CLIENT_NOT_FOUND', 'message' => 'Cliente inexistente neste escritório.'];
            } elseif (! $client->is_active) {
                $blocked = true;
                $conflicts[] = ['code' => 'CLIENT_INACTIVE', 'message' => 'Cliente inativo.'];
            }

            $duplicate = OperationalProcess::query()
                ->where('office_id', $office->id)
                ->where('process_template_id', $template->id)
                ->where('client_id', $clientId)
                ->where('competence', $competenceMonth->value())
                ->where('origin', ProcessOrigin::Template->value)
                ->exists();

            if ($duplicate) {
                $blocked = true;
                $conflicts[] = ['code' => 'DUPLICATE', 'message' => 'Já existe processo gerado para este modelo/cliente/competência.'];
            }

            $taskPreviews = [];
            $taskError = null;
            try {
                foreach ($template->tasks as $tt) {
                    /** @var ProcessTemplateTask $tt */
                    $taskDue = null;
                    if ($tt->due_rule_type !== null) {
                        $rule = new DueRule($tt->due_rule_type, (int) $tt->due_rule_value);
                        $taskDue = $this->dueDates->calculate($rule, $competenceMonth, $tz, $processDue);
                    }
                    $taskPreviews[] = [
                        'sort_order' => $tt->sort_order,
                        'title' => $tt->title,
                        'description' => $tt->description,
                        'due_date' => $taskDue,
                        'work_department_id' => $tt->default_department_id ?? $template->default_department_id,
                        'assignee_membership_id' => $tt->default_assignee_membership_id,
                        'is_required' => $tt->is_required,
                        'is_critical' => $tt->is_critical,
                        'requires_evidence' => $tt->requires_evidence,
                    ];
                }
            } catch (Throwable $e) {
                $blocked = true;
                $taskError = $e->getMessage();
                $conflicts[] = ['code' => 'DUE_RULE_INCOMPLETE', 'message' => $taskError];
            }

            $itemsPayload[] = [
                'client_id' => $clientId,
                'is_blocked' => $blocked,
                'preview_payload' => [
                    'title' => $template->name.' — '.$competenceMonth->value(),
                    'description' => $template->description,
                    'due_date' => $processDue,
                    'target_due_date' => $overrides['target_due_date'] ?? null,
                    'subject_to_fine' => (bool) ($overrides['subject_to_fine'] ?? false),
                    'work_department_id' => $template->default_department_id,
                    'tasks' => $taskPreviews,
                ],
                'alerts' => $alerts,
                'conflicts' => $conflicts,
            ];
        }

        $requestSnapshot = [
            'process_template_id' => $template->id,
            'template_lock_version' => $template->lock_version,
            'competence' => $competenceMonth->value(),
            'client_ids' => $clientIds,
            'overrides' => $overrides,
        ];
        $payloadHash = hash('sha256', json_encode($requestSnapshot, JSON_THROW_ON_ERROR));

        $idempotencyKey = $idempotencyKey ?: (string) Str::uuid();

        return DB::transaction(function () use (
            $office, $template, $competenceMonth, $payloadHash, $idempotencyKey,
            $requestSnapshot, $itemsPayload,
        ): ProcessGenerationBatch {
            $existing = ProcessGenerationBatch::query()
                ->where('office_id', $office->id)
                ->where('idempotency_key', $idempotencyKey)
                ->first();
            if ($existing !== null) {
                return $existing->load('items');
            }

            $batch = ProcessGenerationBatch::query()->create([
                'office_id' => $office->id,
                'process_template_id' => $template->id,
                'template_lock_version' => $template->lock_version,
                'competence' => $competenceMonth->value(),
                'status' => GenerationBatchStatus::Previewed,
                'payload_hash' => $payloadHash,
                'idempotency_key' => $idempotencyKey,
                'request_snapshot' => $requestSnapshot,
                'preview_summary' => [
                    'total' => count($itemsPayload),
                    'blocked' => count(array_filter($itemsPayload, fn ($i) => $i['is_blocked'])),
                    'ready' => count(array_filter($itemsPayload, fn ($i) => ! $i['is_blocked'])),
                ],
                'requested_by_membership_id' => $this->currentOffice->membership()?->id,
                'expires_at' => now()->addMinutes(30),
            ]);

            foreach ($itemsPayload as $item) {
                ProcessGenerationItem::query()->create([
                    'office_id' => $office->id,
                    'batch_id' => $batch->id,
                    'client_id' => $item['client_id'],
                    'status' => GenerationItemStatus::Previewed,
                    'is_blocked' => $item['is_blocked'],
                    'preview_payload' => $item['preview_payload'],
                    'alerts' => $item['alerts'],
                    'conflicts' => $item['conflicts'],
                ]);
            }

            $this->audit->record('work.generation.preview', 'SUCCESS', $batch, [
                'template_id' => $template->id,
                'competence' => $competenceMonth->value(),
                'clients' => count($itemsPayload),
            ]);

            return $batch->load('items');
        });
    }

    public function confirm(ProcessGenerationBatch $batch, ?string $idempotencyKey = null): ProcessGenerationBatch
    {
        $officeId = $this->currentOffice->id();
        if ((int) $batch->office_id !== (int) $officeId) {
            abort(404);
        }

        if ($batch->status === GenerationBatchStatus::Queued
            || $batch->status === GenerationBatchStatus::Processing
            || $batch->status === GenerationBatchStatus::Completed
            || $batch->status === GenerationBatchStatus::CompletedWithErrors) {
            return $batch->load('items');
        }

        if ($batch->status !== GenerationBatchStatus::Previewed) {
            throw ValidationException::withMessages([
                'batch' => ['Batch não está em estado PREVIEWED.'],
            ]);
        }

        if ($batch->expires_at !== null && $batch->expires_at->isPast()) {
            $batch->forceFill(['status' => GenerationBatchStatus::Expired])->save();
            throw ValidationException::withMessages([
                'batch' => ['Preview expirado; gere uma nova prévia.'],
            ]);
        }

        $template = ProcessTemplate::query()->findOrFail($batch->process_template_id);
        if ((int) $template->lock_version !== (int) $batch->template_lock_version) {
            throw ValidationException::withMessages([
                'batch' => ['Modelo foi alterado após o preview; gere uma nova prévia.'],
            ]);
        }

        if (! $template->is_active) {
            throw ValidationException::withMessages([
                'batch' => ['Modelo foi desativado.'],
            ]);
        }

        $ready = $batch->items()->where('is_blocked', false)->count();
        if ($ready === 0) {
            throw ValidationException::withMessages([
                'batch' => ['Nenhum item elegível para geração.'],
            ]);
        }

        $batch->forceFill([
            'status' => GenerationBatchStatus::Queued,
            'queued_at' => now(),
        ])->save();

        $batch->items()
            ->where('is_blocked', false)
            ->update(['status' => GenerationItemStatus::Queued->value]);

        $this->audit->record('work.generation.confirm', 'SUCCESS', $batch, [
            'ready_items' => $ready,
        ]);

        // Processamento síncrono no MVP de testes (Horizon job enfileira em prod)
        $this->processBatch($batch->fresh(['items']));

        return $batch->fresh(['items']);
    }

    public function processBatch(ProcessGenerationBatch $batch): void
    {
        $batch->forceFill(['status' => GenerationBatchStatus::Processing])->save();

        $hadError = false;
        $hadSuccess = false;

        foreach ($batch->items as $item) {
            if ($item->is_blocked || $item->status === GenerationItemStatus::Created) {
                continue;
            }
            if ($item->status !== GenerationItemStatus::Queued
                && $item->status !== GenerationItemStatus::Retryable
                && $item->status !== GenerationItemStatus::Previewed) {
                continue;
            }

            try {
                $this->materializeItem($batch, $item);
                $hadSuccess = true;
            } catch (Throwable $e) {
                $hadError = true;
                $code = $e instanceof \Illuminate\Database\UniqueConstraintViolationException
                    ? GenerationItemStatus::SkippedDuplicate
                    : GenerationItemStatus::Failed;

                if ($code === GenerationItemStatus::SkippedDuplicate) {
                    $item->forceFill([
                        'status' => $code,
                        'error_message' => 'Duplicidade detectada pela constraint.',
                        'attempt_count' => $item->attempt_count + 1,
                    ])->save();
                } else {
                    $item->forceFill([
                        'status' => $code,
                        'error_message' => $e->getMessage(),
                        'attempt_count' => $item->attempt_count + 1,
                    ])->save();
                }
            }
        }

        $status = match (true) {
            $hadError && $hadSuccess => GenerationBatchStatus::CompletedWithErrors,
            $hadError && ! $hadSuccess => GenerationBatchStatus::Failed,
            default => GenerationBatchStatus::Completed,
        };

        $batch->forceFill([
            'status' => $status,
            'completed_at' => now(),
        ])->save();
    }

    private function materializeItem(ProcessGenerationBatch $batch, ProcessGenerationItem $item): void
    {
        DB::transaction(function () use ($batch, $item): void {
            $payload = $item->preview_payload ?? [];

            $process = OperationalProcess::query()->create([
                'office_id' => $batch->office_id,
                'client_id' => $item->client_id,
                'process_template_id' => $batch->process_template_id,
                'generation_batch_id' => $batch->id,
                'origin' => ProcessOrigin::Template,
                'title' => $payload['title'] ?? 'Processo',
                'description' => $payload['description'] ?? null,
                'competence' => $batch->competence,
                'due_date' => $payload['due_date'] ?? null,
                'target_due_date' => $payload['target_due_date'] ?? null,
                'subject_to_fine' => (bool) ($payload['subject_to_fine'] ?? false),
                'work_department_id' => $payload['work_department_id'] ?? null,
                'status' => ProcessStatus::AFazer,
                'template_snapshot' => [
                    'template_id' => $batch->process_template_id,
                    'template_lock_version' => $batch->template_lock_version,
                    'payload' => $payload,
                ],
                'lock_version' => 1,
                'created_by_membership_id' => $batch->requested_by_membership_id,
            ]);

            foreach ($payload['tasks'] ?? [] as $t) {
                OperationalTask::query()->create([
                    'office_id' => $batch->office_id,
                    'operational_process_id' => $process->id,
                    'sort_order' => $t['sort_order'],
                    'title' => $t['title'],
                    'description' => $t['description'] ?? null,
                    'status' => TaskStatus::AFazer,
                    'due_date' => $t['due_date'] ?? null,
                    'work_department_id' => $t['work_department_id'] ?? null,
                    'assignee_membership_id' => $t['assignee_membership_id'] ?? null,
                    'is_required' => (bool) ($t['is_required'] ?? true),
                    'is_critical' => (bool) ($t['is_critical'] ?? false),
                    'requires_evidence' => (bool) ($t['requires_evidence'] ?? false),
                    'lock_version' => 1,
                ]);
            }

            $item->forceFill([
                'status' => GenerationItemStatus::Created,
                'created_process_id' => $process->id,
                'attempt_count' => $item->attempt_count + 1,
                'error_message' => null,
            ])->save();
        });
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function resolveProcessDue(
        ProcessTemplate $template,
        CompetenceMonth $competence,
        string $tz,
        array $overrides,
    ): ?string {
        if (! empty($overrides['due_date'])) {
            return (string) $overrides['due_date'];
        }

        if ($template->default_due_rule_type === null) {
            return null;
        }

        $rule = new DueRule($template->default_due_rule_type, (int) $template->default_due_rule_value);

        return $this->dueDates->calculate($rule, $competence, $tz);
    }
}
