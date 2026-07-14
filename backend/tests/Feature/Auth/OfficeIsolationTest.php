<?php

namespace Tests\Feature\Auth;

use App\Enums\OfficeRole;
use App\Models\Office;
use App\Models\User;
use App\Support\CurrentOffice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class OfficeIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_office_id_da_requisicao_e_ignorado(): void
    {
        $officeA = Office::factory()->create(['name' => 'A']);
        $officeB = Office::factory()->create(['name' => 'B']);
        $user = User::factory()->forOffice($officeA, OfficeRole::Admin)->withTwoFactorConfirmed()->create();

        $this->actingAs($user);

        $this->getJson('/api/v1/me?office_id='.$officeB->id)
            ->assertOk()
            ->assertJsonPath('data.office.id', $officeA->id);

        $resolved = app(CurrentOffice::class);
        $resolved->clear();
        $this->assertSame($officeA->id, $resolved->resolve($user)?->id);
    }

    public function test_assert_outro_escritorio_retorna_404(): void
    {
        $officeA = Office::factory()->create();
        $officeB = Office::factory()->create();
        $user = User::factory()->forOffice($officeA, OfficeRole::Viewer)->create();

        $this->actingAs($user);
        app(CurrentOffice::class)->resolve($user);

        try {
            app(CurrentOffice::class)->assertBelongsToOffice($officeB->id);
            $this->fail('Esperava abort 404');
        } catch (HttpException $e) {
            $this->assertSame(404, $e->getStatusCode());
        }
    }

    public function test_perfis_sao_resolvidos_por_associacao(): void
    {
        $office = Office::factory()->create();
        $admin = User::factory()->forOffice($office, OfficeRole::Admin)->withTwoFactorConfirmed()->create();
        $viewer = User::factory()->forOffice($office, OfficeRole::Viewer)->create();

        $this->actingAs($admin);
        $this->getJson('/api/v1/me')->assertJsonPath('data.role', 'ADMIN');

        $this->actingAs($viewer);
        app(CurrentOffice::class)->clear();
        $this->getJson('/api/v1/me')->assertJsonPath('data.role', 'VIEWER');
    }
}
