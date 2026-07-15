<?php

namespace Tests\Unit\Serpro;

use App\Enums\SerproAuthorizationStatus;
use App\Enums\SerproEnvironment;
use App\Enums\TaxProxyPowerSource;
use App\Enums\TaxProxyPowerStatus;
use App\Models\Client;
use App\Models\Office;
use App\Models\OfficeSerproAuthorization;
use App\Models\SerproContract;
use App\Models\TaxProxyPower;
use App\Models\User;
use App\Enums\OfficeRole;
use App\Enums\SerproContractStatus;
use App\Services\Integra\IntegraEligibilityService;
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

        $office = Office::factory()->create();
        $user = User::factory()->forOffice($office, OfficeRole::Admin)->create();
        $client = Client::factory()->forOffice($office)->create();

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
            'author_identity' => '12345678901',
            'termo_vault_object_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            'termo_valid_to' => now()->addYear(),
            'procurador_token_vault_object_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            'procurador_token_expires_at' => now()->addHours(6),
        ]);

        TaxProxyPower::query()->create([
            'office_id' => $office->id,
            'client_id' => $client->id,
            'office_serpro_authorization_id' => $auth->id,
            'author_identity' => '12345678901',
            'contributor_cnpj' => '11222333000181',
            'system_code' => 'INTEGRA_SN',
            'service_code' => 'PGDASD',
            'power_code' => 'PGDASD',
            'source' => TaxProxyPowerSource::ManualOfficialEvidence,
            'status' => TaxProxyPowerStatus::Active,
            'valid_from' => now()->subDay(),
            'valid_to' => now()->addYear(),
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
    }

    public function test_poder_insuficiente_bloqueia(): void
    {
        config([
            'features.global_enabled' => true,
            'features.modules.simples_mei.enabled' => true,
            'features.modules.simples_mei.allow_all_offices' => true,
        ]);

        $office = Office::factory()->create();
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
            'author_identity' => '12345678901',
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
        $this->assertContains('PROXY_POWER_MISSING', $result->toArray()['codes']);
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

        $office = Office::factory()->create();
        $user = User::factory()->forOffice($office, OfficeRole::Admin)->create();
        $client = Client::factory()->forOffice($office)->create();

        \App\Models\OfficeSubscription::query()->where('office_id', $office->id)->update([
            'monthly_api_quota' => 0,
        ]);

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
            'author_identity' => '12345678901',
            'termo_vault_object_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            'termo_valid_to' => now()->addYear(),
            'procurador_token_vault_object_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            'procurador_token_expires_at' => now()->addHours(6),
        ]);

        TaxProxyPower::query()->create([
            'office_id' => $office->id,
            'client_id' => $client->id,
            'office_serpro_authorization_id' => $auth->id,
            'author_identity' => '12345678901',
            'contributor_cnpj' => '11222333000181',
            'system_code' => 'INTEGRA_SN',
            'service_code' => 'PGDASD',
            'power_code' => 'PGDASD',
            'source' => TaxProxyPowerSource::ManualOfficialEvidence,
            'status' => TaxProxyPowerStatus::Active,
            'valid_from' => now()->subDay(),
            'valid_to' => now()->addYear(),
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
}
