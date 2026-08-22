@php
    $sessionIsOpen = $session->status === 'open';
    $canEditThisCuadre = $sessionIsOpen ? $this->canOpenCash() : ($this->canOpenCash() && $this->canEditHistoricalCuadres());
@endphp

<div class="grid gap-4 lg:grid-cols-3">

    {{-- Columna 1: Cuadre --}}
    <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 space-y-3">
        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-blue-700">Cuadre</p>

        <div class="space-y-1.5 text-sm">
            <p class="text-gray-500">
                @if ($canEditThisCuadre)
                    Bases (puedes agregar mas o corregir montos)
                @else
                    Bases
                @endif
            </p>

            @foreach ($session->funds as $fund)
                <div class="flex items-center gap-2" wire:key="cash-fund-{{ $fund->id }}">
                    @if ($canEditThisCuadre)
                        <input type="text" value="{{ $fund->label }}"
                            wire:change="saveFundLabel({{ $fund->id }}, $event.target.value)"
                            class="w-1/2 rounded-md border-gray-300 py-1 text-sm shadow-sm focus:border-blue-600 focus:ring-blue-600">
                        <input type="text" inputmode="numeric" value="{{ number_format((float) $fund->amount, 0, ',', '.') }}"
                            @input="$event.target.value = $event.target.value.replace(/\D+/g,'').replace(/\B(?=(\d{3})+(?!\d))/g,'.')"
                            wire:change="saveFundAmount({{ $fund->id }}, $event.target.value.replaceAll('.', ''))"
                            class="w-28 rounded-md border-gray-300 py-1 text-sm text-right shadow-sm focus:border-blue-600 focus:ring-blue-600">
                        @if ($session->funds->count() > 1)
                            <button type="button" wire:click="deleteFund({{ $fund->id }})" wire:confirm="¿Eliminar esta base?" class="text-gray-400 hover:text-rose-600">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                            </button>
                        @endif
                    @else
                        <div class="flex w-full items-center justify-between rounded-lg bg-white px-3 py-1.5 ring-1 ring-gray-200">
                            <span class="text-gray-700">{{ $fund->label }}</span>
                            <span class="font-medium text-gray-900">${{ \App\Support\Money::format((float) $fund->amount) }}</span>
                        </div>
                    @endif
                </div>
            @endforeach

            @if ($canEditThisCuadre)
                {{-- Etiqueta en su propia fila (ancho completo) y Valor+boton debajo:
                     con las tres cosas en una sola fila, en una columna de ~230px el
                     input de texto quedaba exprimido a ~68px — apenas espacio para
                     escribir, y facil confundirlo con el de Valor. --}}
                <form wire:submit="addFund" class="space-y-1.5 pt-1">
                    <div>
                        <input wire:model="newFundLabel" type="text" placeholder="Nueva base (ej: refuerzo de caja)"
                            class="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-600 focus:ring-blue-600">
                    </div>
                    <div class="flex items-start gap-2">
                        <div class="flex-1" x-data="digitGroupInput({ path: 'newFundAmount', live: false })">
                            <input type="text" inputmode="numeric" placeholder="Valor" @input="onInput($event)"
                                value="{{ $newFundAmount !== '' ? number_format((int) $newFundAmount, 0, ',', '.') : '' }}"
                                class="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-600 focus:ring-blue-600">
                        </div>
                        <button type="submit" class="inline-flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-lg bg-blue-600 text-white hover:bg-blue-700">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                            </svg>
                        </button>
                    </div>
                </form>
            @endif

            <div class="flex items-center justify-between rounded-lg bg-white px-3 py-1.5 ring-1 ring-gray-200">
                <span class="font-medium text-gray-700">Total bases</span>
                <span class="font-black text-gray-900">${{ \App\Support\Money::format((float) $session->opening_amount) }}</span>
            </div>
        </div>

        <div class="rounded-lg bg-white px-3 py-2 ring-1 ring-gray-200">
            <p class="text-xs text-gray-500">Venta diaria (todos los medios de pago)</p>
            <p class="text-lg font-black text-gray-900">${{ \App\Support\Money::format((float) $this->dailySalesAmount($session)) }}</p>
        </div>

        <div class="rounded-lg bg-gradient-to-br from-blue-600 to-purple-600 px-3 py-3 text-white">
            <p class="text-xs uppercase tracking-[0.18em] text-blue-100">Resultado (efectivo esperado)</p>
            <p class="text-2xl font-black">${{ \App\Support\Money::format((float) $this->expectedCashAmount($session)) }}</p>
            <p class="mt-1 text-[11px] text-blue-100">Bases + ventas en efectivo − pagos de caja</p>
        </div>
    </div>

    {{-- Columna 2: Pagos de caja --}}
    <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 space-y-3">
        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-blue-700">Pagos de caja</p>
        <p class="text-xs text-gray-500">Dinero que sale de caja: gas, servicios, una factura, etc.</p>

        @if ($canEditThisCuadre)
            <form wire:submit="addExpense" class="space-y-1.5">
                <div>
                    <input wire:model="newExpenseDescription" type="text" placeholder="Descripcion"
                        class="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-600 focus:ring-blue-600">
                </div>
                <div class="flex items-start gap-2">
                    <div class="flex-1" x-data="digitGroupInput({ path: 'newExpenseAmount', live: false })">
                        <input type="text" inputmode="numeric" placeholder="Valor" @input="onInput($event)"
                            value="{{ $newExpenseAmount !== '' ? number_format((int) $newExpenseAmount, 0, ',', '.') : '' }}"
                            class="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-600 focus:ring-blue-600">
                    </div>
                    <button type="submit" class="inline-flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-lg bg-blue-600 text-white hover:bg-blue-700">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                        </svg>
                    </button>
                </div>
            </form>
        @endif

        <div class="space-y-1.5">
            @forelse ($session->expenses as $expense)
                <div wire:key="cash-expense-{{ $expense->id }}" class="flex items-center justify-between rounded-lg bg-white px-3 py-1.5 ring-1 ring-gray-200">
                    <span class="truncate text-sm text-gray-700">{{ $expense->description }}</span>
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-medium text-gray-900">${{ \App\Support\Money::format((float) $expense->amount) }}</span>
                        @if ($canEditThisCuadre)
                            <button type="button" wire:click="deleteExpense({{ $expense->id }})" wire:confirm="¿Eliminar este pago de caja?" class="text-gray-400 hover:text-rose-600">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                            </button>
                        @endif
                    </div>
                </div>
            @empty
                <p class="rounded-lg border border-dashed border-gray-300 bg-white px-3 py-3 text-center text-xs text-gray-400">
                    Aun no hay pagos de caja registrados.
                </p>
            @endforelse
        </div>

        <div class="flex items-center justify-between rounded-lg bg-white px-3 py-2 ring-1 ring-gray-200">
            <span class="text-sm font-medium text-gray-700">Total pagos de caja</span>
            <span class="text-sm font-black text-gray-900">${{ \App\Support\Money::format((float) $session->expenses->sum('amount')) }}</span>
        </div>
    </div>

    {{-- Columna 3: Efectivo en caja --}}
    <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 space-y-3">
        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-blue-700">Efectivo en caja</p>

        @if ($sessionIsOpen)
            @foreach ($this->denominations() as $groupLabel => $group)
                <div class="space-y-1">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">{{ $groupLabel }}</p>
                    @foreach ($group as $key => $value)
                        <div class="grid grid-cols-3 items-center gap-2 rounded-lg bg-white px-2 py-1 ring-1 ring-gray-200">
                            <span class="text-sm text-gray-700">${{ \App\Support\Money::format((float) $value) }}</span>
                            <div x-data="digitGroupInput({ path: 'denominationCounts.{{ $key }}', live: true })">
                                <input type="text" inputmode="numeric" placeholder="0" @input="onInput($event)"
                                    value="{{ isset($denominationCounts[$key]) && $denominationCounts[$key] !== '' ? number_format((int) $denominationCounts[$key], 0, ',', '.') : '' }}"
                                    class="w-full rounded-md border-gray-300 py-1 text-sm shadow-sm focus:border-blue-600 focus:ring-blue-600">
                            </div>
                            <span class="text-right text-sm font-medium text-gray-900">
                                ${{ \App\Support\Money::format((float) $value * (int) ($denominationCounts[$key] ?? 0)) }}
                            </span>
                        </div>
                    @endforeach
                </div>
            @endforeach

            <div class="flex items-center justify-between rounded-lg bg-gradient-to-br from-blue-600 to-purple-600 px-3 py-2 text-white">
                <span class="text-sm font-medium">Total contado</span>
                <span class="text-lg font-black">${{ \App\Support\Money::format((float) $this->denominationTotal()) }}</span>
            </div>

            <div x-data="digitGroupInput({ path: 'closingCountedAmount', live: true })">
                <label class="text-xs text-gray-500">O escribe el monto contado directamente (sin denominaciones)</label>
                <input type="text" inputmode="numeric" placeholder="0" @input="onInput($event)"
                    value="{{ $closingCountedAmount !== '' ? number_format((int) $closingCountedAmount, 0, ',', '.') : '' }}"
                    class="mt-1 w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-600 focus:ring-blue-600">
            </div>

            @if ($this->canViewDifference())
                @php($liveDiff = bcsub($this->currentCountedAmount(), $this->expectedCashAmount($session), 2))
                <div class="flex items-center justify-between rounded-lg px-3 py-2 text-sm {{ bccomp($liveDiff, '0.00', 2) === 0 ? 'bg-emerald-50 ring-1 ring-emerald-200' : 'bg-rose-50 ring-1 ring-rose-200' }}">
                    <span class="{{ bccomp($liveDiff, '0.00', 2) === 0 ? 'text-emerald-800' : 'text-rose-800' }}">Diferencia</span>
                    <span class="font-semibold {{ bccomp($liveDiff, '0.00', 2) === 0 ? 'text-emerald-800' : 'text-rose-800' }}">${{ \App\Support\Money::format((float) $liveDiff) }}</span>
                </div>
            @endif

            <p class="text-[11px] text-gray-500">El cierre se permite aunque el conteo no coincida con el resultado esperado; la diferencia queda registrada en la sesion.</p>

            @if ($this->canCloseCash())
                <button wire:click="closeSession" wire:confirm="¿Cerrar esta sesion de caja?"
                    class="w-full rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700">
                    Cerrar caja
                </button>
            @endif
        @else
            @if ($session->closing_denomination_breakdown)
                <div class="space-y-1">
                    @foreach ($session->closing_denomination_breakdown as $row)
                        <div class="flex items-center justify-between rounded-lg bg-white px-3 py-1.5 ring-1 ring-gray-200 text-sm">
                            <span class="text-gray-700">${{ \App\Support\Money::format((float) $row['value']) }} × {{ $row['quantity'] }}</span>
                            <span class="font-medium text-gray-900">${{ \App\Support\Money::format((float) $row['value'] * (int) $row['quantity']) }}</span>
                        </div>
                    @endforeach
                </div>
            @endif

            <div class="space-y-2">
                <div class="flex items-center justify-between rounded-lg bg-white px-3 py-2 ring-1 ring-gray-200 text-sm">
                    <span class="text-gray-700">Esperado</span>
                    <span class="font-semibold text-gray-900">${{ \App\Support\Money::format((float) $session->closing_expected_amount) }}</span>
                </div>
                <div class="flex items-center justify-between rounded-lg bg-white px-3 py-2 ring-1 ring-gray-200 text-sm">
                    <span class="text-gray-700">Contado</span>
                    <span class="font-semibold text-gray-900">${{ \App\Support\Money::format((float) $session->closing_counted_amount) }}</span>
                </div>
                @if ($this->canViewDifference())
                    @php($diff = (float) $session->difference_amount)
                    <div class="flex items-center justify-between rounded-lg px-3 py-2 text-sm {{ $diff === 0.0 ? 'bg-emerald-50 ring-1 ring-emerald-200' : 'bg-rose-50 ring-1 ring-rose-200' }}">
                        <span class="{{ $diff === 0.0 ? 'text-emerald-800' : 'text-rose-800' }}">Diferencia</span>
                        <span class="font-semibold {{ $diff === 0.0 ? 'text-emerald-800' : 'text-rose-800' }}">${{ \App\Support\Money::format($diff) }}</span>
                    </div>
                @endif
            </div>

            @if ($canEditThisCuadre)
                <p class="text-[11px] text-amber-600">
                    Si corriges bases o pagos de caja arriba, el esperado y la diferencia se recalculan solos.
                </p>
            @endif
        @endif
    </div>
</div>
