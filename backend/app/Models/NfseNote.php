<?php

namespace App\Models;

use App\Enums\FiscalRole;
use App\Models\Concerns\BelongsToOffice;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'office_id', 'dfe_document_id', 'access_key', 'number',
    'issuer_cnpj', 'issuer_name', 'taker_cnpj', 'taker_name',
    'intermediary_cnpj', 'intermediary_name', 'fiscal_role',
    'competence', 'issued_at', 'service_amount',
    'issue_location', 'service_location', 'status', 'official_status_code',
])]
class NfseNote extends Model
{
    use BelongsToOffice;

    protected function casts(): array
    {
        return [
            'fiscal_role' => FiscalRole::class,
            'issued_at' => 'immutable_datetime',
            'service_amount' => 'decimal:2',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(DfeDocument::class, 'dfe_document_id');
    }
}
