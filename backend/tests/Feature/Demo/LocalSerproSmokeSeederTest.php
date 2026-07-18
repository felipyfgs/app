<?php

namespace Tests\Feature\Demo;

use App\Enums\TaxRegimeCode;
use App\Models\Client;
use App\Models\Establishment;
use App\Models\Office;
use App\Models\OfficeMembership;
use App\Models\User;
use Database\Seeders\LocalSerproSmokeSeeder;
use Database\Seeders\PilotSeeder;
use Database\Seeders\PlatformAdminDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\InteractsWithSpaAuth;
use Tests\TestCase;

class LocalSerproSmokeSeederTest extends TestCase
{
    use InteractsWithSpaAuth;
    use RefreshDatabase;

    public function test_pilot_seeder_loads_dados_client_and_pilot_users(): void
    {
        $this->seed(PilotSeeder::class);

        $contador = Office::query()->where('slug', LocalSerproSmokeSeeder::CONTADOR_OFFICE_SLUG)->firstOrFail();
        $this->assertSame(LocalSerproSmokeSeeder::CONTADOR_OFFICE_NAME, $contador->name);

        $plataforma = Office::query()->where('slug', PlatformAdminDemoSeeder::OFFICE_SLUG)->firstOrFail();
        $this->assertSame(LocalSerproSmokeSeeder::PLATAFORMA_LEGAL_NAME, $plataforma->name);

        // Carteira do contador: só AUTO CENTER (dados/contador/cliene).
        $client = Client::query()
            ->where('office_id', $contador->id)
            ->where('root_cnpj', substr(LocalSerproSmokeSeeder::AUTO_CENTER_CNPJ, 0, 8))
            ->firstOrFail();

        $this->assertSame(LocalSerproSmokeSeeder::AUTO_CENTER_LEGAL_NAME, $client->legal_name);
        $this->assertSame(TaxRegimeCode::SimplesNacional->value, $client->tax_regime);

        $est = Establishment::query()
            ->where('client_id', $client->id)
            ->where('cnpj', LocalSerproSmokeSeeder::AUTO_CENTER_CNPJ)
            ->firstOrFail();

        $this->assertTrue($est->is_matrix);
        $this->assertSame(LocalSerproSmokeSeeder::AUTO_CENTER_TRADE_NAME, $est->trade_name);

        // Felipe NÃO é cliente do contador — é office plataforma.
        $this->assertSame(
            0,
            Client::query()
                ->where('office_id', $contador->id)
                ->where('root_cnpj', substr(LocalSerproSmokeSeeder::PLATAFORMA_CNPJ, 0, 8))
                ->count(),
        );

        // Felipe: tenant no office plataforma + PLATFORM_ADMIN (aba Admin/SERPRO).
        $felipe = User::query()->where('email', LocalSerproSmokeSeeder::FELIPE_EMAIL)->firstOrFail();
        $this->assertTrue($felipe->is_active);
        $this->assertSame(LocalSerproSmokeSeeder::FELIPE_NAME, $felipe->name);
        $this->assertSame($plataforma->id, $felipe->selected_office_id);
        $this->assertTrue(Hash::check(LocalSerproSmokeSeeder::PILOT_PASSWORD, $felipe->password));
        $this->assertSame(
            1,
            OfficeMembership::query()
                ->where('user_id', $felipe->id)
                ->where('office_id', $plataforma->id)
                ->where('is_active', true)
                ->count(),
        );
        $this->assertSame(1, OfficeMembership::query()->where('user_id', $felipe->id)->count());
        $this->assertTrue($felipe->isPlatformAdmin());
        $felipePm = $felipe->platformMemberships()->where('role', 'PLATFORM_ADMIN')->firstOrFail();
        $this->assertSame($plataforma->id, $felipePm->default_office_id);

        // Gustavo: só tenant do contador (sem aba Admin).
        $gustavo = User::query()->where('email', LocalSerproSmokeSeeder::GUSTAVO_EMAIL)->firstOrFail();
        $this->assertTrue($gustavo->is_active);
        $this->assertSame(LocalSerproSmokeSeeder::GUSTAVO_NAME, $gustavo->name);
        $this->assertSame($contador->id, $gustavo->selected_office_id);
        $this->assertTrue(Hash::check(LocalSerproSmokeSeeder::PILOT_PASSWORD, $gustavo->password));
        $this->assertSame(
            1,
            OfficeMembership::query()
                ->where('user_id', $gustavo->id)
                ->where('office_id', $contador->id)
                ->where('is_active', true)
                ->count(),
        );
        $this->assertSame(1, OfficeMembership::query()->where('user_id', $gustavo->id)->count());
        $this->assertFalse($gustavo->isPlatformAdmin());

        // Fixture técnica fica inativa (titular transferido para Felipe).
        $legacyAdmin = User::query()->where('email', PlatformAdminDemoSeeder::EMAIL)->first();
        if ($legacyAdmin !== null) {
            $this->assertFalse($legacyAdmin->is_active);
            $this->assertFalse($legacyAdmin->isPlatformAdmin());
        }

        $perfilPlataforma = $plataforma->institutionalProfile;
        $this->assertNotNull($perfilPlataforma);
        $this->assertSame(LocalSerproSmokeSeeder::PLATAFORMA_CNPJ, $perfilPlataforma->cnpj);
        $this->assertSame(LocalSerproSmokeSeeder::PLATAFORMA_LEGAL_NAME, $perfilPlataforma->legal_name);
        $this->assertSame(LocalSerproSmokeSeeder::PLATAFORMA_EMAIL, $perfilPlataforma->institutional_email);

        $perfilContador = $contador->institutionalProfile;
        $this->assertNotNull($perfilContador);
        $this->assertSame(LocalSerproSmokeSeeder::CONTADOR_CNPJ, $perfilContador->cnpj);
        $this->assertSame(LocalSerproSmokeSeeder::CONTADOR_OFFICE_NAME, $perfilContador->legal_name);
    }

