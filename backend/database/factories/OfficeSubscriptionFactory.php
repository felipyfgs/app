<?php

namespace Database\Factories;

use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionStatus;
use App\Models\Office;
use App\Models\OfficeSubscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OfficeSubscription>
 */
class OfficeSubscriptionFactory extends Factory
{
    protected $model = OfficeSubscription::class;

    public function definition(): array
    {
        $plan = SubscriptionPlan::Professional;
        $limits = $plan->defaultLimits();
        $now = now();

        return [
            'office_id' => function () {
                $prev = OfficeFactory::$autoSubscription;
                OfficeFactory::$autoSubscription = false;
                try {
                    return Office::factory()->create()->id;
                } finally {
                    OfficeFactory::$autoSubscription = $prev;
                }
            },
            'plan' => $plan,
            'status' => SubscriptionStatus::Active,
            'trial_ends_at' => null,
            'starts_at' => $now,
            'ends_at' => null,
            'current_period_starts_at' => $now->copy()->startOfMonth(),
            'current_period_ends_at' => $now->copy()->endOfMonth(),
            'monthly_api_quota' => $limits['monthly_api_quota'],
            'max_clients' => $limits['max_clients'],
            'max_users' => $limits['max_users'],
            'limits' => $limits,
            'notes' => null,
        ];
    }

    public function forOffice(Office $office): static
    {
        return $this->state(fn () => ['office_id' => $office->id]);
    }

    public function plan(SubscriptionPlan $plan): static
    {
        $limits = $plan->defaultLimits();

        return $this->state(fn () => [
            'plan' => $plan,
            'monthly_api_quota' => $limits['monthly_api_quota'],
            'max_clients' => $limits['max_clients'],
            'max_users' => $limits['max_users'],
            'limits' => $limits,
        ]);
    }

    public function trial(): static
    {
        $limits = SubscriptionPlan::Starter->defaultLimits();

        return $this->state(fn () => [
            'status' => SubscriptionStatus::Trial,
            'trial_ends_at' => now()->addDays(14),
            'plan' => SubscriptionPlan::Starter,
            'monthly_api_quota' => $limits['monthly_api_quota'],
            'max_clients' => $limits['max_clients'],
            'max_users' => $limits['max_users'],
            'limits' => $limits,
        ]);
    }

    public function active(): static
    {
        return $this->state(fn () => ['status' => SubscriptionStatus::Active]);
    }

    public function pastDue(): static
    {
        return $this->state(fn () => ['status' => SubscriptionStatus::PastDue]);
    }

    public function suspended(): static
    {
        return $this->state(fn () => ['status' => SubscriptionStatus::Suspended]);
    }

    public function canceled(): static
    {
        return $this->state(fn () => [
            'status' => SubscriptionStatus::Canceled,
            'ends_at' => now(),
        ]);
    }
}
