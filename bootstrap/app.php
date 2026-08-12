<?php

use App\Http\Middleware\EnsureCompanyContext;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\NormalizeMoneyInput;
use App\Http\Middleware\RequirePasswordChange;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            NormalizeMoneyInput::class,
            EnsureUserIsActive::class,
            RequirePasswordChange::class,
        ]);

        $middleware->alias([
            'company.context'      => EnsureCompanyContext::class,
            'company.permission'   => \App\Http\Middleware\EnsureCompanyPermission::class,
            'subscription.active'  => \App\Http\Middleware\EnsureActiveSubscription::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();


