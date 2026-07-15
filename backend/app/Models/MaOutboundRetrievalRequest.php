<?php

namespace App\Models;

use App\Enums\OutboundCaptureMode;
use App\Enums\OutboundFiscalModel;
use App\Enums\OutboundRetrievalOrigin;
use App\Enums\OutboundRetrievalStatus;
use App\Enums\SvrsNfceFailureReason;
use App\Enums\SvrsNfceRecoveryStatus;
use App\Models\Concerns\BelongsToOffice;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'office_id', 'outbound_capture_profile_id', 'establishment_id', 'environment', 'model',
    'direction', 'competence', 'status', 'mode', 'origin', 'access_key', 'outbound_number_state_id',
    'recovery_status', 'failure_reason', 'attempt_count', 'next_attempt_at', 'correlation_id',
    'sha256', 'dfe_document_id', 'external_ref', 'requested_at', 'expires_at',
    'ready_at', 'ingested_at', 'files_expected', 'files_ingested', 'last_error', 'created_by',
])]
class MaOutboundRetrievalRequest extends Model
{
    use BelongsToOffice;

    protected function casts(): array
    {
        return [
            'model' => OutboundFiscalModel::class,
            'status' => OutboundRetrievalStatus::class,
            'mode' => OutboundCaptureMode::class,
            'origin' => OutboundRetrievalOrigin::class,
            'recovery_status' => SvrsNfceRecoveryStatus::class,
            'failure_reason' => SvrsNfceFailureReason::class,
            'attempt_count' => 'integer',
            'files_expected' => 'integer',
            'files_ingested' => 'integer',
            'requested_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'ready_at' => 'immutable_datetime',
            'ingested_at' => 'immutable_datetime',
            'next_attempt_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $model): void {
            if (is_string($model->access_key) && $model->access_key !== '') {
                $model->access_key = strtoupper(preg_replace('/\s+/', '', $model->access_key) ?? '');
            }
        });
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(OutboundCaptureProfile::class, 'outbound_capture_profile_id');
    }

    public function establishment(): BelongsTo
    {
        return $this->belongsTo(Establishment::class);
    }

    public function numberState(): BelongsTo
    {
        return $this->belongsTo(OutboundNumberState::class, 'outbound_number_state_id');
    }

    public function dfeDocument(): BelongsTo
    {
        return $this->belongsTo(DfeDocument::class, 'dfe_document_id');
    }

    public function recoveryAttempts(): HasMany
    {
        return $this->hasMany(OutboundXmlRecoveryAttempt::class, 'ma_outbound_retrieval_request_id');
    }

    /**
     * @return array<string, mixed>
     */
    public function toPublicArray(): array
    {
        $key = $this->access_key;

        return [
            'id' => $this->id,
            'profile_id' => $this->outbound_capture_profile_id,
            'establishment_id' => $this->establishment_id,
            'environment' => $this->environment,
            'model' => $this->model instanceof OutboundFiscalModel ? $this->model->value : $this->model,
            'direction' => $this->direction,
            'competence' => $this->competence,
            'status' => $this->status instanceof OutboundRetrievalStatus ? $this->status->value : $this->status,
            'mode' => $this->mode instanceof OutboundCaptureMode ? $this->mode->value : $this->mode,
            'origin' => $this->origin instanceof OutboundRetrievalOrigin ? $this->origin->value : $this->origin,
            'access_key_masked' => $this->maskAccessKey(is_string($key) ? $key : null),
            'recovery_status' => $this->recovery_status instanceof SvrsNfceRecoveryStatus
                ? $this->recovery_status->value
                : $this->recovery_status,
            'failure_reason' => $this->failure_reason instanceof SvrsNfceFailureReason
                ? $this->failure_reason->value
                : $this->failure_reason,
            'failure_label' => $this->failure_reason instanceof SvrsNfceFailureReason
                ? $this->failure_reason->label()
                : null,
            'attempt_count' => $this->attempt_count,
            'next_attempt_at' => $this->next_attempt_at?->toIso8601String(),
            'correlation_id' => $this->correlation_id,
            'sha256' => $this->sha256,
            'external_ref' => $this->external_ref,
            'expires_at' => $this->expires_at?->toIso8601String(),
            'files_ingested' => $this->files_ingested,
            'files_expected' => $this->files_expected,
        ];
    }

    private function maskAccessKey(?string $key): ?string
    {
        if ($key === null || $key === '') {
            return null;
        }
        $key = strtoupper($key);
        if (strlen($key) < 12) {
            return str_repeat('*', strlen($key));
        }

        return substr($key, 0, 6).str_repeat('*', max(0, strlen($key) - 10)).substr($key, -4);
    }
}
