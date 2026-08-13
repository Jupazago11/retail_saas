<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ \App\Models\PlatformSetting::appName() }}</title>

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body class="font-sans antialiased">
        @php($launcherMode = request()->routeIs('dashboard'))

        <div x-data="{ sidebarOpen: false, sidebarCollapsed: false }" class="min-h-screen bg-gray-100 text-gray-900">
            <x-toast-stack />

            {{-- Icono de inicio siempre visible y por encima de cualquier
            modal de pantalla completa (p. ej. Caja), para que nunca quede
            atrapado sin poder salir del modulo. No se muestra en el propio
            dashboard: ahi no tiene sentido un boton para ir a donde ya estas. --}}
            @unless ($launcherMode)
                <a href="{{ route('dashboard') }}" wire:navigate data-app-home-link title="Ir al panel principal"
                    class="fixed left-3 top-3 z-[100] inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-500 shadow-md hover:border-blue-300 hover:text-blue-700 transition">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                    </svg>
                </a>
            @endunless

            <div class="min-h-screen {{ $launcherMode ? 'bg-[radial-gradient(circle_at_top,#fff2d8_0%,#f5f5f4_42%,#e7e5e4_100%)]' : 'bg-gray-100' }}">
                <div x-data="{ headerOpen: false }" @mouseenter="headerOpen = true" @mouseleave="headerOpen = false" class="sticky top-0 z-20">
                    <button type="button" @click="headerOpen = !headerOpen" aria-label="Mostrar barra superior"
                        class="mx-auto flex w-full max-w-7xl items-center justify-center transition-all duration-200"
                        :class="headerOpen ? 'h-0 opacity-0' : 'h-3 opacity-100'">
                        <span class="h-1 w-16 rounded-full bg-stone-400/70"></span>
                    </button>

                    <div class="overflow-hidden border-b border-gray-200/80 bg-white/95 shadow-sm backdrop-blur transition-all duration-200"
                        :class="headerOpen ? 'max-h-24 opacity-100' : 'max-h-0 opacity-0'">
                        <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
                            <a href="{{ route('dashboard') }}" wire:navigate data-app-home-link class="flex items-center gap-3" aria-label="Ir al panel principal" title="Ir al panel principal">
                                <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-blue-600 text-white shadow-sm">
                                    <x-application-logo class="h-7 w-auto fill-current" />
                                </div>
                                <p class="text-xs font-semibold uppercase tracking-[0.3em] text-blue-700">{{ \App\Models\PlatformSetting::appName() }}</p>
                            </a>
                            <div class="flex items-center gap-3">
                                @php($initials = collect(explode(' ', auth()->user()->name))->map(fn ($part) => mb_substr($part, 0, 1))->take(2)->implode(''))
                                <div class="flex items-center gap-2.5">
                                    <div class="flex h-9 w-9 items-center justify-center rounded-full bg-blue-600 text-xs font-semibold uppercase text-white">
                                        {{ $initials }}
                                    </div>
                                    <span class="hidden text-sm font-medium text-gray-700 sm:inline">{{ auth()->user()->name }}</span>
                                </div>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="inline-flex items-center rounded-lg border border-rose-200 bg-rose-50 px-4 py-2 text-sm font-semibold text-rose-700 transition hover:border-rose-300 hover:bg-rose-100">
                                        Cerrar sesion
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <main class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                    {{ $slot }}
                </main>
            </div>
        </div>
        @livewireScripts
    </body>
</html>




