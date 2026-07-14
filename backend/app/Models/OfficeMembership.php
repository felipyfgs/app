<?php

namespace App\Models;

use App\Enums\OfficeRole;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

#[Fillable(['office_id', 'user_id', 'role', 'is_active'])]
class OfficeMembership extends Pivot
{
    public $incrementing = true;

    protected $table = 'office_user';

    protected function casts(): array
    {
        return [
            'role' => OfficeRole::class,
            'is_active' => 'boolean',
        ];
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
