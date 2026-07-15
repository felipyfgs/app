<?php

namespace Tests\Feature\Platform;

use App\Enums\OfficeRole;
use App\Enums\SubscriptionStatus;
use App\Models\Client;
use App\Models\Office;
use App\Models\User;
use App\Services\Platform\OfficeSubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_admin_sem_membership_acessa_admin_sanitizado(): void
    {
        $office = Office::factory()->create(['name' => 'Escritório X']);
        $admin = User::factory()->asPlatformAdmin()->create();

        $this->actingAs($admin)
            ->getJson('/api/v1/platform/tenants')
            ->assertOk()
            ->assertJsonPath('data.0.id', $office->id)
            ->assertJsonPath('data.0.name', 'Escritório X')
            ->assertJsonStructure([
                'data' => [[
                    'id',
                    'name',
                    'slug',
                    'is_active',
                    'subscription' => [
                        'plan',
                        'status',
                        'limits',
                    ],
                    'memberships_count',
                ]],
            ]);

        // Payload sanitizado: sem clientes, notas fiscais, etc.
        $payload = $this->actingAs($admin)->getJson('/api/v1/platform/tenants/'.$office->id)->json('data');
        $this->assertArrayNotHasKey('clients', $payload);
        $this->assertArrayNotHasKey('documents', $payload);
        $this->assertArrayNotHasKey('root_cnpj', $payload);
    }

    public function test_platform_admin_sem_membership_nao_acessa_fiscal(): void
    {
        $office = Office::factory()->create();
        Client::factory()->forOffice($office)->create();
        $admin = User::factory()->asPlatformAdmin()->create();

        $this->assertFalse($admin->isPlatformAdmin() && $admin->activeMembership() !== null);
        $this->assertTrue($admin->isPlatformAdmin());
        $this->assertNull($admin->activeMembership());

        $this->actingAs($admin)
            ->getJson('/api/v1/clients')
            ->assertForbidden()
            ->assertJsonPath('message', 'Usuário sem escritório ativo.');

        $this->actingAs($admin)
            ->getJson('/api/v1/office/subscription')
            ->assertForbidden();
    }

    public function test_admin_de_escritorio_nao_acessa_rotas_platform(): void
    {
        $office = Office::factory()->create();
        $user = User::factory()->forOffice($office, OfficeRole::Admin)->withTwoFactorConfirmed()->create();

        $this->assertFalse($user->isPlatformAdmin());

        $this->actingAs($user)
            ->getJson('/api/v1/platform/tenants')
            ->assertForbidden();
    }

    public function test_platform_admin_pode_suspender_tenant(): void
    {
        $office = Office::factory()->create();
        $admin = User::factory()->asPlatformAdmin()->create();

        $this->actingAs($admin)
            ->patchJson('/api/v1/platform/tenants/'.$office->id.'/subscription', [
                'status' => 'SUSPENDED',
                'notes' => 'inadimplência SaaS',
            ])
            ->assertOk()
            ->assertJsonPath('data.subscription.status', SubscriptionStatus::Suspended->value);

        $this->assertSame(
            SubscriptionStatus::Suspended,
            $office->fresh()->subscription->status,
        );
    }

    public function test_platform_admin_com_membership_ainda_nao_implica_bypass_de_isolamento(): void
    {
        // Mesmo com membership em A, PLATFORM_ADMIN não vê dados de B via API tenant.
        $officeA = Office::factory()->create();
        $officeB = Office::factory()->create();
        Client::factory()->forOffice($officeB)->create(['legal_name' => 'Segredo B']);

        $admin = User::factory()
            ->asPlatformAdmin()
            ->forOffice($officeA, OfficeRole::Viewer)
            ->create();

        $this->actingAs($admin)
            ->getJson('/api/v1/clients')
            ->assertOk()
            ->assertJsonMissing(['legal_name' => 'Segredo B']);
    }

    public function test_me_expoe_flag_platform_admin(): void
    {
        $admin = User::factory()->asPlatformAdmin()->create();
        $user = User::factory()->create();

        $this->actingAs($admin)
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.is_platform_admin', true)
            ->assertJsonPath('data.office', null);

        $this->actingAs($user)
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.is_platform_admin', false);
    }

    public function test_filtro_status_na_listagem_platform(): void
    {
        $active = Office::factory()->create();
        $suspended = Office::factory()->create();
        app(OfficeSubscriptionService::class)->suspend($suspended->subscription);

        $admin = User::factory()->asPlatformAdmin()->create();

        $this->actingAs($admin)
            ->getJson('/api/v1/platform/tenants?status=SUSPENDED')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $suspended->id);

        $this->assertNotSame($active->id, $suspended->id);
    }

    public function test_platform_admin_sem_totp_e_bloqueado_em_mutacoes(): void
    {
        config()->set('fortify.two_factor_required', true);

        $office = Office::factory()->create();
        $admin = User::factory()->asPlatformAdmin()->create([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ]);

        $this->assertTrue($admin->isPlatformAdmin());
        $this->assertFalse($admin->hasConfirmedTwoFactor());

        $this->actingAs($admin)
            ->postJson('/api/v1/platform/serpro/kill-switch', ['enabled' => true])
            ->assertForbidden()
            ->assertJsonPath('code', 'platform_two_factor_required');

        $this->actingAs($admin)
            ->postJson('/api/v1/platform/serpro/breaker/reset')
            ->assertForbidden()
            ->assertJsonPath('code', 'platform_two_factor_required');

        $this->actingAs($admin)
            ->patchJson('/api/v1/platform/tenants/'.$office->id.'/subscription', [
                'status' => 'SUSPENDED',
            ])
            ->assertForbidden()
            ->assertJsonPath('code', 'platform_two_factor_required');

        // Leitura platform também bloqueada (grupo inteiro exige 2FA)
        $this->actingAs($admin)
            ->getJson('/api/v1/platform/tenants')
            ->assertForbidden()
            ->assertJsonPath('code', 'platform_two_factor_required');

        // /me continua acessível (fora do grupo platform)
        $this->actingAs($admin)
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.is_platform_admin', true)
            ->assertJsonPath('data.two_factor_confirmed', false);
    }

    public function test_platform_admin_com_totp_acessa_mutacoes_quando_config_exige(): void
    {
        config()->set('fortify.two_factor_required', true);

        $office = Office::factory()->create();
        $admin = User::factory()->asPlatformAdmin()->create();

        $this->assertTrue($admin->hasConfirmedTwoFactor());

        $this->actingAs($admin)
            ->patchJson('/api/v1/platform/tenants/'.$office->id.'/subscription', [
                'status' => 'SUSPENDED',
                'notes' => '2fa ok',
            ])
            ->assertOk()
            ->assertJsonPath('data.subscription.status', SubscriptionStatus::Suspended->value);
    }

    public function test_platform_admin_sem_totp_acessa_quando_2fa_desativado(): void
    {
        config()->set('fortify.two_factor_required', false);

        $admin = User::factory()->asPlatformAdmin()->create([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ]);

        $this->actingAs($admin)
            ->getJson('/api/v1/platform/tenants')
            ->assertOk();
    }
}
