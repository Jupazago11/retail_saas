<div class="py-10">
    <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
        <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-[0.22em] text-blue-700">Reportes</p>
                    <h3 class="mt-2 text-2xl font-black text-gray-900">Corte operativo</h3>
                </div>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-[160px_160px_220px]">
                    <input wire:model.live="dateFrom" type="date" class="rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600">
                    <input wire:model.live="dateTo" type="date" class="rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600">
                    <x-searchable-select
                        id="operational-reports-filter-branch"
                        model="branchId"
                        placeholder="Todas las sucursales"
                        live
                        :options="$branches->map(fn ($branch) => ['id' => $branch->id, 'label' => $branch->name])"
                    />
                </div>
            </div>
        </div>

        @if ($purchasesEnabled)
        {{-- Contraste inmediato del periodo filtrado: cuanto entro por ventas
             vs cuanto salio en compras, antes de entrar al detalle de cada
             modulo en las 2 columnas de abajo. Solo tiene sentido si el plan
             incluye compras — si no, no hay nada contra que contrastar. --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div class="rounded-xl bg-white px-5 py-5 ring-1 ring-blue-200">
                <p class="text-xs uppercase tracking-[0.18em] text-blue-600">Ingresos del periodo</p>
                <p class="mt-2 text-2xl font-black text-blue-700">${{ $periodBalance['income'] }}</p>
                <p class="mt-1 text-xs text-gray-400">Total vendido</p>
            </div>
            <div class="rounded-xl bg-white px-5 py-5 ring-1 ring-amber-200">
                <p class="text-xs uppercase tracking-[0.18em] text-amber-600">Gastos del periodo</p>
                <p class="mt-2 text-2xl font-black text-amber-700">${{ $periodBalance['expense'] }}</p>
                <p class="mt-1 text-xs text-gray-400">Total comprado</p>
            </div>
            <div class="rounded-xl px-5 py-5 text-white {{ $periodBalance['is_positive'] ? 'bg-gradient-to-br from-emerald-600 to-emerald-500' : 'bg-gradient-to-br from-rose-600 to-rose-500' }}">
                <p class="text-xs uppercase tracking-[0.18em] {{ $periodBalance['is_positive'] ? 'text-emerald-100' : 'text-rose-100' }}">
                    {{ $periodBalance['is_positive'] ? 'Ganaste' : 'Gastaste de mas' }}
                </p>
                <p class="mt-2 text-2xl font-black">${{ $periodBalance['difference'] }}</p>
                <p class="mt-1 text-xs {{ $periodBalance['is_positive'] ? 'text-emerald-100' : 'text-rose-100' }}">Ingresos menos gastos</p>
            </div>
        </div>

        {{-- Compras (gasto) y Ventas (ingreso) una al lado de la otra: mismo
             orden interno en las 2 columnas (tarjetas, tendencia, medios de
             pago) para que el contraste sea facil de leer de un vistazo. En
             movil se apilan (Compras primero, Ventas debajo). --}}
        <div class="grid grid-cols-1 items-stretch gap-6 xl:grid-cols-2">

            {{-- Columna Compras --}}
            <div class="h-full rounded-xl bg-white p-6 shadow-sm ring-1 ring-amber-200">
                <div class="flex items-center gap-2">
                    <span class="h-2.5 w-2.5 rounded-full bg-amber-500"></span>
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-[0.22em] text-amber-600">Compras</p>
                        <h3 class="mt-0.5 text-xl font-black text-gray-900">Lo que le compraste a tus proveedores</h3>
                    </div>
                </div>

                <div class="mt-5 grid grid-cols-2 gap-3">
                    <div class="rounded-lg bg-amber-50 px-4 py-4 ring-1 ring-amber-100">
                        <p class="text-[11px] uppercase tracking-[0.16em] text-amber-600">Total comprado</p>
                        <p class="mt-1.5 text-xl font-black text-amber-700">${{ $purchaseSummaryCards['purchases_total'] }}</p>
                    </div>
                    <div class="rounded-lg bg-gray-50 px-4 py-4 ring-1 ring-gray-200">
                        <p class="text-[11px] uppercase tracking-[0.16em] text-gray-500">Compras</p>
                        <p class="mt-1.5 text-xl font-black text-gray-900">{{ $purchaseSummaryCards['confirmed_purchases_count'] }}/{{ $purchaseSummaryCards['purchases_count'] }}</p>
                        <p class="mt-0.5 text-[11px] text-gray-400">vigentes / visibles</p>
                    </div>
                    <div class="rounded-lg bg-gray-50 px-4 py-4 ring-1 ring-gray-200">
                        <p class="text-[11px] uppercase tracking-[0.16em] text-gray-500">Pagado a proveedores</p>
                        <p class="mt-1.5 text-xl font-black text-gray-900">${{ $purchaseSummaryCards['payments_total'] }}</p>
                    </div>
                    <div class="rounded-lg bg-gray-50 px-4 py-4 ring-1 ring-rose-200">
                        <p class="text-[11px] uppercase tracking-[0.16em] text-rose-600">Pendiente a proveedores</p>
                        <p class="mt-1.5 text-xl font-black text-rose-700">${{ $purchaseSummaryCards['payables_balance_due'] }}</p>
                    </div>
                </div>

                <div class="mt-6">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-gray-500">Compras por dia</p>
                    <div class="mt-3">
                        <x-charts.line-chart :data="$purchasesTrend" label-key="date" value-key="purchases_total"
                            color="#eb6834" :money="true" aria-label="Compras por dia" />
                    </div>
                </div>

                <div class="mt-6">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-gray-500">De donde salio el dinero</p>

                    @if ($purchasePaymentMethodBreakdown->count() > 1)
                        <div class="mt-3">
                            <x-charts.bar-chart :data="$purchasePaymentMethodBreakdown" label-key="payment_method_label" value-key="payments_total"
                                :colors="['#eb6834', '#2a78d6', '#9ca3af']" :money="true"
                                aria-label="Compras por medio de pago" height="150" />
                        </div>
                    @endif

                    <div class="mt-3 space-y-2">
                        @forelse ($purchasePaymentMethodBreakdown as $method)
                            <div class="flex items-center justify-between rounded-lg bg-gray-50 px-4 py-2.5 ring-1 ring-gray-200">
                                <div>
                                    <p class="text-sm font-semibold text-gray-900">{{ $method['payment_method_label'] }}</p>
                                    <p class="text-xs text-gray-500">{{ $method['payments_count'] }} {{ \Illuminate\Support\Str::plural('pago', $method['payments_count']) }}</p>
                                </div>
                                <p class="text-sm font-medium text-gray-900">${{ $method['payments_total'] }}</p>
                            </div>
                        @empty
                            <div class="rounded-xl border border-dashed border-gray-300 bg-gray-50 p-6 text-center text-sm text-gray-500">
                                No hay pagos a proveedores para el filtro actual.
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>

            {{-- Columna Ventas --}}
            @include('livewire.reports.partials.sales-summary-panel')
        </div>
        @else
            {{-- Plan sin modulo de compras: la columna Ventas se muestra sola,
                 a ancho completo, sin el contraste (no hay nada que restar). --}}
            @include('livewire.reports.partials.sales-summary-panel')
        @endif

        {{-- Detalle operativo: conteos sueltos que no encajan en ninguna
             seccion tematica de abajo (cartera, promociones y margen ya se
             movieron a su seccion correspondiente para no repetir el mismo
             dato dos veces en la pagina). --}}
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 xl:grid-cols-4">
            <div class="rounded-xl bg-white px-5 py-5 ring-1 ring-gray-200">
                <p class="text-xs uppercase tracking-[0.18em] text-gray-500">Sesiones abiertas</p>
                <p class="mt-2 text-2xl font-black text-gray-900">{{ $summaryCards['open_cash_sessions_count'] }}</p>
            </div>
            <div class="rounded-xl bg-white px-5 py-5 ring-1 ring-sky-200">
                <p class="text-xs uppercase tracking-[0.18em] text-sky-600">Ventas devueltas</p>
                <p class="mt-2 text-2xl font-black text-sky-700">{{ $summaryCards['returned_sales_count'] }}</p>
            </div>
            <div class="rounded-xl bg-white px-5 py-5 ring-1 ring-rose-200">
                <p class="text-xs uppercase tracking-[0.18em] text-rose-600">Ventas anuladas</p>
                <p class="mt-2 text-2xl font-black text-rose-700">{{ $summaryCards['cancelled_sales_count'] }}</p>
            </div>
            @if ($purchasesEnabled && $purchaseSummaryCards['cancelled_purchases_count'] > 0)
                <div class="rounded-xl bg-white px-5 py-5 ring-1 ring-rose-200">
                    <p class="text-xs uppercase tracking-[0.18em] text-rose-600">Compras anuladas</p>
                    <p class="mt-2 text-2xl font-black text-rose-700">{{ $purchaseSummaryCards['cancelled_purchases_count'] }}</p>
                </div>
            @endif
            @if ($loyaltyEnabled)
                <div class="rounded-xl bg-white px-5 py-5 ring-1 ring-violet-200">
                    <p class="text-xs uppercase tracking-[0.18em] text-violet-600">Puntos vigentes</p>
                    <p class="mt-2 text-2xl font-black text-violet-700">{{ $summaryCards['loyalty_points_balance'] }}</p>
                </div>
            @endif
        </div>

        <div class="grid grid-cols-1 items-stretch gap-6 {{ $purchasesEnabled ? 'xl:grid-cols-2' : '' }}">
            <div class="h-full rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-[0.22em] text-blue-700">Productos</p>
                    <h3 class="mt-2 text-2xl font-black text-gray-900">Top vendidos</h3>
                </div>

                <div class="mt-4 space-y-2">
                    @forelse ($topProducts as $product)
                        <div class="flex items-center justify-between gap-3 rounded-lg bg-gray-50 px-4 py-2.5 ring-1 ring-gray-200">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-gray-900">{{ $product['product_name'] }}</p>
                                <p class="text-xs text-gray-500">Cantidad {{ $product['quantity_sum'] }}</p>
                            </div>
                            <p class="shrink-0 text-sm font-semibold text-gray-900">${{ $product['revenue_sum'] }}</p>
                        </div>
                    @empty
                        <div class="rounded-xl border border-dashed border-gray-300 bg-gray-50 p-8 text-center text-gray-500">
                            No hay productos para el filtro actual.
                        </div>
                    @endforelse
                </div>
            </div>

            @if ($purchasesEnabled)
                <div class="h-full rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-[0.22em] text-amber-600">Proveedores</p>
                        <h3 class="mt-2 text-2xl font-black text-gray-900">Mayor gasto</h3>
                    </div>

                    <div class="mt-6 space-y-3">
                        @forelse ($topSuppliers as $supplier)
                            <div class="rounded-lg bg-gray-50 p-4 ring-1 ring-gray-200">
                                <div class="flex flex-col gap-2 lg:flex-row lg:items-center lg:justify-between">
                                    <div>
                                        <p class="text-sm font-black text-gray-900">{{ $supplier['supplier_label'] }}</p>
                                        <p class="mt-1 text-sm text-gray-600">{{ $supplier['purchases_count'] }} {{ \Illuminate\Support\Str::plural('compra', $supplier['purchases_count']) }}</p>
                                    </div>
                                    <p class="text-sm text-gray-600">Total: <span class="font-medium text-gray-900">${{ $supplier['total_sum'] }}</span></p>
                                </div>
                            </div>
                        @empty
                            <div class="rounded-xl border border-dashed border-gray-300 bg-gray-50 p-8 text-center text-gray-500">
                                No hay compras para el filtro actual.
                            </div>
                        @endforelse
                    </div>
                </div>
            @endif
        </div>

        @if ($branches->count() > 1)
            <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-[0.22em] text-blue-700">Sucursales</p>
                    <h3 class="mt-2 text-2xl font-black text-gray-900">Desempeno por sede</h3>
                </div>

                @if ($branchBreakdown->count() > 1)
                    <div class="mt-6">
                        <x-charts.bar-chart :data="$branchBreakdown" label-key="branch_name" value-key="sales_total"
                            :colors="['#2a78d6', '#eb6834', '#1baf7a', '#eda100']" :money="true"
                            aria-label="Ventas por sucursal" height="180" />
                    </div>
                @endif

                <div class="mt-6 space-y-3">
                    @forelse ($branchBreakdown as $branch)
                        <div class="rounded-lg bg-gray-50 p-4 ring-1 ring-gray-200">
                            <div class="flex flex-col gap-2 lg:flex-row lg:items-center lg:justify-between">
                                <div>
                                    <p class="text-sm font-black text-gray-900">{{ $branch['branch_name'] }}</p>
                                    <p class="mt-1 text-sm text-gray-600">{{ $branch['sales_count'] }} ventas · {{ $branch['payments_count'] }} pagos</p>
                                </div>
                                <div class="grid gap-2 text-sm text-gray-600 md:grid-cols-2">
                                    <p>Ventas: <span class="font-medium text-gray-900">${{ $branch['sales_total'] }}</span></p>
                                    <p>Pagos: <span class="font-medium text-gray-900">${{ $branch['payments_total'] }}</span></p>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="rounded-xl border border-dashed border-gray-300 bg-gray-50 p-8 text-center text-gray-500">
                            No hay sucursales para el filtro actual.
                        </div>
                    @endforelse
                </div>
            </div>
        @endif

        @if ($cashEnabled)
            {{-- "Efectivo en caja" = suma de lo contado al cerrar cada sesion
                 (closing_counted_amount), no un calculo derivado — mismo
                 numero que ya se ve como "Contado" en el cuadre de cada dia
                 (ver Cash\CashSessionsPage). Una sesion sin cerrar no aporta
                 nada todavia, por eso el grafico puede tener menos puntos
                 que dias con actividad de ventas/compras en el mismo rango. --}}
            <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-teal-200">
                <div class="flex items-center gap-2">
                    <span class="h-2.5 w-2.5 rounded-full bg-teal-500"></span>
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-[0.22em] text-teal-600">Caja</p>
                        <h3 class="mt-0.5 text-xl font-black text-gray-900">Efectivo en caja por dia</h3>
                    </div>
                </div>

                <div class="mt-5">
                    <x-charts.line-chart :data="$cashOnHandTrend" label-key="date" value-key="cash_on_hand_total"
                        color="#0d9488" :money="true" aria-label="Efectivo en caja por dia" />
                </div>
            </div>
        @endif

        @if ($creditEnabled)
            <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-[0.22em] text-blue-700">Credito</p>
                        <h3 class="mt-2 text-2xl font-black text-gray-900">Aging de cartera</h3>
                    </div>
                    <div class="flex gap-3">
                        <div class="rounded-lg bg-rose-50 px-4 py-2.5 ring-1 ring-rose-200">
                            <p class="text-[11px] uppercase tracking-[0.16em] text-rose-600">Cartera vencida</p>
                            <p class="mt-1 text-lg font-black text-rose-700">${{ $summaryCards['credit_overdue_balance'] }}</p>
                        </div>
                        <div class="rounded-lg bg-gray-50 px-4 py-2.5 ring-1 ring-gray-200">
                            <p class="text-[11px] uppercase tracking-[0.16em] text-gray-500">Docs vencidos</p>
                            <p class="mt-1 text-lg font-black text-gray-900">{{ $summaryCards['credit_overdue_sales_count'] }}</p>
                        </div>
                    </div>
                </div>

                <div class="mt-6">
                    <x-charts.bar-chart :data="$creditAging" label-key="bucket_label" value-key="balance_total"
                        color="#2a78d6" :money="true" aria-label="Cartera vencida por antiguedad" height="180" />
                </div>

                <div class="mt-6 space-y-3">
                    @forelse ($creditAging as $bucket)
                        <div class="rounded-lg bg-gray-50 p-4 ring-1 ring-gray-200">
                            <div class="flex flex-col gap-2 lg:flex-row lg:items-center lg:justify-between">
                                <div>
                                    <p class="text-sm font-black text-gray-900">{{ $bucket['bucket_label'] }}</p>
                                    <p class="mt-1 text-sm text-gray-600">{{ $bucket['sales_count'] }} documentos abiertos</p>
                                </div>
                                <p class="text-sm text-gray-600">Saldo: <span class="font-medium text-gray-900">${{ $bucket['balance_total'] }}</span></p>
                            </div>
                        </div>
                    @empty
                        <div class="rounded-xl border border-dashed border-gray-300 bg-gray-50 p-8 text-center text-gray-500">
                            No hay cartera abierta para el filtro actual.
                        </div>
                    @endforelse
                </div>
            </div>
        @endif

        @if ($promotionsEnabled)
            <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-[0.22em] text-blue-700">Promociones</p>
                        <h3 class="mt-2 text-2xl font-black text-gray-900">Actividad comercial reciente</h3>
                    </div>
                    <div class="rounded-lg bg-blue-50 px-4 py-2.5 ring-1 ring-blue-200">
                        <p class="text-[11px] uppercase tracking-[0.16em] text-blue-600">Activas</p>
                        <p class="mt-1 text-lg font-black text-blue-700">{{ $summaryCards['active_promotions_count'] }}</p>
                    </div>
                </div>

                <div class="mt-6 space-y-3">
                    @forelse ($recentPromotions as $promotion)
                        <div class="rounded-lg bg-gray-50 p-4 ring-1 ring-gray-200">
                            <div class="flex flex-col gap-2 lg:flex-row lg:items-center lg:justify-between">
                                <div>
                                    <p class="text-sm font-black text-gray-900">{{ $promotion['name'] }}</p>
                                    <p class="mt-1 text-sm text-gray-600">{{ $promotion['promotion_type'] }} · {{ $promotion['status'] }}</p>
                                </div>
                                <div class="grid gap-2 text-sm text-gray-600 md:grid-cols-2">
                                    <p>Inicio: <span class="font-medium text-gray-900">{{ $promotion['starts_at'] ?: 'Inmediato' }}</span></p>
                                    <p>Fin: <span class="font-medium text-gray-900">{{ $promotion['ends_at'] ?: 'Sin cierre' }}</span></p>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="rounded-xl border border-dashed border-gray-300 bg-gray-50 p-8 text-center text-gray-500">
                            Aun no hay promociones registradas.
                        </div>
                    @endforelse
                </div>
            </div>
        @endif
    </div>
</div>
