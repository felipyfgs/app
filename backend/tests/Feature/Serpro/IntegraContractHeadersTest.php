<?php

namespace Tests\Feature\Serpro;

use App\Contracts\AutenticarProcuradorClient;
use App\Contracts\CaixaPostalClient;
use App\Contracts\DteIndicatorClient;
use App\Contracts\FiscalMutationTransport;
use App\Contracts\GuideEmissionClient;
use App\Contracts\IntegraContadorClient;
use App\Contracts\IntegraProcuracoesClient;
use App\Contracts\ParcelamentoSource;
use App\Contracts\SerproContractAuthenticator;
use App\DTO\Serpro\IntegraRequest;
use App\DTO\Serpro\ProcuracaoLookupRequest;
use App\DTO\Serpro\ProcuradorAuthRequest;
use App\Enums\TaxInstallmentModality;
use App\Services\Fiscal\Guides\SerproGuideEmissionClient;
use App\Services\Fiscal\Mutations\IntegraFiscalMutationTransport;
use App\Services\Integra\CapabilityAwareIntegraContadorClient;
use App\Services\Integra\DisabledAutenticarProcuradorClient;
use App\Services\Integra\DisabledIntegraProcuracoesClient;
use App\Services\Integra\HttpAutenticarProcuradorClient;
use App\Services\Integra\HttpIntegraProcuracoesClient;
use App\Services\Integra\Mailbox\SerproCaixaPostalClient;
use App\Services\Integra\Mailbox\SerproDteIndicatorClient;
use App\Services\Integra\Parcelamento\SerproParcelamentoSource;
use App\Services\Serpro\HttpSerproContractAuthenticator;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Contratos centrais sob o container real da aplicação, inclusive em testing.
 */
class IntegraContractHeadersTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        config([
            'serpro.capabilities.default' => 'disabled',
            'serpro.capabilities.autentica_procurador' => 'disabled',
            'serpro.capabilities.authorization' => 'disabled',
            'serpro.capabilities.simples_mei' => 'disabled',
            'serpro.capabilities.mailbox' => 'disabled',
            'serpro.capabilities.installments' => 'disabled',
            'serpro.capabilities.guides' => 'disabled',
        ]);
    }

    public function test_container_testing_mantem_bindings_reais_centrais(): void
    {
        $this->assertInstanceOf(
            HttpSerproContractAuthenticator::class,
            app(SerproContractAuthenticator::class),
        );
        $this->assertInstanceOf(
            CapabilityAwareIntegraContadorClient::class,
            app(IntegraContadorClient::class),
        );
        $this->assertInstanceOf(
            DisabledAutenticarProcuradorClient::class,
            app(AutenticarProcuradorClient::class),
        );
        $this->assertInstanceOf(
            DisabledIntegraProcuracoesClient::class,
            app(IntegraProcuracoesClient::class),
        );
    }

    public function test_gateway_disabled_falha_fechado_sem_http(): void
    {
        $response = app(IntegraContadorClient::class)->execute(new IntegraRequest(
            officeId: 1,
            clientId: 1,
            environment: 'TRIAL',
            contractorCnpj: '11222333000181',
            authorIdentity: '52998224725',
            contributorCnpj: '11222333000181',
            operationKey: 'pgdasd.consdeclaracao',
            correlationId: 'corr-disabled-1',
        ));

        $this->assertFalse($response->success);
        $this->assertFalse($response->simulated);
        $this->assertSame(503, $response->httpStatus);
        $this->assertSame('CAPABILITY_DISABLED', $response->errorCode);
        $this->assertSame('corr-disabled-1', $response->correlationId);
    }

    public function test_container_testing_mantem_bindings_reais_secundarios(): void
    {
        $this->assertInstanceOf(SerproCaixaPostalClient::class, app(CaixaPostalClient::class));
        $this->assertInstanceOf(SerproDteIndicatorClient::class, app(DteIndicatorClient::class));
        $this->assertInstanceOf(SerproParcelamentoSource::class, app(ParcelamentoSource::class));
        $this->assertInstanceOf(SerproGuideEmissionClient::class, app(GuideEmissionClient::class));
        $this->assertInstanceOf(IntegraFiscalMutationTransport::class, app(FiscalMutationTransport::class));
    }

    public function test_adapters_secundarios_disabled_falham_fechado_sem_http(): void
    {
        $mailbox = app(CaixaPostalClient::class)->listMessages();
        $dte = app(DteIndicatorClient::class)->getIndicator();
        $installments = app(ParcelamentoSource::class)->execute(TaxInstallmentModality::Parcsn, 'LISTAR');

        $this->assertFalse($mailbox->success);
        $this->assertSame('CAPABILITY_DISABLED', $mailbox->errorCode);
        $this->assertFalse($dte->success);
        $this->assertSame('CAPABILITY_DISABLED', $dte->errorCode);
        $this->assertFalse($installments['success']);
        $this->assertFalse($installments['simulated']);
        $this->assertSame('CAPABILITY_DISABLED', $installments['error_code']);
        $this->assertSame([], $installments['body']);
    }

    public function test_clientes_disabled_nao_criam_token_poder_ou_evidencia(): void
    {
        $procurador = app(AutenticarProcuradorClient::class)->authenticate(new ProcuradorAuthRequest(
            officeId: 1,
            environment: 'TRIAL',
            authorIdentity: '52998224725',
            termoXml: '<termo/>',
            contractorBearerToken: 'nao-utilizado',
        ));

        $this->assertFalse($procurador->success);
        $this->assertFalse($procurador->simulated);
        $this->assertNull($procurador->token);
        $this->assertSame('CAPABILITY_DISABLED', $procurador->errorCode);

        $procuracao = app(IntegraProcuracoesClient::class)->lookup(new ProcuracaoLookupRequest(
            officeId: 1,
            clientId: 1,
            environment: 'TRIAL',
            authorIdentity: '52998224725',
            contributorCnpj: '11222333000181',
        ));

        $this->assertFalse($procuracao->success);
        $this->assertFalse($procuracao->simulated);
        $this->assertSame([], $procuracao->powers);
        $this->assertNull($procuracao->evidenceRef);
        $this->assertSame('CAPABILITY_DISABLED', $procuracao->errorCode);
    }

    public function test_driver_real_resolve_somente_clientes_http_sem_executa_los(): void
    {
        config([
            'serpro.capabilities.autentica_procurador' => 'real',
            'serpro.capabilities.authorization' => 'real',
        ]);

        $this->assertInstanceOf(
            HttpAutenticarProcuradorClient::class,
            app(AutenticarProcuradorClient::class),
        );
        $this->assertInstanceOf(
            HttpIntegraProcuracoesClient::class,
            app(IntegraProcuracoesClient::class),
        );
    }
}
