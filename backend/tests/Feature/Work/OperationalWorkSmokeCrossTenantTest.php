<?php

namespace Tests\Feature\Work;

use App\Enums\OfficeRole;
use App\Enums\Work\TaskStatus;
use App\Models\Client;
use App\Models\Office;
use App\Models\OfficeMembership;
use App\Models\OperationalExport;
use App\Models\OperationalProcess;
use App\Models\OperationalTask;
use App\Models\User;
use App\Models\WorkDepartment;
use App\Support\CurrentOffice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ApiSecretScanner;
use Tests\TestCase;

/**
 * Smoke restrito: dois tenants, mesmo CNPJ/competência, varredura cross-tenant.
 */
class OperationalWorkSmokeCrossTenantTest extends TestCase
{
    use RefreshDatabase;

    public function test_smoke_dois_tenants_sem_vazamento(): void
    {
        $officeA = Office::factory()->create(['timezone' => 'America/Sao_Paulo']);
        $officeB = Office::factory()->create(['timezone' => 'America/Sao_Paulo']);
        $adminA = User::factory()->forOffice($officeA, OfficeRole::Admin)->withTwoFactorConfirmed()->create();
        $adminB = User::factory()->forOffice($officeB, OfficeRole::Admin)->withTwoFactorConfirmed()->create();
        $cnpj = '99888777000166';
        $clientA = Client::factory()->create(['office_id' => $officeA->id, 'root_cnpj' => $cnpj, 'is_active' => true]);
        $clientB = Client::factory()->create(['office_id' => $officeB->id, 'root_cnpj' => $cnpj, 'is_active' => true]);
        $deptA = WorkDepartment::factory()->create(['office_id' => $officeA->id, 'code' => 'FIS']);
        $deptB = WorkDepartment::factory()->create(['office_id' => $officeB->id, 'code' => 'FIS']);

        $processA = OperationalProcess::factory()->create([
            'office_id' => $officeA->id,
            'client_id' => $clientA->id,
            'competence' => '2026-02',
            'title' => 'Proc secret A',
            'work_department_id' => $deptA->id,
        ]);
        $taskA = OperationalTask::factory()->create([
            'office_id' => $officeA->id,
            'operational_process_id' => $processA->id,
            'title' => 'Task secret A',
            'status' => TaskStatus::AFazer,
            'work_department_id' => $deptA->id,
        ]);
        $processB = OperationalProcess::factory()->create([
            'office_id' => $officeB->id,
            'client_id' => $clientB->id,
            'competence' => '2026-02',
            'title' => 'Proc secret B',
            'work_department_id' => $deptB->id,
        ]);
        $taskB = OperationalTask::factory()->create([
            'office_id' => $officeB->id,
            'operational_process_id' => $processB->id,
            'title' => 'Task secret B',
            'status' => TaskStatus::AFazer,
        ]);

        // A não lê B
        $this->actingAs($adminA, 'sanctum');
        app(CurrentOffice::class)->clear();
        app(CurrentOffice::class)->resolve($adminA);

        $this->getJson("/api/v1/work/processes/{$processB->id}")->assertNotFound();
        $this->getJson("/api/v1/work/tasks/{$taskB->id}")->assertNotFound();
        $listA = $this->getJson('/api/v1/work/processes')->assertOk()->json('data');
        $titlesA = collect($listA)->pluck('title')->all();
        $this->assertContains('Proc secret A', $titlesA);
        $this->assertNotContains('Proc secret B', $titlesA);

        $queueA = $this->getJson('/api/v1/work/queue')->assertOk();
        ApiSecretScanner::assertClean(json_encode($queueA->json()) ?: '', 'smoke.queue.a');

        $exportA = $this->postJson('/api/v1/work/exports', ['filters' => []])->assertCreated()->json('data');
        $this->assertArrayNotHasKey('storage_path', $exportA);
        ApiSecretScanner::assertClean(json_encode($exportA) ?: '', 'smoke.export.a');

        // B não lê A
        $this->actingAs($adminB, 'sanctum');
        app(CurrentOffice::class)->clear();
        app(CurrentOffice::class)->resolve($adminB);

        $this->getJson("/api/v1/work/processes/{$processA->id}")->assertNotFound();
        $this->getJson("/api/v1/work/tasks/{$taskA->id}")->assertNotFound();
        $listB = $this->getJson('/api/v1/work/processes')->assertOk()->json('data');
        $this->assertContains('Proc secret B', collect($listB)->pluck('title')->all());
        $this->assertNotContains('Proc secret A', collect($listB)->pluck('title')->all());

        // Export de B não enxerga storage de A
        $exportB = $this->postJson('/api/v1/work/exports', ['filters' => []])->assertCreated()->json('data');
        $this->assertNotSame($exportA['id'] ?? null, $exportB['id'] ?? null);

        // Contagens cross-tenant ignoram global scope (só no teste)
        $this->assertSame(
            1,
            OperationalTask::withoutGlobalScopes()->where('office_id', $officeA->id)->count()
        );
        $this->assertSame(
            1,
            OperationalTask::withoutGlobalScopes()->where('office_id', $officeB->id)->count()
        );
        $this->assertSame(
            1,
            OperationalExport::withoutGlobalScopes()->where('office_id', $officeA->id)->count()
        );
    }
}
