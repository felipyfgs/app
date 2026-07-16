<?php

namespace App\Models;

use App\Enums\OfficeRole;
use Database\Factories\OfficeMembershipFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

#[Fillable(['office_id', 'user_id', 'role', 'is_active', 'work_department_id'])]
class OfficeMembership extends Pivot
{
    /** @use HasFactory<OfficeMembershipFactory> */
    use HasFactory;

    public $incrementing = true;

    protected $table = 'office_user';

    protected function casts(): array
    {
        return [
            'role' => OfficeRole::class,
            'is_active' => 'boolean',
        ];
    }

    protected static function newFactory(): OfficeMembershipFactory
    {
        return OfficeMembershipFactory::new();
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Departamento primário operacional (opcional, mesmo escritório). */
    public function workDepartment(): BelongsTo
    {
        return $this->belongsTo(WorkDepartment::class, 'work_department_id');
    }
}
