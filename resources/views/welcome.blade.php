<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ \App\Models\PlatformSetting::appName() }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-stone-950 text-stone-50 antialiased">
        <main class="relative overflow-hidden">
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_left,_rgba(245,158,11,0.28),_transparent_32%),radial-gradient(circle_at_80%_20%,_rgba(251,191,36,0.18),_transparent_25%),linear-gradient(135deg,_#111827_0%,_#1c1917_45%,_#292524_100%)]"></div>

            <div class="relative mx-auto flex min-h-screen max-w-7xl flex-col justify-center px-6 py-14 lg:px-10">
                <div class="grid items-center gap-12 lg:grid-cols-[1.15fr_0.85fr]">
                    <section class="space-y-8">
                        <div class="inline-flex items-center rounded-full border border-amber-400/40 bg-amber-300/10 px-4 py-1 text-sm font-semibold uppercase tracking-[0.26em] text-amber-200">
                            Multiempresa
                        </div>

                        <div class="space-y-5">
                            <h1 class="max-w-4xl text-4xl font-black tracking-tight text-white sm:text-5xl lg:text-6xl">
                                {{ \App\Models\PlatformSetting::appName() }} para tiendas, minimercados y operacion comercial diaria.
                            </h1>
                            <p class="max-w-3xl text-lg leading-8 text-stone-300">
                                Base del producto enfocada en autenticacion central, empresa activa, inventario, POS,
                                caja y crecimiento modular sobre Laravel, PostgreSQL y Livewire.
                            </p>
                        </div>

                        <div class="flex flex-wrap gap-4">
                            @auth
                                <a href="{{ route('dashboard') }}" class="inline-flex items-center rounded-lg bg-gradient-to-br from-blue-600 to-purple-600 px-5 py-3 text-sm font-semibold text-white shadow-lg shadow-blue-900/30 transition hover:from-blue-700 hover:to-purple-700">
                                    Entrar al dashboard
                                </a>
                            @else
                                <a href="{{ route('login') }}" class="inline-flex items-center rounded-lg bg-gradient-to-br from-blue-600 to-purple-600 px-5 py-3 text-sm font-semibold text-white shadow-lg shadow-blue-900/30 transition hover:from-blue-700 hover:to-purple-700">
                                    Iniciar sesion
                                </a>
                                <a href="{{ route('register') }}" class="inline-flex items-center rounded-lg border border-stone-600 px-5 py-3 text-sm font-semibold text-white transition hover:border-stone-400 hover:bg-white/5">
                                    Crear cuenta
                                </a>
                            @endauth
                        </div>

                        <div class="grid gap-4 sm:grid-cols-3">
                            <article class="rounded-xl border border-white/10 bg-white/5 p-5 backdrop-blur-sm">
                                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-amber-200">Auth</p>
                                <p class="mt-3 text-2xl font-bold text-white">Username</p>
                                <p class="mt-2 text-sm leading-6 text-stone-300">Acceso central con empresa activa y onboarding inicial.</p>
                            </article>
                            <article class="rounded-xl border border-white/10 bg-white/5 p-5 backdrop-blur-sm">
                                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-amber-200">Stack</p>
                                <p class="mt-3 text-2xl font-bold text-white">Livewire</p>
                                <p class="mt-2 text-sm leading-6 text-stone-300">UI reactiva sobre Blade sin SPA separada.</p>
                            </article>
                            <article class="rounded-xl border border-white/10 bg-white/5 p-5 backdrop-blur-sm">
                                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-amber-200">Base</p>
                                <p class="mt-3 text-2xl font-bold text-white">Tenant</p>
                                <p class="mt-2 text-sm leading-6 text-stone-300">Empresa, sucursal, bodega y caja principal automáticas.</p>
                            </article>
                        </div>
                    </section>

                    <aside class="rounded-[2rem] border border-amber-400/20 bg-black/25 p-6 shadow-2xl shadow-black/40 backdrop-blur-md">
                        <p class="text-sm font-semibold uppercase tracking-[0.18em] text-amber-200">Estado actual</p>
                        <div class="mt-6 space-y-4">
                            <div class="rounded-lg border border-white/10 bg-white/5 p-4">
                                <p class="text-sm text-gray-400">Fase tecnica</p>
                                <p class="mt-1 text-lg font-semibold text-white">Autenticacion y selector de empresa</p>
                            </div>
                            <div class="rounded-lg border border-white/10 bg-white/5 p-4">
                                <p class="text-sm text-gray-400">Servicios locales</p>
                                <p class="mt-1 text-lg font-semibold text-white">app, web, postgres, mailpit, queue, vite</p>
                            </div>
                            <div class="rounded-lg border border-white/10 bg-white/5 p-4">
                                <p class="text-sm text-gray-400">Siguiente bloque</p>
                                <p class="mt-1 text-lg font-semibold text-white">Roles, permisos y configuracion empresarial</p>
                            </div>
                        </div>
                    </aside>
                </div>
            </div>
        </main>
    </body>
</html>
