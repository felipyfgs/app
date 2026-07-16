<?php

namespace Tests\Unit\Serpro;

use App\Enums\SerproFunctionalRoute;
use App\Services\Serpro\SerproKillSwitchService;
use App\Services\Serpro\SerproProductionEgressGate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SerproProductionEgressGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_billable_routes_are_allowed_when_no_exposed_credentials(): void
    {
        config(['serpro.kill_switch' => false]);
        Cache::flush();

        $gate = $this->app->make(SerproProductionEgressGate::class);

        $eval = $gate->evaluateBillableEgress(route: SerproFunctionalRoute::Apoiar);
        $this->assertTrue($eval['allowed']);

        $monitor = $gate->evaluateBillableEgress(route: SerproFunctionalRoute::Monitorar);
        $this->assertTrue($monitor['allowed']);
    }

    public function test_kill_switch_blocks_billable_route(): void
    {
        config(['serpro.kill_switch' => true]);
        Cache::flush();

        $this->assertTrue($this->app->make(SerproKillSwitchService::class)->isGlobalActive());

        $gate = $this->app->make(SerproProductionEgressGate::class);
        $eval = $gate->evaluateBillableEgress(route: SerproFunctionalRoute::Consultar);

        $this->assertFalse($eval['allowed']);
        $this->assertSame('KILL_SWITCH', $eval['code']);
    }
}
