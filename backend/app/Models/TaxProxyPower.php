<?php

namespace App\Models;

use App\Enums\TaxProxyPowerSource;
use App\Enums\TaxProxyPowerStatus;
use App\Models\Concerns\BelongsToOffice;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'office_id',
    'client_id',
    'office_serpro_authorization_id',
    'author_identity',
    'contributor_cnpj',
    'system_code',
    'service_code',
    'power_code',
    'source',
    'status',
    'valid_from',
    'valid_to',
    'evidence_ref',
    'evidence_sha256',
    'verified_at',
    'last_check_result',
    'metadata',
])]
class TaxProxyPower extends Model
{
    use BelongsToOffice;

    protected function casts(): array
    {
        return [
            'source' => TaxProxyPowerSource::class,
            'status' => TaxProxyPowerStatus::class,
            'valid_from' => 'immutable_datetime',
            'valid_to' => 'immutable_datetime',
            'verified_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function authorization(): BelongsTo
    {
        return $this->belongsTo(OfficeSerproAuthorization::class, 'office_serpro_authorization_id');
    }

    public function isCurrentlyValid(): bool
    {
        if (! $this->status->isUsable()) {
            return false;
        }

        if ($this->valid_to !== null && $this->valid_to->isPast()) {
            return false;
        }

        if ($this->valid_from !== null && $this->valid_from->isFuture()) {
            return false;
        }

        return true;
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
            'author_identity_masked' => $this->mask($this->author_identity),
            'contributor_cnpj_masked' => $this->mask($this->contributor_cnpj),
            'system_code' => $this->system_code,
            'service_code' => $this->service_code,
            'power_code' => $this->power_code,
            'source' => $this->source->value,
            'status' => $this->status->value,
            'valid_from' => $this->valid_from?->toIso8601String(),
            'valid_to' => $this->valid_to?->toIso8601String(),
            'evidence_ref' => $this->evidence_ref,
            'evidence_sha256' => $this->evidence_sha256,
            'verified_at' => $this->verified_at?->toIso8601String(),
            'last_check_result' => $this->last_check_result,
            'is_currently_valid' => $this->isCurrentlyValid(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    private function mask(string $value): string
    {
        $value = strtoupper(preg_replace('/[^0-9A-Za-z]/', '', $value) ?? $value);
        $len = strlen($value);
        if ($len <= 4) {
            return str_repeat('*', $len);
        }

        return substr($value, 0, 2).str_repeat('*', max(0, $len - 6)).substr($value, -4);
    }
}
