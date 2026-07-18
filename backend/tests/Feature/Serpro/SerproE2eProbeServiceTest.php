<?php

namespace Tests\Feature\Serpro;

use App\Contracts\IntegraContadorClient;
use App\DTO\Serpro\IntegraRequest;
use App\DTO\Serpro\IntegraResponse;
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
use App\Models\SerproContract;
use App\Models\TaxProxyPower;
use App\Models\User;
use App\Services\Integra\TaxProxyPowerService;
use App\Services\Serpro\E2e\SerproE2eProbeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Prova que o probe e2e aciona o executor shipped (não reimplementa HTTP).
 * Usa client programável in-process; o runner real do piloto usa SERPRO live.
 */
class SerproE2eProbeServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_probe_sitfis_solicitar_grava_artifact_e_classifica_sucesso(): void
    {
        config([
            'features.global_enabled' => true,
            'features.kill_switch' => false,
            'features.modules.sitfis.enabled' => true,
            'features.modules.sitfis.allow_all_offices' => true,
            'serpro.capabilities.sitfis' => 'real',
            'serpro.trial.use_fake_clients' => true,
            'serpro.default_environment' => 'TRIAL',
            'serpro.kill_switch' => false,
        ]);

        $office = Office::factory()->create();
        $client = Client::factory()->forOffice($office)->create();
        Establishment::factory()->create([
            'office_id' => $office->id,
            'client_id' => $client->id,
            'cnpj' => '11222333000181',
            'is_matrix' => true,
            'is_active' => true,
        ]);
        User::factory()->forOffice($office, OfficeRole::Admin)->withTwoFactorConfirmed()->create();

        SerproContract::query()->create([
            'environment' => SerproEnvironment::Trial,
            'status' => SerproContractStatus::Active,
            'contractor_cnpj' => '11222333000181',
            'contractor_name' => 'Trial',
            'health_status' => 'OK',
            'credentials_exposed' => false,
            'activated_at' => now(),
        ]);

        $auth = OfficeSerproAuthorization::query()->create([
            'office_id' => $office->id,
            'environment' => SerproEnvironment::Trial,
            'status' => SerproAuthorizationStatus::TokenActive,
            'author_identity_type' => 'CNPJ',
            'author_identity' => '11222333000181',
            'certificate_mode' => 'EXTERNAL_SIGNATURE',
            'termo_vault_object_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            'termo_valid_to' => now()->addYear(),
            'procurador_token_vault_object_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            'procurador_token_expires_at' => now()->addHours(6),
        ]);

        TaxProxyPower::query()->create([
            'office_id' => $office->id,
            'client_id' => $client->id,
            'office_serpro_authorization_id' => $auth->id,
            'environment' => SerproEnvironment::Trial->value,
            'author_identity' => '11222333000181',
            'contributor_cnpj' => '11222333000181',
            'system_code' => 'SITFIS',
            'service_code' => 'SITFIS',
            'power_code' => '00002',
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

        $this->app->instance(IntegraContadorClient::class, new class implements IntegraContadorClient
        {
            public function execute(IntegraRequest $request): IntegraResponse
            {
                return new IntegraResponse(
                    success: true,
                    httpStatus: 200,
                    body: [
                        'status' => 200,
                        'dados' => [
                            'protocoloRelatorio' => 'PROT-E2E-1',
                            'tempoEspera' => 4000,
                        ],
                    ],
                    simulated: false,
                    correlationId: $request->correlationId,
                    dados: [
                        'protocoloRelatorio' => 'PROT-E2E-1',
                        'tempoEspera' => 4000,
                    ],
                    operationKey: $request->operationKey,
                    sourceProvenance: 'SERPRO_REAL',
                );
            }
        });

        $dir = sys_get_temp_dir().'/serpro-e2e-probe-test-'.uniqid();
        $probe = app(SerproE2eProbeService::class);
        $result = $probe->probe($office, $client, 'sitfis.solicitar_protocolo', [], $dir);

        $this->assertTrue($result['evaluated']);
        $this->assertSame('sitfis.solicitar_protocolo', $result['operation_key']);
        $this->assertSame('PASS_BUSINESS', $result['classification']);
        $this->assertSame('PROT-E2E-1', $result['protocol_extracted']);
        $this->assertFileExists($result['artifact_path']);
        $this->assertFalse($result['simulated']);
    }
}
