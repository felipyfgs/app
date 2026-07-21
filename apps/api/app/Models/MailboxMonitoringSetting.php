<?php

namespace App\Models;

use App\Enums\MailboxMonitoringMode;
use App\Models\Concerns\BelongsToOffice;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'office_id',
    'enabled',
    'mode',
    'daily_time',
    'timezone',
    'reconciliation_days',
    'auto_detail_limit',
    'monthly_budget_micros',
    'last_dispatched_at',
    'next_due_at',
])]
class MailboxMonitoringSetting extends Model
{
    use BelongsToOffice;

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'mode' => MailboxMonitoringMode::class,
            'reconciliation_days' => 'integer',
            'auto_detail_limit' => 'integer',
            'monthly_budget_micros' => 'integer',
            'last_dispatched_at' => 'immutable_datetime',
            'next_due_at' => 'immutable_datetime',
        ];
    }
}
