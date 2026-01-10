<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: [
            __DIR__.'/../routes/web.php',
            __DIR__.'/../routes/roles/admin.php',
            __DIR__.'/../routes/roles/manager.php',
            __DIR__.'/../routes/roles/member.php',
            __DIR__.'/../routes/roles/treasurer.php',
            __DIR__.'/../routes/roles/loan.php',
            __DIR__.'/../routes/roles/secretary.php',
            // IMPORTANT TO REMOVE IN PROD
            __DIR__.'/../routes/dev/dev.php',
        ],
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // ...existing middleware registrations...
        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
