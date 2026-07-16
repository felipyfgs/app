<?php

namespace Tests\Feature\Demo;

use App\Enums\ActivationMethod;
use App\Enums\ActivationPurpose;
use App\Enums\OfficeRole;
use App\Enums\PlatformRole;
use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionStatus;
use App\Models\AccountActivation;
use App\Models\Office;
use App\Models\OfficeMembership;
use App\Models\OfficeSubscription;
use App\Models\PlatformMembership;
use App\Models\User;
use App\Services\Activation\OfficeTeamService;
use App\Services\Auth\RecentPasswordConfirmationGate;
use Database\Seeders\PlatformAdminDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use LogicException;
use RuntimeException;
use Tests\Concerns\InteractsWithSpaAuth;
use Tests\TestCase;

class PlatformAdminDemoSeederTest extends TestCase
{
    use InteractsWithSpaAuth;
    use RefreshDatabase;

    private function createDemoOffice(bool $active = true): Office
    {
        $office = Office::factory()->create([
            'slug' => PlatformAdminDemoSeeder::OFFICE_SLUG,
            'name' => PlatformAdminDemoSeeder::OFFICE_NAME,
            'is_active' => $active,
        ]);

        if ($active) {
            $this->ensureSubscription($office);
        }

        return $office;
    }

