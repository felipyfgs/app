<?php

namespace App\Services\Platform;

use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionStatus;
use App\Models\Office;
use App\Models\OfficeSubscription;
use App\Services\Audit\AuditLogger;
use InvalidArgumentException;
use RuntimeException;

/**
 * Ciclo de vida comercial do tenant: TRIAL → ACTIVE → PAST_DUE → SUSPENDED → CANCELED.
 * Não apaga ledger, auditoria, snapshots nem evidências fiscais.
 */
final class OfficeSubscriptionService
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Garante assinatura corrente do office (idempotente).
     */
    public function ensureForOffice(
        Office $office,
        SubscriptionPlan $plan = SubscriptionPlan::Professional,
        SubscriptionStatus $status = SubscriptionStatus::Active,
    ): OfficeSubscription {
        $existing = OfficeSubscription::query()->where('office_id', $office->id)->first();
        if ($existing !== null) {
            return $existing;
        }

        return $this->create($office, $plan, $status);
    }

    public function create(
        Office $office,
        SubscriptionPlan $plan = SubscriptionPlan::Professional,
        SubscriptionStatus $status = SubscriptionStatus::Active,
        ?int $trialDays = null,
    ): OfficeSubscription {
        if (OfficeSubscription::query()->where('office_id', $office->id)->exists()) {
            throw new RuntimeException('Escritório já possui assinatura.');
        }

        $limits = $plan->defaultLimits();
        $now = now();

        $subscription = OfficeSubscription::query()->create([
            'office_id' => $office->id,
            'plan' => $plan,
            'status' => $status,
            'trial_ends_at' => $status === SubscriptionStatus::Trial
                ? $now->copy()->addDays($trialDays ?? 14)
                : null,
            'starts_at' => $now,
            'ends_at' => null,
            'current_period_starts_at' => $now->copy()->startOfMonth(),
            'current_period_ends_at' => $now->copy()->endOfMonth(),
            'monthly_api_quota' => $limits['monthly_api_quota'],
            'max_clients' => $limits['max_clients'],
            'max_users' => $limits['max_users'],
            'limits' => $limits,
        ]);

        $this->audit->record(
            action: 'office_subscription.created',
            result: 'SUCCESS',
            subject: $subscription,
            context: [
                'plan' => $plan->value,
                'status' => $status->value,
            ],
            officeId: $office->id,
        );

        return $subscription;
    }

    public function activate(OfficeSubscription $subscription): OfficeSubscription
    {
        return $this->transition($subscription, SubscriptionStatus::Active, [
            'starts_at' => $subscription->starts_at ?? now(),
            'ends_at' => null,
            'trial_ends_at' => $subscription->trial_ends_at,
        ]);
    }

    public function markPastDue(OfficeSubscription $subscription): OfficeSubscription
    {
        return $this->transition($subscription, SubscriptionStatus::PastDue);
    }

    public function suspend(OfficeSubscription $subscription, ?string $reason = null): OfficeSubscription
    {
        return $this->transition($subscription, SubscriptionStatus::Suspended, notes: $reason);
    }

    public function cancel(OfficeSubscription $subscription, ?string $reason = null): OfficeSubscription
    {
        return $this->transition($subscription, SubscriptionStatus::Canceled, [
            'ends_at' => now(),
        ], $reason);
    }

    public function resume(OfficeSubscription $subscription): OfficeSubscription
    {
        if ($subscription->status === SubscriptionStatus::Canceled) {
            throw new InvalidArgumentException('Assinatura cancelada não pode ser reativada; crie nova se necessário.');
        }

        if (! in_array($subscription->status, [SubscriptionStatus::Suspended, SubscriptionStatus::PastDue], true)) {
            throw new InvalidArgumentException('Somente PAST_DUE ou SUSPENDED podem retomar para ACTIVE.');
        }

        return $this->transition($subscription, SubscriptionStatus::Active, [
            'ends_at' => null,
        ]);
    }

    public function changePlan(OfficeSubscription $subscription, SubscriptionPlan $plan): OfficeSubscription
    {
        if ($subscription->status === SubscriptionStatus::Canceled) {
            throw new InvalidArgumentException('Não é possível alterar plano de assinatura cancelada.');
        }

        $limits = $plan->defaultLimits();
        $from = $subscription->plan->value;

        $subscription->fill([
            'plan' => $plan,
            'monthly_api_quota' => $limits['monthly_api_quota'],
            'max_clients' => $limits['max_clients'],
            'max_users' => $limits['max_users'],
            'limits' => array_merge($subscription->limits ?? [], $limits),
        ]);
        $subscription->save();

        $this->audit->record(
            action: 'office_subscription.plan_changed',
            result: 'SUCCESS',
            subject: $subscription,
            context: [
                'from_plan' => $from,
                'to_plan' => $plan->value,
                'status' => $subscription->status->value,
            ],
            officeId: $subscription->office_id,
        );

        return $subscription->refresh();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function transition(
        OfficeSubscription $subscription,
        SubscriptionStatus $to,
        array $attributes = [],
        ?string $notes = null,
    ): OfficeSubscription {
        $from = $subscription->status;

        if ($from === $to) {
            return $subscription;
        }

        $this->assertTransitionAllowed($from, $to);

        $subscription->fill(array_merge($attributes, [
            'status' => $to,
        ]));

        if ($notes !== null && $notes !== '') {
            $subscription->notes = trim(($subscription->notes ? $subscription->notes."\n" : '').$notes);
        }

        $subscription->save();

        $this->audit->record(
            action: 'office_subscription.status_changed',
            result: 'SUCCESS',
            subject: $subscription,
            context: [
                'from_status' => $from->value,
                'to_status' => $to->value,
            ],
            officeId: $subscription->office_id,
        );

        return $subscription->refresh();
    }

    private function assertTransitionAllowed(SubscriptionStatus $from, SubscriptionStatus $to): void
    {
        // Matriz mínima do ciclo de vida comercial.
        $allowed = match ($from) {
            SubscriptionStatus::Trial => [
                SubscriptionStatus::Active,
                SubscriptionStatus::PastDue,
                SubscriptionStatus::Suspended,
                SubscriptionStatus::Canceled,
            ],
            SubscriptionStatus::Active => [
                SubscriptionStatus::PastDue,
                SubscriptionStatus::Suspended,
                SubscriptionStatus::Canceled,
            ],
            SubscriptionStatus::PastDue => [
                SubscriptionStatus::Active,
                SubscriptionStatus::Suspended,
                SubscriptionStatus::Canceled,
            ],
            SubscriptionStatus::Suspended => [
                SubscriptionStatus::Active,
                SubscriptionStatus::Canceled,
            ],
            SubscriptionStatus::Canceled => [],
        };

        if (! in_array($to, $allowed, true)) {
            throw new InvalidArgumentException(
                "Transição de assinatura inválida: {$from->value} → {$to->value}."
            );
        }
    }
}
