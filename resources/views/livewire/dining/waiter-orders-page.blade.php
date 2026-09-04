<div x-data="{ view: 'simple' }" class="space-y-5">
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

    {{-- Vista de mapa: plano de solo lectura, clic en una mesa abre el modal con el mismo pedido. --}}
    <div x-show="view === 'map'" class="space-y-5">
        <div class="rounded-xl bg-white p-4 ring-1 ring-gray-200">
            @if ($floorPlan)
                <p class="mb-3 text-xs text-gray-500">Toca una mesa para abrir su pedido.</p>

                <div class="relative aspect-square w-full max-w-xl overflow-hidden rounded-lg bg-gray-50 ring-1 ring-inset ring-gray-200">
                    <svg viewBox="0 0 100 100" class="absolute inset-0 h-full w-full select-none" preserveAspectRatio="xMidYMid meet">
                        <polygon points="{{ collect($floorPlan->outline_points)->map(fn ($p) => $p['x'].','.$p['y'])->implode(' ') }}"
                            fill="#2563eb14" stroke="#2563eb" stroke-width="0.6"></polygon>
                    </svg>

                    <div class="absolute inset-0">
                        @foreach ($tables as $table)
                            <button type="button" wire:click="selectTable({{ $table->id }})"
                                title="Mesa {{ $table->name }}"
                                @class([
                                    'absolute flex -translate-x-1/2 -translate-y-1/2 items-center justify-center border-2 text-[11px] font-bold leading-none shadow-sm transition',
                                    'rounded-full' => $table->shape === 'round',
                                    'rounded-md' => $table->shape !== 'round',
                                    'border-blue-600 bg-blue-600 text-white ring-4 ring-blue-200' => $selectedTable?->id === $table->id,
                                    'border-amber-400 bg-amber-100 text-amber-800' => $selectedTable?->id !== $table->id && $table->occupancy_status === 'occupied',
                                    'border-gray-300 bg-white text-gray-600 hover:border-blue-400' => $selectedTable?->id !== $table->id && $table->occupancy_status !== 'occupied',
                                ])
                                style="left: {{ (float) ($table->pos_x ?? 50) }}%; top: {{ (float) ($table->pos_y ?? 50) }}%; width: {{ (float) ($table->size ?? 8) }}%; height: {{ (float) ($table->size ?? 8) }}%">
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

        @if ($selectedTable)
            <div class="fixed inset-0 z-40 flex items-center justify-center bg-black/40 p-4" wire:key="order-modal-{{ $selectedTable->id }}">
                <div class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-2xl bg-gray-50 p-5 shadow-2xl">
                    <div class="mb-3 flex items-center justify-between">
                        <h3 class="text-base font-black text-gray-900">Mesa {{ $selectedTable->name }}</h3>
                        <button type="button" wire:click="closeOrder" class="rounded-full p-1.5 text-gray-400 hover:bg-gray-200 hover:text-gray-700">
                            <x-heroicon-o-x-mark class="h-5 w-5" />
                        </button>
                    </div>

                    @include('livewire.dining.partials.order-builder')
                </div>
            </div>
        @endif
    </div>
</div>
