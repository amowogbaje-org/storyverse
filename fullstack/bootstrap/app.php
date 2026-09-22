<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'admin' => \App\Http\Middleware\RequireAdmin::class,
            'author' => \App\Http\Middleware\RequireAuthor::class,
        ]);

        // Payment gateways POST here directly, not through a browser form,
        // so they can't send a CSRF token.
        $middleware->validateCsrfTokens(except: ['webhooks/*']);

        // Laravel's default 'auth' middleware looks for a route named
        // 'login' to redirect guests to - ours is 'auth.login.show', so
        // point it there explicitly instead.
        $middleware->redirectGuestsTo(fn () => route('auth.login.show'));

        // Same reasoning as the old backend: "which pages are slow" is
        // exactly what you don't know in advance.
        $middleware->append(\App\Http\Middleware\LogSlowRequests::class);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