    public function test_smoke_seeder_is_idempotent(): void
    {
        $this->seed(PilotSeeder::class);
        $this->seed(LocalSerproSmokeSeeder::class);
        $this->seed(LocalSerproSmokeSeeder::class);

        $this->assertSame(
            1,
            Client::query()
                ->where('root_cnpj', substr(LocalSerproSmokeSeeder::AUTO_CENTER_CNPJ, 0, 8))
                ->where('notes', 'like', '%'.LocalSerproSmokeSeeder::MARKER.'%')
                ->count(),
        );
        $this->assertSame(
            1,
            Establishment::query()
                ->where('cnpj', LocalSerproSmokeSeeder::AUTO_CENTER_CNPJ)
                ->count(),
        );
        $this->assertSame(1, User::query()->where('email', LocalSerproSmokeSeeder::FELIPE_EMAIL)->count());
        $this->assertSame(1, User::query()->where('email', LocalSerproSmokeSeeder::GUSTAVO_EMAIL)->count());
    }

    public function test_felipe_and_gustavo_can_login(): void
    {
        $this->seed(PilotSeeder::class);

        $felipe = User::query()->where('email', LocalSerproSmokeSeeder::FELIPE_EMAIL)->firstOrFail();
        $gustavo = User::query()->where('email', LocalSerproSmokeSeeder::GUSTAVO_EMAIL)->firstOrFail();

        $this->asSpa()->postJson('/login', [
            'email' => LocalSerproSmokeSeeder::FELIPE_EMAIL,
            'password' => LocalSerproSmokeSeeder::PILOT_PASSWORD,
        ])->assertOk();
        $this->assertAuthenticatedAs($felipe);
        $this->asSpa()->postJson('/logout')->assertNoContent();

        $this->asSpa()->postJson('/login', [
            'email' => LocalSerproSmokeSeeder::GUSTAVO_EMAIL,
            'password' => LocalSerproSmokeSeeder::PILOT_PASSWORD,
        ])->assertOk();
        $this->assertAuthenticatedAs($gustavo);
    }
}
