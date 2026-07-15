<?php

namespace App\Console\Commands;

use App\Enums\OfficeRole;
use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionStatus;
use App\Models\Office;
use App\Models\OfficeSubscription;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class BootstrapOfficeCommand extends Command
{
    protected $signature = 'app:bootstrap-office
        {--name= : Nome do escritório}
        {--slug= : Slug do escritório}
        {--admin-name= : Nome do administrador}
        {--admin-email= : E-mail do administrador}';

    protected $description = 'Cria o primeiro escritório e administrador (seguro; falha se já existir escritório)';

    public function handle(): int
    {
        if (Office::query()->exists()) {
            $this->error('Já existe ao menos um escritório. Bootstrap recusado.');

            return self::FAILURE;
        }

        $name = $this->option('name') ?: $this->ask('Nome do escritório');
        $slug = $this->option('slug') ?: Str::slug((string) $name);
        $adminName = $this->option('admin-name') ?: $this->ask('Nome do administrador');
        $adminEmail = $this->option('admin-email') ?: $this->ask('E-mail do administrador');
        // Nunca aceite senha por argumento: argv pode aparecer no histórico e na lista de processos.
        $adminPassword = $this->secret('Senha do administrador');

        $validator = Validator::make([
            'name' => $name,
            'slug' => $slug,
            'admin_name' => $adminName,
            'admin_email' => $adminEmail,
            'admin_password' => $adminPassword,
        ], [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'alpha_dash'],
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'admin_password' => ['required', 'string', 'min:12'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        DB::transaction(function () use ($name, $slug, $adminName, $adminEmail, $adminPassword): void {
            $office = Office::query()->create([
                'name' => $name,
                'slug' => $slug,
                'is_active' => true,
            ]);

            $user = User::query()->create([
                'name' => $adminName,
                'email' => Str::lower($adminEmail),
                'password' => Hash::make($adminPassword),
                'is_active' => true,
                'selected_office_id' => $office->id,
            ]);

            $office->users()->attach($user->id, [
                'role' => OfficeRole::Admin->value,
                'is_active' => true,
            ]);

            // Assinatura ACTIVE para o tenant (sem isso, mutações HTTP retornam 403 MISSING).
            $plan = SubscriptionPlan::Professional;
            $limits = $plan->defaultLimits();
            $now = now();
            OfficeSubscription::query()->create([
                'office_id' => $office->id,
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
                'notes' => 'Assinatura ACTIVE criada no bootstrap inicial.',
            ]);
        });

        $this->info('Escritório, administrador e assinatura ACTIVE criados.');
        $this->warn('Configure e confirme o TOTP antes de usar funções administrativas.');

        return self::SUCCESS;
    }
}
