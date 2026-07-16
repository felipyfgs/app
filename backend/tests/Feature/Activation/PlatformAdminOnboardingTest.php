<?php

namespace Tests\Feature\Activation;

use App\Enums\ActivationMethod;
use App\Enums\ActivationPurpose;
use App\Enums\OfficeRole;
use App\Models\AccountActivation;
use App\Models\Office;
use App\Models\OfficeMembership;
use App\Models\User;
use App\Services\Auth\RecentPasswordConfirmationGate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformAdminOnboardingTest extends TestCase
{
    use RefreshDatabase;

    private function actor(): User
    {
        $office = Office::factory()->create();

        return User::factory()->asPlatformAdmin($office->id)->create([
            'password' => bcrypt('admin-secret-12'),
        ]);
    }

    public function test_cria_platform_admin_sem_membership(): void
    {
        $actor = $this->actor();
        $this->actingAs($actor);
        app(RecentPasswordConfirmationGate::class)->markConfirmed($actor);

        $response = $this->actingAs($actor)
            ->postJson('/api/v1/platform/admins', [
                'name' => 'Global Admin',
                'email' => 'global@platform.example',
                'method' => ActivationMethod::ManualLink->value,
            ])
            ->assertCreated()
            ->assertHeader('Cache-Control', 'no-store, private');

        $userId = $response->json('data.admin.user_id');
        $this->assertNotNull($userId);
        $this->assertSame(0, OfficeMembership::query()->where('user_id', $userId)->count());
        $this->assertDatabaseHas('platform_memberships', [
            'user_id' => $userId,
            'is_active' => false,
        ]);

        $activation = AccountActivation::query()->where('user_id', $userId)->first();
        $this->assertSame(ActivationPurpose::PlatformAdmin, $activation?->purpose);
    }

    public function test_email_existente_rejeitado_neutro(): void
    {
        $actor = $this->actor();
        $office = Office::factory()->create();
        User::factory()->forOffice($office, OfficeRole::Operator)->create([
            'email' => 'existe@office.example',
        ]);

        $this->actingAs($actor);
        app(RecentPasswordConfirmationGate::class)->markConfirmed($actor);

        $this->actingAs($actor)
            ->postJson('/api/v1/platform/admins', [
                'name' => 'Dup',
                'email' => 'existe@office.example',
                'method' => ActivationMethod::ManualLink->value,
            ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'email_unavailable');
    }

    public function test_listagem_e_regeneracao(): void
    {
        $actor = $this->actor();
        $this->actingAs($actor);
        app(RecentPasswordConfirmationGate::class)->markConfirmed($actor);

        $create = $this->actingAs($actor)
            ->postJson('/api/v1/platform/admins', [
                'name' => 'Pending Admin',
                'email' => 'pending.admin@platform.example',
                'method' => ActivationMethod::ManualLink->value,
            ])
            ->assertCreated();

        $userId = $create->json('data.admin.user_id');

        $this->actingAs($actor)
            ->getJson('/api/v1/platform/admins')
            ->assertOk()
            ->assertJsonFragment(['email' => 'pending.admin@platform.example']);

        $detail = $this->actingAs($actor)
            ->getJson('/api/v1/platform/admins/'.$userId)
            ->assertOk();

        $this->assertArrayNotHasKey('secret_hash', $detail->json('data.activation') ?? []);
        $this->assertArrayNotHasKey('activation_url', $detail->json('data'));

        $this->actingAs($actor)
            ->postJson('/api/v1/platform/admins/'.$userId.'/activation/regenerate', [
                'method' => ActivationMethod::TemporaryPassword->value,
            ])
            ->assertOk()
            ->assertJsonStructure(['data' => ['temporary_password', 'activation']]);
    }

    public function test_platform_admin_nao_conta_em_seats_da_equipe(): void
    {
        $office = Office::factory()->create();
        $officeAdmin = User::factory()->forOffice($office, OfficeRole::Admin)->create([
            'password' => bcrypt('admin-secret-12'),
        ]);

        $platformOnly = User::factory()->asPlatformAdmin($office->id)->create([
            'is_active' => true,
            'password_change_required' => false,
        ]);

        // Ativa membership de plataforma
        $platformOnly->platformMemberships()->update(['is_active' => true]);

        $this->assertSame(0, OfficeMembership::query()->where('user_id', $platformOnly->id)->count());

        $this->actingAs($officeAdmin);
        app(RecentPasswordConfirmationGate::class)->markConfirmed($officeAdmin);

        $list = $this->actingAs($officeAdmin)
            ->getJson('/api/v1/office/members')
            ->assertOk();

        $emails = collect($list->json('data'))->pluck('email')->all();
        $this->assertNotContains($platformOnly->email, $emails);
    }
}
