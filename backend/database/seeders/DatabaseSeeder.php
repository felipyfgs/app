<?php

namespace Database\Seeders;

use App\Enums\OfficeRole;
use App\Models\Office;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use LogicException;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new LogicException('O seeder de demonstração só pode ser executado em local/testing.');
        }

        $office = Office::query()->firstOrCreate(
            ['slug' => 'demo'],
            ['name' => 'Escritório Demo', 'is_active' => true],
        );

        // Operador: acessa o painel sem exigir TOTP
        if (! User::query()->where('email', 'operador@example.com')->exists()) {
            User::factory()
                ->forOffice($office, OfficeRole::Operator)
                ->create([
                    'name' => 'Operador Demo',
                    'email' => 'operador@example.com',
                    'password' => 'password',
                ]);
        }

        // Admin com 2FA já confirmado (segredo de teste — só local)
        if (! User::query()->where('email', 'admin@example.com')->exists()) {
            User::factory()
                ->forOffice($office, OfficeRole::Admin)
                ->withTwoFactorConfirmed()
                ->create([
                    'name' => 'Admin Demo',
                    'email' => 'admin@example.com',
                    'password' => 'password',
                ]);
        }
    }
}
