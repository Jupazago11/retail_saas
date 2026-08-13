<div class="pb-10">
    <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
        <x-admin-nav active="admin.structure" />

        {{-- Sucursales --}}
        <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.22em] text-blue-700">Sucursales</p>
                    <h3 class="mt-1 text-2xl font-black text-gray-900">
                        Estructura registrada
                        <span class="ml-2 text-sm font-normal text-gray-400">
                            {{ $branches->count() }}{{ $maxBranches !== null ? ' de '.$maxBranches : '' }} registros
                        </span>
                    </h3>
                </div>
                <button wire:click="openModal('branch')"
                    class="flex h-9 w-9 items-center justify-center rounded-full bg-blue-600 text-white transition hover:bg-blue-700"
                    title="Nueva sucursal">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                </button>
            </div>

            <div class="mt-5 overflow-x-auto">
                <table class="min-w-full divide-y divide-stone-200 text-sm">
                    <thead>
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-400">
                            <th class="pb-2">Nombre</th>
                            <th class="pb-2">Código</th>
                            <th class="pb-2 w-px whitespace-nowrap">Tipo</th>
                            <th class="pb-2 w-px whitespace-nowrap">Estado</th>
                            <th class="pb-2 w-px whitespace-nowrap text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-100">
                        @forelse ($branches as $branch)
                            <tr wire:key="branch-{{ $branch->id }}" class="even:bg-gray-50">
                                <td class="py-3 font-medium text-gray-900">{{ $branch->name }}</td>
                                <td class="py-3 font-mono text-xs text-gray-500">{{ $branch->code }}</td>
                                <td class="py-3 w-px whitespace-nowrap">
                                    @if ($branch->is_primary)
                                        <x-status-badge color="amber">Principal</x-status-badge>
                                    @else
                                        <x-status-badge color="stone">Operativa</x-status-badge>
                                    @endif
                                </td>
                                <td class="py-3 w-px whitespace-nowrap">
                                    @if ($branch->status === 'active')
                                        <x-status-badge color="emerald">Activa</x-status-badge>
                                    @else
                                        <x-status-badge color="stone">Inactiva</x-status-badge>
                                    @endif
                                </td>
                                <td class="py-3 w-px whitespace-nowrap">
                                    <div class="flex justify-end gap-2">
                                        @if (! $branch->is_primary)
                                            <button wire:click="toggleStatus('branch', {{ $branch->id }})"
                                                class="rounded-full border border-gray-300 px-3 py-1 text-xs font-semibold text-gray-600 transition hover:border-blue-400 hover:text-blue-700">
                                                {{ $branch->status === 'active' ? 'Inactivar' : 'Activar' }}
                                            </button>
                                        @endif
                                        @if (in_array($branch->id, $deletableBranchIds))
                                            <button wire:click="deleteRecord('branch', {{ $branch->id }})"
                                                wire:confirm="¿Eliminar la sucursal {{ $branch->name }}? Esta acción no se puede deshacer."
                                                class="rounded-full border border-rose-200 px-3 py-1 text-xs font-semibold text-rose-600 transition hover:bg-rose-50">
                                                Eliminar
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-6 text-center text-xs text-gray-400">Sin sucursales registradas.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Bodegas --}}
        <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.22em] text-blue-700">Bodegas</p>
                    <h3 class="mt-1 text-2xl font-black text-gray-900">
                        Bodegas registradas
                        <span class="ml-2 text-sm font-normal text-gray-400">
                            {{ $warehouses->count() }}{{ $maxWarehouses !== null ? ' de '.$maxWarehouses : '' }} registros
                        </span>
                    </h3>
                </div>
                <button wire:click="openModal('warehouse')"
                    class="flex h-9 w-9 items-center justify-center rounded-full bg-blue-600 text-white transition hover:bg-blue-700"
                    title="Nueva bodega">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                </button>
            </div>

            <div class="mt-5 overflow-x-auto">
                <table class="min-w-full divide-y divide-stone-200 text-sm">
                    <thead>
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-400">
                            <th class="pb-2">Nombre</th>
                            <th class="pb-2">Código</th>
                            <th class="pb-2">Sucursal</th>
                            <th class="pb-2 w-px whitespace-nowrap">Tipo</th>
                            <th class="pb-2 w-px whitespace-nowrap">Estado</th>
                            <th class="pb-2 w-px whitespace-nowrap text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-100">
                        @forelse ($warehouses as $warehouse)
                            <tr wire:key="warehouse-{{ $warehouse->id }}" class="even:bg-gray-50">
                                <td class="py-3 font-medium text-gray-900">{{ $warehouse->name }}</td>
                                <td class="py-3 font-mono text-xs text-gray-500">{{ $warehouse->code }}</td>
                                <td class="py-3 text-xs text-gray-500">{{ $warehouse->branch?->name ?? '—' }}</td>
                                <td class="py-3 w-px whitespace-nowrap">
                                    @if ($warehouse->is_primary)
                                        <x-status-badge color="amber">Principal</x-status-badge>
                                    @else
                                        <x-status-badge color="stone">Operativa</x-status-badge>
                                    @endif
                                </td>
                                <td class="py-3 w-px whitespace-nowrap">
                                    @if ($warehouse->status === 'active')
                                        <x-status-badge color="emerald">Activa</x-status-badge>
                                    @else
                                        <x-status-badge color="stone">Inactiva</x-status-badge>
                                    @endif
                                </td>
                                <td class="py-3 w-px whitespace-nowrap">
                                    <div class="flex justify-end gap-2">
                                        @if (! $warehouse->is_primary)
                                            <button wire:click="toggleStatus('warehouse', {{ $warehouse->id }})"
                                                class="rounded-full border border-gray-300 px-3 py-1 text-xs font-semibold text-gray-600 transition hover:border-blue-400 hover:text-blue-700">
                                                {{ $warehouse->status === 'active' ? 'Inactivar' : 'Activar' }}
                                            </button>
                                        @endif
                                        @if (in_array($warehouse->id, $deletableWarehouseIds))
                                            <button wire:click="deleteRecord('warehouse', {{ $warehouse->id }})"
                                                wire:confirm="¿Eliminar la bodega {{ $warehouse->name }}? Esta acción no se puede deshacer."
                                                class="rounded-full border border-rose-200 px-3 py-1 text-xs font-semibold text-rose-600 transition hover:bg-rose-50">
                                                Eliminar
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="py-6 text-center text-xs text-gray-400">Sin bodegas registradas.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Cajas --}}
        <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.22em] text-blue-700">Cajas</p>
                    <h3 class="mt-1 text-2xl font-black text-gray-900">
                        Cajas registradas
                        <span class="ml-2 text-sm font-normal text-gray-400">
                            {{ $cashRegisters->count() }}{{ $maxCashRegisters !== null ? ' de '.$maxCashRegisters : '' }} registros
                        </span>
                    </h3>
                </div>
                <button wire:click="openModal('cash')"
                    class="flex h-9 w-9 items-center justify-center rounded-full bg-blue-600 text-white transition hover:bg-blue-700"
                    title="Nueva caja">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                </button>
            </div>

            <div class="mt-5 overflow-x-auto">
                <table class="min-w-full divide-y divide-stone-200 text-sm">
                    <thead>
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-400">
                            <th class="pb-2">Nombre</th>
                            <th class="pb-2">Código</th>
                            <th class="pb-2">Sucursal</th>
                            <th class="pb-2 w-px whitespace-nowrap">Tipo</th>
                            <th class="pb-2 w-px whitespace-nowrap">Estado</th>
                            <th class="pb-2 w-px whitespace-nowrap text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-100">
                        @forelse ($cashRegisters as $cashRegister)
                            <tr wire:key="cash-{{ $cashRegister->id }}" class="even:bg-gray-50">
                                <td class="py-3 font-medium text-gray-900">{{ $cashRegister->name }}</td>
                                <td class="py-3 font-mono text-xs text-gray-500">{{ $cashRegister->code }}</td>
                                <td class="py-3 text-xs text-gray-500">{{ $cashRegister->branch?->name ?? '—' }}</td>
                                <td class="py-3 w-px whitespace-nowrap">
                                    @if ($cashRegister->is_primary)
                                        <x-status-badge color="amber">Principal</x-status-badge>
                                    @else
                                        <x-status-badge color="stone">Operativa</x-status-badge>
                                    @endif
                                </td>
                                <td class="py-3 w-px whitespace-nowrap">
                                    @if ($cashRegister->status === 'active')
                                        <x-status-badge color="emerald">Activa</x-status-badge>
                                    @else
                                        <x-status-badge color="stone">Inactiva</x-status-badge>
                                    @endif
                                </td>
                                <td class="py-3 w-px whitespace-nowrap">
                                    <div class="flex justify-end gap-2">
                                        @if (! $cashRegister->is_primary)
                                            <button wire:click="toggleStatus('cash', {{ $cashRegister->id }})"
                                                class="rounded-full border border-gray-300 px-3 py-1 text-xs font-semibold text-gray-600 transition hover:border-blue-400 hover:text-blue-700">
                                                {{ $cashRegister->status === 'active' ? 'Inactivar' : 'Activar' }}
                                            </button>
                                        @endif
                                        @if (in_array($cashRegister->id, $deletableCashIds))
                                            <button wire:click="deleteRecord('cash', {{ $cashRegister->id }})"
                                                wire:confirm="¿Eliminar la caja {{ $cashRegister->name }}? Esta acción no se puede deshacer."
                                                class="rounded-full border border-rose-200 px-3 py-1 text-xs font-semibold text-rose-600 transition hover:bg-rose-50">
                                                Eliminar
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="py-6 text-center text-xs text-gray-400">Sin cajas registradas.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    {{-- Modal nueva sucursal --}}
    @if ($activeModal === 'branch')
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl ring-1 ring-gray-200">
                <h3 class="text-lg font-black text-gray-900">Nueva sucursal</h3>
                <div class="mt-5 space-y-4">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Nombre</label>
                        <input wire:model="branchName" type="text" autocomplete="new-password" autofocus
                            class="mt-1 w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-600 focus:ring-blue-600">
                        @error('branchName') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Código</label>
                        <input wire:model="branchCode" type="text" inputmode="text" autocomplete="new-password"
                            placeholder="Ej: SUC01"
                            class="mt-1 w-full rounded-lg border-gray-300 text-sm uppercase shadow-sm focus:border-blue-600 focus:ring-blue-600">
                        @error('branchCode') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-3">
                    <button wire:click="closeModal" class="rounded-full border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-600 hover:bg-gray-50">Cancelar</button>
                    <button wire:click="saveBranch" class="rounded-full bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Guardar</button>
                </div>
            </div>
        </div>
    @endif

    {{-- Modal nueva bodega --}}
    @if ($activeModal === 'warehouse')
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl ring-1 ring-gray-200">
                <h3 class="text-lg font-black text-gray-900">Nueva bodega</h3>
                <div class="mt-5 space-y-4">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Sucursal</label>
                        <select wire:model="warehouseBranchId"
                            class="mt-1 w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-600 focus:ring-blue-600">
                            @foreach ($branches as $branch)
                                <option value="{{ $branch->id }}">{{ $branch->name }} ({{ $branch->code }})</option>
                            @endforeach
                        </select>
                        @error('warehouseBranchId') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Nombre</label>
                        <input wire:model="warehouseName" type="text" autocomplete="new-password"
                            class="mt-1 w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-600 focus:ring-blue-600">
                        @error('warehouseName') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Código</label>
                        <input wire:model="warehouseCode" type="text" inputmode="text" autocomplete="new-password"
                            placeholder="Ej: BOD01"
                            class="mt-1 w-full rounded-lg border-gray-300 text-sm uppercase shadow-sm focus:border-blue-600 focus:ring-blue-600">
                        @error('warehouseCode') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-3">
                    <button wire:click="closeModal" class="rounded-full border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-600 hover:bg-gray-50">Cancelar</button>
                    <button wire:click="saveWarehouse" class="rounded-full bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Guardar</button>
                </div>
            </div>
        </div>
    @endif

    {{-- Modal nueva caja --}}
    @if ($activeModal === 'cash')
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl ring-1 ring-gray-200">
                <h3 class="text-lg font-black text-gray-900">Nueva caja</h3>
                <div class="mt-5 space-y-4">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Sucursal</label>
                        <select wire:model="cajaBranchId"
                            class="mt-1 w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-600 focus:ring-blue-600">
                            @foreach ($branches as $branch)
                                <option value="{{ $branch->id }}">{{ $branch->name }} ({{ $branch->code }})</option>
                            @endforeach
                        </select>
                        @error('cajaBranchId') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Nombre</label>
                        <input wire:model="cajaName" type="text" autocomplete="new-password"
                            class="mt-1 w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-600 focus:ring-blue-600">
                        @error('cajaName') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Código</label>
                        <input wire:model="cajaCode" type="text" autocomplete="new-password"
                            placeholder="Ej: CAJA01"
                            class="mt-1 w-full rounded-lg border-gray-300 text-sm uppercase shadow-sm focus:border-blue-600 focus:ring-blue-600">
                        @error('cajaCode') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-3">
                    <button wire:click="closeModal" class="rounded-full border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-600 hover:bg-gray-50">Cancelar</button>
                    <button wire:click="saveCashRegister" class="rounded-full bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Guardar</button>
                </div>
            </div>
        </div>
    @endif
</div>
