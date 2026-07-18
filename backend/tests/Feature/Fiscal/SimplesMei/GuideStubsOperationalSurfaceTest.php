<?php

namespace Tests\Feature\Fiscal\SimplesMei;

use App\Enums\OfficeRole;
use App\Models\Office;
use App\Models\User;
use App\Support\CurrentOffice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuideStubsOperationalSurfaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_rota_operacional_de_guide_stubs_nao_e_exposta(): void
    {
        $office = Office::factory()->create();
        $user = User::factory()
            ->forOffice($office, OfficeRole::Admin)
            ->withTwoFactorConfirmed()
            ->create();

        $this->actingAs($user);
        app(CurrentOffice::class)->resolve($user);

        $this->getJson('/api/v1/fiscal/simples-mei/clients/999999/guide-stubs')
            ->assertNotFound();
    }

    public function test_runtime_nao_conecta_emissor_das_ao_produtor_de_stub(): void
    {
        $adapter = file_get_contents(app_path('Services/Fiscal/SimplesMei/SimplesMeiAdapter.php'));
        $provider = file_get_contents(app_path('Providers/AppServiceProvider.php'));

        self::assertIsString($adapter);
        self::assertIsString($provider);
        self::assertStringNotContainsString('DasGuideHookService', $adapter);
        self::assertStringNotContainsString('DasGuideHookService', $provider);
    }
}
