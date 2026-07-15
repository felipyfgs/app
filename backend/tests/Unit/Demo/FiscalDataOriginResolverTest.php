<?php

namespace Tests\Unit\Demo;

use App\Enums\FiscalDataOrigin;
use App\Models\Office;
use App\Services\Fiscal\Demo\DemoEnvironmentGuard;
use App\Services\Fiscal\Demo\FiscalDataOriginResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FiscalDataOriginResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_office_em_testing_retorna_demo(): void
    {
        $office = Office::factory()->create(['slug' => 'demo']);
        $resolver = app(FiscalDataOriginResolver::class);

        $this->assertSame(FiscalDataOrigin::Demo, $resolver->resolve($office, true));
        $this->assertTrue($resolver->isDemoOfficeContext($office));

        $meta = $resolver->toPublicMeta($office, true);
        $this->assertSame('DEMO', $meta['origin']);
        $this->assertTrue($meta['synthetic']);
        $this->assertNotNull($meta['banner']);
    }

    public function test_outro_office_nao_e_demo(): void
    {
        $office = Office::factory()->create(['slug' => 'outro']);
        $resolver = app(FiscalDataOriginResolver::class);

        $this->assertSame(FiscalDataOrigin::Live, $resolver->resolve($office, false));
        $this->assertFalse($resolver->isDemoOfficeContext($office));
    }

    public function test_production_nunca_retorna_demo(): void
    {
        $office = Office::factory()->create(['slug' => 'demo']);
        $this->app['env'] = 'production';

        $resolver = app(FiscalDataOriginResolver::class);
        $this->assertSame(FiscalDataOrigin::Live, $resolver->resolve($office, true));
        $this->assertFalse($resolver->isDemoOfficeContext($office));
    }

    public function test_guard_rejeita_production(): void
    {
        $this->app['env'] = 'production';
        config(['fiscal_demo.enabled' => true]);

        $this->expectException(\LogicException::class);
        app(DemoEnvironmentGuard::class)->assertCanSeed();
    }
}
