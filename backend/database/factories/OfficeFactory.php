<?php

namespace Database\Factories;

use App\Models\Office;
use App\Models\OfficeSubscription;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Office>
 */
class OfficeFactory extends Factory
{
    protected $model = Office::class;

    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'is_active' => true,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Office $office): void {
            if (! OfficeSubscription::query()->where('office_id', $office->id)->exists()) {
                OfficeSubscription::factory()->forOffice($office)->active()->create();
            }
        });
    }

    public function withoutSubscription(): static
    {
        return $this->state(fn () => [])->afterCreating(function (): void {
            // no-op marker; callers that need bare office should delete subscription or use withoutEvents
        });
    }
}

