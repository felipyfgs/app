<?php

namespace App\Models;

use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionStatus;
use Database\Factories\OfficeSubscriptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Assinatura/plano comercial do escritório (tenant).
 * COM office_id obrigatório.
 */
#[Fillable([
    'office_id',
    'plan',
    'status',
    'trial_ends_at',
    'starts_at',
    'ends_at',
    'current_period_starts_at',
    'current_period_ends_at',
    'monthly_api_quota',
    'max_clients',
    'max_users',
    'limits',
    'notes',
])]
class OfficeSubscription extends Model
{
    /** @use HasFactory<OfficeSubscriptionFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'plan' => SubscriptionPlan::class,
            'status' => SubscriptionStatus::class,
            'trial_ends_at' => 'datetime',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'current_period_starts_at' => 'datetime',
            'current_period_ends_at' => 'datetime',
            'monthly_api_quota' => 'integer',
            'max_clients' => 'integer',
            'max_users' => 'integer',
            'limits' => 'array',
        ];
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }

    public function allowsMutations(): bool
    {
        return $this->status->allowsMutations();
    }

    public function allowsExternalCalls(): bool
    {
        return $this->status->allowsExternalCalls();
    }

    public function allowsRead(): bool
    {
        return $this->status->allowsRead();
    }

    /**
     * Payload sanitizado para API tenant-scoped (sem dados fiscais).
     *
     * @return array<string, mixed>
     */
    public function toPublicArray(): array
    {
        return [
            'id' => $this->id,
            'office_id' => $this->office_id,
            'plan' => $this->plan->value,
            'status' => $this->status->value,
            'trial_ends_at' => $this->trial_ends_at?->toIso8601String(),
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'current_period_starts_at' => $this->current_period_starts_at?->toIso8601String(),
            'current_period_ends_at' => $this->current_period_ends_at?->toIso8601String(),
            'limits' => [
                'monthly_api_quota' => $this->monthly_api_quota,
                'max_clients' => $this->max_clients,
                'max_users' => $this->max_users,
            ],
            'allows_mutations' => $this->allowsMutations(),
            'allows_external_calls' => $this->allowsExternalCalls(),
        ];
    }

    /**
     * Payload sanitizado para PLATFORM_ADMIN (sem conteúdo fiscal).
     *
     * @return array<string, mixed>
     */
    public function toSanitizedAdminArray(): array
    {
        return array_merge($this->toPublicArray(), [
            'notes' => $this->notes,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ]);
    }
}
