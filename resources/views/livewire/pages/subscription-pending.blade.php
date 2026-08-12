<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Activacion pendiente · {{ \App\Models\PlatformSetting::appName() }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-gray-100 font-sans antialiased">

    <div class="flex min-h-screen flex-col items-center justify-center px-4 py-12">
        <div class="w-full max-w-md space-y-6">

            {{-- Logo --}}
            <div class="text-center">
                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-lg bg-stone-950 text-white shadow">
                    <x-application-logo class="h-8 w-auto fill-current" />
                </div>
                <p class="mt-3 text-xs font-semibold uppercase tracking-[0.3em] text-blue-700">{{ \App\Models\PlatformSetting::appName() }}</p>
            </div>

            {{-- Card principal --}}
            <div class="rounded-xl bg-white p-8 shadow-sm ring-1 ring-gray-200">
                <div class="text-center">
                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-amber-50">
                        <svg class="h-6 w-6 text-blue-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2m6-2a10 10 0 11-20 0 10 10 0 0120 0z"/>
                        </svg>
                    </div>
                    <h1 class="mt-4 text-2xl font-black text-gray-900">Activacion pendiente</h1>
                    <p class="mt-2 text-sm text-gray-500">
                        Tu empresa fue creada correctamente. Para acceder, realiza el pago por transferencia bancaria y nuestro equipo activara tu cuenta.
                    </p>
                </div>

                @php
                    $bankAccount  = \App\Models\PlatformSetting::get('bank_account',  config('platform.bank_account', ''));
                    $bankName     = \App\Models\PlatformSetting::get('bank_name',     config('platform.bank_name', ''));
                    $bankType     = \App\Models\PlatformSetting::get('bank_type',     config('platform.bank_type', ''));
                    $bankHolder   = \App\Models\PlatformSetting::get('bank_holder',   config('platform.bank_holder', ''));
                    $bankNit      = \App\Models\PlatformSetting::get('bank_nit',      config('platform.bank_nit', ''));
                    $planPrice    = \App\Models\PlatformSetting::get('plan_price',    config('platform.plan_price', ''));
                    $contactEmail = \App\Models\PlatformSetting::get('contact_email', config('platform.contact_email', ''));
                    $contactPhone = \App\Models\PlatformSetting::get('contact_phone', config('platform.contact_phone', ''));
                @endphp

                {{-- Datos bancarios --}}
                @if ($bankAccount)
                    <div class="mt-6 rounded-lg bg-gray-50 p-5 ring-1 ring-gray-200">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-gray-500">Datos para transferencia</p>
                        <dl class="mt-3 space-y-2 text-sm">
                            @if ($bankName)
                                <div class="flex justify-between gap-4">
                                    <dt class="text-gray-500">Banco</dt>
                                    <dd class="font-semibold text-gray-900">{{ $bankName }}</dd>
                                </div>
                            @endif
                            @if ($bankType)
                                <div class="flex justify-between gap-4">
                                    <dt class="text-gray-500">Tipo</dt>
                                    <dd class="font-semibold text-gray-900">{{ $bankType }}</dd>
                                </div>
                            @endif
                            <div class="flex justify-between gap-4">
                                <dt class="text-gray-500">No. de cuenta</dt>
                                <dd class="font-semibold text-gray-900 tracking-wider">{{ $bankAccount }}</dd>
                            </div>
                            @if ($bankHolder)
                                <div class="flex justify-between gap-4">
                                    <dt class="text-gray-500">A nombre de</dt>
                                    <dd class="font-semibold text-gray-900">{{ $bankHolder }}</dd>
                                </div>
                            @endif
                            @if ($bankNit)
                                <div class="flex justify-between gap-4">
                                    <dt class="text-gray-500">NIT</dt>
                                    <dd class="font-semibold text-gray-900">{{ $bankNit }}</dd>
                                </div>
                            @endif
                            @if ($planPrice)
                                <div class="flex justify-between gap-4 border-t border-gray-200 pt-2">
                                    <dt class="font-semibold text-gray-700">Valor</dt>
                                    <dd class="font-black text-gray-900">{{ $planPrice }}</dd>
                                </div>
                            @endif
                        </dl>
                    </div>
                @endif

                {{-- Contacto --}}
                @if ($contactEmail || $contactPhone)
                    <div class="mt-4 rounded-lg bg-amber-50 p-4 ring-1 ring-amber-200">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-blue-700">Despues de transferir</p>
                        <p class="mt-1 text-sm text-gray-600">
                            Envianos el comprobante al
                            @if ($contactEmail)
                                <span class="font-semibold text-gray-900">{{ $contactEmail }}</span>
                            @endif
                            @if ($contactPhone)
                                o al
                                @php($whatsappUrl = \App\Models\PlatformSetting::whatsappUrl('Hola, quiero enviar el comprobante de pago de mi empresa.'))
                                @if ($whatsappUrl !== '')
                                    <a href="{{ $whatsappUrl }}" target="_blank" rel="noopener" class="font-semibold text-emerald-700 underline">{{ $contactPhone }} (WhatsApp)</a>
                                @else
                                    <span class="font-semibold text-gray-900">{{ $contactPhone }}</span>
                                @endif
                            @endif
                            y activaremos tu cuenta en menos de 24 horas habiles.
                        </p>
                    </div>
                @endif
            </div>

            {{-- Cerrar sesion --}}
            <div class="text-center">
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="text-sm text-gray-500 hover:text-gray-700 transition">
                        Cerrar sesion
                    </button>
                </form>
            </div>

        </div>
    </div>

</body>
</html>
