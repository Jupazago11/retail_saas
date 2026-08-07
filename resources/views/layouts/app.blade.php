<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        @php($launcherMode = request()->routeIs('dashboard'))

        <div x-data="{ sidebarOpen: false, sidebarCollapsed: false }" class="min-h-screen bg-stone-100 text-stone-900">
            <x-toast-stack />

            @if ($launcherMode)
                <div class="min-h-screen bg-[radial-gradient(circle_at_top,#fff2d8_0%,#f5f5f4_42%,#e7e5e4_100%)]">
                    <div class="border-b border-stone-200/80 bg-white/75 backdrop-blur">
    <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-3" aria-label="Ir al panel principal" title="Ir al panel principal">
            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-stone-950 text-white shadow-sm">
                <x-application-logo class="h-7 w-auto fill-current" />
            </div>
            <p class="text-xs font-semibold uppercase tracking-[0.3em] text-amber-700">Retail SaaS</p>
        </a>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="inline-flex items-center rounded-2xl border border-rose-200 bg-rose-50 px-4 py-2 text-sm font-semibold text-rose-700 transition hover:border-rose-300 hover:bg-rose-100">
                Cerrar sesion
            </button>
        </form>
    </div>
</div>

<main class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                        {{ $slot }}
                    </main>
                </div>
            @else
    <div class="min-h-screen bg-stone-100">
        <div class="border-b border-stone-200/80 bg-white/90 backdrop-blur">
            <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
                <a href="{{ route('dashboard') }}" class="flex items-center gap-3" aria-label="Ir al panel principal" title="Ir al panel principal">
                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-stone-950 text-white shadow-sm">
                        <x-application-logo class="h-7 w-auto fill-current" />
                    </div>
                    <p class="text-xs font-semibold uppercase tracking-[0.3em] text-amber-700">Retail SaaS</p>
                </a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="inline-flex items-center rounded-2xl border border-rose-200 bg-rose-50 px-4 py-2 text-sm font-semibold text-rose-700 transition hover:border-rose-300 hover:bg-rose-100">
                        Cerrar sesion
                    </button>
                </form>
            </div>
        </div>

        <main class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
            {{ $slot }}
        </main>
    </div>
@endif
        </div>
    </body>
</html>




