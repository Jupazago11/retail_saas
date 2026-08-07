<div class="pb-10">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-6">

        {{-- Filtros --}}
        <div class="rounded-3xl bg-white p-5 shadow-sm ring-1 ring-stone-200">
            <div class="flex flex-wrap gap-3">
                <select wire:model.live="branchId"
                    class="rounded-2xl border-stone-300 shadow-sm focus:border-amber-500 focus:ring-amber-500 text-sm">
                    <option value="">Sucursal</option>
                    @foreach ($branches as $branch)
                        <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                    @endforeach
                </select>
                <select wire:model.live="warehouseId"
                    class="rounded-2xl border-stone-300 shadow-sm focus:border-amber-500 focus:ring-amber-500 text-sm">
                    <option value="">Bodega</option>
                    @foreach ($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                    @endforeach
                </select>
                <input wire:model.live.debounce.300ms="search" type="text" placeholder="Buscar producto o codigo"
                    class="flex-1 min-w-[200px] rounded-2xl border-stone-300 shadow-sm focus:border-amber-500 focus:ring-amber-500 text-sm">
            </div>
        </div>

        {{-- Tabla --}}
        <div class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-stone-200">
            <div class="mb-4">
                <p class="text-sm font-semibold uppercase tracking-[0.22em] text-amber-700">Inventario</p>
                <h3 class="mt-1 text-2xl font-black text-stone-900">Ajuste manual</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-stone-200 text-sm">
                    <thead>
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-stone-500">
                            <th class="pb-2">Producto</th>
                            <th class="pb-2">Categoria</th>
                            <th class="pb-2 text-right">Stock actual</th>
                            <th class="pb-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-100">
                        @forelse ($products as $product)
                            @php
                                $balance = $product->inventoryBalances->first();
                                $qty = $balance ? (float) $balance->quantity_on_hand : 0;
                            @endphp
                            <tr wire:key="inv-product-{{ $product->id }}">
                                <td class="py-2 align-middle">
                                    <p class="font-semibold text-stone-900">{{ $product->name }}</p>
                                    <p class="text-xs text-stone-400">{{ $product->barcode ?: '—' }}</p>
                                </td>
                                <td class="py-2 align-middle text-xs text-stone-500">
                                    {{ $product->category->name }}<br>
                                    {{ $product->baseUnit->code }}
                                </td>
                                <td class="py-2 align-middle text-right">
                                    <span class="text-lg font-black {{ $qty <= 0 ? 'text-rose-600' : 'text-stone-900' }}">
                                        {{ number_format($qty, 0, '.', '.') }}
                                    </span>
                                </td>
                                <td class="py-2 align-middle text-right">
                                    @if ($adjustingProductId === $product->id)
                                        <button wire:click="closeAdjustForm" class="text-xs text-stone-400 hover:text-stone-600">✕</button>
                                    @else
                                        <button wire:click="openAdjustForm({{ $product->id }})" title="Ajustar stock"
                                            class="text-stone-400 hover:text-blue-600 transition">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536M9 13l6.586-6.586a2 2 0 112.828 2.828L11.828 15.828a2 2 0 01-1.415.586H9v-2.414a2 2 0 01.586-1.415z"/>
                                            </svg>
                                        </button>
                                    @endif
                                </td>
                            </tr>

                            {{-- Formulario inline de ajuste --}}
                            @if ($adjustingProductId === $product->id)
                                <tr wire:key="adj-form-{{ $product->id }}">
                                    <td colspan="4" class="pb-4 pt-1">
                                        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4">
                                            <p class="mb-3 text-xs font-semibold uppercase tracking-widest text-amber-700">
                                                Ajustando: {{ $product->name }}
                                            </p>
                                            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                                <div>
                                                    <label class="mb-1 block text-xs font-medium text-stone-700">Tipo</label>
                                                    <select wire:model.live="adjustmentType"
                                                        class="block w-full rounded-xl border-stone-300 shadow-sm focus:border-amber-500 focus:ring-amber-500 text-sm">
                                                        <option value="increase">Entrada</option>
                                                        <option value="decrease">Salida</option>
                                                    </select>
                                                    @error('adjustmentType') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                                                </div>
                                                <div>
                                                    <label class="mb-1 block text-xs font-medium text-stone-700">Cantidad</label>
                                                    <input wire:model="quantity" type="number" min="0.001" step="0.001"
                                                        class="block w-full rounded-xl border-stone-300 shadow-sm focus:border-amber-500 focus:ring-amber-500 text-sm"
                                                        placeholder="0">
                                                    @error('quantity') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                                                </div>
                                                @if ($adjustmentType === 'increase')
                                                    <div>
                                                        <label class="mb-1 block text-xs font-medium text-stone-700">Costo unitario</label>
                                                        <input wire:model="unitCost" type="number" min="0" step="0.01"
                                                            class="block w-full rounded-xl border-stone-300 shadow-sm focus:border-amber-500 focus:ring-amber-500 text-sm"
                                                            placeholder="0.00">
                                                        @error('unitCost') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                                                    </div>
                                                @endif
                                                <div class="{{ $adjustmentType === 'increase' ? '' : 'sm:col-span-2' }}">
                                                    <label class="mb-1 block text-xs font-medium text-stone-700">Motivo</label>
                                                    <input wire:model="reason" type="text"
                                                        class="block w-full rounded-xl border-stone-300 shadow-sm focus:border-amber-500 focus:ring-amber-500 text-sm"
                                                        placeholder="Ej: Conteo fisico, merma...">
                                                    @error('reason') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                                                </div>
                                            </div>
                                            <div class="mt-3 flex gap-2">
                                                <button wire:click="saveAdjustment"
                                                    class="rounded-full bg-stone-900 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-700">
                                                    Confirmar ajuste
                                                </button>
                                                <button wire:click="closeAdjustForm"
                                                    class="rounded-full border border-stone-300 px-4 py-2 text-sm font-semibold text-stone-700 hover:bg-stone-50">
                                                    Cancelar
                                                </button>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @endif
                        @empty
                            <tr>
                                <td colspan="4" class="py-10 text-center text-stone-400">
                                    No se encontraron productos con inventario activo.
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
</div>
