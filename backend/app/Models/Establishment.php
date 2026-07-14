<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOffice;
use Database\Factories\EstablishmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['office_id', 'client_id', 'cnpj', 'trade_name', 'is_matrix', 'is_active'])]
class Establishment extends Model
{
    /** @use HasFactory<EstablishmentFactory> */
    use BelongsToOffice, HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'is_matrix' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
