<?php

namespace Database\Seeders;

use App\Enums\OfficeRole;
use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionStatus;
use App\Models\Office;
use App\Models\OfficeSubscription;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use LogicException;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public const ACCOUNTANT_OFFICE_SLUG = 'demo';

    public const ACCOUNTANT_OFFICE_NAME = 'Contador Genérico';

    /** @var list<string> */
    private const LEGACY_SENTINEL_SLUGS = [
        'demo-sentinel',
        'demo-work-sentinel',
    ];

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new LogicException('O seeder de demonstração só pode ser executado em local/testing.');
        }

        $this->retireLegacySentinelOffices();

        $platformOffice = Office::query()->updateOrCreate(
            ['slug' => PlatformAdminDemoSeeder::OFFICE_SLUG],
            ['name' => PlatformAdminDemoSeeder::OFFICE_NAME, 'is_active' => true],
        );
        $office = Office::query()->updateOrCreate(
            ['slug' => self::ACCOUNTANT_OFFICE_SLUG],
            ['name' => self::ACCOUNTANT_OFFICE_NAME, 'is_active' => true],
        );

        $this->ensureActiveSubscription($platformOffice);
        $this->ensureActiveSubscription($office);

        // Operador: acessa o painel sem exigir TOTP
        if (! User::query()->where('email', 'operador@example.com')->exists()) {
            User::factory()
                ->forOffice($office, OfficeRole::Operator)
                ->create([
                    'name' => 'Operador Demo',
                    'email' => 'operador@example.com',
                    'password' => 'password',
                    'selected_office_id' => $office->id,
                ]);
        }

        // Admin com 2FA já confirmado (segredo de teste — só local)
        if (! User::query()->where('email', 'admin@example.com')->exists()) {
            User::factory()
                ->forOffice($office, OfficeRole::Admin)
                ->withTwoFactorConfirmed()
                ->create([
                    'name' => self::ACCOUNTANT_OFFICE_NAME,
                    'email' => 'admin@example.com',
                    'password' => 'password',
                    'selected_office_id' => $office->id,
                ]);
        }

        // Viewer só leitura
        if (! User::query()->where('email', 'viewer@example.com')->exists()) {
            User::factory()
                ->forOffice($office, OfficeRole::Viewer)
                ->create([
                    'name' => 'Viewer Demo',
                    'email' => 'viewer@example.com',
                    'password' => 'password',
                    'selected_office_id' => $office->id,
                ]);
        }

        // Garante tenant ativo nos usuários demo já existentes
        User::query()
            ->whereIn('email', [
                'operador@example.com',
                'admin@example.com',
                'viewer@example.com',
            ])
            ->whereNull('selected_office_id')
            ->update(['selected_office_id' => $office->id]);

        // PLATFORM_ADMIN exclusivamente global (sem OfficeMembership / seats)
        $this->call(PlatformAdminDemoSeeder::class);

        // Catálogo rico: clientes, sync, notas, exportações (só local/testing)
        $this->call(DemoCatalogSeeder::class);

        // Fixtures fiscais do hub de monitoramento (office demo only; guard interno)
        $this->call(FiscalMonitoringDemoSeeder::class);

        // Massa operacional /work no office demo (guard interno; âncora DEMO_WORK_ANCHOR_DATE)
        $this->call(OperationalWorkDemoSeeder::class);
    }

    private function retireLegacySentinelOffices(): void
    {
        // Fixtures antigas criavam dois Offices técnicos visíveis no seletor.
        // Mantemos os dados para diagnóstico local, mas eles deixam de ser selecionáveis.
        Office::query()
            ->whereIn('slug', self::LEGACY_SENTINEL_SLUGS)
            ->update(['is_active' => false]);
    }

    private function ensureActiveSubscription(Office $office): void
    {
        if (OfficeSubscription::query()->where('office_id', $office->id)->exists()) {
            return;
        }

        $plan = SubscriptionPlan::Professional;
        $limits = $plan->defaultLimits();
        $commercial = $plan->commercialEntitlements();
        $now = now();

        OfficeSubscription::query()->create([
            'office_id' => $office->id,
            'plan' => $plan,
            'status' => SubscriptionStatus::Active,
            'starts_at' => $now,
            'current_period_starts_at' => $now->copy()->startOfMonth(),
            'current_period_ends_at' => $now->copy()->endOfMonth(),
            'monthly_api_quota' => $limits['monthly_api_quota'],
            'commercial_monitor_units' => $commercial['commercial_monitor_units'],
            'max_clients' => $limits['max_clients'],
            'negotiated_client_limit' => null,
            'max_users' => $limits['max_users'],
            'limits' => array_merge($limits, $commercial),
            'notes' => 'Assinatura ACTIVE criada no DatabaseSeeder (local/testing).',
        ]);
    }
}
