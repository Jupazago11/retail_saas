<div class="pb-10">
    <div class="mx-auto max-w-7xl space-y-4 px-4 sm:px-6 lg:px-8">

        <x-purchases-nav active="purchases.payables" />

        {{-- Filtros --}}
        <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-[0.22em] text-blue-700">Filtros</p>
                    <h3 class="mt-1 text-2xl font-black text-gray-900">Consulta operativa</h3>
                </div>
                <p class="text-sm text-gray-500">{{ $supplierSummary->count() }} proveedores en el agregado</p>
            </div>

            <div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="sm:col-span-2">
                    <label for="payables-supplier-id" class="text-xs font-medium text-gray-700">Proveedor formal</label>
                    <select wire:model.live="supplierId" id="payables-supplier-id"
                        class="mt-1 block w-full rounded-xl border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600 text-sm">
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

                <div class="sm:col-span-2">
                    <label for="payables-supplier-name" class="text-xs font-medium text-gray-700">Nombre congelado en compra</label>
                    <input wire:model.live.debounce.300ms="supplierName" id="payables-supplier-name" type="text"
                        class="mt-1 block w-full rounded-xl border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600 text-sm">
                </div>

                <div class="sm:col-span-2 lg:col-span-4">
                    <label class="text-xs font-medium text-gray-700">Estado</label>
                    <div class="mt-1 inline-flex flex-wrap gap-1 rounded-lg border border-gray-200 bg-gray-50 p-1 text-sm">
                        <button type="button" wire:click="setStatus('open')"
                            class="rounded-md px-3 py-1.5 font-semibold transition {{ $status === 'open' ? 'bg-gradient-to-br from-blue-600 to-purple-600 text-white shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">
                            Abiertas
                        </button>
                        <button type="button" wire:click="setStatus('')"
                            class="rounded-md px-3 py-1.5 font-semibold transition {{ $status === '' ? 'bg-gradient-to-br from-blue-600 to-purple-600 text-white shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">
                            Todas
                        </button>
                        <button type="button" wire:click="setStatus('confirmed')"
                            class="rounded-md px-3 py-1.5 font-semibold transition {{ $status === 'confirmed' ? 'bg-blue-600 text-white shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">
                            Pendientes
                        </button>
                        <button type="button" wire:click="setStatus('partially_paid')"
                            class="rounded-md px-3 py-1.5 font-semibold transition {{ $status === 'partially_paid' ? 'bg-amber-600 text-white shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">
                            Parcialmente pagadas
                        </button>
                        <button type="button" wire:click="setStatus('paid')"
                            class="rounded-md px-3 py-1.5 font-semibold transition {{ $status === 'paid' ? 'bg-emerald-600 text-white shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">
                            Pagadas
                        </button>
                        <button type="button" wire:click="setStatus('cancelled')"
                            class="rounded-md px-3 py-1.5 font-semibold transition {{ $status === 'cancelled' ? 'bg-rose-600 text-white shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">
                            Canceladas
                        </button>
                    </div>
                </div>

                <div>
                    <label for="payables-aging-bucket" class="text-xs font-medium text-gray-700">Bucket aging</label>
                    <select wire:model.live="agingBucket" id="payables-aging-bucket"
                        class="mt-1 block w-full rounded-xl border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600 text-sm">
                        <option value="">Todos</option>
                        <option value="0_30">0-30 dias</option>
                        <option value="31_60">31-60 dias</option>
                        <option value="61_90">61-90 dias</option>
                        <option value="91_plus">91+ dias</option>
                    </select>
                </div>

                <div>
                    <label for="payables-due-from" class="text-xs font-medium text-gray-700">Vence desde</label>
                    <input wire:model.live="dueFrom" id="payables-due-from" type="date"
                        class="mt-1 block w-full rounded-xl border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600 text-sm">
                </div>

                <div>
                    <label for="payables-due-to" class="text-xs font-medium text-gray-700">Vence hasta</label>
                    <input wire:model.live="dueTo" id="payables-due-to" type="date"
                        class="mt-1 block w-full rounded-xl border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600 text-sm">
                </div>

                <div class="flex items-end gap-6 sm:col-span-2 lg:col-span-4">
                    <label class="inline-flex items-center gap-2 text-sm font-medium text-gray-700 cursor-pointer">
                        <input type="checkbox"
                            :checked="$wire.overdueOnly"
                            @change="$wire.set('overdueOnly', $event.target.checked)"
                            class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-600">
                        Solo vencidas
                    </label>
                    <label class="inline-flex items-center gap-2 text-sm font-medium text-gray-700 cursor-pointer">
                        <input type="checkbox"
                            :checked="$wire.hasCreditOnly"
                            @change="$wire.set('hasCreditOnly', $event.target.checked)"
                            class="rounded border-gray-300 text-emerald-600 shadow-sm focus:ring-emerald-500">
                        Solo con saldo a favor
                    </label>
                </div>
            </div>
        </div>

        {{-- Metricas globales --}}
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl bg-blue-600 px-5 py-5 text-white">
                <p class="text-xs uppercase tracking-[0.18em] text-stone-300">Saldo abierto</p>
                <p class="mt-2 text-2xl font-black">${{ \App\Support\Money::format((float) $openBalanceTotal) }}</p>
            </div>
            <div class="rounded-xl bg-white px-5 py-5 ring-1 ring-rose-200">
                <p class="text-xs uppercase tracking-[0.18em] text-rose-600">Saldo vencido</p>
                <p class="mt-2 text-2xl font-black text-rose-700">${{ \App\Support\Money::format((float) $overdueBalanceTotal) }}</p>
            </div>
            <div class="rounded-xl bg-white px-5 py-5 ring-1 ring-emerald-200">
                <p class="text-xs uppercase tracking-[0.18em] text-emerald-600">Saldo a favor</p>
                <p class="mt-2 text-2xl font-black text-emerald-700">${{ \App\Support\Money::format((float) $availableCreditTotal) }}</p>
            </div>
            <div class="rounded-xl bg-white px-5 py-5 ring-1 ring-amber-200">
                <p class="text-xs uppercase tracking-[0.18em] text-blue-700">Exposicion neta</p>
                <p class="mt-2 text-2xl font-black text-amber-800">${{ \App\Support\Money::format((float) $netExposureTotal) }}</p>
            </div>
        </div>

        {{-- Resumen por proveedor --}}
        @if ($supplierSummary->isNotEmpty())
            <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                <p class="text-sm font-semibold uppercase tracking-[0.22em] text-blue-700">Resumen</p>
                <h3 class="mt-1 text-2xl font-black text-gray-900">Proveedores filtrados</h3>

                <div class="mt-5 space-y-3">
                    @foreach ($supplierSummary as $summary)
                        <div class="rounded-lg bg-gray-50 px-4 py-4 ring-1 ring-gray-200">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <p class="font-semibold text-gray-900">{{ $summary['supplier_name'] }}</p>
                                    <p class="text-xs text-gray-500">
                                        {{ $summary['document_number'] ?: 'Sin documento' }}
                                        · {{ $summary['open_purchases_count'] }} compras abiertas
                                        · {{ $summary['overdue_purchases_count'] }} vencidas
                                    </p>
                                </div>
                                <span class="whitespace-nowrap rounded-full bg-blue-600 px-3 py-1 text-xs font-semibold text-white">
                                    Neto ${{ \App\Support\Money::format((float) $summary['net_balance_exposure']) }}
                                </span>
                            </div>

                            <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
                                <div class="rounded-lg bg-white px-3 py-3 lg:col-span-2">
                                    <p class="text-xs uppercase tracking-[0.18em] text-gray-500">Abierto / Vencido</p>
                                    <p class="mt-1 font-semibold text-gray-900">
                                        ${{ \App\Support\Money::format((float) $summary['open_balance_total']) }}
                                        / ${{ \App\Support\Money::format((float) $summary['overdue_balance_total']) }}
                                    </p>
                                </div>
                                <div class="rounded-lg bg-white px-3 py-3 lg:col-span-2">
                                    <p class="text-xs uppercase tracking-[0.18em] text-gray-500">Credito / Proximo venc.</p>
                                    <p class="mt-1 font-semibold text-gray-900">
                                        ${{ \App\Support\Money::format((float) $summary['credit_balance']) }}
                                        / {{ $summary['next_due_at'] ? \Illuminate\Support\Carbon::parse($summary['next_due_at'])->format('d/m/Y') : '—' }}
                                    </p>
                                </div>
                                <div class="rounded-lg bg-white px-3 py-3 text-center">
                                    <p class="text-[11px] uppercase tracking-[0.16em] text-gray-500">0-30</p>
                                    <p class="mt-1 text-sm font-semibold text-gray-900">${{ \App\Support\Money::format((float) $summary['age_0_30_balance_total']) }}</p>
                                </div>
                                <div class="rounded-lg bg-white px-3 py-3 text-center">
                                    <p class="text-[11px] uppercase tracking-[0.16em] text-gray-500">31-60</p>
                                    <p class="mt-1 text-sm font-semibold text-gray-900">${{ \App\Support\Money::format((float) $summary['age_31_60_balance_total']) }}</p>
                                </div>
                                <div class="rounded-lg bg-white px-3 py-3 text-center">
                                    <p class="text-[11px] uppercase tracking-[0.16em] text-gray-500">61-90</p>
                                    <p class="mt-1 text-sm font-semibold text-gray-900">${{ \App\Support\Money::format((float) $summary['age_61_90_balance_total']) }}</p>
                                </div>
                                <div class="rounded-lg bg-white px-3 py-3 text-center">
                                    <p class="text-[11px] uppercase tracking-[0.16em] text-gray-500">91+</p>
                                    <p class="mt-1 text-sm font-semibold text-gray-900">${{ \App\Support\Money::format((float) $summary['age_91_plus_balance_total']) }}</p>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Tabla de compras y saldos --}}
        <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.22em] text-blue-700">Listado</p>
                <h3 class="mt-1 text-2xl font-black text-gray-900">Compras y saldos</h3>
            </div>

            <div class="mt-6 overflow-x-auto">
                <table class="min-w-full divide-y divide-stone-200 text-sm">
                    <thead>
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <th class="pb-2">Compra</th>
                            <th class="pb-2">Proveedor</th>
                            <th class="pb-2">Vence</th>
                            <th class="pb-2">Pagado</th>
                            <th class="pb-2">Saldo</th>
                            <th class="pb-2 w-px whitespace-nowrap">Estado</th>
                            <th class="pb-2 w-px whitespace-nowrap text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-100">
                        @forelse ($payables as $purchase)
                            @php
                                $fullName = trim(implode(' ', array_filter([
                                    $purchase->supplier?->person?->first_name,
                                    $purchase->supplier?->person?->last_name,
                                ])));
                                $supplierLabel = $fullName !== '' ? $fullName : ($purchase->supplier_name ?: 'Sin proveedor formal');
                            @endphp
                            <tr wire:key="purchase-payable-{{ $purchase->id }}" class="even:bg-gray-50">
                                <td class="py-3 align-middle">
                                    <p class="font-semibold text-gray-900">{{ $purchase->invoice_number ?: 'Compra #'.$purchase->company_sequence }}</p>
                                    <p class="text-xs text-gray-400">{{ optional($purchase->purchased_at)->format('d/m/Y') ?: 'Sin fecha' }}</p>
                                </td>
                                <td class="py-3 align-middle">
                                    <p class="font-medium text-gray-900">{{ $supplierLabel }}</p>
                                    @if ((float) ($purchase->supplier?->credit_balance ?? 0) > 0)
                                        <p class="text-xs text-emerald-600">
                                            A favor: ${{ \App\Support\Money::format((float) ($purchase->supplier?->credit_balance ?? 0)) }}
                                        </p>
                                    @endif
                                </td>
                                <td class="py-3 align-middle text-gray-600 text-sm">
                                    {{ optional($purchase->due_at)->format('d/m/Y') ?: '—' }}
                                </td>
                                <td class="py-3 align-middle text-gray-700 text-sm">
                                    ${{ \App\Support\Money::format((float) $purchase->amount_paid) }}
                                </td>
                                <td class="py-3 align-middle">
                                    <x-status-badge :color="(float) $purchase->balance_due > 0 ? 'amber' : 'emerald'">
                                        ${{ \App\Support\Money::format((float) $purchase->balance_due) }}
                                    </x-status-badge>
                                </td>
                                <td class="py-3 align-middle w-px whitespace-nowrap">
                                    <x-status-badge :color="$purchase->status === 'paid' ? 'emerald' :
                                        ($purchase->status === 'cancelled' ? 'rose' : 'stone')">
                                        {{ match($purchase->status) {
                                            'confirmed'      => 'Pendiente',
                                            'partially_paid' => 'Parcial',
                                            'paid'           => 'Pagada',
                                            'cancelled'      => 'Cancelada',
                                            default          => $purchase->status
                                        } }}
                                    </x-status-badge>
                                </td>
                                <td class="py-3 align-middle">
                                    @php $tip = "x-data=\"{tip:false,p:{},show(e){const r=e.getBoundingClientRect();this.p={t:r.top-38,l:r.left+r.width/2};this.tip=true;}}\""; @endphp
                                    <div class="flex justify-end items-center gap-1">

                                        <div {!! $tip !!}>
                                            <button wire:click="toggleLedger({{ $purchase->id }})"
                                                @mouseenter="show($el)" @mouseleave="tip=false"
                                                class="p-1.5 rounded-lg transition {{ $expandedLedgerPurchaseId === $purchase->id ? 'text-gray-700 bg-gray-100' : 'text-gray-400 hover:text-gray-700 hover:bg-gray-100' }}">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                                                </svg>
                                            </button>
                                            <div x-show="tip" :style="`position:fixed;top:${p.t}px;left:${p.l}px;transform:translateX(-50%);z-index:9999`"
                                                class="pointer-events-none whitespace-nowrap rounded-lg bg-blue-600 px-2.5 py-1 text-xs font-semibold text-white shadow-lg">
                                                Movimientos
                                                <div class="absolute left-1/2 top-full -translate-x-1/2 border-4 border-transparent border-t-stone-900"></div>
                                            </div>
                                        </div>

                                        @if ((float) $purchase->balance_due > 0 && (float) ($purchase->supplier?->credit_balance ?? 0) > 0)
                                        <div {!! $tip !!}>
                                            <button wire:click="startApplyingCredit({{ $purchase->id }})"
                                                @mouseenter="show($el)" @mouseleave="tip=false"
                                                class="p-1.5 rounded-lg text-gray-400 hover:text-emerald-600 hover:bg-emerald-50 transition">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z"/>
                                                </svg>
                                            </button>
                                            <div x-show="tip" :style="`position:fixed;top:${p.t}px;left:${p.l}px;transform:translateX(-50%);z-index:9999`"
                                                class="pointer-events-none whitespace-nowrap rounded-lg bg-blue-600 px-2.5 py-1 text-xs font-semibold text-white shadow-lg">
                                                Aplicar saldo a favor
                                                <div class="absolute left-1/2 top-full -translate-x-1/2 border-4 border-transparent border-t-stone-900"></div>
                                            </div>
                                        </div>
                                        @endif

                                    </div>
                                </td>
                            </tr>

                            @if ($applyingPurchaseId === $purchase->id)
                                <tr wire:key="purchase-credit-form-{{ $purchase->id }}" class="even:bg-gray-50">
                                    <td colspan="7" class="pb-4 pt-1">
                                        <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4">
                                            <p class="mb-3 text-xs font-semibold uppercase tracking-[0.18em] text-emerald-700">Aplicar saldo a favor · {{ $supplierLabel }}</p>
                                            <div class="flex flex-col gap-3 lg:flex-row lg:items-end">
                                                <div class="flex-1" x-data="digitGroupInput({ path: 'creditAmount', live: false })">
                                                    <label class="text-xs font-medium text-gray-700">Monto a aplicar</label>
                                                    <input type="text" inputmode="numeric" @input="onInput($event)"
                                                        value="{{ $creditAmount !== '' ? number_format((int) $creditAmount, 0, ',', '.') : '' }}"
                                                        class="mt-1 block w-full rounded-xl border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 text-sm">
                                                    @error('creditAmount') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                                                </div>
                                                <div class="flex-1">
                                                    <label class="text-xs font-medium text-gray-700">Referencia</label>
                                                    <input wire:model="creditReference" type="text"
                                                        class="mt-1 block w-full rounded-xl border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 text-sm">
                                                    @error('creditReference') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                                                </div>
                                                <div class="flex gap-2">
                                                    <button wire:click="applySupplierCredit"
                                                        class="rounded-full bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                                                        Confirmar
                                                    </button>
                                                    <button wire:click="cancelApplyingCredit"
                                                        class="rounded-full border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                                                        Cancelar
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @endif

                            @if ($expandedLedgerPurchaseId === $purchase->id)
                                <tr wire:key="purchase-ledger-{{ $purchase->id }}" class="even:bg-gray-50">
                                    <td colspan="7" class="pb-4 pt-1">
                                        @include('livewire.purchases.partials.payable-ledger', ['purchase' => $purchase])
                                    </td>
                                </tr>
                            @endif
                        @empty
                            <tr>
                                <td colspan="7" class="py-10 text-center text-gray-400">
                                    No hay compras que coincidan con el filtro actual.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>
