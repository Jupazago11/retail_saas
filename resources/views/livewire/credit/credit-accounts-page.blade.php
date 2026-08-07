<div class="pb-10">
    <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
        {{-- Summary cards --}}
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-3xl bg-white p-5 shadow-sm ring-1 ring-stone-200">
                <p class="text-xs uppercase tracking-[0.18em] text-stone-500">Cuentas activas</p>
                <p class="mt-2 text-3xl font-black text-stone-900">{{ $statusCards['accounts_count'] }}</p>
            </div>
            <div class="rounded-3xl bg-white p-5 shadow-sm ring-1 ring-stone-200">
                <p class="text-xs uppercase tracking-[0.18em] text-stone-500">Saldo total</p>
                <p class="mt-2 text-3xl font-black text-rose-700">${{ $statusCards['balance_due_total'] }}</p>
            </div>
            <div class="rounded-3xl bg-white p-5 shadow-sm ring-1 ring-stone-200">
                <p class="text-xs uppercase tracking-[0.18em] text-stone-500">Cupo disponible</p>
                <p class="mt-2 text-3xl font-black text-emerald-700">${{ $statusCards['available_credit_total'] }}</p>
            </div>
            <div class="rounded-3xl bg-white p-5 shadow-sm ring-1 ring-stone-200">
                <p class="text-xs uppercase tracking-[0.18em] text-stone-500">Ventas vencidas</p>
                <p class="mt-2 text-3xl font-black text-amber-700">{{ $statusCards['overdue_sales_count'] }}</p>
            </div>
        </div>

        {{-- Tabla de clientes --}}
        <div class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-stone-200">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between mb-5">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-[0.22em] text-amber-700">Cartera</p>
                    <h3 class="mt-1 text-2xl font-black text-stone-900">Clientes</h3>
                </div>
                <div class="flex gap-2 flex-wrap">
                    <input wire:model.live.debounce.300ms="search" type="text"
                        placeholder="Buscar cliente o documento"
                        class="rounded-2xl border-stone-300 shadow-sm focus:border-amber-500 focus:ring-amber-500 text-sm w-56">
                    <label class="inline-flex items-center gap-2 rounded-2xl border border-stone-200 px-3 py-2 text-sm text-stone-600">
                        <input wire:model.live="overdueOnly" type="checkbox"
                            class="rounded border-stone-300 text-amber-600 shadow-sm focus:ring-amber-500">
                        Solo vencidas
                    </label>
                    @if ($this->canManageCredit())
                        <button wire:click="openAddCustomerModal" type="button"
                            class="rounded-2xl bg-stone-900 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-700">
                            + Agregar cliente a credito
                        </button>
                    @endif
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-stone-200 text-sm">
                    <thead>
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-stone-500">
                            <th class="pb-2">Cliente</th>
                            <th class="pb-2 text-right">Cupo disp.</th>
                            <th class="pb-2 text-right">Saldo pend.</th>
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
                            @endphp
                            <tr wire:key="account-{{ $account->id }}"
                                wire:click="selectCustomer({{ $account->customer_id }})"
                                class="cursor-pointer hover:bg-stone-50 transition">
                                <td class="py-2.5 align-middle">
                                    <p class="font-semibold text-stone-900">{{ $name }}</p>
                                    <p class="text-xs text-stone-400">{{ $doc }}</p>
                                </td>
                                <td class="py-2.5 align-middle text-right font-semibold text-emerald-700 text-sm">
                                    ${{ \App\Support\Money::format((float) $account->available_credit) }}
                                </td>
                                <td class="py-2.5 align-middle text-right font-semibold text-sm {{ (float) $account->balance_due > 0 ? 'text-rose-700' : 'text-stone-400' }}">
                                    ${{ \App\Support\Money::format((float) $account->balance_due) }}
                                </td>
                                <td class="py-2.5 align-middle text-right text-xs text-stone-400">
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
                                <td colspan="4" class="py-10 text-center text-stone-400">
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
            $selectedAccount = $accounts->firstWhere('customer_id', $selectedCustomerId);
            $selectedName = $selectedAccount?->customer?->person?->full_name ?: 'Cliente #'.$selectedCustomerId;
            $selectedDoc  = $selectedAccount?->customer?->person?->document_number ?: '—';
            $selectedPhone = $selectedAccount?->customer?->person?->phone ?? '—';
        @endphp
        <div wire:click.self="closeDetailModal"
            class="fixed inset-0 z-50 flex items-center justify-center p-4"
            style="background: rgba(0,0,0,0.5);">
            <div class="w-full max-w-2xl rounded-3xl bg-white shadow-xl flex flex-col" style="max-height: 90vh;">

                {{-- Header --}}
                <div class="flex-shrink-0 flex items-start justify-between border-b border-stone-100 px-6 py-4">
                    <div class="flex-1">
                        <p class="text-xs font-semibold uppercase tracking-widest text-amber-700">Detalle cliente</p>
                        <div class="mt-0.5 flex items-center gap-2">
                            <h3 class="text-xl font-black text-stone-900">{{ $selectedName }}</h3>
                            @if ($this->canManageCredit())
                                <button wire:click="openEditModal" title="Editar cliente"
                                    class="text-stone-400 hover:text-blue-600 transition flex-shrink-0">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536M9 13l6.586-6.586a2 2 0 112.828 2.828L11.828 15.828a2 2 0 01-1.415.586H9v-2.414a2 2 0 01.586-1.415z"/>
                                    </svg>
                                </button>
                            @endif
                        </div>
                        <div class="mt-1 flex flex-wrap gap-3 text-xs text-stone-400">
                            <span>{{ $selectedDoc }}</span>
                            @if ($selectedPhone !== '—')
                                <span>{{ $selectedPhone }}</span>
                            @endif
                            @if ($selectedAccount)
                                <span class="text-emerald-700 font-semibold">Cupo: ${{ \App\Support\Money::format((float) $selectedAccount->available_credit) }}</span>
                                <span class="{{ (float) $selectedAccount->balance_due > 0 ? 'text-rose-700 font-semibold' : '' }}">Saldo: ${{ \App\Support\Money::format((float) $selectedAccount->balance_due) }}</span>
                            @endif
                        </div>
                    </div>
                    <button wire:click="closeDetailModal" class="text-stone-400 hover:text-stone-700 text-2xl leading-none px-1 ml-4">&times;</button>
                </div>

                {{-- Body scrollable --}}
                <div class="flex-1 overflow-y-auto px-6 py-4 space-y-3">
                    @forelse ($selectedCustomerSales as $sale)
                        @php
                            $outstanding = $this->outstandingForSale($sale);
                            $isOverdue = $sale->credit_due_at && $sale->credit_due_at->isPast() && bccomp($outstanding, '0.00', 2) === 1;
                            $cashReceived = $sale->payments->whereNotIn('payment_method_code', ['credit'])->sum('amount');
                            $creditCharged = $sale->creditMovements->where('movement_type', \App\Enums\CreditMovementType::SaleCharge->value)->sum('amount');
                            $creditPaid = $sale->creditMovements->where('movement_type', \App\Enums\CreditMovementType::Payment->value)->sum('amount');
                            $cashSessions = $this->openCashSessionsForSale($sale);
                        @endphp
                        <div wire:key="dsale-{{ $sale->id }}" class="rounded-2xl border border-stone-200 bg-stone-50 p-4">
                            <div class="flex items-start justify-between gap-2 mb-3">
                                <div>
                                    <p class="font-semibold text-stone-900">{{ $sale->document_number }}</p>
                                    <p class="text-xs text-stone-400">{{ optional($sale->sold_at)->format('d/m/Y H:i') ?? '—' }}</p>
                                </div>
                                <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold flex-shrink-0 {{ $isOverdue ? 'bg-rose-100 text-rose-700' : (bccomp($outstanding, '0.00', 2) <= 0 ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700') }}">
                                    {{ $isOverdue ? 'Vencida' : (bccomp($outstanding, '0.00', 2) <= 0 ? 'Pagada' : 'En cartera') }}
                                </span>
                            </div>

                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-xs mb-3">
                                <div class="rounded-lg bg-white px-2.5 py-2 ring-1 ring-stone-200">
                                    <p class="text-stone-400">Total factura</p>
                                    <p class="font-semibold text-stone-900">${{ \App\Support\Money::format((float) $sale->grand_total) }}</p>
                                </div>
                                <div class="rounded-lg bg-white px-2.5 py-2 ring-1 ring-stone-200">
                                    <p class="text-stone-400">Pendiente</p>
                                    <p class="font-semibold {{ bccomp($outstanding, '0.00', 2) > 0 ? 'text-rose-700' : 'text-emerald-700' }}">${{ \App\Support\Money::format((float) $outstanding) }}</p>
                                </div>
                                @if ($cashReceived > 0)
                                    <div class="rounded-lg bg-white px-2.5 py-2 ring-1 ring-stone-200">
                                        <p class="text-stone-400">Efectivo / pago</p>
                                        <p class="font-semibold text-stone-900">${{ \App\Support\Money::format((float) $cashReceived) }}</p>
                                    </div>
                                @endif
                                @if ($creditPaid > 0)
                                    <div class="rounded-lg bg-white px-2.5 py-2 ring-1 ring-stone-200">
                                        <p class="text-stone-400">Abonado</p>
                                        <p class="font-semibold text-emerald-700">${{ \App\Support\Money::format((float) $creditPaid) }}</p>
                                    </div>
                                @endif
                            </div>

                            <div class="flex flex-wrap gap-2">
                                <a href="{{ route('sales.ticket', $sale) }}" target="_blank" rel="noopener noreferrer"
                                    class="rounded-full border border-stone-300 px-3 py-1 text-xs font-semibold text-stone-600 hover:bg-stone-100">
                                    Ver ticket
                                </a>
                                @if ($this->canManageCredit() && bccomp($outstanding, '0.00', 2) === 1 && $sale->status !== 'cancelled')
                                    <button wire:click="startRegisteringPayment({{ $sale->id }})"
                                        class="rounded-full bg-stone-900 px-3 py-1 text-xs font-semibold text-white hover:bg-amber-700">
                                        Registrar abono
                                    </button>
                                @endif
                            </div>

                            @if ($paymentSaleId === $sale->id)
                                <div class="mt-3 rounded-xl border border-emerald-200 bg-emerald-50 p-3 space-y-2">
                                    <div class="grid grid-cols-2 gap-2">
                                        <div>
                                            <label class="text-xs font-medium text-stone-700">Metodo</label>
                                            <select wire:model="paymentMethodCode"
                                                class="mt-0.5 block w-full rounded-xl border-stone-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 text-sm">
                                                <option value="cash">Efectivo</option>
                                                <option value="card">Tarjeta</option>
                                                <option value="transfer">Transferencia</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="text-xs font-medium text-stone-700">Monto</label>
                                            <input wire:model="paymentAmount" type="number" min="0.01" step="0.01"
                                                class="mt-0.5 block w-full rounded-xl border-stone-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 text-sm">
                                            @error('paymentAmount') <p class="mt-0.5 text-xs text-rose-600">{{ $message }}</p> @enderror
                                        </div>
                                    </div>
                                    @if ($cashSessions->isNotEmpty())
                                        <div>
                                            <label class="text-xs font-medium text-stone-700">Sesion de caja</label>
                                            <select wire:model="cashSessionId"
                                                class="mt-0.5 block w-full rounded-xl border-stone-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 text-sm">
                                                <option value="">Sin sesion</option>
                                                @foreach ($cashSessions as $cs)
                                                    <option value="{{ $cs->id }}">{{ $cs->cashRegister?->name ?? 'Caja' }} · {{ optional($cs->opened_at)->format('d/m H:i') }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    @endif
                                    <div class="flex gap-2 pt-1">
                                        <button wire:click="registerPayment"
                                            class="rounded-full bg-stone-900 px-3 py-1.5 text-xs font-semibold text-white hover:bg-amber-700">
                                            Confirmar abono
                                        </button>
                                        <button wire:click="cancelRegisteringPayment"
                                            class="rounded-full border border-stone-300 px-3 py-1.5 text-xs font-semibold text-stone-600">
                                            Cancelar
                                        </button>
                                    </div>
                                </div>
                            @endif
                        </div>
                    @empty
                        <div class="py-10 text-center text-stone-400 text-sm">
                            Este cliente no tiene facturas a credito registradas.
                        </div>
                    @endforelse
                </div>

                {{-- Footer --}}
                <div class="flex-shrink-0 border-t border-stone-100 px-6 py-3">
                    <button wire:click="closeDetailModal"
                        class="rounded-full border border-stone-300 px-4 py-2 text-sm font-semibold text-stone-700 hover:bg-stone-50">
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
            <div class="w-full max-w-md rounded-3xl bg-white shadow-xl">
                <div class="flex items-center justify-between border-b border-stone-100 px-5 py-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-widest text-amber-700">Editar</p>
                        <h3 class="mt-0.5 text-lg font-black text-stone-900">Datos del cliente</h3>
                    </div>
                    <button wire:click="closeEditModal" class="text-stone-400 hover:text-stone-700 text-2xl leading-none px-1">&times;</button>
                </div>
                <div class="px-5 py-4 space-y-3">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-stone-700">Nombre</label>
                            <input wire:model="editFirstName" type="text"
                                class="block w-full rounded-xl border-stone-300 shadow-sm focus:border-amber-500 focus:ring-amber-500 text-sm">
                            @error('editFirstName') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-stone-700">Apellido</label>
                            <input wire:model="editLastName" type="text"
                                class="block w-full rounded-xl border-stone-300 shadow-sm focus:border-amber-500 focus:ring-amber-500 text-sm">
                            @error('editLastName') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-stone-700">Telefono</label>
                            <input wire:model="editPhone" type="text"
                                class="block w-full rounded-xl border-stone-300 shadow-sm focus:border-amber-500 focus:ring-amber-500 text-sm">
                            @error('editPhone') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-stone-700">Email</label>
                            <input wire:model="editEmail" type="email"
                                class="block w-full rounded-xl border-stone-300 shadow-sm focus:border-amber-500 focus:ring-amber-500 text-sm">
                            @error('editEmail') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-stone-700">Cupo de credito</label>
                        <input wire:model="editCreditLimit" type="number" min="0" step="1"
                            class="block w-full rounded-xl border-stone-300 shadow-sm focus:border-amber-500 focus:ring-amber-500 text-sm">
                        @error('editCreditLimit') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                    <div class="flex gap-2 border-t border-stone-100 pt-3">
                        <button wire:click="closeEditModal"
                            class="rounded-full border border-stone-300 px-4 py-2 text-sm font-semibold text-stone-700 hover:bg-stone-50">
                            Cancelar
                        </button>
                        <button wire:click="saveCustomerEdit"
                            class="rounded-full bg-stone-900 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-700">
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
            <div x-data="creditCustomerSearch(@js($this->customersWithoutCreditOptions()))" class="w-full max-w-md rounded-3xl bg-white shadow-xl">
                <div class="flex items-center justify-between border-b border-stone-100 px-5 py-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-widest text-amber-700">Cartera</p>
                        <h3 class="mt-0.5 text-lg font-black text-stone-900">Agregar cliente a credito</h3>
                    </div>
                    <button wire:click="closeAddCustomerModal" class="text-stone-400 hover:text-stone-700 text-2xl leading-none px-1">&times;</button>
                </div>
                <div class="px-5 py-4 space-y-3">
                    @if (! $addCustomerSelectedId)
                        <div class="relative">
                            <label class="mb-1 block text-xs font-medium text-stone-700">Buscar cliente</label>
                            <input type="text" x-model="search" x-on:input="onInput"
                                placeholder="Nombre o documento"
                                class="block w-full rounded-xl border-stone-300 shadow-sm focus:border-amber-500 focus:ring-amber-500 text-sm"
                                autocomplete="off">
                            <ul x-show="open" x-cloak
                                class="absolute left-0 right-0 top-full z-50 mt-1 max-h-48 overflow-y-auto rounded-xl border border-stone-200 bg-white shadow-lg">
                                <template x-for="c in results" :key="c.id">
                                    <li @click="select(c)"
                                        class="flex cursor-pointer items-center justify-between gap-2 border-b border-stone-100 px-3 py-2 hover:bg-amber-50">
                                        <span class="text-sm font-semibold text-stone-800" x-text="c.name"></span>
                                        <span class="font-mono text-xs text-stone-400" x-text="c.document"></span>
                                    </li>
                                </template>
                                <li x-show="search.length > 0 && results.length === 0" class="px-3 py-2 text-sm text-stone-400">
                                    Sin coincidencias.
                                </li>
                            </ul>
                        </div>
                        @error('addCustomerSelectedId') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror
                    @else
                        @php
                            $selectedForCredit = collect($this->customersWithoutCreditOptions())->firstWhere('id', $addCustomerSelectedId);
                        @endphp
                        <div class="flex items-center justify-between rounded-xl bg-stone-50 px-3 py-2 ring-1 ring-stone-200">
                            <div>
                                <p class="text-sm font-semibold text-stone-800">{{ $selectedForCredit['name'] ?? 'Cliente #'.$addCustomerSelectedId }}</p>
                                @if (! empty($selectedForCredit['document']))
                                    <p class="font-mono text-xs text-stone-400">{{ $selectedForCredit['document'] }}</p>
                                @endif
                            </div>
                            <button wire:click="$set('addCustomerSelectedId', null)" class="text-stone-400 hover:text-rose-600 text-lg leading-none">&times;</button>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-stone-700">Limite de credito</label>
                            <input wire:model="addCustomerCreditLimit" type="number" min="0" step="1"
                                class="block w-full rounded-xl border-stone-300 shadow-sm focus:border-amber-500 focus:ring-amber-500 text-sm">
                            @error('addCustomerCreditLimit') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                        </div>
                        <div class="flex gap-2 border-t border-stone-100 pt-3">
                            <button wire:click="closeAddCustomerModal"
                                class="rounded-full border border-stone-300 px-4 py-2 text-sm font-semibold text-stone-700 hover:bg-stone-50">
                                Cancelar
                            </button>
                            <button wire:click="enableCredit"
                                class="rounded-full bg-stone-900 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-700">
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
