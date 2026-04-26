<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: [
            __DIR__ . '/../routes/web.php',
            __DIR__ . '/../routes/roles/admin.php',
            __DIR__ . '/../routes/roles/manager.php',
            __DIR__ . '/../routes/roles/member.php',
            __DIR__ . '/../routes/roles/treasurer.php',
            __DIR__ . '/../routes/roles/loan.php',
            __DIR__ . '/../routes/roles/secretary.php',
        ],
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // ...existing middleware registrations...
        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            'spatie_role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'otp.verified' => \App\Http\Middleware\EnsureOtpVerified::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
