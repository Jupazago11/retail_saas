<div class="pb-10">
    <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
        <x-sales-nav active="sales.frozen" />

        <div class="grid gap-4 md:grid-cols-4">
            <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200">
                <p class="text-xs uppercase tracking-[0.18em] text-gray-500">Congeladas filtradas</p>
                <p class="mt-2 text-3xl font-black text-gray-900">{{ $statusCards['total_count'] }}</p>
            </div>
            <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200">
                <p class="text-xs uppercase tracking-[0.18em] text-gray-500">Abiertas</p>
                <p class="mt-2 text-3xl font-black text-emerald-700">{{ $statusCards['open_count'] }}</p>
            </div>
            <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200">
                <p class="text-xs uppercase tracking-[0.18em] text-gray-500">Expiradas</p>
                <p class="mt-2 text-3xl font-black text-rose-700">{{ $statusCards['expired_count'] }}</p>
            </div>
            <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200">
                <p class="text-xs uppercase tracking-[0.18em] text-gray-500">Convertidas</p>
                <p class="mt-2 text-3xl font-black text-blue-700">{{ $statusCards['converted_count'] }}</p>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-[0.97fr_1.15fr]">
            <div class="space-y-6">
                @unless ($canCreateFrozenSales)
                    <div class="rounded-xl border border-amber-200 bg-amber-50 p-5 text-amber-900">
                        <p class="text-sm font-semibold uppercase tracking-[0.22em]">Prerequisitos</p>
                        <p class="mt-2 text-sm">
                            {{ $frozenSalesRequirementsMessage }}
                        </p>
                    </div>
                @endunless

                <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-semibold uppercase tracking-[0.22em] text-blue-700">Carrito</p>
                            <h3 class="mt-2 text-2xl font-black text-gray-900">
                                {{ $resumingFrozenSaleId ? 'Retomar congelada #'.$resumingFrozenSaleId : 'Nueva venta congelada' }}
                            </h3>
                        </div>

                        <button wire:click="clearFrozenSaleForm" class="text-sm font-medium text-gray-500">
                            {{ $resumingFrozenSaleId ? 'Cancelar retoma' : 'Limpiar' }}
                        </button>
                    </div>

                    @if ($this->canFreezeSales())
                        <form wire:submit="saveFrozenSale" class="mt-6 space-y-5">
                            <div class="grid gap-4 md:grid-cols-3">
                                <div>
                                    <label class="text-sm font-medium text-gray-700">Sucursal</label>
                                    <select wire:model.live="branchId" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600" @disabled(! $canCreateFrozenSales)>
                                        <option value="">Selecciona</option>
                                        @foreach ($branches as $branch)
                                            <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('branchId') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                                </div>

                                <div>
                                    <label class="text-sm font-medium text-gray-700">Bodega</label>
                                    <select wire:model="warehouseId" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600" @disabled(! $canCreateFrozenSales)>
                                        <option value="">Selecciona</option>
                                        @foreach ($warehouses as $warehouse)
                                            <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('warehouseId') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                                </div>

                                <div>
                                    <label class="text-sm font-medium text-gray-700">Caja</label>
                                    <select wire:model="cashRegisterId" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600" @disabled(! $canCreateFrozenSales)>
                                        <option value="">Sin caja</option>
                                        @foreach ($cashRegisters as $cashRegister)
                                            <option value="{{ $cashRegister->id }}">{{ $cashRegister->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('cashRegisterId') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                                </div>
                            </div>

                            <div class="grid gap-4 md:grid-cols-2">
                                <div>
                                    <label class="text-sm font-medium text-gray-700">Etiqueta</label>
                                    <input wire:model="label" type="text" placeholder="Mesa 4, cliente esperando, pedido rapido..." class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600" @disabled(! $canCreateFrozenSales)>
                                    @error('label') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                                </div>

                                <div>
                                    <label class="text-sm font-medium text-gray-700">Cliente</label>
                                    <select wire:model="customerId" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600" @disabled(! $canCreateFrozenSales)>
                                        <option value="">Consumidor final</option>
                                        @foreach ($customers as $customer)
                                            @php
                                                $fullName = trim(implode(' ', array_filter([
                                                    $customer->person?->first_name,
                                                    $customer->person?->last_name,
                                                ])));
                                            @endphp
                                            <option value="{{ $customer->id }}">{{ $fullName !== '' ? $fullName : 'Cliente '.$customer->id }}</option>
                                        @endforeach
                                    </select>
                                    @error('customerId') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                                </div>
                            </div>

                            <div>
                                <div class="flex items-center justify-between">
                                    <label class="text-sm font-medium text-gray-700">Lineas</label>
                                    <button type="button" wire:click="addItemLine" class="rounded-full border border-gray-300 px-3 py-1 text-sm font-semibold text-gray-700" @disabled(! $canCreateFrozenSales)>
                                        Agregar linea
                                    </button>
                                </div>

                                <div class="mt-3 space-y-4">
                                    @foreach ($items as $index => $item)
                                        @php
                                            $selectedProduct = $products->firstWhere('id', (int) ($item['product_id'] ?? 0));
                                            $presentations = $selectedProduct?->presentations ?? collect();
                                            $variants = $selectedProduct?->variants ?? collect();
                                        @endphp
                                        <div wire:key="frozen-sale-line-{{ $item['_key'] }}" class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                                            <div class="flex items-center justify-between">
                                                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-gray-600">Linea {{ $index + 1 }}</p>
                                                <button type="button" wire:click="removeItemLine({{ $item['_key'] }})" class="text-sm font-medium text-rose-600" @disabled(count($items) === 1 || ! $canCreateFrozenSales)>
                                                    Quitar
                                                </button>
                                            </div>

                                            <div class="mt-4 grid gap-4 md:grid-cols-2">
                                                <div class="md:col-span-2">
                                                    <label class="text-sm font-medium text-gray-700">Producto</label>
                                                    <select wire:model.live="items.{{ $index }}.product_id" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600" @disabled(! $canCreateFrozenSales)>
                                                        <option value="">Selecciona</option>
                                                        @foreach ($products as $product)
                                                            <option value="{{ $product->id }}">{{ $product->name }}</option>
                                                        @endforeach
                                                    </select>
                                                    @error('items.'.$index.'.product_id') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                                                </div>

                                                <div>
                                                    <label class="text-sm font-medium text-gray-700">Presentacion</label>
                                                    <select wire:model.live="items.{{ $index }}.product_presentation_id" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600" @disabled(! $canCreateFrozenSales)>
                                                        <option value="">Unidad base</option>
                                                        @foreach ($presentations as $presentation)
                                                            <option value="{{ $presentation->id }}">{{ $presentation->name }}</option>
                                                        @endforeach
                                                    </select>
                                                    @error('items.'.$index.'.product_presentation_id') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                                                </div>

                                                <div>
                                                    <label class="text-sm font-medium text-gray-700">Variante</label>
                                                    <select wire:model="items.{{ $index }}.product_variant_id" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600" @disabled(! $canCreateFrozenSales)>
                                                        <option value="">Sin variante</option>
                                                        @foreach ($variants as $variant)
                                                            <option value="{{ $variant->id }}">{{ $this->variantSummary($variant) }}</option>
                                                        @endforeach
                                                    </select>
                                                    @error('items.'.$index.'.product_variant_id') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                                                </div>

                                                <div>
                                                    <label class="text-sm font-medium text-gray-700">Cantidad</label>
                                                    <input wire:model="items.{{ $index }}.quantity" type="number" min="0.000001" step="0.000001" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600" @disabled(! $canCreateFrozenSales)>
                                                    @error('items.'.$index.'.quantity') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                                                </div>

                                                <div>
                                                    <label class="text-sm font-medium text-gray-700">Precio unitario</label>
                                                    <input wire:model="items.{{ $index }}.unit_price" type="number" min="0" step="0.0001" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600" @disabled(! $canCreateFrozenSales)>
                                                    @error('items.'.$index.'.unit_price') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                                                </div>

                                                <div>
                                                    <label class="text-sm font-medium text-gray-700">Descuento manual</label>
                                                    <input wire:model="items.{{ $index }}.discount_amount" type="number" min="0" step="0.01" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600" @disabled(! $canCreateFrozenSales)>
                                                    @error('items.'.$index.'.discount_amount') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                                                </div>

                                                <div>
                                                    <label class="text-sm font-medium text-gray-700">Impuesto %</label>
                                                    <input wire:model="items.{{ $index }}.tax_rate" type="number" min="0" step="0.01" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600" @disabled(! $canCreateFrozenSales)>
                                                    @error('items.'.$index.'.tax_rate') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                                @error('items') <p class="mt-2 text-sm text-rose-600">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label class="text-sm font-medium text-gray-700">Notas</label>
                                <textarea wire:model="notes" rows="4" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600" @disabled(! $canCreateFrozenSales)></textarea>
                                @error('notes') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                            </div>

                            <button type="submit" class="inline-flex rounded-full bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-60" @disabled(! $canCreateFrozenSales)>
                                Guardar congelada
                            </button>
                        </form>
                    @else
                        <div class="mt-6 rounded-lg border border-dashed border-gray-300 bg-gray-50 p-5 text-sm text-gray-600">
                            Tu rol actual no puede crear o cancelar ventas congeladas.
                        </div>
                    @endif
                </div>
            </div>

            <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-[0.22em] text-blue-700">Listado</p>
                        <h3 class="mt-2 text-2xl font-black text-gray-900">Congeladas recientes</h3>
                    </div>

                    <div class="grid gap-3 md:grid-cols-[minmax(0,1fr)_0.7fr]">
                        <input wire:model.live.debounce.300ms="search" type="text" placeholder="Buscar por etiqueta, cliente o creador" class="rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600">
                        <select wire:model.live="statusFilter" class="rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600">
                            <option value="">Todos los estados</option>
                            <option value="open">Abiertas</option>
                            <option value="expired">Expiradas</option>
                            <option value="converted">Convertidas</option>
                            <option value="cancelled">Canceladas</option>
                        </select>
                    </div>
                </div>

                <div class="mt-6 space-y-4">
                    @forelse ($frozenSales as $frozenSale)
                        @php
                            $effectiveStatus = $this->effectiveStatus($frozenSale);
                            $customerName = trim(implode(' ', array_filter([
                                $frozenSale->customer?->person?->first_name,
                                $frozenSale->customer?->person?->last_name,
                            ])));
                            $payload = $frozenSale->payload_snapshot;
                            $items = collect($payload['items'] ?? []);
                            $totals = $payload['totals'] ?? [];
                        @endphp
                        <div wire:key="frozen-sale-card-{{ $frozenSale->id }}" class="rounded-xl border border-gray-200 bg-gray-50 p-5">
                            <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                                <div class="space-y-3">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h4 class="text-lg font-black text-gray-900">#{{ $frozenSale->id }} · {{ $frozenSale->label }}</h4>
                                        <x-status-badge :color="$effectiveStatus === 'open' ? 'emerald' : ($effectiveStatus === 'converted' ? 'amber' : 'rose')">
                                            {{ $effectiveStatus }}
                                        </x-status-badge>
                                    </div>

                                    <div class="grid gap-2 text-sm text-gray-600 md:grid-cols-2">
                                        <p>Cliente: <span class="font-medium text-gray-900">{{ $customerName !== '' ? $customerName : 'Consumidor final' }}</span></p>
                                        <p>Creador: <span class="font-medium text-gray-900">{{ $frozenSale->creator?->name ?: 'Sin creador' }}</span></p>
                                        <p>Sucursal/Caja: <span class="font-medium text-gray-900">{{ $frozenSale->branch?->name ?: 'Sin sucursal' }} / {{ $frozenSale->cashRegister?->name ?: 'Sin caja' }}</span></p>
                                        <p>Expira: <span class="font-medium text-gray-900">{{ optional($frozenSale->expires_at)->format('Y-m-d H:i') ?: 'Sin expiracion' }}</span></p>
                                    </div>

                                    <div class="rounded-lg bg-white p-4 ring-1 ring-gray-200">
                                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-gray-500">Lineas</p>
                                        <div class="mt-3 space-y-3">
                                            @foreach ($items as $item)
                                                <div class="flex flex-col gap-1 border-b border-stone-100 pb-3 last:border-b-0 last:pb-0">
                                                    <p class="font-medium text-gray-900">{{ $item['description'] ?? ('Producto #'.($item['product_id'] ?? '?')) }}</p>
                                                    <p class="text-sm text-gray-600">
                                                        {{ number_format((float) ($item['quantity'] ?? 0), 2, '.', ',') }}
                                                        · precio {{ \App\Support\Money::format((float) ($item['unit_price'] ?? 0)) }}
                                                        · descuento {{ \App\Support\Money::format((float) ($item['discount_amount'] ?? 0)) }}
                                                    </p>
                                                    <p class="text-sm text-gray-500">
                                                        Variante/presentacion ya congeladas en snapshot
                                                        · total {{ \App\Support\Money::format((float) ($item['line_total'] ?? 0)) }}
                                                    </p>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>

                                <div class="min-w-[240px] space-y-3">
                                    <div class="grid grid-cols-2 gap-3">
                                        <div class="rounded-lg bg-white p-4 ring-1 ring-gray-200">
                                            <p class="text-xs uppercase tracking-[0.18em] text-gray-500">Subtotal</p>
                                            <p class="mt-1 text-lg font-black text-gray-900">${{ \App\Support\Money::format((float) ($totals['subtotal'] ?? 0)) }}</p>
                                        </div>
                                        <div class="rounded-lg bg-white p-4 ring-1 ring-gray-200">
                                            <p class="text-xs uppercase tracking-[0.18em] text-gray-500">Total</p>
                                            <p class="mt-1 text-lg font-black text-gray-900">${{ \App\Support\Money::format((float) ($totals['grand_total'] ?? 0)) }}</p>
                                        </div>
                                    </div>

                                    @if ($effectiveStatus === 'open')
                                        <div class="flex flex-wrap gap-2">
                                            @if ($this->canFreezeSales())
                                                <button wire:click="startResumingFrozenSale({{ $frozenSale->id }})" class="rounded-full border border-sky-300 px-3 py-1 font-medium text-sky-700">
                                                    Retomar en formulario
                                                </button>
                                                <button wire:click="cancelFrozenSale({{ $frozenSale->id }})" class="rounded-full border border-rose-300 px-3 py-1 font-medium text-rose-700">
                                                    Cancelar
                                                </button>
                                            @endif

                                            @if ($this->canConvertFrozenSales())
                                                <button wire:click="convertFrozenSaleToSale({{ $frozenSale->id }})" class="rounded-full bg-blue-600 px-3 py-1 font-medium text-white">
                                                    Convertir a venta
                                                </button>
                                            @endif
                                        </div>
                                    @elseif ($frozenSale->status === 'converted' && $frozenSale->convertedSale)
                                        <a href="{{ route('sales.ticket', $frozenSale->convertedSale) }}" target="_blank" rel="noopener noreferrer" class="inline-flex rounded-full bg-blue-600 px-4 py-2 text-sm font-semibold text-white">
                                            Abrir ticket convertido
                                        </a>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="rounded-xl border border-dashed border-gray-300 bg-gray-50 p-8 text-center text-gray-500">
                            Aun no hay ventas congeladas con el filtro actual.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
