<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOffice;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'office_id',
    'title',
    'shortcut',
    'body_encrypted',
    'is_active',
    'created_by_membership_id',
])]
class CommunicationCannedResponse extends Model
{
    use BelongsToOffice;

    protected function casts(): array
    {
        return [
            'body_encrypted' => 'encrypted',
            'is_active' => 'boolean',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(OfficeMembership::class, 'created_by_membership_id');
    }
}
