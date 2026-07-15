<?php

namespace Tests\Feature\Demo;

use App\Enums\FiscalMutationDenialCode;
use App\Enums\OfficeRole;
use App\Enums\SerproEnvironment;
use App\Models\Client;
use App\Models\Office;
use App\Models\User;
use App\Services\Fiscal\Demo\FiscalDataOriginResolver;
use App\Services\Fiscal\Mutations\FiscalMutationPolicy;
use Database\Seeders\FiscalMonitoringDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FiscalDemoMutationBlockTest extends TestCase
{
    use RefreshDatabase;

    public function test_preflight_policy_bloqueia_office_demo(): void
    {
        config([
            'fiscal_demo.enabled' => true,
            'fiscal_demo.office_slug' => 'demo',
            'features.global_enabled' => true,
            'features.mutating.enabled' => true,
            'fiscal_mutations.enabled' => true,
            'fiscal_mutations.kill_switch' => false,
        ]);

        $office = Office::factory()->create(['slug' => 'demo']);
        $this->seed(FiscalMonitoringDemoSeeder::class);

        $client = Client::query()->withoutGlobalScopes()
            ->where('office_id', $office->id)
            ->where('notes', 'like', '%[demo-fixture]%')
            ->firstOrFail();

        $admin = User::factory()
            ->forOffice($office, OfficeRole::Admin)
            ->withTwoFactorConfirmed()
            ->create();

        $policy = app(FiscalMutationPolicy::class);
        $result = $policy->evaluate(
            office: $office,
            client: $client,
            user: $admin,
            solutionCode: 'INTEGRA_SN',
            serviceCode: 'PGDASD',
            operationCode: 'TRANSMITIR',
            environment: SerproEnvironment::Trial,
            module: 'simples_mei',
            options: ['require_totp' => false, 'skip_anti_repeat' => true, 'skip_uncertain_check' => true],
        );

        $this->assertFalse($result->allowed);
        $codes = array_map(fn (FiscalMutationDenialCode $c) => $c->value, $result->codes);
        $this->assertContains(FiscalMutationDenialCode::DemoMode->value, $codes);
        $this->assertTrue(app(FiscalDataOriginResolver::class)->isDemoOfficeContext($office));
    }

    public function test_office_nao_demo_nao_recebe_demo_mode_por_default(): void
    {
        config([
            'fiscal_demo.office_slug' => 'demo',
            'features.global_enabled' => true,
        ]);

        $office = Office::factory()->create(['slug' => 'prod-like']);
        $this->assertFalse(app(FiscalDataOriginResolver::class)->isDemoOfficeContext($office));
    }
}
