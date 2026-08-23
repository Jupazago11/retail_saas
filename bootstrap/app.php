<?php

use App\Http\Middleware\EnsureActiveSubscription;
use App\Http\Middleware\EnsureCompanyContext;
use App\Http\Middleware\EnsureCompanyPermission;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\NormalizeMoneyInput;
use App\Http\Middleware\RequirePasswordChange;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

// El paso de build de Railway (railpack) crea y da permisos a storage/
// framework/* durante el build, pero esa etapa no sobrevive a la copia
// final hacia la imagen de runtime: en produccion el contenedor arrancaba
// sin storage/framework/cache en absoluto, lo que rompia cualquier cosa
// que necesitara escribir ahi (ej. AliasLoader::ensureFacadeExists() de
// Laravel, disparado por las subidas de archivo de Livewire — un tempnam()
// fallido ahi tumbaba con 500 cualquier pantalla con input de archivo).
// Se recrean aqui, antes de arrancar la aplicacion, para no depender de
// que el build de la plataforma las haya dejado en su sitio. El guard con
// is_dir() en la primera hace esto gratis despues del primer request.
$basePath = dirname(__DIR__);

if (! is_dir($basePath.'/storage/framework/cache/data')) {
    foreach ([
        $basePath.'/storage/framework/cache/data',
        $basePath.'/storage/framework/sessions',
        $basePath.'/storage/framework/testing',
        $basePath.'/storage/framework/views',
        $basePath.'/storage/logs',
        $basePath.'/storage/app/public',
        $basePath.'/storage/app/private',
        $basePath.'/bootstrap/cache',
    ] as $requiredDirectory) {
        if (! is_dir($requiredDirectory)) {
            @mkdir($requiredDirectory, 0775, true);
        }
    }
}

return Application::configure(basePath: $basePath)
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
            'company.context' => EnsureCompanyContext::class,
            'company.permission' => EnsureCompanyPermission::class,
            'subscription.active' => EnsureActiveSubscription::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
