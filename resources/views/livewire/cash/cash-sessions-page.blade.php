<div class="py-10">
    <div class="mx-auto grid max-w-7xl gap-6 px-4 sm:px-6 lg:grid-cols-[0.95fr_1.15fr] lg:px-8">
        <div class="space-y-6">
            @unless ($canOpenCashSessions)
                <div class="rounded-3xl border border-amber-200 bg-amber-50 p-5 text-amber-900">
                    <p class="text-sm font-semibold uppercase tracking-[0.22em]">Prerequisitos</p>
                    <p class="mt-2 text-sm">
                        Antes de operar caja debes tener al menos una sucursal activa y una caja activa en la empresa actual.
                    </p>
                </div>
            @endunless

            <div class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-stone-200">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-[0.22em] text-amber-700">Apertura</p>
                        <h3 class="mt-2 text-2xl font-black text-stone-900">Nueva sesion</h3>
                    </div>

                    <button wire:click="clearOpenForm" class="text-sm font-medium text-stone-500">
                        Limpiar
                    </button>
                </div>

                @if ($this->canOpenCash())
                    <form wire:submit="openSession" class="mt-6 space-y-5">
                        @if ($branches->count() > 1 || $cashRegisters->count() > 1)
                            <div class="grid gap-4 md:grid-cols-2">
                                <div>
                                    <label class="text-sm font-medium text-stone-700">Sucursal</label>
                                    <select wire:model.live="branchId" class="mt-1 block w-full rounded-2xl border-stone-300 shadow-sm focus:border-amber-500 focus:ring-amber-500" @disabled(! $canOpenCashSessions)>
                                        <option value="">Selecciona</option>
                                        @foreach ($branches as $branch)
                                            <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('branchId') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                                </div>

                                <div>
                                    <label class="text-sm font-medium text-stone-700">Caja</label>
                                    <select wire:model="cashRegisterId" class="mt-1 block w-full rounded-2xl border-stone-300 shadow-sm focus:border-amber-500 focus:ring-amber-500" @disabled(! $canOpenCashSessions)>
                                        <option value="">Selecciona</option>
                                        @foreach ($cashRegisters as $cashRegister)
                                            <option value="{{ $cashRegister->id }}">{{ $cashRegister->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('cashRegisterId') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                                </div>
                            </div>
                        @elseif ($canOpenCashSessions)
                            <p class="text-xs text-stone-500">
                                {{ $branches->first()?->name }} · {{ $cashRegisters->first()?->name }}
                            </p>
                        @endif

                        <div>
                            <label class="text-sm font-medium text-stone-700">Monto de apertura</label>
                            <input wire:model="openingAmount" type="number" min="0" step="0.01" placeholder="{{ $this->openingRequired() ? 'Si lo dejas vacio se usa '.$this->defaultOpeningAmount() : 'Opcional; si lo dejas vacio abre en 0.00' }}" class="mt-1 block w-full rounded-2xl border-stone-300 shadow-sm focus:border-amber-500 focus:ring-amber-500" @disabled(! $canOpenCashSessions)>
                            @error('openingAmount') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                            <p class="mt-1 text-xs text-stone-500">
                                @if ($this->openingRequired())
                                    La empresa exige apertura. Si no escribes monto, se usara el valor por defecto: ${{ $this->defaultOpeningAmount() }}.
                                @else
                                    La empresa no exige apertura. Si no escribes monto, la sesion abre en $0.00.
                                @endif
                            </p>
                        </div>

                        <button type="submit" class="inline-flex rounded-full bg-stone-900 px-5 py-2.5 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-60" @disabled(! $canOpenCashSessions)>
                            Abrir sesion
                        </button>
                    </form>
                @else
                    <div class="mt-6 rounded-2xl border border-dashed border-stone-300 bg-stone-50 p-5 text-sm text-stone-600">
                        Tu rol actual no puede abrir sesiones de caja.
                    </div>
                @endif
            </div>

            <div class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-stone-200">
                <p class="text-sm font-semibold uppercase tracking-[0.22em] text-amber-700">Politicas</p>
                <h3 class="mt-2 text-2xl font-black text-stone-900">Reglas vigentes</h3>
                <div class="mt-4 space-y-3 text-sm text-stone-600">
                    <p>
                        Apertura requerida:
                        <span class="font-medium text-stone-900">{{ $this->openingRequired() ? 'Si' : 'No' }}</span>
                    </p>
                    <p>
                        Apertura por defecto:
                        <span class="font-medium text-stone-900">${{ $this->defaultOpeningAmount() }}</span>
                    </p>
                    <p>
                        Cierre con diferencia:
                        <span class="font-medium text-stone-900">{{ $this->allowsCloseWithDifference() ? 'Permitido' : 'Bloqueado' }}</span>
                    </p>
                </div>
            </div>
        </div>

        <div class="space-y-6">
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-5">
                <div class="rounded-2xl bg-white p-3 shadow-sm ring-1 ring-stone-200">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-stone-500">Abiertas</p>
                    <p class="mt-1 text-xl font-black text-stone-900">{{ $statusCards['open_count'] }}</p>
                </div>
                <div class="rounded-2xl bg-white p-3 shadow-sm ring-1 ring-stone-200">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-stone-500">Cerradas</p>
                    <p class="mt-1 text-xl font-black text-stone-900">{{ $statusCards['closed_count'] }}</p>
                </div>
                <div class="rounded-2xl bg-white p-3 shadow-sm ring-1 ring-stone-200">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-stone-500">Cuadradas</p>
                    <p class="mt-1 text-xl font-black text-emerald-700">{{ $statusCards['reconciled_count'] }}</p>
                </div>
                <div class="rounded-2xl bg-white p-3 shadow-sm ring-1 ring-stone-200">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-stone-500">Efectivo esperado</p>
                    <p class="mt-1 text-xl font-black text-stone-900">${{ $statusCards['open_expected_cash'] }}</p>
                </div>
                @if ($filterCashRegisters->count() > 1)
                    <div class="rounded-2xl bg-white p-3 shadow-sm ring-1 ring-stone-200">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-stone-500">Cajas filtradas</p>
                        <p class="mt-1 text-xl font-black text-stone-900">{{ $statusCards['cash_registers_count'] }}</p>
                    </div>
                @endif
            </div>

            <div class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-stone-200">
                <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-[0.22em] text-amber-700">Sesiones</p>
                        <h3 class="mt-2 text-2xl font-black text-stone-900">Historial reciente de caja</h3>
                    </div>

                    @php $showLocationFilters = $branches->count() > 1 || $filterCashRegisters->count() > 1; @endphp
                    <div class="grid gap-3 {{ $showLocationFilters ? 'md:grid-cols-4' : 'md:grid-cols-2' }}">
                        <input wire:model.live.debounce.300ms="search" type="text" placeholder="Buscar por caja, actor o id" class="rounded-2xl border-stone-300 shadow-sm focus:border-amber-500 focus:ring-amber-500">
                        <select wire:model.live="statusFilter" class="rounded-2xl border-stone-300 shadow-sm focus:border-amber-500 focus:ring-amber-500">
                            <option value="">Todos los estados</option>
                            <option value="open">Abiertas</option>
                            <option value="closed">Cerradas</option>
                            <option value="reconciled">Cuadradas</option>
                        </select>
                        @if ($showLocationFilters)
                            <select wire:model.live="branchFilterId" class="rounded-2xl border-stone-300 shadow-sm focus:border-amber-500 focus:ring-amber-500">
                                <option value="">Todas las sucursales</option>
                                @foreach ($branches as $branch)
                                    <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                                @endforeach
                            </select>
                            <select wire:model.live="cashRegisterFilterId" class="rounded-2xl border-stone-300 shadow-sm focus:border-amber-500 focus:ring-amber-500">
                                <option value="">Todas las cajas</option>
                                @foreach ($filterCashRegisters as $cashRegister)
                                    <option value="{{ $cashRegister->id }}">{{ $cashRegister->name }}</option>
                                @endforeach
                            </select>
                        @endif
                    </div>
                </div>

                <div class="mt-6 space-y-4">
                    @forelse ($sessions as $session)
                        @php
                            $cashPayments = $session->payments->where('status', 'confirmed')->where('payment_method_code', 'cash')->sum('amount');
                        @endphp
                        <div wire:key="cash-session-card-{{ $session->id }}" class="rounded-3xl border border-stone-200 bg-stone-50 p-5">
                            <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                                <div class="space-y-3">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h4 class="text-lg font-black text-stone-900">Sesion #{{ $session->company_sequence }}</h4>
                                        <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $session->status === 'open' ? 'bg-emerald-100 text-emerald-700' : ($session->status === 'reconciled' ? 'bg-sky-100 text-sky-700' : 'bg-stone-200 text-stone-700') }}">
                                            {{ $session->status === 'open' ? 'Abierta' : ($session->status === 'reconciled' ? 'Cuadrada' : 'Cerrada') }}
                                        </span>
                                    </div>

                                    <div class="grid gap-2 text-sm text-stone-600 md:grid-cols-2">
                                        @if ($showLocationFilters)
                                            <p>Sucursal: <span class="font-medium text-stone-900">{{ $session->branch?->name }}</span></p>
                                            <p>Caja: <span class="font-medium text-stone-900">{{ $session->cashRegister?->name }}</span></p>
                                        @endif
                                        <p>Abrio: <span class="font-medium text-stone-900">{{ $session->opener?->name }}</span></p>
                                        <p>Fecha apertura: <span class="font-medium text-stone-900">{{ optional($session->opened_at)->format('Y-m-d H:i') ?: 'Sin fecha' }}</span></p>
                                        <p>Apertura: <span class="font-medium text-stone-900">${{ \App\Support\Money::format((float) $session->opening_amount) }}</span></p>
                                        <p>Efectivo cobrado: <span class="font-medium text-stone-900">${{ \App\Support\Money::format((float) $cashPayments) }}</span></p>
                                        <p>Efectivo esperado: <span class="font-medium text-stone-900">${{ \App\Support\Money::format((float) $this->expectedCashAmount($session)) }}</span></p>
                                        @if (in_array($session->status, ['closed', 'reconciled'], true))
                                            <p>Cerro: <span class="font-medium text-stone-900">{{ $session->closer?->name ?: 'Sin cierre' }}</span></p>
                                            <p>Fecha cierre: <span class="font-medium text-stone-900">{{ optional($session->closed_at)->format('Y-m-d H:i') ?: 'Sin fecha' }}</span></p>
                                            <p>Contado: <span class="font-medium text-stone-900">${{ \App\Support\Money::format((float) $session->closing_counted_amount) }}</span></p>
                                            @if ($this->canViewDifference())
                                                <p>Diferencia: <span class="font-medium {{ (float) $session->difference_amount === 0.0 ? 'text-emerald-700' : 'text-rose-700' }}">${{ \App\Support\Money::format((float) $session->difference_amount) }}</span></p>
                                            @endif
                                        @endif
                                    </div>
                                </div>

                                <div class="min-w-[250px] space-y-3">
                                    <div class="grid grid-cols-2 gap-3">
                                        <div class="rounded-2xl bg-white p-4 ring-1 ring-stone-200">
                                            <p class="text-xs uppercase tracking-[0.18em] text-stone-500">Pagos confirmados</p>
                                            <p class="mt-1 text-lg font-black text-stone-900">{{ $session->payments->where('status', 'confirmed')->count() }}</p>
                                        </div>
                                        <div class="rounded-2xl bg-white p-4 ring-1 ring-stone-200">
                                            <p class="text-xs uppercase tracking-[0.18em] text-stone-500">Otros medios</p>
                                            <p class="mt-1 text-lg font-black text-stone-900">${{ \App\Support\Money::format((float) $session->payments->where('status', 'confirmed')->where('payment_method_code', '!=', 'cash')->sum('amount')) }}</p>
                                        </div>
                                    </div>

                                    @if ($session->status === 'open' && $this->canCloseCash())
                                        <button wire:click="startClosingSession({{ $session->id }})" class="rounded-full border border-emerald-300 px-3 py-1 font-medium text-emerald-700">
                                            Cerrar sesion
                                        </button>
                                    @endif

                                    @if ($closingSessionId === $session->id)
                                        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4">
                                            <div class="space-y-3">
                                                <div>
                                                    <label class="text-sm font-medium text-stone-700">Monto contado al cierre</label>
                                                    <input wire:model="closingCountedAmount" type="number" step="0.01" class="mt-1 block w-full rounded-2xl border-stone-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                                                    @error('closingCountedAmount') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                                                </div>

                                                <p class="text-xs text-stone-500">
                                                    @if ($this->allowsCloseWithDifference())
                                                        La empresa permite cerrar con diferencia si el conteo no coincide.
                                                    @else
                                                        La empresa bloquea cierres con diferencia. El contado debe coincidir con el esperado.
                                                    @endif
                                                </p>

                                                <div class="flex gap-2">
                                                    <button wire:click="closeSession" class="rounded-full bg-stone-900 px-4 py-2 text-sm font-semibold text-white">
                                                        Confirmar cierre
                                                    </button>
                                                    <button wire:click="cancelClosingSession" class="rounded-full border border-stone-300 px-4 py-2 text-sm font-semibold text-stone-700">
                                                        Cancelar
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="rounded-3xl border border-dashed border-stone-300 bg-stone-50 p-8 text-center text-stone-500">
                            Aun no hay sesiones de caja registradas con el filtro actual.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
