<?php

namespace Database\Seeders;

use App\Enums\OfficeRole;
use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionStatus;
use App\Models\Office;
use App\Models\OfficeMembership;
use App\Models\OfficeSubscription;
use App\Models\User;
use Illuminate\Database\Seeder;
use LogicException;

/** Fixtures determinísticas e exclusivamente locais para o gate de navegador. */
class FiscalMonitoringE2ESeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('testing')) {
            throw new LogicException('FiscalMonitoringE2ESeeder exige APP_ENV=testing.');
        }

        $primaryOffice = Office::query()->updateOrCreate(
            ['slug' => 'contador'],
            ['name' => 'Escritório Contábil Demo', 'is_active' => true],
        );
        $this->ensureSubscription($primaryOffice);

        $users = [];
        foreach ([
            'admin@example.com' => ['name' => 'Admin E2E', 'role' => OfficeRole::Admin],
            'operador@example.com' => ['name' => 'Operador E2E', 'role' => OfficeRole::Operator],
            'viewer@example.com' => ['name' => 'Viewer E2E', 'role' => OfficeRole::Viewer],
        ] as $email => $definition) {
            $user = User::query()->updateOrCreate(
                ['email' => $email],
                [
                    'name' => $definition['name'],
                    'password' => 'password',
                    'is_active' => true,
                    'password_change_required' => false,
                ],
            );
            $user->forceFill([
                'email_verified_at' => now(),
                'selected_office_id' => $primaryOffice->id,
            ])->saveQuietly();
            OfficeMembership::query()->updateOrCreate(
                ['office_id' => $primaryOffice->id, 'user_id' => $user->id],
                ['role' => $definition['role'], 'is_active' => true],
            );
            $users[$email] = $user;
        }

        $this->call(FiscalMonitoringDemoSeeder::class);

        $secondOffice = Office::query()->updateOrCreate(
            ['slug' => 'e2e-second-office'],
            ['name' => 'Escritório E2E Secundário', 'is_active' => true],
        );
        $this->ensureSubscription($secondOffice);

        foreach ([
            'admin@example.com' => OfficeRole::Admin,
            'operador@example.com' => OfficeRole::Operator,
            'viewer@example.com' => OfficeRole::Viewer,
        ] as $email => $role) {
            $user = $users[$email];
            OfficeMembership::query()->updateOrCreate(
                ['office_id' => $secondOffice->id, 'user_id' => $user->id],
                ['role' => $role, 'is_active' => true],
            );
        }
    }

    private function ensureSubscription(Office $office): void
    {
        OfficeSubscription::query()->updateOrCreate(
            ['office_id' => $office->id],
            [
                'plan' => SubscriptionPlan::Professional,
                'status' => SubscriptionStatus::Active,
                'starts_at' => now()->startOfDay(),
                'current_period_starts_at' => now()->startOfMonth(),
                'current_period_ends_at' => now()->endOfMonth(),
                'monthly_api_quota' => 10_000,
                'max_clients' => 150,
                'max_users' => 25,
            ],
        );
    }
}
