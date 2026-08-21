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
        // Railway (y cualquier PaaS similar: Heroku, Render, Fly) termina el
        // TLS en su borde y le reenvia la peticion al contenedor por HTTP
        // plano, con headers X-Forwarded-*. Sin confiar en ese proxy, Laravel
        // ve la conexion interna como "http" y genera TODAS las URLs
        // (assets, rutas, redirects) con http:// aunque el sitio publico sea
        // https:// — el navegador bloquea esos assets como "contenido
        // mixto" y la pagina carga sin CSS/JS. 'at' => '*' es seguro aqui
        // porque el unico que le puede hablar al contenedor es el borde de
        // Railway (no hay acceso publico directo que lo bypassee).
        $middleware->trustProxies(
            at: '*',
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO,
        );

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


