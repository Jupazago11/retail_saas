<div wire:poll.5s class="space-y-6">
    <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
        @forelse ($groupedItems as $tableName => $items)
            <div class="rounded-xl bg-white p-5 ring-1 ring-gray-200">
                <p class="text-sm font-black text-gray-900">{{ $tableName }}</p>

                <div class="mt-3 space-y-2.5">
                    @foreach ($items as $item)
                        @php($minutes = $this->elapsedMinutes($item))
                        <div @class([
                            'rounded-lg p-3',
                            'bg-amber-50 ring-1 ring-amber-300' => $item->is_modified && $item->kitchen_status !== 'cancelled',
                            'bg-rose-50 ring-1 ring-rose-200' => $item->kitchen_status === 'cancelled',
                            'bg-gray-50' => ! $item->is_modified && $item->kitchen_status !== 'cancelled',
                        ])>
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <span class="text-sm font-semibold text-gray-800">{{ $item->quantity + 0 }}x {{ $item->product->name }}</span>
                                    @if ($item->is_modified && $item->kitchen_status !== 'cancelled')
                                        <span class="ml-1 inline-flex items-center gap-0.5 text-[10px] font-semibold uppercase text-amber-700" title="Este plato se modifico despues de creado">
                                            <x-heroicon-o-exclamation-triangle class="h-3 w-3" />
                                            Novedad
                                        </span>
                                    @endif
                                    @if ($item->notes)
                                        <p class="mt-0.5 text-xs font-semibold text-rose-700">"{{ $item->notes }}"</p>
                                    @endif
                                    <p class="text-[11px] text-gray-400">
                                        {{ $item->creator?->name ?? 'Sin registrar' }}
                                    </p>
                                </div>
                                <span class="inline-flex shrink-0 items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $this->urgencyClasses($minutes) }}">
                                    <x-heroicon-o-clock class="h-3 w-3" />
                                    {{ $this->elapsedLabel($minutes) }}
                                </span>
                            </div>

                            <span class="mt-1 inline-block rounded-full px-2 py-0.5 text-[11px] font-medium {{ $this->statusClasses($item->kitchen_status) }}">
                                {{ $this->statusLabel($item->kitchen_status) }}
                            </span>

                            @if ($item->kitchen_status === 'cancelled')
                                <button wire:click="dismiss({{ $item->id }})"
                                    class="mt-2 w-full rounded-full border border-rose-300 py-1.5 text-xs font-semibold text-rose-700 transition hover:bg-rose-100">
                                    Descartar
                                </button>
                            @else
                                <div class="mt-2 flex gap-1.5">
                                    @foreach ($this->availableActions($item) as $action)
                                        <button wire:click="advance({{ $item->id }}, '{{ $action['status'] }}')"
                                            class="flex-1 rounded-full border border-gray-300 py-1.5 text-xs font-semibold text-gray-700 transition hover:border-blue-400 hover:text-blue-700">
                                            {{ $action['label'] }}
                                        </button>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @empty
            <div class="col-span-full rounded-xl bg-white p-10 text-center text-sm text-gray-400 ring-1 ring-gray-200">
                No hay platos pendientes en este momento.
            </div>
        @endforelse
    </div>
</div>
