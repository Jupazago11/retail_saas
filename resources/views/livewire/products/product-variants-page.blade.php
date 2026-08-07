<div class="py-10">
    <div class="mx-auto grid max-w-7xl gap-6 px-4 sm:px-6 lg:grid-cols-[1fr_1.45fr] lg:px-8">
        <div class="space-y-6">
            @unless ($canCreateVariants)
                <div class="rounded-3xl border border-amber-200 bg-amber-50 p-5 text-amber-900">
                    <p class="text-sm font-semibold uppercase tracking-[0.22em]">Prerequisitos</p>
                    <p class="mt-2 text-sm">
                        Antes de crear variantes debes tener al menos un producto activo y atributos con valores activos.
                    </p>
                </div>
            @endunless

            <div class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-stone-200">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-[0.22em] text-amber-700">Variante</p>
                        <h3 class="mt-2 text-2xl font-black text-stone-900">
                            {{ $editingVariantId ? 'Editar variante' : 'Nueva variante' }}
                        </h3>
                    </div>

                    @if ($editingVariantId)
                        <button wire:click="resetVariantForm" class="text-sm font-medium text-stone-500">
                            Cancelar
                        </button>
                    @endif
                </div>

                <form wire:submit="saveVariant" class="mt-6 space-y-5">
                    <div>
                        <label for="variant-product" class="text-sm font-medium text-stone-700">Producto</label>
                        <select wire:model="productId" id="variant-product" class="mt-1 block w-full rounded-2xl border-stone-300 shadow-sm focus:border-amber-500 focus:ring-amber-500" @disabled(! $canCreateVariants)>
                            <option value="">Selecciona</option>
                            @foreach ($products as $product)
                                <option value="{{ $product->id }}">{{ $product->name }}</option>
                            @endforeach
                        </select>
                        @error('productId') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="variant-sku" class="text-sm font-medium text-stone-700">SKU</label>
                            <input wire:model="sku" id="variant-sku" type="text" class="mt-1 block w-full rounded-2xl border-stone-300 shadow-sm focus:border-amber-500 focus:ring-amber-500" @disabled(! $canCreateVariants)>
                            @error('sku') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="variant-barcode" class="text-sm font-medium text-stone-700">Codigo de barras</label>
                            <input wire:model="barcode" id="variant-barcode" type="text" class="mt-1 block w-full rounded-2xl border-stone-300 shadow-sm focus:border-amber-500 focus:ring-amber-500" @disabled(! $canCreateVariants)>
                            @error('barcode') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div>
                        <label for="variant-price-override" class="text-sm font-medium text-stone-700">Precio override</label>
                        <input wire:model="priceOverride" id="variant-price-override" type="number" min="0" step="0.01" class="mt-1 block w-full rounded-2xl border-stone-300 shadow-sm focus:border-amber-500 focus:ring-amber-500" @disabled(! $canCreateVariants)>
                        @error('priceOverride') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="rounded-2xl border border-stone-200 bg-stone-50 p-4">
                        <p class="text-sm font-semibold uppercase tracking-[0.18em] text-stone-600">Combinacion</p>
                        <div class="mt-4 space-y-4">
                            @forelse ($attributes as $attribute)
                                @if ($attribute->values->isNotEmpty())
                                    <div>
                                        <p class="text-sm font-semibold text-stone-900">{{ $attribute->name }}</p>
                                        <div class="mt-2 flex flex-wrap gap-2">
                                            @foreach ($attribute->values as $attributeValue)
                                                <label class="inline-flex items-center gap-2 rounded-full border border-stone-200 bg-white px-3 py-2 text-sm text-stone-700">
                                                    <input wire:model="selectedAttributeValueIds" type="checkbox" value="{{ $attributeValue->id }}" class="rounded border-stone-300 text-amber-600 shadow-sm focus:ring-amber-500" @disabled(! $canCreateVariants)>
                                                    {{ $attributeValue->value }}
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            @empty
                                <p class="text-sm text-stone-500">Aun no hay atributos activos con valores disponibles.</p>
                            @endforelse
                        </div>
                        @error('selectedAttributeValueIds') <p class="mt-3 text-sm text-rose-600">{{ $message }}</p> @enderror
                    </div>

                    <button type="submit" class="inline-flex rounded-full bg-stone-900 px-5 py-2.5 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-60" @disabled(! $canCreateVariants)>
                        {{ $editingVariantId ? 'Actualizar variante' : 'Guardar variante' }}
                    </button>
                </form>
            </div>
        </div>

        <div class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-stone-200">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-[0.22em] text-amber-700">Listado</p>
                    <h3 class="mt-2 text-2xl font-black text-stone-900">Variantes registradas</h3>
                </div>

                <div class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center">
                    <input wire:model.live.debounce.300ms="search" type="text" placeholder="Buscar por producto, SKU o codigo" class="w-full rounded-2xl border-stone-300 shadow-sm focus:border-amber-500 focus:ring-amber-500">

                    <label class="flex items-center gap-2 text-sm text-stone-600">
                        <input wire:model.live="showArchived" type="checkbox" class="rounded border-stone-300 text-amber-600 shadow-sm focus:ring-amber-500">
                        Ver archivadas
                    </label>
                </div>
            </div>

            <div class="mt-6 overflow-x-auto">
                <table class="min-w-full divide-y divide-stone-200 text-sm">
                    <thead>
                        <tr class="text-left text-stone-500">
                            <th class="pb-3 font-medium">Producto</th>
                            <th class="pb-3 font-medium">Combinacion</th>
                            <th class="pb-3 font-medium">Referencia</th>
                            <th class="pb-3 font-medium">Estado</th>
                            <th class="pb-3 font-medium text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-100">
                        @forelse ($variants as $variant)
                            <tr wire:key="variant-{{ $variant->id }}">
                                <td class="py-4 align-top">
                                    <p class="font-semibold text-stone-900">{{ $variant->product->name }}</p>
                                    <p class="mt-1 text-xs text-stone-500">
                                        {{ $variant->price_override !== null ? 'Override: '.\App\Support\Money::format((float) $variant->price_override) : 'Sin override de precio' }}
                                    </p>
                                </td>
                                <td class="py-4 align-top text-stone-600">
                                    @foreach ($variant->attributeValues->sortBy(fn ($value) => $value->attribute->name.'-'.$value->value) as $attributeValue)
                                        <p>{{ $attributeValue->attribute->name }}: {{ $attributeValue->value }}</p>
                                    @endforeach
                                </td>
                                <td class="py-4 align-top text-stone-600">
                                    <p>SKU: {{ $variant->sku ?: 'Sin SKU' }}</p>
                                    <p>Codigo: {{ $variant->barcode ?: 'Sin codigo' }}</p>
                                </td>
                                <td class="py-4 align-top">
                                    <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $variant->status === 'active' ? 'bg-emerald-100 text-emerald-700' : ($variant->status === 'inactive' ? 'bg-stone-200 text-stone-700' : 'bg-amber-100 text-amber-700') }}">
                                        {{ $variant->status }}
                                    </span>
                                </td>
                                <td class="py-4 align-top text-right">
                                    <div class="flex justify-end gap-2">
                                        <button wire:click="editVariant({{ $variant->id }})" class="rounded-full border border-stone-300 px-3 py-1 font-medium text-stone-700">
                                            Editar
                                        </button>
                                        @if ($variant->status === 'archived')
                                            <button wire:click="restoreVariant({{ $variant->id }})" class="rounded-full border border-emerald-300 px-3 py-1 font-medium text-emerald-700">
                                                Restaurar
                                            </button>
                                        @else
                                            <button wire:click="toggleVariantStatus({{ $variant->id }})" class="rounded-full border border-amber-300 px-3 py-1 font-medium text-amber-700">
                                                {{ $variant->status === 'active' ? 'Desactivar' : 'Activar' }}
                                            </button>
                                            <button wire:click="archiveVariant({{ $variant->id }})" class="rounded-full border border-rose-300 px-3 py-1 font-medium text-rose-700">
                                                Archivar
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-6 text-center text-stone-500">Aun no hay variantes registradas para esta empresa.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
