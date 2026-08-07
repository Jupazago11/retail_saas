<div class="py-10">
    <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
        <div class="grid gap-4 md:grid-cols-4">
            <div class="rounded-3xl bg-white p-5 shadow-sm ring-1 ring-stone-200">
                <p class="text-xs uppercase tracking-[0.18em] text-stone-500">Cuentas visibles</p>
                <p class="mt-2 text-3xl font-black text-stone-900">{{ $statusCards['accounts_count'] }}</p>
            </div>
            <div class="rounded-3xl bg-white p-5 shadow-sm ring-1 ring-stone-200">
                <p class="text-xs uppercase tracking-[0.18em] text-stone-500">Cuentas activas</p>
                <p class="mt-2 text-3xl font-black text-emerald-700">{{ $statusCards['active_count'] }}</p>
            </div>
            <div class="rounded-3xl bg-white p-5 shadow-sm ring-1 ring-stone-200">
                <p class="text-xs uppercase tracking-[0.18em] text-stone-500">Saldo total</p>
                <p class="mt-2 text-3xl font-black text-sky-700">{{ $statusCards['points_balance_total'] }}</p>
            </div>
            <div class="rounded-3xl bg-white p-5 shadow-sm ring-1 ring-stone-200">
                <p class="text-xs uppercase tracking-[0.18em] text-stone-500">Movimientos</p>
                <p class="mt-2 text-3xl font-black text-amber-700">{{ $statusCards['movements_count'] }}</p>
            </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-[0.95fr_1.15fr]">
            <div class="space-y-6">
                <div class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-stone-200">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                        <div>
                            <p class="text-sm font-semibold uppercase tracking-[0.22em] text-amber-700">Programa</p>
                            <h3 class="mt-2 text-2xl font-black text-stone-900">Configuracion activa</h3>
                        </div>

                        <div class="grid gap-3 md:grid-cols-[170px_auto]">
                            <input wire:model="expirationAsOf" type="date" class="rounded-2xl border-stone-300 shadow-sm focus:border-amber-500 focus:ring-amber-500">
                            <button wire:click="expirePoints" class="rounded-full bg-stone-900 px-4 py-2 text-sm font-semibold text-white">
                                Ejecutar expiracion
                            </button>
                        </div>
                    </div>

                    @error('expirationAsOf') <p class="mt-3 text-sm text-rose-600">{{ $message }}</p> @enderror

                    <div class="mt-5 grid gap-4 md:grid-cols-2">
                        <div class="rounded-3xl bg-stone-50 p-4 ring-1 ring-stone-200">
                            <p class="text-xs uppercase tracking-[0.18em] text-stone-500">Estado</p>
                            <p class="mt-2 text-lg font-black {{ $settingsSnapshot['enabled'] ? 'text-emerald-700' : 'text-rose-700' }}">
                                {{ $settingsSnapshot['enabled'] ? 'Habilitado' : 'Deshabilitado' }}
                            </p>
                        </div>
                        <div class="rounded-3xl bg-stone-50 p-4 ring-1 ring-stone-200">
                            <p class="text-xs uppercase tracking-[0.18em] text-stone-500">Regla</p>
                            <p class="mt-2 text-lg font-black text-stone-900">{{ $this->ruleTypeLabel($settingsSnapshot['rule_type']) }}</p>
                        </div>
                        <div class="rounded-3xl bg-stone-50 p-4 ring-1 ring-stone-200">
                            <p class="text-xs uppercase tracking-[0.18em] text-stone-500">Tasa</p>
                            <p class="mt-2 text-lg font-black text-stone-900">{{ $settingsSnapshot['points_rate'] }}</p>
                        </div>
                        <div class="rounded-3xl bg-stone-50 p-4 ring-1 ring-stone-200">
                            <p class="text-xs uppercase tracking-[0.18em] text-stone-500">Expiracion</p>
                            <p class="mt-2 text-lg font-black text-stone-900">
                                {{ $settingsSnapshot['expiration_days'] > 0 ? $settingsSnapshot['expiration_days'].' dias' : 'Deshabilitada' }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-stone-200">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                        <div>
                            <p class="text-sm font-semibold uppercase tracking-[0.22em] text-amber-700">Clientes</p>
                            <h3 class="mt-2 text-2xl font-black text-stone-900">Cuentas de puntos</h3>
                        </div>

                        <div class="grid gap-3 md:grid-cols-[minmax(0,1fr)_180px]">
                            <input wire:model.live.debounce.300ms="search" type="text" placeholder="Buscar por cliente o documento" class="rounded-2xl border-stone-300 shadow-sm focus:border-amber-500 focus:ring-amber-500">
                            <select wire:model.live="statusFilter" class="rounded-2xl border-stone-300 shadow-sm focus:border-amber-500 focus:ring-amber-500">
                                <option value="">Todos los estados</option>
                                <option value="active">Activas</option>
                                <option value="blocked">Bloqueadas</option>
                                <option value="closed">Cerradas</option>
                            </select>
                        </div>
                    </div>

                    <div class="mt-6 space-y-4">
                        @forelse ($accounts as $account)
                            @php
                                $customerName = trim(implode(' ', array_filter([
                                    $account->customer?->person?->first_name,
                                    $account->customer?->person?->last_name,
                                ])));
                            @endphp
                            <div class="rounded-3xl border border-stone-200 bg-stone-50 p-5">
                                <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                                    <div class="space-y-2">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <h4 class="text-lg font-black text-stone-900">{{ $customerName !== '' ? $customerName : 'Cliente '.$account->customer_id }}</h4>
                                            <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $account->status === 'active' ? 'bg-emerald-100 text-emerald-700' : ($account->status === 'blocked' ? 'bg-amber-100 text-amber-700' : 'bg-stone-200 text-stone-700') }}">
                                                {{ $this->statusLabel($account->status) }}
                                            </span>
                                        </div>

                                        <div class="grid gap-2 text-sm text-stone-600 md:grid-cols-2">
                                            <p>Documento: <span class="font-medium text-stone-900">{{ $account->customer?->person?->document_number ?: 'Sin documento' }}</span></p>
                                            <p>Movimientos: <span class="font-medium text-stone-900">{{ $account->movements->count() }}</span></p>
                                        </div>
                                    </div>

                                    <div class="space-y-3">
                                        <div class="rounded-2xl bg-white p-4 ring-1 ring-stone-200">
                                            <p class="text-xs uppercase tracking-[0.18em] text-stone-500">Saldo actual</p>
                                            <p class="mt-1 text-xl font-black text-stone-900">{{ number_format((float) $account->points_balance, 4, '.', ',') }}</p>
                                        </div>

                                        <button wire:click="startAdjustingAccount({{ $account->id }})" class="rounded-full bg-stone-900 px-4 py-2 text-sm font-semibold text-white">
                                            Ajustar puntos
                                        </button>
                                    </div>
                                </div>

                                @if ($adjustmentAccountId === $account->id)
                                    <div class="mt-4 rounded-2xl border border-sky-200 bg-sky-50 p-4">
                                        <div class="space-y-4">
                                            <div class="grid gap-4 md:grid-cols-3">
                                                <div>
                                                    <label class="text-sm font-medium text-stone-700">Operacion</label>
                                                    <select wire:model="adjustmentType" class="mt-1 block w-full rounded-2xl border-stone-300 shadow-sm focus:border-sky-500 focus:ring-sky-500">
                                                        <option value="credit">Sumar puntos</option>
                                                        <option value="debit">Restar puntos</option>
                                                    </select>
                                                    @error('adjustmentType') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                                                </div>

                                                <div>
                                                    <label class="text-sm font-medium text-stone-700">Motivo</label>
                                                    <select wire:model="adjustmentReasonCode" class="mt-1 block w-full rounded-2xl border-stone-300 shadow-sm focus:border-sky-500 focus:ring-sky-500">
                                                        <option value="admin_correction">Correccion administrativa</option>
                                                        <option value="service_recovery">Recuperacion de servicio</option>
                                                        <option value="promotion_compensation">Compensacion promocional</option>
                                                        <option value="migration_adjustment">Ajuste por migracion</option>
                                                        <option value="fraud_correction">Correccion por fraude</option>
                                                    </select>
                                                    @error('adjustmentReasonCode') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                                                </div>

                                                <div>
                                                    <label class="text-sm font-medium text-stone-700">Puntos</label>
                                                    <input wire:model="adjustmentPoints" type="number" min="0.0001" step="0.0001" class="mt-1 block w-full rounded-2xl border-stone-300 shadow-sm focus:border-sky-500 focus:ring-sky-500">
                                                    @error('adjustmentPoints') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                                                </div>
                                            </div>

                                            <div>
                                                <label class="text-sm font-medium text-stone-700">Notas</label>
                                                <input wire:model="adjustmentNotes" type="text" class="mt-1 block w-full rounded-2xl border-stone-300 shadow-sm focus:border-sky-500 focus:ring-sky-500">
                                                @if ($adjustmentType === 'debit')
                                                    <p class="mt-1 text-xs text-stone-500">Para restar puntos debes explicar el motivo en la nota.</p>
                                                @endif
                                                @error('adjustmentNotes') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                                            </div>

                                            <div class="flex gap-2">
                                                <button wire:click="applyAdjustment" class="rounded-full bg-stone-900 px-4 py-2 text-sm font-semibold text-white">
                                                    Aplicar ajuste
                                                </button>
                                                <button wire:click="cancelAdjustment" class="rounded-full border border-stone-300 px-4 py-2 text-sm font-semibold text-stone-700">
                                                    Cancelar
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                @endif

                                @if ($account->movements->isNotEmpty())
                                    <div class="mt-4 rounded-2xl bg-white p-4 ring-1 ring-stone-200">
                                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-stone-500">Ultimos movimientos</p>
                                        <div class="mt-3 space-y-2">
                                            @foreach ($account->movements->take(3) as $movement)
                                                @php
                                                    $direction = $this->movementDirection($movement->movement_type);
                                                @endphp
                                                <div class="flex flex-col gap-1 text-sm text-stone-600">
                                                    <p>
                                                        <span class="font-medium text-stone-900">{{ $this->movementLabel($movement->movement_type) }}</span>
                                                        · <span class="{{ $direction >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">{{ $direction >= 0 ? '+' : '-' }}{{ number_format((float) $movement->points, 4, '.', ',') }}</span>
                                                        · saldo {{ number_format((float) $movement->balance_after, 4, '.', ',') }}
                                                    </p>
                                                    <p>
                                                        Venta #{{ $movement->sale_id ?: 'N/A' }}
                                                        · {{ optional($movement->occurred_at)->format('Y-m-d H:i') ?: 'Sin fecha' }}
                                                    </p>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @empty
                            <div class="rounded-3xl border border-dashed border-stone-300 bg-stone-50 p-8 text-center text-stone-500">
                                Aun no hay cuentas de fidelizacion con el filtro actual.
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-stone-200">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-[0.22em] text-amber-700">Ledger</p>
                        <h3 class="mt-2 text-2xl font-black text-stone-900">Movimientos recientes</h3>
                    </div>

                    <div class="w-full max-w-[220px]">
                        <select wire:model.live="movementTypeFilter" class="w-full rounded-2xl border-stone-300 shadow-sm focus:border-amber-500 focus:ring-amber-500">
                            <option value="">Todos los movimientos</option>
                            <option value="earn">Acumulacion</option>
                            <option value="redeem">Redencion</option>
                            <option value="manual_credit">Ajuste manual a favor</option>
                            <option value="manual_debit">Ajuste manual en contra</option>
                            <option value="expiration">Expiracion</option>
                            <option value="sale_return_reversal">Reverso devolucion</option>
                            <option value="sale_cancellation_reversal">Reverso anulacion</option>
                            <option value="sale_return_redemption_restore">Restitucion devolucion</option>
                            <option value="sale_cancellation_redemption_restore">Restitucion anulacion</option>
                        </select>
                    </div>
                </div>

                <div class="mt-6 space-y-4">
                    @forelse ($recentMovements as $movement)
                        @php
                            $customerName = trim(implode(' ', array_filter([
                                $movement->loyaltyAccount?->customer?->person?->first_name,
                                $movement->loyaltyAccount?->customer?->person?->last_name,
                            ])));
                            $direction = $this->movementDirection($movement->movement_type);
                        @endphp
                        <div class="rounded-3xl border border-stone-200 bg-stone-50 p-5">
                            <div class="flex flex-col gap-3 xl:flex-row xl:items-start xl:justify-between">
                                <div class="space-y-2">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h4 class="text-lg font-black text-stone-900">{{ $this->movementLabel($movement->movement_type) }}</h4>
                                        <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $direction >= 0 ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700' }}">
                                            {{ $direction >= 0 ? 'Entrada' : 'Salida' }}
                                        </span>
                                    </div>

                                    <div class="grid gap-2 text-sm text-stone-600 md:grid-cols-2">
                                        <p>Cliente: <span class="font-medium text-stone-900">{{ $customerName !== '' ? $customerName : 'Cliente '.$movement->loyaltyAccount?->customer_id }}</span></p>
                                        <p>Venta: <span class="font-medium text-stone-900">{{ $movement->sale_id ? '#'.$movement->sale_id : 'N/A' }}</span></p>
                                        <p>Puntos: <span class="font-medium {{ $direction >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">{{ $direction >= 0 ? '+' : '-' }}{{ number_format((float) $movement->points, 4, '.', ',') }}</span></p>
                                        <p>Saldo despues: <span class="font-medium text-stone-900">{{ number_format((float) $movement->balance_after, 4, '.', ',') }}</span></p>
                                        <p>Efectivo equivalente: <span class="font-medium text-stone-900">${{ \App\Support\Money::format((float) $movement->cash_equivalent) }}</span></p>
                                        <p>Fecha: <span class="font-medium text-stone-900">{{ optional($movement->occurred_at)->format('Y-m-d H:i') ?: 'Sin fecha' }}</span></p>
                                        @if ($movement->notes)
                                            <p>Notas: <span class="font-medium text-stone-900">{{ $movement->notes }}</span></p>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="rounded-3xl border border-dashed border-stone-300 bg-stone-50 p-8 text-center text-stone-500">
                            Aun no hay movimientos de fidelizacion con el filtro actual.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
