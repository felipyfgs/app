<?php

namespace Tests\Unit\Serpro;

use App\Enums\SerproConsumptionClass;
use App\Models\SerproPriceVersion;
use App\Services\Serpro\Usage\BillingCycleResolver;
use App\Services\Serpro\Usage\ContractPriceTableImporter;
use App\Services\Serpro\Usage\PriceCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

final class SerproBillingCycleAndPriceTest extends TestCase
{
    use RefreshDatabase;

    public function test_ciclo_21_20_separado_do_mes_calendario(): void
    {
        $resolver = app(BillingCycleResolver::class);

        // 10 de julho → ciclo 21/jun a 20/jul
        $july10 = Carbon::parse('2026-07-10', 'America/Sao_Paulo');
        $c = $resolver->resolve($july10);
        $this->assertSame('2026-06-21', $c['period_start']->toDateString());
        $this->assertSame('2026-07-20', $c['period_end']->toDateString());
        $this->assertStringContainsString('D21D20-', $c['cycle_code']);

        // 21 de julho → ciclo 21/jul a 20/ago
        $july21 = Carbon::parse('2026-07-21', 'America/Sao_Paulo');
        $c2 = $resolver->resolve($july21);
        $this->assertSame('2026-07-21', $c2['period_start']->toDateString());
        $this->assertSame('2026-08-20', $c2['period_end']->toDateString());
        $this->assertNotSame($c['cycle_code'], $c2['cycle_code']);

        // Mês calendário do fim do ciclo
        $cal = $resolver->calendarMonthOfCycleEnd($july10);
        $this->assertSame(2026, $cal['year']);
        $this->assertSame(7, $cal['month']);

        $persisted = $resolver->ensurePersisted($july10);
        $this->assertSame($c['cycle_code'], $persisted->cycle_code);
        $this->assertTrue($persisted->contains($july10));
    }

    public function test_importa_faixas_contratuais_e_retira_shadow_da_producao(): void
    {
        $shadow = SerproPriceVersion::query()->where('version_code', 'v1-shadow-2026')->first();
        $this->assertNotNull($shadow);

        $importer = app(ContractPriceTableImporter::class);
        $result = $importer->importFromFile();

        $this->assertGreaterThanOrEqual(4, $result['tiers_imported']);
        $this->assertSame('contract-approved-v1-2026', $result['version_code']);

        $shadow->refresh();
        $this->assertFalse((bool) $shadow->authorizes_production);
        $this->assertSame('SHADOW', $shadow->eligibility);

        $prod = SerproPriceVersion::query()->where('version_code', 'contract-approved-v1-2026')->firstOrFail();
        $this->assertTrue($prod->authorizesProductiveEgress());
        $this->assertNotNull($prod->source_hash);
        $this->assertSame('D21_D20', $prod->billing_cycle_kind);

        // Production-only resolve não usa shadow
        config([
            'serpro_usage.shadow_mode' => false,
            'serpro_usage.commercial_blocking_enabled' => true,
            'serpro_usage.require_production_price_table' => true,
        ]);
        $calc = app(PriceCalculator::class);
        $version = $calc->resolveVersion(now(), productionOnly: true);
        $this->assertNotNull($version);
        $this->assertTrue($version->authorizesProductiveEgress());
        $this->assertNotSame('v1-shadow-2026', $version->version_code);

        $estimate = $calc->estimate(SerproConsumptionClass::Consulta, quantity: 1);
        $this->assertFalse($estimate['price_unknown']);
        $this->assertSame(100_000, $estimate['estimated_cost_micros']);
    }
}
