<?php

namespace App\Models;

use App\Enums\SerproEnvironment;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'subject_type',
    'subject_id',
    'action',
    'environment',
    'office_id',
    'status',
    'reason',
    'requested_by_user_id',
    'first_approver_user_id',
    'second_approver_user_id',
    'first_approved_at',
    'second_approved_at',
    'executed_at',
    'expires_at',
    'context',
])]
class SerproRolloutApproval extends Model
{
    protected function casts(): array
    {
        return [
            'environment' => SerproEnvironment::class,
            'first_approved_at' => 'immutable_datetime',
            'second_approved_at' => 'immutable_datetime',
            'executed_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'context' => 'array',
        ];
    }

    public function isFullyApproved(): bool
    {
        return $this->first_approver_user_id !== null
            && $this->second_approver_user_id !== null
            && $this->first_approver_user_id !== $this->second_approver_user_id
            && $this->status === 'APPROVED';
    }
}
