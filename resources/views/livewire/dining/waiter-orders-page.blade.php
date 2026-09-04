<div x-data="{ view: 'map' }" class="space-y-5">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap items-center gap-2">
            @if ($branches->count() > 1)
                <select wire:change="switchBranch($event.target.value)" class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-600 focus:ring-blue-600">
                    @foreach ($branches as $branch)
                        <option value="{{ $branch->id }}" @selected($branch->id === $branchId)>{{ $branch->name }}</option>
                    @endforeach
                </select>
            @endif
        </div>

        <button type="button" x-on:click="view = view === 'simple' ? 'map' : 'simple'"
            class="inline-flex items-center gap-2 rounded-full border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50">
            <x-heroicon-o-eye class="h-4 w-4" />
            <span x-text="view === 'simple' ? 'Ver plano del salon' : 'Ver lista de mesas'"></span>
        </button>
    </div>

    {{-- Vista simple: select de mesa + pedido inline debajo. --}}
    <div x-show="view === 'simple'" class="space-y-5">
        <div class="rounded-xl bg-white p-4 ring-1 ring-gray-200">
            <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Mesa</label>
            <select wire:change="selectTable($event.target.value)" class="mt-1 w-full max-w-xs rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-600 focus:ring-blue-600">
                <option value="">Seleccionar mesa...</option>
                @foreach ($tables as $table)
                    <option value="{{ $table->id }}" @selected($table->id === $selectedTableId)>
                        Mesa {{ $table->name }} &middot; {{ $table->occupancy_status === 'occupied' ? 'Ocupada' : 'Libre' }}
                    </option>
                @endforeach
                @if ($tables->isEmpty())
                    <option value="" disabled>No hay mesas activas en esta sucursal</option>
                @endif
            </select>
        </div>

        @if ($selectedTable)
            <div wire:key="order-simple-{{ $selectedTable->id }}">
                @include('livewire.dining.partials.order-builder')
            </div>
        @endif
    </div>

    {{-- Vista de mapa: plano de solo lectura a la izquierda; clic en una mesa abre su pedido en el panel de la derecha (debajo en pantallas chicas). --}}
    <div x-show="view === 'map'" class="space-y-5">
        <div class="grid grid-cols-1 gap-5 lg:grid-cols-[minmax(0,1fr)_380px] lg:items-start">
            <div class="rounded-xl bg-white p-4 ring-1 ring-gray-200">
                @if ($floorPlan)
                    <p class="mb-3 text-xs text-gray-500">Toca una mesa para abrir su pedido.</p>

                    <div class="relative mx-auto aspect-square w-full max-w-xl overflow-hidden rounded-lg bg-gray-50 ring-1 ring-inset ring-gray-200">
                        <svg viewBox="0 0 100 100" class="absolute inset-0 h-full w-full select-none" preserveAspectRatio="xMidYMid meet">
                            <polygon points="{{ collect($floorPlan->outline_points)->map(fn ($p) => $p['x'].','.$p['y'])->implode(' ') }}"
                                fill="#2563eb14" stroke="#2563eb" stroke-width="0.6"></polygon>

                            @foreach ($tables as $table)
                                @php($chairCount = $table->capacity && $table->capacity > 0 ? min((int) $table->capacity, 12) : 4)
                                @php($chairDistance = (float) ($table->size ?? 8) / 2)
                                @for ($i = 0; $i < $chairCount; $i++)
                                    @php($angle = (2 * M_PI / $chairCount) * $i - M_PI / 2)
                                    @php($chairX = ($table->pos_x ?? 50) + cos($angle) * $chairDistance)
                                    @php($chairY = ($table->pos_y ?? 50) + sin($angle) * $chairDistance)
                                    <circle cx="{{ $chairX }}" cy="{{ $chairY }}" r="1.8" fill="#bfdbfe" stroke="#60a5fa" stroke-width="0.3"></circle>
                                @endfor
                            @endforeach

                            @foreach ($obstacles as $obstacle)
                                <rect x="{{ (float) $obstacle->pos_x - (float) $obstacle->width / 2 }}"
                                    y="{{ (float) $obstacle->pos_y - (float) $obstacle->height / 2 }}"
                                    width="{{ (float) $obstacle->width }}" height="{{ (float) $obstacle->height }}" rx="0.5"
                                    fill="{{ $obstacleColor }}" stroke="#0a0a0a" stroke-width="0.3"></rect>
                            @endforeach

                            @foreach ($placedCashRegisters as $register)
                                @php($registerHalf = (float) $register->size / 2)
                                <g transform="translate({{ $register->pos_x }}, {{ $register->pos_y }})">
                                    <rect x="{{ -$registerHalf }}" y="{{ -$registerHalf }}" width="{{ $register->size }}" height="{{ $register->size }}" rx="1.5" fill="#1e293b" stroke="#ffffff" stroke-width="0.6"></rect>
                                    <text text-anchor="middle" dominant-baseline="central" font-size="{{ $registerHalf }}" fill="#ffffff" font-weight="bold">$</text>
                                    <title>{{ $register->name }}</title>
                                </g>
                            @endforeach
                        </svg>

                        <div class="absolute inset-0">
                            @foreach ($tables as $table)
                                <button type="button" wire:click="selectTable({{ $table->id }})"
                                    x-on:click="$nextTick(() => window.innerWidth < 1024 && $refs.orderPanel?.scrollIntoView({ behavior: 'smooth', block: 'start' }))"
                                    title="Mesa {{ $table->name }}"
                                    @class([
                                        'absolute flex -translate-x-1/2 -translate-y-1/2 items-center justify-center border-2 font-black leading-none shadow-sm transition',
                                        'rounded-full' => $table->shape === 'round',
                                        'rounded-md' => $table->shape !== 'round',
                                        'border-blue-800 bg-blue-600 text-white ring-4 ring-blue-200' => $selectedTable?->id === $table->id,
                                        'border-amber-600 bg-amber-100 text-amber-800' => $selectedTable?->id !== $table->id && $table->occupancy_status === 'occupied',
                                        'border-blue-600 bg-white text-blue-900 hover:bg-blue-50' => $selectedTable?->id !== $table->id && $table->occupancy_status !== 'occupied',
                                    ])
                                    style="left: {{ (float) ($table->pos_x ?? 50) }}%; top: {{ (float) ($table->pos_y ?? 50) }}%; width: {{ (float) ($table->size ?? 8) }}%; height: {{ (float) ($table->size ?? 8) }}%; font-size: clamp(9px, 3.4vw, 18px);">
                                    {{ $table->name }}
                                </button>
                            @endforeach
                        </div>
                    </div>
                @else
                    <p class="py-10 text-center text-sm text-gray-400">
                        Esta sucursal todavia no tiene un plano configurado. Usa la vista de lista, o pide al dueño que dibuje el plano en Mesas y comandas &rarr; Plano del salon.
                    </p>
                @endif
            </div>

            <div x-ref="orderPanel" class="lg:sticky lg:top-4">
                @if ($selectedTable)
                    <div class="rounded-xl bg-white p-4 ring-1 ring-gray-200" wire:key="order-panel-{{ $selectedTable->id }}">
                        <div class="mb-3 flex items-center justify-between">
                            <h3 class="text-base font-black text-gray-900">Mesa {{ $selectedTable->name }}</h3>
                            <button type="button" wire:click="closeOrder" class="rounded-full p-1.5 text-gray-400 hover:bg-gray-200 hover:text-gray-700">
                                <x-heroicon-o-x-mark class="h-5 w-5" />
                            </button>
                        </div>

                        @include('livewire.dining.partials.order-builder')
                    </div>
                @else
                    <div class="hidden rounded-xl border-2 border-dashed border-gray-200 p-8 text-center text-sm text-gray-400 lg:block">
                        Toca una mesa en el plano para ver y editar su pedido aqui.
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
