<div class="pb-10">
    <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">

        {{-- Filtros y buscador --}}
        <div class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-stone-200">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-[0.22em] text-amber-700">Plataforma</p>
                    <h3 class="mt-1 text-2xl font-black text-stone-900">
                        Empresas
                        @if ($pendingCount > 0)
                            <span class="ml-2 inline-flex items-center rounded-full bg-rose-100 px-2.5 py-0.5 text-sm font-semibold text-rose-700">
                                {{ $pendingCount }} pendiente{{ $pendingCount > 1 ? 's' : '' }}
                            </span>
                        @endif
                    </h3>
                </div>
                <div class="flex items-center gap-3">
                    <input wire:model.live.debounce.300ms="search" type="text"
                        placeholder="Buscar empresa..."
                        class="w-56 rounded-2xl border-stone-300 shadow-sm focus:border-amber-500 focus:ring-amber-500 text-sm">
                    <select wire:model.live="filter"
                        class="rounded-2xl border-stone-300 shadow-sm focus:border-amber-500 focus:ring-amber-500 text-sm">
                        <option value="all">Todas</option>
                        <option value="pending">Pendientes</option>
                        <option value="active">Activas</option>
                    </select>
                </div>
            </div>

            <div class="mt-6 overflow-x-auto">
                <table class="min-w-full divide-y divide-stone-200 text-sm">
                    <thead>
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-stone-500">
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
                            <tr wire:key="company-{{ $company->id }}">
                                <td class="py-3 align-middle">
                                    <p class="font-semibold text-stone-900">{{ $company->display_name }}</p>
                                    <p class="text-xs text-stone-400">{{ $company->legal_name }}</p>
                                </td>
                                <td class="py-3 align-middle text-stone-600 text-xs">
                                    <p>{{ $company->owner?->name }}</p>
                                    <p class="text-stone-400">{{ $company->owner?->email }}</p>
                                </td>
                                <td class="py-3 align-middle text-stone-600 text-xs">
                                    {{ $sub?->plan?->name ?? '—' }}
                                </td>
                                <td class="py-3 align-middle w-px whitespace-nowrap">
                                    @if ($status === 'pending')
                                        <span class="inline-flex w-24 justify-center rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-700">pendiente</span>
                                    @elseif ($status === 'active')
                                        <span class="inline-flex w-24 justify-center rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-700">activa</span>
                                    @elseif ($status === 'trialing')
                                        <span class="inline-flex w-24 justify-center rounded-full bg-blue-100 px-3 py-1 text-xs font-semibold text-blue-700">trial</span>
                                    @else
                                        <span class="inline-flex w-24 justify-center rounded-full bg-stone-100 px-3 py-1 text-xs font-semibold text-stone-500">{{ $status }}</span>
                                    @endif
                                </td>
                                <td class="py-3 align-middle text-xs text-stone-500 w-px whitespace-nowrap">
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
                                                class="rounded-full border border-stone-300 px-3 py-1 text-xs font-semibold text-stone-600 hover:border-rose-300 hover:text-rose-600 transition">
                                                Suspender
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-10 text-center text-stone-400">
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
