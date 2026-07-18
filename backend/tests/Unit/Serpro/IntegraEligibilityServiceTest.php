<?php

namespace Tests\Unit\Serpro;

use App\Enums\OfficeRole;
use App\Enums\SerproAuthorizationStatus;
use App\Enums\SerproContractStatus;
use App\Enums\SerproEnvironment;
use App\Enums\TaxProxyPowerSource;
use App\Enums\TaxProxyPowerStatus;
use App\Models\Client;
use App\Models\Establishment;
use App\Models\Office;
use App\Models\OfficeSerproAuthorization;
use App\Models\OfficeSubscription;
use App\Models\SerproContract;
use App\Models\SerproServiceCatalogEntry;
use App\Models\TaxProxyPower;
use App\Models\User;
use App\Services\Integra\IntegraEligibilityService;
use App\Services\Integra\TaxProxyPowerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IntegraEligibilityServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_elegivel_quando_cadeia_completa(): void
    {
        config([
            'features.global_enabled' => true,
            'features.kill_switch' => false,
            'features.modules.simples_mei.enabled' => true,
            'features.modules.simples_mei.allow_all_offices' => true,
            'serpro.kill_switch' => false,
        ]);

        [$office, $user, $client, $auth] = $this->seedEligibleChain();

        TaxProxyPower::query()->create([
            'office_id' => $office->id,
            'client_id' => $client->id,
            'office_serpro_authorization_id' => $auth->id,
            'environment' => SerproEnvironment::Trial->value,
            'author_identity' => '52998224725',
            'contributor_cnpj' => '11222333000181',
            'system_code' => 'INTEGRA_SN',
            'service_code' => 'PGDASD',
            'power_code' => 'PGDASD',
            'source' => TaxProxyPowerSource::ManualOfficialEvidence,
            'provenance' => TaxProxyPowerService::PROVENANCE_MANUAL_APPROVED,
            'segregation_class' => 'PRODUCTION',
            'status' => TaxProxyPowerStatus::Active,
            'valid_from' => now()->subDay(),
            'valid_to' => now()->addYear(),
            'accepted_at' => now()->subDay(),
            'freshness_checked_at' => now(),
            'verified_at' => now(),
        ]);

        $result = app(IntegraEligibilityService::class)->evaluate(
            $office,
            $client,
            'INTEGRA_SN',
            'PGDASD',
            'CONSULTAR_DECLARACAO',
            SerproEnvironment::Trial,
            $user,
            'simples_mei',
        );

        $this->assertTrue($result->eligible, json_encode($result->toArray()));
        $this->assertTrue($result->context['representation_chain']['complete'] ?? false);
    }

    public function test_poder_insuficiente_bloqueia(): void
    {
        config([
            'features.global_enabled' => true,
            'features.modules.simples_mei.enabled' => true,
            'features.modules.simples_mei.allow_all_offices' => true,
        ]);

        [$office, $user, $client] = $this->seedEligibleChain();

        $result = app(IntegraEligibilityService::class)->evaluate(
            $office,
            $client,
            'INTEGRA_SN',
            'PGDASD',
            'CONSULTAR_DECLARACAO',
            SerproEnvironment::Trial,
            $user,
            'simples_mei',
        );

        $this->assertFalse($result->eligible);
        $codes = $result->toArray()['codes'];
        $this->assertTrue(
            in_array('PROXY_POWER_MISSING', $codes, true)
            || in_array('PROXY_POWER_NOT_ACCEPTED', $codes, true)
            || in_array('PROXY_POWER_STALE', $codes, true),
            json_encode($codes)
        );
    }

    public function test_poder_alternativo_unico_basta_any_of(): void
    {
        config([
            'features.global_enabled' => true,
            'features.kill_switch' => false,
            'features.modules.parcelamentos.enabled' => true,
            'features.modules.parcelamentos.allow_all_offices' => true,
            'serpro.kill_switch' => false,
        ]);

        [$office, $user, $client, $auth] = $this->seedEligibleChain();

        // Só 00076; matriz PARCSN exige 00076|00188 (ANY-of).
        TaxProxyPower::query()->create([
            'office_id' => $office->id,
            'client_id' => $client->id,
            'office_serpro_authorization_id' => $auth->id,
            'environment' => SerproEnvironment::Trial->value,
            'author_identity' => '52998224725',
            'contributor_cnpj' => '11222333000181',
            'system_code' => 'PARCSN',
            'service_code' => 'PEDIDOSPARC163',
            'power_code' => '00076',
            'source' => TaxProxyPowerSource::ManualOfficialEvidence,
            'provenance' => TaxProxyPowerService::PROVENANCE_MANUAL_APPROVED,
            'segregation_class' => 'PRODUCTION',
            'status' => TaxProxyPowerStatus::Active,
            'valid_from' => now()->subDay(),
            'valid_to' => now()->addYear(),
            'accepted_at' => now()->subDay(),
            'freshness_checked_at' => now(),
            'verified_at' => now(),
        ]);

        SerproServiceCatalogEntry::query()->create([
            'environment' => SerproEnvironment::Trial->value,
            'solution_code' => 'PARCSN',
            'service_code' => 'PEDIDOSPARC163',
            'operation_code' => 'PEDIDOSPARC',
            'label' => 'Consultar pedidos PARCSN',
            'id_sistema' => 'PARCSN',
            'id_servico' => 'PEDIDOSPARC163',
            'is_enabled' => true,
            'is_mutating' => false,
            'coverage' => 'FULL',
            'catalog_version' => 1,
            'metadata' => ['required_proxy_powers' => ['00076', '00188']],
            'required_proxy_power' => '00076 00188',
            'billable_class' => 'CONSULTA',
            'official_state' => 'PRODUCTION',
            'platform_support' => 'IMPLEMENTED',
            'coverage' => 'KNOWN',
            'effective_from' => now(),
        ]);
        $result = app(IntegraEligibilityService::class)->evaluate(
            $office,
            $client,
            'PARCSN',
            'PEDIDOSPARC163',
            'PEDIDOSPARC',
            SerproEnvironment::Trial,
            $user,
            'parcelamentos',
        );

        $this->assertTrue($result->eligible, json_encode($result->toArray()));
    }

    public function test_orcamento_alinhado_ao_usage_budget_gate(): void
    {
        config([
            'features.global_enabled' => true,
            'features.kill_switch' => false,
            'features.modules.simples_mei.enabled' => true,
            'features.modules.simples_mei.allow_all_offices' => true,
            'serpro.kill_switch' => false,
            'serpro_usage.shadow_mode' => false,
            'serpro_usage.commercial_blocking_enabled' => true,
        ]);

        [$office, $user, $client, $auth] = $this->seedEligibleChain();

        OfficeSubscription::query()->where('office_id', $office->id)->update([
            'monthly_api_quota' => 0,
        ]);

        TaxProxyPower::query()->create([
            'office_id' => $office->id,
            'client_id' => $client->id,
            'office_serpro_authorization_id' => $auth->id,
            'environment' => SerproEnvironment::Trial->value,
            'author_identity' => '52998224725',
            'contributor_cnpj' => '11222333000181',
            'system_code' => 'INTEGRA_SN',
            'service_code' => 'PGDASD',
            'power_code' => 'PGDASD',
            'source' => TaxProxyPowerSource::ManualOfficialEvidence,
            'provenance' => TaxProxyPowerService::PROVENANCE_MANUAL_APPROVED,
            'segregation_class' => 'PRODUCTION',
            'status' => TaxProxyPowerStatus::Active,
            'valid_from' => now()->subDay(),
            'valid_to' => now()->addYear(),
            'accepted_at' => now()->subDay(),
            'freshness_checked_at' => now(),
            'verified_at' => now(),
        ]);

        $result = app(IntegraEligibilityService::class)->evaluate(
            $office,
            $client,
            'INTEGRA_SN',
            'PGDASD',
            'CONSULTAR_DECLARACAO',
            SerproEnvironment::Trial,
            $user,
            'simples_mei',
        );

        $this->assertFalse($result->eligible);
        $this->assertContains('BUDGET_EXCEEDED', $result->toArray()['codes']);
        $this->assertArrayHasKey('budget_used', $result->context);
        $this->assertArrayNotHasKey('global_used', $result->context);
    }

    public function test_cadeia_incompleta_sem_estabelecimento_bloqueia(): void
    {
        config([
            'features.global_enabled' => true,
            'features.modules.simples_mei.enabled' => true,
            'features.modules.simples_mei.allow_all_offices' => true,
        ]);

        $office = Office::factory()->create(['slug' => 'real-office']);
        $user = User::factory()->forOffice($office, OfficeRole::Admin)->create();
        $client = Client::factory()->forOffice($office)->create();

        SerproContract::query()->create([
            'environment' => SerproEnvironment::Trial,
            'status' => SerproContractStatus::Active,
            'contractor_cnpj' => '11222333000181',
            'health_status' => 'OK',
        ]);

        OfficeSerproAuthorization::query()->create([
            'office_id' => $office->id,
            'environment' => SerproEnvironment::Trial,
            'status' => SerproAuthorizationStatus::TokenActive,
            'author_identity_type' => 'CPF',
            'author_identity' => '52998224725',
            'termo_vault_object_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            'termo_valid_to' => now()->addYear(),
            'procurador_token_vault_object_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            'procurador_token_expires_at' => now()->addHours(6),
        ]);

        $result = app(IntegraEligibilityService::class)->evaluate(
            $office,
            $client,
            'INTEGRA_SN',
            'PGDASD',
            'CONSULTAR_DECLARACAO',
            SerproEnvironment::Trial,
            $user,
            'simples_mei',
        );

        $this->assertFalse($result->eligible);
        $this->assertContains('REPRESENTATION_CHAIN_INCOMPLETE', $result->toArray()['codes']);
    }

    public function test_free_smoke_bloqueia_obterprocuracao41(): void
    {
        config([
            'features.global_enabled' => true,
            'features.modules.simples_mei.enabled' => true,
            'features.modules.simples_mei.allow_all_offices' => true,
        ]);

        [$office, $user, $client] = $this->seedEligibleChain();

        $result = app(IntegraEligibilityService::class)->evaluate(
            $office,
            $client,
            'PROCURACOES',
            'OBTERPROCURACAO41',
            'OBTERPROCURACAO41',
            SerproEnvironment::Trial,
            $user,
            null,
            requireD1: false,
            freeSmokeMode: true,
        );

        $this->assertFalse($result->eligible);
        $this->assertContains('FREE_SMOKE_BILLABLE_BLOCKED', $result->toArray()['codes']);
    }

    public function test_trial_usa_catalogo_oficial_de_producao_quando_nao_ha_projecao_do_ambiente(): void
    {
        config([
            'features.global_enabled' => true,
            'features.kill_switch' => false,
            'serpro.kill_switch' => false,
        ]);

        [$office, $user, $client] = $this->seedEligibleChain();
        SerproServiceCatalogEntry::query()->create([
            'catalog_version' => 999,
            'environment' => SerproEnvironment::Production,
            'operation_key' => 'procuracoes.obter',
            'solution_code' => 'PROCURACOES',
            'service_code' => 'PROCURACOES',
            'operation_code' => 'OBTERPROCURACAO41',
            'id_sistema' => 'PROCURACOES',
            'id_servico' => 'OBTERPROCURACAO41',
            'label' => 'Obter Procuração',
            'is_mutating' => false,
            'is_enabled' => true,
            'billable_class' => 'CONSULTA',
            'coverage' => 'KNOWN',
            'effective_from' => now(),
        ]);

        $result = app(IntegraEligibilityService::class)->evaluate(
            $office,
            $client,
            'PROCURACOES',
            'OBTERPROCURACAO41',
            'OBTERPROCURACAO41',
            SerproEnvironment::Trial,
            $user,
        );

        $this->assertTrue($result->eligible, json_encode($result->toArray()));
    }

    public function test_demo_office_bloqueia_producao(): void
    {
        config([
            'features.global_enabled' => true,
            'features.modules.simples_mei.enabled' => true,
            'features.modules.simples_mei.allow_all_offices' => true,
            'fiscal_demo.office_slug' => 'demo',
        ]);

        $office = Office::factory()->create([
            'slug' => 'demo',
            'serpro_segregation_class' => 'DEMO',
        ]);
        $user = User::factory()->forOffice($office, OfficeRole::Admin)->create();
        $client = Client::factory()->forOffice($office)->create();
        Establishment::factory()->forClient($client, '11222333000181')->create();

        SerproContract::query()->create([
            'environment' => SerproEnvironment::Production,
            'status' => SerproContractStatus::Active,
            'contractor_cnpj' => '11222333000181',
            'health_status' => 'OK',
        ]);

        OfficeSerproAuthorization::query()->create([
            'office_id' => $office->id,
            'environment' => SerproEnvironment::Production,
            'status' => SerproAuthorizationStatus::TokenActive,
            'author_identity_type' => 'CPF',
            'author_identity' => '52998224725',
            'termo_vault_object_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            'termo_valid_to' => now()->addYear(),
            'procurador_token_vault_object_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            'procurador_token_expires_at' => now()->addHours(6),
        ]);

        $result = app(IntegraEligibilityService::class)->evaluate(
            $office,
            $client,
            'INTEGRA_SN',
            'PGDASD',
            'CONSULTAR_DECLARACAO',
            SerproEnvironment::Production,
            $user,
            'simples_mei',
        );

        $this->assertFalse($result->eligible);
        $this->assertContains('DEMO_OFFICE_BLOCKED', $result->toArray()['codes']);
    }

    /**
     * @return array{0: Office, 1: User, 2: Client, 3: OfficeSerproAuthorization}
     */
    private function seedEligibleChain(): array
    {
        $office = Office::factory()->create(['slug' => 'real-office-'.uniqid()]);
        $user = User::factory()->forOffice($office, OfficeRole::Admin)->create();
        $client = Client::factory()->forOffice($office)->create();
        Establishment::factory()->forClient($client, '11222333000181')->create();

        SerproContract::query()->create([
            'environment' => SerproEnvironment::Trial,
            'status' => SerproContractStatus::Active,
            'contractor_cnpj' => '11222333000181',
            'health_status' => 'OK',
        ]);

        $auth = OfficeSerproAuthorization::query()->create([
            'office_id' => $office->id,
            'environment' => SerproEnvironment::Trial,
            'status' => SerproAuthorizationStatus::TokenActive,
            'author_identity_type' => 'CPF',
            'author_identity' => '52998224725',
            'termo_vault_object_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            'termo_valid_to' => now()->addYear(),
            'procurador_token_vault_object_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            'procurador_token_expires_at' => now()->addHours(6),
        ]);

        return [$office, $user, $client, $auth];
    }
}
