<?php

namespace Tests\Support;

use App\Contracts\EsocialEventClient;
use Illuminate\Support\ServiceProvider;
use Tests\Support\Fakes\FakeEsocialEventClient;

/**
 * Double eSocial opt-in para testes offline.
 *
 * Este provider pertence ao autoload-dev e jamais é registrado pelo
 * bootstrap da aplicação ou pelo TestCase base.
 */
final class EsocialTestDoubleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(FakeEsocialEventClient::class);
        $this->app->bind(EsocialEventClient::class, FakeEsocialEventClient::class);
    }
}
