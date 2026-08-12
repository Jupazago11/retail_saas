<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ \App\Models\PlatformSetting::appName() }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-white text-gray-900 antialiased">
        <main class="relative overflow-hidden">
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_left,_rgba(37,99,235,0.08),_transparent_35%),radial-gradient(circle_at_80%_10%,_rgba(124,58,237,0.08),_transparent_30%)]"></div>

            <div class="relative mx-auto flex min-h-screen max-w-7xl flex-col justify-center px-6 py-14 lg:px-10">
                <div class="grid items-center gap-12 lg:grid-cols-[1.15fr_0.85fr]">
                    <section class="space-y-8">
                        <div class="inline-flex items-center rounded-full border border-blue-200 bg-blue-50 px-4 py-1 text-sm font-semibold uppercase tracking-[0.26em] text-blue-700">
                            Multiempresa
                        </div>

                        <div class="space-y-5">
                            <h1 class="max-w-4xl text-4xl font-black tracking-tight text-gray-900 sm:text-5xl lg:text-6xl">
                                {{ \App\Models\PlatformSetting::appName() }} para tiendas, minimercados y operacion comercial diaria.
                            </h1>
                            <p class="max-w-3xl text-lg leading-8 text-gray-600">
                                Base del producto enfocada en autenticacion central, empresa activa, inventario, POS,
                                caja y crecimiento modular sobre Laravel, PostgreSQL y Livewire.
                            </p>
                        </div>

                        <div class="flex flex-wrap gap-4">
                            @auth
                                <a href="{{ route('dashboard') }}" class="inline-flex items-center rounded-lg bg-gradient-to-br from-blue-600 to-purple-600 px-5 py-3 text-sm font-semibold text-white shadow-sm shadow-blue-600/20 transition hover:from-blue-700 hover:to-purple-700">
                                    Entrar al dashboard
                                </a>
                            @else
                                <a href="{{ route('login') }}" class="inline-flex items-center rounded-lg bg-gradient-to-br from-blue-600 to-purple-600 px-5 py-3 text-sm font-semibold text-white shadow-sm shadow-blue-600/20 transition hover:from-blue-700 hover:to-purple-700">
                                    Iniciar sesion
                                </a>
                                <a href="{{ route('register') }}" class="inline-flex items-center rounded-lg border border-gray-300 px-5 py-3 text-sm font-semibold text-gray-700 transition hover:border-gray-400 hover:bg-gray-50">
                                    Crear cuenta
                                </a>
                            @endauth
                        </div>

                        <div class="grid gap-4 sm:grid-cols-3">
                            <article class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-blue-600">Auth</p>
                                <p class="mt-3 text-2xl font-bold text-gray-900">Username</p>
                                <p class="mt-2 text-sm leading-6 text-gray-600">Acceso central con empresa activa y onboarding inicial.</p>
                            </article>
                            <article class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-blue-600">Stack</p>
                                <p class="mt-3 text-2xl font-bold text-gray-900">Livewire</p>
                                <p class="mt-2 text-sm leading-6 text-gray-600">UI reactiva sobre Blade sin SPA separada.</p>
                            </article>
                            <article class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-blue-600">Base</p>
                                <p class="mt-3 text-2xl font-bold text-gray-900">Tenant</p>
                                <p class="mt-2 text-sm leading-6 text-gray-600">Empresa, sucursal, bodega y caja principal automáticas.</p>
                            </article>
                        </div>
                    </section>

                    <aside class="rounded-[2rem] border border-gray-200 bg-gray-50 p-6 shadow-sm">
                        <p class="text-sm font-semibold uppercase tracking-[0.18em] text-blue-600">Estado actual</p>
                        <div class="mt-6 space-y-4">
                            <div class="rounded-lg border border-gray-200 bg-white p-4">
                                <p class="text-sm text-gray-500">Fase tecnica</p>
                                <p class="mt-1 text-lg font-semibold text-gray-900">Autenticacion y selector de empresa</p>
                            </div>
                            <div class="rounded-lg border border-gray-200 bg-white p-4">
                                <p class="text-sm text-gray-500">Servicios locales</p>
                                <p class="mt-1 text-lg font-semibold text-gray-900">app, web, postgres, mailpit, queue, vite</p>
                            </div>
                            <div class="rounded-lg border border-gray-200 bg-white p-4">
                                <p class="text-sm text-gray-500">Siguiente bloque</p>
                                <p class="mt-1 text-lg font-semibold text-gray-900">Roles, permisos y configuracion empresarial</p>
                            </div>
                        </div>
                    </aside>
                </div>
            </div>
        </main>
    </body>
</html>
