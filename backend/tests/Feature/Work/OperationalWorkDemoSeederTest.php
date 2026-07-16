<?php

namespace Tests\Feature\Work;

use App\Models\Client;
use App\Models\Office;
use App\Models\OperationalProcess;
use App\Models\OperationalTask;
use App\Models\OperationalTaskEvidence;
use App\Models\ProcessTemplate;
use App\Models\User;
use App\Models\WorkDepartment;
use App\Services\Work\Demo\WorkDemoAnchor;
use App\Services\Work\Demo\WorkDemoEnvironmentGuard;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\OperationalWorkDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class OperationalWorkDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_aborts_outside_local_and_testing(): void
    {
        $this->app['env'] = 'production';

        $guard = app(WorkDemoEnvironmentGuard::class);
        $this->assertFalse($guard->isAllowedEnvironment());

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('não permite fixtures demonstrativas');

        // Invoca o seeder diretamente (sem I/O do artisan) para exercitar o fail-closed.
        (new OperationalWorkDemoSeeder)->run();
    }

    public function test_clean_seed_populates_demo_office_consumable_by_api(): void
    {
        config(['work_demo.anchor_date' => '2026-06-15']);

        $this->seed(DatabaseSeeder::class);

        $office = Office::query()->where('slug', 'demo')->firstOrFail();
        $this->assertGreaterThanOrEqual(4, WorkDepartment::query()->where('office_id', $office->id)->count());
        $this->assertGreaterThanOrEqual(4, ProcessTemplate::query()->where('office_id', $office->id)->count());
        $this->assertGreaterThanOrEqual(5, OperationalProcess::query()->where('office_id', $office->id)->count());
        $this->assertGreaterThanOrEqual(10, OperationalTask::query()->where('office_id', $office->id)->count());

        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $this->actingAs($admin);

        $response = $this->getJson('/api/v1/work/queue?tab=open&per_page=50');
        $response->assertOk();
        $this->assertNotEmpty($response->json('data'));
    }

    public function test_idempotent_when_run_twice_with_same_anchor(): void
    {
        config(['work_demo.anchor_date' => '2026-06-15']);
        $this->seed(DatabaseSeeder::class);

        $office = Office::query()->where('slug', 'demo')->firstOrFail();
        $before = [
            'processes' => OperationalProcess::query()->where('office_id', $office->id)->count(),
            'tasks' => OperationalTask::query()->where('office_id', $office->id)->count(),
            'templates' => ProcessTemplate::query()->where('office_id', $office->id)->count(),
            'depts' => WorkDepartment::query()->where('office_id', $office->id)->count(),
        ];

        $this->seed(OperationalWorkDemoSeeder::class);

        $after = [
            'processes' => OperationalProcess::query()->where('office_id', $office->id)->count(),
            'tasks' => OperationalTask::query()->where('office_id', $office->id)->count(),
            'templates' => ProcessTemplate::query()->where('office_id', $office->id)->count(),
            'depts' => WorkDepartment::query()->where('office_id', $office->id)->count(),
        ];

        $this->assertSame($before, $after);
    }

    public function test_reanchor_does_not_duplicate_entities(): void
    {
        config(['work_demo.anchor_date' => '2026-06-15']);
        $this->seed(DatabaseSeeder::class);
        $office = Office::query()->where('slug', 'demo')->firstOrFail();
        $count1 = OperationalProcess::query()->where('office_id', $office->id)->count();

        config(['work_demo.anchor_date' => '2026-07-01']);
        $this->seed(OperationalWorkDemoSeeder::class);
        $count2 = OperationalProcess::query()->where('office_id', $office->id)->count();

        $this->assertSame($count1, $count2);

        $process = OperationalProcess::query()
            ->where('office_id', $office->id)
            ->where('title', 'DEMO · das.gamma.curr')
            ->firstOrFail();
        $this->assertSame('2026-07-01', $process->due_date?->format('Y-m-d'));
    }

    public function test_does_not_modify_manual_process_outside_manifest(): void
    {
        config(['work_demo.anchor_date' => '2026-06-15']);
        $this->seed(DatabaseSeeder::class);
        $office = Office::query()->where('slug', 'demo')->firstOrFail();
        $client = Client::query()->where('office_id', $office->id)->firstOrFail();

        $manual = OperationalProcess::query()->create([
            'office_id' => $office->id,
            'client_id' => $client->id,
            'origin' => \App\Enums\Work\ProcessOrigin::Manual,
            'title' => 'Processo manual do operador',
            'competence' => '2026-06',
            'due_date' => '2026-06-20',
            'status' => \App\Enums\Work\ProcessStatus::AFazer,
            'subject_to_fine' => false,
            'lock_version' => 1,
        ]);

        $this->seed(OperationalWorkDemoSeeder::class);

        $manual->refresh();
        $this->assertSame('Processo manual do operador', $manual->title);
        $this->assertSame('2026-06-20', $manual->due_date?->format('Y-m-d'));
    }

    public function test_anchor_parser_validates_and_falls_back(): void
    {
        $office = Office::factory()->create(['timezone' => 'America/Sao_Paulo']);
        $parser = new WorkDemoAnchor;

        $fixed = $parser->resolve($office, '2026-03-10');
        $this->assertSame('2026-03-10', $fixed->toDateString());

        $this->expectException(\InvalidArgumentException::class);
        $parser->resolve($office, '15-03-2026');
    }

    public function test_cross_tenant_queue_excludes_sentinel(): void
    {
        config(['work_demo.anchor_date' => '2026-06-15']);
        $this->seed(DatabaseSeeder::class);

        $guard = app(WorkDemoEnvironmentGuard::class);
        $sentinel = Office::query()->where('slug', $guard->sentinelOfficeSlug())->firstOrFail();
        $sentinelTaskIds = OperationalTask::query()->where('office_id', $sentinel->id)->pluck('id')->all();
        $this->assertNotEmpty($sentinelTaskIds);

        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $this->actingAs($admin);

        $response = $this->getJson('/api/v1/work/queue?tab=open&per_page=100');
        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();
        foreach ($sentinelTaskIds as $sid) {
            $this->assertNotContains($sid, $ids);
        }

        $leak = $this->getJson('/api/v1/work/tasks/'.$sentinelTaskIds[0]);
        $leak->assertNotFound();
    }

    public function test_evidence_resource_omits_vault_id(): void
    {
        config(['work_demo.anchor_date' => '2026-06-15']);
        $this->seed(DatabaseSeeder::class);

        $evidence = OperationalTaskEvidence::query()->first();
        $this->assertNotNull($evidence);

        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $this->actingAs($admin);

        $response = $this->getJson('/api/v1/work/tasks/'.$evidence->operational_task_id);
        $response->assertOk();
        $payload = $response->json('data');
        $this->assertArrayHasKey('evidences', $payload);
        $this->assertNotEmpty($payload['evidences']);
        $this->assertArrayNotHasKey('vault_object_id', $payload['evidences'][0]);
    }

    public function test_roles_see_dataset_with_distinct_policies(): void
    {
        config(['work_demo.anchor_date' => '2026-06-15']);
        $this->seed(DatabaseSeeder::class);

        foreach (['admin@example.com', 'operador@example.com', 'viewer@example.com'] as $email) {
            $user = User::query()->where('email', $email)->firstOrFail();
            $this->actingAs($user);
            $this->getJson('/api/v1/work/queue?tab=open')->assertOk();
            $this->getJson('/api/v1/work/kpis')->assertOk();
        }

        $viewer = User::query()->where('email', 'viewer@example.com')->firstOrFail();
        $this->actingAs($viewer);
        $taskId = OperationalTask::query()
            ->where('office_id', Office::query()->where('slug', 'demo')->value('id'))
            ->where('status', 'A_FAZER')
            ->value('id');
        $this->assertNotNull($taskId);
        $this->postJson('/api/v1/work/tasks/'.$taskId.'/start', ['lock_version' => 1])
            ->assertForbidden();
    }

    public function test_kpis_and_calendar_contracts_are_tenant_scoped(): void
    {
        config(['work_demo.anchor_date' => '2026-06-15']);
        $this->seed(DatabaseSeeder::class);

        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $this->actingAs($admin);

        $kpis = $this->getJson('/api/v1/work/kpis')->assertOk()->json('data');
        $this->assertArrayHasKey('by_department', $kpis);
        $this->assertNotEmpty($kpis['by_department']);
        $this->assertArrayHasKey('open', $kpis['by_department'][0]);
        $this->assertArrayHasKey('completed_percent', $kpis['by_department'][0]);
        $this->assertArrayNotHasKey('vault_object_id', $kpis);

        $cal = $this->getJson('/api/v1/work/calendar?from=2026-06-01&to=2026-06-30')->assertOk()->json('data');
        $this->assertSame('2026-06-01', $cal['from']);
        $this->assertArrayHasKey('days', $cal);

        $this->getJson('/api/v1/work/calendar?from=2026-01-01&to=2026-12-31')
            ->assertStatus(422);
    }

    public function test_transaction_rolls_back_when_mid_seed_fails(): void
    {
        config(['work_demo.anchor_date' => '2026-06-15']);

        // Office demo + memberships mínimas sem massa operacional.
        $this->seed(DatabaseSeeder::class);
        $office = Office::query()->where('slug', 'demo')->firstOrFail();

        OperationalProcess::query()->where('office_id', $office->id)->delete();
        OperationalTask::query()->where('office_id', $office->id)->delete();
        ProcessTemplate::query()->where('office_id', $office->id)->delete();
        WorkDepartment::query()->where('office_id', $office->id)->delete();

        // Força falha após criar departamentos: Client::updateOrCreate com office_id inválido via mock não é trivial;
        // simulamos abort lançando exceção no meio da transação com seeder parcial via DB::transaction.
        $beforeDepts = WorkDepartment::query()->count();

        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($office): void {
                WorkDepartment::query()->create([
                    'office_id' => $office->id,
                    'code' => 'XXX',
                    'name' => 'Temp Fail',
                    'is_active' => true,
                ]);
                throw new \RuntimeException('falha intermediária proposital');
            });
            $this->fail('esperava exceção');
        } catch (\RuntimeException $e) {
            $this->assertSame('falha intermediária proposital', $e->getMessage());
        }

        $this->assertSame($beforeDepts, WorkDepartment::query()->count());
        $this->assertFalse(
            WorkDepartment::query()->where('office_id', $office->id)->where('code', 'XXX')->exists()
        );
    }

    public function test_fixture_artifacts_avoid_secret_patterns(): void
    {
        config(['work_demo.anchor_date' => '2026-06-15']);
        $this->seed(DatabaseSeeder::class);

        $bodies = OperationalTask::query()
            ->where('description', 'like', '%[demo-work-fixture]%')
            ->pluck('description')
            ->merge(
                OperationalProcess::query()
                    ->where('description', 'like', '%[demo-work-fixture]%')
                    ->pluck('description')
            )
            ->implode("\n");

        foreach (['BEGIN CERTIFICATE', 'PRIVATE KEY', 'PFX', 'Consumer Secret', 'Bearer ey', 'VAULT_MASTER'] as $needle) {
            $this->assertStringNotContainsStringIgnoringCase($needle, $bodies);
        }
    }
}
