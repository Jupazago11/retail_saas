<div class="pb-10">
    <div class="mx-auto max-w-7xl space-y-4 px-4 sm:px-6 lg:px-8">

        <x-purchases-nav active="purchases.payables" />

        {{-- Filtros: solo lo esencial a la vista (proveedor, estado, vencidas);
             el resto queda en "Mas filtros" para no saturar la pantalla. --}}
        <div x-data="{ showMoreFilters: false }" class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-[0.22em] text-blue-700">Cuentas por pagar</p>
                    <h3 class="mt-1 text-2xl font-black text-gray-900">Que le debo a mis proveedores</h3>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <x-searchable-select
                        id="payables-filter-supplier"
                        model="supplierId"
                        placeholder="Todos los proveedores"
                        class="w-56"
                        live
                        :options="$suppliers->map(function ($supplier) {
                            $fullName = trim(implode(' ', array_filter([
                                $supplier->person?->first_name,
                                $supplier->person?->last_name,
                            ])));

                            return ['id' => $supplier->id, 'label' => $fullName !== '' ? $fullName : 'Proveedor '.$supplier->id];
                        })"
                    />

                    <div class="inline-flex flex-wrap gap-1 rounded-lg border border-gray-200 bg-gray-50 p-1 text-sm">
                        <button type="button" wire:click="setStatus('open')"
                            class="rounded-md px-3 py-1.5 font-semibold transition {{ $status === 'open' ? 'bg-gradient-to-br from-blue-600 to-purple-600 text-white shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">
                            Pendientes
                        </button>
                        <button type="button" wire:click="setStatus('')"
                            class="rounded-md px-3 py-1.5 font-semibold transition {{ $status === '' ? 'bg-gradient-to-br from-blue-600 to-purple-600 text-white shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">
                            Todas
                        </button>
                        <button type="button" wire:click="setStatus('paid')"
                            class="rounded-md px-3 py-1.5 font-semibold transition {{ $status === 'paid' ? 'bg-emerald-600 text-white shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">
                            Pagadas
                        </button>
                    </div>

                    <label class="inline-flex items-center gap-2 text-sm font-medium text-gray-700 cursor-pointer">
                        <input type="checkbox"
                            :checked="$wire.overdueOnly"
                            @change="$wire.set('overdueOnly', $event.target.checked)"
                            class="rounded border-gray-300 text-rose-600 shadow-sm focus:ring-rose-500">
                        Solo vencidas
                    </label>

                    <button type="button" @click="showMoreFilters = ! showMoreFilters"
                        class="text-sm font-semibold text-blue-700 hover:underline">
                        <span x-text="showMoreFilters ? 'Menos filtros' : 'Mas filtros'"></span>
                    </button>
                </div>
            </div>

            <div x-show="showMoreFilters" x-cloak class="mt-5 grid gap-4 border-t border-stone-100 pt-5 sm:grid-cols-2 lg:grid-cols-4">
                <div class="sm:col-span-2">
                    <label for="payables-supplier-name" class="text-xs font-medium text-gray-700">Nombre congelado en compra (sin proveedor formal)</label>
                    <input wire:model.live.debounce.300ms="supplierName" id="payables-supplier-name" type="text"
                        class="mt-1 block w-full rounded-xl border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600 text-sm">
                </div>

                <div>
                    <label for="payables-aging-bucket" class="text-xs font-medium text-gray-700">Antiguedad</label>
                    <x-searchable-select
                        id="payables-aging-bucket"
                        model="agingBucket"
                        placeholder="Todas"
                        class="mt-1"
                        live
                        :options="[
                            ['id' => '0_30', 'label' => '0-30 dias'],
                            ['id' => '31_60', 'label' => '31-60 dias'],
                            ['id' => '61_90', 'label' => '61-90 dias'],
                            ['id' => '91_plus', 'label' => '91+ dias'],
                        ]"
                    />
                </div>

                <div>
                    <label class="inline-flex items-center gap-2 text-sm font-medium text-gray-700 cursor-pointer mt-5">
                        <input type="checkbox"
                            :checked="$wire.hasCreditOnly"
                            @change="$wire.set('hasCreditOnly', $event.target.checked)"
                            class="rounded border-gray-300 text-emerald-600 shadow-sm focus:ring-emerald-500">
                        Solo con saldo a favor
                    </label>
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
            </div>
        </div>

        {{-- Metricas: cambian segun la pestaña activa (Pendientes/Todas/Pagadas)
             — antes eran fijas y en "Pagadas" mostraban deuda que no tenia
             nada que ver con lo que se estaba mirando. Cada set trae un
             numero protagonista (mas grande) mas 2-3 tarjetas de apoyo. --}}
        @php
            $agingColors = ['0_30' => '#10b981', '31_60' => '#f59e0b', '61_90' => '#f97316', '91_plus' => '#e11d48'];
            $agingTotal = array_sum($statusCards['aging']);
        @endphp

        @if ($statusCards['mode'] === 'open')
            <div class="grid gap-4 lg:grid-cols-4">
                <div class="rounded-xl bg-gradient-to-br from-blue-600 to-purple-600 px-6 py-5 text-white lg:col-span-2">
                    <p class="text-xs uppercase tracking-[0.18em] text-blue-100">Debes en total</p>
                    <p class="mt-2 text-3xl font-black">${{ \App\Support\Money::format((float) $statusCards['pending_amount']) }}</p>
                    <p class="mt-1 text-xs text-blue-100">
                        {{ $statusCards['purchases_count'] }} {{ $statusCards['purchases_count'] === 1 ? 'compra pendiente' : 'compras pendientes' }}
                    </p>
                    @if ($statusCards['overdue_amount'] > 0)
                        @php $vencidoPct = round($statusCards['overdue_amount'] / $statusCards['pending_amount'] * 100); @endphp
                        <div class="mt-3 flex h-1.5 overflow-hidden rounded-full bg-white/25">
                            <div class="bg-white" style="width: {{ 100 - $vencidoPct }}%"></div>
                            <div class="bg-white/40" style="width: {{ $vencidoPct }}%"></div>
                        </div>
                        <div class="mt-1.5 flex gap-3 text-[11px] text-blue-100">
                            <span>Vigente {{ 100 - $vencidoPct }}%</span>
                            <span>Vencido {{ $vencidoPct }}%</span>
                        </div>
                    @endif
                </div>

                <div class="rounded-xl bg-white px-5 py-5 ring-1 ring-rose-200">
                    <p class="text-xs uppercase tracking-[0.18em] text-rose-600">Vencido</p>
                    <p class="mt-2 text-2xl font-black text-rose-700">${{ \App\Support\Money::format((float) $statusCards['overdue_amount']) }}</p>
                    <p class="mt-1 text-xs text-gray-500">
                        {{ $statusCards['overdue_count'] }} {{ $statusCards['overdue_count'] === 1 ? 'compra vencida' : 'compras vencidas' }}
                    </p>
                    @if ($agingTotal > 0)
                        <div class="mt-3 flex h-1.5 overflow-hidden rounded-full bg-gray-100">
                            @foreach ($statusCards['aging'] as $bucket => $amount)
                                @continue($amount <= 0)
                                <div style="width: {{ $amount / $agingTotal * 100 }}%; background-color: {{ $agingColors[$bucket] }}"></div>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="rounded-xl bg-white px-5 py-5 ring-1 ring-emerald-200">
                    <p class="text-xs uppercase tracking-[0.18em] text-emerald-600">A tu favor</p>
                    <p class="mt-2 text-2xl font-black text-emerald-700">${{ \App\Support\Money::format((float) $statusCards['available_credit_total']) }}</p>
                    <p class="mt-1 text-xs text-gray-500">Aplicable a tus proximas compras</p>
                </div>
            </div>
        @elseif ($statusCards['mode'] === 'paid')
            <div class="grid gap-4 lg:grid-cols-3">
                <div class="rounded-xl bg-gradient-to-br from-blue-600 to-purple-600 px-6 py-5 text-white">
                    <p class="text-xs uppercase tracking-[0.18em] text-blue-100">Total pagado</p>
                    <p class="mt-2 text-3xl font-black">${{ \App\Support\Money::format((float) $statusCards['paid_amount']) }}</p>
                    <p class="mt-1 text-xs text-blue-100">
                        {{ $statusCards['paid_purchases_count'] }} {{ $statusCards['paid_purchases_count'] === 1 ? 'compra totalmente pagada' : 'compras totalmente pagadas' }}
                    </p>
                </div>

                <div class="rounded-xl bg-white px-5 py-5 ring-1 ring-emerald-200">
                    <p class="text-xs uppercase tracking-[0.18em] text-emerald-600">Pagadas a tiempo</p>
                    <p class="mt-2 text-2xl font-black text-emerald-700">
                        {{ $statusCards['paid_on_time_count'] }}
                        <span class="text-sm font-semibold text-gray-400">/ {{ $statusCards['paid_purchases_count'] }}</span>
                    </p>
                    @if ($statusCards['paid_purchases_count'] > 0)
                        <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-gray-100">
                            <div class="h-full bg-emerald-500" style="width: {{ round($statusCards['paid_on_time_count'] / $statusCards['paid_purchases_count'] * 100) }}%"></div>
                        </div>
                    @endif
                </div>

                <div class="rounded-xl bg-white px-5 py-5 ring-1 ring-rose-200">
                    <p class="text-xs uppercase tracking-[0.18em] text-rose-600">Con retraso</p>
                    <p class="mt-2 text-2xl font-black text-rose-700">
                        {{ $statusCards['paid_late_count'] }}
                        <span class="text-sm font-semibold text-gray-400">/ {{ $statusCards['paid_purchases_count'] }}</span>
                    </p>
                    @if ($statusCards['paid_purchases_count'] > 0)
                        <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-gray-100">
                            <div class="h-full bg-rose-500" style="width: {{ round($statusCards['paid_late_count'] / $statusCards['paid_purchases_count'] * 100) }}%"></div>
                        </div>
                    @endif
                </div>
            </div>
        @else
            <div class="grid gap-4 lg:grid-cols-4">
                <div class="rounded-xl bg-gradient-to-br from-blue-600 to-purple-600 px-6 py-5 text-white lg:col-span-2">
                    <p class="text-xs uppercase tracking-[0.18em] text-blue-100">Total en compras</p>
                    <p class="mt-2 text-3xl font-black">${{ \App\Support\Money::format((float) $statusCards['total_amount']) }}</p>
                    <p class="mt-1 text-xs text-blue-100">
                        {{ $statusCards['purchases_count'] }} {{ $statusCards['purchases_count'] === 1 ? 'compra registrada' : 'compras registradas' }}
                    </p>
                    @if ($statusCards['total_amount'] > 0)
                        @php $pagadoPct = round($statusCards['paid_amount'] / $statusCards['total_amount'] * 100); @endphp
                        <div class="mt-3 flex h-1.5 overflow-hidden rounded-full bg-white/25">
                            <div class="bg-white" style="width: {{ $pagadoPct }}%"></div>
                            <div class="bg-white/40" style="width: {{ 100 - $pagadoPct }}%"></div>
                        </div>
                        <div class="mt-1.5 flex gap-3 text-[11px] text-blue-100">
                            <span>Pagado {{ $pagadoPct }}%</span>
                            <span>Pendiente {{ 100 - $pagadoPct }}%</span>
                        </div>
                    @endif
                </div>

                <div class="rounded-xl bg-white px-5 py-5 ring-1 ring-emerald-200">
                    <p class="text-xs uppercase tracking-[0.18em] text-emerald-600">Pagado</p>
                    <p class="mt-2 text-2xl font-black text-emerald-700">${{ \App\Support\Money::format((float) $statusCards['paid_amount']) }}</p>
                </div>

                <div class="rounded-xl bg-white px-5 py-5 ring-1 ring-amber-200">
                    <p class="text-xs uppercase tracking-[0.18em] text-amber-600">Pendiente</p>
                    <p class="mt-2 text-2xl font-black text-amber-700">${{ \App\Support\Money::format((float) $statusCards['pending_amount']) }}</p>
                </div>

                @if ($statusCards['overdue_amount'] > 0)
                    <div class="rounded-xl bg-white px-5 py-5 ring-1 ring-rose-200">
                        <p class="text-xs uppercase tracking-[0.18em] text-rose-600">Vencido</p>
                        <p class="mt-2 text-2xl font-black text-rose-700">${{ \App\Support\Money::format((float) $statusCards['overdue_amount']) }}</p>
                        <p class="mt-1 text-xs text-gray-500">
                            {{ $statusCards['overdue_count'] }} {{ $statusCards['overdue_count'] === 1 ? 'compra vencida' : 'compras vencidas' }}
                        </p>
                    </div>
                @endif
            </div>
        @endif

        @if ($emptyDueToFilters)
            <p class="mt-2 text-xs text-gray-400">
                Ningun proveedor o compra cumple los filtros actuales &mdash; por eso las tarjetas estan en $0. Prueba a quitar algun filtro.
            </p>
        @endif

        {{-- Graficas: reemplazan la grilla densa de 6 columnas por proveedor
             que habia antes — de un vistazo se ve que tan vieja es la deuda
             y a quien se le debe mas, sin leer una tabla de numeros. --}}
        @if ($supplierSummary->isNotEmpty())
            <div class="grid gap-4 lg:grid-cols-2">
                <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                    <p class="text-sm font-semibold uppercase tracking-[0.22em] text-blue-700">Por antiguedad</p>
                    <h3 class="mt-1 text-lg font-black text-gray-900">Que tan vieja es la deuda</h3>
                    <div class="mt-4">
                        <x-charts.bar-chart
                            :data="$agingChartData"
                            :colors="['#10b981', '#f59e0b', '#f97316', '#e11d48']"
                            :money="true"
                            height="180"
                            aria-label="Saldo por antiguedad" />
                    </div>
                </div>

                @if ($topSuppliersChartData !== [])
                    <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                        <p class="text-sm font-semibold uppercase tracking-[0.22em] text-blue-700">Top proveedores</p>
                        <h3 class="mt-1 text-lg font-black text-gray-900">A quien le debes mas</h3>
                        <div class="mt-4">
                            <x-charts.bar-chart
                                :data="$topSuppliersChartData"
                                color="#2563eb"
                                :money="true"
                                height="180"
                                aria-label="Saldo pendiente por proveedor" />
                        </div>
                    </div>
                @endif
            </div>
        @endif

        {{-- De donde salio el dinero con el que se pago a proveedores —
             mismo indicador que ya existe en Reportes, pero acotado a los
             filtros actuales de esta pantalla (proveedor, estado, etc.). --}}
        @if ($paymentMethodBreakdown !== [])
            <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                <p class="text-sm font-semibold uppercase tracking-[0.22em] text-amber-600">Pagos</p>
                <h3 class="mt-1 text-lg font-black text-gray-900">De donde salio el dinero</h3>

                @if (count($paymentMethodBreakdown) > 1)
                    <div class="mt-4">
                        <x-charts.bar-chart
                            :data="$paymentMethodBreakdown"
                            label-key="payment_method_label"
                            value-key="payments_total"
                            :colors="['#eb6834', '#2a78d6', '#9ca3af']"
                            :money="true"
                            height="150"
                            aria-label="Pagos a proveedores por medio de pago" />
                    </div>
                @endif

                <div class="mt-4 grid gap-3 sm:grid-cols-{{ min(count($paymentMethodBreakdown), 3) }}">
                    @foreach ($paymentMethodBreakdown as $method)
                        <div class="rounded-lg bg-gray-50 px-4 py-3 ring-1 ring-gray-200">
                            <p class="text-sm font-semibold text-gray-900">{{ $method['payment_method_label'] }}</p>
                            <p class="mt-1 text-lg font-black text-gray-900">${{ $method['payments_total'] }}</p>
                            <p class="text-xs text-gray-500">{{ $method['payments_count'] }} {{ \Illuminate\Support\Str::plural('pago', $method['payments_count']) }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Resumen por proveedor: simplificado a nombre + saldo neto, sin la
             grilla de 6 estadisticas por fila (ya cubierta por las graficas). --}}
        @if ($supplierSummary->isNotEmpty())
            <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                <p class="text-sm font-semibold uppercase tracking-[0.22em] text-blue-700">Resumen</p>
                <h3 class="mt-1 text-lg font-black text-gray-900">Proveedores filtrados</h3>

                <div class="mt-4 divide-y divide-stone-100">
                    @foreach ($supplierSummary as $summary)
                        <div class="flex flex-wrap items-center justify-between gap-3 py-3">
                            <div>
                                <p class="font-semibold text-gray-900">{{ $summary['supplier_name'] }}</p>
                                <p class="text-xs text-gray-500">
                                    {{ $summary['open_purchases_count'] }} compras pendientes
                                    @if ($summary['overdue_purchases_count'] > 0)
                                        · <span class="text-rose-600">{{ $summary['overdue_purchases_count'] }} vencidas</span>
                                    @endif
                                    @if ($summary['next_due_at'])
                                        · vence {{ \Illuminate\Support\Carbon::parse($summary['next_due_at'])->format('d/m/Y') }}
                                    @endif
                                </p>
                            </div>
                            <div class="flex items-center gap-2">
                                @if ((float) $summary['credit_balance'] > 0)
                                    <x-status-badge color="emerald">
                                        A favor ${{ \App\Support\Money::format((float) $summary['credit_balance']) }}
                                    </x-status-badge>
                                @endif
                                <x-status-badge :color="(float) $summary['overdue_balance_total'] > 0 ? 'rose' : 'blue'">
                                    Debe ${{ \App\Support\Money::format((float) $summary['open_balance_total']) }}
                                </x-status-badge>
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
                                                    <label class="text-xs font-medium text-gray-700">Monto a aplicar <span class="text-rose-600">*</span></label>
                                                    <input type="text" inputmode="numeric" @input="onInput($event)"
                                                        value="{{ $creditAmount !== '' ? number_format((int) $creditAmount, 0, ',', '.') : '' }}"
                                                        class="mt-1 block w-full rounded-xl border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 text-sm">
                                                </div>
                                                <div class="flex-1">
                                                    <label class="text-xs font-medium text-gray-700">Referencia <span class="text-rose-600">*</span></label>
                                                    <input wire:model="creditReference" type="text"
                                                        class="mt-1 block w-full rounded-xl border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 text-sm">
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
