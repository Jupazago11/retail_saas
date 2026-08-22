<div class="py-10">
    <div class="mx-auto grid max-w-7xl gap-6 px-4 sm:px-6 lg:grid-cols-[0.95fr_1.35fr] lg:px-8">
        <div class="space-y-6">
            <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-[0.22em] text-blue-700">Formulario</p>
                        <h3 class="mt-2 text-2xl font-black text-gray-900">
                            {{ $editingBrandId ? 'Editar marca' : 'Nueva marca' }}
                        </h3>
                    </div>

                    @if ($editingBrandId)
                        <button wire:click="resetBrandForm" class="text-sm font-medium text-gray-500">
                            Cancelar
                        </button>
                    @endif
                </div>

                <form wire:submit="saveBrand" class="mt-6 space-y-4">
                    <div>
                        <label for="brand-name" class="text-sm font-medium text-gray-700">Nombre <span class="text-rose-600">*</span></label>
                        <input wire:model="name" id="brand-name" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600">
                    </div>

                    <button type="submit" class="inline-flex rounded-full bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white">
                        {{ $editingBrandId ? 'Actualizar marca' : 'Guardar marca' }}
                    </button>
                </form>
            </div>
        </div>

        <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
            <div class="flex items-end justify-between gap-4">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-[0.22em] text-blue-700">Listado</p>
                    <h3 class="mt-2 text-2xl font-black text-gray-900">Marcas registradas</h3>
                </div>
                <p class="text-sm text-gray-500">{{ $brands->count() }} registros</p>
            </div>

            <div class="mt-6 overflow-x-auto">
                <table class="min-w-full divide-y divide-stone-200 text-sm">
                    <thead>
                        <tr class="text-left text-gray-500">
                            <th class="pb-3 font-medium">Nombre</th>
                            <th class="pb-3 font-medium">Estado</th>
                            <th class="pb-3 font-medium text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-100">
                        @forelse ($brands as $brand)
                            <tr wire:key="brand-{{ $brand->id }}" class="even:bg-gray-50">
                                <td class="py-4 font-medium text-gray-900">{{ $brand->name }}</td>
                                <td class="py-4">
                                    <x-status-badge :color="$brand->status === 'active' ? 'emerald' : ($brand->status === 'inactive' ? 'stone' : 'amber')">
                                        {{ $brand->status }}
                                    </x-status-badge>
                                </td>
                                <td class="py-4 text-right">
                                    <div class="flex justify-end gap-2">
                                        @if ($brand->deleted_at)
                                            <button wire:click="restoreBrand({{ $brand->id }})" wire:confirm="¿Restaurar esta marca?" class="rounded-full border border-emerald-300 px-3 py-1 font-medium text-emerald-700">
                                                Restaurar
                                            </button>
                                        @else
                                            <button wire:click="editBrand({{ $brand->id }})" class="rounded-full border border-gray-300 px-3 py-1 font-medium text-gray-700">
                                                Editar
                                            </button>
                                            <button wire:click="toggleBrandStatus({{ $brand->id }})" class="rounded-full border border-blue-300 px-3 py-1 font-medium text-blue-700">
                                                {{ $brand->status === 'active' ? 'Desactivar' : 'Activar' }}
                                            </button>
                                            <button wire:click="archiveBrand({{ $brand->id }})" class="rounded-full border border-rose-300 px-3 py-1 font-medium text-rose-700">
                                                Archivar
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="py-6 text-center text-gray-500">Aun no hay marcas creadas para esta empresa.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
