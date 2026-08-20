<div class="pb-10">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-4">

        <x-purchases-nav active="purchases.index" />

        @unless ($canCreatePurchases)
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-5 text-amber-900">
                <p class="text-sm font-semibold uppercase tracking-[0.22em]">Prerequisitos</p>
                <p class="mt-2 text-sm">
                    Antes de registrar compras debes tener al menos una sucursal activa y una bodega activa.
                </p>
            </div>
        @endunless

        <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-[0.22em] text-blue-700">Compras</p>
                    <h3 class="mt-1 text-2xl font-black text-gray-900">Compras registradas</h3>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <input wire:model.live.debounce.300ms="search" type="text" placeholder="Buscar por factura o proveedor"
                        class="w-52 rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600 text-sm">
                    <select wire:model.live="supplierFilterId" class="rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600 text-sm">
                        <option value="">Todos los proveedores</option>
                        @foreach ($suppliers as $supplier)
                            @php
                                $fullName = trim(implode(' ', array_filter([
                                    $supplier->person?->first_name,
                                    $supplier->person?->last_name,
                                ])));
                            @endphp
                            <option value="{{ $supplier->id }}">{{ $fullName !== '' ? $fullName : 'Proveedor '.$supplier->id }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="mt-3 inline-flex flex-wrap gap-1 rounded-lg border border-gray-200 bg-gray-50 p-1 text-sm">
                <button type="button" wire:click="setStatusFilter('')"
                    class="rounded-md px-3 py-1.5 font-semibold transition {{ $statusFilter === '' ? 'bg-gradient-to-br from-blue-600 to-purple-600 text-white shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">
                    Todas
                </button>
                <button type="button" wire:click="setStatusFilter('confirmed')"
                    class="rounded-md px-3 py-1.5 font-semibold transition {{ $statusFilter === 'confirmed' ? 'bg-blue-600 text-white shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">
                    Pendiente
                </button>
                <button type="button" wire:click="setStatusFilter('partially_paid')"
                    class="rounded-md px-3 py-1.5 font-semibold transition {{ $statusFilter === 'partially_paid' ? 'bg-amber-600 text-white shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">
                    Parcial
                </button>
                <button type="button" wire:click="setStatusFilter('paid')"
                    class="rounded-md px-3 py-1.5 font-semibold transition {{ $statusFilter === 'paid' ? 'bg-emerald-600 text-white shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">
                    Pagada
                </button>
                <button type="button" wire:click="setStatusFilter('cancelled')"
                    class="rounded-md px-3 py-1.5 font-semibold transition {{ $statusFilter === 'cancelled' ? 'bg-rose-600 text-white shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">
                    Cancelada
                </button>
                <button type="button" wire:click="setStatusFilter('archived')"
                    class="rounded-md px-3 py-1.5 font-semibold transition {{ $statusFilter === 'archived' ? 'bg-amber-800 text-white shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">
                    Archivadas
                </button>
            </div>

            <div class="mt-6 overflow-x-auto">
                <table class="min-w-full divide-y divide-stone-200 text-sm">
                    <thead>
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <th class="pb-2">Compra</th>
                            <th class="pb-2">Proveedor</th>
                            <th class="pb-2">Sucursal / Bodega</th>
                            <th class="pb-2 w-px whitespace-nowrap">Estado</th>
                            <th class="pb-2 w-px whitespace-nowrap">Vence</th>
                            <th class="pb-2 text-right whitespace-nowrap">Total / Saldo</th>
                            <th class="pb-2 w-px whitespace-nowrap text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-100">
                        @forelse ($purchases as $purchase)
                            @php
                                $supplierLabel = trim(implode(' ', array_filter([
                                    $purchase->supplier?->person?->first_name,
                                    $purchase->supplier?->person?->last_name,
                                ])));
                                $supplierLabel = $supplierLabel !== '' ? $supplierLabel : ($purchase->supplier_name ?: 'Sin proveedor');
                            @endphp
                            <tr wire:key="purchase-row-{{ $purchase->id }}" class="even:bg-gray-50">
                                <td class="py-3 align-middle">
                                    <p class="font-semibold text-gray-900">{{ $purchase->invoice_number ?: 'Compra #'.$purchase->company_sequence }}</p>
                                    <p class="text-xs text-gray-400">{{ optional($purchase->purchased_at)->format('d/m/Y') ?: 'Sin fecha' }}</p>
                                </td>
                                <td class="py-3 align-middle text-gray-700">
                                    {{ $supplierLabel }}
                                </td>
                                <td class="py-3 align-middle text-xs text-gray-500">
                                    <p>{{ $purchase->branch?->name }}</p>
                                    <p>{{ $purchase->warehouse?->name }}</p>
                                </td>
                                <td class="py-3 align-middle w-px whitespace-nowrap">
                                    <x-status-badge :color="$purchase->status === 'paid' ? 'emerald' :
                                        ($purchase->status === 'cancelled' ? 'rose' : 'amber')">
                                        {{ match($purchase->status) {
                                            'confirmed'      => 'Pendiente',
                                            'partially_paid' => 'Parcial',
                                            'paid'           => 'Pagada',
                                            'cancelled'      => 'Cancelada',
                                            default          => $purchase->status
                                        } }}
                                    </x-status-badge>
                                </td>
                                <td class="py-3 align-middle w-px whitespace-nowrap">
                                    {{-- Semaforo: verde = falta tiempo, amarillo = vence en <=7 dias,
                                         rojo = vence hoy o ya paso. Null cuando no aplica (pagada,
                                         cancelada, sin saldo o sin fecha limite). --}}
                                    @php $due = $this->dueStatus($purchase); @endphp
                                    @if ($due)
                                        <x-status-badge :color="$due['color']">{{ $due['label'] }}</x-status-badge>
                                    @else
                                        <span class="text-xs text-gray-300">—</span>
                                    @endif
                                </td>
                                <td class="py-3 align-middle text-right whitespace-nowrap">
                                    <p class="font-semibold text-gray-900">${{ \App\Support\Money::format((float) $purchase->total) }}</p>
                                    @if ((float) $purchase->balance_due > 0)
                                        <p class="text-xs text-blue-600">Saldo ${{ \App\Support\Money::format((float) $purchase->balance_due) }}</p>
                                    @endif
                                </td>
                                <td class="py-3 align-middle">
                                    @php
                                        $tip = "x-data=\"{tip:false,p:{},show(e){const r=e.getBoundingClientRect();this.p={t:r.top-38,l:r.left+r.width/2};this.tip=true;}}\"";
                                    @endphp
                                    <div class="flex justify-end items-center gap-1">

                                        @if ($purchase->deleted_at)
                                            <button wire:click="restorePurchase({{ $purchase->id }})"
                                                class="rounded-full border border-emerald-300 px-3 py-1 text-xs font-medium text-emerald-700 hover:bg-emerald-50">
                                                Restaurar
                                            </button>
                                        @else

                                        <div {!! $tip !!}>
                                            <button wire:click="openLedger({{ $purchase->id }})"
                                                @mouseenter="show($el)" @mouseleave="tip=false"
                                                class="p-1.5 rounded-lg transition {{ $ledgerPurchaseId === $purchase->id ? 'text-gray-700 bg-gray-100' : 'text-gray-400 hover:text-gray-700 hover:bg-gray-100' }}">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                                                </svg>
                                            </button>
                                            <div x-show="tip" :style="`position:fixed;top:${p.t}px;left:${p.l}px;transform:translateX(-50%);z-index:9999`"
                                                class="pointer-events-none whitespace-nowrap rounded-lg bg-blue-600 px-2.5 py-1 text-xs font-semibold text-white shadow-lg">
                                                Movimientos{{ $canManagePayables && in_array($purchase->status, ['confirmed', 'partially_paid'], true) ? ' y pagos' : '' }}
                                                <div class="absolute left-1/2 top-full -translate-x-1/2 border-4 border-transparent border-t-stone-900"></div>
                                            </div>
                                        </div>

                                        @if (in_array($purchase->status, ['confirmed', 'partially_paid', 'paid'], true))
                                        <div {!! $tip !!}>
                                            <button wire:click="cancelPurchase({{ $purchase->id }})"
                                                wire:confirm="¿Cancelar esta compra? Esta accion no se puede deshacer."
                                                @mouseenter="show($el)" @mouseleave="tip=false"
                                                class="p-1.5 rounded-lg text-gray-400 hover:text-rose-600 hover:bg-rose-50 transition">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/>
                                                </svg>
                                            </button>
                                            <div x-show="tip" :style="`position:fixed;top:${p.t}px;left:${p.l}px;transform:translateX(-50%);z-index:9999`"
                                                class="pointer-events-none whitespace-nowrap rounded-lg bg-blue-600 px-2.5 py-1 text-xs font-semibold text-white shadow-lg">
                                                Cancelar compra
                                                <div class="absolute left-1/2 top-full -translate-x-1/2 border-4 border-transparent border-t-stone-900"></div>
                                            </div>
                                        </div>
                                        @endif

                                        <div {!! $tip !!}>
                                            <button wire:click="archivePurchase({{ $purchase->id }})"
                                                wire:confirm="¿Archivar esta compra? Podras restaurarla despues."
                                                @mouseenter="show($el)" @mouseleave="tip=false"
                                                class="p-1.5 rounded-lg text-gray-400 hover:text-rose-600 hover:bg-rose-50 transition">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                </svg>
                                            </button>
                                            <div x-show="tip" :style="`position:fixed;top:${p.t}px;left:${p.l}px;transform:translateX(-50%);z-index:9999`"
                                                class="pointer-events-none whitespace-nowrap rounded-lg bg-blue-600 px-2.5 py-1 text-xs font-semibold text-white shadow-lg">
                                                Archivar
                                                <div class="absolute left-1/2 top-full -translate-x-1/2 border-4 border-transparent border-t-stone-900"></div>
                                            </div>
                                        </div>

                                        @endif

                                    </div>
                                </td>
                            </tr>

                        @empty
                            <tr>
                                <td colspan="7" class="py-10 text-center text-gray-400">
                                    Aun no hay compras registradas con el filtro actual.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Boton flotante + --}}
    @if ($canCreatePurchases)
        <button wire:click="openModal" title="Nueva compra"
            style="position:fixed;bottom:2rem;right:2rem;z-index:9999;width:3.5rem;height:3.5rem;border-radius:9999px;background:#2563eb;color:#fff;border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;box-shadow:0 8px 24px rgba(0,0,0,0.25);"
            onmouseover="this.style.background='#b45309'" onmouseout="this.style.background='#1c1917'">
            <svg xmlns="http://www.w3.org/2000/svg" style="width:1.75rem;height:1.75rem" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
            </svg>
        </button>
    @endif

    {{-- Modal crear / editar compra --}}
    @if ($showModal)
        <div wire:click.self="closeModal"
            class="fixed inset-0 z-50 flex items-center justify-center p-4"
            style="background: rgba(0,0,0,0.5);">
            <div class="w-full max-w-2xl rounded-xl bg-white shadow-xl flex flex-col" style="max-height: 90vh;">

                {{-- Header --}}
                <div class="flex-shrink-0 flex items-center justify-between border-b border-stone-100 px-5 py-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-widest text-blue-700">Nueva</p>
                        <h3 class="mt-0.5 text-lg font-black text-gray-900">Compra</h3>
                    </div>
                    <button wire:click="closeModal" class="text-gray-400 hover:text-gray-700 text-xl leading-none px-1">&times;</button>
                </div>

                {{-- Form --}}
                <form wire:submit="savePurchase" class="flex flex-col flex-1 min-h-0">
                    <div class="flex-1 overflow-y-auto px-5 py-4 space-y-4">

                        @php $showBranch = $branches->count() > 1; $showWarehouse = $warehouses->count() > 1; @endphp
                        @if ($showBranch || $showWarehouse)
                        <div class="grid gap-3 {{ $showBranch && $showWarehouse ? 'sm:grid-cols-2' : '' }}">
                            @if ($showBranch)
                            <div>
                                <label for="purchase-branch" class="text-xs font-medium text-gray-700">Sucursal</label>
                                <select wire:model.live="branchId" id="purchase-branch"
                                    class="mt-1 block w-full rounded-xl border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600 text-sm">
                                    <option value="">Selecciona</option>
                                    @foreach ($branches as $branch)
                                        <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                                    @endforeach
                                </select>
                                @error('branchId') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                            </div>
                            @endif
                            @if ($showWarehouse)
                            <div>
                                <label for="purchase-warehouse" class="text-xs font-medium text-gray-700">Bodega</label>
                                <select wire:model="warehouseId" id="purchase-warehouse"
                                    class="mt-1 block w-full rounded-xl border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600 text-sm">
                                    <option value="">Selecciona</option>
                                    @foreach ($warehouses as $warehouse)
                                        <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                                    @endforeach
                                </select>
                                @error('warehouseId') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                            </div>
                            @endif
                        </div>
                        @endif

                        @php
                            $suppliersJson = $suppliers->map(fn($s) => [
                                'id' => $s->id,
                                'name' => trim(implode(' ', array_filter([$s->person?->first_name, $s->person?->last_name]))) ?: 'Proveedor '.$s->id,
                            ])->values()->toJson();
                            $initialSupplierDisplay = '';
                            if ($supplierId) {
                                $foundSupplier = $suppliers->firstWhere('id', $supplierId);
                                if ($foundSupplier) {
                                    $initialSupplierDisplay = trim(implode(' ', array_filter([$foundSupplier->person?->first_name, $foundSupplier->person?->last_name])));
                                }
                            } elseif ($supplierName) {
                                $initialSupplierDisplay = $supplierName;
                            }
                        @endphp
                        <div class="relative"
                            x-data="{
                                open: false,
                                query: @js($initialSupplierDisplay),
                                suppliers: {{ $suppliersJson }},
                                get filtered() {
                                    const q = this.query.toLowerCase();
                                    if (!q) return this.suppliers.slice(0, 8);
                                    return this.suppliers.filter(s => s.name.toLowerCase().includes(q)).slice(0, 8);
                                },
                                select(s) {
                                    this.query = s.name;
                                    this.open = false;
                                    $wire.supplierId = s.id;
                                    $wire.supplierName = '';
                                },
                                clearSupplierId() {
                                    $wire.supplierId = null;
                                }
                            }"
                            @click.outside="open = false">
                            <label class="text-xs font-medium text-gray-700">Proveedor</label>
                            <input type="text"
                                x-model="query"
                                @focus="open = true"
                                @input="open = true; clearSupplierId(); $wire.supplierName = $event.target.value"
                                placeholder="Buscar o escribir proveedor..."
                                autocomplete="off"
                                class="mt-1 block w-full rounded-xl border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600 text-sm">
                            <div x-show="open && filtered.length > 0"
                                class="absolute z-20 mt-1 w-full rounded-xl bg-white shadow-lg ring-1 ring-gray-200 max-h-48 overflow-y-auto">
                                <template x-for="s in filtered" :key="s.id">
                                    <button type="button" @click.stop="select(s)"
                                        class="w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-50"
                                        x-text="s.name"></button>
                                </template>
                            </div>
                            @error('supplierId') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                            @error('supplierName') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <label for="purchase-invoice-number" class="text-xs font-medium text-gray-700">Factura</label>
                                <input wire:model="invoiceNumber" id="purchase-invoice-number" type="text"
                                    class="mt-1 block w-full rounded-xl border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600 text-sm">
                                @error('invoiceNumber') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="purchase-type" class="text-xs font-medium text-gray-700">Tipo</label>
                                <select wire:model="purchaseType" id="purchase-type"
                                    class="mt-1 block w-full rounded-xl border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600 text-sm">
                                    <option value="invoice">Factura</option>
                                    <option value="remission">Remision</option>
                                    <option value="receipt">Recibo de caja</option>
                                    <option value="expense">Gasto</option>
                                    <option value="other">Otro</option>
                                </select>
                                @error('purchaseType') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-3">
                            <div>
                                <label for="purchase-status" class="text-xs font-medium text-gray-700">Estado inicial</label>
                                <select wire:model.live="purchaseStatus" id="purchase-status"
                                    class="mt-1 block w-full rounded-xl border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600 text-sm">
                                    <option value="confirmed">Pendiente</option>
                                    <option value="partially_paid">Parcialmente pagada</option>
                                    <option value="paid">Pagada</option>
                                </select>
                                @error('purchaseStatus') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="purchase-purchased-at" class="text-xs font-medium text-gray-700">Fecha compra</label>
                                <input wire:model="purchasedAt" id="purchase-purchased-at" type="date"
                                    class="mt-1 block w-full rounded-xl border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600 text-sm">
                                @error('purchasedAt') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="purchase-due-at" class="text-xs font-medium text-gray-700">Vence</label>
                                <input wire:model="dueAt" id="purchase-due-at" type="date"
                                    class="mt-1 block w-full rounded-xl border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600 text-sm">
                                @error('dueAt') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        @if (in_array($purchaseStatus, ['partially_paid', 'paid'], true))
                            <div>
                                <label for="purchase-initial-paid-amount" class="text-xs font-medium text-gray-700">Pago inicial</label>
                                <input wire:model="initialPaidAmount" id="purchase-initial-paid-amount" type="number" min="0" step="0.01"
                                    class="mt-1 block w-full rounded-xl border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600 text-sm">
                                @error('initialPaidAmount') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                            </div>
                        @endif

                        <div>
                            <label for="purchase-total-amount" class="text-xs font-medium text-gray-700">Monto total</label>
                            <input wire:model="totalAmount" id="purchase-total-amount" type="number" min="0.01" step="0.01"
                                class="mt-1 block w-full rounded-xl border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600 text-sm">
                            @error('totalAmount') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="purchase-notes" class="text-xs font-medium text-gray-700">Notas</label>
                            <textarea wire:model="notes" id="purchase-notes" rows="3"
                                class="mt-1 block w-full rounded-xl border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600 text-sm"></textarea>
                            @error('notes') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                        </div>

                    </div>{{-- /scrollable body --}}

                    {{-- Footer --}}
                    <div class="flex-shrink-0 flex gap-2 border-t border-stone-100 px-5 py-3">
                        <button type="button" wire:click="closeModal"
                            class="rounded-full border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                            Cancelar
                        </button>
                        <button type="submit"
                            class="rounded-full bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-60">
                            Guardar compra
                        </button>
                    </div>
                </form>

            </div>
        </div>
    @endif

    {{-- Modal de movimientos (ledger) y registro de pagos de una compra --}}
    @if ($ledgerPurchaseId && $ledgerPurchase)
        <div wire:click.self="closeLedger"
            class="fixed inset-0 z-50 flex items-center justify-center p-4"
            style="background: rgba(0,0,0,0.5);">
            <div class="w-full max-w-2xl rounded-xl bg-white shadow-xl flex flex-col" style="max-height: 90vh;">

                {{-- Header --}}
                <div class="flex-shrink-0 flex items-center justify-between border-b border-stone-100 px-5 py-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-widest text-blue-700">Movimientos</p>
                        <h3 class="mt-0.5 text-lg font-black text-gray-900">{{ $ledgerPurchase->invoice_number ?: 'Compra #'.$ledgerPurchase->company_sequence }}</h3>
                    </div>
                    <button wire:click="closeLedger" class="text-gray-400 hover:text-gray-700 text-xl leading-none px-1">&times;</button>
                </div>

                <div class="flex-1 overflow-y-auto px-5 py-4 space-y-4">

                    @if ($canManagePayables && in_array($ledgerPurchase->status, ['confirmed', 'partially_paid'], true) && (float) $ledgerPurchase->balance_due > 0)
                        <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4">
                            <p class="mb-3 text-xs font-semibold uppercase tracking-[0.18em] text-emerald-700">Registrar pago</p>
                            <div class="flex flex-col gap-3 md:flex-row md:items-end">
                                <div class="flex-1" x-data="digitGroupInput({ path: 'paymentAmount', live: false })">
                                    <label class="text-xs font-medium text-gray-700">Monto</label>
                                    <input type="text" inputmode="numeric" @input="onInput($event)"
                                        value="{{ $paymentAmount !== '' ? number_format((int) $paymentAmount, 0, ',', '.') : '' }}"
                                        class="mt-1 block w-full rounded-xl border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 text-sm">
                                    @error('paymentAmount') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                                </div>
                                <div class="flex-1">
                                    <label class="text-xs font-medium text-gray-700">Referencia</label>
                                    <input wire:model="paymentReference" type="text"
                                        class="mt-1 block w-full rounded-xl border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 text-sm">
                                    @error('paymentReference') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                                </div>
                                <div class="flex gap-2">
                                    <button wire:click="registerPayment"
                                        class="rounded-full bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                                        Confirmar
                                    </button>
                                </div>
                            </div>
                        </div>
                    @endif

                    @include('livewire.purchases.partials.payable-ledger', ['purchase' => $ledgerPurchase])

                </div>

                <div class="flex-shrink-0 flex gap-2 border-t border-stone-100 px-5 py-3">
                    <button type="button" wire:click="closeLedger"
                        class="rounded-full border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                        Cerrar
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
