<?php

namespace Database\Factories;

use App\Enums\OfficeRole;
use App\Models\Office;
use App\Models\OfficeMembership;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OfficeMembership>
 */
class OfficeMembershipFactory extends Factory
{
    protected $model = OfficeMembership::class;

    public function definition(): array
    {
        return [
            'office_id' => Office::factory(),
            'user_id' => User::factory(),
            'role' => OfficeRole::Operator,
            'is_active' => true,
            'work_department_id' => null,
        ];
    }

    public function admin(): static
    {
        return $this->state(fn () => ['role' => OfficeRole::Admin]);
    }

    public function viewer(): static
    {
        return $this->state(fn () => ['role' => OfficeRole::Viewer]);
    }
}
