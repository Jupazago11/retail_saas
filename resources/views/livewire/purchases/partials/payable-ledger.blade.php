<div class="rounded-lg border border-gray-200 bg-white p-4">
    <div class="flex items-center justify-between gap-3">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-gray-500">Ledger financiero</p>
            <p class="mt-1 text-sm text-gray-600">Trazabilidad cronologica del saldo y del saldo a favor ligado a esta compra.</p>
        </div>
        <p class="text-xs text-gray-500">{{ $purchase->payableMovements->count() }} movimientos</p>
    </div>

    <div class="mt-4 space-y-3">
        @forelse ($purchase->payableMovements->sortByDesc('occurred_at')->values() as $movement)
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
                    </div>

                    <div class="grid gap-2 text-sm text-gray-600 md:grid-cols-2 lg:min-w-[320px]">
                        <p>Saldo compra: <span class="font-medium text-gray-900">${{ \App\Support\Money::format((float) $movement->balance_after) }}</span></p>
                        <p>Saldo a favor: <span class="font-medium text-gray-900">${{ \App\Support\Money::format((float) $movement->supplier_credit_after) }}</span></p>
                        @if ($movement->supplier)
                            @php
                                $movementSupplier = trim(implode(' ', array_filter([
                                    $movement->supplier?->person?->first_name,
                                    $movement->supplier?->person?->last_name,
                                ])));
                            @endphp
                            <p class="md:col-span-2">
                                Proveedor:
                                <span class="font-medium text-gray-900">{{ $movementSupplier !== '' ? $movementSupplier : 'Proveedor '.$movement->supplier->id }}</span>
                            </p>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <p class="text-sm text-gray-500">Aun no hay movimientos financieros registrados para esta compra.</p>
        @endforelse
    </div>
</div>
