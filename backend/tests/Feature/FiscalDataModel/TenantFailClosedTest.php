<?php

namespace Tests\Feature\FiscalDataModel;

use App\Enums\OfficeRole;
use App\Enums\PlatformRole;
use App\Models\Client;
use App\Models\Office;
use App\Models\PlatformMembership;
use App\Models\User;
use App\Support\CurrentOffice;
use App\Support\FiscalDataModel\PrivilegedOfficeContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantFailClosedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['fiscal_data_model.fail_closed_scopes' => true]);
        PrivilegedOfficeContext::reset();
        app(CurrentOffice::class)->clear();
    }

    protected function tearDown(): void
    {
        PrivilegedOfficeContext::reset();
        parent::tearDown();
    }

    public function test_sem_contexto_de_office_nao_lista_clientes(): void
    {
        $office = Office::factory()->create();
        Client::factory()->forOffice($office)->count(2)->create();

        app(CurrentOffice::class)->clear();
        PrivilegedOfficeContext::reset();

        $this->assertSame(0, Client::query()->count());
    }

    public function test_com_office_ativo_lista_apenas_do_tenant(): void
    {
        $officeA = Office::factory()->create();
        $officeB = Office::factory()->create();
        Client::factory()->forOffice($officeA)->count(2)->create();
        Client::factory()->forOffice($officeB)->count(3)->create();

        $user = User::factory()->forOffice($officeA, OfficeRole::Admin)->create();
        $this->actingAs($user);
        app(CurrentOffice::class)->clear();
        app(CurrentOffice::class)->resolve($user);

        $this->assertSame(2, Client::query()->count());
    }

    public function test_mesmo_cnpj_em_dois_tenants_permanece_isolado(): void
    {
        $officeA = Office::factory()->create();
        $officeB = Office::factory()->create();
        Client::factory()->forOffice($officeA)->create([
            'root_cnpj' => '11222333',
            'legal_name' => 'A',
        ]);
        Client::factory()->forOffice($officeB)->create([
            'root_cnpj' => '11222333',
            'legal_name' => 'B',
        ]);

        $userA = User::factory()->forOffice($officeA, OfficeRole::Viewer)->create();
        $this->actingAs($userA);
        app(CurrentOffice::class)->clear();
        app(CurrentOffice::class)->resolve($userA);

        $this->assertSame(1, Client::query()->count());
        $this->assertSame('A', Client::query()->first()->legal_name);
    }

    public function test_contexto_privilegiado_permite_rotina_global_com_motivo(): void
    {
        $office = Office::factory()->create();
        Client::factory()->forOffice($office)->count(2)->create();

        app(CurrentOffice::class)->clear();
        $this->assertSame(0, Client::query()->count());

        $count = PrivilegedOfficeContext::run('test:global-job', function () {
            return Client::query()->count();
        });

        $this->assertSame(2, $count);
        $this->assertFalse(PrivilegedOfficeContext::isOpen());
    }

    public function test_platform_admin_sem_membership_nao_herda_leitura_fiscal(): void
    {
        $office = Office::factory()->create();
        Client::factory()->forOffice($office)->create();

        $admin = User::factory()->create(['is_active' => true]);
        PlatformMembership::query()->create([
            'user_id' => $admin->id,
            'role' => PlatformRole::PlatformAdmin,
            'is_active' => true,
        ]);

        $this->actingAs($admin);
        app(CurrentOffice::class)->clear();
        $this->assertNull(app(CurrentOffice::class)->resolve($admin));
        $this->assertSame(0, Client::query()->count());
        $this->assertTrue($admin->isPlatformAdmin());
    }
}
