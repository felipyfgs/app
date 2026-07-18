<?php

namespace Tests\Support;

use App\Contracts\AutenticarProcuradorClient;
use App\Contracts\CaixaPostalClient;
use App\Contracts\DteIndicatorClient;
use App\Contracts\FiscalMutationTransport;
use App\Contracts\GuideEmissionClient;
use App\Contracts\IntegraContadorClient;
use App\Contracts\IntegraProcuracoesClient;
use App\Contracts\ParcelamentoSource;
use App\Contracts\SerproContractAuthenticator;
use Illuminate\Support\ServiceProvider;
use Tests\Support\Fakes\FakeAutenticarProcuradorClient;
use Tests\Support\Fakes\FakeCaixaPostalClient;
use Tests\Support\Fakes\FakeDteIndicatorClient;
use Tests\Support\Fakes\FakeFiscalMutationTransport;
use Tests\Support\Fakes\FakeGuideEmissionClient;
use Tests\Support\Fakes\FakeIntegraContadorClient;
use Tests\Support\Fakes\FakeIntegraProcuracoesClient;
use Tests\Support\Fakes\FakeParcelamentoSource;
use Tests\Support\Fakes\FakeSerproContractAuthenticator;
use Tests\Support\Fakes\SimulatedIntegraContadorClient;

/**
 * Doubles SERPRO opt-in para testes offline.
 *
 * Este provider pertence ao autoload-dev e jamais é registrado pelo bootstrap
 * da aplicação ou pelo TestCase base. Cada teste que precisar destes doubles
 * deve registrá-lo explicitamente.
 */
final class SerproTestDoubleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(FakeSerproContractAuthenticator::class);
        $this->app->singleton(FakeIntegraContadorClient::class);
        $this->app->singleton(FakeAutenticarProcuradorClient::class);
        $this->app->singleton(FakeIntegraProcuracoesClient::class);
        $this->app->singleton(SimulatedIntegraContadorClient::class);
        $this->app->singleton(FakeCaixaPostalClient::class);
        $this->app->singleton(FakeDteIndicatorClient::class);
        $this->app->singleton(FakeGuideEmissionClient::class);
        $this->app->singleton(FakeFiscalMutationTransport::class);
        $this->app->singleton(FakeParcelamentoSource::class);

        $this->app->bind(SerproContractAuthenticator::class, FakeSerproContractAuthenticator::class);
        $this->app->bind(IntegraContadorClient::class, FakeIntegraContadorClient::class);
        $this->app->bind(AutenticarProcuradorClient::class, FakeAutenticarProcuradorClient::class);
        $this->app->bind(IntegraProcuracoesClient::class, FakeIntegraProcuracoesClient::class);
        $this->app->bind(CaixaPostalClient::class, FakeCaixaPostalClient::class);
        $this->app->bind(DteIndicatorClient::class, FakeDteIndicatorClient::class);
        $this->app->bind(GuideEmissionClient::class, FakeGuideEmissionClient::class);
        $this->app->bind(FiscalMutationTransport::class, FakeFiscalMutationTransport::class);
        $this->app->bind(ParcelamentoSource::class, FakeParcelamentoSource::class);
    }
}
