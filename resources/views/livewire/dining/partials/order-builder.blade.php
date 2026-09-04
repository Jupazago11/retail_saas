{{-- Constructor de pedido reutilizado por la vista simple (inline) y la vista de mapa (panel lateral) de WaiterOrdersPage. Espera $selectedTable, $products, $orderItems, $orderTotal, $canCharge en el scope. --}}
<div class="space-y-4">
    <div class="rounded-xl bg-white p-4 ring-1 ring-gray-200">
        <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Agregar plato</p>

        <div class="mt-3 flex flex-wrap items-end gap-3">
            <div class="min-w-[200px] flex-1">
                <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Plato</label>
                <x-searchable-select
                    class="mt-1"
                    model="productId"
                    placeholder="Buscar plato..."
                    :options="$products->map(fn ($product) => ['id' => $product->id, 'label' => $product->name.' · '.number_format((float) $product->price_1, 0, ',', '.')])"
                />
                @error('productId') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
            </div>

            <div class="w-24">
                <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Cant.</label>
                <input wire:model="quantity" type="number" min="1" step="1"
                    class="mt-1 w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-600 focus:ring-blue-600">
                @error('quantity') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
            </div>

            <div class="min-w-[160px] flex-1">
                <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Comentario</label>
                <input wire:model="notes" type="text" placeholder="Ej: sin cebolla"
                    class="mt-1 w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-600 focus:ring-blue-600">
                @error('notes') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
            </div>

            <button wire:click="addDish"
                class="rounded-full bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-700">
                Agregar
            </button>
        </div>
    </div>

    <div class="rounded-xl bg-white p-4 ring-1 ring-gray-200">
        <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Pedido de la mesa</p>

        <div class="mt-3 divide-y divide-gray-100">
            @forelse ($orderItems as $item)
                <div class="flex flex-wrap items-center justify-between gap-2 py-2.5 text-sm">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="font-semibold text-gray-800">{{ $item->product->name }}</span>
                            <span class="rounded-full px-2 py-0.5 text-xs font-medium
                                {{ match ($item->kitchen_status) {
                                    'pending' => 'bg-gray-100 text-gray-600',
                                    'preparing' => 'bg-amber-50 text-amber-700',
                                    'on_hold' => 'bg-sky-100 text-sky-700',
                                    'ready' => 'bg-emerald-50 text-emerald-700',
                                    'served' => 'bg-blue-50 text-blue-700',
                                    'cancelled' => 'bg-rose-100 text-rose-700',
                                    default => 'bg-blue-50 text-blue-700',
                                } }}">
                                {{ $this->kitchenStatusLabel($item->kitchen_status) }}
                            </span>
                        </div>
                        @if ($item->notes)
                            <p class="mt-0.5 text-xs italic text-gray-500">"{{ $item->notes }}"</p>
                        @endif
                    </div>

                    @if ($item->kitchen_status !== 'cancelled')
                        <div class="flex items-center gap-2">
                            <input type="number" min="1" step="1" value="{{ $item->quantity + 0 }}"
                                wire:change="updateItemQuantity({{ $item->id }}, $event.target.value)"
                                class="w-16 rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-600 focus:ring-blue-600">
                            <button type="button" wire:click="removeItem({{ $item->id }})" wire:confirm="¿Quitar este plato de la comanda?"
                                class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-rose-500 hover:bg-rose-50" title="Quitar">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                                    <path stroke-linecap="round" d="M6 6l12 12M18 6L6 18" />
                                </svg>
                            </button>
                        </div>
                    @endif
                </div>
            @empty
                <p class="py-6 text-center text-sm text-gray-400">Todavia no se ha agregado ningun plato.</p>
            @endforelse
        </div>
    </div>

    @if ($orderTotal)
        <div class="rounded-xl bg-white p-4 ring-1 ring-gray-200">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Cuenta</p>
            <dl class="mt-3 space-y-1.5 text-sm">
                <div class="flex justify-between"><dt class="text-gray-500">Subtotal</dt><dd>{{ number_format((float) $orderTotal['subtotal'], 0, ',', '.') }}</dd></div>
                <div class="flex justify-between border-t border-gray-100 pt-1.5 text-base font-black text-gray-900"><dt>Total</dt><dd>{{ number_format((float) $orderTotal['grand_total'], 0, ',', '.') }}</dd></div>
            </dl>

            @if ($canCharge)
                <a href="{{ route('dining.tables.order', $selectedTable) }}" wire:navigate
                    class="mt-4 block w-full rounded-full bg-emerald-600 py-2.5 text-center text-sm font-semibold text-white transition hover:bg-emerald-700">
                    Ir a cobrar
                </a>
            @endif
        </div>
    @endif
</div>
