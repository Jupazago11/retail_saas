<div class="pb-10">
    {{-- Iconos fijos arriba a la derecha, al mismo nivel que el icono de
    inicio (arriba a la izquierda, definido en el layout). Sin titulo de
    pagina aqui para no gastar espacio vertical: cada paso ya dice donde
    esta parado.

    El "top" usa la misma variable CSS que el icono de inicio del layout
    (--impersonation-banner-height, publicada en layouts/app.blade.php) en
    vez de un top-3 fijo: sin esto, cuando un platform_super_admin esta
    viendo la cuenta de otra empresa, la franja naranja de "Volver a mi
    cuenta" (sticky, mas arriba en z-index) tapaba estos botones porque
    ambos ocupaban la misma franja superior de la pantalla. --}}
    <div style="top: calc(0.75rem + var(--impersonation-banner-height, 0px));" class="fixed right-3 z-[100] flex items-center gap-2">
        <button type="button" wire:click="openCalendarShortcut" title="Ver cuadres de caja"
            class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-500 shadow-md hover:border-blue-300 hover:text-blue-700 transition">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
        </button>

        @if ($this->canEditHistoricalCuadres())
            {{-- Mismo destino que "Ver cuadres de caja" (el calendario ya
            deja editar un dia cerrado al entrar), pero como boton propio y
            con su propio rotulo: la edicion quedaba escondida detras del
            icono de lapiz del popover al hacer hover, dificil de encontrar
            si no sabes que existe. --}}
            <button type="button" wire:click="openCalendarShortcut" title="Editar cuadres de caja"
                class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-500 shadow-md hover:border-blue-300 hover:text-blue-700 transition">
                <x-heroicon-o-pencil-square class="h-5 w-5" />
            </button>
        @endif

        @if ($requiresCashRegisterSelection)
            <button type="button" wire:click="changeCashRegisterShortcut" title="Cambiar de caja"
                class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-500 shadow-md hover:border-blue-300 hover:text-blue-700 transition">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="6" width="18" height="12" rx="2" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M8 14h.01M12 14h4" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M20 3l1.5 1.5L20 6M4 21l-1.5-1.5L4 18" />
                </svg>
            </button>
        @endif

        @if ($this->canManageRules())
            <button type="button" wire:click="openRulesModal" title="Reglas de caja"
                class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-500 shadow-md hover:border-blue-300 hover:text-blue-700 transition">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
            </button>
        @endif
    </div>

    {{-- El resto de la pagina queda en blanco a proposito: toda la operacion
    pasa por el modal de abajo (elegir / crear / historial), salvo el
    cuadre de un dia del historial, que se ve como vista normal (no modal). --}}

    @if ($cashStep === 'day_view')
        <div x-data x-show="true" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
            class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-4">
            <button type="button" wire:click="backToCalendar" class="text-sm font-medium text-gray-500 hover:text-gray-700">← Volver al calendario</button>

            <h2 class="text-xl font-black text-gray-900">
                {{ ucfirst(\Illuminate\Support\Carbon::parse($historyDate)->translatedFormat('l, d \d\e F \d\e Y')) }}
            </h2>

            @if ($historyCashRegisterOptions->count() > 1)
                <div class="max-w-xs">
                    <label class="text-sm font-medium text-gray-700">Caja</label>
                    <select wire:model.live="historyCashRegisterId" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600">
                        @foreach ($historyCashRegisterOptions as $register)
                            <option value="{{ $register->id }}">{{ $register->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            @if ($historySession)
                <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                    <p class="text-sm font-semibold uppercase tracking-[0.22em] text-blue-700">
                        Sesion #{{ $historySession->company_sequence }} · {{ $historySession->cashRegister?->name }}
                    </p>
                    <div class="mt-4">
                        @include('livewire.cash.partials.cuadre-columns', ['session' => $historySession])
                    </div>
                </div>
            @else
                <div class="rounded-xl border border-dashed border-gray-300 bg-gray-50 py-10 text-center">
                    <p class="text-sm text-gray-400">No hay cuadres de caja para esta fecha.</p>
                    @if ($this->canCreateForHistoryDate())
                        <button type="button" wire:click="startCreateForHistoryDate"
                            class="mt-4 inline-flex items-center gap-1.5 rounded-lg bg-gradient-to-br from-blue-600 to-purple-600 px-4 py-2 text-sm font-semibold text-white hover:from-blue-700 hover:to-purple-700">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                            </svg>
                            Crear cuadre de caja para este dia
                        </button>
                    @endif
                </div>
            @endif
        </div>
    @endif

    <div x-data
        x-show="!$wire.showCuadreModal && $wire.cashStep !== 'day_view'"
        x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
        x-cloak
        class="fixed inset-0 z-40 flex items-center justify-center p-4" style="background: rgba(0,0,0,0.5);">
            <div x-show="!$wire.showCuadreModal && $wire.cashStep !== 'day_view'"
                x-transition:enter="transition ease-out duration-200 delay-75" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
                class="w-full {{ $cashStep === 'calendar' ? 'max-w-2xl' : 'max-w-lg' }} rounded-xl bg-white shadow-xl flex flex-col" style="max-height: 90vh;">
                <div class="flex-1 overflow-y-auto p-6">

                    {{-- Paso: elegir con cual caja se va a trabajar (solo si hay mas de una habilitada) --}}
                    @if ($cashStep === 'select_register')
                        @if ($activeCashRegisterId)
                            <button type="button" wire:click="backToChoice" class="text-sm font-medium text-gray-500 hover:text-gray-700">← Volver</button>
                        @endif
                        <div class="text-center">
                            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-blue-600 to-purple-600 text-white">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <rect x="3" y="6" width="18" height="12" rx="2" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M8 14h.01M12 14h4" />
                                </svg>
                            </div>
                            <h3 class="mt-3 text-xl font-black text-gray-900">¿Con cual caja vas a trabajar?</h3>
                            <p class="mt-1 text-sm text-gray-500">Tu empresa tiene mas de una caja habilitada. Elige una para esta sesion; puedes cambiarla despues si te equivocas.</p>
                        </div>

                        <div class="mt-6 space-y-2">
                            @foreach ($enabledCashRegisters as $register)
                                <button type="button" wire:click="selectActiveCashRegister({{ $register->id }})"
                                    class="flex w-full items-center justify-between rounded-xl border-2 border-gray-200 bg-gray-50 p-4 text-left hover:border-blue-400 transition {{ $activeCashRegisterId === $register->id ? 'border-blue-400 bg-blue-50' : '' }}">
                                    <span>
                                        <span class="block font-bold text-gray-900">
                                            {{ $register->name }}
                                            @if ($register->is_primary)
                                                <span class="text-xs font-normal text-gray-400">(principal)</span>
                                            @endif
                                        </span>
                                        <span class="block text-xs text-gray-500">{{ $register->branch?->name }}</span>
                                    </span>
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                                    </svg>
                                </button>
                            @endforeach
                        </div>

                    {{-- Paso: elegir --}}
                    @elseif ($cashStep === 'choice')
                        <div class="text-center">
                            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-blue-600 to-purple-600 text-white">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <h3 class="mt-3 text-xl font-black text-gray-900">Aun no tienes un cuadre de caja abierto hoy</h3>
                            <p class="mt-1 text-sm text-gray-500">¿Que deseas hacer?</p>
                        </div>

                        @if ($requiresCashRegisterSelection && $activeCashRegisterId)
                            <div class="mt-4 flex items-center justify-center gap-2 text-xs text-gray-500">
                                <span>Trabajando en <span class="font-semibold text-gray-700">{{ $enabledCashRegisters->firstWhere('id', $activeCashRegisterId)?->name }}</span></span>
                                <button type="button" wire:click="changeActiveCashRegister" class="font-semibold text-blue-700 hover:underline">Cambiar</button>
                            </div>
                        @endif

                        <div class="mt-6 grid gap-3 {{ $this->canOpenCash() ? 'sm:grid-cols-2' : '' }}">
                            @if ($this->canOpenCash())
                                <button type="button" wire:click="startCreate"
                                    class="rounded-xl border-2 border-blue-200 bg-blue-50 p-5 text-left hover:border-blue-400 transition">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-blue-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                                    </svg>
                                    <p class="mt-2 font-bold text-gray-900">Crear caja</p>
                                    <p class="mt-0.5 text-xs text-gray-500">
                                        @if ($this->hasAvailableCashRegisters())
                                            Abre una nueva sesion de caja.
                                        @else
                                            Tu plan no tiene mas cajas disponibles; te llevara a la que ya esta abierta.
                                        @endif
                                    </p>
                                </button>
                            @endif

                            <button type="button" wire:click="startHistory"
                                class="rounded-xl border-2 border-gray-200 bg-gray-50 p-5 text-left hover:border-gray-400 transition">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-gray-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                                <p class="mt-2 font-bold text-gray-900">Ver cuadres de caja</p>
                                <p class="mt-0.5 text-xs text-gray-500">Consulta el historial por fecha.</p>
                            </button>
                        </div>

                        @if ($openSessionsForChoice->isNotEmpty())
                            <div class="mt-6 border-t border-gray-100 pt-4">
                                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Cajas abiertas ahora</p>
                                <div class="mt-2 space-y-2">
                                    @foreach ($openSessionsForChoice as $session)
                                        <button type="button" wire:click="openCuadre({{ $session->id }})"
                                            class="flex w-full items-center justify-between rounded-lg bg-emerald-50 px-3 py-2 text-sm hover:bg-emerald-100 transition">
                                            <span class="font-medium text-emerald-900">{{ $session->cashRegister?->name }} · Sesion #{{ $session->company_sequence }}</span>
                                            <span class="font-semibold text-emerald-700">Continuar →</span>
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                    {{-- Paso: crear caja --}}
                    @elseif ($cashStep === 'create')
                        <button type="button" wire:click="backToChoice" class="text-sm font-medium text-gray-500 hover:text-gray-700">← Volver</button>

                        <p class="mt-3 text-sm font-semibold uppercase tracking-[0.22em] text-blue-700">Apertura</p>
                        <h3 class="text-xl font-black text-gray-900">Nueva sesion de caja</h3>

                        @if ($creatingSessionForDate !== '')
                            <p class="mt-1 text-xs font-semibold text-amber-600">
                                Se creara con fecha {{ ucfirst(\Illuminate\Support\Carbon::parse($creatingSessionForDate)->translatedFormat('d \d\e F \d\e Y')) }}, no con la fecha de hoy.
                            </p>
                        @endif

                        @unless ($canOpenCashSessions)
                            <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                                Antes de operar caja debes tener al menos una sucursal activa y una caja activa en la empresa actual.
                            </div>
                        @endunless

                        <form wire:submit="openSession" class="mt-5 space-y-5">
                            @if ($branches->count() > 1 || $cashRegisters->count() > 1)
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <label class="text-sm font-medium text-gray-700">Sucursal <span class="text-rose-600">*</span></label>
                                        <select wire:model.live="branchId" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600" @disabled(! $canOpenCashSessions)>
                                            <option value="">Selecciona</option>
                                            @foreach ($branches as $branch)
                                                <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div>
                                        <label class="text-sm font-medium text-gray-700">Caja <span class="text-rose-600">*</span></label>
                                        <select wire:model="cashRegisterId" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600" @disabled(! $canOpenCashSessions)>
                                            <option value="">Selecciona</option>
                                            @foreach ($cashRegisters as $cashRegister)
                                                <option value="{{ $cashRegister->id }}">{{ $cashRegister->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            @elseif ($canOpenCashSessions)
                                <p class="text-xs text-gray-500">
                                    {{ $branches->first()?->name }} · {{ $cashRegisters->first()?->name }}
                                </p>
                            @endif

                            <div>
                                <div class="flex items-center justify-between">
                                    <label class="text-sm font-medium text-gray-700">Bases de apertura</label>
                                    <button type="button" wire:click="addOpenFund" class="text-xs font-semibold text-blue-700 hover:underline">
                                        + Agregar base
                                    </button>
                                </div>

                                <div class="mt-2 space-y-2">
                                    @foreach ($openFunds as $index => $fund)
                                        <div class="flex items-center gap-2" wire:key="open-fund-{{ $index }}">
                                            <input wire:model="openFunds.{{ $index }}.label" type="text" placeholder="Descripcion"
                                                class="w-1/2 rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-600 focus:ring-blue-600" @disabled(! $canOpenCashSessions)>
                                            <div class="w-1/2" x-data="digitGroupInput({ path: 'openFunds.{{ $index }}.amount', live: false })">
                                                <input type="text" inputmode="numeric" placeholder="0" @input="onInput($event)"
                                                    value="{{ $fund['amount'] !== '' ? number_format((int) $fund['amount'], 0, ',', '.') : '' }}"
                                                    class="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-600 focus:ring-blue-600" @disabled(! $canOpenCashSessions)>
                                            </div>
                                            @if (count($openFunds) > 1)
                                                <button type="button" wire:click="removeOpenFund({{ $index }})" class="text-gray-400 hover:text-rose-600">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                    </svg>
                                                </button>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>

                                <p class="mt-2 text-xs text-gray-500">
                                    Si dejas todas las bases vacias,
                                    @if ($this->openingRequired())
                                        se usara el valor por defecto: ${{ $this->defaultOpeningAmount() }}.
                                    @else
                                        la sesion abre en $0.00.
                                    @endif
                                </p>
                            </div>

                            <button type="submit" class="w-full rounded-lg bg-gradient-to-br from-blue-600 to-purple-600 px-5 py-2.5 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-60" @disabled(! $canOpenCashSessions)>
                                Abrir sesion
                            </button>
                        </form>

                    {{-- Paso: calendario --}}
                    @elseif ($cashStep === 'calendar')
                        <div class="flex items-center justify-between">
                            <button type="button" wire:click="backToChoice" class="text-sm font-medium text-gray-500 hover:text-gray-700">← Volver</button>
                            <div class="flex items-center gap-3">
                                <button type="button" wire:click="calendarPrevMonth" wire:loading.attr="disabled" wire:target="calendarPrevMonth,calendarNextMonth" class="rounded-lg border border-gray-200 p-1.5 text-gray-500 hover:bg-gray-50 disabled:opacity-50">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                                </button>
                                <h3 class="w-40 text-center text-lg font-black text-gray-900" wire:key="calendar-month-{{ $calendarMonth }}">
                                    {{ ucfirst(\Illuminate\Support\Carbon::createFromFormat('Y-m-d', $calendarMonth . '-01')->translatedFormat('F Y')) }}
                                </h3>
                                <button type="button" wire:click="calendarNextMonth" wire:loading.attr="disabled" wire:target="calendarPrevMonth,calendarNextMonth" class="rounded-lg border border-gray-200 p-1.5 text-gray-500 hover:bg-gray-50 disabled:opacity-50">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                                </button>
                            </div>
                            <span class="w-16 text-right">
                                @if ($requiresCashRegisterSelection)
                                    <button type="button" wire:click="changeActiveCashRegister" class="text-xs font-semibold text-blue-700 hover:underline">Cambiar caja</button>
                                @endif
                            </span>
                        </div>

                        <p class="mt-4 text-center text-sm text-gray-500">Selecciona un dia para ver su cuadre de caja.</p>

                        <div class="mt-4 grid grid-cols-7 gap-1.5 text-center text-xs font-semibold uppercase text-gray-400">
                            <div>Lun</div><div>Mar</div><div>Mie</div><div>Jue</div><div>Vie</div><div>Sab</div><div>Dom</div>
                        </div>
                        <div class="mt-2 grid grid-cols-7 gap-1.5">
                            @foreach ($calendarCells as $cell)
                                @if ($cell['date'] === null)
                                    <div wire:key="calendar-pad-{{ $loop->index }}"></div>
                                @elseif (! $cell['hasSessions'])
                                    {{-- wire:key por fecha: sin esto, Livewire hace matching posicional
                                         entre renders y puede confundir esta celda con la de abajo (que
                                         alterna entre <div>, <button> y el <div x-data=... > del popover
                                         segun hasSessions) — eso es lo que dejaba el hover roto despues
                                         de cerrar una caja y volver a entrar al calendario. --}}
                                    <button type="button" wire:key="calendar-day-{{ $cell['date'] }}" wire:click="selectHistoryDate('{{ $cell['date'] }}')"
                                        class="flex aspect-square flex-col items-center justify-center rounded-xl text-base transition bg-gray-50 text-gray-600 hover:bg-gray-100
                                            {{ $cell['isToday'] ? 'ring-2 ring-inset ring-blue-400' : '' }}">
                                        {{ $cell['day'] }}
                                    </button>
                                @else
                                    {{-- Popover flotante en vez de title=: reutiliza el mismo patron de
                                         posicionamiento (getBoundingClientRect + position:fixed) que usan
                                         los tooltips de purchases-page.blade.php, pero como card blanca
                                         porque aqui el contenido es una lista, no una sola palabra — y a
                                         diferencia de esos, este es interactivo (tiene el boton de editar),
                                         asi que usa un pequeno delay al esconderse (en vez de solo
                                         mouseleave del boton) para que de tiempo de mover el mouse hasta
                                         el icono sin que el popover desaparezca en el camino.

                                         Posicion en dos pasos: primero se abre debajo de la celda (igual
                                         que antes) para no parpadear, y en $nextTick (ya con el popover
                                         renderizado y medible) se corrige si no cabe — si no hay espacio
                                         abajo (celdas de la ultima fila del mes) se voltea arriba de la
                                         celda en vez de quedar fuera de la pantalla, y se recorta
                                         horizontalmente para no salirse por los bordes. --}}
                                    <div wire:key="calendar-day-{{ $cell['date'] }}" x-data="{
                                            show: false,
                                            flipped: false,
                                            hideTimer: null,
                                            p: { t: 0, l: 0 },
                                            open(e) {
                                                clearTimeout(this.hideTimer);
                                                const r = e.currentTarget.getBoundingClientRect();
                                                this.flipped = false;
                                                this.p = { t: r.bottom + 8, l: r.left + r.width / 2 };
                                                this.show = true;
                                                this.$nextTick(() => {
                                                    const el = this.$refs.popover;
                                                    if (! el) return;
                                                    const margin = 8;
                                                    if (el.offsetHeight > window.innerHeight - r.bottom - margin) {
                                                        this.flipped = true;
                                                        this.p.t = Math.max(margin, r.top - el.offsetHeight - margin);
                                                    }
                                                    const halfWidth = el.offsetWidth / 2;
                                                    this.p.l = Math.min(Math.max(this.p.l, halfWidth + margin), window.innerWidth - halfWidth - margin);
                                                });
                                            },
                                            scheduleHide() {
                                                this.hideTimer = setTimeout(() => { this.show = false; }, 200);
                                            },
                                        }" class="relative">
                                        <button type="button" wire:click="selectHistoryDate('{{ $cell['date'] }}')"
                                            @mouseenter="open($event)" @mouseleave="scheduleHide()"
                                            class="flex aspect-square w-full flex-col items-center justify-center rounded-xl text-base font-bold text-white shadow-sm transition bg-gradient-to-br from-blue-600 to-purple-600 hover:from-blue-700 hover:to-purple-700">
                                            {{ $cell['day'] }}
                                        </button>

                                        <div x-ref="popover" x-show="show" x-cloak
                                            @mouseenter="clearTimeout(hideTimer)" @mouseleave="scheduleHide()"
                                            x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
                                            :style="`position:fixed;top:${p.t}px;left:${p.l}px;transform:translateX(-50%);z-index:9999`"
                                            class="w-56 rounded-xl bg-white p-3 text-left shadow-xl ring-1 ring-gray-200">
                                            <div class="absolute left-1/2 h-3 w-3 -translate-x-1/2 rotate-45 bg-white ring-1 ring-gray-200"
                                                :class="flipped ? '-bottom-1.5' : '-top-1.5'"></div>

                                            <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-400">
                                                {{ ucfirst(\Illuminate\Support\Carbon::parse($cell['date'])->translatedFormat('d \d\e F')) }}
                                            </p>
                                            <div class="relative mt-1.5 space-y-1.5">
                                                @foreach ($cell['registers'] as $register)
                                                    <div class="flex items-start justify-between gap-2">
                                                        <span class="pt-1 text-xs font-medium text-gray-700">{{ $register['name'] }}</span>
                                                        @if ($register['status'] === 'open')
                                                            <x-status-badge color="amber">sin cerrar</x-status-badge>
                                                        @else
                                                            <div class="flex items-start gap-1">
                                                                <x-status-badge color="stone">
                                                                    {{ \App\Support\Money::format($register['closingCounted']) }}
                                                                </x-status-badge>
                                                                @if ($this->canEditHistoricalCuadres())
                                                                    <button type="button" wire:click="openCuadre({{ $register['sessionId'] }})"
                                                                        title="Editar este cuadre"
                                                                        class="mt-0.5 shrink-0 text-gray-400 transition hover:text-blue-600">
                                                                        <x-heroicon-o-pencil-square class="h-3.5 w-3.5" />
                                                                    </button>
                                                                @endif
                                                            </div>
                                                        @endif
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        </div>

                        <div class="mt-4 flex items-center justify-center gap-2 text-xs text-gray-500">
                            <span class="h-3 w-3 rounded bg-gradient-to-br from-blue-600 to-purple-600"></span>
                            Dias con cuadres de caja registrados
                        </div>

                    @endif
                </div>
            </div>
        </div>

    <div x-data x-show="$wire.showRulesModal"
        x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
        x-cloak
        wire:click.self="closeRulesModal"
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
        style="background: rgba(0,0,0,0.5);">
            <div x-show="$wire.showRulesModal"
                x-transition:enter="transition ease-out duration-200 delay-75" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
                class="w-full max-w-md rounded-xl bg-white shadow-xl flex flex-col" style="max-height: 90vh;">

                {{-- Header pinned --}}
                <div class="flex-shrink-0 flex items-center justify-between border-b border-stone-100 px-5 py-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-widest text-blue-700">Reglas</p>
                        <h3 class="mt-0.5 text-lg font-black text-gray-900">Caja</h3>
                    </div>
                    <button wire:click="closeRulesModal" class="text-gray-400 hover:text-gray-700 text-xl leading-none px-1">&times;</button>
                </div>

                {{-- Form: scrollable body + pinned footer --}}
                <form wire:submit="saveRules" class="flex flex-col flex-1 min-h-0">
                    <div class="flex-1 overflow-y-auto px-5 py-4 space-y-4">
                        <label class="flex items-start gap-3">
                            <input wire:model="ruleOpeningRequired" type="checkbox"
                                class="mt-0.5 rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-600">
                            <span>
                                <span class="block text-sm font-medium text-gray-900">Exigir monto de apertura</span>
                                <span class="block text-xs text-gray-500">Si esta desactivado, la caja se puede abrir en $0.00 sin pedir monto.</span>
                            </span>
                        </label>

                        <div x-data="digitGroupInput({ path: 'ruleDefaultOpeningAmount', live: false })">
                            <label class="text-sm font-medium text-gray-700">Monto por defecto de apertura <span class="text-rose-600">*</span></label>
                            <input type="text" inputmode="numeric" @input="onInput($event)"
                                value="{{ $ruleDefaultOpeningAmount !== '' ? number_format((int) $ruleDefaultOpeningAmount, 0, ',', '.') : '' }}"
                                class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600">
                        </div>

                        @if ($cashRegisterPlanLimit === null || $cashRegisterPlanLimit > 1)
                            <div class="border-t border-stone-100 pt-4">
                                <p class="text-sm font-medium text-gray-900">Cajas habilitadas</p>
                                <p class="text-xs text-gray-500">
                                    Tu plan permite hasta {{ $cashRegisterPlanLimit ?? 'un numero ilimitado de' }} cajas. Desmarca las que no uses (por ejemplo, si hoy no vas a abrir esa caja).
                                </p>
                                <div class="mt-2 space-y-1.5">
                                    @foreach ($companyCashRegisters as $register)
                                        <label wire:key="cash-register-{{ $register->id }}" class="flex items-center justify-between rounded-lg border border-gray-200 px-3 py-2">
                                            <span class="text-sm text-gray-700">
                                                {{ $register->name }}
                                                @if ($register->is_primary)
                                                    <span class="text-xs text-gray-400">(principal)</span>
                                                @endif
                                            </span>
                                            <input type="checkbox" wire:click="toggleCashRegisterStatus({{ $register->id }})"
                                                @checked($register->status === 'active') @disabled($register->is_primary)
                                                class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-600 disabled:opacity-50">
                                        </label>
                                    @endforeach
                                </div>

                                @if ($this->canCreateMoreCashRegisters())
                                    {{-- Div, no <form>: ya estamos dentro del <form wire:submit="saveRules">
                                         de todo el modal, y un <form> anidado es HTML invalido — el navegador
                                         lo "arregla" cerrando el de afuera antes de tiempo, lo que desincroniza
                                         el DOM real del que Livewire cree que renderizo y corrompe el morphing
                                         (el input pierde lo escrito/pegado y el boton termina disparando
                                         "saveRules" en vez de "addCashRegister"). --}}
                                    <div class="mt-2 flex items-start gap-2">
                                        <div class="flex-1">
                                            <input wire:model="newRegisterName" type="text" placeholder="Nombre de la nueva caja (ej: Caja 2)"
                                                wire:keydown.enter.prevent="addCashRegister"
                                                class="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-600 focus:ring-blue-600">
                                        </div>
                                        <div class="w-40 flex-shrink-0">
                                            <select wire:model="newRegisterPrinterType"
                                                class="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-600 focus:ring-blue-600">
                                                @foreach (\App\Models\CashRegister::PRINTER_TYPES as $value => $label)
                                                    <option value="{{ $value }}">{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <button type="button" wire:click="addCashRegister" class="inline-flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-lg bg-blue-600 text-white hover:bg-blue-700">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                                            </svg>
                                        </button>
                                    </div>
                                    <p class="mt-1 text-xs text-gray-400">Podras cambiar el tipo de impresora despues desde Estructura de empresa.</p>
                                @else
                                    <p class="mt-2 text-xs text-gray-400">Ya creaste el maximo de cajas que permite tu plan.</p>
                                @endif
                            </div>
                        @endif
                    </div>

                    <div class="flex-shrink-0 flex items-center justify-end gap-3 border-t border-stone-100 px-5 py-3">
                        <button type="button" wire:click="closeRulesModal" class="text-sm font-medium text-gray-500 hover:text-gray-700">
                            Cancelar
                        </button>
                        <button type="submit"
                            class="inline-flex items-center rounded-lg bg-gradient-to-br from-blue-600 to-purple-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:from-blue-700 hover:to-purple-700">
                            Guardar
                        </button>
                    </div>
                </form>
            </div>
        </div>

    @if ($showCuadreModal && $cuadreSession)
        @php
            $sessionIsOpen = $cuadreSession->status === 'open';
        @endphp
        <div x-data x-show="true" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
            class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-4">
            <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-widest text-blue-700">
                            Cuadre de caja
                        </p>
                        <h2 class="mt-0.5 text-xl font-black text-gray-900">
                            {{ ucfirst(\Illuminate\Support\Carbon::parse($cuadreSession->opened_at)->translatedFormat('d \d\e F \d\e Y')) }} · {{ $cuadreSession->cashRegister?->name }}
                            @unless ($sessionIsOpen)
                                <span class="ml-1 align-middle">
                                    <x-status-badge :color="$cuadreSession->status === 'reconciled' ? 'sky' : 'stone'">
                                        {{ $cuadreSession->status === 'reconciled' ? 'Cuadrada' : 'Cerrada' }}
                                    </x-status-badge>
                                </span>
                            @endunless
                        </h2>
                        @if (! $sessionIsOpen && ! $this->canEditHistoricalCuadres())
                            <p class="mt-0.5 text-xs text-gray-500">Esta caja ya cerro. Solo lectura.</p>
                        @elseif (! $sessionIsOpen && $this->canEditHistoricalCuadres())
                            <p class="mt-0.5 text-xs text-amber-600">Estas corrigiendo un cuadre ya cerrado, como administrador.</p>
                        @endif
                    </div>
                </div>

                <div class="mt-4">
                    @include('livewire.cash.partials.cuadre-columns', ['session' => $cuadreSession])
                </div>
            </div>
        </div>
    @endif
</div>
