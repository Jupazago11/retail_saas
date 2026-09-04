<div class="space-y-6">
    <div class="flex items-center justify-between">
        <p class="text-sm text-gray-500">{{ $tables->count() }} mesa(s) activa(s).</p>
        <div class="flex items-center gap-2">
            @if ($isOwner)
                <a href="{{ route('dining.floor-plan') }}" wire:navigate
                    class="rounded-full border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-50">
                    Editar plano
                </a>
            @endif
            <button wire:click="startCreate"
                class="rounded-full bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-700">
                + Nueva mesa
            </button>
        </div>
    </div>

    @forelse ($tables->groupBy('branch_id') as $branchId => $branchTables)
        @php($floorPlan = $floorPlans->get($branchId))
        <div>
            @if ($branches->count() > 1)
                <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-400">{{ $branchTables->first()->branch->name }}</p>
            @endif

            @if ($floorPlan)
                <div class="relative mx-auto aspect-square w-full max-w-xl overflow-hidden rounded-lg bg-gray-50 ring-1 ring-inset ring-gray-200">
                    <svg viewBox="0 0 100 100" class="h-full w-full" preserveAspectRatio="xMidYMid meet">
                        <polygon points="{{ collect($floorPlan->outline_points)->map(fn ($p) => $p['x'].','.$p['y'])->implode(' ') }}"
                            fill="#2563eb14" stroke="#2563eb" stroke-width="0.6"></polygon>

                        @foreach ($branchTables as $table)
                            @php($chairCount = $table->capacity && $table->capacity > 0 ? min((int) $table->capacity, 12) : 4)
                            @php($chairDistance = (float) $table->size / 2)
                            @for ($i = 0; $i < $chairCount; $i++)
                                @php($angle = (2 * M_PI / $chairCount) * $i - M_PI / 2)
                                @php($chairX = ($table->pos_x ?? 50) + cos($angle) * $chairDistance)
                                @php($chairY = ($table->pos_y ?? 50) + sin($angle) * $chairDistance)
                                <circle cx="{{ $chairX }}" cy="{{ $chairY }}" r="1.8" fill="#bfdbfe" stroke="#60a5fa" stroke-width="0.3"></circle>
                            @endfor
                        @endforeach

                        @foreach ($branchTables as $table)
                            @php($half = (float) $table->size / 2)
                            <a href="{{ route('dining.tables.order', $table) }}" wire:navigate>
                                <g transform="translate({{ $table->pos_x ?? 50 }}, {{ $table->pos_y ?? 50 }})" class="cursor-pointer">
                                    @if ($table->shape === 'round')
                                        <circle r="{{ $half }}" fill="{{ $table->occupancy_status === 'occupied' ? '#fef3c7' : '#ffffff' }}"
                                            stroke="{{ $table->occupancy_status === 'occupied' ? '#d97706' : '#2563eb' }}" stroke-width="0.6"></circle>
                                    @else
                                        <rect x="{{ -$half }}" y="{{ -$half }}" width="{{ $table->size }}" height="{{ $table->size }}" rx="1"
                                            fill="{{ $table->occupancy_status === 'occupied' ? '#fef3c7' : '#ffffff' }}"
                                            stroke="{{ $table->occupancy_status === 'occupied' ? '#d97706' : '#2563eb' }}" stroke-width="0.6"></rect>
                                    @endif
                                    <text text-anchor="middle" dominant-baseline="central" font-size="3.4" fill="#1e3a8a" font-weight="bold">{{ $table->name }}</text>
                                </g>
                            </a>
                        @endforeach

                        @foreach ($obstacles->get($branchId, collect()) as $obstacle)
                            <rect x="{{ (float) $obstacle->pos_x - (float) $obstacle->width / 2 }}"
                                y="{{ (float) $obstacle->pos_y - (float) $obstacle->height / 2 }}"
                                width="{{ (float) $obstacle->width }}" height="{{ (float) $obstacle->height }}" rx="0.5"
                                fill="{{ $obstacleColor }}" stroke="#0a0a0a" stroke-width="0.3"></rect>
                        @endforeach

                        @foreach ($placedCashRegisters->get($branchId, collect()) as $register)
                            @php($registerHalf = (float) $register->size / 2)
                            <g transform="translate({{ $register->pos_x }}, {{ $register->pos_y }})">
                                <rect x="{{ -$registerHalf }}" y="{{ -$registerHalf }}" width="{{ $register->size }}" height="{{ $register->size }}" rx="1.5" fill="#1e293b" stroke="#ffffff" stroke-width="0.6"></rect>
                                <text text-anchor="middle" dominant-baseline="central" font-size="{{ $registerHalf }}" fill="#ffffff" font-weight="bold">$</text>
                                <title>{{ $register->name }}</title>
                            </g>
                        @endforeach
                    </svg>
                </div>
            @else
                <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
                    @foreach ($branchTables as $table)
                        @php($openOrder = $table->frozenSales->first())
                        <a href="{{ route('dining.tables.order', $table) }}" wire:navigate
                            class="flex flex-col rounded-xl p-5 text-center ring-1 transition hover:shadow-md {{ $table->occupancy_status === 'occupied' ? 'bg-amber-50 ring-amber-300' : 'bg-white ring-gray-200' }}">
                            <span class="text-lg font-black text-gray-900">{{ $table->name }}</span>
                            <span class="mt-1 text-xs text-gray-400">{{ $table->branch->name }}</span>
                            @if ($table->capacity)
                                <span class="mt-1 text-xs text-gray-400">{{ $table->capacity }} puestos</span>
                            @endif

                            <span class="mt-3 inline-flex justify-center rounded-full px-3 py-1 text-xs font-semibold {{ $table->occupancy_status === 'occupied' ? 'bg-amber-100 text-amber-700' : 'bg-emerald-50 text-emerald-700' }}">
                                {{ $table->occupancy_status === 'occupied' ? 'Ocupada' : 'Libre' }}
                            </span>

                            @if ($openOrder)
                                <span class="mt-2 text-xs font-semibold text-gray-600">
                                    {{ number_format((float) ($openOrder->payload_snapshot['totals']['grand_total'] ?? 0), 0, ',', '.') }} COP
                                </span>
                            @endif
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    @empty
        <div class="rounded-xl bg-white p-10 text-center text-sm text-gray-400 ring-1 ring-gray-200">
            Todavia no hay mesas creadas. Crea la primera con "+ Nueva mesa".
        </div>
    @endforelse

    @if ($showModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" wire:click.self="closeModal">
            <div class="w-full max-w-sm rounded-xl bg-white p-6 shadow-xl ring-1 ring-gray-200">
                <h3 class="text-lg font-black text-gray-900">{{ $editingId ? 'Editar mesa' : 'Nueva mesa' }}</h3>

                <div class="mt-5 space-y-4">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Sucursal <span class="text-rose-600">*</span></label>
                        <select wire:model="branchId" class="mt-1 w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-600 focus:ring-blue-600">
                            @foreach ($branches as $branch)
                                <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                            @endforeach
                        </select>
                        @error('branchId') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>

                    @if ($editingId)
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Numero <span class="text-rose-600">*</span></label>
                            <input wire:model="name" type="text" placeholder="1"
                                class="mt-1 w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-600 focus:ring-blue-600">
                            @error('name') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                        </div>
                    @else
                        <p class="text-xs text-gray-400">El numero de la mesa se asigna automaticamente (el siguiente disponible).</p>
                    @endif

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Capacidad</label>
                        <input wire:model="capacity" type="number" min="1" placeholder="4"
                            class="mt-1 w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-600 focus:ring-blue-600">
                        @error('capacity') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <button wire:click="closeModal"
                        class="rounded-full border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-600 transition hover:bg-gray-50">
                        Cancelar
                    </button>
                    <button wire:click="save"
                        class="rounded-full bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-700">
                        Guardar
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
