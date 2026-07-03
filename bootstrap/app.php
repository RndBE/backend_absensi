<?php

use App\Http\Middleware\EmployeeSessionLifetime;
use App\Http\Middleware\TessaActor;
use App\Http\Middleware\TessaApiKey;
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
        $middleware->web(prepend: [
            EmployeeSessionLifetime::class,
        ]);

        $middleware->alias([
            'tessa.service' => TessaApiKey::class, // service key: /ping & /session (mint token)
            'tessa.actor' => TessaActor::class,    // token per-user: data & aksi (ikut role HRIS)
            'tessa.api' => TessaApiKey::class,      // alias lama (kompatibilitas)
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
