<?php

namespace App\Providers;

use App\Contracts\AdnContributorClient;
use App\Contracts\AutenticarProcuradorClient;
use App\Contracts\CaixaPostalClient;
use App\Contracts\CnpjRegistrationLookup;
use App\Contracts\CteXmlSignatureValidator;
use App\Contracts\DteIndicatorClient;
use App\Contracts\EsocialEventClient;
use App\Contracts\FiscalMutationTransport;
use App\Contracts\GuideEmissionClient;
use App\Contracts\IntegraContadorClient;
use App\Contracts\IntegraProcuracoesClient;
use App\Contracts\MaOutboundXmlRetrievalClient;
use App\Contracts\OutboundXmlCaptureCapacityPlanner;
use App\Contracts\PfxReaderInterface;
use App\Contracts\SecureObjectStore;
use App\Contracts\SefazCteDistDfeClient;
use App\Contracts\SefazDistDfeClient;
use App\Contracts\SefazNfeManifestationClient;
use App\Contracts\SefazOutboundInutilizationClient;
use App\Contracts\SefazOutboundMutatingProbeClient;
use App\Contracts\SefazOutboundProtocolQueryClient;
use App\Contracts\SerproContractAuthenticator;
use App\Contracts\SerproOperationExecutor;
use App\Contracts\SvrsNfceDownloadResponseParser as SvrsNfceDownloadResponseParserContract;
use App\Contracts\SvrsNfceOutboundXmlRetrievalClient;
use App\Contracts\SvrsNfe55OutboundXmlRetrievalClient;
use App\Contracts\SvrsPortalEgressGovernor;
use App\Contracts\TaxGuideEnrollment;
use App\Enums\SerproCapabilityDriver;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\ClientCredential;
use App\Models\Establishment;
use App\Models\OfficeCredential;
use App\Models\OfficeFiscalIdentity;
use App\Models\OperationalExport;
use App\Models\OperationalProcess;
use App\Models\OperationalTask;
use App\Models\OutboundCaptureProfile;
use App\Models\ProcessTemplate;
use App\Models\SavedListFilter;
use App\Models\User;
use App\Models\WorkDepartment;
use App\Policies\ClientContactPolicy;
use App\Policies\ClientCredentialPolicy;
use App\Policies\ClientPolicy;
use App\Policies\EstablishmentPolicy;
use App\Policies\OfficeFiscalCredentialPolicy;
use App\Policies\OfficeSettingsPolicy;
use App\Policies\OutboundCaptureProfilePolicy;
use App\Policies\SavedListFilterPolicy;
use App\Policies\Work\OperationalExportPolicy;
use App\Policies\Work\OperationalProcessPolicy;
use App\Policies\Work\OperationalTaskPolicy;
use App\Policies\Work\ProcessTemplatePolicy;
use App\Policies\Work\WorkDepartmentPolicy;
use App\Services\Adn\CurlMtlsTransport;
use App\Services\Adn\HttpAdnContributorClient;
use App\Services\Certificates\PfxReader;
use App\Services\Clients\CnpjWsRegistrationLookup;
use App\Services\Esocial\FakeEsocialEventClient;
use App\Services\Esocial\FgtsEsocialSourceAdapter;
use App\Services\Fiscal\Guides\FakeGuideEmissionClient;
use App\Services\Fiscal\Guides\SerproGuideEmissionClient;
use App\Services\Fiscal\Mutations\FakeFiscalMutationTransport;
use App\Services\Fiscal\Mutations\IntegraFiscalMutationTransport;
use App\Services\Fiscal\SimplesMei\DasGuideHookService;
use App\Services\Fiscal\SimplesMei\Pgdasd\PgdasdConsDeclaracao13Codec;
use App\Services\Fiscal\SimplesMei\Pgdasd\PgdasdDocumentCodecs;
use App\Services\Fiscal\SimplesMei\Pgdasd\PgdasdPostConsultService;
use App\Services\Fiscal\SimplesMei\RegimeApplicabilityService;
use App\Services\Fiscal\SimplesMei\SimplesMeiAdapter;
use App\Services\Fiscal\SimplesMei\SimplesMeiCatalog;
use App\Services\Fiscal\SimplesMei\SimplesMeiResponseMapper;
use App\Services\FiscalMonitoring\FiscalAdapterRegistry;
use App\Services\Integra\CapabilityAwareIntegraContadorClient;
use App\Services\Integra\ContributorCnpjResolver;
use App\Services\Integra\Dctfweb\DctfwebAdapterRegistrar;
use App\Services\Integra\DisabledAutenticarProcuradorClient;
use App\Services\Integra\FakeAutenticarProcuradorClient;
use App\Services\Integra\FakeIntegraContadorClient;
use App\Services\Integra\FakeIntegraProcuracoesClient;
use App\Services\Integra\HttpAutenticarProcuradorClient;
use App\Services\Integra\HttpIntegraProcuracoesClient;
use App\Services\Integra\IntegraEligibilityService;
use App\Services\Integra\Mailbox\CaixaPostalDetailAdapter;
use App\Services\Integra\Mailbox\CaixaPostalListAdapter;
use App\Services\Integra\Mailbox\DteIndicatorAdapter;
use App\Services\Integra\Mailbox\FakeCaixaPostalClient;
use App\Services\Integra\Mailbox\FakeDteIndicatorClient;
use App\Services\Integra\Mailbox\SerproCaixaPostalClient;
use App\Services\Integra\Mailbox\SerproDteIndicatorClient;
use App\Services\Integra\OfficeSerproAuthorizationService;
use App\Services\Integra\Parcelamento\FakeParcelamentoSource;
use App\Services\Integra\Parcelamento\ParcelamentoEmitDocumentAdapter;
use App\Services\Integra\Parcelamento\ParcelamentoMutatingAdapter;
use App\Services\Integra\Parcelamento\ParcelamentoReadAdapter;
use App\Services\Integra\Parcelamento\SerproParcelamentoSource;
use App\Services\Integra\Parcelamento\StubTaxGuideEnrollment;
use App\Services\Integra\SimulatedIntegraContadorClient;
use App\Services\Integra\Sitfis\SitfisSourceAdapter;
use App\Services\Outbound\DisabledMaOutboundXmlRetrievalClient;
use App\Services\Outbound\DisabledSefazOutboundInutilizationClient;
use App\Services\Outbound\DisabledSefazOutboundMutatingProbeClient;
use App\Services\Outbound\DisabledSvrsNfceOutboundXmlRetrievalClient;
use App\Services\Outbound\DisabledSvrsNfe55OutboundXmlRetrievalClient;
use App\Services\Outbound\HttpSefazOutboundProtocolQueryClient;
use App\Services\Outbound\HttpSvrsNfceOutboundXmlRetrievalClient;
use App\Services\Outbound\HttpSvrsNfe55OutboundXmlRetrievalClient;
use App\Services\Outbound\ProtocolQueryResponseParser;
use App\Services\Outbound\RedisSvrsPortalEgressGovernor;
use App\Services\Outbound\SvrsNfceConfig;
use App\Services\Outbound\SvrsNfceDownloadResponseParser;
use App\Services\Outbound\SvrsNfceKillSwitchService;
use App\Services\Outbound\SvrsNfe55Config;
use App\Services\Outbound\SvrsNfe55KillSwitchService;
use App\Services\Outbound\SvrsPortalEgressConfig;
use App\Services\Platform\OfficeSubscriptionGate;
use App\Services\Sefaz\DistDfeResponseParser;
use App\Services\Sefaz\HttpSefazCteDistDfeClient;
use App\Services\Sefaz\HttpSefazDistDfeClient;
use App\Services\Sefaz\HttpSefazNfeManifestationClient;
use App\Services\Sefaz\ManifestationResponseParser;
use App\Services\Sefaz\SpedCommonCteXmlSignatureValidator;
use App\Services\Serpro\CapabilityDriverResolver;
use App\Services\Serpro\Catalog\OfficialServiceCatalogImporter;
use App\Services\Serpro\Catalog\OfficialServiceCatalogManifest;
use App\Services\Serpro\Catalog\OperationCoordinateResolver;
use App\Services\Serpro\FakeSerproContractAuthenticator;
use App\Services\Serpro\HttpSerproContractAuthenticator;
use App\Services\Serpro\SerproContractService;
use App\Services\Serpro\SerproHttpTransport;
use App\Services\Serpro\SerproOperationService;
use App\Services\Serpro\SerproProductionBootGuard;
use App\Services\Vault\EnvelopeCrypto;
use App\Services\Vault\FilesystemSecureObjectStore;
use App\Support\CurrentOffice;
use App\Support\FiscalDataModel\PrivilegedOfficeContext;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(CurrentOffice::class, fn () => new CurrentOffice);

        $this->app->singleton(EnvelopeCrypto::class, function () {
            $masterKey = (string) config('vault.master_key', '');

            // Fail-fast fora de testing: sem chave efêmera (FPM/Horizon precisam da mesma master key)
            if ($masterKey === '' && ! $this->app->environment('testing')) {
                throw new \RuntimeException(
                    'VAULT_MASTER_KEY não configurada. Defina em backend/.env (32 bytes em base64).'
                );
            }

            if ($masterKey === '' && $this->app->environment('testing')) {
                config(['vault.master_key' => base64_encode(random_bytes(32))]);
            }

            return EnvelopeCrypto::fromConfig();
        });

        $this->app->singleton(SecureObjectStore::class, function ($app) {
            return new FilesystemSecureObjectStore(
                $app->make(EnvelopeCrypto::class),
                (string) config('vault.disk_root'),
            );
        });

        $this->app->singleton(CurlMtlsTransport::class, function () {
            return new CurlMtlsTransport(
                timeoutSeconds: (int) config('adn.timeout_seconds', 30),
                connectTimeoutSeconds: (int) config('adn.connect_timeout_seconds', 10),
                verifyTls: (bool) config('adn.verify_tls', true),
            );
        });

        $this->app->singleton(AdnContributorClient::class, function ($app) {
            return new HttpAdnContributorClient(
                $app->make(CurlMtlsTransport::class),
                (string) config('adn.base_url'),
            );
        });

        $this->app->singleton(DistDfeResponseParser::class);
        $this->app->singleton(SefazDistDfeClient::class, function ($app) {
            return new HttpSefazDistDfeClient(
                $app->make(CurlMtlsTransport::class),
                $app->make(DistDfeResponseParser::class),
            );
        });
        $this->app->singleton(SefazCteDistDfeClient::class, function ($app) {
            return new HttpSefazCteDistDfeClient(
                $app->make(CurlMtlsTransport::class),
                $app->make(DistDfeResponseParser::class),
            );
        });
        $this->app->singleton(CteXmlSignatureValidator::class, SpedCommonCteXmlSignatureValidator::class);

        $this->app->singleton(ManifestationResponseParser::class);
        $this->app->singleton(SefazNfeManifestationClient::class, function ($app) {
            return new HttpSefazNfeManifestationClient(
                $app->make(CurlMtlsTransport::class),
                $app->make(ManifestationResponseParser::class),
            );
        });

        $this->app->singleton(PfxReaderInterface::class, PfxReader::class);
        $this->app->singleton(CnpjRegistrationLookup::class, CnpjWsRegistrationLookup::class);
        $this->app->singleton(CnpjWsRegistrationLookup::class);

        // MA outbound — defaults seguros (M2M/mutação desabilitados; consulta HTTP real)
        $this->app->singleton(ProtocolQueryResponseParser::class);
        $this->app->singleton(SefazOutboundProtocolQueryClient::class, function ($app) {
            return new HttpSefazOutboundProtocolQueryClient(
                $app->make(CurlMtlsTransport::class),
                $app->make(ProtocolQueryResponseParser::class),
            );
        });
        $this->app->singleton(MaOutboundXmlRetrievalClient::class, DisabledMaOutboundXmlRetrievalClient::class);
        $this->app->singleton(SefazOutboundInutilizationClient::class, DisabledSefazOutboundInutilizationClient::class);
        $this->app->singleton(SefazOutboundMutatingProbeClient::class, DisabledSefazOutboundMutatingProbeClient::class);

        // SVRS portal egress (compartilhado NF-e 55 + NFC-e 65) — fail-closed
        $this->app->singleton(SvrsPortalEgressConfig::class);
        $this->app->singleton(SvrsPortalEgressGovernor::class, RedisSvrsPortalEgressGovernor::class);

        // Agendamento por prazo — capacidade lê o governador (sem PFX)
        $this->app->singleton(OutboundXmlCaptureCapacityPlanner::class, \App\Services\Outbound\OutboundXmlCaptureCapacityPlanner::class);

        // SVRS NFC-e XML retrieval — default disabled client unless flag on
        $this->app->singleton(SvrsNfceConfig::class);
        $this->app->singleton(SvrsNfceKillSwitchService::class);
        $this->app->singleton(SvrsNfceDownloadResponseParserContract::class, SvrsNfceDownloadResponseParser::class);
        $this->app->singleton(SvrsNfceDownloadResponseParser::class);
        // Factory por resolução (não singleton): flag pode mudar sem restart do worker
        $this->app->bind(SvrsNfceOutboundXmlRetrievalClient::class, function ($app) {
            if (! (bool) config('sefaz.svrs_nfce_xml.retrieval_enabled', false)) {
                return $app->make(DisabledSvrsNfceOutboundXmlRetrievalClient::class);
            }

            return $app->make(HttpSvrsNfceOutboundXmlRetrievalClient::class);
        });

        // SVRS NF-e 55 — default desabilitado até smoke G13
        $this->app->singleton(SvrsNfe55Config::class);
        $this->app->singleton(SvrsNfe55KillSwitchService::class);
        $this->app->bind(SvrsNfe55OutboundXmlRetrievalClient::class, function ($app) {
            if (! (bool) config('sefaz.svrs_nfe55_xml.retrieval_enabled', false)) {
                return $app->make(DisabledSvrsNfe55OutboundXmlRetrievalClient::class);
            }

            return $app->make(HttpSvrsNfe55OutboundXmlRetrievalClient::class);
        });

        // SERPRO / Integra Contador — catálogo, drivers e transporte oficial
        $this->app->singleton(SerproHttpTransport::class, function () {
            return new SerproHttpTransport(
                timeoutSeconds: (int) config('serpro.api.timeout_seconds', 60),
                connectTimeoutSeconds: (int) config('serpro.api.connect_timeout_seconds', 10),
                verifyTls: (bool) config('serpro.api.verify_tls', true),
            );
        });

        $this->app->singleton(OfficialServiceCatalogManifest::class);
        $this->app->singleton(OfficialServiceCatalogImporter::class);
        $this->app->singleton(OperationCoordinateResolver::class);
        $this->app->singleton(CapabilityDriverResolver::class);
        $this->app->singleton(SimulatedIntegraContadorClient::class);

        $this->app->bind(SerproContractAuthenticator::class, function ($app) {
            $useFake = $app->environment('testing')
                || (bool) config('serpro.trial.use_fake_clients', true);

            return $useFake
                ? $app->make(FakeSerproContractAuthenticator::class)
                : $app->make(HttpSerproContractAuthenticator::class);
        });

        // Fake Integra como singleton para testes enfileirarem respostas
        $this->app->singleton(FakeIntegraContadorClient::class);

        $this->app->bind(IntegraContadorClient::class, function ($app) {
            if ($app->environment('testing')) {
                return $app->make(FakeIntegraContadorClient::class);
            }

            return $app->make(CapabilityAwareIntegraContadorClient::class);
        });

        // Único entrypoint produtivo — adapters/jobs injetam isto, não o client HTTP.
        $this->app->singleton(SerproOperationExecutor::class, SerproOperationService::class);
        $this->app->singleton(SerproOperationService::class);

        $this->app->bind(AutenticarProcuradorClient::class, function ($app) {
            $useFake = $app->environment('testing')
                || (bool) config('serpro.trial.use_fake_clients', true);
            if ($useFake) {
                return $app->make(FakeAutenticarProcuradorClient::class);
            }

            $driver = $app->make(CapabilityDriverResolver::class)->forCapability('autentica_procurador');

            return match ($driver) {
                SerproCapabilityDriver::Disabled => $app->make(DisabledAutenticarProcuradorClient::class),
                SerproCapabilityDriver::Simulated => $app->make(FakeAutenticarProcuradorClient::class),
                SerproCapabilityDriver::Real => $app->make(HttpAutenticarProcuradorClient::class),
            };
        });
        $this->app->bind(IntegraProcuracoesClient::class, function ($app) {
            if ($app->environment('testing')) {
                return $app->make(FakeIntegraProcuracoesClient::class);
            }

            $driver = $app->make(CapabilityDriverResolver::class)->forCapability('authorization');

            return match ($driver) {
                SerproCapabilityDriver::Disabled, SerproCapabilityDriver::Simulated => $app->make(FakeIntegraProcuracoesClient::class),
                SerproCapabilityDriver::Real => $app->make(HttpIntegraProcuracoesClient::class),
            };
        });

        // Núcleo fiscal — registry de adapters (módulos filhos registram em boot de seus providers)
        $this->app->singleton(FiscalAdapterRegistry::class);

        // FGTS / eSocial — fake em testing e por padrão (sem HTTP/portal); M2M real futuro via bind
        $this->app->singleton(FakeEsocialEventClient::class);
        $this->app->bind(EsocialEventClient::class, FakeEsocialEventClient::class);

        // Caixa Postal / DTE — driver por capacidade (fake em testing / simulated)
        $this->app->singleton(FakeCaixaPostalClient::class);
        $this->app->singleton(FakeDteIndicatorClient::class);
        $this->app->bind(CaixaPostalClient::class, function ($app) {
            if ($app->environment('testing')) {
                return $app->make(FakeCaixaPostalClient::class);
            }

            return $app->make(SerproCaixaPostalClient::class);
        });
        $this->app->bind(DteIndicatorClient::class, function ($app) {
            if ($app->environment('testing')) {
                return $app->make(FakeDteIndicatorClient::class);
            }

            return $app->make(SerproDteIndicatorClient::class);
        });

        // Mutações fiscais — fake controlável em testing; Integra em demais ambientes
        $this->app->singleton(FakeFiscalMutationTransport::class);
        $this->app->bind(FiscalMutationTransport::class, function ($app) {
            if ($app->environment('testing')) {
                return $app->make(FakeFiscalMutationTransport::class);
            }

            return $app->make(IntegraFiscalMutationTransport::class);
        });

        // Guias fiscais — fake apenas em testes; demais ambientes falham fechado
        // no adapter SERPRO quando o driver da capacidade não for explicitamente real.
        $this->app->singleton(FakeGuideEmissionClient::class);
        $this->app->singleton(SerproGuideEmissionClient::class);
        $this->app->bind(GuideEmissionClient::class, function ($app) {
            if ($app->environment('testing')) {
                return $app->make(FakeGuideEmissionClient::class);
            }

            return $app->make(SerproGuideEmissionClient::class);
        });

        // Parcelamentos SN/MEI — driver por capacidade (fake em simulated/testing)
        $this->app->singleton(FakeParcelamentoSource::class);
        $this->app->singleton(SerproParcelamentoSource::class);
        $this->app->singleton(TaxGuideEnrollment::class, StubTaxGuideEnrollment::class);
        $this->app->singleton(ParcelamentoReadAdapter::class);
        $this->app->singleton(ParcelamentoEmitDocumentAdapter::class);
        $this->app->singleton(ParcelamentoMutatingAdapter::class);
    }

    public function boot(): void
    {
        $this->registerPrivilegedOfficeContextListeners();

        // Preflight: simulated proibido em production
        if ($this->app->environment('production')) {
            // Isolamento de tenant: fail-closed não desligável via env em produção.
            config(['fiscal_data_model.fail_closed_scopes' => true]);

            try {
                $this->app->make(CapabilityDriverResolver::class)->assertProductionSafe();
                $this->app->make(SerproProductionBootGuard::class)->assertSafeOrFail();
            } catch (\Throwable $e) {
                // Fail-closed no boot de produção
                throw $e;
            }
        }

        Gate::policy(Client::class, ClientPolicy::class);
        Gate::policy(Establishment::class, EstablishmentPolicy::class);
        Gate::policy(ClientCredential::class, ClientCredentialPolicy::class);
        Gate::policy(ClientContact::class, ClientContactPolicy::class);
        Gate::policy(OutboundCaptureProfile::class, OutboundCaptureProfilePolicy::class);
        Gate::policy(OfficeFiscalIdentity::class, OfficeFiscalCredentialPolicy::class);
        Gate::policy(OfficeCredential::class, OfficeFiscalCredentialPolicy::class);
        Gate::policy(SavedListFilter::class, SavedListFilterPolicy::class);
        Gate::define('office-settings.view', [OfficeSettingsPolicy::class, 'view']);
        Gate::define('office-settings.manage', [OfficeSettingsPolicy::class, 'manage']);

        // Módulo operacional (Work) — plano de dados tenant-scoped
        Gate::policy(WorkDepartment::class, WorkDepartmentPolicy::class);
        Gate::policy(ProcessTemplate::class, ProcessTemplatePolicy::class);
        Gate::policy(OperationalProcess::class, OperationalProcessPolicy::class);
        Gate::policy(OperationalTask::class, OperationalTaskPolicy::class);
        Gate::policy(OperationalExport::class, OperationalExportPolicy::class);

        // PLATFORM_ADMIN é global e separado dos papéis do tenant (ADMIN/OPERATOR/VIEWER).
        // NÃO concede leitura fiscal implícita.
        Gate::define('platform-admin', function (User $user): bool {
            return $user->is_active && $user->isPlatformAdmin();
        });

        // Mutações no office atual exigem assinatura operacional (TRIAL/ACTIVE/PAST_DUE).
        Gate::define('office-subscription-writable', function (User $user): bool {
            if (! $user->is_active) {
                return false;
            }

            return app(OfficeSubscriptionGate::class)->allowsMutations();
        });

        Gate::define('office-subscription-external', function (User $user): bool {
            if (! $user->is_active) {
                return false;
            }

            return app(OfficeSubscriptionGate::class)->allowsExternalCalls();
        });

        // Adapters de módulos fiscais no registry do núcleo
        $registry = $this->app->make(FiscalAdapterRegistry::class);
        $registry->register($this->app->make(SitfisSourceAdapter::class));
        $registry->register($this->app->make(FgtsEsocialSourceAdapter::class));
        $registry->register($this->app->make(CaixaPostalListAdapter::class));
        $registry->register($this->app->make(CaixaPostalDetailAdapter::class));
        $registry->register($this->app->make(DteIndicatorAdapter::class));

        // Integra-SN / Integra-MEI — um adapter por operação do catálogo
        foreach (SimplesMeiCatalog::all() as $def) {
            $registry->register(new SimplesMeiAdapter(
                definition: $def,
                eligibility: $this->app->make(IntegraEligibilityService::class),
                operations: $this->app->make(SerproOperationService::class),
                mapper: $this->app->make(SimplesMeiResponseMapper::class),
                contracts: $this->app->make(SerproContractService::class),
                authorizations: $this->app->make(OfficeSerproAuthorizationService::class),
                regimeApplicability: $this->app->make(RegimeApplicabilityService::class),
                dasGuideHook: $this->app->make(DasGuideHookService::class),
                contributors: $this->app->make(ContributorCnpjResolver::class),
                pgdasdCodec13: $this->app->make(PgdasdConsDeclaracao13Codec::class),
                pgdasdDocumentCodecs: $this->app->make(PgdasdDocumentCodecs::class),
                pgdasdPostConsult: $this->app->make(PgdasdPostConsultService::class),
            ));
        }

        // Integra-DCTFWeb / MIT (adapters somente-leitura + mutantes atrás de flags OFF)
        $this->app->make(DctfwebAdapterRegistrar::class)
            ->register($registry);

        // Integra-Parcelamento — modalidades SN/MEI (leitura + emissão assistida + mutantes OFF)
        $registry->register($this->app->make(ParcelamentoReadAdapter::class));
        $registry->register($this->app->make(ParcelamentoEmitDocumentAdapter::class));
        $registry->register($this->app->make(ParcelamentoMutatingAdapter::class));
    }

    /**
     * Jobs e console operam sem membership HTTP: abrem contexto privilegiado tipado
     * para não reativar o anti-padrão "scope nulo = todos os tenants".
     */
    private function registerPrivilegedOfficeContextListeners(): void
    {
        Event::listen(JobProcessing::class, function (JobProcessing $event): void {
            $name = $event->job->resolveName();
            PrivilegedOfficeContext::enter('queue:'.$name);
        });

        Event::listen(JobProcessed::class, function (): void {
            PrivilegedOfficeContext::leave();
        });

        Event::listen(JobFailed::class, function (): void {
            PrivilegedOfficeContext::leave();
        });

        // JobExceptionOccurred não faz leave: JobFailed/JobProcessed fecham o ciclo.
        // Leave duplo em drivers sync com jobs aninhados zera o depth cedo demais.

        Event::listen(CommandStarting::class, function (CommandStarting $event): void {
            $command = (string) ($event->command ?? 'unknown');
            // Jobs e console multi-tenant devem filtrar office_id explicitamente
            // mesmo com contexto privilegiado aberto.
            if (PrivilegedOfficeContext::isOpen()) {
                return;
            }
            PrivilegedOfficeContext::enter('console:'.$command);
        });

        Event::listen(CommandFinished::class, function (): void {
            if (PrivilegedOfficeContext::isOpen()) {
                PrivilegedOfficeContext::leave();
            }
        });
    }
}
