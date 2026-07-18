<?php

namespace Tests\Feature\Fiscal;

use App\Enums\OfficeRole;
use App\Http\Controllers\Api\V1\Fiscal\RegistrationLinkController;
use App\Http\Controllers\Api\V1\Fiscal\TaxProcessController;
use App\Jobs\Fiscal\RefreshRegistrationLinksJob;
use App\Jobs\Fiscal\RefreshTaxProcessesJob;
use App\Models\Client;
use App\Models\FiscalRegistrationLink;
use App\Models\FiscalTaxProcess;
use App\Models\Office;
use App\Models\OfficeMembership;
use App\Models\User;
use App\Support\CurrentOffice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class RegistrationAndTaxProcessApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Office $officeA;

    private Office $officeB;

    private Client $clientA;

    private Client $clientB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->officeA = Office::factory()->create();
        $this->officeB = Office::factory()->create();
        $this->admin = User::factory()
            ->forOffice($this->officeA, OfficeRole::Admin)
            ->withTwoFactorConfirmed()
            ->create();
        // membership no office B também
        OfficeMembership::query()->create([
            'office_id' => $this->officeB->id,
            'user_id' => $this->admin->id,
            'role' => OfficeRole::Admin,
        ]);
        $this->admin->forceFill(['selected_office_id' => $this->officeA->id])->save();

        $this->clientA = Client::factory()->forOffice($this->officeA)->create([
            'root_cnpj' => '11222333000181',
        ]);
        $this->clientB = Client::factory()->forOffice($this->officeB)->create([
            'root_cnpj' => '11222333000181',
        ]);
    }

    public function test_lists_registration_links_scoped_to_current_office(): void
    {
        FiscalRegistrationLink::query()->create([
            'office_id' => $this->officeA->id,
            'client_id' => $this->clientA->id,
            'contributor_cnpj' => '11222333000181',
            'link_key' => 'link-a',
            'status' => 'ACTIVE',
            'is_simulated' => true,
        ]);
        FiscalRegistrationLink::query()->create([
            'office_id' => $this->officeB->id,
            'client_id' => $this->clientB->id,
            'contributor_cnpj' => '11222333000181',
            'link_key' => 'link-b',
            'status' => 'ACTIVE',
            'is_simulated' => true,
        ]);

        $this->actingAs($this->admin);
        app(CurrentOffice::class)->clear();
        app(CurrentOffice::class)->resolve($this->admin);

        $res = $this->getJson('/api/v1/fiscal/registrations');
        $res->assertOk();
        $data = $res->json('data');
        $this->assertCount(1, $data);
        $this->assertSame('link-a', $data[0]['link_key']);
        $this->assertArrayNotHasKey('office_id', $data[0]);
        $this->assertArrayNotHasKey('contributor_cnpj', $data[0]);
    }

    public function test_tax_process_of_other_office_is_not_found(): void
    {
        $foreign = FiscalTaxProcess::query()->create([
            'office_id' => $this->officeB->id,
            'client_id' => $this->clientB->id,
            'contributor_cnpj' => '11222333000181',
            'process_number' => 'PROC-B-1',
            'status' => 'OPEN',
            'is_simulated' => true,
        ]);

        $this->actingAs($this->admin);
        app(CurrentOffice::class)->clear();
        app(CurrentOffice::class)->resolve($this->admin);

        $this->getJson('/api/v1/fiscal/tax-processes/'.$foreign->id)
            ->assertNotFound();
    }

    public function test_office_id_in_query_is_ignored(): void
    {
        FiscalTaxProcess::query()->create([
            'office_id' => $this->officeA->id,
            'client_id' => $this->clientA->id,
            'contributor_cnpj' => '11222333000181',
            'process_number' => 'PROC-A',
            'status' => 'OPEN',
        ]);
        FiscalTaxProcess::query()->create([
            'office_id' => $this->officeB->id,
            'client_id' => $this->clientB->id,
            'contributor_cnpj' => '11222333000181',
            'process_number' => 'PROC-B',
            'status' => 'OPEN',
        ]);

        $this->actingAs($this->admin);
        app(CurrentOffice::class)->clear();
        app(CurrentOffice::class)->resolve($this->admin);

        $res = $this->getJson('/api/v1/fiscal/tax-processes?office_id='.$this->officeB->id);
        $res->assertOk();
        $numbers = array_column($res->json('data'), 'process_number');
        $this->assertSame(['PROC-A'], $numbers);
    }

    public function test_canonical_routes_and_single_migration_contract(): void
    {
        $this->assertTrue(Schema::hasTable('fiscal_registration_links'));
        $this->assertTrue(Schema::hasTable('fiscal_tax_processes'));

        $migrationFiles = glob(database_path('migrations/*.php')) ?: [];
        $registrationCreators = [];
        $taxProcessCreators = [];
        foreach ($migrationFiles as $file) {
            $source = (string) file_get_contents($file);
            if (str_contains($source, "Schema::create('fiscal_registration_links'")) {
                $registrationCreators[] = basename($file);
            }
            if (str_contains($source, "Schema::create('fiscal_tax_processes'")) {
                $taxProcessCreators[] = basename($file);
            }
        }
        $this->assertSame(
            ['2026_07_16_500100_create_fiscal_registration_and_tax_process_tables.php'],
            $registrationCreators,
        );
        $this->assertSame($registrationCreators, $taxProcessCreators);

        $effective = [];
        foreach (Route::getRoutes()->getRoutes() as $route) {
            foreach (array_diff($route->methods(), ['HEAD']) as $method) {
                $key = $method.' '.$route->uri();
                $this->assertArrayNotHasKey($key, $effective, 'Rota método+URI duplicada: '.$key);
                $effective[$key] = $route->getActionName();
            }
        }

        $expected = [
            'GET api/v1/fiscal/registrations' => RegistrationLinkController::class.'@index',
            'GET api/v1/fiscal/clients/{clientId}/registrations' => RegistrationLinkController::class.'@showForClient',
            'POST api/v1/fiscal/clients/{clientId}/registrations/refresh' => RegistrationLinkController::class.'@refresh',
            'GET api/v1/fiscal/tax-processes' => TaxProcessController::class.'@index',
            'GET api/v1/fiscal/clients/{clientId}/tax-processes' => TaxProcessController::class.'@showForClient',
            'POST api/v1/fiscal/clients/{clientId}/tax-processes/refresh' => TaxProcessController::class.'@refresh',
            'GET api/v1/fiscal/tax-processes/{id}' => TaxProcessController::class.'@show',
        ];
        foreach ($expected as $key => $action) {
            $this->assertSame($action, $effective[$key] ?? null, 'Contrato canônico ausente: '.$key);
        }
        $this->assertArrayNotHasKey('GET api/v1/fiscal/registrations/clients/{clientId}', $effective);
        $this->assertArrayNotHasKey('GET api/v1/fiscal/tax-processes/clients/{clientId}', $effective);
    }

    public function test_refresh_uses_current_office_and_role_matrix(): void
    {
        Queue::fake();
        config([
            'serpro.capabilities.registrations' => 'real',
            'serpro.capabilities.tax_processes' => 'real',
        ]);

        $this->actingAs($this->admin);
        app(CurrentOffice::class)->clear();
        $this->postJson(
            "/api/v1/fiscal/clients/{$this->clientA->id}/registrations/refresh",
            ['office_id' => $this->officeB->id],
        )->assertAccepted();

        Queue::assertPushed(RefreshRegistrationLinksJob::class, function (RefreshRegistrationLinksJob $job): bool {
            return $job->officeId === $this->officeA->id && $job->clientId === $this->clientA->id;
        });

        $operator = User::factory()->forOffice($this->officeA, OfficeRole::Operator)->create();
        $this->actingAs($operator);
        app(CurrentOffice::class)->clear();
        $this->postJson("/api/v1/fiscal/clients/{$this->clientA->id}/tax-processes/refresh")
            ->assertAccepted();

        $viewer = User::factory()->forOffice($this->officeA, OfficeRole::Viewer)->create();
        $this->actingAs($viewer);
        app(CurrentOffice::class)->clear();
        $this->postJson("/api/v1/fiscal/clients/{$this->clientA->id}/tax-processes/refresh")
            ->assertForbidden();

        Queue::assertPushed(RefreshTaxProcessesJob::class, 1);
    }

    public function test_foreign_client_and_platform_admin_fail_closed(): void
    {
        Queue::fake();
        $this->actingAs($this->admin);
        app(CurrentOffice::class)->clear();
        $this->postJson(
            "/api/v1/fiscal/clients/{$this->clientB->id}/registrations/refresh",
            ['office_id' => $this->officeB->id],
        )->assertNotFound();
        Queue::assertNothingPushed();

        $platformAdmin = User::factory()->asPlatformAdmin()->create();
        $this->actingAs($platformAdmin);
        app(CurrentOffice::class)->clear();
        $this->getJson('/api/v1/fiscal/registrations')
            ->assertStatus(409)
            ->assertJsonPath('code', 'office_context_required');
    }
}
