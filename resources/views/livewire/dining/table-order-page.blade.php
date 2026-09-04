<div class="space-y-6">
    <a href="{{ route('dining.tables') }}" wire:navigate class="text-sm font-semibold text-blue-600 hover:underline">&larr; Volver a mesas</a>

    @if ($completedSaleIds !== [])
        <div class="rounded-xl bg-emerald-50 p-5 ring-1 ring-emerald-200">
            <p class="text-sm font-black text-emerald-900">Cuenta dividida y cobrada correctamente.</p>
            <p class="mt-1 text-xs text-emerald-700">Imprime el ticket de cada pagador:</p>
            <div class="mt-3 flex flex-wrap gap-2">
                @foreach ($completedSaleIds as $saleId)
                    <a href="{{ route('sales.ticket', $saleId) }}" target="_blank"
                        class="rounded-full bg-white px-4 py-1.5 text-xs font-semibold text-emerald-700 shadow-sm ring-1 ring-emerald-300 hover:bg-emerald-100">
                        Ticket #{{ $loop->iteration }}
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2 space-y-4">
            <div class="rounded-xl bg-white p-5 ring-1 ring-gray-200">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Agregar plato</p>

                <div class="mt-3 flex flex-wrap items-end gap-3">
                    <div class="min-w-[200px] flex-1">
                        <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Plato</label>
                        <select wire:model="productId" class="mt-1 w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-600 focus:ring-blue-600">
                            <option value="">Seleccionar...</option>
                            @foreach ($products as $product)
                                <option value="{{ $product->id }}">{{ $product->name }} &middot; {{ number_format((float) $product->price_1, 0, ',', '.') }}</option>
                            @endforeach
                        </select>
                        @error('productId') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="w-24">
                        <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Cant.</label>
                        <input wire:model="quantity" type="number" min="1" step="1"
                            class="mt-1 w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-600 focus:ring-blue-600">
                        @error('quantity') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>

                    <button wire:click="addDish"
                        class="rounded-full bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-700">
                        Agregar
                    </button>
                </div>
            </div>

            <div class="rounded-xl bg-white p-5 ring-1 ring-gray-200">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Pedido de la mesa</p>

                <div class="mt-3 divide-y divide-gray-100">
                    @forelse ($orderItems as $item)
                        <div class="flex items-center justify-between py-2.5 text-sm">
                            <div>
                                <span class="font-semibold text-gray-800">{{ $item->quantity + 0 }}x {{ $item->product->name }}</span>
                            </div>
                            <span class="rounded-full px-2.5 py-0.5 text-xs font-medium
                                {{ match($item->kitchen_status) {
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
                    @empty
                        <p class="py-6 text-center text-sm text-gray-400">Todavia no se ha agregado ningun plato.</p>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="rounded-xl bg-white p-5 ring-1 ring-gray-200">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Cuenta</p>

            @if ($orderTotal)
                <dl class="mt-3 space-y-1.5 text-sm">
                    <div class="flex justify-between"><dt class="text-gray-500">Subtotal</dt><dd>{{ number_format((float) $orderTotal['subtotal'], 0, ',', '.') }}</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">Descuentos</dt><dd>{{ number_format((float) $orderTotal['discount_total'], 0, ',', '.') }}</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">Impuestos</dt><dd>{{ number_format((float) $orderTotal['tax_total'], 0, ',', '.') }}</dd></div>
                    <div class="flex justify-between border-t border-gray-100 pt-1.5 text-base font-black text-gray-900"><dt>Total</dt><dd>{{ number_format((float) $orderTotal['grand_total'], 0, ',', '.') }}</dd></div>
                </dl>

                <a href="{{ route('dining.tables.preview-ticket', $table) }}" target="_blank"
                    class="mt-4 block w-full rounded-full border border-gray-300 py-2 text-center text-xs font-semibold text-gray-600 transition hover:bg-gray-50">
                    Imprimir cuenta (sin pagar)
                </a>

                <button wire:click="openCheckout"
                    class="mt-2 w-full rounded-full bg-emerald-600 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-700">
                    Cobrar mesa
                </button>
            @else
                <p class="mt-3 text-sm text-gray-400">Agrega al menos un plato para poder cobrar.</p>
            @endif
        </div>
    </div>

    @if ($showCheckout)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-2xl bg-white p-6 shadow-2xl">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-base font-black text-gray-900">Cobrar mesa {{ $table->name }}</h3>
                    <button type="button" wire:click="closeCheckout" class="rounded-full p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-700">
                        <x-heroicon-o-x-mark class="h-5 w-5" />
                    </button>
                </div>

                <button type="button" wire:click="toggleSplitBill"
                    class="mb-4 inline-flex items-center gap-2 rounded-full border px-4 py-1.5 text-xs font-semibold transition
                        {{ $splitBill ? 'border-blue-600 bg-blue-50 text-blue-700' : 'border-gray-300 text-gray-600 hover:bg-gray-50' }}">
                    <x-heroicon-o-users class="h-4 w-4" />
                    Dividir cuenta por items
                </button>

                @if ($splitBill)
                    <div class="mb-5 rounded-xl bg-gray-50 p-4 ring-1 ring-gray-200">
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">Asignar cada plato a un pagador</p>
                        <div class="space-y-2">
                            @foreach ($orderItems as $item)
                                <div class="flex items-center justify-between gap-2 text-sm">
                                    <span class="text-gray-700">{{ $item->quantity + 0 }}x {{ $item->product->name }}</span>
                                    <select wire:change="assignItemPayer({{ $item->id }}, $event.target.value)"
                                        class="w-40 rounded-lg border-gray-300 text-xs shadow-sm focus:border-blue-600 focus:ring-blue-600">
                                        @foreach ($payerLabels as $payerIndex => $label)
                                            <option value="{{ $payerIndex }}" @selected(($itemPayer[$item->id] ?? 0) === $payerIndex)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endforeach
                        </div>

                        <button type="button" wire:click="addPayer"
                            class="mt-3 text-xs font-semibold text-blue-600 hover:underline">
                            + Agregar otro pagador
                        </button>
                    </div>
                @endif

                <div class="space-y-4">
                    @foreach ($payerLabels as $payerIndex => $label)
                        @php
                            $subtotal = $this->payerSubtotal($payerIndex);
                            $paid = $this->payerPaidTotal($payerIndex);
                            $remaining = number_format(max(0, (float) $subtotal - (float) $paid), 0, ',', '.');
                        @endphp

                        <div class="rounded-xl border border-gray-200 p-4">
                            <div class="mb-2 flex items-center justify-between gap-2">
                                @if ($splitBill)
                                    <input type="text" wire:model.blur="payerLabels.{{ $payerIndex }}"
                                        class="w-40 rounded-lg border-gray-300 text-sm font-semibold shadow-sm focus:border-blue-600 focus:ring-blue-600">
                                @else
                                    <span class="text-sm font-semibold text-gray-800">{{ $label }}</span>
                                @endif

                                <span class="text-sm font-black text-gray-900">{{ number_format((float) $subtotal, 0, ',', '.') }}</span>

                                @if ($splitBill && $payerIndex !== 0)
                                    <button type="button" wire:click="removePayer({{ $payerIndex }})" title="Quitar pagador"
                                        class="ml-auto flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-rose-500 hover:bg-rose-50">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                                            <path stroke-linecap="round" d="M6 6l12 12M18 6L6 18" />
                                        </svg>
                                    </button>
                                @endif
                            </div>

                            <div class="space-y-2">
                                @foreach ($payments[$payerIndex] ?? [] as $rowIndex => $row)
                                    <div class="grid grid-cols-[140px_1fr_36px] gap-1.5">
                                        <select wire:model.live="payments.{{ $payerIndex }}.{{ $rowIndex }}.payment_method_code"
                                            class="h-9 rounded-lg border-gray-300 bg-white px-2 text-sm text-gray-800 focus:border-blue-600 focus:ring-blue-600">
                                            @foreach ($this->paymentMethodOptions() as $code => $optionLabel)
                                                <option value="{{ $code }}">{{ $optionLabel }}</option>
                                            @endforeach
                                        </select>
                                        <input type="number" min="0" step="1" placeholder="Monto"
                                            wire:model.live.blur="payments.{{ $payerIndex }}.{{ $rowIndex }}.amount"
                                            class="h-9 rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-600 focus:ring-blue-600">
                                        <button type="button" wire:click="removePaymentRow({{ $payerIndex }}, {{ $rowIndex }})"
                                            class="flex h-9 w-9 items-center justify-center rounded-lg text-rose-500 hover:bg-rose-50" title="Quitar pago">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                                                <path stroke-linecap="round" d="M6 6l12 12M18 6L6 18" />
                                            </svg>
                                        </button>
                                    </div>
                                @endforeach
                            </div>

                            <div class="mt-2 flex items-center justify-between">
                                <button type="button" wire:click="addPaymentRow({{ $payerIndex }})"
                                    class="text-xs font-semibold text-blue-600 hover:underline">
                                    + Agregar metodo de pago
                                </button>
                                <span class="text-xs font-semibold {{ (float) $remaining > 0 ? 'text-rose-600' : 'text-emerald-600' }}">
                                    Restante: {{ $remaining }}
                                </span>
                            </div>
                        </div>
                    @endforeach
                </div>

                <button wire:click="submitCheckout"
                    class="mt-5 w-full rounded-full bg-emerald-600 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-700">
                    Confirmar cobro
                </button>
            </div>
        </div>
    @endif
</div>
