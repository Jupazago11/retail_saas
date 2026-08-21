<div class="pb-10">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-6">
        <x-admin-nav active="admin.audit-logs" />

        <div x-data="responsivePageSize({ rowHeight: 68, reserved: 400 })" class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-[0.22em] text-blue-700">Auditoria</p>
                    <h3 class="mt-1 text-2xl font-black text-gray-900">Eventos registrados</h3>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <input wire:model.live.debounce.300ms="search" type="text" placeholder="Buscar por accion, entidad, IP o actor"
                        class="w-64 rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600 text-sm">
                    <span class="text-sm text-gray-500">{{ $logs->total() }} {{ \Illuminate\Support\Str::plural('evento', $logs->total()) }}</span>
                    <a href="{{ $this->exportUrl() }}"
                        class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:border-stone-400">
                        Exportar CSV
                    </a>
                </div>
            </div>

            <div class="mt-4 flex flex-wrap items-center gap-3">
                <x-searchable-select
                    id="audit-filter-action"
                    model="action"
                    placeholder="Todas las acciones"
                    class="w-48"
                    live
                    :options="collect($actionOptions)->map(fn ($actionOption) => ['id' => $actionOption, 'label' => $this->actionLabel($actionOption)])"
                />

                <x-searchable-select
                    id="audit-filter-actor"
                    model="actorUserId"
                    placeholder="Todos los actores"
                    class="w-56"
                    live
                    :options="$actors->map(fn ($actor) => ['id' => $actor->id, 'label' => $actor->name.' · @'.$actor->username])"
                />

                <x-searchable-select
                    id="audit-filter-auditable-type"
                    model="auditableType"
                    placeholder="Todas las entidades"
                    class="w-48"
                    live
                    :options="collect($auditableTypeOptions)->map(fn ($typeOption) => ['id' => $typeOption, 'label' => $this->auditableTypeLabel($typeOption)])"
                />

                <input wire:model.live.debounce.300ms="auditableId" type="number" min="1" placeholder="ID entidad"
                    class="w-32 rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600 text-sm">

                <input wire:model.live="dateFrom" type="date" class="rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600 text-sm">
                <span class="text-sm text-gray-400">hasta</span>
                <input wire:model.live="dateTo" type="date" class="rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600 text-sm">

                <input wire:model.live.debounce.300ms="ipAddress" type="text" placeholder="IP"
                    class="w-32 rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600 text-sm">
            </div>

            <div class="mt-6 overflow-x-auto">
                <table class="min-w-full divide-y divide-stone-200 text-sm">
                    <thead>
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <th class="pb-2">Evento</th>
                            <th class="pb-2">Actor</th>
                            <th class="pb-2">Entidad</th>
                            <th class="pb-2">Momento</th>
                            <th class="pb-2 w-px whitespace-nowrap text-right">Detalle</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-100">
                        @forelse ($logs as $log)
                            <tr wire:key="audit-log-{{ $log->id }}" class="even:bg-gray-50">
                                <td class="py-2 align-middle">
                                    <p class="font-semibold text-gray-900">{{ $this->actionLabel($log->action) }}</p>
                                    <p class="mt-0.5 text-xs text-gray-400">IP: {{ $log->ip_address ?: 'No capturada' }}</p>
                                </td>
                                <td class="py-2 align-middle text-gray-600 text-xs">
                                    <p class="font-medium text-gray-900">{{ $log->actor?->name ?? 'Sistema' }}</p>
                                    <p>{{ $log->actor?->username ? '@'.$log->actor->username : 'N/A' }}</p>
                                </td>
                                <td class="py-2 align-middle text-gray-600 text-xs">
                                    <p>{{ $this->auditableTypeLabel($log->auditable_type) }}</p>
                                    <p>ID: {{ $log->auditable_id ?? 'N/A' }}</p>
                                </td>
                                <td class="py-2 align-middle text-gray-600 text-xs">
                                    {{ $log->created_at?->format('d/m/Y H:i:s') }}
                                </td>
                                <td class="py-2 align-middle w-px whitespace-nowrap text-right">
                                    <button wire:click="toggleLog({{ $log->id }})" class="text-sm font-semibold text-blue-700 hover:text-blue-900">
                                        {{ $expandedLogId === $log->id ? 'Ocultar' : 'Ver capturas' }}
                                    </button>
                                </td>
                            </tr>
                            @if ($expandedLogId === $log->id)
                                <tr wire:key="audit-log-{{ $log->id }}-detail">
                                    <td colspan="5" class="bg-gray-50 px-2 py-4">
                                        <div class="grid gap-4 lg:grid-cols-2">
                                            <div class="rounded-lg bg-white p-4 ring-1 ring-gray-200">
                                                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-gray-500">Antes</p>
                                                <pre class="mt-3 overflow-x-auto rounded-lg bg-stone-950 p-4 text-xs text-stone-100">{{ $this->snapshotJson($log->before_snapshot) }}</pre>
                                            </div>
                                            <div class="rounded-lg bg-white p-4 ring-1 ring-gray-200">
                                                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-gray-500">Despues</p>
                                                <pre class="mt-3 overflow-x-auto rounded-lg bg-stone-950 p-4 text-xs text-stone-100">{{ $this->snapshotJson($log->after_snapshot) }}</pre>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @endif
                        @empty
                            <tr>
                                <td colspan="5" class="py-10 text-center text-sm text-gray-500">
                                    No hay eventos de auditoria con el filtro actual.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($logs->hasPages())
                <div class="mt-4 border-t border-stone-100 pt-4">
                    {{ $logs->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
