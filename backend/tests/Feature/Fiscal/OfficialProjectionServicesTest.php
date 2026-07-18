<?php

namespace Tests\Feature\Fiscal;

use App\Contracts\SerproOperationExecutor;
use App\DTO\Serpro\IntegraResponse;
use App\Enums\AuthorCertificateMode;
use App\Enums\AuthorIdentityType;
use App\Enums\FiscalSourceProvenance;
use App\Enums\SerproAuthorizationStatus;
use App\Enums\SerproContractStatus;
use App\Enums\SerproEnvironment;
use App\Enums\TaxProxyPowerSource;
use App\Enums\TaxProxyPowerStatus;
use App\Models\Client;
use App\Models\Establishment;
use App\Models\FiscalRegistrationLink;
use App\Models\FiscalTaxProcess;
use App\Models\Office;
use App\Models\OfficeSerproAuthorization;
use App\Models\SerproContract;
use App\Models\TaxProxyPower;
use App\Services\Integra\Registrations\RegistrationLinkProjectionService;
use App\Services\Integra\TaxProcesses\TaxProcessProjectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\Support\Fakes\FakeIntegraContadorClient;
use Tests\Support\FakeSerproOperationExecutor;
use Tests\Support\SerproTestDoubleServiceProvider;
use Tests\TestCase;

final class OfficialProjectionServicesTest extends TestCase
{
    use RefreshDatabase;

    private Office $office;

    private Client $client;

    private FakeIntegraContadorClient $integra;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->register(SerproTestDoubleServiceProvider::class);
        $this->app->instance(
            SerproOperationExecutor::class,
            new FakeSerproOperationExecutor($this->app->make(FakeIntegraContadorClient::class)),
        );

        config([
            'serpro.default_environment' => SerproEnvironment::Trial->value,
            // O provider de teste é instalado explicitamente; o runtime só
            // exercita o ramo real e nunca aceita driver simulated.
            'serpro.capabilities.registrations' => 'real',
            'serpro.capabilities.tax_processes' => 'real',
            'serpro_usage.shadow_mode' => true,
            'serpro_usage.commercial_blocking_enabled' => false,
        ]);

        $this->office = Office::factory()->create();
        $this->client = Client::factory()->forOffice($this->office)->create(['root_cnpj' => '11222333']);
        Establishment::factory()->forClient($this->client, '11222333000181')->create();

        SerproContract::query()->create([
            'environment' => SerproEnvironment::Trial,
            'status' => SerproContractStatus::Active,
            'contractor_cnpj' => '11222333000181',
            'contractor_name' => 'Contrato teste',
            'health_status' => 'HEALTHY',
            'pfx_vault_object_id' => (string) Str::ulid(),
            'oauth_vault_object_id' => (string) Str::ulid(),
            'fingerprint_sha256' => hash('sha256', 'projection-test'),
            'cert_valid_from' => now()->subYear(),
            'cert_valid_to' => now()->addYear(),
            'activated_at' => now(),
        ]);
        $auth = OfficeSerproAuthorization::query()->create([
            'office_id' => $this->office->id,
            'environment' => SerproEnvironment::Trial,
            'status' => SerproAuthorizationStatus::TokenActive,
            'author_identity_type' => AuthorIdentityType::Cpf,
            'author_identity' => '52998224725',
            'certificate_mode' => AuthorCertificateMode::ExternalSignature,
        ]);

        // eprocesso.consultar_por_interessado exige poder e-CAC 00051 (manifesto oficial).
        TaxProxyPower::query()->create([
            'office_id' => $this->office->id,
            'client_id' => $this->client->id,
            'office_serpro_authorization_id' => $auth->id,
            'author_identity' => '52998224725',
            'contributor_cnpj' => '11222333000181',
            'system_code' => 'EPROCESSO',
            'service_code' => 'EPROCESSO',
            'power_code' => '00051',
            'source' => TaxProxyPowerSource::ManualOfficialEvidence,
            'status' => TaxProxyPowerStatus::Active,
            'valid_from' => now()->subDay(),
            'valid_to' => now()->addYear(),
        ]);

