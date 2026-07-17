<?php

namespace Tests\Feature\Activation;

use App\Enums\ActivationMethod;
use App\Enums\OfficeRole;
use App\Enums\SubscriptionPlan;
use App\Models\Office;
use App\Models\OfficeMembership;
use App\Models\User;
use App\Services\Activation\OfficeTeamService;
use App\Services\Auth\RecentPasswordConfirmationGate;
use App\Support\CurrentOffice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfficeTeamTest extends TestCase
{
    use RefreshDatabase;

    private function officeAdmin(Office $office): User
    {
        return User::factory()->forOffice($office, OfficeRole::Admin)->create([
            'password' => bcrypt('admin-secret-12'),
        ]);
    }

    public function test_criar_membro_pendente_com_vaga(): void
    {
        $office = Office::factory()->create();
        $office->subscription->forceFill(['max_users' => 5, 'plan' => SubscriptionPlan::Starter])->save();
        $admin = $this->officeAdmin($office);

        $this->actingAs($admin);
        app(RecentPasswordConfirmationGate::class)->markConfirmed($admin);

        $response = $this->actingAs($admin)
            ->postJson('/api/v1/office/members', [
                'name' => 'Operador Um',
                'email' => 'op1@team.example',
                'role' => OfficeRole::Operator->value,
                'method' => ActivationMethod::ManualLink->value,
                'office_id' => 99999, // deve ser ignorado
            ])
            ->assertCreated()
            ->assertHeader('Cache-Control', 'no-store, private');

        $this->assertNotEmpty($response->json('data.activation_url'));
        $this->assertDatabaseHas('office_user', [
            'office_id' => $office->id,
            'is_active' => false,
            'role' => OfficeRole::Operator->value,
        ]);
        $this->assertSame(0, OfficeMembership::query()->where('office_id', 99999)->count());
    }

    public function test_limite_max_users(): void
    {
        $office = Office::factory()->create();
        $office->subscription->forceFill(['max_users' => 2, 'plan' => SubscriptionPlan::Starter])->save();
        $admin = $this->officeAdmin($office); // 1 seat

        $this->actingAs($admin);
        app(RecentPasswordConfirmationGate::class)->markConfirmed($admin);

        $this->actingAs($admin)
            ->postJson('/api/v1/office/members', [
                'name' => 'Op',
                'email' => 'op@team.example',
                'role' => OfficeRole::Operator->value,
                'method' => ActivationMethod::ManualLink->value,
            ])
            ->assertCreated();

        $this->actingAs($admin)
            ->postJson('/api/v1/office/members', [
                'name' => 'Op2',
                'email' => 'op2@team.example',
                'role' => OfficeRole::Operator->value,
                'method' => ActivationMethod::ManualLink->value,
            ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'seat_limit_reached');
    }

    public function test_platform_privilegiado_lista_equipe_do_office_selecionado(): void
    {
        config([
            'features.kill_switch' => false,
            'features.platform_privileged_context.enabled' => true,
        ]);

        $office = Office::factory()->create();
        $this->officeAdmin($office);
        $platform = User::factory()->asPlatformAdmin($office->id)->create([
            'password' => bcrypt('admin-secret-12'),
        ]);

        $this->actingAs($platform);
        app(RecentPasswordConfirmationGate::class)->markConfirmed($platform);

        // Seleciona office em modo privilegiado (sem OfficeMembership real)
        $this->actingAs($platform)
            ->postJson('/api/v1/platform/offices/select', ['office_id' => $office->id])
            ->assertOk();

        // PLATFORM_ADMIN com office selecionado gerencia a equipe do CurrentOffice
        $this->actingAs($platform)
            ->getJson('/api/v1/office/members')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta' => ['occupied_seats', 'max_users']]);
    }

    public function test_platform_sem_office_resolvido_nao_lista_equipe(): void
    {
        config([
            'features.kill_switch' => false,
            'features.platform_privileged_context.enabled' => true,
        ]);

        // Sem default_office_id e sem select → sem CurrentOffice
        $platform = User::factory()->asPlatformAdmin(null)->create([
            'password' => bcrypt('admin-secret-12'),
        ]);

        $this->actingAs($platform);
        app(RecentPasswordConfirmationGate::class)->markConfirmed($platform);

        // Fail-closed: 403 (autorização) ou 409 (sem CurrentOffice resolvido)
        $this->actingAs($platform)
            ->getJson('/api/v1/office/members')
            ->assertStatus(409);
    }

    public function test_operator_nao_gerencia_equipe(): void
    {
        $office = Office::factory()->create();
        $op = User::factory()->forOffice($office, OfficeRole::Operator)->create([
            'password' => bcrypt('admin-secret-12'),
        ]);

        $this->actingAs($op);
        app(RecentPasswordConfirmationGate::class)->markConfirmed($op);

        $this->actingAs($op)
            ->postJson('/api/v1/office/members', [
                'name' => 'X',
                'email' => 'x@team.example',
                'role' => OfficeRole::Viewer->value,
                'method' => ActivationMethod::ManualLink->value,
            ])
            ->assertStatus(403);
    }

    public function test_nao_desativa_ultimo_admin(): void
    {
        $office = Office::factory()->create();
        $admin = $this->officeAdmin($office);
        $membership = OfficeMembership::query()
            ->where('office_id', $office->id)
            ->where('user_id', $admin->id)
            ->firstOrFail();

        $this->actingAs($admin);
        app(RecentPasswordConfirmationGate::class)->markConfirmed($admin);

        $this->actingAs($admin)
            ->postJson('/api/v1/office/members/'.$membership->id.'/deactivate')
            ->assertStatus(403);
    }

    public function test_desativar_e_reativar_exige_nova_ativacao(): void
    {
        $office = Office::factory()->create();
        $office->subscription->forceFill(['max_users' => 10])->save();
        $admin = $this->officeAdmin($office);

        $member = User::factory()->forOffice($office, OfficeRole::Operator)->create([
            'email' => 'member@team.example',
            'password' => bcrypt('member-secret-12'),
        ]);
        $membership = OfficeMembership::query()
            ->where('office_id', $office->id)
            ->where('user_id', $member->id)
            ->firstOrFail();

        $this->actingAs($admin);
        app(RecentPasswordConfirmationGate::class)->markConfirmed($admin);

        $this->actingAs($admin)
            ->postJson('/api/v1/office/members/'.$membership->id.'/deactivate')
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->assertFalse($member->fresh()->is_active);

        $react = $this->actingAs($admin)
            ->postJson('/api/v1/office/members/'.$membership->id.'/reactivate', [
                'method' => ActivationMethod::ManualLink->value,
            ])
            ->assertOk();

        $this->assertFalse($react->json('data.immediate'));
        $this->assertSame('delivered', $react->json('data.credential_delivery'));
        $this->assertNotEmpty($react->json('data.activation_url'));
        $this->assertFalse($membership->fresh()->is_active);
    }

    public function test_email_existente_rejeitado_na_criacao(): void
    {
        $officeA = Office::factory()->create();
        $officeB = Office::factory()->create();
        User::factory()->forOffice($officeB, OfficeRole::Viewer)->create([
            'email' => 'taken@other.example',
        ]);
        $admin = $this->officeAdmin($officeA);

        $this->actingAs($admin);
        app(RecentPasswordConfirmationGate::class)->markConfirmed($admin);

        $this->actingAs($admin)
            ->postJson('/api/v1/office/members', [
                'name' => 'Y',
                'email' => 'taken@other.example',
                'role' => OfficeRole::Operator->value,
                'method' => ActivationMethod::ManualLink->value,
            ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'email_unavailable');
    }

    public function test_contagem_concorrente_ultima_vaga(): void
    {
        $office = Office::factory()->create();
        $office->subscription->forceFill(['max_users' => 2])->save();
        $admin = $this->officeAdmin($office);

        $service = app(OfficeTeamService::class);
        $this->actingAs($admin);
        // bind CurrentOffice via resolve
        app(CurrentOffice::class)->resolve($admin);

        $ok = 0;
        $fail = 0;
        foreach (['a@team.example', 'b@team.example'] as $email) {
            try {
                $service->createMember($admin, [
                    'name' => 'M',
                    'email' => $email,
                    'role' => OfficeRole::Operator,
                    'method' => ActivationMethod::ManualLink,
                ]);
                $ok++;
            } catch (\Throwable) {
                $fail++;
            }
        }

        $this->assertSame(1, $ok);
        $this->assertSame(1, $fail);
        $this->assertSame(2, app(OfficeTeamService::class)->occupiedSeats($office));
    }
}
