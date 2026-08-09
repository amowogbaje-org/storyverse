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
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'jwt.auth' => \App\Http\Middleware\JwtAuthenticate::class,
            'jwt.optional' => \App\Http\Middleware\JwtOptionalAuthenticate::class,
            'admin' => \App\Http\Middleware\RequireAdmin::class,
            'author_or_admin' => \App\Http\Middleware\RequireAuthorOrAdmin::class,
        ]);

        // Global, not route-specific - "which endpoints are slow" is exactly
        // the thing you don't know in advance. See LogSlowRequests for what
        // gets logged and logging.slow_request_threshold_ms for the cutoff.
        $middleware->append(\App\Http\Middleware\LogSlowRequests::class);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
