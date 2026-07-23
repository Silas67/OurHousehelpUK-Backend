<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Render terminates TLS at its edge and forwards HTTP internally.
        // Without trusting the proxy, Laravel thinks requests are HTTP and
        // generates http:// URLs (e.g. profile_photo_url), which the HTTPS
        // web app then blocks as mixed content. Trust the proxy so generated
        // URLs use https.
        $middleware->trustProxies(at: '*');

        $middleware->api(prepend: [
            \Illuminate\Http\Middleware\HandleCors::class,
        ]);

        $middleware->alias([
            'account_type' => \App\Http\Middleware\RequireAccountType::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
