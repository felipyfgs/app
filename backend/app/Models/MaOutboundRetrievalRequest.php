<?php

namespace App\Models;

use App\Enums\OutboundCaptureMode;
use App\Enums\OutboundFiscalModel;
use App\Enums\OutboundRetrievalStatus;
use App\Models\Concerns\BelongsToOffice;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'office_id', 'outbound_capture_profile_id', 'establishment_id', 'environment', 'model',
    'direction', 'competence', 'status', 'mode', 'external_ref', 'requested_at', 'expires_at',
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
            'requested_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'ready_at' => 'immutable_datetime',
            'ingested_at' => 'immutable_datetime',
            'files_expected' => 'integer',
            'files_ingested' => 'integer',
        ];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(OutboundCaptureProfile::class, 'outbound_capture_profile_id');
    }

    public function establishment(): BelongsTo
    {
        return $this->belongsTo(Establishment::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function toPublicArray(): array
    {
        return [
            'id' => $this->id,
            'profile_id' => $this->outbound_capture_profile_id,
            'establishment_id' => $this->establishment_id,
            'environment' => $this->environment,
            'model' => $this->model->value,
            'direction' => $this->direction,
            'competence' => $this->competence,
            'status' => $this->status->value,
            'mode' => $this->mode->value,
            'external_ref' => $this->external_ref,
            'expires_at' => $this->expires_at?->toIso8601String(),
            'files_ingested' => $this->files_ingested,
            'files_expected' => $this->files_expected,
        ];
    }
}
