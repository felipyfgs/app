<?php

namespace App\Models;

use App\Casts\FiscalSourceProvenanceCast;
use App\Enums\FiscalVerificationKind;
use App\Enums\MeiAutomationStatus;
use App\Enums\MeiProvider;
use App\Models\Concerns\BelongsToOffice;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'office_id',
    'client_id',
    'fiscal_monitoring_run_id',
    'fiscal_mutation_operation_id',
    'external_job_id',
    'operation_key',
    'provider',
    'status',
    'idempotency_key',
    'request_fingerprint',
    'attempt_number',
    'source_provenance',
    'verification_kind',
    'portal_version',
    'parser_version',
    'captcha_driver',
    'captcha_cost_micros',
    'fallback_reason',
    'error_code',
    'error_message',
    'safe_metadata',
    'started_at',
    'finished_at',
])]
class MeiAutomationAttempt extends Model
{
    use BelongsToOffice;

    protected function casts(): array
    {
        return [
            'provider' => MeiProvider::class,
            'status' => MeiAutomationStatus::class,
            'source_provenance' => FiscalSourceProvenanceCast::class,
            'verification_kind' => FiscalVerificationKind::class,
            'attempt_number' => 'integer',
            'captcha_cost_micros' => 'integer',
            'safe_metadata' => 'array',
            'started_at' => 'immutable_datetime',
            'finished_at' => 'immutable_datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function monitoringRun(): BelongsTo
    {
        return $this->belongsTo(FiscalMonitoringRun::class, 'fiscal_monitoring_run_id');
    }

    public function mutationOperation(): BelongsTo
    {
        return $this->belongsTo(FiscalMutationOperation::class, 'fiscal_mutation_operation_id');
    }

    /** @return array<string, mixed> */
    public function toPublicArray(): array
    {
        return [
            'id' => $this->id,
            'client_id' => $this->client_id,
            'operation_key' => $this->operation_key,
            'provider' => $this->provider?->value,
            'status' => $this->status?->value,
            'source_provenance' => $this->source_provenance instanceof \BackedEnum
                ? $this->source_provenance->value
                : $this->source_provenance,
            'verification_kind' => $this->verification_kind?->value,
            'fallback_reason' => $this->fallback_reason,
            'error_code' => $this->error_code,
            'error_message' => $this->error_message,
            'metadata' => $this->safe_metadata ?? [],
            'started_at' => $this->started_at?->toIso8601String(),
            'finished_at' => $this->finished_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
