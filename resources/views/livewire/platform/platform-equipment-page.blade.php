<div>
    <div class="space-y-6">

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.22em] text-blue-600">Plataforma</p>
            <h1 class="mt-1 text-2xl font-black text-gray-900">Equipos</h1>
        </div>
        <button wire:click="openCreate"
            class="rounded-full bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700">
            + Nuevo equipo
        </button>
    </div>

    {{-- Tabla --}}
    <div class="rounded-xl bg-white p-6 ring-1 ring-gray-200">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-stone-200 text-sm">
                <thead>
                    <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                        <th class="pb-2">Nombre</th>
                        <th class="pb-2">Código</th>
                        <th class="pb-2 w-px whitespace-nowrap">Costo unitario</th>
                        <th class="pb-2 w-px whitespace-nowrap">Precio mensual</th>
                        <th class="pb-2 w-px whitespace-nowrap">Estado</th>
                        <th class="pb-2 w-px whitespace-nowrap text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                    @forelse ($equipmentTypes as $type)
                        <tr wire:key="equipment-type-{{ $type->id }}" class="even:bg-gray-50">
                            <td class="py-3 align-middle font-medium text-gray-900">{{ $type->name }}</td>
                            <td class="py-3 align-middle font-mono text-xs text-gray-500">{{ $type->code }}</td>
                            <td class="py-3 align-middle text-xs text-gray-600 w-px whitespace-nowrap">${{ number_format((float) $type->unit_cost, 0, ',', '.') }}</td>
                            <td class="py-3 align-middle text-xs text-gray-600 w-px whitespace-nowrap">${{ number_format((float) $type->monthly_price, 0, ',', '.') }}/mes</td>
                            <td class="py-3 align-middle w-px whitespace-nowrap">
                                <x-status-toggle :active="$type->status === 'active'" action="toggleStatus({{ $type->id }})" />
                            </td>
                            <td class="py-3 align-middle w-px whitespace-nowrap">
                                <div class="flex justify-end gap-2">
                                    <button wire:click="startEdit({{ $type->id }})"
                                        class="rounded-full border border-gray-300 px-3 py-1 text-xs font-semibold text-gray-600 transition hover:border-blue-400 hover:text-blue-700">
                                        Editar
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-10 text-center text-xs text-gray-400">Sin equipos creados.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    </div>

    {{-- Modal --}}
    @if ($showModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl ring-1 ring-gray-200">
                <h3 class="text-lg font-black text-gray-900">{{ $editingId ? 'Editar equipo' : 'Nuevo equipo' }}</h3>

                <div class="mt-5 space-y-4">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Nombre <span class="text-rose-600">*</span></label>
                        <input wire:model="name" type="text" autofocus placeholder="Ej: Impresora termica"
                            class="mt-1 w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-600 focus:ring-blue-600">
                    </div>

                    <div x-data="digitGroupInput({ path: 'unitCost', live: false })">
                        <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Costo unitario <span class="text-rose-600">*</span></label>
                        <input type="text" inputmode="numeric" @input="onInput($event)"
                            value="{{ $unitCost !== '' ? number_format((int) $unitCost, 0, ',', '.') : '' }}"
                            placeholder="110.000"
                            class="mt-1 w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-600 focus:ring-blue-600">
                        <p class="mt-1 text-xs text-gray-400">Lo que le cuesta a la plataforma comprar una unidad.</p>
                    </div>

                    <div x-data="digitGroupInput({ path: 'monthlyPrice', live: false })">
                        <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Precio mensual <span class="text-rose-600">*</span></label>
                        <input type="text" inputmode="numeric" @input="onInput($event)"
                            value="{{ $monthlyPrice !== '' ? number_format((int) $monthlyPrice, 0, ',', '.') : '' }}"
                            placeholder="15.000"
                            class="mt-1 w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-600 focus:ring-blue-600">
                        <p class="mt-1 text-xs text-gray-400">Lo que se le factura a la empresa por unidad alquilada.</p>
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <button wire:click="closeModal"
                        class="rounded-full border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-600 transition hover:bg-gray-50">
                        Cancelar
                    </button>
                    <button wire:click="save"
                        class="rounded-full bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-700">
                        {{ $editingId ? 'Actualizar' : 'Crear equipo' }}
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
