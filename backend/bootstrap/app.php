<?php

use App\Http\Middleware\EnsureAdminTwoFactor;
use App\Http\Middleware\EnsureOfficeContext;
use App\Http\Middleware\EnsureOfficeRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();

        // SPA: não há rota nomeada `login` no Laravel; API não autenticada deve responder 401.
        $middleware->redirectGuestsTo(fn (Request $request) => null);

        $middleware->alias([
            'office' => EnsureOfficeContext::class,
            'office.role' => EnsureOfficeRole::class,
            'admin.2fa' => EnsureAdminTwoFactor::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
