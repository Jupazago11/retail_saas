<div class="py-10">
    <div class="mx-auto grid max-w-7xl gap-6 px-4 sm:px-6 lg:grid-cols-[1fr_1.4fr] lg:px-8">
        <div class="space-y-6">
            @unless ($canCreatePresentations)
                <div class="rounded-xl border border-amber-200 bg-amber-50 p-5 text-amber-900">
                    <p class="text-sm font-semibold uppercase tracking-[0.22em]">Prerequisitos</p>
                    <p class="mt-2 text-sm">
                        Antes de crear presentaciones debes tener al menos un producto y una unidad activa en esta empresa.
                    </p>
                </div>
            @endunless

            <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-[0.22em] text-blue-700">Formulario</p>
                        <h3 class="mt-2 text-2xl font-black text-gray-900">
                            {{ $editingPresentationId ? 'Editar presentacion' : 'Nueva presentacion' }}
                        </h3>
                    </div>

                    @if ($editingPresentationId)
                        <button wire:click="resetPresentationForm" class="text-sm font-medium text-gray-500">
                            Cancelar
                        </button>
                    @endif
                </div>

                <form wire:submit="savePresentation" class="mt-6 space-y-5">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="presentation-product" class="text-sm font-medium text-gray-700">Producto <span class="text-rose-600">*</span></label>
                            <select wire:model="productId" id="presentation-product" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600" @disabled(! $canCreatePresentations)>
                                <option value="">Selecciona</option>
                                @foreach ($products as $product)
                                    <option value="{{ $product->id }}">{{ $product->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="presentation-unit" class="text-sm font-medium text-gray-700">Unidad de presentacion <span class="text-rose-600">*</span></label>
                            <select wire:model="unitId" id="presentation-unit" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600" @disabled(! $canCreatePresentations)>
                                <option value="">Selecciona</option>
                                @foreach ($units as $unit)
                                    <option value="{{ $unit->id }}">{{ $unit->name }} ({{ $unit->code }})</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="presentation-name" class="text-sm font-medium text-gray-700">Nombre <span class="text-rose-600">*</span></label>
                            <input wire:model="name" id="presentation-name" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600" @disabled(! $canCreatePresentations)>
                        </div>

                        <div>
                            <label for="presentation-barcode" class="text-sm font-medium text-gray-700">Codigo de barras</label>
                            <input wire:model="barcode" id="presentation-barcode" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600" @disabled(! $canCreatePresentations)>
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        <div>
                            <label for="presentation-factor" class="text-sm font-medium text-gray-700">Factor de conversion <span class="text-rose-600">*</span></label>
                            <input wire:model.live="conversionFactor" id="presentation-factor" type="number" min="0.000001" step="0.000001" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600" @disabled(! $canCreatePresentations)>
                        </div>

                        <div>
                            <label for="presentation-price-1" class="text-sm font-medium text-gray-700">Precio 1 <span class="text-rose-600">*</span></label>
                            <input wire:model="price1" id="presentation-price-1" type="number" min="0" step="0.01" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600" @disabled(! $canCreatePresentations)>
                        </div>

                        <div>
                            <label for="presentation-price-2" class="text-sm font-medium text-gray-700">Precio 2</label>
                            <input wire:model="price2" id="presentation-price-2" type="number" min="0" step="0.01" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600" @disabled(! $canCreatePresentations)>
                        </div>

                        <div>
                            <label for="presentation-price-3" class="text-sm font-medium text-gray-700">Precio 3</label>
                            <input wire:model="price3" id="presentation-price-3" type="number" min="0" step="0.01" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600" @disabled(! $canCreatePresentations)>
                        </div>
                    </div>

                    <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                        <div class="grid gap-4 sm:grid-cols-[0.8fr_1.2fr] sm:items-end">
                            <div>
                                <label for="sample-quantity" class="text-sm font-medium text-gray-700">Cantidad de ejemplo</label>
                                <input wire:model.live="samplePresentationQuantity" id="sample-quantity" type="number" min="0.000001" step="0.000001" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600">
                            </div>
                            <div class="text-sm text-gray-600">
                                <p class="font-semibold text-gray-900">Preview de conversion</p>
                                <p class="mt-1">
                                    {{ $samplePresentationQuantity ?: '0' }} presentacion(es) equivalen a
                                    <span class="font-semibold text-blue-700">{{ $conversionPreview ?? '0' }}</span>
                                    unidad(es) base.
                                </p>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="inline-flex rounded-full bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-60" @disabled(! $canCreatePresentations)>
                        {{ $editingPresentationId ? 'Actualizar presentacion' : 'Guardar presentacion' }}
                    </button>
                </form>
            </div>
        </div>

        <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-[0.22em] text-blue-700">Listado</p>
                    <h3 class="mt-2 text-2xl font-black text-gray-900">Presentaciones registradas</h3>
                </div>

                <div class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center">
                    <input wire:model.live.debounce.300ms="search" type="text" placeholder="Buscar por presentacion, producto o codigo" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600">

                    <label class="flex items-center gap-2 text-sm text-gray-600">
                        <input wire:model.live="showArchived" type="checkbox" class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-600">
                        Ver archivadas
                    </label>
                </div>
            </div>

            <div class="mt-6 overflow-x-auto">
                <table class="min-w-full divide-y divide-stone-200 text-sm">
                    <thead>
                        <tr class="text-left text-gray-500">
                            <th class="pb-3 font-medium">Presentacion</th>
                            <th class="pb-3 font-medium">Producto</th>
                            <th class="pb-3 font-medium">Conversion</th>
                            <th class="pb-3 font-medium">Precios</th>
                            <th class="pb-3 font-medium">Estado</th>
                            <th class="pb-3 font-medium text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-100">
                        @forelse ($presentations as $presentation)
                            <tr wire:key="presentation-{{ $presentation->id }}" class="even:bg-gray-50">
                                <td class="py-4 align-top">
                                    <p class="font-semibold text-gray-900">{{ $presentation->name }}</p>
                                    <p class="mt-1 text-xs text-gray-500">{{ $presentation->barcode ?: 'Sin codigo de barras' }}</p>
                                </td>
                                <td class="py-4 align-top text-gray-600">
                                    <p>{{ $presentation->product->name }}</p>
                                    <p>{{ $presentation->unit->name }} ({{ $presentation->unit->code }})</p>
                                </td>
                                <td class="py-4 align-top text-gray-600">
                                    <p>1 presentacion = {{ rtrim(rtrim((string) $presentation->conversion_factor, '0'), '.') }} {{ $presentation->product->baseUnit->code }}</p>
                                </td>
                                <td class="py-4 align-top text-gray-600">
                                    <p>P1: {{ \App\Support\Money::format((float) $presentation->price_1) }}</p>
                                    <p>P2: {{ $presentation->price_2 !== null ? \App\Support\Money::format((float) $presentation->price_2) : 'No aplica' }}</p>
                                    <p>P3: {{ $presentation->price_3 !== null ? \App\Support\Money::format((float) $presentation->price_3) : 'No aplica' }}</p>
                                </td>
                                <td class="py-4 align-top">
                                    <x-status-badge :color="$presentation->status === 'active' ? 'emerald' : ($presentation->status === 'inactive' ? 'stone' : 'amber')">
                                        {{ $presentation->status }}
                                    </x-status-badge>
                                </td>
                                <td class="py-4 align-top text-right">
                                    <div class="flex justify-end gap-2">
                                        @if ($presentation->deleted_at)
                                            <button wire:click="restorePresentation({{ $presentation->id }})" wire:confirm="¿Restaurar esta presentacion?" class="rounded-full border border-emerald-300 px-3 py-1 font-medium text-emerald-700">
                                                Restaurar
                                            </button>
                                        @else
                                            <button wire:click="editPresentation({{ $presentation->id }})" class="rounded-full border border-gray-300 px-3 py-1 font-medium text-gray-700">
                                                Editar
                                            </button>
                                            <button wire:click="togglePresentationStatus({{ $presentation->id }})" class="rounded-full border border-blue-300 px-3 py-1 font-medium text-blue-700">
                                                {{ $presentation->status === 'active' ? 'Desactivar' : 'Activar' }}
                                            </button>
                                            <button wire:click="archivePresentation({{ $presentation->id }})" class="rounded-full border border-rose-300 px-3 py-1 font-medium text-rose-700">
                                                Archivar
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-6 text-center text-gray-500">Aun no hay presentaciones registradas para esta empresa.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
