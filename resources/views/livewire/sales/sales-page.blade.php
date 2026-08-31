<div class="pb-10">
    <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
        {{-- Sin <x-sales-nav>: las pestañas Ventas/POS las dibuja el
        componente contenedor (sales-workspace-page.blade.php) una sola vez. --}}

        {{-- Panel --}}
        <div x-data="responsivePageSize({ rowHeight: 64, reserved: 340 })" class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">

            {{-- Cabecera --}}
            <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-[0.22em] text-blue-700">Historial</p>
                    <h3 class="mt-1 text-2xl font-black text-gray-900">Ventas registradas</h3>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    @if ($this->canCreateSales())
                        <button type="button" x-on:click="$dispatch('switch-sales-tab', { tab: 'pos' })"
                            class="rounded-full bg-blue-600 px-4 py-1.5 text-xs font-semibold text-white">
                            Ir al POS
                        </button>
                    @endif
                    @if ($this->canManageRules())
                        <button type="button" wire:click="openRulesModal" title="Reglas de ventas"
                            class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-500 shadow-sm hover:border-blue-300 hover:text-blue-700 transition">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                        </button>
                    @endif
                    <input wire:model.live.debounce.300ms="search" type="text" placeholder="Buscar por documento, cliente o vendedor"
                        class="w-64 rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600 text-sm">
                </div>
            </div>

            {{-- Filtros --}}
            <div class="mt-4 flex flex-wrap items-center gap-3">
                <div class="inline-flex flex-wrap rounded-lg border border-gray-200 bg-gray-50 p-1 text-sm">
                    <button type="button" wire:click="setStatusFilter('')"
                        class="rounded-md px-3 py-1.5 font-semibold transition {{ $statusFilter === '' ? 'bg-gradient-to-br from-blue-600 to-purple-600 text-white shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">
                        Todos
                    </button>
                    <button type="button" wire:click="setStatusFilter('draft')"
                        class="rounded-md px-3 py-1.5 font-semibold transition {{ $statusFilter === 'draft' ? 'bg-gray-600 text-white shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">
                        Borrador
                    </button>
                    <button type="button" wire:click="setStatusFilter('confirmed')"
                        class="rounded-md px-3 py-1.5 font-semibold transition {{ $statusFilter === 'confirmed' ? 'bg-emerald-600 text-white shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">
                        Confirmada
                    </button>
                    <button type="button" wire:click="setStatusFilter('partially_returned')"
                        class="rounded-md px-3 py-1.5 font-semibold transition {{ $statusFilter === 'partially_returned' ? 'bg-amber-600 text-white shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">
                        Dev. parcial
                    </button>
                    <button type="button" wire:click="setStatusFilter('returned')"
                        class="rounded-md px-3 py-1.5 font-semibold transition {{ $statusFilter === 'returned' ? 'bg-sky-600 text-white shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">
                        Devuelta
                    </button>
                    <button type="button" wire:click="setStatusFilter('cancelled')"
                        class="rounded-md px-3 py-1.5 font-semibold transition {{ $statusFilter === 'cancelled' ? 'bg-rose-600 text-white shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">
                        Anulada
                    </button>
                </div>
                <div class="inline-flex rounded-lg border border-gray-200 bg-gray-50 p-1 text-sm">
                    <button type="button" wire:click="setSaleTypeFilter('')"
                        class="rounded-md px-3 py-1.5 font-semibold transition {{ $saleTypeFilter === '' ? 'bg-gradient-to-br from-blue-600 to-purple-600 text-white shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">
                        Todos
                    </button>
                    <button type="button" wire:click="setSaleTypeFilter('pos')"
                        class="rounded-md px-3 py-1.5 font-semibold transition {{ $saleTypeFilter === 'pos' ? 'bg-blue-600 text-white shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">
                        POS
                    </button>
                    <button type="button" wire:click="setSaleTypeFilter('credit')"
                        class="rounded-md px-3 py-1.5 font-semibold transition {{ $saleTypeFilter === 'credit' ? 'bg-fuchsia-600 text-white shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">
                        Credito
                    </button>
                </div>
                <div class="inline-flex rounded-lg border border-gray-200 bg-gray-50 p-1 text-sm">
                    <button type="button" wire:click="setPaymentMethodFilter('')"
                        class="rounded-md px-3 py-1.5 font-semibold transition {{ $paymentMethodFilter === '' ? 'bg-gradient-to-br from-blue-600 to-purple-600 text-white shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">
                        Todos los medios
                    </button>
                    @foreach ($this->paymentMethodOptions() as $code => $label)
                        <button type="button" wire:click="setPaymentMethodFilter('{{ $code }}')"
                            class="rounded-md px-3 py-1.5 font-semibold transition {{ $paymentMethodFilter === $code ? 'bg-teal-600 text-white shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">
                            {{ $label }}
                        </button>
                    @endforeach
                </div>
                <div class="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-1.5 text-sm">
                    <label for="sales-date-from" class="text-xs font-medium text-gray-500">Desde</label>
                    <input wire:model.live="dateFrom" type="date" id="sales-date-from" max="{{ $dateTo ?: '' }}"
                        class="rounded-md border-gray-300 py-1 text-xs shadow-sm focus:border-blue-600 focus:ring-blue-600">
                    <label for="sales-date-to" class="text-xs font-medium text-gray-500">Hasta</label>
                    <input wire:model.live="dateTo" type="date" id="sales-date-to" min="{{ $dateFrom ?: '' }}"
                        class="rounded-md border-gray-300 py-1 text-xs shadow-sm focus:border-blue-600 focus:ring-blue-600">
                    @if ($dateFrom !== '' || $dateTo !== '')
                        <button type="button" wire:click="clearDateFilter" title="Limpiar fechas"
                            class="text-gray-400 hover:text-gray-600">
                            &times;
                        </button>
                    @endif
                </div>
            </div>

            {{-- Tabla de ventas --}}
            <div class="mt-6 overflow-x-auto">
                <table class="min-w-full divide-y divide-stone-200 text-sm">
                    <thead>
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <th class="pb-2">Documento</th>
                            <th class="pb-2">Cliente</th>
                            <th class="pb-2">Detalle</th>
                            <th class="pb-2 w-px whitespace-nowrap">Total</th>
                            <th class="pb-2 w-px whitespace-nowrap text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-100">
                        @forelse ($sales as $sale)
                            @php
                                $customerName = trim(implode(' ', array_filter([
                                    $sale->customer?->person?->first_name,
                                    $sale->customer?->person?->last_name,
                                ])));
                                [$statusBadge, $statusLabel] = match($sale->status) {
                                    'confirmed'          => ['bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-600/20', 'Confirmada'],
                                    'cancelled'          => ['bg-rose-50 text-rose-600 ring-1 ring-inset ring-rose-600/20',    'Anulada'],
                                    'returned'           => ['bg-sky-50 text-sky-700 ring-1 ring-inset ring-sky-600/20',       'Devuelta'],
                                    'partially_returned' => ['bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-600/20',   'Dev. parcial'],
                                    'draft'              => ['bg-gray-100 text-gray-500',   'Borrador'],
                                    default              => ['bg-gray-100 text-gray-500',   $sale->status],
                                };
                            @endphp
                            <tr wire:key="sale-row-{{ $sale->id }}" class="even:bg-gray-50">
                                <td class="py-2 align-middle">
                                    <p class="font-semibold text-gray-900">{{ $sale->document_number }}</p>
                                    <div class="mt-1 flex flex-wrap items-center gap-1">
                                        <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $statusBadge }}">{{ $statusLabel }}</span>
                                        <span class="rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-medium text-gray-500">{{ strtoupper($sale->sale_type) }}</span>
                                        @foreach ($sale->payments->pluck('payment_method_code')->filter()->unique() as $methodCode)
                                            <span class="rounded-full bg-teal-50 px-2 py-0.5 text-[10px] font-medium text-teal-700 ring-1 ring-inset ring-teal-600/20">
                                                {{ $this->paymentMethodLabel($methodCode) }}
                                            </span>
                                        @endforeach
                                        @if ($sale->replacesSale)
                                            <span class="rounded-full bg-violet-50 px-2 py-0.5 text-[10px] font-medium text-violet-600 ring-1 ring-inset ring-violet-600/20">
                                                Reemplaza a {{ $sale->replacesSale->document_number }}
                                            </span>
                                        @endif
                                        @if ($sale->replacedBySale)
                                            <span class="rounded-full bg-violet-50 px-2 py-0.5 text-[10px] font-medium text-violet-600 ring-1 ring-inset ring-violet-600/20">
                                                Reemplazada por {{ $sale->replacedBySale->document_number }}
                                            </span>
                                        @endif
                                    </div>
                                </td>
                                <td class="py-2 align-middle text-gray-600 text-xs">
                                    <p class="font-semibold text-gray-800">{{ $customerName ?: 'Consumidor final' }}</p>
                                </td>
                                <td class="py-2 align-middle text-gray-500 text-xs">
                                    <p>{{ optional($sale->sold_at)->format('d/m/Y H:i') ?: $sale->created_at->format('d/m/Y H:i') }}</p>
                                    <p>{{ $sale->branch?->name ?: '—' }} · {{ $sale->user?->name ?: 'Sin vendedor' }}</p>
                                </td>
                                <td class="py-2 align-middle w-px whitespace-nowrap font-black text-gray-900">
                                    ${{ \App\Support\Money::format((float) $sale->grand_total) }}
                                </td>
                                <td class="py-2 align-middle w-px whitespace-nowrap">
                                    <div class="flex items-center justify-end gap-3">
                                        @if ($this->canCreateSales() && $sale->status === 'draft')
                                            {{-- Dispara un evento (escuchado por PosPage::loadDraftSaleForEditing
                                            via #[On(...)] Y por el contenedor para cambiar de pestaña) en vez de
                                            navegar — POS ya esta montado, solo hay que pedirle que cargue esta venta. --}}
                                            <button type="button" wire:click="$dispatch('edit-draft-requested', { saleId: {{ $sale->id }} })"
                                                title="Editar borrador" class="text-gray-400 hover:text-blue-600 transition">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536M9 13l6.586-6.586a2 2 0 112.828 2.828L11.828 15.828a2 2 0 01-1.415.586H9v-2.414a2 2 0 01.586-1.415z"/>
                                                </svg>
                                            </button>
                                        @endif
                                        @if ($this->canCreateSales() && $sale->status === 'confirmed' && $sale->sale_type === 'pos')
                                            <button type="button" wire:click="$dispatch('modify-sale-requested', { saleId: {{ $sale->id }} })"
                                                title="Modificar venta" class="text-gray-400 hover:text-violet-600 transition">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536M9 13l6.586-6.586a2 2 0 112.828 2.828L11.828 15.828a2 2 0 01-1.415.586H9v-2.414a2 2 0 01.586-1.415z"/>
                                                </svg>
                                            </button>
                                        @endif
                                        @if ($this->canReturnSales() && in_array($sale->status, ['confirmed', 'partially_returned'], true))
                                            <button wire:click="startReturningSale({{ $sale->id }})" title="Devolver"
                                                class="text-gray-400 hover:text-sky-600 transition">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3"/>
                                                </svg>
                                            </button>
                                        @endif
                                        @if ($this->canCancelSales() && in_array($sale->status, ['draft', 'confirmed'], true))
                                            <button wire:click="startCancellingSale({{ $sale->id }})" title="Anular"
                                                class="text-gray-400 hover:text-rose-600 transition">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                </svg>
                                            </button>
                                        @endif
                                        <a href="{{ route('sales.ticket', $sale) }}" target="_blank" rel="noopener noreferrer" title="Ver ticket"
                                            class="text-gray-400 hover:text-blue-600 transition">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0110.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0l.229 2.523a1.125 1.125 0 01-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0021 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 00-1.913-.247M6.34 18H5.25A2.25 2.25 0 013 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 011.913-.247m10.5 0a48.536 48.536 0 00-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5zm-3 0h.008v.008H15V10.5z"/>
                                            </svg>
                                        </a>
                                    </div>
                                </td>
                            </tr>

                            {{-- Devolucion --}}
                            @if ($returningSaleId === $sale->id)
                                <tr wire:key="sale-return-{{ $sale->id }}">
                                    <td colspan="5" class="bg-sky-50 px-4 py-4 border-b border-stone-100">
                                        <div class="flex items-center justify-between">
                                            <p class="text-[10px] font-semibold uppercase tracking-widest text-sky-600">Devolucion · {{ $sale->document_number }}</p>
                                            <button wire:click="cancelReturningSale" class="text-[11px] text-gray-400 hover:text-gray-600">Cancelar</button>
                                        </div>
                                        <div class="mt-2 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                            <div class="sm:col-span-2 lg:col-span-3">
                                                <label class="text-xs text-gray-600">Motivo <span class="text-rose-600">*</span></label>
                                                <textarea wire:model="returnReason" rows="2"
                                                    class="mt-1 block w-full rounded-lg border-gray-200 text-xs focus:border-sky-400 focus:ring-0"></textarea>
                                            </div>
                                            @foreach ($returnItems as $index => $returnItem)
                                                @php $saleItem = $sale->items->firstWhere('id', $returnItem['sale_item_id']); @endphp
                                                @if ($saleItem)
                                                    <div class="rounded-lg bg-white p-2.5 ring-1 ring-sky-100">
                                                        <p class="text-xs font-medium text-gray-800">{{ $saleItem->product?->name }}</p>
                                                        <p class="mt-0.5 text-[11px] text-gray-400">Pendiente: {{ number_format((float) $returnItem['pending_quantity'], 2, '.', ',') }} {{ $saleItem->presentation?->name ?: 'u.' }}</p>
                                                        <input wire:model="returnItems.{{ $index }}.quantity" type="number" min="0.000001" step="0.000001"
                                                            placeholder="Cantidad a devolver"
                                                            class="mt-1.5 block w-full rounded-lg border-gray-200 text-xs focus:border-sky-400 focus:ring-0">
                                                    </div>
                                                @endif
                                            @endforeach
                                        </div>
                                        <button wire:click="registerReturn"
                                            class="mt-3 rounded-full bg-blue-600 px-4 py-1.5 text-xs font-semibold text-white">
                                            Confirmar devolucion
                                        </button>
                                    </td>
                                </tr>
                            @endif

                            {{-- Anulacion --}}
                            @if ($cancellingSaleId === $sale->id)
                                <tr wire:key="sale-cancel-{{ $sale->id }}">
                                    <td colspan="5" class="bg-rose-50 px-4 py-4 border-b border-stone-100">
                                        <div class="flex items-center justify-between">
                                            <p class="text-[10px] font-semibold uppercase tracking-widest text-rose-600">Anulacion · {{ $sale->document_number }}</p>
                                            <button wire:click="cancelCancellingSale" class="text-[11px] text-gray-400 hover:text-gray-600">Cancelar</button>
                                        </div>
                                        <div class="mt-2">
                                            <label class="text-xs text-gray-600">Motivo <span class="text-rose-600">*</span></label>
                                            <textarea wire:model="cancellationReason" rows="2"
                                                class="mt-1 block w-full rounded-lg border-gray-200 text-xs focus:border-rose-400 focus:ring-0"></textarea>
                                        </div>
                                        <button wire:click="cancelSaleDocument({{ $sale->id }})"
                                            class="mt-3 rounded-full bg-blue-600 px-4 py-1.5 text-xs font-semibold text-white">
                                            Confirmar anulacion
                                        </button>
                                    </td>
                                </tr>
                            @endif
                        @empty
                            <tr>
                                <td colspan="5" class="py-10 text-center text-gray-400">
                                    Sin ventas con el filtro actual.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($sales->hasPages())
                <div class="mt-4 border-t border-stone-100 pt-4">
                    {{ $sales->links() }}
                </div>
            @endif
        </div>
    </div>

    @if ($this->canManageRules())
        <div x-data x-show="$wire.showRulesModal"
            x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
            x-cloak
            wire:click.self="closeRulesModal"
            class="fixed inset-0 z-50 flex items-center justify-center p-4"
            style="background: rgba(0,0,0,0.5);">
                <div x-show="$wire.showRulesModal"
                    x-transition:enter="transition ease-out duration-200 delay-75" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                    x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
                    class="w-full max-w-md rounded-xl bg-white shadow-xl flex flex-col" style="max-height: 90vh;">

                    {{-- Header pinned --}}
                    <div class="flex-shrink-0 flex items-center justify-between border-b border-stone-100 px-5 py-3">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-widest text-blue-700">Reglas</p>
                            <h3 class="mt-0.5 text-lg font-black text-gray-900">Ventas</h3>
                        </div>
                        <button wire:click="closeRulesModal" class="text-gray-400 hover:text-gray-700 text-xl leading-none px-1">&times;</button>
                    </div>

                    {{-- Form: scrollable body + pinned footer --}}
                    <form wire:submit="saveRules" class="flex flex-col flex-1 min-h-0">
                        <div class="flex-1 overflow-y-auto px-5 py-4 space-y-4">
                            @if ($ruleFeatureGates['alternative_prices'] ?? false)
                                <label class="flex items-start gap-3">
                                    <input wire:model="ruleAllowAlternativePrices" type="checkbox"
                                        class="mt-0.5 rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-600">
                                    <span>
                                        <span class="block text-sm font-medium text-gray-900">Permitir precios alternos</span>
                                        <span class="block text-xs text-gray-500">El cajero puede elegir entre precio 1, 2 o 3 al vender.</span>
                                    </span>
                                </label>
                            @endif

                            @if ($ruleFeatureGates['manual_discounts'] ?? false)
                                <label class="flex items-start gap-3">
                                    <input wire:model="ruleAllowManualDiscounts" type="checkbox"
                                        class="mt-0.5 rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-600">
                                    <span>
                                        <span class="block text-sm font-medium text-gray-900">Permitir descuentos manuales</span>
                                        <span class="block text-xs text-gray-500">El cajero puede aplicar un descuento libre en la venta.</span>
                                    </span>
                                </label>
                            @endif

                            @if ($ruleFeatureGates['promotion_stacking'] ?? false)
                                <label class="flex items-start gap-3">
                                    <input wire:model="ruleAllowPromotionStacking" type="checkbox"
                                        class="mt-0.5 rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-600">
                                    <span>
                                        <span class="block text-sm font-medium text-gray-900">Permitir apilar promociones</span>
                                        <span class="block text-xs text-gray-500">Una misma venta puede aplicar mas de una promocion a la vez.</span>
                                    </span>
                                </label>
                            @endif

                            @if ($ruleFeatureGates['negative_stock'] ?? false)
                                <label class="flex items-start gap-3">
                                    <input wire:model="ruleAllowNegativeStock" type="checkbox"
                                        class="mt-0.5 rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-600">
                                    <span>
                                        <span class="block text-sm font-medium text-gray-900">Permitir inventario negativo</span>
                                        <span class="block text-xs text-gray-500">Deja vender aunque el stock disponible sea cero o menor.</span>
                                    </span>
                                </label>
                            @endif

                            @if ($ruleFeatureGates['credit_sale'] ?? false)
                                <label class="flex items-start gap-3">
                                    <input wire:model="ruleRequireCustomerForCreditSale" type="checkbox"
                                        class="mt-0.5 rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-600">
                                    <span>
                                        <span class="block text-sm font-medium text-gray-900">Exigir cliente para venta a credito</span>
                                        <span class="block text-xs text-gray-500">No deja confirmar una venta a credito sin asociar un cliente.</span>
                                    </span>
                                </label>
                            @endif

                            <div class="border-t border-stone-100 pt-4">
                                <p class="text-sm font-semibold text-gray-900">Impresion de tickets</p>
                                <p class="mt-1 text-xs text-gray-400">El tipo de impresora (58mm/80mm/carta) ahora se configura por caja en Admin &rarr; Estructura.</p>

                                <div class="mt-3">
                                    <label class="text-sm font-medium text-gray-700">Logo de la empresa</label>

                                    @unless ($this->isLogoStorageConfigured())
                                        <div class="mt-2 rounded-lg bg-amber-50 p-2.5 text-xs text-amber-700 ring-1 ring-amber-200">
                                            El almacenamiento de archivos todavia no esta configurado. Avisale al soporte de la plataforma.
                                        </div>
                                    @endunless

                                    <div class="mt-2 flex items-center gap-3">
                                        <div class="flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-gray-50 ring-1 ring-gray-200">
                                            @if ($newLogo)
                                                <img src="{{ $newLogo->temporaryUrl() }}" alt="Vista previa" class="h-full w-full object-contain">
                                            @elseif ($currentLogoUrl)
                                                <img src="{{ $currentLogoUrl }}" alt="Logo actual" class="h-full w-full object-contain">
                                            @else
                                                <span class="text-[10px] text-gray-400">Sin logo</span>
                                            @endif
                                        </div>

                                        <div class="flex-1">
                                            <input type="file" wire:model="newLogo" accept=".jpg,.jpeg,.png,.gif,.webp,.bmp"
                                                {{ $this->isLogoStorageConfigured() ? '' : 'disabled' }}
                                                class="block w-full text-xs text-gray-600 file:mr-2 file:rounded-full file:border-0 file:bg-blue-50 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-blue-700 hover:file:bg-blue-100 disabled:opacity-50">

                                            <div class="mt-1.5 flex gap-2">
                                                @if ($newLogo)
                                                    <button type="button" wire:click="uploadLogo" wire:loading.attr="disabled" wire:target="uploadLogo"
                                                        class="rounded-full bg-blue-600 px-3 py-1 text-[11px] font-semibold text-white transition hover:bg-blue-700 disabled:opacity-50">
                                                        <span wire:loading.remove wire:target="uploadLogo">Guardar logo</span>
                                                        <span wire:loading wire:target="uploadLogo">Subiendo...</span>
                                                    </button>
                                                @endif
                                                @if ($currentLogoUrl)
                                                    <button type="button" wire:click="removeLogo" wire:confirm="¿Quitar el logo actual?"
                                                        class="rounded-full border border-gray-300 px-3 py-1 text-[11px] font-medium text-gray-600 transition hover:border-rose-300 hover:text-rose-600">
                                                        Quitar logo
                                                    </button>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <label class="mt-3 flex items-start gap-3 {{ $currentLogoUrl ? '' : 'opacity-50' }}">
                                    <input wire:model="ruleShowLogo" type="checkbox" @disabled(! $currentLogoUrl)
                                        class="mt-0.5 rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-600 disabled:cursor-not-allowed">
                                    <span class="block text-sm font-medium text-gray-900">Mostrar logo en el ticket</span>
                                </label>
                                @unless ($currentLogoUrl)
                                    <p class="mt-1 text-xs text-gray-400">Carga un logo para poder activar esta opcion.</p>
                                @endunless

                                <p class="mt-3 text-xs text-gray-500">
                                    La marca "Desarrollado por {{ \App\Models\PlatformSetting::appName() }}" siempre aparece en el ticket.
                                </p>
                            </div>
                        </div>

                        <div class="flex-shrink-0 flex items-center justify-end gap-3 border-t border-stone-100 px-5 py-3">
                            <button type="button" wire:click="closeRulesModal" class="text-sm font-medium text-gray-500 hover:text-gray-700">
                                Cancelar
                            </button>
                            <button type="submit"
                                class="inline-flex items-center rounded-lg bg-gradient-to-br from-blue-600 to-purple-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:from-blue-700 hover:to-purple-700">
                                Guardar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
    @endif
</div>
