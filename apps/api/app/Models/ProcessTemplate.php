<?php

namespace App\Models;

use App\Enums\Work\DueRuleType;
use App\Models\Concerns\BelongsToOffice;
use Database\Factories\ProcessTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'office_id',
    'catalog_key',
    'catalog_version',
    'name',
    'description',
    'monitoring_module_key',
    'audience_rules',
    'default_department_id',
    'default_due_rule_type',
    'default_due_rule_value',
    'is_active',
    'lock_version',
    'created_by_membership_id',
])]
class ProcessTemplate extends Model
{
    /** @use HasFactory<ProcessTemplateFactory> */
    use BelongsToOffice, HasFactory;

    protected function casts(): array
    {
        return [
            'catalog_version' => 'integer',
            'audience_rules' => 'array',
            'default_due_rule_type' => DueRuleType::class,
            'default_due_rule_value' => 'integer',
            'is_active' => 'boolean',
            'lock_version' => 'integer',
        ];
    }

    public function defaultDepartment(): BelongsTo
    {
        return $this->belongsTo(WorkDepartment::class, 'default_department_id');
    }

    public function createdByMembership(): BelongsTo
    {
        return $this->belongsTo(OfficeMembership::class, 'created_by_membership_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(ProcessTemplateTask::class)->orderBy('sort_order');
    }

    public function processes(): HasMany
    {
        return $this->hasMany(OperationalProcess::class);
    }

    protected static function newFactory(): ProcessTemplateFactory
    {
        return ProcessTemplateFactory::new();
    }
}
