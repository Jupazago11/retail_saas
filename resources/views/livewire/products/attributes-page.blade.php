<div class="py-10">
    <div class="mx-auto grid max-w-7xl gap-6 px-4 sm:px-6 lg:grid-cols-[1fr_1.35fr] lg:px-8">
        <div class="space-y-6">
            <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-[0.22em] text-blue-700">Atributo</p>
                        <h3 class="mt-2 text-2xl font-black text-gray-900">
                            {{ $editingAttributeId ? 'Editar atributo' : 'Nuevo atributo' }}
                        </h3>
                    </div>

                    @if ($editingAttributeId)
                        <button wire:click="resetAttributeForm" class="text-sm font-medium text-gray-500">
                            Cancelar
                        </button>
                    @endif
                </div>

                <form wire:submit="saveAttribute" class="mt-6 space-y-4">
                    <div>
                        <label for="attribute-name" class="text-sm font-medium text-gray-700">Nombre <span class="text-rose-600">*</span></label>
                        <input wire:model="name" id="attribute-name" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600">
                        @error('name') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="attribute-code" class="text-sm font-medium text-gray-700">Codigo <span class="text-rose-600">*</span></label>
                        <input wire:model="code" id="attribute-code" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600">
                        @error('code') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                    </div>

                    <button type="submit" class="inline-flex rounded-full bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white">
                        {{ $editingAttributeId ? 'Actualizar atributo' : 'Guardar atributo' }}
                    </button>
                </form>
            </div>

            <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-[0.22em] text-blue-700">Valor</p>
                        <h3 class="mt-2 text-2xl font-black text-gray-900">
                            {{ $editingValueId ? 'Editar valor' : 'Nuevo valor' }}
                        </h3>
                    </div>

                    @if ($editingValueId)
                        <button wire:click="resetValueForm" class="text-sm font-medium text-gray-500">
                            Cancelar
                        </button>
                    @endif
                </div>

                @if ($selectedAttribute)
                    <p class="mt-3 text-sm text-gray-500">
                        Trabajando sobre: <span class="font-semibold text-gray-900">{{ $selectedAttribute->name }}</span>
                    </p>

                    <form wire:submit="saveValue" class="mt-6 space-y-4">
                        <div>
                            <label for="attribute-value" class="text-sm font-medium text-gray-700">Valor <span class="text-rose-600">*</span></label>
                            <input wire:model="value" id="attribute-value" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600">
                            @error('value') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                        </div>

                        <button type="submit" class="inline-flex rounded-full bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white">
                            {{ $editingValueId ? 'Actualizar valor' : 'Guardar valor' }}
                        </button>
                    </form>
                @else
                    <p class="mt-4 rounded-lg border border-gray-200 bg-gray-50 px-4 py-4 text-sm text-gray-600">
                        Selecciona un atributo del listado para administrar sus valores.
                    </p>
                @endif
            </div>
        </div>

        <div class="space-y-6">
            <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                <div class="flex items-end justify-between gap-4">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-[0.22em] text-blue-700">Listado</p>
                        <h3 class="mt-2 text-2xl font-black text-gray-900">Atributos registrados</h3>
                    </div>
                    <p class="text-sm text-gray-500">{{ $attributes->count() }} registros</p>
                </div>

                <div class="mt-6 overflow-x-auto">
                    <table class="min-w-full divide-y divide-stone-200 text-sm">
                        <thead>
                            <tr class="text-left text-gray-500">
                                <th class="pb-3 font-medium">Atributo</th>
                                <th class="pb-3 font-medium">Valores</th>
                                <th class="pb-3 font-medium">Estado</th>
                                <th class="pb-3 font-medium text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-stone-100">
                            @forelse ($attributes as $attribute)
                                <tr wire:key="attribute-{{ $attribute->id }}" class="even:bg-gray-50">
                                    <td class="py-4">
                                        <p class="font-semibold text-gray-900">{{ $attribute->name }}</p>
                                        <p class="text-xs text-gray-500">{{ $attribute->code }}</p>
                                    </td>
                                    <td class="py-4 text-gray-600">{{ $attribute->values->count() }}</td>
                                    <td class="py-4">
                                        <x-status-badge :color="$attribute->status === 'active' ? 'emerald' : ($attribute->status === 'inactive' ? 'stone' : 'amber')">
                                            {{ $attribute->status }}
                                        </x-status-badge>
                                    </td>
                                    <td class="py-4 text-right">
                                        <div class="flex justify-end gap-2">
                                            <button wire:click="selectAttribute({{ $attribute->id }})" class="rounded-full border border-sky-300 px-3 py-1 font-medium text-sky-700">
                                                Valores
                                            </button>
                                            <button wire:click="editAttribute({{ $attribute->id }})" class="rounded-full border border-gray-300 px-3 py-1 font-medium text-gray-700">
                                                Editar
                                            </button>
                                            @if ($attribute->status === 'archived')
                                                <button wire:click="restoreAttribute({{ $attribute->id }})" wire:confirm="¿Restaurar este atributo?" class="rounded-full border border-emerald-300 px-3 py-1 font-medium text-emerald-700">
                                                    Restaurar
                                                </button>
                                            @else
                                                <button wire:click="toggleAttributeStatus({{ $attribute->id }})" class="rounded-full border border-blue-300 px-3 py-1 font-medium text-blue-700">
                                                    {{ $attribute->status === 'active' ? 'Desactivar' : 'Activar' }}
                                                </button>
                                                <button wire:click="archiveAttribute({{ $attribute->id }})" class="rounded-full border border-rose-300 px-3 py-1 font-medium text-rose-700">
                                                    Archivar
                                                </button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="py-6 text-center text-gray-500">Aun no hay atributos registrados para esta empresa.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                <div class="flex items-end justify-between gap-4">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-[0.22em] text-blue-700">Valores</p>
                        <h3 class="mt-2 text-2xl font-black text-gray-900">
                            {{ $selectedAttribute ? 'Valores de '.$selectedAttribute->name : 'Selecciona un atributo' }}
                        </h3>
                    </div>
                </div>

                @if ($selectedAttribute)
                    <div class="mt-6 overflow-x-auto">
                        <table class="min-w-full divide-y divide-stone-200 text-sm">
                            <thead>
                                <tr class="text-left text-gray-500">
                                    <th class="pb-3 font-medium">Valor</th>
                                    <th class="pb-3 font-medium">Estado</th>
                                    <th class="pb-3 font-medium text-right">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-stone-100">
                                @forelse ($selectedAttribute->values as $attributeValue)
                                    <tr wire:key="attribute-value-{{ $attributeValue->id }}" class="even:bg-gray-50">
                                        <td class="py-4 font-medium text-gray-900">{{ $attributeValue->value }}</td>
                                        <td class="py-4">
                                            <x-status-badge :color="$attributeValue->status === 'active' ? 'emerald' : ($attributeValue->status === 'inactive' ? 'stone' : 'amber')">
                                                {{ $attributeValue->status }}
                                            </x-status-badge>
                                        </td>
                                        <td class="py-4 text-right">
                                            <div class="flex justify-end gap-2">
                                                <button wire:click="editValue({{ $attributeValue->id }})" class="rounded-full border border-gray-300 px-3 py-1 font-medium text-gray-700">
                                                    Editar
                                                </button>
                                                @if ($attributeValue->status === 'archived')
                                                    <button wire:click="restoreValue({{ $attributeValue->id }})" wire:confirm="¿Restaurar este valor?" class="rounded-full border border-emerald-300 px-3 py-1 font-medium text-emerald-700">
                                                        Restaurar
                                                    </button>
                                                @else
                                                    <button wire:click="toggleValueStatus({{ $attributeValue->id }})" class="rounded-full border border-blue-300 px-3 py-1 font-medium text-blue-700">
                                                        {{ $attributeValue->status === 'active' ? 'Desactivar' : 'Activar' }}
                                                    </button>
                                                    <button wire:click="archiveValue({{ $attributeValue->id }})" class="rounded-full border border-rose-300 px-3 py-1 font-medium text-rose-700">
                                                        Archivar
                                                    </button>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="py-6 text-center text-gray-500">Aun no hay valores para este atributo.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="mt-4 rounded-lg border border-gray-200 bg-gray-50 px-4 py-4 text-sm text-gray-600">
                        Selecciona un atributo para ver y administrar sus valores.
                    </p>
                @endif
            </div>
        </div>
    </div>
</div>
