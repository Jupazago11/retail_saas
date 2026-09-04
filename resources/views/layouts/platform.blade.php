<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>Plataforma — {{ \App\Models\PlatformSetting::appName() }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        <div class="flex min-h-screen">

            {{-- Sidebar --}}
            <aside class="flex w-56 flex-col border-r border-gray-200 bg-[#fafafa]">
                {{-- Logo --}}
                <div class="flex items-center gap-3 px-5 py-5">
                    <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-blue-600">
                        <x-application-logo class="h-5 w-auto fill-white" />
                    </div>
                    <div>
                        <p class="text-xs font-bold leading-tight text-gray-900">{{ \App\Models\PlatformSetting::appName() }}</p>
                        <p class="text-[9px] font-semibold uppercase tracking-[0.2em] text-gray-500">Plataforma</p>
                    </div>
                </div>

                <div class="mx-4 border-t border-gray-200"></div>

                {{-- Nav --}}
                <nav class="flex-1 space-y-0.5 px-3 py-4">
                    @php
                        $navItem = fn(string $route, string $label, string $icon) => [
                            'active' => request()->routeIs($route),
                            'href'   => route($route),
                            'label'  => $label,
                            'icon'   => $icon,
                        ];
                        $items = [
                            $navItem('platform.dashboard',      'Dashboard',       'chart'),
                            $navItem('platform.companies',      'Empresas',        'building'),
                            $navItem('platform.subscriptions',  'Suscripciones',   'credit'),
                            $navItem('platform.plans',          'Planes',          'layers'),
                            $navItem('platform.coupons',        'Cupones',         'tag'),
                            $navItem('platform.printers',       'Impresoras',      'printer'),
                            $navItem('platform.equipment',      'Equipos',         'cube'),
                            $navItem('platform.users',          'Usuarios',        'users'),
                            $navItem('platform.settings',       'Parámetros',      'settings'),
                        ];
                    @endphp

                    @foreach ($items as $item)
                        <a href="{{ $item['href'] }}"
                           class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition
                                  {{ $item['active']
                                     ? 'bg-blue-50 text-blue-700'
                                     : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900' }}">
                            {{-- Icon --}}
                            @if ($item['icon'] === 'chart')
                                <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" /></svg>
                            @elseif ($item['icon'] === 'building')
                                <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>
                            @elseif ($item['icon'] === 'credit')
                                <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" /></svg>
                            @elseif ($item['icon'] === 'layers')
                                <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5" /></svg>
                            @elseif ($item['icon'] === 'tag')
                                <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" /></svg>
                            @elseif ($item['icon'] === 'printer')
                                <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0110.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0l.229 2.523a1.125 1.125 0 01-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0021 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 00-1.913-.247M6.34 18H5.25A2.25 2.25 0 013 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 011.913-.247m10.5 0a48.536 48.536 0 00-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659" /></svg>
                            @elseif ($item['icon'] === 'cube')
                                <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 7.5l-9-5.25L3 7.5m18 0l-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9" /></svg>
                            @elseif ($item['icon'] === 'users')
                                <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" /></svg>
                            @elseif ($item['icon'] === 'settings')
                                <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                            @endif
                            {{ $item['label'] }}
                        </a>
                    @endforeach
                </nav>

                {{-- Logout --}}
                <div class="border-t border-gray-200 p-3">
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit"
                            class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-gray-500 transition hover:bg-gray-100 hover:text-rose-600">
                            <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" /></svg>
                            Cerrar sesion
                        </button>
                    </form>
                </div>
            </aside>

            {{-- Content --}}
            <div class="flex min-h-screen flex-1 flex-col bg-gray-100">
                <x-toast-stack />
                <main class="flex-1 px-8 py-8">
                    {{ $slot }}
                </main>
            </div>

        </div>
    </body>
</html>
