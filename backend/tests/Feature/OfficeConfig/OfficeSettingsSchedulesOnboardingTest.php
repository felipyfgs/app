<?php

namespace Tests\Feature\OfficeConfig;

use App\Enums\OfficeRole;
use App\Models\Office;
use App\Models\OfficeMonitorSchedulePolicy;
use App\Models\User;
use App\Support\CurrentOffice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Agendas mensais e status de onboarding acionável em /office/settings/*.
 */
class OfficeSettingsSchedulesOnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_lista_agendas_com_defaults(): void
    {
        [$office] = $this->actingAsOfficeAdmin();

        $response = $this->getJson('/api/v1/office/settings/monitor-schedules');

        $response->assertOk()
            ->assertJsonPath('data.0.monitor_key', 'sitfis')
            ->assertJsonStructure([
                'data' => [
                    ['monitor_key', 'monitor_label', 'day_of_month', 'is_default', 'timezone'],
                ],
            ]);

        $this->assertGreaterThanOrEqual(1, count($response->json('data')));
        $this->assertDatabaseHas('office_monitor_schedule_policies', [
            'office_id' => $office->id,
            'monitor_key' => 'sitfis',
        ]);
    }

    public function test_admin_atualiza_dia_da_agenda(): void
    {
        [$office, $admin] = $this->actingAsOfficeAdmin();

        $response = $this->putJson('/api/v1/office/settings/monitor-schedules/sitfis', [
            'day_of_month' => 15,
            'office_id' => 99999,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.monitor_key', 'sitfis')
            ->assertJsonPath('data.day_of_month', 15)
            ->assertJsonPath('data.is_default', false);

        $policy = OfficeMonitorSchedulePolicy::query()
            ->where('office_id', $office->id)
            ->where('monitor_key', 'sitfis')
            ->first();
        $this->assertNotNull($policy);
        $this->assertSame(15, $policy->day_of_month);
        $this->assertTrue($policy->is_custom);
        $this->assertSame($admin->id, $policy->updated_by_user_id);
    }

    public function test_monitor_desconhecido_retorna_404(): void
    {
        $this->actingAsOfficeAdmin();

        $this->putJson('/api/v1/office/settings/monitor-schedules/unknown_monitor', [
            'day_of_month' => 10,
        ])->assertNotFound();
    }

    public function test_onboarding_status_retorna_estado_acionavel(): void
    {
        $this->actingAsOfficeAdmin();

        $response = $this->getJson('/api/v1/office/settings/onboarding-status');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'status',
                    'actions',
                    'correlation_id',
                    'message',
                ],
            ]);

        $status = $response->json('data.status');
        $this->assertIsString($status);
        $this->assertNotSame('', $status);
    }

    public function test_viewer_pode_ler_mas_nao_mutar_agenda(): void
    {
        $office = Office::factory()->create();
        $viewer = User::factory()
            ->forOffice($office, OfficeRole::Viewer)
            ->withTwoFactorConfirmed()
            ->create();
        $this->actingAs($viewer);
        app(CurrentOffice::class)->resolve($viewer);

        $this->getJson('/api/v1/office/settings/monitor-schedules')->assertOk();
        $this->getJson('/api/v1/office/settings/onboarding-status')->assertOk();
        $this->putJson('/api/v1/office/settings/monitor-schedules/sitfis', [
            'day_of_month' => 12,
        ])->assertForbidden();
    }

    /**
     * @return array{0: Office, 1: User}
     */
    private function actingAsOfficeAdmin(): array
    {
        $office = Office::factory()->create();
        $admin = User::factory()->forOffice($office, OfficeRole::Admin)->withTwoFactorConfirmed()->create();
        $this->actingAs($admin);
        app(CurrentOffice::class)->resolve($admin);

        return [$office, $admin];
    }
}
