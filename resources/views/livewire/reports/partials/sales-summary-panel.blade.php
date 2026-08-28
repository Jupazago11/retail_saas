{{-- Panel de Ventas: vive aparte para poder reusarlo tanto dentro de la
     columna Compras/Ventas (empresa con modulo de compras) como solo, a
     ancho completo, cuando el plan no incluye compras y no hay con que
     contrastar (ver OperationalReportsPage::purchasesEnabled()). --}}
<div class="h-full rounded-xl bg-white p-6 shadow-sm ring-1 ring-blue-200">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-2">
            <span class="h-2.5 w-2.5 rounded-full bg-blue-600"></span>
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.22em] text-blue-700">Ventas</p>
                <h3 class="mt-0.5 text-xl font-black text-gray-900">Lo que tus clientes te compraron</h3>
            </div>
        </div>

        {{-- Filtro secundario: solo aparece si la empresa tiene mas de una
             caja habilitada (plan Basico solo permite 1). Acota todo lo de
             esta columna a una caja puntual. --}}
        @if ($cashRegisters->count() > 1)
            <div class="w-full sm:w-52">
                <x-searchable-select
                    id="operational-reports-filter-cash-register"
                    model="cashRegisterId"
                    placeholder="Todas las cajas"
                    live
                    :options="$cashRegisters->map(fn ($register) => ['id' => $register->id, 'label' => $register->name])"
                />
            </div>
        @endif
    </div>

    <div class="mt-5 grid grid-cols-2 gap-3">
        <div class="rounded-lg bg-blue-50 px-4 py-4 ring-1 ring-blue-100">
            <p class="text-[11px] uppercase tracking-[0.16em] text-blue-600">Facturacion</p>
            <p class="mt-1.5 text-xl font-black text-blue-700">${{ $summaryCards['sales_total'] }}</p>
        </div>
        <div class="rounded-lg bg-gray-50 px-4 py-4 ring-1 ring-gray-200">
            <p class="text-[11px] uppercase tracking-[0.16em] text-gray-500">Ventas</p>
            <p class="mt-1.5 text-xl font-black text-gray-900">{{ $summaryCards['confirmed_sales_count'] }}/{{ $summaryCards['sales_count'] }}</p>
            <p class="mt-0.5 text-[11px] text-gray-400">confirmadas / visibles</p>
        </div>
        <div class="rounded-lg bg-gray-50 px-4 py-4 ring-1 ring-gray-200">
            <p class="text-[11px] uppercase tracking-[0.16em] text-gray-500">Recaudo</p>
            <p class="mt-1.5 text-xl font-black text-gray-900">${{ $summaryCards['payments_total'] }}</p>
        </div>
        @if ($creditEnabled)
            <div class="rounded-lg bg-gray-50 px-4 py-4 ring-1 ring-rose-200">
                <p class="text-[11px] uppercase tracking-[0.16em] text-rose-600">Cartera</p>
                <p class="mt-1.5 text-xl font-black text-rose-700">${{ $summaryCards['credit_balance_due'] }}</p>
            </div>
        @endif
        @if (isset($summaryCards['gross_margin_total']))
            <div class="rounded-lg bg-gray-50 px-4 py-4 ring-1 ring-emerald-200">
                <p class="text-[11px] uppercase tracking-[0.16em] text-emerald-600">Margen bruto</p>
                <p class="mt-1.5 text-xl font-black text-emerald-700">${{ $summaryCards['gross_margin_total'] }}</p>
            </div>
        @endif
    </div>

    <div class="mt-6">
        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-gray-500">Ventas por dia</p>
        <div class="mt-3">
            <x-charts.line-chart :data="$salesTrend" label-key="date" value-key="sales_total"
                color="#2a78d6" :money="true" aria-label="Ventas por dia" />
        </div>
    </div>

    <div class="mt-6">
        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-gray-500">Medios de pago</p>

        @if ($paymentMethodBreakdown->count() > 1)
            <div class="mt-3">
                <x-charts.bar-chart :data="$paymentMethodBreakdown" label-key="payment_method_label" value-key="payments_total"
                    :colors="['#2a78d6', '#eb6834', '#1baf7a', '#eda100']" :money="true"
                    aria-label="Recaudo por medio de pago" height="150" />
            </div>
        @endif

        <div class="mt-3 space-y-2">
            @forelse ($paymentMethodBreakdown as $method)
                <div class="flex items-center justify-between rounded-lg bg-gray-50 px-4 py-2.5 ring-1 ring-gray-200">
                    <div>
                        <p class="text-sm font-semibold text-gray-900">{{ $method['payment_method_label'] }}</p>
                        <p class="text-xs text-gray-500">{{ $method['payments_count'] }} {{ \Illuminate\Support\Str::plural('movimiento', $method['payments_count']) }}</p>
                    </div>
                    <p class="text-sm font-medium text-gray-900">${{ $method['payments_total'] }}</p>
                </div>
            @empty
                <div class="rounded-xl border border-dashed border-gray-300 bg-gray-50 p-6 text-center text-sm text-gray-500">
                    No hay pagos confirmados para el filtro actual.
                </div>
            @endforelse
        </div>
    </div>
</div>
