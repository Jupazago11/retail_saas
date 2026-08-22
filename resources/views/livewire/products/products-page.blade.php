<div class="pb-10">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-6">

        <div x-data="responsivePageSize({ rowHeight: 72, reserved: 320 })" class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-[0.22em] text-blue-700">Catalogo</p>
                    <h3 class="mt-1 text-2xl font-black text-gray-900">Productos</h3>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <input wire:model.live.debounce.300ms="search" type="text" placeholder="Buscar por nombre o codigo de barras"
                        class="w-64 rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600 text-sm">
                    <x-searchable-select
                        id="products-filter-brand"
                        model="filterBrandId"
                        placeholder="Todas las marcas"
                        class="w-48"
                        live
                        :options="$brands->map(fn ($brand) => ['id' => $brand->id, 'label' => $brand->name])"
                    />
                    <div class="inline-flex rounded-lg border border-gray-200 bg-gray-50 p-1 text-sm">
                        <button type="button" wire:click="setStatusFilter('all')"
                            class="rounded-md px-3 py-1.5 font-semibold transition {{ $statusFilter === 'all' ? 'bg-gradient-to-br from-blue-600 to-purple-600 text-white shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">
                            Todos
                        </button>
                        <button type="button" wire:click="setStatusFilter('active')"
                            class="rounded-md px-3 py-1.5 font-semibold transition {{ $statusFilter === 'active' ? 'bg-emerald-600 text-white shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">
                            Activos
                        </button>
                        <button type="button" wire:click="setStatusFilter('inactive')"
                            class="rounded-md px-3 py-1.5 font-semibold transition {{ $statusFilter === 'inactive' ? 'bg-stone-600 text-white shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">
                            Inactivos
                        </button>
                        <button type="button" wire:click="setStatusFilter('archived')"
                            class="rounded-md px-3 py-1.5 font-semibold transition {{ $statusFilter === 'archived' ? 'bg-amber-600 text-white shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">
                            Archivados
                        </button>
                    </div>
                </div>
            </div>

            <div class="mt-6 overflow-x-auto">
                <table class="min-w-full divide-y divide-stone-200 text-sm">
                    <thead>
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <th class="pb-2">Producto</th>
                            <th class="pb-2">Maestras</th>
                            <th class="pb-2">Precios</th>
                            <th class="pb-2 w-px whitespace-nowrap">Estado</th>
                            <th class="pb-2 w-px whitespace-nowrap text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-100">
                        @forelse ($products as $product)
                            <tr wire:key="product-{{ $product->id }}" class="even:bg-gray-50">
                                <td class="py-2 align-middle">
                                    <p class="font-semibold text-gray-900">{{ $product->name }}</p>
                                    <div class="mt-0.5 space-y-px text-xs text-gray-400">
                                        <p>{{ $product->barcode ?: '—' }}</p>
                                        @if ($hasInventory)
                                            <p>{{ $product->tracks_inventory ? 'Con inventario' : 'Sin inventario' }}</p>
                                        @endif
                                    </div>
                                </td>
                                <td class="py-2 align-middle text-gray-600 text-xs">
                                    <p>{{ $product->category->name }}</p>
                                    <p>{{ $product->brand?->name ?: '—' }}</p>
                                    <p class="font-semibold text-indigo-600">IVA {{ rtrim(rtrim(number_format((float) $product->tax_rate, 2, '.', ''), '0'), '.') }}%</p>
                                </td>
                                <td class="py-2 align-middle text-gray-600 text-xs">
                                    <p class="text-gray-400">{{ \App\Support\Money::format((float) $product->cost) }}</p>
                                    <p class="font-semibold text-gray-800">{{ \App\Support\Money::format((float) $product->price_1) }}</p>
                                    @if ($product->price_2 !== null)
                                        <p>{{ \App\Support\Money::format((float) $product->price_2) }}</p>
                                    @endif
                                    @if ($product->price_3 !== null)
                                        <p>{{ \App\Support\Money::format((float) $product->price_3) }}</p>
                                    @endif
                                </td>
                                <td class="py-2 align-middle w-px whitespace-nowrap">
                                    @if ($product->deleted_at)
                                        <x-status-badge color="amber" class="w-20">archivado</x-status-badge>
                                    @else
                                        <button wire:click="toggleProductStatus({{ $product->id }})"
                                            class="inline-flex w-20 justify-center rounded-full px-3 py-1 text-xs font-semibold transition {{ $product->status === 'active' ? 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-600/20 hover:bg-emerald-100' : 'bg-stone-200 text-gray-600 hover:bg-stone-300' }}">
                                            {{ $product->status === 'active' ? 'activo' : 'inactivo' }}
                                        </button>
                                    @endif
                                </td>
                                <td class="py-2 align-middle">
                                    <div class="flex justify-end gap-2">
                                        @if ($product->deleted_at)
                                            <button wire:click="restoreProduct({{ $product->id }})"
                                                wire:confirm="¿Restaurar este producto?"
                                                class="rounded-full border border-emerald-300 px-3 py-1 text-xs font-medium text-emerald-700 hover:bg-emerald-50">
                                                Restaurar
                                            </button>
                                        @else
                                            <button wire:click="editProduct({{ $product->id }})" title="Editar"
                                                class="text-gray-400 hover:text-blue-600 transition">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536M9 13l6.586-6.586a2 2 0 112.828 2.828L11.828 15.828a2 2 0 01-1.415.586H9v-2.414a2 2 0 01.586-1.415z"/>
                                                </svg>
                                            </button>
                                            <button wire:click="archiveProduct({{ $product->id }})"
                                                wire:confirm="¿Archivar este producto? Esta accion es reversible."
                                                title="Archivar"
                                                class="text-gray-400 hover:text-rose-600 transition">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                </svg>
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-10 text-center text-gray-400">
                                    Aun no hay productos registrados.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($products->hasPages())
                <div class="mt-4 border-t border-stone-100 pt-4">
                    {{ $products->links() }}
                </div>
            @endif
        </div>
    </div>

    {{-- Boton flotante + (oculto mientras hay un modal abierto para que no quede flotando sobre el overlay) --}}
    @if (! $showModal)
    <button wire:click="openModal" title="Nuevo producto"
        style="position:fixed;bottom:2rem;right:2rem;z-index:9999;width:3.5rem;height:3.5rem;border-radius:9999px;background:#2563eb;color:#fff;border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;box-shadow:0 8px 24px rgba(0,0,0,0.25);"
        onmouseover="this.style.background='#b45309'" onmouseout="this.style.background='#1c1917'">
        <svg xmlns="http://www.w3.org/2000/svg" style="width:1.75rem;height:1.75rem" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
        </svg>
    </button>
    @endif

    {{-- Modal crear / editar producto --}}
    @if ($showModal)
        {{-- wire:click.self="dismissModal" (no closeModal): un clic afuera suele
        ser sin querer, no debe botar lo que ya se escribio. Solo Cancelar/X
        descartan el formulario a proposito. --}}
        <div wire:click.self="dismissModal"
            class="fixed inset-0 z-50 flex items-center justify-center p-4"
            style="background: rgba(0,0,0,0.5);">
            <div class="w-full max-w-xl rounded-xl bg-white shadow-xl flex flex-col" style="max-height: 90vh;">

                {{-- Header pinned --}}
                <div class="flex-shrink-0 flex items-center justify-between border-b border-stone-100 px-5 py-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-widest text-blue-700">
                            {{ $editingProductId ? 'Editar' : 'Nuevo' }}
                        </p>
                        <h3 class="mt-0.5 text-lg font-black text-gray-900">Producto</h3>
                    </div>
                    <button wire:click="closeModal" class="text-gray-400 hover:text-gray-700 text-xl leading-none px-1">&times;</button>
                </div>

                {{-- Form: scrollable body + pinned footer --}}
                <form wire:submit="saveProduct" class="flex flex-col flex-1 min-h-0">
                    <div class="flex-1 overflow-y-auto px-5 py-4 space-y-3">

                        {{-- Categoria / Marca --}}
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <label for="product-category" class="mb-1 block text-xs font-medium text-gray-700">Categoria <span class="text-rose-600">*</span></label>
                                <x-searchable-select
                                    id="product-category"
                                    model="categoryId"
                                    allow-create
                                    :options="$categories->map(fn ($category) => ['id' => $category->id, 'label' => $category->name])"
                                />
                                <p class="mt-1 text-[11px] text-gray-400">Si escribes un nombre que no existe, se crea al guardar.</p>
                            </div>

                            <div>
                                <label for="product-brand" class="mb-1 block text-xs font-medium text-gray-700">Marca</label>
                                <x-searchable-select
                                    id="product-brand"
                                    model="brandId"
                                    placeholder="Sin marca"
                                    allow-create
                                    :options="$brands->map(fn ($brand) => ['id' => $brand->id, 'label' => $brand->name])"
                                />
                                <p class="mt-1 text-[11px] text-gray-400">Si escribes un nombre que no existe, se crea al guardar.</p>
                            </div>
                        </div>

                        {{-- Codigo de barras + Nombre --}}
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div class="sm:col-span-2">
                                <div class="mb-1 flex items-center gap-2">
                                    <label for="product-barcode" class="block text-xs font-medium text-gray-700">Codigo de barras</label>
                                    <span wire:loading wire:target="lookupBarcode" class="inline-flex items-center gap-1 text-xs text-blue-600">
                                        <svg class="h-3.5 w-3.5 animate-spin" viewBox="0 0 24 24" fill="none">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                        </svg>
                                        Consultando...
                                    </span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <input wire:model.live.debounce.400ms="barcode"
                                           wire:keydown.enter.prevent="lookupBarcode"
                                           wire:loading.attr="disabled" wire:target="lookupBarcode"
                                           id="product-barcode" type="text"
                                           class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600 text-sm disabled:bg-gray-50 disabled:text-gray-400">
                                    @if ($barcodePreviewSvg)
                                        <div class="flex-shrink-0 rounded-lg border border-gray-200 bg-white px-2 py-1" wire:key="barcode-preview">
                                            {!! $barcodePreviewSvg !!}
                                        </div>
                                    @endif
                                </div>
                            </div>
                            <div class="sm:col-span-2">
                                <label for="product-name" class="mb-1 block text-xs font-medium text-gray-700">Nombre <span class="text-rose-600">*</span></label>
                                <input wire:model="name" id="product-name" type="text"
                                    wire:loading.attr="disabled" wire:target="lookupBarcode"
                                    x-on:blur="$wire.set('name', $capitalize($event.target.value), false)"
                                    class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600 text-sm disabled:bg-gray-50 disabled:text-gray-400">
                            </div>
                        </div>

                        {{-- Precios --}}
                        {{-- wire:key: sin esto, wire:navigate puede dejar los efectos
                        reactivos de cls()/margin() pegados a un nodo que sobrevive el
                        morph al salir de esta pagina, tirando "cls is not defined" en
                        consola. Ver el mismo comentario en searchable-select.blade.php. --}}
                        <div class="grid gap-3 grid-cols-2 sm:grid-cols-5"
                            wire:key="product-prices-{{ $editingProductId ?? 'new' }}"
                            x-data="{
                                group(digits) {
                                    return digits ? digits.replace(/\B(?=(\d{3})+(?!\d))/g, '.') : '';
                                },
                                onInput(event, path) {
                                    const digits = event.target.value.replace(/\D+/g, '');
                                    event.target.value = this.group(digits);
                                    this.$wire.set(path, digits, false);
                                },
                                margin(priceDigits) {
                                    const cost = parseFloat($wire.cost) || 0;
                                    const price = parseFloat(priceDigits) || 0;
                                    if (cost <= 0 || price <= 0) return null;
                                    return (((price - cost) / cost) * 100).toFixed(1);
                                },
                                cls(m) {
                                    if (m === null) return 'text-stone-300';
                                    const v = parseFloat(m);
                                    if (v < 0)  return 'text-rose-600 font-semibold';
                                    if (v < 15) return 'text-blue-600 font-semibold';
                                    return 'text-emerald-600 font-semibold';
                                }
                            }">
                            <div>
                                <label for="product-cost" class="mb-1 block text-xs font-medium text-gray-700">Costo <span class="text-rose-600">*</span></label>
                                <input type="text" inputmode="numeric" @input="onInput($event, 'cost')"
                                    value="{{ $cost !== '' ? number_format((int) $cost, 0, ',', '.') : '' }}"
                                    id="product-cost"
                                    class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600 text-sm">
                            </div>
                            <div wire:key="product-tax-rate-stepper" x-data="{
                                step(delta) {
                                    const current = parseFloat(String($wire.taxRate).replace(',', '.')) || 0;
                                    const next = Math.max(0, Math.round((current + delta) * 100) / 100);
                                    $wire.taxRate = String(next);
                                },
                                onInput(event) {
                                    // Igual que Costo/Precio: si el input traia solo '0' y se
                                    // escribe un digito nuevo, el '0' sobra ('0'+'1' -> '01' por
                                    // insercion normal del navegador) — se quita, salvo que sea
                                    // parte de un decimal valido tipo '0.5'.
                                    let value = event.target.value.replace(',', '.').replace(/[^\d.]/g, '');
                                    const firstDot = value.indexOf('.');
                                    if (firstDot !== -1) {
                                        value = value.slice(0, firstDot + 1) + value.slice(firstDot + 1).replace(/\./g, '');
                                    }
                                    value = value.replace(/^0+(?=\d)/, '');
                                    event.target.value = value;
                                    $wire.taxRate = value;
                                }
                            }">
                                <label for="product-tax-rate" class="mb-1 block text-xs font-medium text-gray-700">IVA % <span class="text-rose-600">*</span></label>
                                <div class="relative">
                                    <input @input="onInput($event)"
                                        value="{{ $taxRate }}"
                                        id="product-tax-rate" type="text" inputmode="decimal" placeholder="0"
                                        class="block w-full rounded-xl border-gray-300 pr-5 shadow-sm focus:border-blue-600 focus:ring-blue-600 text-sm">
                                    <div class="absolute inset-y-0 right-0 flex flex-col justify-center gap-px pr-1.5">
                                        <button type="button" x-on:click="step(1)"
                                            class="leading-none text-gray-300 hover:text-gray-600" style="font-size: 8px;">&#9650;</button>
                                        <button type="button" x-on:click="step(-1)"
                                            class="leading-none text-gray-300 hover:text-gray-600" style="font-size: 8px;">&#9660;</button>
                                    </div>
                                </div>
                                <p class="mt-0.5 min-h-[1rem] text-xs text-gray-400">Incluido en el costo</p>
                            </div>
                            <div>
                                <label for="product-price-1" class="mb-1 block text-xs font-medium text-gray-700">Precio 1 <span class="text-rose-600">*</span></label>
                                <input type="text" inputmode="numeric" @input="onInput($event, 'price1')"
                                    value="{{ $price1 !== '' ? number_format((int) $price1, 0, ',', '.') : '' }}"
                                    id="product-price-1"
                                    class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600 text-sm">
                                <p class="mt-0.5 min-h-[1rem] text-xs" :class="cls(margin($wire.price1))">
                                    <span x-text="margin($wire.price1) !== null ? margin($wire.price1) + '% margen' : ''"></span>
                                </p>
                            </div>
                            <div>
                                <label for="product-price-2" class="mb-1 block text-xs font-medium text-gray-700">Precio 2</label>
                                <input type="text" inputmode="numeric" @input="onInput($event, 'price2')"
                                    value="{{ $price2 !== '' ? number_format((int) $price2, 0, ',', '.') : '' }}"
                                    id="product-price-2"
                                    class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600 text-sm">
                                <p class="mt-0.5 min-h-[1rem] text-xs" :class="cls(margin($wire.price2))">
                                    <span x-text="$wire.price2 > 0 && margin($wire.price2) !== null ? margin($wire.price2) + '% margen' : ''"></span>
                                </p>
                            </div>
                            <div>
                                <label for="product-price-3" class="mb-1 block text-xs font-medium text-gray-700">Precio 3</label>
                                <input type="text" inputmode="numeric" @input="onInput($event, 'price3')"
                                    value="{{ $price3 !== '' ? number_format((int) $price3, 0, ',', '.') : '' }}"
                                    id="product-price-3"
                                    class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600 text-sm">
                                <p class="mt-0.5 min-h-[1rem] text-xs" :class="cls(margin($wire.price3))">
                                    <span x-text="$wire.price3 > 0 && margin($wire.price3) !== null ? margin($wire.price3) + '% margen' : ''"></span>
                                </p>
                            </div>
                        </div>

                        @if ($hasInventory)
                        {{-- Inventario: primero se pregunta si el producto lo lleva --}}
                        <div class="rounded-xl border border-gray-200 p-3">
                            <label class="flex cursor-pointer items-center gap-3 text-sm text-gray-700">
                                <input wire:model.live="tracksInventory" type="checkbox"
                                    class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-600">
                                ¿Este producto lleva inventario?
                            </label>

                            @if ($tracksInventory)
                                <div class="mt-3">
                                    <label for="product-minimum-stock" class="mb-1 block text-xs font-medium text-gray-700">Stock minimo (alerta) <span class="text-rose-600">*</span></label>
                                    <input wire:model="minimumStock" id="product-minimum-stock" type="number" min="0" step="1"
                                        class="block w-full max-w-[200px] rounded-xl border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600 text-sm">
                                </div>

                                @if ($editingProductId)
                                    <p class="mt-3 text-xs text-gray-400">Para ajustar la cantidad de un producto existente usa el modulo de Inventario.</p>
                                @elseif ($warehouses->isNotEmpty())
                                    <div class="mt-3">
                                        <p class="mb-1 text-xs font-medium text-gray-700">
                                            Cantidad inicial{{ $warehouses->count() > 1 ? ' por bodega' : '' }}
                                        </p>
                                        <div class="grid gap-2 sm:grid-cols-2">
                                            @foreach ($warehouses as $warehouse)
                                                <div>
                                                    @if ($warehouses->count() > 1)
                                                        <label class="mb-1 block text-xs text-gray-500">{{ $warehouse->name }}</label>
                                                    @endif
                                                    <input wire:model="initialQuantities.{{ $warehouse->id }}" type="number" min="0" step="1" placeholder="0"
                                                        class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600 text-sm">
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            @endif
                        </div>
                        @endif

                    </div>{{-- /scrollable body --}}

                    {{-- Footer pinned --}}
                    <div class="flex-shrink-0 flex gap-2 border-t border-stone-100 px-5 py-3">
                        <button type="button" wire:click="closeModal"
                            class="rounded-full border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                            Cancelar
                        </button>
                        <button type="submit"
                            class="rounded-full bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-60"
                            >
                            {{ $editingProductId ? 'Actualizar' : 'Guardar' }}
                        </button>
                    </div>
                </form>

            </div>
        </div>
    @endif
</div>
