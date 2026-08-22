<div class="pb-10">
    <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
        {{-- Summary cards --}}
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200">
                <p class="text-xs uppercase tracking-[0.18em] text-gray-500">Cuentas activas</p>
                <p class="mt-2 text-3xl font-black text-gray-900">{{ $statusCards['accounts_count'] }}</p>
            </div>
            <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200">
                <p class="text-xs uppercase tracking-[0.18em] text-gray-500">Saldo total</p>
                <p class="mt-2 text-3xl font-black text-rose-700">${{ $statusCards['balance_due_total'] }}</p>
            </div>
            <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200">
                <p class="text-xs uppercase tracking-[0.18em] text-gray-500">Cupo disponible</p>
                <p class="mt-2 text-3xl font-black text-emerald-700">${{ $statusCards['available_credit_total'] }}</p>
            </div>
            <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200">
                <p class="text-xs uppercase tracking-[0.18em] text-gray-500">Cuentas en mora</p>
                <p class="mt-2 text-3xl font-black text-rose-700">{{ $statusCards['overdue_accounts_count'] }}</p>
            </div>
        </div>

        {{-- Tabla de clientes --}}
        <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between mb-5">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-[0.22em] text-blue-700">Cartera</p>
                    <h3 class="mt-1 text-2xl font-black text-gray-900">Clientes</h3>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <input wire:model.live.debounce.300ms="search" type="text"
                        placeholder="Buscar cliente o documento"
                        class="rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600 text-sm w-56">
                    <div class="inline-flex rounded-lg border border-gray-200 bg-gray-50 p-1 text-sm">
                        <button type="button" wire:click="setStatusFilter('all')"
                            class="rounded-md px-3 py-1.5 font-semibold transition {{ $statusFilter === 'all' ? 'bg-gradient-to-br from-blue-600 to-purple-600 text-white shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">
                            Todos
                        </button>
                        <button type="button" wire:click="setStatusFilter('current')"
                            class="rounded-md px-3 py-1.5 font-semibold transition {{ $statusFilter === 'current' ? 'bg-emerald-600 text-white shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">
                            Al dia
                        </button>
                        <button type="button" wire:click="setStatusFilter('overdue')"
                            class="rounded-md px-3 py-1.5 font-semibold transition {{ $statusFilter === 'overdue' ? 'bg-rose-600 text-white shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">
                            En mora
                        </button>
                    </div>
                    @if ($this->canManageCredit())
                        <button wire:click="openAddCustomerModal" type="button"
                            class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                            + Agregar cliente a credito
                        </button>
                    @endif
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-stone-200 text-sm">
                    <thead>
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <th class="pb-2">Cliente</th>
                            <th class="pb-2 text-right">Cupo disp.</th>
                            <th class="pb-2 text-right">Saldo pend.</th>
                            <th class="pb-2 text-right">Mora</th>
                            <th class="pb-2 text-right">Ultimo mov.</th>
                            <th class="pb-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-100">
                        @forelse ($accounts as $account)
                            @php
                                $name = $account->customer?->person?->full_name ?: 'Cliente #'.$account->customer_id;
                                $doc = $account->customer?->person?->document_number ?: '—';
                                $lastMovement = $account->movements->first();
                                $mora = $this->moraStatus($account);
                            @endphp
                            <tr wire:key="account-{{ $account->id }}"
                                wire:click="selectCustomer({{ $account->customer_id }})"
                                class="cursor-pointer even:bg-gray-50 hover:bg-gray-100 transition">
                                <td class="py-2.5 align-middle">
                                    <p class="font-semibold text-gray-900">{{ $name }}</p>
                                    <p class="text-xs text-gray-400">{{ $doc }}</p>
                                </td>
                                <td class="py-2.5 align-middle text-right font-semibold text-emerald-700 text-sm">
                                    ${{ \App\Support\Money::format((float) $account->available_credit) }}
                                </td>
                                <td class="py-2.5 align-middle text-right font-semibold text-sm {{ (float) $account->balance_due > 0 ? 'text-rose-700' : 'text-gray-400' }}">
                                    ${{ \App\Support\Money::format((float) $account->balance_due) }}
                                </td>
                                <td class="py-2.5 align-middle text-right">
                                    @if ($mora)
                                        <x-status-badge :color="$mora['color']">{{ $mora['label'] }}</x-status-badge>
                                    @else
                                        <span class="text-xs text-gray-300">—</span>
                                    @endif
                                </td>
                                <td class="py-2.5 align-middle text-right text-xs text-gray-400">
                                    {{ $lastMovement?->occurred_at?->format('d/m/Y') ?? '—' }}
                                </td>
                                <td class="py-2.5 align-middle text-right">
                                    @if ($this->canManageCredit())
                                        <button wire:click.stop="editCustomer({{ $account->customer_id }})" title="Editar cliente"
                                            class="text-stone-300 hover:text-blue-600 transition">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536M9 13l6.586-6.586a2 2 0 112.828 2.828L11.828 15.828a2 2 0 01-1.415.586H9v-2.414a2 2 0 01.586-1.415z"/>
                                            </svg>
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-10 text-center text-gray-400">
                                    No hay cuentas de credito con el filtro actual.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Modal: detalle del cliente --}}
    @if ($showDetailModal && $selectedCustomerId)
        @php
            $selectedName = $selectedAccount?->customer?->person?->full_name ?: 'Cliente #'.$selectedCustomerId;
            $selectedDoc  = $selectedAccount?->customer?->person?->document_number ?: '—';
            $selectedPhone = $selectedAccount?->customer?->person?->phone ?? '—';
            $selectedMora = $selectedAccount ? $this->moraStatus($selectedAccount) : null;
            $hasPendingBalance = $selectedAccount && bccomp((string) $selectedAccount->balance_due, '0.00', 2) === 1;
        @endphp
        <div wire:click.self="closeDetailModal"
            class="fixed inset-0 z-50 flex items-center justify-center p-4"
            style="background: rgba(0,0,0,0.5);">
            <div class="w-full max-w-2xl rounded-xl bg-white shadow-xl flex flex-col" style="max-height: 90vh;">

                {{-- Header --}}
                <div class="flex-shrink-0 flex items-start justify-between border-b border-stone-100 px-6 py-4">
                    <div class="flex-1">
                        <p class="text-xs font-semibold uppercase tracking-widest text-blue-700">Detalle cliente</p>
                        <div class="mt-0.5 flex items-center gap-2">
                            <h3 class="text-xl font-black text-gray-900">{{ $selectedName }}</h3>
                            @if ($this->canManageCredit())
                                <button wire:click="openEditModal" title="Editar cliente"
                                    class="text-gray-400 hover:text-blue-600 transition flex-shrink-0">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536M9 13l6.586-6.586a2 2 0 112.828 2.828L11.828 15.828a2 2 0 01-1.415.586H9v-2.414a2 2 0 01.586-1.415z"/>
                                    </svg>
                                </button>
                            @endif
                        </div>
                        <div class="mt-1 flex flex-wrap items-center gap-3 text-xs text-gray-400">
                            <span>{{ $selectedDoc }}</span>
                            @if ($selectedPhone !== '—')
                                <span>{{ $selectedPhone }}</span>
                            @endif
                            @if ($selectedAccount)
                                <span class="text-emerald-700 font-semibold">Cupo: ${{ \App\Support\Money::format((float) $selectedAccount->available_credit) }}</span>
                                <span class="{{ (float) $selectedAccount->balance_due > 0 ? 'text-rose-700 font-semibold' : '' }}">Saldo: ${{ \App\Support\Money::format((float) $selectedAccount->balance_due) }}</span>
                                @if ($selectedMora)
                                    <x-status-badge :color="$selectedMora['color']">{{ $selectedMora['label'] }}</x-status-badge>
                                @endif
                            @endif
                        </div>
                    </div>
                    <button wire:click="closeDetailModal" class="text-gray-400 hover:text-gray-700 text-2xl leading-none px-1 ml-4">&times;</button>
                </div>

                {{-- Body scrollable --}}
                <div class="flex-1 overflow-y-auto px-6 py-4 space-y-4">
                    {{-- Abono sobre el saldo general de la cuenta (no sobre una venta puntual) --}}
                    @if ($this->canManageCredit() && $hasPendingBalance)
                        <div class="rounded-xl border border-gray-200 p-4">
                            @if (! $showPaymentForm)
                                <div class="flex items-center justify-between gap-3">
                                    <p class="text-sm text-gray-600">Los abonos se aplican contra el saldo total de la cuenta, sin importar a cuantas facturas corresponda.</p>
                                    <button wire:click="startRegisteringPayment"
                                        class="flex-shrink-0 rounded-full bg-blue-600 px-4 py-1.5 text-xs font-semibold text-white hover:bg-blue-700">
                                        Registrar abono
                                    </button>
                                </div>
                            @else
                                <div class="space-y-3">
                                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-emerald-700">Registrar abono · saldo total de la cuenta</p>
                                    <div class="grid gap-3 sm:grid-cols-2">
                                        <div x-data="digitGroupInput({ path: 'paymentAmount', live: false })">
                                            <label class="text-xs font-medium text-gray-700">Monto <span class="text-rose-600">*</span></label>
                                            <input type="text" inputmode="numeric" @input="onInput($event)"
                                                value="{{ $paymentAmount !== '' ? number_format((int) $paymentAmount, 0, ',', '.') : '' }}"
                                                class="mt-0.5 block w-full rounded-xl border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 text-sm">
                                        </div>
                                        <div>
                                            <label class="text-xs font-medium text-gray-700">Metodo <span class="text-rose-600">*</span></label>
                                            <select wire:model="paymentMethodCode"
                                                class="mt-0.5 block w-full rounded-xl border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 text-sm">
                                                <option value="cash">Efectivo</option>
                                                <option value="card">Tarjeta</option>
                                                <option value="transfer">Transferencia</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="grid gap-3 sm:grid-cols-2">
                                        <div>
                                            <label class="text-xs font-medium text-gray-700">Referencia</label>
                                            <input wire:model="paymentReference" type="text"
                                                class="mt-0.5 block w-full rounded-xl border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 text-sm">
                                        </div>
                                        @if ($openCashSessions->isNotEmpty())
                                            <div>
                                                <label class="text-xs font-medium text-gray-700">Sesion de caja</label>
                                                <select wire:model="cashSessionId"
                                                    class="mt-0.5 block w-full rounded-xl border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 text-sm">
                                                    <option value="">Sin sesion</option>
                                                    @foreach ($openCashSessions as $cs)
                                                        <option value="{{ $cs->id }}">{{ $cs->cashRegister?->name ?? 'Caja' }} · {{ optional($cs->opened_at)->format('d/m H:i') }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        @endif
                                    </div>
                                    <div class="flex gap-2 pt-1">
                                        <button wire:click="registerPayment"
                                            class="rounded-full bg-blue-600 px-4 py-1.5 text-xs font-semibold text-white hover:bg-blue-700">
                                            Confirmar abono
                                        </button>
                                        <button wire:click="cancelRegisteringPayment"
                                            class="rounded-full border border-gray-300 px-4 py-1.5 text-xs font-semibold text-gray-600">
                                            Cancelar
                                        </button>
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endif

                    <div class="space-y-3">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-gray-500">Facturas a credito</p>
                        @forelse ($selectedCustomerSales as $sale)
                            @php
                                $cashReceived = $sale->payments->whereNotIn('payment_method_code', ['credit'])->sum('amount');
                                $creditCharged = $sale->creditMovements->where('movement_type', \App\Enums\CreditMovementType::SaleCharge->value)->sum('amount');
                                $saleBadge = match ($sale->status) {
                                    'cancelled' => ['color' => 'rose', 'label' => 'Anulada'],
                                    'returned' => ['color' => 'stone', 'label' => 'Devuelta'],
                                    'partially_returned' => ['color' => 'amber', 'label' => 'Devolucion parcial'],
                                    default => ['color' => 'emerald', 'label' => 'Confirmada'],
                                };
                            @endphp
                            <div wire:key="dsale-{{ $sale->id }}" class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                                <div class="flex items-start justify-between gap-2 mb-3">
                                    <div>
                                        <p class="font-semibold text-gray-900">{{ $sale->document_number }}</p>
                                        <p class="text-xs text-gray-400">{{ optional($sale->sold_at)->format('d/m/Y H:i') ?? '—' }}</p>
                                    </div>
                                    <x-status-badge :color="$saleBadge['color']" class="flex-shrink-0">{{ $saleBadge['label'] }}</x-status-badge>
                                </div>

                                <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 text-xs">
                                    <div class="rounded-lg bg-white px-2.5 py-2 ring-1 ring-gray-200">
                                        <p class="text-gray-400">Total factura</p>
                                        <p class="font-semibold text-gray-900">${{ \App\Support\Money::format((float) $sale->grand_total) }}</p>
                                    </div>
                                    <div class="rounded-lg bg-white px-2.5 py-2 ring-1 ring-gray-200">
                                        <p class="text-gray-400">Cargado a credito</p>
                                        <p class="font-semibold text-rose-700">${{ \App\Support\Money::format((float) $creditCharged) }}</p>
                                    </div>
                                    @if ($cashReceived > 0)
                                        <div class="rounded-lg bg-white px-2.5 py-2 ring-1 ring-gray-200">
                                            <p class="text-gray-400">Pagado al momento</p>
                                            <p class="font-semibold text-gray-900">${{ \App\Support\Money::format((float) $cashReceived) }}</p>
                                        </div>
                                    @endif
                                </div>

                                <div class="mt-3 flex flex-wrap gap-2">
                                    <a href="{{ route('sales.ticket', $sale) }}" target="_blank" rel="noopener noreferrer"
                                        class="rounded-full border border-gray-300 px-3 py-1 text-xs font-semibold text-gray-600 hover:bg-gray-100">
                                        Ver ticket
                                    </a>
                                </div>
                            </div>
                        @empty
                            <div class="py-10 text-center text-gray-400 text-sm">
                                Este cliente no tiene facturas a credito registradas.
                            </div>
                        @endforelse
                    </div>

                    <div class="space-y-3" x-data="{ open: false }">
                        <div class="flex items-center justify-between">
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-gray-500">Historial de movimientos</p>
                            <button type="button" x-on:click="open = !open"
                                class="flex items-center gap-1.5 rounded-full border border-gray-300 px-3 py-1 text-xs font-semibold text-gray-600 hover:bg-gray-50">
                                <svg x-show="!open" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                                <svg x-show="open" x-cloak xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88"/>
                                </svg>
                                <span x-text="open ? 'Ocultar' : 'Ver historial'"></span>
                            </button>
                        </div>
                        <div x-show="open" x-cloak class="overflow-x-auto rounded-lg border border-gray-200">
                            <table class="min-w-full divide-y divide-stone-200 text-xs">
                                <thead class="bg-gray-50">
                                    <tr class="text-left font-semibold uppercase tracking-wide text-gray-500">
                                        <th class="px-3 py-2">Fecha</th>
                                        <th class="px-3 py-2">Tipo</th>
                                        <th class="px-3 py-2">Factura</th>
                                        <th class="px-3 py-2 text-right">Monto</th>
                                        <th class="px-3 py-2 text-right">Saldo despues</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-stone-100">
                                    @forelse ($this->selectedAccountMovements() as $movement)
                                        @php
                                            $movementMeta = match ($movement->movement_type) {
                                                \App\Enums\CreditMovementType::SaleCharge->value => ['label' => 'Cargo por venta', 'color' => 'rose', 'sign' => '+'],
                                                \App\Enums\CreditMovementType::Payment->value => ['label' => 'Abono', 'color' => 'emerald', 'sign' => '-'],
                                                \App\Enums\CreditMovementType::SaleReturnAdjustment->value => ['label' => 'Ajuste por devolucion', 'color' => 'amber', 'sign' => '-'],
                                                \App\Enums\CreditMovementType::SaleCancellationAdjustment->value => ['label' => 'Ajuste por anulacion', 'color' => 'stone', 'sign' => '-'],
                                                default => ['label' => $movement->movement_type, 'color' => 'stone', 'sign' => ''],
                                            };
                                        @endphp
                                        <tr wire:key="movement-{{ $movement->id }}">
                                            <td class="px-3 py-2 whitespace-nowrap text-gray-500">{{ optional($movement->occurred_at)->format('d/m/Y H:i') }}</td>
                                            <td class="px-3 py-2 whitespace-nowrap">
                                                <x-status-badge :color="$movementMeta['color']">{{ $movementMeta['label'] }}</x-status-badge>
                                            </td>
                                            <td class="px-3 py-2 whitespace-nowrap text-gray-500">
                                                @if ($movement->sale)
                                                    <a href="{{ route('sales.ticket', $movement->sale) }}" target="_blank" rel="noopener noreferrer" class="font-semibold text-blue-700 hover:text-blue-800">
                                                        {{ $movement->sale->document_number }}
                                                    </a>
                                                @elseif ($movement->notes)
                                                    {{ $movement->notes }}
                                                @else
                                                    —
                                                @endif
                                            </td>
                                            <td class="px-3 py-2 whitespace-nowrap text-right font-semibold {{ $movementMeta['sign'] === '+' ? 'text-rose-700' : 'text-emerald-700' }}">
                                                {{ $movementMeta['sign'] }}${{ \App\Support\Money::format((float) $movement->amount) }}
                                            </td>
                                            <td class="px-3 py-2 whitespace-nowrap text-right text-gray-500">
                                                ${{ \App\Support\Money::format((float) $movement->balance_after) }}
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="px-3 py-6 text-center text-gray-400">Este cliente no tiene movimientos registrados.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                {{-- Footer --}}
                <div class="flex-shrink-0 border-t border-stone-100 px-6 py-3">
                    <button wire:click="closeDetailModal"
                        class="rounded-full border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                        Cerrar
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Modal: editar cliente --}}
    @if ($showEditModal)
        <div wire:click.self="closeEditModal"
            class="fixed inset-0 z-[60] flex items-center justify-center p-4"
            style="background: rgba(0,0,0,0.6);">
            <div class="w-full max-w-md rounded-xl bg-white shadow-xl">
                <div class="flex items-center justify-between border-b border-stone-100 px-5 py-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-widest text-blue-700">Editar</p>
                        <h3 class="mt-0.5 text-lg font-black text-gray-900">Datos del cliente</h3>
                    </div>
                    <button wire:click="closeEditModal" class="text-gray-400 hover:text-gray-700 text-2xl leading-none px-1">&times;</button>
                </div>
                <div class="px-5 py-4 space-y-3">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-700">Nombre <span class="text-rose-600">*</span></label>
                            <input wire:model="editFirstName" type="text"
                                class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600 text-sm">
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-700">Apellido</label>
                            <input wire:model="editLastName" type="text"
                                class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600 text-sm">
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-700">Telefono</label>
                            <input wire:model="editPhone" type="text"
                                class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600 text-sm">
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-700">Email</label>
                            <input wire:model="editEmail" type="email"
                                class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600 text-sm">
                        </div>
                    </div>
                    <div x-data="creditLimitField('{{ $editCreditLimit }}')">
                        <label class="mb-1 block text-xs font-medium text-gray-700">Cupo de credito <span class="text-rose-600">*</span></label>
                        <input type="text" x-ref="input" inputmode="numeric"
                            x-on:focus="onFocus" x-on:input="onInput" x-on:blur="onBlur"
                            class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600 text-sm">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-700">Dias para mora</label>
                        <input wire:model="editPaymentTermDays" type="number" min="1" max="365" step="1"
                            placeholder="Usa el plazo general de la empresa ({{ $this->defaultTermDays() }} dias)"
                            class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600 text-sm">
                        <p class="mt-1 text-xs text-gray-400">Dejar en blanco usa el plazo general de la empresa. Solo aplica a compras nuevas, no cambia el vencimiento de facturas ya generadas.</p>
                    </div>
                    <div class="flex gap-2 border-t border-stone-100 pt-3">
                        <button wire:click="closeEditModal"
                            class="rounded-full border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                            Cancelar
                        </button>
                        <button wire:click="saveCustomerEdit"
                            class="rounded-full bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                            Guardar cambios
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Modal: agregar cliente a credito --}}
    @if ($showAddCustomerModal)
        <div wire:click.self="closeAddCustomerModal"
            class="fixed inset-0 z-[60] flex items-center justify-center p-4"
            style="background: rgba(0,0,0,0.6);">
            <div x-data="creditCustomerSearch(@js($this->customersWithoutCreditOptions()))" class="w-full max-w-md rounded-xl bg-white shadow-xl">
                <div class="flex items-center justify-between border-b border-stone-100 px-5 py-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-widest text-blue-700">Cartera</p>
                        <h3 class="mt-0.5 text-lg font-black text-gray-900">Agregar cliente a credito</h3>
                    </div>
                    <button wire:click="closeAddCustomerModal" class="text-gray-400 hover:text-gray-700 text-2xl leading-none px-1">&times;</button>
                </div>
                <div class="px-5 py-4 space-y-3">
                    @if ($addCustomerCreatingNew)
                        <p class="text-xs text-gray-500">Cliente nuevo: no existe todavia en el sistema. Estos datos se pueden completar despues desde "Editar cliente".</p>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-xs font-medium text-gray-700">Nombre <span class="text-rose-600">*</span></label>
                                <input wire:model="newCustomerFirstName" type="text"
                                    class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600 text-sm">
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium text-gray-700">Apellido</label>
                                <input wire:model="newCustomerLastName" type="text"
                                    class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600 text-sm">
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium text-gray-700">Tipo documento</label>
                                <input wire:model="newCustomerDocumentType" type="text" placeholder="CC, NIT..."
                                    class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600 text-sm">
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium text-gray-700">Numero documento</label>
                                <input wire:model="newCustomerDocumentNumber" type="text"
                                    class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600 text-sm">
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium text-gray-700">Telefono</label>
                                <input wire:model="newCustomerPhone" type="text"
                                    class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600 text-sm">
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium text-gray-700">Email</label>
                                <input wire:model="newCustomerEmail" type="email"
                                    class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600 text-sm">
                            </div>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-700">Limite de credito <span class="text-rose-600">*</span></label>
                            <input wire:model="addCustomerCreditLimit" type="number" min="0" step="1"
                                class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600 text-sm">
                        </div>
                        <div class="flex gap-2 border-t border-stone-100 pt-3">
                            <button wire:click="cancelCreatingCustomerForCredit"
                                class="rounded-full border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                                Volver
                            </button>
                            <button wire:click="createCustomerForCredit"
                                class="rounded-full bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                                Crear y habilitar credito
                            </button>
                        </div>
                    @elseif (! $addCustomerSelectedId)
                        <div class="relative">
                            <label class="mb-1 block text-xs font-medium text-gray-700">Buscar cliente</label>
                            <input type="text" x-model="search" x-on:input="onInput"
                                placeholder="Nombre o documento"
                                class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600 text-sm"
                                autocomplete="off">
                            <ul x-show="open" x-cloak
                                class="absolute left-0 right-0 top-full z-50 mt-1 max-h-48 overflow-y-auto rounded-xl border border-gray-200 bg-white shadow-lg">
                                <template x-for="c in results" :key="c.id">
                                    <li @click="select(c)"
                                        class="flex cursor-pointer items-center justify-between gap-2 border-b border-stone-100 px-3 py-2 hover:bg-blue-50">
                                        <span class="text-sm font-semibold text-gray-800" x-text="c.name"></span>
                                        <span class="font-mono text-xs text-gray-400" x-text="c.document"></span>
                                    </li>
                                </template>
                                <li x-show="search.length > 0 && results.length === 0" class="px-3 py-2 text-sm text-gray-400">
                                    Sin coincidencias.
                                </li>
                            </ul>
                        </div>
                        <button type="button" wire:click="startCreatingCustomerForCredit"
                            class="text-sm font-semibold text-blue-700 hover:text-blue-800">
                            + Crear cliente nuevo
                        </button>
                    @else
                        @php
                            $selectedForCredit = collect($this->customersWithoutCreditOptions())->firstWhere('id', $addCustomerSelectedId);
                        @endphp
                        <div class="flex items-center justify-between rounded-xl bg-gray-50 px-3 py-2 ring-1 ring-gray-200">
                            <div>
                                <p class="text-sm font-semibold text-gray-800">{{ $selectedForCredit['name'] ?? 'Cliente #'.$addCustomerSelectedId }}</p>
                                @if (! empty($selectedForCredit['document']))
                                    <p class="font-mono text-xs text-gray-400">{{ $selectedForCredit['document'] }}</p>
                                @endif
                            </div>
                            <button wire:click="$set('addCustomerSelectedId', null)" class="text-gray-400 hover:text-rose-600 text-lg leading-none">&times;</button>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-700">Limite de credito <span class="text-rose-600">*</span></label>
                            <input wire:model="addCustomerCreditLimit" type="number" min="0" step="1"
                                class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600 text-sm">
                        </div>
                        <div class="flex gap-2 border-t border-stone-100 pt-3">
                            <button wire:click="closeAddCustomerModal"
                                class="rounded-full border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                                Cancelar
                            </button>
                            <button wire:click="enableCredit"
                                class="rounded-full bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                                Habilitar credito
                            </button>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>

