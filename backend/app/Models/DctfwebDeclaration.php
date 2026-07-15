<?php

namespace App\Models;

use App\Enums\DctfwebTransmissionStatus;
use App\Enums\FiscalCoverage;
use App\Enums\FiscalPaymentStatus;
use App\Enums\FiscalSituation;
use App\Models\Concerns\BelongsToOffice;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'office_id',
    'client_id',
    'competence_id',
    'period_key',
    'declaration_type',
    'transmission_status',
    'situation',
    'coverage',
    'receipt_number',
    'transmitted_at',
    'official_at',
    'evidence_version',
    'payment_status',
    'current_snapshot_id',
    'metadata',
])]
class DctfwebDeclaration extends Model
{
    use BelongsToOffice;

    protected function casts(): array
    {
        return [
            'transmission_status' => DctfwebTransmissionStatus::class,
            'situation' => FiscalSituation::class,
            'coverage' => FiscalCoverage::class,
            'payment_status' => FiscalPaymentStatus::class,
            'transmitted_at' => 'immutable_datetime',
            'official_at' => 'immutable_datetime',
            'evidence_version' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function competence(): BelongsTo
    {
        return $this->belongsTo(FiscalCompetence::class, 'competence_id');
    }

    public function evidenceVersions(): HasMany
    {
        return $this->hasMany(DctfwebEvidenceVersion::class, 'declaration_id');
    }

    public function darfDocuments(): HasMany
    {
        return $this->hasMany(DctfwebDarfDocument::class, 'declaration_id');
    }

    /**
     * @return array<string, mixed>
     */
    public function toPublicArray(): array
    {
        return [
            'id' => $this->id,
            'office_id' => $this->office_id,
            'client_id' => $this->client_id,
            'competence_id' => $this->competence_id,
            'period_key' => $this->period_key,
            'declaration_type' => $this->declaration_type,
            'transmission_status' => $this->transmission_status?->value,
            'situation' => $this->situation?->value,
            'coverage' => $this->coverage?->value,
            'receipt_number' => $this->receipt_number,
            'transmitted_at' => $this->transmitted_at?->toIso8601String(),
            'official_at' => $this->official_at?->toIso8601String(),
            'evidence_version' => $this->evidence_version,
            'payment_status' => $this->payment_status?->value,
            'current_snapshot_id' => $this->current_snapshot_id,
        ];
    }
}
