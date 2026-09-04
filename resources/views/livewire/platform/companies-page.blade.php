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
                <div class="flex flex-wrap items-center gap-3">
                    <input wire:model.live.debounce.300ms="search" type="text"
                        placeholder="Buscar empresa..."
                        class="w-56 rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600 text-sm">
                    <div class="inline-flex rounded-lg border border-gray-200 bg-gray-50 p-1 text-sm">
                        <button type="button" wire:click="setFilter('all')"
                            class="rounded-md px-3 py-1.5 font-semibold transition {{ $filter === 'all' ? 'bg-gradient-to-br from-blue-600 to-purple-600 text-white shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">
                            Todas
                        </button>
                        <button type="button" wire:click="setFilter('pending')"
                            class="rounded-md px-3 py-1.5 font-semibold transition {{ $filter === 'pending' ? 'bg-amber-600 text-white shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">
                            Pendientes
                        </button>
                        <button type="button" wire:click="setFilter('active')"
                            class="rounded-md px-3 py-1.5 font-semibold transition {{ $filter === 'active' ? 'bg-emerald-600 text-white shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">
                            Activas
                        </button>
                    </div>
                </div>
            </div>

            <div class="mt-6 overflow-x-auto">
                <table class="min-w-full divide-y divide-stone-200 text-sm">
                    <thead>
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <th class="pb-2">Empresa</th>
                            <th class="pb-2">Propietario</th>
                            <th class="pb-2">Plan</th>
                            <th class="pb-2 w-px whitespace-nowrap">Tipo</th>
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
                                    @if ($company->businessType)
                                        <div class="flex items-center gap-2">
                                            <span class="inline-flex items-center gap-1.5 rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-600">
                                                <x-business-type-icon :icon="$company->businessType->icon" class="h-3.5 w-3.5" />
                                                {{ $company->businessType->name }}
                                            </span>
                                            <button wire:click="openTypeEditModal({{ $company->id }})"
                                                class="text-xs font-semibold text-blue-600 hover:text-blue-700">
                                                Cambiar
                                            </button>
                                        </div>
                                    @else
                                        <x-status-badge color="stone">sin definir</x-status-badge>
                                    @endif
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
                                            <button wire:click="openActivationModal({{ $company->id }})"
                                                class="rounded-full bg-emerald-600 px-3 py-1 text-xs font-semibold text-white transition hover:bg-emerald-700">
                                                Activar
                                            </button>
                                        @elseif ($status === 'active')
                                            <button wire:click="suspendCompany({{ $company->id }})"
                                                wire:confirm="¿Suspender la empresa {{ $company->display_name }}?"
                                                class="rounded-full border border-gray-300 px-3 py-1 text-xs font-semibold text-gray-600 transition hover:border-rose-300 hover:text-rose-600">
                                                Suspender
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-10 text-center text-gray-400">
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

    @if ($showTypeModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div class="w-full max-w-lg rounded-xl bg-white p-6 shadow-xl ring-1 ring-gray-200">
                <h3 class="text-lg font-black text-gray-900">
                    {{ $typeModalMode === 'activate' ? 'Activar empresa' : 'Cambiar tipo de negocio' }}
                </h3>
                <p class="mt-1 text-sm text-gray-500">
                    @if ($typeModalMode === 'activate')
                        Elige el tipo de negocio antes de activar la suscripcion. Esta eleccion la hace unicamente la plataforma.
                    @else
                        Puedes corregir el tipo de negocio en cualquier momento.
                    @endif
                </p>

                <div class="mt-5 grid grid-cols-2 gap-3">
                    @foreach ($businessTypes as $type)
                        <button type="button" wire:click="$set('selectedBusinessTypeId', {{ $type->id }})"
                            class="flex flex-col items-center gap-2 rounded-xl border-2 p-4 text-center transition {{ (int) $selectedBusinessTypeId === $type->id ? 'border-blue-600 bg-blue-50' : 'border-gray-200 hover:border-gray-300' }}">
                            <x-business-type-icon :icon="$type->icon" class="h-8 w-8 {{ (int) $selectedBusinessTypeId === $type->id ? 'text-blue-600' : 'text-gray-400' }}" />
                            <span class="text-sm font-semibold text-gray-800">{{ $type->name }}</span>
                        </button>
                    @endforeach
                </div>
                @error('selectedBusinessTypeId')
                    <p class="mt-2 text-xs font-medium text-rose-600">{{ $message }}</p>
                @enderror

                <div class="mt-6 flex justify-end gap-3">
                    <button wire:click="closeTypeModal"
                        class="rounded-full border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-600 transition hover:bg-gray-50">
                        Cancelar
                    </button>
                    <button wire:click="confirmTypeModal"
                        class="rounded-full bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-700">
                        {{ $typeModalMode === 'activate' ? 'Activar empresa' : 'Guardar' }}
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
