<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ \App\Models\PlatformSetting::appName() }}</title>

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-gray-900 antialiased">
        <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-white bg-[radial-gradient(circle_at_top,_rgba(37,99,235,0.08),_transparent_45%)]">
            <div>
                <a href="/" wire:navigate aria-label="Ir al inicio" title="Ir al inicio" class="flex items-center gap-3">
                    <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-blue-600 to-purple-600 shadow-sm shadow-blue-600/20">
                        <x-application-logo class="w-9 h-9 fill-current text-white" />
                    </span>
                    <span class="text-lg font-bold text-gray-900">{{ \App\Models\PlatformSetting::appName() }}</span>
                </a>
            </div>

            <div class="w-full sm:max-w-md mt-6 px-6 py-6 bg-white border border-gray-200 shadow-sm overflow-hidden sm:rounded-2xl">
                {{ $slot }}
            </div>
        </div>

        <x-toast-stack />
    </body>
</html>