        $this->integra = app(FakeIntegraContadorClient::class);
        $this->integra->reset();
    }

    public function test_registration_refresh_paginates_and_uses_page_idempotency(): void
    {
        $this->integra->queue('', '', '', $this->ok([
            'cnpjs' => [
                ['cnpj' => '11222333000181', 'situacaoCadastral' => 'ATIVA'],
                ['cnpj' => '55666777000009', 'situacaoCadastral' => 'ATIVA'],
            ],
            'totalInThePage' => 2,
            'totalInTheDatabase' => 3,
            'lastCnpj' => '55666777000009',
        ]));
        $this->integra->queue('', '', '', $this->ok([
            'cnpjs' => [
                ['cnpj' => '99888777000100', 'situacaoCadastral' => 'BAIXADA'],
            ],
            'totalInThePage' => 1,
            'totalInTheDatabase' => 3,
            'lastCnpj' => '99888777000100',
        ]));

        $result = app(RegistrationLinkProjectionService::class)->refresh($this->office, $this->client, 'corr-pages');

        $this->assertTrue($result['success']);
        $this->assertSame(3, $result['count']);
        $this->assertCount(3, FiscalRegistrationLink::query()->get());
        $this->assertCount(2, $this->integra->history);
        $this->assertArrayNotHasKey('lastCnpj', $this->integra->history[0]->businessData);
        $this->assertSame('55666777000009', $this->integra->history[1]->businessData['lastCnpj']);
        $this->assertStringEndsWith(':page:1', (string) $this->integra->history[0]->idempotencyKey);
        $this->assertStringEndsWith(':page:2', (string) $this->integra->history[1]->idempotencyKey);
    }

    public function test_registration_refresh_honors_exclusive_lock(): void
    {
        $lock = Cache::lock("fiscal:reglinks:{$this->office->id}:{$this->client->id}", 120);
        $this->assertTrue($lock->get());
        try {
            $result = app(RegistrationLinkProjectionService::class)->refresh($this->office, $this->client);
            $this->assertFalse($result['success']);
            $this->assertSame('LOCK_BUSY', $result['error_code']);
            $this->assertSame(0, $this->integra->calls);
        } finally {
            $lock->release();
        }
    }

    public function test_registration_invalid_second_page_does_not_persist_partial_projection(): void
    {
        $this->integra->queue('', '', '', $this->ok([
            'cnpjs' => [['cnpj' => '11222333000181', 'situacaoCadastral' => 'ATIVA']],
            'totalInThePage' => 1,
            'totalInTheDatabase' => 2,
            'lastCnpj' => '11222333000181',
        ]));
        $this->integra->queue('', '', '', $this->ok(['links' => []]));

        $result = app(RegistrationLinkProjectionService::class)->refresh($this->office, $this->client);

        $this->assertFalse($result['success']);
        $this->assertSame('RESPONSE_LAYOUT_INVALID', $result['error_code']);
        $this->assertSame(0, FiscalRegistrationLink::query()->count());
    }

    public function test_tax_process_uses_numero_do_processo_and_rejects_missing_key(): void
    {
        $this->integra->queue('', '', '', $this->ok([
            'numeroDoProcesso' => '10200.000001/2026-10',
            'situacao' => 'ATIVO',
        ]));
        $valid = app(TaxProcessProjectionService::class)->refresh($this->office, $this->client, 'corr-tax');
        $this->assertTrue($valid['success']);
        $this->assertSame('10200.000001/2026-10', FiscalTaxProcess::query()->sole()->process_number);

        $this->travel(1)->minutes();
        $this->integra->queue('', '', '', $this->ok(['numero' => 'legacy']));
        $invalid = app(TaxProcessProjectionService::class)->refresh($this->office, $this->client, 'corr-tax-invalid');
        $this->assertFalse($invalid['success']);
        $this->assertSame('RESPONSE_LAYOUT_INVALID', $invalid['error_code']);
        $this->assertSame(1, FiscalTaxProcess::query()->count());
    }

    /** @param array<string, mixed> $dados */
    private function ok(array $dados): IntegraResponse
    {
        return new IntegraResponse(
            success: true,
            httpStatus: 200,
            body: [],
            simulated: false,
            sourceProvenance: FiscalSourceProvenance::SerproTrial->value,
            dados: $dados,
        );
    }
}
