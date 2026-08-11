<div class="space-y-6">

    {{-- Header --}}
    <div>
        <p class="text-xs font-semibold uppercase tracking-[0.22em] text-blue-600">Plataforma</p>
        <h1 class="mt-1 text-2xl font-black text-gray-900">Dashboard</h1>
    </div>

    {{-- Stats --}}
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <div class="rounded-lg bg-white p-5 ring-1 ring-gray-200">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-gray-500">Total empresas</p>
            <p class="mt-2 text-3xl font-black text-gray-900">{{ $stats['total'] }}</p>
        </div>
        <div class="rounded-lg bg-white p-5 ring-1 ring-gray-200">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-gray-500">Activas</p>
            <p class="mt-2 text-3xl font-black text-emerald-600">{{ $stats['active'] }}</p>
        </div>
        <div class="rounded-lg bg-white p-5 ring-1 ring-gray-200">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-gray-500">Pendientes de pago</p>
            <p class="mt-2 text-3xl font-black text-blue-600">{{ $stats['pending'] }}</p>
        </div>
        <div class="rounded-lg bg-white p-5 ring-1 ring-gray-200">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-gray-500">Nuevas este mes</p>
            <p class="mt-2 text-3xl font-black text-gray-900">{{ $stats['newMonth'] }}</p>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-[1fr_auto]">

        {{-- Empresas recientes --}}
        <div class="rounded-xl bg-white p-6 ring-1 ring-gray-200">
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-bold text-gray-900">Registros recientes</h2>
                <a href="{{ route('platform.companies') }}" class="text-xs font-semibold text-blue-600 hover:underline">Ver todas →</a>
            </div>
            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full divide-y divide-stone-100 text-sm">
                    <thead>
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-400">
                            <th class="pb-2">Empresa</th>
                            <th class="pb-2">Propietario</th>
                            <th class="pb-2">Plan</th>
                            <th class="pb-2 w-px whitespace-nowrap">Estado</th>
                            <th class="pb-2 w-px whitespace-nowrap">Fecha</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-50">
                        @forelse ($recentCompanies as $company)
                            @php $sub = $company->subscriptions->first(); @endphp
                            <tr>
                                <td class="py-2.5">
                                    <p class="font-semibold text-gray-900">{{ $company->display_name }}</p>
                                    <p class="text-xs text-gray-400">{{ $company->tax_id ?: '—' }}</p>
                                </td>
                                <td class="py-2.5 text-xs text-gray-600">{{ $company->owner?->name ?? '—' }}</td>
                                <td class="py-2.5 text-xs text-gray-500">{{ $sub?->plan?->name ?? '—' }}</td>
                                <td class="py-2.5 w-px whitespace-nowrap">
                                    @php $status = $sub?->status ?? 'sin suscripcion'; @endphp
                                    @if ($status === 'active')
                                        <x-status-badge color="emerald">Activa</x-status-badge>
                                    @elseif ($status === 'pending')
                                        <x-status-badge color="amber">Pendiente</x-status-badge>
                                    @else
                                        <x-status-badge color="stone">{{ $status }}</x-status-badge>
                                    @endif
                                </td>
                                <td class="py-2.5 text-xs text-gray-400 w-px whitespace-nowrap">{{ $company->created_at->format('d/m/Y') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-8 text-center text-gray-400 text-xs">Sin registros aún.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Pendientes de activación --}}
        <div class="w-72 rounded-xl bg-amber-50 p-6 ring-1 ring-amber-200">
            <h2 class="text-sm font-bold text-amber-900">Pendientes de activar</h2>
            <p class="mt-0.5 text-xs text-blue-700">Empresas esperando confirmación de pago.</p>

            <div class="mt-4 space-y-3">
                @forelse ($pendingCompanies as $company)
                    <div class="rounded-lg bg-white p-3 ring-1 ring-amber-100">
                        <p class="text-sm font-semibold text-gray-900">{{ $company->display_name }}</p>
                        <p class="text-xs text-gray-500">{{ $company->owner?->email ?? '—' }}</p>
                        <p class="mt-1 text-xs text-gray-400">{{ $company->created_at->diffForHumans() }}</p>
                    </div>
                @empty
                    <p class="text-xs text-blue-700">No hay empresas pendientes.</p>
                @endforelse
            </div>

            @if ($pendingCompanies->isNotEmpty())
                <a href="{{ route('platform.companies', ['filter' => 'pending']) }}"
                   class="mt-4 block text-center text-xs font-semibold text-blue-700 hover:underline">
                    Gestionar →
                </a>
            @endif
        </div>

    </div>
</div>
