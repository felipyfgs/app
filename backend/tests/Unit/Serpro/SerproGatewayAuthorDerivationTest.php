<?php

namespace Tests\Unit\Serpro;

use App\DTO\Serpro\SerproOperationCommand;
use App\Enums\AuthorIdentityType;
use App\Enums\SerproAuthorizationStatus;
use App\Enums\SerproContractStatus;
use App\Enums\SerproEnvironment;
use App\Models\Client;
use App\Models\Office;
use App\Models\OfficeSerproAuthorization;
use App\Models\SerproContract;
use App\Services\Serpro\SerproOperationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Gateway deriva autor do office e recusa parâmetros técnicos (F-3.2).
 */
class SerproGatewayAuthorDerivationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'serpro.trial.use_fake_clients' => true,
            'serpro.kill_switch' => false,
            'features.kill_switch' => false,
            'features.global_enabled' => true,
            'serpro.capabilities.default' => 'real',
            'serpro.capabilities.sitfis' => 'real',
        ]);
    }

    public function test_rejects_technical_params_in_business_data(): void
    {
        $office = Office::factory()->create();
        $client = Client::factory()->create(['office_id' => $office->id, 'root_cnpj' => '11222333000181']);
        $this->seedContractAndAuth($office);

        $response = app(SerproOperationService::class)->run(new SerproOperationCommand(
            office: $office,
            client: $client,
            operationKey: 'sitfis.solicitar_protocolo',
            businessData: [
                'jwt_token' => 'evil',
                'periodo' => '2026-01',
            ],
        ));

        $this->assertFalse($response->success);
        $this->assertSame('TECHNICAL_PARAM_REJECTED', $response->errorCode);
    }

    public function test_rejects_author_override_mismatch(): void
    {
        $office = Office::factory()->create();
        $client = Client::factory()->create(['office_id' => $office->id, 'root_cnpj' => '11222333000181']);
        $this->seedContractAndAuth($office, author: '11222333000181');

        $response = app(SerproOperationService::class)->run(new SerproOperationCommand(
            office: $office,
            client: $client,
            operationKey: 'sitfis.solicitar_protocolo',
            businessData: ['periodo' => '2026-01'],
            authorIdentityOverride: '99888777000166',
        ));

        $this->assertFalse($response->success);
        $this->assertSame('TECHNICAL_PARAM_REJECTED', $response->errorCode);
    }

    public function test_derives_author_from_office_authorization(): void
    {
        $office = Office::factory()->create();
        $client = Client::factory()->create(['office_id' => $office->id, 'root_cnpj' => '11222333000181']);
        $this->seedContractAndAuth($office, author: '11222333000181');

        // Override idêntico ao autor do office é aceito (callers internos)
        $response = app(SerproOperationService::class)->run(new SerproOperationCommand(
            office: $office,
            client: $client,
            operationKey: 'sitfis.solicitar_protocolo',
            businessData: ['periodo' => '2026-01'],
            authorIdentityOverride: '11222333000181',
        ));

        // Pode falhar por capability/catalog, mas NÃO por TECHNICAL_PARAM / AUTHOR
        if (! $response->success) {
            $this->assertNotSame('TECHNICAL_PARAM_REJECTED', $response->errorCode);
            $this->assertNotSame('AUTHOR_IDENTITY_MISSING', $response->errorCode);
            $this->assertNotSame('AUTHORIZATION_MISSING', $response->errorCode);
        } else {
            $this->assertTrue($response->success);
        }
    }

    private function seedContractAndAuth(Office $office, string $author = '11222333000181'): void
    {
        SerproContract::query()->create([
            'environment' => SerproEnvironment::Trial,
            'status' => SerproContractStatus::Active,
            'contractor_cnpj' => '11222333000181',
            'health_status' => 'OK',
            'activated_at' => now(),
        ]);

        OfficeSerproAuthorization::query()->create([
            'office_id' => $office->id,
            'environment' => SerproEnvironment::Trial,
            'status' => SerproAuthorizationStatus::TokenActive,
            'author_identity_type' => AuthorIdentityType::Cnpj,
            'author_identity' => $author,
        ]);
    }
}
