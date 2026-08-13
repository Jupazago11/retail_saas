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
                    <select wire:model.live="filterBrandId"
                        class="rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600 text-sm">
                        <option value="">Todas las marcas</option>
                        @foreach ($brands as $brand)
                            <option value="{{ $brand->id }}">{{ $brand->name }}</option>
                        @endforeach
                    </select>
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
                                        <p>{{ $product->tracks_inventory ? 'Con inventario' : 'Sin inventario' }}</p>
                                    </div>
                                </td>
                                <td class="py-2 align-middle text-gray-600 text-xs">
                                    <p>{{ $product->category->name }}</p>
                                    <p>{{ $product->brand?->name ?: '—' }}</p>
                                    <p>{{ $product->baseUnit->name }} ({{ $product->baseUnit->code }})</p>
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

    {{-- Boton flotante + --}}
    <button wire:click="openModal" title="Nuevo producto"
        style="position:fixed;bottom:2rem;right:2rem;z-index:9999;width:3.5rem;height:3.5rem;border-radius:9999px;background:#2563eb;color:#fff;border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;box-shadow:0 8px 24px rgba(0,0,0,0.25);"
        onmouseover="this.style.background='#b45309'" onmouseout="this.style.background='#1c1917'">
        <svg xmlns="http://www.w3.org/2000/svg" style="width:1.75rem;height:1.75rem" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
        </svg>
    </button>

    {{-- Modal crear / editar producto --}}
    @if ($showModal)
        <div wire:click.self="closeModal"
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

                        {{-- Categoria / Marca / Unidad / Ref fiscal --}}
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div x-data="{ open: false, n: '' }">
                                <div class="flex items-center justify-between mb-1">
                                    <label for="product-category" class="text-xs font-medium text-gray-700">Categoria</label>
                                    <button type="button" x-on:click="open = !open"
                                        class="text-xs font-semibold text-blue-700 hover:underline">+ Nueva</button>
                                </div>
                                <x-searchable-select
                                    id="product-category"
                                    model="categoryId"
                                    :options="$categories->map(fn ($category) => ['id' => $category->id, 'label' => $category->name])"
                                />
                                <div x-show="open" x-cloak class="mt-1.5 flex gap-1.5">
                                    <input x-model="n" type="text" placeholder="Nombre"
                                        class="flex-1 rounded-lg border border-gray-300 px-2.5 py-1.5 text-sm focus:border-blue-400 focus:outline-none">
                                    <button type="button"
                                        x-on:click="$wire.call('saveQuickCategory', n).then(function() { open = false; n = ''; })"
                                        class="rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-blue-700">Crear</button>
                                    <button type="button" x-on:click="open = false; n = ''"
                                        class="text-gray-400 hover:text-gray-600 px-1">×</button>
                                </div>
                                @error('categoryId') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                            </div>

                            <div x-data="{ open: false, n: '' }">
                                <div class="flex items-center justify-between mb-1">
                                    <label for="product-brand" class="text-xs font-medium text-gray-700">Marca</label>
                                    <button type="button" x-on:click="open = !open"
                                        class="text-xs font-semibold text-blue-700 hover:underline">+ Nueva</button>
                                </div>
                                <x-searchable-select
                                    id="product-brand"
                                    model="brandId"
                                    placeholder="Sin marca"
                                    :options="$brands->map(fn ($brand) => ['id' => $brand->id, 'label' => $brand->name])"
                                />
                                <div x-show="open" x-cloak class="mt-1.5 flex gap-1.5">
                                    <input x-model="n" type="text" placeholder="Nombre"
                                        class="flex-1 rounded-lg border border-gray-300 px-2.5 py-1.5 text-sm focus:border-blue-400 focus:outline-none">
                                    <button type="button"
                                        x-on:click="$wire.call('saveQuickBrand', n).then(function() { open = false; n = ''; })"
                                        class="rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-blue-700">Crear</button>
                                    <button type="button" x-on:click="open = false; n = ''"
                                        class="text-gray-400 hover:text-gray-600 px-1">×</button>
                                </div>
                                @error('brandId') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                            </div>

                            <div x-data="{ open: false, n: '', c: '' }">
                                <div class="flex items-center justify-between mb-1">
                                    <label for="product-unit" class="text-xs font-medium text-gray-700">Unidad base</label>
                                    <button type="button" x-on:click="open = !open"
                                        class="text-xs font-semibold text-blue-700 hover:underline">+ Nueva</button>
                                </div>
                                <x-searchable-select
                                    id="product-unit"
                                    model="baseUnitId"
                                    :options="$units->map(fn ($unit) => ['id' => $unit->id, 'label' => $unit->name.' ('.$unit->code.')'])"
                                />
                                <div x-show="open" x-cloak class="mt-1.5 flex gap-1.5">
                                    <input x-model="n" type="text" placeholder="Nombre (ej: Kilogramo)"
                                        class="flex-1 rounded-lg border border-gray-300 px-2.5 py-1.5 text-sm focus:border-blue-400 focus:outline-none">
                                    <input x-model="c" type="text" placeholder="Cod" maxlength="10"
                                        class="w-14 rounded-lg border border-gray-300 px-2 py-1.5 text-sm focus:border-blue-400 focus:outline-none">
                                    <button type="button"
                                        x-on:click="$wire.call('saveQuickUnit', n, c).then(function() { open = false; n = ''; c = ''; })"
                                        class="rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-blue-700">Crear</button>
                                    <button type="button" x-on:click="open = false; n = ''; c = ''"
                                        class="text-gray-400 hover:text-gray-600 px-1">×</button>
                                </div>
                                @error('baseUnitId') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label for="product-supplier" class="mb-1 block text-xs font-medium text-gray-700">Proveedor</label>
                                <x-searchable-select
                                    id="product-supplier"
                                    model="supplierId"
                                    placeholder="Sin proveedor"
                                    :options="$suppliers->map(fn ($supplier) => ['id' => $supplier->id, 'label' => $supplier->person?->full_name ?: 'Sin nombre'])"
                                />
                                @error('supplierId') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                            </div>

                        </div>

                        {{-- Codigo de barras + Nombre --}}
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div class="sm:col-span-2">
                                <div class="mb-1 flex items-center gap-2">
                                    <label for="product-barcode" class="block text-xs font-medium text-gray-700">Codigo de barras</label>
                                    <span wire:loading wire:target="lookupBarcode" class="text-xs text-blue-600">Buscando...</span>
                                </div>
                                <input wire:model="barcode"
                                       wire:keydown.enter.prevent="lookupBarcode"
                                       id="product-barcode" type="text"
                                       class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600 text-sm">
                                @error('barcode') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                            </div>
                            <div class="sm:col-span-2">
                                <label for="product-name" class="mb-1 block text-xs font-medium text-gray-700">Nombre</label>
                                <input wire:model="name" id="product-name" type="text"
                                    class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600 text-sm">
                                @error('name') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        {{-- Precios --}}
                        <div class="grid gap-3 grid-cols-2 sm:grid-cols-4"
                            x-data="{
                                cost: parseFloat('{{ $cost }}') || 0,
                                p1:   parseFloat('{{ $price1 }}') || 0,
                                p2:   parseFloat('{{ $price2 ?: 0 }}') || 0,
                                p3:   parseFloat('{{ $price3 ?: 0 }}') || 0,
                                moneyValue(raw) {
                                    // Money inputs are live-formatted with '.' as a thousands
                                    // separator (e.g. '20.000'); parseFloat would misread that
                                    // dot as a decimal point, so strip everything but digits.
                                    const digits = String(raw ?? '').replace(/\D+/g, '');
                                    return digits ? parseInt(digits, 10) : 0;
                                },
                                margin(price) {
                                    if (this.cost <= 0 || price <= 0) return null;
                                    return (((price - this.cost) / this.cost) * 100).toFixed(1);
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
                                <label for="product-cost" class="mb-1 block text-xs font-medium text-gray-700">Costo</label>
                                <input wire:model="cost" @input="cost = moneyValue($event.target.value)"
                                    id="product-cost" type="number" min="0" step="1"
                                    class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600 text-sm">
                                @error('cost') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="product-price-1" class="mb-1 block text-xs font-medium text-gray-700">Precio 1</label>
                                <input wire:model="price1" @input="p1 = moneyValue($event.target.value)"
                                    id="product-price-1" type="number" min="0" step="1"
                                    class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600 text-sm">
                                <p class="mt-0.5 min-h-[1rem] text-xs" :class="cls(margin(p1))">
                                    <span x-text="margin(p1) !== null ? margin(p1) + '% margen' : ''"></span>
                                </p>
                                @error('price1') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="product-price-2" class="mb-1 block text-xs font-medium text-gray-700">Precio 2</label>
                                <input wire:model="price2" @input="p2 = moneyValue($event.target.value)"
                                    id="product-price-2" type="number" min="0" step="1"
                                    class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600 text-sm">
                                <p class="mt-0.5 min-h-[1rem] text-xs" :class="cls(margin(p2))">
                                    <span x-text="p2 > 0 && margin(p2) !== null ? margin(p2) + '% margen' : ''"></span>
                                </p>
                                @error('price2') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="product-price-3" class="mb-1 block text-xs font-medium text-gray-700">Precio 3</label>
                                <input wire:model="price3" @input="p3 = moneyValue($event.target.value)"
                                    id="product-price-3" type="number" min="0" step="1"
                                    class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600 text-sm">
                                <p class="mt-0.5 min-h-[1rem] text-xs" :class="cls(margin(p3))">
                                    <span x-text="p3 > 0 && margin(p3) !== null ? margin(p3) + '% margen' : ''"></span>
                                </p>
                                @error('price3') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
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
                                    <label for="product-minimum-stock" class="mb-1 block text-xs font-medium text-gray-700">Stock minimo (alerta)</label>
                                    <input wire:model="minimumStock" id="product-minimum-stock" type="number" min="0" step="1"
                                        class="block w-full max-w-[200px] rounded-xl border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600 text-sm">
                                    @error('minimumStock') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
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
                                        @error('initialQuantities') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
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
