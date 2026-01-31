<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(\App\Http\Middleware\CorrelationIdMiddleware::class);
        $middleware->append(\App\Http\Middleware\TraceContextMiddleware::class);
        $middleware->alias([
            'api.key' => \App\Http\Middleware\ApiKeyAuth::class,
            'client.rate' => \App\Http\Middleware\ClientRateLimit::class,
            'admin.basic' => \App\Http\Middleware\AdminBasicAuth::class,
            'admin.session' => \App\Http\Middleware\AdminSessionAuth::class,
            'admin.role' => \App\Http\Middleware\AdminRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
