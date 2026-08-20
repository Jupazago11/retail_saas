<div class="space-y-4">
    @php
        $chargeMovement = $purchase->payableMovements->firstWhere('movement_type', \App\Enums\PayableMovementType::PurchaseCharge->value);
        $otherMovements = $purchase->payableMovements
            ->reject(fn ($m) => $m->movement_type === \App\Enums\PayableMovementType::PurchaseCharge->value)
            ->sortByDesc('occurred_at')
            ->values();
        $ledgerSupplierLabel = null;
        if ($purchase->supplier) {
            $ledgerSupplierLabel = trim(implode(' ', array_filter([
                $purchase->supplier->person?->first_name,
                $purchase->supplier->person?->last_name,
            ])));
            $ledgerSupplierLabel = $ledgerSupplierLabel !== '' ? $ledgerSupplierLabel : 'Proveedor '.$purchase->supplier->id;
        } elseif ($purchase->supplier_name) {
            $ledgerSupplierLabel = $purchase->supplier_name;
        }
    @endphp

    {{-- La factura/cargo inicial se muestra aparte, con formato de documento,
    para que no se confunda visualmente con los abonos de la lista de abajo. --}}
    @if ($chargeMovement)
        <div class="rounded-lg border border-blue-100 bg-gradient-to-br from-blue-50 to-white px-4 py-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-blue-700">Factura</p>
                    <p class="mt-1 text-2xl font-black text-gray-900">${{ \App\Support\Money::format((float) $chargeMovement->amount) }}</p>
                    <p class="mt-1 text-xs text-gray-500">
                        {{ optional($chargeMovement->occurred_at)->format('d/m/Y') ?: 'Sin fecha' }}
                        @if ($chargeMovement->reference)
                            · Ref: {{ $chargeMovement->reference }}
                        @endif
                    </p>
                    @if ($ledgerSupplierLabel)
                        <p class="mt-1 text-xs text-gray-500">Proveedor: <span class="font-medium text-gray-700">{{ $ledgerSupplierLabel }}</span></p>
                    @endif
                </div>
                <div class="text-left sm:text-right">
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
                    @if ((float) $purchase->balance_due > 0)
                        <p class="mt-2 text-sm text-gray-600">
                            Saldo pendiente: <span class="font-semibold text-gray-900">${{ \App\Support\Money::format((float) $purchase->balance_due) }}</span>
                        </p>
                    @endif
                </div>
            </div>
        </div>
    @endif

    <div class="rounded-lg border border-gray-200 bg-white p-4">
        <div class="flex items-center justify-between gap-3">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-gray-500">Abonos y ajustes</p>
            <p class="text-xs text-gray-500">{{ $otherMovements->count() }} {{ \Illuminate\Support\Str::plural('movimiento', $otherMovements->count()) }}</p>
        </div>

        <div class="mt-4 space-y-3">
            @forelse ($otherMovements as $movement)
                <div class="rounded-lg bg-gray-50 px-4 py-3">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                        <div class="space-y-1">
                            <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $this->movementBadgeClass($movement) }}">
                                {{ $this->movementLabel($movement) }}
                            </span>
                            <p class="text-sm font-medium text-gray-900">
                                ${{ \App\Support\Money::format((float) $movement->amount) }}
                            </p>
                            <p class="text-xs text-gray-500">
                                {{ optional($movement->occurred_at)->format('Y-m-d H:i:s') ?: 'Sin fecha' }}
                                @if ($movement->reference)
                                    · Ref: {{ $movement->reference }}
                                @endif
                            </p>
                            @php $timeliness = $this->paymentTimeliness($movement, $purchase); @endphp
                            @if ($timeliness)
                                <span class="inline-block rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $timeliness['color'] === 'rose' ? 'bg-rose-100 text-rose-700' : 'bg-emerald-100 text-emerald-700' }}">
                                    {{ $timeliness['label'] }}
                                </span>
                            @endif
                        </div>

                        <div class="grid gap-2 text-sm text-gray-600 md:grid-cols-2 lg:min-w-[320px]">
                            <p>Saldo compra: <span class="font-medium text-gray-900">${{ \App\Support\Money::format((float) $movement->balance_after) }}</span></p>
                            {{-- El saldo a favor solo aplica cuando hay devoluciones sobre
                            compras ya pagadas; se oculta mientras sea $0 para no
                            confundir el flujo normal de "debo esto, abono esto". --}}
                            @if ((float) $movement->supplier_credit_after > 0)
                                <p>Saldo a favor: <span class="font-medium text-gray-900">${{ \App\Support\Money::format((float) $movement->supplier_credit_after) }}</span></p>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <p class="text-sm text-gray-500">Aun no hay abonos ni ajustes registrados para esta compra.</p>
            @endforelse
        </div>
    </div>
</div>
