<div class="pb-10">
    <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">

        {{-- Filtros y buscador --}}
        <div x-data="responsivePageSize({ rowHeight: 68, reserved: 300 })" class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-[0.22em] text-blue-700">Plataforma</p>
                    <h3 class="mt-1 text-2xl font-black text-gray-900">
                        Empresas
                        @if ($pendingCount > 0)
                            <x-status-badge color="rose" class="ml-2">
                                {{ $pendingCount }} pendiente{{ $pendingCount > 1 ? 's' : '' }}
                            </x-status-badge>
                        @endif
                    </h3>
                </div>
                <div class="flex items-center gap-3">
                    <input wire:model.live.debounce.300ms="search" type="text"
                        placeholder="Buscar empresa..."
                        class="w-56 rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600 text-sm">
                    <select wire:model.live="filter"
                        class="rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600 text-sm">
                        <option value="all">Todas</option>
                        <option value="pending">Pendientes</option>
                        <option value="active">Activas</option>
                    </select>
                </div>
            </div>

            <div class="mt-6 overflow-x-auto">
                <table class="min-w-full divide-y divide-stone-200 text-sm">
                    <thead>
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <th class="pb-2">Empresa</th>
                            <th class="pb-2">Propietario</th>
                            <th class="pb-2">Plan</th>
                            <th class="pb-2 w-px whitespace-nowrap">Estado</th>
                            <th class="pb-2 w-px whitespace-nowrap">Registrada</th>
                            <th class="pb-2 w-px whitespace-nowrap text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-100">
                        @forelse ($companies as $company)
                            @php
                                $sub = $company->subscriptions->first();
                                $status = $sub?->status ?? 'sin suscripcion';
                            @endphp
                            <tr wire:key="company-{{ $company->id }}" class="even:bg-gray-50">
                                <td class="py-3 align-middle">
                                    <p class="font-semibold text-gray-900">{{ $company->display_name }}</p>
                                    <p class="text-xs text-gray-400">{{ $company->legal_name }}</p>
                                </td>
                                <td class="py-3 align-middle text-gray-600 text-xs">
                                    <p>{{ $company->owner?->name }}</p>
                                    <p class="text-gray-400">{{ $company->owner?->email }}</p>
                                </td>
                                <td class="py-3 align-middle text-gray-600 text-xs">
                                    {{ $sub?->plan?->name ?? '—' }}
                                </td>
                                <td class="py-3 align-middle w-px whitespace-nowrap">
                                    @if ($status === 'pending')
                                        <x-status-badge color="amber" class="w-24">pendiente</x-status-badge>
                                    @elseif ($status === 'active')
                                        <x-status-badge color="emerald" class="w-24">activa</x-status-badge>
                                    @elseif ($status === 'trialing')
                                        <x-status-badge color="blue" class="w-24">trial</x-status-badge>
                                    @else
                                        <x-status-badge color="stone" class="w-24">{{ $status }}</x-status-badge>
                                    @endif
                                </td>
                                <td class="py-3 align-middle text-xs text-gray-500 w-px whitespace-nowrap">
                                    {{ $company->created_at->format('d/m/Y') }}
                                </td>
                                <td class="py-3 align-middle w-px whitespace-nowrap">
                                    <div class="flex justify-end gap-2">
                                        @if ($status === 'pending')
                                            <button wire:click="activate({{ $company->id }})"
                                                wire:confirm="¿Activar la empresa {{ $company->display_name }}?"
                                                class="rounded-full bg-emerald-600 px-3 py-1 text-xs font-semibold text-white hover:bg-emerald-700 transition">
                                                Activar
                                            </button>
                                        @elseif ($status === 'active')
                                            <button wire:click="suspend({{ $company->id }})"
                                                wire:confirm="¿Suspender la empresa {{ $company->display_name }}?"
                                                class="rounded-full border border-gray-300 px-3 py-1 text-xs font-semibold text-gray-600 hover:border-rose-300 hover:text-rose-600 transition">
                                                Suspender
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-10 text-center text-gray-400">
                                    No hay empresas que coincidan con el filtro.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($companies->hasPages())
                <div class="mt-4">
                    {{ $companies->links() }}
                </div>
            @endif
        </div>

    </div>
</div>
