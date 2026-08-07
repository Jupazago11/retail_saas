<div class="py-10">
    <div class="mx-auto grid max-w-7xl gap-6 px-4 sm:px-6 lg:grid-cols-[0.95fr_1.35fr] lg:px-8">
        <div class="space-y-6">
            <div class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-stone-200">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-[0.22em] text-amber-700">Formulario</p>
                        <h3 class="mt-2 text-2xl font-black text-stone-900">
                            {{ $editingUnitId ? 'Editar unidad' : 'Nueva unidad' }}
                        </h3>
                    </div>

                    @if ($editingUnitId)
                        <button wire:click="resetUnitForm" class="text-sm font-medium text-stone-500">
                            Cancelar
                        </button>
                    @endif
                </div>

                <form wire:submit="saveUnit" class="mt-6 space-y-4">
                    <div>
                        <label for="unit-code" class="text-sm font-medium text-stone-700">Codigo</label>
                        <input wire:model="code" id="unit-code" type="text" class="mt-1 block w-full rounded-2xl border-stone-300 shadow-sm focus:border-amber-500 focus:ring-amber-500">
                        @error('code') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="unit-name" class="text-sm font-medium text-stone-700">Nombre</label>
                        <input wire:model="name" id="unit-name" type="text" class="mt-1 block w-full rounded-2xl border-stone-300 shadow-sm focus:border-amber-500 focus:ring-amber-500">
                        @error('name') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="unit-precision" class="text-sm font-medium text-stone-700">Precision decimal</label>
                        <input wire:model="precisionScale" id="unit-precision" type="number" min="0" max="6" class="mt-1 block w-full rounded-2xl border-stone-300 shadow-sm focus:border-amber-500 focus:ring-amber-500">
                        @error('precisionScale') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                    </div>

                    <button type="submit" class="inline-flex rounded-full bg-stone-900 px-5 py-2.5 text-sm font-semibold text-white">
                        {{ $editingUnitId ? 'Actualizar unidad' : 'Guardar unidad' }}
                    </button>
                </form>
            </div>
        </div>

        <div class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-stone-200">
            <div class="flex items-end justify-between gap-4">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-[0.22em] text-amber-700">Listado</p>
                    <h3 class="mt-2 text-2xl font-black text-stone-900">Unidades registradas</h3>
                </div>
                <p class="text-sm text-stone-500">{{ $units->count() }} registros</p>
            </div>

            <div class="mt-6 overflow-x-auto">
                <table class="min-w-full divide-y divide-stone-200 text-sm">
                    <thead>
                        <tr class="text-left text-stone-500">
                            <th class="pb-3 font-medium">Codigo</th>
                            <th class="pb-3 font-medium">Nombre</th>
                            <th class="pb-3 font-medium">Precision</th>
                            <th class="pb-3 font-medium">Estado</th>
                            <th class="pb-3 font-medium text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-100">
                        @forelse ($units as $unit)
                            <tr wire:key="unit-{{ $unit->id }}">
                                <td class="py-4 font-medium text-stone-900">{{ $unit->code }}</td>
                                <td class="py-4 text-stone-700">{{ $unit->name }}</td>
                                <td class="py-4 text-stone-600">{{ $unit->precision_scale }}</td>
                                <td class="py-4">
                                    <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $unit->status === 'active' ? 'bg-emerald-100 text-emerald-700' : ($unit->status === 'inactive' ? 'bg-stone-200 text-stone-700' : 'bg-amber-100 text-amber-700') }}">
                                        {{ $unit->status }}
                                    </span>
                                </td>
                                <td class="py-4 text-right">
                                    <div class="flex justify-end gap-2">
                                        <button wire:click="editUnit({{ $unit->id }})" class="rounded-full border border-stone-300 px-3 py-1 font-medium text-stone-700">
                                            Editar
                                        </button>
                                        @if ($unit->status === 'archived')
                                            <button wire:click="restoreUnit({{ $unit->id }})" class="rounded-full border border-emerald-300 px-3 py-1 font-medium text-emerald-700">
                                                Restaurar
                                            </button>
                                        @else
                                            <button wire:click="toggleUnitStatus({{ $unit->id }})" class="rounded-full border border-amber-300 px-3 py-1 font-medium text-amber-700">
                                                {{ $unit->status === 'active' ? 'Desactivar' : 'Activar' }}
                                            </button>
                                            <button wire:click="archiveUnit({{ $unit->id }})" class="rounded-full border border-rose-300 px-3 py-1 font-medium text-rose-700">
                                                Archivar
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-6 text-center text-stone-500">Aun no hay unidades creadas para esta empresa.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
