<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Pagina no encontrada &middot; {{ \App\Models\PlatformSetting::appName() }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-gray-900 antialiased">
        <div class="flex min-h-screen flex-col items-center justify-center bg-white bg-[radial-gradient(circle_at_top,_rgba(37,99,235,0.08),_transparent_45%)] px-6 py-12 text-center">
            <a href="/" wire:navigate aria-label="Ir al inicio" title="Ir al inicio" class="mb-10 flex items-center gap-3">
                <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-blue-600 to-purple-600 shadow-sm shadow-blue-600/20">
                    <x-application-logo class="h-7 w-7 fill-current text-white" />
                </span>
                <span class="text-lg font-bold text-gray-900">{{ \App\Models\PlatformSetting::appName() }}</span>
            </a>

            {{-- Icono gigante: una caja con signo de pregunta, para que el
            404 se sienta propio del contexto de inventario/retail en vez de
            un icono generico. --}}
            <div class="relative mb-4">
                <svg viewBox="0 0 200 200" class="h-48 w-48 sm:h-56 sm:w-56" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <defs>
                        <linearGradient id="box-grad" x1="0" y1="0" x2="1" y2="1">
                            <stop offset="0%" stop-color="#2563eb" />
                            <stop offset="100%" stop-color="#9333ea" />
                        </linearGradient>
                    </defs>
                    <path d="M100 18 L176 56 L176 144 L100 182 L24 144 L24 56 Z" fill="url(#box-grad)" opacity="0.1" />
                    <path d="M100 18 L176 56 L100 94 L24 56 Z" stroke="url(#box-grad)" stroke-width="5" stroke-linejoin="round" fill="none" />
                    <path d="M24 56 L24 144 L100 182 L100 94 Z" stroke="url(#box-grad)" stroke-width="5" stroke-linejoin="round" fill="none" />
                    <path d="M176 56 L176 144 L100 182 L100 94 Z" stroke="url(#box-grad)" stroke-width="5" stroke-linejoin="round" fill="none" />
                    <path d="M62 37 L138 75" stroke="url(#box-grad)" stroke-width="5" stroke-linecap="round" />
                    <text x="100" y="76" text-anchor="middle" font-family="Segoe UI, Arial, sans-serif" font-size="52" font-weight="800" fill="url(#box-grad)">?</text>
                </svg>
            </div>

            <p class="bg-gradient-to-br from-blue-600 to-purple-600 bg-clip-text text-6xl font-black tracking-tight text-transparent sm:text-7xl">404</p>

            <h1 class="mt-3 text-2xl font-black text-gray-900 sm:text-3xl">Esto no esta en el inventario</h1>
            <p class="mx-auto mt-2 max-w-md text-sm text-gray-500 sm:text-base">
                La pagina que buscas no existe, se movio o el enlace ya no es valido.
            </p>

            <a href="/" wire:navigate
                class="mt-8 inline-flex items-center gap-2 rounded-full bg-gradient-to-br from-blue-600 to-purple-600 px-6 py-3 text-sm font-semibold text-white shadow-sm shadow-blue-600/20 transition hover:shadow-md hover:shadow-blue-600/30">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                </svg>
                Volver al inicio
            </a>
        </div>
    </body>
</html>
