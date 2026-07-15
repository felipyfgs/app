<?php

namespace Tests\Unit\Integra;

use App\DTO\Fiscal\FiscalAdapterRequest;
use App\Enums\FiscalTrigger;
use App\Enums\SerproAuthorizationStatus;
use App\Enums\SerproContractStatus;
use App\Enums\SerproEnvironment;
use App\Models\Client;
use App\Models\FiscalMonitoringRun;
use App\Models\Office;
use App\Models\OfficeSerproAuthorization;
use App\Models\SerproContract;
use App\Services\Integra\Dctfweb\DctfwebCodes;
use App\Services\Integra\Dctfweb\DctfwebIntegraCaller;
use App\Services\Integra\FakeIntegraContadorClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DctfwebIntegraCallerGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_rejeita_fallback_autor_placeholder_sem_chamar_client(): void
    {
        config([
            'serpro.default_environment' => 'TRIAL',
            'serpro.trial.use_fake_clients' => true,
            'features.global_enabled' => true,
            'features.modules.dctfweb_mit.enabled' => true,
            'features.modules.dctfweb_mit.allow_all_offices' => true,
        ]);

        $office = Office::factory()->create();
        $client = Client::factory()->forOffice($office)->create();

        SerproContract::query()->create([
            'environment' => SerproEnvironment::Trial,
            'status' => SerproContractStatus::Active,
            'contractor_cnpj' => '11222333000181',
            'health_status' => 'OK',
        ]);

        // Placeholder de getOrCreate — deve ser rejeitado (não usar contratante).
        OfficeSerproAuthorization::query()->create([
            'office_id' => $office->id,
            'environment' => SerproEnvironment::Trial,
            'status' => SerproAuthorizationStatus::Draft,
            'author_identity_type' => 'CNPJ',
            'author_identity' => '00000000000000',
            'certificate_mode' => 'EXTERNAL_SIGNATURE',
        ]);

        $fake = app(FakeIntegraContadorClient::class);
        $fake->reset();

        $run = FiscalMonitoringRun::query()->create([
            'office_id' => $office->id,
            'client_id' => $client->id,
            'system_code' => DctfwebCodes::SYSTEM_DCTFWEB,
            'service_code' => DctfwebCodes::SERVICE_DCTFWEB,
            'operation_code' => DctfwebCodes::OP_CONSULTAR_RECIBO,
            'status' => 'QUEUED',
            'trigger' => FiscalTrigger::Manual->value,
            'idempotency_key' => 'dctf-gate-1',
            'correlation_id' => 'corr-dctf-gate-1',
        ]);

        $request = new FiscalAdapterRequest(
            office: $office,
            client: $client,
            run: $run,
            systemCode: DctfwebCodes::SYSTEM_DCTFWEB,
            serviceCode: DctfwebCodes::SERVICE_DCTFWEB,
            operationCode: DctfwebCodes::OP_CONSULTAR_RECIBO,
            trigger: FiscalTrigger::Manual,
        );

        $response = app(DctfwebIntegraCaller::class)->call(
            $request,
            DctfwebCodes::SYSTEM_DCTFWEB,
            DctfwebCodes::SERVICE_DCTFWEB,
            DctfwebCodes::OP_CONSULTAR_RECIBO,
        );

        $this->assertFalse($response->success);
        $this->assertSame('AUTHOR_IDENTITY_MISSING', $response->errorCode);
        $this->assertSame(0, $fake->calls);
    }

    public function test_sem_autorizacao_nao_usa_cnpj_contratante_como_autor(): void
    {
        config([
            'serpro.default_environment' => 'TRIAL',
            'features.global_enabled' => true,
            'features.modules.dctfweb_mit.enabled' => true,
            'features.modules.dctfweb_mit.allow_all_offices' => true,
        ]);

        $office = Office::factory()->create();
        $client = Client::factory()->forOffice($office)->create();

        SerproContract::query()->create([
            'environment' => SerproEnvironment::Trial,
            'status' => SerproContractStatus::Active,
            'contractor_cnpj' => '11222333000181',
            'health_status' => 'OK',
        ]);

        $fake = app(FakeIntegraContadorClient::class);
        $fake->reset();

        $run = FiscalMonitoringRun::query()->create([
            'office_id' => $office->id,
            'client_id' => $client->id,
            'system_code' => DctfwebCodes::SYSTEM_DCTFWEB,
            'service_code' => DctfwebCodes::SERVICE_DCTFWEB,
            'operation_code' => DctfwebCodes::OP_CONSULTAR_RECIBO,
            'status' => 'QUEUED',
            'trigger' => FiscalTrigger::Manual->value,
            'idempotency_key' => 'dctf-gate-2',
            'correlation_id' => 'corr-dctf-gate-2',
        ]);

        $request = new FiscalAdapterRequest(
            office: $office,
            client: $client,
            run: $run,
            systemCode: DctfwebCodes::SYSTEM_DCTFWEB,
            serviceCode: DctfwebCodes::SERVICE_DCTFWEB,
            operationCode: DctfwebCodes::OP_CONSULTAR_RECIBO,
            trigger: FiscalTrigger::Manual,
        );

        $response = app(DctfwebIntegraCaller::class)->call(
            $request,
            DctfwebCodes::SYSTEM_DCTFWEB,
            DctfwebCodes::SERVICE_DCTFWEB,
            DctfwebCodes::OP_CONSULTAR_RECIBO,
        );

        $this->assertFalse($response->success);
        $this->assertSame('AUTHORIZATION_MISSING', $response->errorCode);
        $this->assertSame(0, $fake->calls);
    }
}
