<?php

namespace App\Models;

use Database\Factories\OfficeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['name', 'slug', 'is_active', 'serpro_segregation_class', 'deadline_timezone', 'timezone'])]
class Office extends Model
{
    /** @use HasFactory<OfficeFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->using(OfficeMembership::class)
            ->withPivot(['role', 'is_active', 'work_department_id'])
            ->withTimestamps();
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(OfficeMembership::class);
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(OfficeSubscription::class);
    }
}
