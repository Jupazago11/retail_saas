<div class="pb-10">
    <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
        <x-sales-nav />

        {{-- Stats --}}
        <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
            <div class="rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-200">
                <p class="text-[10px] font-semibold uppercase tracking-widest text-gray-400">Filtradas</p>
                <p class="mt-1 text-2xl font-black text-gray-900">{{ $statusCards['total_count'] }}</p>
            </div>
            <div class="rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-200">
                <p class="text-[10px] font-semibold uppercase tracking-widest text-gray-400">Confirmadas</p>
                <p class="mt-1 text-2xl font-black text-emerald-600">{{ $statusCards['confirmed_count'] }}</p>
            </div>
            <div class="rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-200">
                <p class="text-[10px] font-semibold uppercase tracking-widest text-gray-400">A credito</p>
                <p class="mt-1 text-2xl font-black text-blue-600">{{ $statusCards['credit_count'] }}</p>
            </div>
            <div class="rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-200">
                <p class="text-[10px] font-semibold uppercase tracking-widest text-gray-400">Total bruto</p>
                <p class="mt-1 text-2xl font-black text-gray-900">${{ $statusCards['grand_total'] }}</p>
            </div>
        </div>

        {{-- Panel --}}
        <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">

            {{-- Cabecera --}}
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-[0.22em] text-blue-700">Historial</p>
                    <h3 class="mt-1 text-2xl font-black text-gray-900">Ventas registradas</h3>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    @if ($this->canCreateSales())
                        <a href="{{ route('sales.pos') }}" wire:navigate
                            class="rounded-full bg-blue-600 px-4 py-1.5 text-xs font-semibold text-white">
                            Ir al POS
                        </a>
                    @endif
                    <input wire:model.live.debounce.300ms="search" type="text" placeholder="Buscar…"
                        class="h-8 rounded-full border-gray-200 px-4 text-xs focus:border-blue-400 focus:ring-0">
                    <select wire:model.live="statusFilter"
                        class="h-8 rounded-full border-gray-200 px-3 text-xs focus:border-blue-400 focus:ring-0">
                        <option value="">Todos los estados</option>
                        <option value="draft">Borrador</option>
                        <option value="confirmed">Confirmada</option>
                        <option value="partially_returned">Parcialmente devuelta</option>
                        <option value="returned">Devuelta</option>
                        <option value="cancelled">Anulada</option>
                    </select>
                    <select wire:model.live="saleTypeFilter"
                        class="h-8 rounded-full border-gray-200 px-3 text-xs focus:border-blue-400 focus:ring-0">
                        <option value="">Todos los tipos</option>
                        <option value="pos">POS</option>
                        <option value="credit">Credito</option>
                    </select>
                </div>
            </div>

            {{-- Cards de ventas --}}
            <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
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

                    <div wire:key="sale-card-{{ $sale->id }}"
                        class="flex flex-col justify-between rounded-xl border border-gray-200 bg-white shadow-sm transition hover:border-gray-300 hover:shadow-md">

                        <div class="flex flex-1 flex-col justify-between p-4">
                            {{-- Top --}}
                            <div class="space-y-2">
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <span class="text-sm font-black text-gray-900">{{ $sale->document_number }}</span>
                                    <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $statusBadge }}">{{ $statusLabel }}</span>
                                    <span class="rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-medium text-gray-500">{{ strtoupper($sale->sale_type) }}</span>
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

                                <div class="space-y-0.5 text-xs text-gray-500">
                                    <p class="font-semibold text-gray-800">{{ $customerName ?: 'Consumidor final' }}</p>
                                    <p>{{ optional($sale->sold_at)->format('d/m/Y H:i') ?: $sale->created_at->format('d/m/Y H:i') }}</p>
                                    <p>{{ $sale->branch?->name ?: '—' }} · {{ $sale->user?->name ?: 'Sin vendedor' }}</p>
                                </div>
                            </div>

                            {{-- Bottom --}}
                            <div class="mt-4">
                                <p class="text-2xl font-black text-gray-900">${{ \App\Support\Money::format((float) $sale->grand_total) }}</p>

                                <div class="mt-3 flex items-center gap-2 border-t border-stone-100 pt-3">
                                    <div class="flex gap-1.5">
                                        @if ($this->canCreateSales() && $sale->status === 'draft')
                                            <a href="{{ $this->draftEditUrl($sale->id) }}" wire:navigate
                                                class="rounded-full border border-blue-300 px-4 py-1 text-[11px] font-medium text-blue-700 hover:bg-blue-50">
                                                Editar
                                            </a>
                                        @endif
                                        @if ($this->canCreateSales() && $sale->status === 'confirmed' && $sale->sale_type === 'pos')
                                            <a href="{{ $this->modifySaleUrl($sale->id) }}" wire:navigate
                                                class="rounded-full border border-violet-300 px-4 py-1 text-[11px] font-medium text-violet-700 hover:bg-violet-50">
                                                Modificar
                                            </a>
                                        @endif
                                        @if ($this->canReturnSales() && in_array($sale->status, ['confirmed', 'partially_returned'], true))
                                            <button wire:click="startReturningSale({{ $sale->id }})"
                                                class="rounded-full border border-sky-200 px-4 py-1 text-[11px] font-medium text-sky-600 hover:bg-sky-50">
                                                Devolver
                                            </button>
                                        @endif
                                        @if ($this->canCancelSales() && in_array($sale->status, ['draft', 'confirmed'], true))
                                            <button wire:click="startCancellingSale({{ $sale->id }})"
                                                class="rounded-full border border-rose-200 px-4 py-1 text-[11px] font-medium text-rose-600 hover:bg-rose-50">
                                                Anular
                                            </button>
                                        @endif
                                    </div>
                                    <a href="{{ route('sales.ticket', $sale) }}" target="_blank" rel="noopener noreferrer"
                                        class="ml-auto rounded-full bg-blue-600 px-3 py-1 text-[11px] font-semibold text-white hover:bg-blue-700">
                                        Ticket
                                    </a>
                                </div>
                            </div>
                        </div>

                        {{-- Devolucion --}}
                        @if ($returningSaleId === $sale->id)
                            <div class="mt-3 rounded-xl border border-sky-200 bg-sky-50 p-3">
                                <div class="flex items-center justify-between">
                                    <p class="text-[10px] font-semibold uppercase tracking-widest text-sky-600">Devolucion</p>
                                    <button wire:click="cancelReturningSale" class="text-[11px] text-gray-400 hover:text-gray-600">Cancelar</button>
                                </div>
                                <div class="mt-2 space-y-2">
                                    <div>
                                        <label class="text-xs text-gray-600">Motivo</label>
                                        <textarea wire:model="returnReason" rows="2"
                                            class="mt-1 block w-full rounded-lg border-gray-200 text-xs focus:border-sky-400 focus:ring-0"></textarea>
                                        @error('returnReason') <p class="mt-0.5 text-[11px] text-rose-500">{{ $message }}</p> @enderror
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
                                    @error('returnItems') <p class="mt-1 text-[11px] text-rose-500">{{ $message }}</p> @enderror
                                </div>
                                <button wire:click="registerReturn"
                                    class="mt-2.5 rounded-full bg-blue-600 px-4 py-1.5 text-xs font-semibold text-white">
                                    Confirmar devolucion
                                </button>
                            </div>
                        @endif

                        {{-- Anulacion --}}
                        @if ($cancellingSaleId === $sale->id)
                            <div class="mt-3 rounded-xl border border-rose-200 bg-rose-50 p-3">
                                <div class="flex items-center justify-between">
                                    <p class="text-[10px] font-semibold uppercase tracking-widest text-rose-600">Anulacion</p>
                                    <button wire:click="cancelCancellingSale" class="text-[11px] text-gray-400 hover:text-gray-600">Cancelar</button>
                                </div>
                                <div class="mt-2">
                                    <label class="text-xs text-gray-600">Motivo</label>
                                    <textarea wire:model="cancellationReason" rows="2"
                                        class="mt-1 block w-full rounded-lg border-gray-200 text-xs focus:border-rose-400 focus:ring-0"></textarea>
                                    @error('cancellationReason') <p class="mt-0.5 text-[11px] text-rose-500">{{ $message }}</p> @enderror
                                </div>
                                <button wire:click="cancelSaleDocument({{ $sale->id }})"
                                    class="mt-2.5 rounded-full bg-blue-600 px-4 py-1.5 text-xs font-semibold text-white">
                                    Confirmar anulacion
                                </button>
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="col-span-full rounded-lg border border-dashed border-gray-200 py-12 text-center text-sm text-gray-400">
                        Sin ventas con el filtro actual.
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>
