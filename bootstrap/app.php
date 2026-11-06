<?php

use App\Http\Middleware\CheckServiceAvailability;
use App\Http\Middleware\EnsureCurrentSession;
use App\Http\Middleware\EnsureOtpVerified;
use App\Http\Middleware\EnsureProfileCompleted;
use App\Http\Middleware\EnsurePublicLoginEnabled;
use App\Http\Middleware\EnsureSessionActivity;
use App\Http\Middleware\RoleMiddleware;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [EnsureCurrentSession::class, EnsureSessionActivity::class]);

        $middleware->trustProxies(
            at: '*',
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO
                | Request::HEADER_X_FORWARDED_PREFIX,
        );

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            'spatie_role' => Spatie\Permission\Middleware\RoleMiddleware::class,
            'public.login.enabled' => EnsurePublicLoginEnabled::class,
            'otp.verified' => EnsureOtpVerified::class,
            'service.availability' => CheckServiceAvailability::class,
            'profile.completed' => EnsureProfileCompleted::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(function ($request, $e) {
            return $request->expectsJson();
        });
        $exceptions->render(function (AuthenticationException $e, $request) {
            if (! $request->expectsJson()) {
                return redirect()->route('login_page');
            }
        });
    })->create();