    private function ensureSubscription(Office $office): void
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
            'notes' => 'test subscription',
        ]);
    }

    private function seedPlatformAdmin(): void
    {
        $this->seed(PlatformAdminDemoSeeder::class);
    }

    public function test_primeira_execucao_cria_fixture_global_exata(): void
    {
        $office = $this->createDemoOffice();

        $this->seedPlatformAdmin();

        $user = User::query()->where('email', PlatformAdminDemoSeeder::EMAIL)->first();
        $this->assertNotNull($user);
        $this->assertSame(PlatformAdminDemoSeeder::NAME, $user->name);
        $this->assertSame(PlatformAdminDemoSeeder::EMAIL, $user->email);
        $this->assertTrue($user->is_active);
        $this->assertFalse($user->password_change_required);
        $this->assertNotNull($user->email_verified_at);
        $this->assertNull($user->selected_office_id);
        $this->assertTrue(Hash::check(PlatformAdminDemoSeeder::PASSWORD, $user->password));
        $this->assertTrue($user->isPlatformAdmin());

        $this->assertSame(1, User::query()->where('email', PlatformAdminDemoSeeder::EMAIL)->count());
        $this->assertSame(1, PlatformMembership::query()->where('user_id', $user->id)->count());
        $this->assertSame(0, OfficeMembership::query()->where('user_id', $user->id)->count());
        $this->assertSame(0, AccountActivation::query()->where('user_id', $user->id)->count());

        $this->assertDatabaseHas('platform_memberships', [
            'user_id' => $user->id,
            'role' => PlatformRole::PlatformAdmin->value,
            'is_active' => true,
            'default_office_id' => $office->id,
        ]);
    }

    public function test_login_e_grupo_admin_com_office_da_plataforma_padrao(): void
    {
        $office = $this->createDemoOffice();
        $this->seedPlatformAdmin();

        $user = User::query()->where('email', PlatformAdminDemoSeeder::EMAIL)->firstOrFail();

        $this->asSpa()->postJson('/login', [
            'email' => PlatformAdminDemoSeeder::EMAIL,
            'password' => PlatformAdminDemoSeeder::PASSWORD,
        ])->assertOk();

        $this->assertAuthenticatedAs($user);

        $this->asSpa()
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.is_platform_admin', true)
            ->assertJsonPath('data.email', PlatformAdminDemoSeeder::EMAIL);

        $this->asSpa()
            ->getJson('/api/v1/platform/tenants')
            ->assertOk()
            ->assertJsonPath('data.0.id', $office->id);

        $pm = PlatformMembership::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame($office->id, (int) $pm->default_office_id);
    }

    public function test_segunda_execucao_preserva_ids_e_contagens(): void
    {
        $this->createDemoOffice();
        $this->seedPlatformAdmin();

        $user = User::query()->where('email', PlatformAdminDemoSeeder::EMAIL)->firstOrFail();
        $pm = PlatformMembership::query()->where('user_id', $user->id)->firstOrFail();

        $userId = $user->id;
        $pmId = $pm->id;
        $userCount = User::query()->count();
        $pmCount = PlatformMembership::query()->count();
        $omCount = OfficeMembership::query()->count();

        $this->seedPlatformAdmin();

        $this->assertSame(1, User::query()->where('email', PlatformAdminDemoSeeder::EMAIL)->count());
        $this->assertSame(1, PlatformMembership::query()->where('user_id', $userId)->count());
        $this->assertSame($userId, User::query()->where('email', PlatformAdminDemoSeeder::EMAIL)->value('id'));
        $this->assertSame($pmId, PlatformMembership::query()->where('user_id', $userId)->value('id'));
        $this->assertSame($userCount, User::query()->count());
        $this->assertSame($pmCount, PlatformMembership::query()->count());
        $this->assertSame($omCount, OfficeMembership::query()->count());
    }

    public function test_repeticao_preserva_hash_de_senha_alterado(): void
    {
        $this->createDemoOffice();
        $this->seedPlatformAdmin();

        $user = User::query()->where('email', PlatformAdminDemoSeeder::EMAIL)->firstOrFail();
        $user->forceFill(['password' => 'senha-local-alterada'])->save();
        $hash = $user->fresh()->password;

        $this->seedPlatformAdmin();

        $fresh = User::query()->where('email', PlatformAdminDemoSeeder::EMAIL)->firstOrFail();
        $this->assertSame($hash, $fresh->password);
        $this->assertTrue(Hash::check('senha-local-alterada', $fresh->password));
        $this->assertFalse(Hash::check(PlatformAdminDemoSeeder::PASSWORD, $fresh->password));
        $this->assertTrue($fresh->isPlatformAdmin());
    }

    public function test_colisao_com_office_membership_falha_sem_escalada(): void
    {
        $office = $this->createDemoOffice();
        $existing = User::factory()->forOffice($office, OfficeRole::Operator)->create([
            'email' => PlatformAdminDemoSeeder::EMAIL,
            'name' => 'Operador Colisão',
            'password' => 'password',
        ]);
        $passwordBefore = $existing->password;
        $omCount = OfficeMembership::query()->where('user_id', $existing->id)->count();
        $pmBefore = PlatformMembership::query()->where('user_id', $existing->id)->count();

        try {
            $this->seedPlatformAdmin();
            $this->fail('Esperava RuntimeException por colisão com OfficeMembership.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('OfficeMembership', $e->getMessage());
        }

        $fresh = $existing->fresh();
        $this->assertSame($passwordBefore, $fresh->password);
        $this->assertSame('Operador Colisão', $fresh->name);
        $this->assertFalse($fresh->isPlatformAdmin());
        $this->assertSame($omCount, OfficeMembership::query()->where('user_id', $existing->id)->count());
        $this->assertSame($pmBefore, PlatformMembership::query()->where('user_id', $existing->id)->count());
        $this->assertSame(0, PlatformMembership::query()->where('user_id', $existing->id)->count());
    }

    public function test_colisao_sem_grant_global_falha_sem_promocao(): void
    {
        $this->createDemoOffice();
        $orphan = User::factory()->create([
            'email' => PlatformAdminDemoSeeder::EMAIL,
            'name' => 'Orfão',
            'selected_office_id' => null,
        ]);

        try {
            $this->seedPlatformAdmin();
            $this->fail('Esperava RuntimeException por grant incompatível.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('sem PlatformMembership', $e->getMessage());
        }

        $this->assertSame(0, PlatformMembership::query()->where('user_id', $orphan->id)->count());
        $this->assertFalse($orphan->fresh()->isPlatformAdmin());
    }

    public function test_office_da_plataforma_ausente_falha_sem_escrita_parcial(): void
    {
        $usersBefore = User::query()->count();
        $pmBefore = PlatformMembership::query()->count();

        try {
            $this->seedPlatformAdmin();
            $this->fail('Esperava RuntimeException por Office da Plataforma ausente.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString(PlatformAdminDemoSeeder::OFFICE_SLUG, $e->getMessage());
        }

        $this->assertSame($usersBefore, User::query()->count());
        $this->assertSame($pmBefore, PlatformMembership::query()->count());
        $this->assertNull(User::query()->where('email', PlatformAdminDemoSeeder::EMAIL)->first());
    }

    public function test_office_da_plataforma_inativo_falha_sem_escrita_parcial(): void
    {
        $this->createDemoOffice(active: false);

        $usersBefore = User::query()->count();
        $pmBefore = PlatformMembership::query()->count();

        try {
            $this->seedPlatformAdmin();
            $this->fail('Esperava RuntimeException por Office da Plataforma inativo.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString(PlatformAdminDemoSeeder::OFFICE_SLUG, $e->getMessage());
        }

        $this->assertSame($usersBefore, User::query()->count());
        $this->assertSame($pmBefore, PlatformMembership::query()->count());
        $this->assertNull(User::query()->where('email', PlatformAdminDemoSeeder::EMAIL)->first());
    }

    public function test_sub_seeder_recusa_production(): void
    {
        $this->createDemoOffice();
        $this->app['env'] = 'production';

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('local/testing');

        (new PlatformAdminDemoSeeder)->run();

        $this->assertNull(User::query()->where('email', PlatformAdminDemoSeeder::EMAIL)->first());
    }

    public function test_fixture_global_nao_aparece_na_equipe_nem_consome_max_users(): void
    {
        $office = $this->createDemoOffice();
        $officeAdmin = User::factory()->forOffice($office, OfficeRole::Admin)->create([
            'email' => 'admin@example.com',
            'password' => 'password',
            'selected_office_id' => $office->id,
        ]);

        $seatsBefore = app(OfficeTeamService::class)->occupiedSeats($office);

        $this->seedPlatformAdmin();

        $platform = User::query()->where('email', PlatformAdminDemoSeeder::EMAIL)->firstOrFail();
        $this->assertSame(0, OfficeMembership::query()->where('user_id', $platform->id)->count());

        $seatsAfter = app(OfficeTeamService::class)->occupiedSeats($office);
        $this->assertSame($seatsBefore, $seatsAfter);

        $this->actingAs($officeAdmin);
        app(RecentPasswordConfirmationGate::class)->markConfirmed($officeAdmin);

        $list = $this->actingAs($officeAdmin)
            ->getJson('/api/v1/office/members')
            ->assertOk();

        $emails = collect($list->json('data'))->pluck('email')->all();
        $this->assertNotContains(PlatformAdminDemoSeeder::EMAIL, $emails);
        $this->assertContains('admin@example.com', $emails);
    }

    public function test_colisao_com_ativacao_pendente_falha_sem_escalada(): void
    {
        $this->createDemoOffice();
        $user = User::factory()->create([
            'email' => PlatformAdminDemoSeeder::EMAIL,
            'is_active' => false,
            'selected_office_id' => null,
        ]);
        $pm = PlatformMembership::query()->create([
            'user_id' => $user->id,
            'role' => PlatformRole::PlatformAdmin,
            'is_active' => false,
            'default_office_id' => null,
        ]);
        AccountActivation::query()->create([
            'purpose' => ActivationPurpose::PlatformAdmin,
            'method' => ActivationMethod::ManualLink,
            'user_id' => $user->id,
            'platform_membership_id' => $pm->id,
            'email_normalized' => PlatformAdminDemoSeeder::EMAIL,
            'secret_hash' => Hash::make('secret'),
            'expires_at' => now()->addDay(),
            'generation' => 1,
            'created_by_user_id' => $user->id,
        ]);

        try {
            $this->seedPlatformAdmin();
            $this->fail('Esperava RuntimeException por AccountActivation.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('AccountActivation', $e->getMessage());
        }

        $this->assertFalse($user->fresh()->is_active);
        $this->assertFalse((bool) $pm->fresh()->is_active);
        $this->assertSame(1, AccountActivation::query()->where('user_id', $user->id)->count());
    }
}
