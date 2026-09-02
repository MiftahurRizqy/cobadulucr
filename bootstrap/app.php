<?php

use App\Http\Middleware\EnsureActiveUser;
use App\Http\Middleware\NormalizeMoneyInput;
use App\Http\Middleware\RequirePermission;
use App\Http\Middleware\IdentifyTenant;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [NormalizeMoneyInput::class]);
        $middleware->alias([
            'active' => EnsureActiveUser::class,
            'permission' => RequirePermission::class,
            'tenant' => IdentifyTenant::class,
        ]);
        // Tenant harus dipilih sebelum guard memuat user dan sebelum route
        // model binding mencari model seperti /users/{user}.
        $middleware->prependToPriorityList(AuthenticatesRequests::class, IdentifyTenant::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash(['admin_password', 'admin_password_confirmation']);
    })->create();
