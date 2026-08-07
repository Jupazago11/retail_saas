<div class="py-10">
    <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
        <div class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-stone-200">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-[0.22em] text-amber-700">Filtros</p>
                    <h3 class="mt-2 text-2xl font-black text-stone-900">Corte operativo</h3>
                </div>

                <div class="grid gap-3 md:grid-cols-[160px_160px_220px]">
                    <input wire:model.live="dateFrom" type="date" class="rounded-2xl border-stone-300 shadow-sm focus:border-amber-500 focus:ring-amber-500">
                    <input wire:model.live="dateTo" type="date" class="rounded-2xl border-stone-300 shadow-sm focus:border-amber-500 focus:ring-amber-500">
                    <select wire:model.live="branchId" class="rounded-2xl border-stone-300 shadow-sm focus:border-amber-500 focus:ring-amber-500">
                        <option value="">Todas las sucursales</option>
                        @foreach ($branches as $branch)
                            <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        <div class="flex flex-wrap gap-4">
            <div class="min-w-[200px] flex-1 rounded-3xl bg-white p-5 shadow-sm ring-1 ring-stone-200">
                <p class="text-xs uppercase tracking-[0.18em] text-stone-500">Ventas</p>
                <p class="mt-2 text-3xl font-black text-stone-900">{{ $summaryCards['confirmed_sales_count'] }}/{{ $summaryCards['sales_count'] }}</p>
                <p class="mt-1 text-xs text-stone-500">confirmadas / visibles</p>
            </div>
            <div class="min-w-[200px] flex-1 rounded-3xl bg-white p-5 shadow-sm ring-1 ring-stone-200">
                <p class="text-xs uppercase tracking-[0.18em] text-stone-500">Facturacion</p>
                <p class="mt-2 text-3xl font-black text-emerald-700">${{ $summaryCards['sales_total'] }}</p>
            </div>
            <div class="min-w-[200px] flex-1 rounded-3xl bg-white p-5 shadow-sm ring-1 ring-stone-200">
                <p class="text-xs uppercase tracking-[0.18em] text-stone-500">Recaudo</p>
                <p class="mt-2 text-3xl font-black text-sky-700">${{ $summaryCards['payments_total'] }}</p>
            </div>
            @if ($creditEnabled)
                <div class="min-w-[200px] flex-1 rounded-3xl bg-white p-5 shadow-sm ring-1 ring-stone-200">
                    <p class="text-xs uppercase tracking-[0.18em] text-stone-500">Cartera</p>
                    <p class="mt-2 text-3xl font-black text-rose-700">${{ $summaryCards['credit_balance_due'] }}</p>
                </div>
            @endif
            @if ($loyaltyEnabled)
                <div class="min-w-[200px] flex-1 rounded-3xl bg-white p-5 shadow-sm ring-1 ring-stone-200">
                    <p class="text-xs uppercase tracking-[0.18em] text-stone-500">Puntos vigentes</p>
                    <p class="mt-2 text-3xl font-black text-stone-900">{{ $summaryCards['loyalty_points_balance'] }}</p>
                </div>
            @endif
            @if ($promotionsEnabled)
                <div class="min-w-[200px] flex-1 rounded-3xl bg-white p-5 shadow-sm ring-1 ring-stone-200">
                    <p class="text-xs uppercase tracking-[0.18em] text-stone-500">Promociones</p>
                    <p class="mt-2 text-3xl font-black text-amber-700">{{ $summaryCards['active_promotions_count'] }}</p>
                </div>
            @endif
            <div class="min-w-[200px] flex-1 rounded-3xl bg-white p-5 shadow-sm ring-1 ring-stone-200">
                <p class="text-xs uppercase tracking-[0.18em] text-stone-500">Sesiones abiertas</p>
                <p class="mt-2 text-3xl font-black text-stone-900">{{ $summaryCards['open_cash_sessions_count'] }}</p>
            </div>
            @if (isset($summaryCards['gross_margin_total']))
                <div class="min-w-[200px] flex-1 rounded-3xl bg-white p-5 shadow-sm ring-1 ring-stone-200">
                    <p class="text-xs uppercase tracking-[0.18em] text-stone-500">Margen bruto</p>
                    <p class="mt-2 text-3xl font-black text-emerald-700">${{ $summaryCards['gross_margin_total'] }}</p>
                </div>
            @endif
            <div class="min-w-[200px] flex-1 rounded-3xl bg-white p-5 shadow-sm ring-1 ring-stone-200">
                <p class="text-xs uppercase tracking-[0.18em] text-stone-500">Ventas devueltas</p>
                <p class="mt-2 text-3xl font-black text-sky-700">{{ $summaryCards['returned_sales_count'] }}</p>
            </div>
            <div class="min-w-[200px] flex-1 rounded-3xl bg-white p-5 shadow-sm ring-1 ring-stone-200">
                <p class="text-xs uppercase tracking-[0.18em] text-stone-500">Ventas anuladas</p>
                <p class="mt-2 text-3xl font-black text-rose-700">{{ $summaryCards['cancelled_sales_count'] }}</p>
            </div>
            @if ($creditEnabled)
                <div class="min-w-[200px] flex-1 rounded-3xl bg-white p-5 shadow-sm ring-1 ring-stone-200">
                    <p class="text-xs uppercase tracking-[0.18em] text-stone-500">Cartera vencida</p>
                    <p class="mt-2 text-3xl font-black text-rose-700">${{ $summaryCards['credit_overdue_balance'] }}</p>
                </div>
                <div class="min-w-[200px] flex-1 rounded-3xl bg-white p-5 shadow-sm ring-1 ring-stone-200">
                    <p class="text-xs uppercase tracking-[0.18em] text-stone-500">Docs vencidos</p>
                    <p class="mt-2 text-3xl font-black text-stone-900">{{ $summaryCards['credit_overdue_sales_count'] }}</p>
                </div>
            @endif
        </div>

        {{-- Ventas por dia --}}
        <div class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-stone-200">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.22em] text-amber-700">Tendencia</p>
                <h3 class="mt-2 text-2xl font-black text-stone-900">Ventas por dia</h3>
            </div>

            <div class="mt-6">
                <x-charts.line-chart :data="$salesTrend" label-key="date" value-key="sales_total"
                    color="#2a78d6" :money="true" aria-label="Ventas por dia" />
            </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-[1fr_1fr]">
            <div class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-stone-200">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-[0.22em] text-amber-700">Productos</p>
                        <h3 class="mt-2 text-2xl font-black text-stone-900">Top vendidos</h3>
                    </div>
                    <a href="{{ $this->exportUrl('products') }}" class="rounded-full border border-stone-300 px-4 py-2 text-sm font-semibold text-stone-700">
                        Exportar CSV
                    </a>
                </div>

                <div class="mt-6 space-y-3">
                    @forelse ($topProducts as $product)
                        <div class="rounded-2xl bg-stone-50 p-4 ring-1 ring-stone-200">
                            <div class="flex flex-col gap-2 lg:flex-row lg:items-center lg:justify-between">
                                <div>
                                    <p class="text-sm font-black text-stone-900">{{ $product['product_name'] }}</p>
                                    <p class="mt-1 text-sm text-stone-600">Cantidad {{ $product['quantity_sum'] }}</p>
                                </div>
                                <div class="grid gap-2 text-sm text-stone-600 md:grid-cols-{{ auth()->user()?->hasCurrentCompanyPermission('reports.view_costs') ? '3' : '1' }}">
                                    <p>Ingresos: <span class="font-medium text-stone-900">${{ $product['revenue_sum'] }}</span></p>
                                    @if (isset($product['cost_sum']))
                                        <p>Costo: <span class="font-medium text-stone-900">${{ $product['cost_sum'] }}</span></p>
                                        <p>Margen: <span class="font-medium text-stone-900">${{ $product['margin_sum'] }}</span></p>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="rounded-3xl border border-dashed border-stone-300 bg-stone-50 p-8 text-center text-stone-500">
                            No hay productos para el filtro actual.
                        </div>
                    @endforelse
                </div>
            </div>

            <div class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-stone-200">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-[0.22em] text-amber-700">Sucursales</p>
                        <h3 class="mt-2 text-2xl font-black text-stone-900">Desempeno por sede</h3>
                    </div>
                    <a href="{{ $this->exportUrl('branches') }}" class="rounded-full border border-stone-300 px-4 py-2 text-sm font-semibold text-stone-700">
                        Exportar CSV
                    </a>
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
                        <div class="rounded-2xl bg-stone-50 p-4 ring-1 ring-stone-200">
                            <div class="flex flex-col gap-2 lg:flex-row lg:items-center lg:justify-between">
                                <div>
                                    <p class="text-sm font-black text-stone-900">{{ $branch['branch_name'] }}</p>
                                    <p class="mt-1 text-sm text-stone-600">{{ $branch['sales_count'] }} ventas · {{ $branch['payments_count'] }} pagos</p>
                                </div>
                                <div class="grid gap-2 text-sm text-stone-600 md:grid-cols-2">
                                    <p>Ventas: <span class="font-medium text-stone-900">${{ $branch['sales_total'] }}</span></p>
                                    <p>Pagos: <span class="font-medium text-stone-900">${{ $branch['payments_total'] }}</span></p>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="rounded-3xl border border-dashed border-stone-300 bg-stone-50 p-8 text-center text-stone-500">
                            No hay sucursales para el filtro actual.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-[1fr_1fr]">
            <div class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-stone-200">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-[0.22em] text-amber-700">Recaudo</p>
                        <h3 class="mt-2 text-2xl font-black text-stone-900">Medios de pago</h3>
                    </div>
                    <a href="{{ $this->exportUrl('payment-methods') }}" class="rounded-full border border-stone-300 px-4 py-2 text-sm font-semibold text-stone-700">
                        Exportar CSV
                    </a>
                </div>

                @if ($paymentMethodBreakdown->count() > 1)
                    <div class="mt-6">
                        <x-charts.bar-chart :data="$paymentMethodBreakdown" label-key="payment_method_label" value-key="payments_total"
                            :colors="['#2a78d6', '#eb6834', '#1baf7a', '#eda100']" :money="true"
                            aria-label="Recaudo por medio de pago" height="180" />
                    </div>
                @endif

                <div class="mt-6 space-y-3">
                    @forelse ($paymentMethodBreakdown as $method)
                        <div class="rounded-2xl bg-stone-50 p-4 ring-1 ring-stone-200">
                            <div class="flex flex-col gap-2 lg:flex-row lg:items-center lg:justify-between">
                                <div>
                                    <p class="text-sm font-black text-stone-900">{{ $method['payment_method_label'] }}</p>
                                    <p class="mt-1 text-sm text-stone-600">{{ $method['payments_count'] }} movimientos confirmados</p>
                                </div>
                                <p class="text-sm text-stone-600">Total: <span class="font-medium text-stone-900">${{ $method['payments_total'] }}</span></p>
                            </div>
                        </div>
                    @empty
                        <div class="rounded-3xl border border-dashed border-stone-300 bg-stone-50 p-8 text-center text-stone-500">
                            No hay pagos confirmados para el filtro actual.
                        </div>
                    @endforelse
                </div>
            </div>

            @if ($creditEnabled)
                <div class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-stone-200">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <p class="text-sm font-semibold uppercase tracking-[0.22em] text-amber-700">Credito</p>
                            <h3 class="mt-2 text-2xl font-black text-stone-900">Aging de cartera</h3>
                        </div>
                        <a href="{{ $this->exportUrl('credit-aging') }}" class="rounded-full border border-stone-300 px-4 py-2 text-sm font-semibold text-stone-700">
                            Exportar CSV
                        </a>
                    </div>

                    <div class="mt-6">
                        <x-charts.bar-chart :data="$creditAging" label-key="bucket_label" value-key="balance_total"
                            color="#2a78d6" :money="true" aria-label="Cartera vencida por antiguedad" height="180" />
                    </div>

                    <div class="mt-6 space-y-3">
                        @forelse ($creditAging as $bucket)
                            <div class="rounded-2xl bg-stone-50 p-4 ring-1 ring-stone-200">
                                <div class="flex flex-col gap-2 lg:flex-row lg:items-center lg:justify-between">
                                    <div>
                                        <p class="text-sm font-black text-stone-900">{{ $bucket['bucket_label'] }}</p>
                                        <p class="mt-1 text-sm text-stone-600">{{ $bucket['sales_count'] }} documentos abiertos</p>
                                    </div>
                                    <p class="text-sm text-stone-600">Saldo: <span class="font-medium text-stone-900">${{ $bucket['balance_total'] }}</span></p>
                                </div>
                            </div>
                        @empty
                            <div class="rounded-3xl border border-dashed border-stone-300 bg-stone-50 p-8 text-center text-stone-500">
                                No hay cartera abierta para el filtro actual.
                            </div>
                        @endforelse
                    </div>
                </div>
            @endif
        </div>

        @if ($promotionsEnabled)
            <div class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-stone-200">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-[0.22em] text-amber-700">Promociones</p>
                    <h3 class="mt-2 text-2xl font-black text-stone-900">Actividad comercial reciente</h3>
                </div>

                <div class="mt-6 space-y-3">
                    @forelse ($recentPromotions as $promotion)
                        <div class="rounded-2xl bg-stone-50 p-4 ring-1 ring-stone-200">
                            <div class="flex flex-col gap-2 lg:flex-row lg:items-center lg:justify-between">
                                <div>
                                    <p class="text-sm font-black text-stone-900">{{ $promotion['name'] }}</p>
                                    <p class="mt-1 text-sm text-stone-600">{{ $promotion['promotion_type'] }} · {{ $promotion['status'] }}</p>
                                </div>
                                <div class="grid gap-2 text-sm text-stone-600 md:grid-cols-2">
                                    <p>Inicio: <span class="font-medium text-stone-900">{{ $promotion['starts_at'] ?: 'Inmediato' }}</span></p>
                                    <p>Fin: <span class="font-medium text-stone-900">{{ $promotion['ends_at'] ?: 'Sin cierre' }}</span></p>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="rounded-3xl border border-dashed border-stone-300 bg-stone-50 p-8 text-center text-stone-500">
                            Aun no hay promociones registradas.
                        </div>
                    @endforelse
                </div>
            </div>
        @endif
    </div>
</div>
