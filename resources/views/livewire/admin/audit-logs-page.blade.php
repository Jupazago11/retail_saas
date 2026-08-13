<div class="pb-10">
    <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
        <x-admin-nav active="admin.audit-logs" />

        <div class="grid gap-6 lg:grid-cols-[0.85fr_1.15fr]">
        <div class="space-y-6">
            <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-[0.22em] text-blue-700">Filtros</p>
                    <h3 class="mt-2 text-2xl font-black text-gray-900">Consulta operativa</h3>
                </div>

                <div class="mt-6 grid gap-4 md:grid-cols-2">
                    <div class="md:col-span-2">
                        <label for="audit-search" class="text-sm font-medium text-gray-700">Busqueda general</label>
                        <input wire:model.live.debounce.300ms="search" id="audit-search" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600">
                    </div>

                    <div class="md:col-span-2">
                        <label for="audit-action" class="text-sm font-medium text-gray-700">Accion</label>
                        <select wire:model.live="action" id="audit-action" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600">
                            <option value="">Todas</option>
                            @foreach ($actionOptions as $actionOption)
                                <option value="{{ $actionOption }}">{{ $actionOption }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="audit-actor" class="text-sm font-medium text-gray-700">Actor</label>
                        <select wire:model.live="actorUserId" id="audit-actor" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600">
                            <option value="">Todos</option>
                            @foreach ($actors as $actor)
                                <option value="{{ $actor->id }}">{{ $actor->name }} · {{ '@'.$actor->username }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="audit-type" class="text-sm font-medium text-gray-700">Entidad</label>
                        <select wire:model.live="auditableType" id="audit-type" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600">
                            <option value="">Todas</option>
                            @foreach ($auditableTypeOptions as $typeOption)
                                <option value="{{ $typeOption }}">{{ $this->auditableTypeLabel($typeOption) }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="audit-id" class="text-sm font-medium text-gray-700">ID entidad</label>
                        <input wire:model.live.debounce.300ms="auditableId" id="audit-id" type="number" min="1" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600">
                    </div>

                    <div>
                        <label for="audit-date-from" class="text-sm font-medium text-gray-700">Fecha desde</label>
                        <input wire:model.live="dateFrom" id="audit-date-from" type="date" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600">
                    </div>

                    <div>
                        <label for="audit-date-to" class="text-sm font-medium text-gray-700">Fecha hasta</label>
                        <input wire:model.live="dateTo" id="audit-date-to" type="date" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600">
                    </div>

                    <div class="md:col-span-2">
                        <label for="audit-ip" class="text-sm font-medium text-gray-700">IP</label>
                        <input wire:model.live.debounce.300ms="ipAddress" id="audit-ip" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600">
                    </div>
                </div>
            </div>

            <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                <div class="flex items-end justify-between gap-4">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-[0.22em] text-blue-700">Resumen</p>
                        <h3 class="mt-2 text-2xl font-black text-gray-900">Lectura rapida</h3>
                    </div>
                    <div class="flex items-center gap-3">
                        <p class="text-sm text-gray-500">{{ $logs->count() }} eventos filtrados</p>
                        <a href="{{ $this->exportUrl() }}" class="rounded-full border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700">
                            Exportar CSV
                        </a>
                    </div>
                </div>

                <div class="mt-6 grid gap-3 sm:grid-cols-2">
                    <div class="rounded-lg bg-blue-600 px-4 py-4 text-white">
                        <p class="text-xs uppercase tracking-[0.18em] text-stone-300">Evento mas reciente</p>
                        <p class="mt-1 text-sm font-semibold">{{ $logs->first()?->action ?? 'Sin eventos' }}</p>
                    </div>
                    <div class="rounded-lg bg-gray-50 px-4 py-4 ring-1 ring-gray-200">
                        <p class="text-xs uppercase tracking-[0.18em] text-gray-500">Actores visibles</p>
                        <p class="mt-1 text-sm font-semibold text-gray-900">{{ $logs->pluck('actor_user_id')->filter()->unique()->count() }}</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.22em] text-blue-700">Eventos</p>
                <h3 class="mt-2 text-2xl font-black text-gray-900">Timeline de auditoria</h3>
            </div>

            <div class="mt-6 space-y-4">
                @forelse ($logs as $log)
                    <div wire:key="audit-log-{{ $log->id }}" class="rounded-xl border border-gray-200 bg-gray-50 p-5">
                        <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                            <div class="space-y-3">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h4 class="text-lg font-black text-gray-900">{{ $log->action }}</h4>
                                    <span class="rounded-full bg-stone-200 px-3 py-1 text-xs font-semibold text-gray-700">
                                        {{ $this->auditableTypeLabel($log->auditable_type) }}
                                    </span>
                                </div>

                                <div class="grid gap-2 text-sm text-gray-600 md:grid-cols-2">
                                    <p>Actor: <span class="font-medium text-gray-900">{{ $log->actor?->name ?? 'Sistema' }}</span></p>
                                    <p>Usuario: <span class="font-medium text-gray-900">{{ $log->actor?->username ? '@'.$log->actor->username : 'N/A' }}</span></p>
                                    <p>Entidad ID: <span class="font-medium text-gray-900">{{ $log->auditable_id ?? 'N/A' }}</span></p>
                                    <p>Momento: <span class="font-medium text-gray-900">{{ $log->created_at?->format('Y-m-d H:i:s') }}</span></p>
                                </div>
                            </div>

                            <div class="min-w-[220px] space-y-3">
                                <div class="rounded-lg bg-white p-4 ring-1 ring-gray-200">
                                    <p class="text-xs uppercase tracking-[0.18em] text-gray-500">IP</p>
                                    <p class="mt-1 text-sm font-semibold text-gray-900">{{ $log->ip_address ?: 'No capturada' }}</p>
                                </div>

                                <button wire:click="toggleLog({{ $log->id }})" class="rounded-full border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700">
                                    {{ $expandedLogId === $log->id ? 'Ocultar capturas' : 'Ver capturas' }}
                                </button>
                            </div>
                        </div>

                        @if ($expandedLogId === $log->id)
                            <div class="mt-5 grid gap-4 lg:grid-cols-2">
                                <div class="rounded-lg bg-white p-4 ring-1 ring-gray-200">
                                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-gray-500">Antes</p>
                                    <pre class="mt-3 overflow-x-auto rounded-lg bg-stone-950 p-4 text-xs text-stone-100">{{ $this->snapshotJson($log->before_snapshot) }}</pre>
                                </div>

                                <div class="rounded-lg bg-white p-4 ring-1 ring-gray-200">
                                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-gray-500">Despues</p>
                                    <pre class="mt-3 overflow-x-auto rounded-lg bg-stone-950 p-4 text-xs text-stone-100">{{ $this->snapshotJson($log->after_snapshot) }}</pre>
                                </div>
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="rounded-xl border border-dashed border-gray-300 bg-gray-50 p-8 text-center text-gray-500">
                        No hay eventos de auditoria con el filtro actual.
                    </div>
                @endforelse
            </div>
        </div>
        </div>
    </div>
</div>
