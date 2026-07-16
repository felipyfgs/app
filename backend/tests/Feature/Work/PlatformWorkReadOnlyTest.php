<?php

namespace Tests\Feature\Work;

use App\Enums\OfficeRole;
use App\Models\Office;
use App\Models\User;
use App\Models\WorkDepartment;
use App\Support\CurrentOffice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fronteira Work: PLATFORM_ADMIN sem membership = leitura ok, mutação 403.
 * Conta dual usa papel real da membership.
 */
class PlatformWorkReadOnlyTest extends TestCase
{
    use RefreshDatabase;

    private function enablePrivileged(): void
    {
        config([
            'features.kill_switch' => false,
            'features.platform_privileged_context.enabled' => true,
        ]);
    }

    public function test_platform_admin_sem_membership_le_work_e_nao_muta(): void
    {
        $this->enablePrivileged();

        $office = Office::factory()->create();
        WorkDepartment::factory()->create(['office_id' => $office->id, 'name' => 'Fiscal']);

        $admin = User::factory()->asPlatformAdmin($office->id)->create();

        $this->actingAs($admin)
            ->postJson('/api/v1/platform/offices/select', ['office_id' => $office->id])
            ->assertOk();

        app(CurrentOffice::class)->clear();

        $this->actingAs($admin)
            ->getJson('/api/v1/work/departments')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Fiscal']);

        $this->actingAs($admin)
            ->postJson('/api/v1/work/departments', [
                'name' => 'Novo Dept',
                'code' => 'novo',
            ])
            ->assertForbidden()
            ->assertJsonPath('code', 'work_real_membership_required');

        $this->assertDatabaseMissing('work_departments', ['name' => 'Novo Dept']);
    }

    public function test_platform_admin_sem_membership_nao_exporta(): void
    {
        $this->enablePrivileged();

        $office = Office::factory()->create();
        $admin = User::factory()->asPlatformAdmin($office->id)->create();

        $this->actingAs($admin)
            ->postJson('/api/v1/platform/offices/select', ['office_id' => $office->id])
            ->assertOk();

        app(CurrentOffice::class)->clear();

        $this->actingAs($admin)
            ->postJson('/api/v1/work/exports', [
                'scope' => 'queue',
            ])
            ->assertForbidden();
    }

    public function test_conta_dual_muta_com_papel_real_admin(): void
    {
        $this->enablePrivileged();

        $office = Office::factory()->create();
        $dual = User::factory()
            ->asPlatformAdmin($office->id)
            ->forOffice($office, OfficeRole::Admin)
            ->create();

        $this->actingAs($dual)
            ->postJson('/api/v1/platform/offices/select', ['office_id' => $office->id])
            ->assertOk();

        app(CurrentOffice::class)->clear();

        $this->actingAs($dual)
            ->postJson('/api/v1/work/departments', [
                'name' => 'Criado pela dual',
                'code' => 'dual-dept',
            ])
            ->assertSuccessful();

        $this->assertDatabaseHas('work_departments', [
            'office_id' => $office->id,
            'name' => 'Criado pela dual',
        ]);
    }

    public function test_conta_dual_viewer_nao_cria_processo(): void
    {
        $this->enablePrivileged();

        $office = Office::factory()->create();
        $dual = User::factory()
            ->asPlatformAdmin($office->id)
            ->forOffice($office, OfficeRole::Viewer)
            ->create();

        $this->actingAs($dual)
            ->postJson('/api/v1/platform/offices/select', ['office_id' => $office->id])
            ->assertOk();

        app(CurrentOffice::class)->clear();

        // Middleware passa (tem membership real), policy nega por papel VIEWER
        $response = $this->actingAs($dual)
            ->postJson('/api/v1/work/processes', [
                'title' => 'Não deve criar',
            ]);

        $this->assertTrue(in_array($response->status(), [403, 422], true));
        $this->assertDatabaseMissing('operational_processes', ['title' => 'Não deve criar']);
    }

    public function test_isolamento_entre_offices(): void
    {
        $this->enablePrivileged();

        $officeA = Office::factory()->create();
        $officeB = Office::factory()->create();
        WorkDepartment::factory()->create(['office_id' => $officeA->id, 'name' => 'Dept A']);
        WorkDepartment::factory()->create(['office_id' => $officeB->id, 'name' => 'Dept B']);

        $admin = User::factory()->asPlatformAdmin($officeA->id)->create();

        $this->actingAs($admin)
            ->postJson('/api/v1/platform/offices/select', ['office_id' => $officeA->id])
            ->assertOk();

        app(CurrentOffice::class)->clear();

        $this->actingAs($admin)
            ->getJson('/api/v1/work/departments')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Dept A'])
            ->assertJsonMissing(['name' => 'Dept B']);
    }

    public function test_work_route_matrix_versionada(): void
    {
        $matrix = config('work_route_matrix');
        $this->assertIsArray($matrix);
        $this->assertSame(1, $matrix['version']);
        $this->assertNotEmpty($matrix['read']);
        $this->assertNotEmpty($matrix['mutate']);
        $this->assertContains('POST /api/v1/work/exports', $matrix['mutate']);
        $this->assertContains('GET /api/v1/work/departments', $matrix['read']);
    }
}