<script>
    // Mismo patron de paymentAmount() en pos-page.blade.php: input type="text"
    // formateado en vivo con separador de miles, en vez de type="number" —
    // ese ultimo deja que el navegador (segun el idioma/region del SO) le
    // dibuje una coma decimal encima sin separador de miles, inconsistente
    // con el resto de montos de la app (Money::format ya usa "$1.000.000").
    function creditLimitField(initialValue) {
        return {
            // initialValue llega como decimal de BD, ej. "1000000.00" — quitar
            // solo los digitos (regex previa) borraba el punto y pegaba las
            // partes ("1000000" + "00" = 100000000). parseFloat() respeta el
            // punto decimal antes de quedarnos con los pesos enteros.
            raw: initialValue && !isNaN(parseFloat(String(initialValue)))
                ? String(Math.round(parseFloat(String(initialValue))))
                : '',
            fmt: function(n) {
                var num = parseFloat(String(n).replace(/[^\d.]/g, ''));
                if (!n || isNaN(num)) return '';
                return new Intl.NumberFormat('es-CO', { maximumFractionDigits: 0 }).format(num);
            },
            init: function() {
                this.$refs.input.value = this.raw ? this.fmt(this.raw) : '';
            },
            onFocus: function() {
                var self = this;
                this.$nextTick(function() { self.$refs.input.select(); });
            },
            onInput: function() {
                var input = this.$refs.input;
                var digitsBeforeCursor = input.value.slice(0, input.selectionStart).replace(/[^\d]/g, '').length;

                var raw = input.value.replace(/[^\d]/g, '');
                input.value = raw ? this.fmt(raw) : '';

                var pos = 0, digitsSeen = 0;
                while (pos < input.value.length && digitsSeen < digitsBeforeCursor) {
                    if (/\d/.test(input.value[pos])) digitsSeen++;
                    pos++;
                }
                input.setSelectionRange(pos, pos);

                this.raw = raw;
                this.$wire.set('editCreditLimit', raw || '', false);
            },
            onBlur: function() {
                var raw = this.$refs.input.value.replace(/[^\d]/g, '');
                this.raw = raw;
                this.$refs.input.value = raw ? this.fmt(raw) : '';
                this.$wire.set('editCreditLimit', raw || '', false);
            },
        };
    }

    function creditCustomerSearch(catalog) {
        return {
            catalog: catalog,
            search: '',
            open: false,
            get results() {
                var q = this.search.trim().toLowerCase();
                if (q.length < 1) return [];
                return this.catalog.filter(function(c) {
                    return c.name.toLowerCase().includes(q) || c.document.toLowerCase().includes(q);
                }).slice(0, 8);
            },
            onInput: function() {
                this.open = this.search.length > 0;
            },
            select: function(c) {
                this.search = '';
                this.open = false;
                this.$wire.call('selectCustomerForCredit', c.id);
            },
        };
    }
</script>
