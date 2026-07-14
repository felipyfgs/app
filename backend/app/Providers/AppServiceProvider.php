<?php

namespace App\Providers;

use App\Contracts\AdnContributorClient;
use App\Contracts\SecureObjectStore;
use App\Models\Client;
use App\Models\ClientCredential;
use App\Models\Establishment;
use App\Policies\ClientCredentialPolicy;
use App\Policies\ClientPolicy;
use App\Policies\EstablishmentPolicy;
use App\Services\Adn\CurlMtlsTransport;
use App\Services\Adn\HttpAdnContributorClient;
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
    }

    public function boot(): void
    {
        Gate::policy(Client::class, ClientPolicy::class);
        Gate::policy(Establishment::class, EstablishmentPolicy::class);
        Gate::policy(ClientCredential::class, ClientCredentialPolicy::class);
    }
}
