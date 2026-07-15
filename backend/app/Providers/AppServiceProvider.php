<?php

namespace App\Providers;

use App\Contracts\AdnContributorClient;
use App\Contracts\CnpjRegistrationLookup;
use App\Contracts\MaOutboundXmlRetrievalClient;
use App\Contracts\OutboundXmlCaptureCapacityPlanner;
use App\Contracts\PfxReaderInterface;
use App\Contracts\SecureObjectStore;
use App\Contracts\SvrsNfceDownloadResponseParser as SvrsNfceDownloadResponseParserContract;
use App\Contracts\SvrsNfceOutboundXmlRetrievalClient;
use App\Contracts\SvrsNfe55OutboundXmlRetrievalClient;
use App\Contracts\SvrsPortalEgressGovernor;
use App\Contracts\SefazCteDistDfeClient;
use App\Contracts\SefazDistDfeClient;
use App\Contracts\SefazNfeManifestationClient;
use App\Contracts\SefazOutboundInutilizationClient;
use App\Contracts\SefazOutboundMutatingProbeClient;
use App\Contracts\SefazOutboundProtocolQueryClient;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\ClientCredential;
use App\Models\Establishment;
use App\Models\OfficeCredential;
use App\Models\OfficeFiscalIdentity;
use App\Models\OutboundCaptureProfile;
use App\Policies\ClientContactPolicy;
use App\Policies\ClientCredentialPolicy;
use App\Policies\ClientPolicy;
use App\Policies\EstablishmentPolicy;
use App\Policies\OfficeFiscalCredentialPolicy;
use App\Policies\OutboundCaptureProfilePolicy;
use App\Services\Adn\CurlMtlsTransport;
use App\Services\Adn\HttpAdnContributorClient;
use App\Services\Certificates\PfxReader;
use App\Services\Outbound\DisabledMaOutboundXmlRetrievalClient;
use App\Services\Outbound\DisabledSefazOutboundInutilizationClient;
use App\Services\Outbound\DisabledSefazOutboundMutatingProbeClient;
use App\Services\Outbound\DisabledSvrsNfceOutboundXmlRetrievalClient;
use App\Services\Outbound\DisabledSvrsNfe55OutboundXmlRetrievalClient;
use App\Services\Outbound\HttpSvrsNfe55OutboundXmlRetrievalClient;
use App\Services\Outbound\SvrsNfe55KillSwitchService;
use App\Services\Outbound\HttpSefazOutboundProtocolQueryClient;
use App\Services\Outbound\HttpSvrsNfceOutboundXmlRetrievalClient;
use App\Services\Outbound\ProtocolQueryResponseParser;
use App\Services\Outbound\RedisSvrsPortalEgressGovernor;
use App\Services\Outbound\SvrsNfceConfig;
use App\Services\Outbound\SvrsNfceDownloadResponseParser;
use App\Services\Outbound\SvrsNfceKillSwitchService;
use App\Services\Outbound\SvrsNfe55Config;
use App\Services\Outbound\SvrsPortalEgressConfig;
use App\Services\Sefaz\DistDfeResponseParser;
use App\Services\Sefaz\HttpSefazCteDistDfeClient;
use App\Services\Sefaz\HttpSefazDistDfeClient;
use App\Services\Sefaz\HttpSefazNfeManifestationClient;
use App\Services\Sefaz\ManifestationResponseParser;
use App\Services\Clients\CnpjWsRegistrationLookup;
use App\Services\Vault\EnvelopeCrypto;
use App\Services\Vault\FilesystemSecureObjectStore;
use App\Support\CurrentOffice;
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
    }

    public function boot(): void
    {
        Gate::policy(Client::class, ClientPolicy::class);
        Gate::policy(Establishment::class, EstablishmentPolicy::class);
        Gate::policy(ClientCredential::class, ClientCredentialPolicy::class);
        Gate::policy(ClientContact::class, ClientContactPolicy::class);
        Gate::policy(OutboundCaptureProfile::class, OutboundCaptureProfilePolicy::class);
        Gate::policy(OfficeFiscalIdentity::class, OfficeFiscalCredentialPolicy::class);
        Gate::policy(OfficeCredential::class, OfficeFiscalCredentialPolicy::class);
    }
}
