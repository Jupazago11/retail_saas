<div class="pb-10">
    <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
        <x-admin-nav />

        <div class="grid gap-6 lg:grid-cols-4">
        <div class="space-y-6">
            <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                <p class="text-sm font-semibold uppercase tracking-[0.22em] text-blue-700">Snapshot</p>
                <h3 class="mt-2 text-2xl font-black text-gray-900">{{ $snapshot['plan']?->name ?? 'Sin plan' }}</h3>
                <p class="mt-2 text-sm text-gray-500">Modulos: {{ count($snapshot['modules']) }} · Features: {{ count($snapshot['features']) }} · Limites: {{ count($snapshot['limits']) }}</p>
            </div>

            <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-[0.22em] text-blue-700">Modulo</p>
                        <h3 class="mt-2 text-2xl font-black text-gray-900">{{ $editingModuleOverrideId ? 'Editar override' : 'Nuevo override' }}</h3>
                    </div>
                    @if ($editingModuleOverrideId)
                        <button type="button" wire:click="resetModuleForm" class="rounded-full border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700">Cancelar</button>
                    @endif
                </div>

                <form wire:submit="saveModuleOverride" class="mt-6 space-y-4">
                    <div>
                        <label class="text-sm font-medium text-gray-700">Modulo</label>
                        <select wire:model="moduleId" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600">
                            <option value="">Selecciona un modulo</option>
                            @foreach ($availableModules as $module)
                                <option value="{{ $module->id }}">{{ $module->name }} ({{ $module->code }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-700">Estado override</label>
                        <select wire:model="moduleEnabled" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600">
                            <option value="1">Habilitar</option>
                            <option value="0">Deshabilitar</option>
                        </select>
                    </div>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label class="text-sm font-medium text-gray-700">Inicio</label>
                            <input wire:model="moduleStartsAt" type="datetime-local" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600">
                        </div>
                        <div>
                            <label class="text-sm font-medium text-gray-700">Fin</label>
                            <input wire:model="moduleEndsAt" type="datetime-local" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600">
                        </div>
                    </div>
                    @error('moduleId') <p class="text-sm text-rose-600">{{ $message }}</p> @enderror
                    @error('moduleStartsAt') <p class="text-sm text-rose-600">{{ $message }}</p> @enderror
                    @error('moduleEndsAt') <p class="text-sm text-rose-600">{{ $message }}</p> @enderror
                    <button type="submit" class="inline-flex rounded-full bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white">Guardar override</button>
                </form>
            </div>

            <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-[0.22em] text-blue-700">Feature</p>
                        <h3 class="mt-2 text-2xl font-black text-gray-900">{{ $editingFeatureOverrideId ? 'Editar override' : 'Nuevo override' }}</h3>
                    </div>
                    @if ($editingFeatureOverrideId)
                        <button type="button" wire:click="resetFeatureForm" class="rounded-full border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700">Cancelar</button>
                    @endif
                </div>

                <form wire:submit="saveFeatureOverride" class="mt-6 space-y-4">
                    <div>
                        <label class="text-sm font-medium text-gray-700">Feature</label>
                        <select wire:model="featureId" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600">
                            <option value="">Selecciona una feature</option>
                            @foreach ($availableFeatures as $feature)
                                <option value="{{ $feature->id }}">{{ $feature->name }} ({{ $feature->code }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-700">Estado override</label>
                        <select wire:model="featureEnabled" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600">
                            <option value="1">Habilitar</option>
                            <option value="0">Deshabilitar</option>
                        </select>
                    </div>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label class="text-sm font-medium text-gray-700">Inicio</label>
                            <input wire:model="featureStartsAt" type="datetime-local" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600">
                        </div>
                        <div>
                            <label class="text-sm font-medium text-gray-700">Fin</label>
                            <input wire:model="featureEndsAt" type="datetime-local" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600">
                        </div>
                    </div>
                    @error('featureId') <p class="text-sm text-rose-600">{{ $message }}</p> @enderror
                    @error('featureStartsAt') <p class="text-sm text-rose-600">{{ $message }}</p> @enderror
                    @error('featureEndsAt') <p class="text-sm text-rose-600">{{ $message }}</p> @enderror
                    <button type="submit" class="inline-flex rounded-full bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white">Guardar override</button>
                </form>
            </div>

            <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-[0.22em] text-blue-700">Limite</p>
                        <h3 class="mt-2 text-2xl font-black text-gray-900">{{ $editingLimitOverrideId ? 'Editar override' : 'Nuevo override' }}</h3>
                    </div>
                    @if ($editingLimitOverrideId)
                        <button type="button" wire:click="resetLimitForm" class="rounded-full border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700">Cancelar</button>
                    @endif
                </div>

                <form wire:submit="saveLimitOverride" class="mt-6 space-y-4">
                    <div>
                        <label class="text-sm font-medium text-gray-700">Clave de limite</label>
                        <select wire:model="limitKey" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600">
                            <option value="">Selecciona un limite</option>
                            @foreach ($availableLimitKeys as $key)
                                <option value="{{ $key }}">{{ $key }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-700">Valor</label>
                        <input wire:model="limitValue" type="number" min="1" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600">
                    </div>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label class="text-sm font-medium text-gray-700">Inicio</label>
                            <input wire:model="limitStartsAt" type="datetime-local" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600">
                        </div>
                        <div>
                            <label class="text-sm font-medium text-gray-700">Fin</label>
                            <input wire:model="limitEndsAt" type="datetime-local" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600">
                        </div>
                    </div>
                    @error('limitKey') <p class="text-sm text-rose-600">{{ $message }}</p> @enderror
                    @error('limitValue') <p class="text-sm text-rose-600">{{ $message }}</p> @enderror
                    @error('limitStartsAt') <p class="text-sm text-rose-600">{{ $message }}</p> @enderror
                    @error('limitEndsAt') <p class="text-sm text-rose-600">{{ $message }}</p> @enderror
                    <button type="submit" class="inline-flex rounded-full bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white">Guardar override</button>
                </form>
            </div>
        </div>

        <div class="space-y-6 lg:col-span-3 lg:col-start-2">
            <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                <div class="flex items-end justify-between gap-4">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-[0.22em] text-blue-700">Modulos</p>
                        <h3 class="mt-2 text-2xl font-black text-gray-900">Overrides registrados</h3>
                    </div>
                    <p class="text-sm text-gray-500">{{ $moduleOverrides->count() }} registros</p>
                </div>
                <div class="mt-6 overflow-x-auto">
                    <table class="min-w-full divide-y divide-stone-200 text-sm">
                        <thead>
                            <tr class="text-left text-gray-500">
                                <th class="pb-3 font-medium">Modulo</th>
                                <th class="pb-3 font-medium">Estado</th>
                                <th class="pb-3 font-medium">Inicio</th>
                                <th class="pb-3 font-medium">Fin</th>
                                <th class="pb-3 font-medium">Accion</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-stone-100">
                            @forelse ($moduleOverrides as $override)
                                <tr>
                                    <td class="py-4 font-medium text-gray-900">{{ $override->module?->name ?? '-' }}</td>
                                    <td class="py-4 text-gray-600">{{ $override->enabled ? 'Habilitado' : 'Deshabilitado' }}</td>
                                    <td class="py-4 text-gray-600">{{ optional($override->starts_at)?->format('Y-m-d H:i') ?? '-' }}</td>
                                    <td class="py-4 text-gray-600">{{ optional($override->ends_at)?->format('Y-m-d H:i') ?? '-' }}</td>
                                    <td class="py-4"><button type="button" wire:click="startEditingModuleOverride({{ $override->id }})" class="rounded-full border border-gray-300 px-3 py-1 text-xs font-semibold text-gray-700">Editar</button></td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="py-6 text-center text-gray-500">Sin overrides de modulo.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                <div class="flex items-end justify-between gap-4">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-[0.22em] text-blue-700">Features</p>
                        <h3 class="mt-2 text-2xl font-black text-gray-900">Overrides registrados</h3>
                    </div>
                    <p class="text-sm text-gray-500">{{ $featureOverrides->count() }} registros</p>
                </div>
                <div class="mt-6 overflow-x-auto">
                    <table class="min-w-full divide-y divide-stone-200 text-sm">
                        <thead>
                            <tr class="text-left text-gray-500">
                                <th class="pb-3 font-medium">Feature</th>
                                <th class="pb-3 font-medium">Estado</th>
                                <th class="pb-3 font-medium">Inicio</th>
                                <th class="pb-3 font-medium">Fin</th>
                                <th class="pb-3 font-medium">Accion</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-stone-100">
                            @forelse ($featureOverrides as $override)
                                <tr>
                                    <td class="py-4 font-medium text-gray-900">{{ $override->feature?->name ?? '-' }}</td>
                                    <td class="py-4 text-gray-600">{{ $override->enabled ? 'Habilitada' : 'Deshabilitada' }}</td>
                                    <td class="py-4 text-gray-600">{{ optional($override->starts_at)?->format('Y-m-d H:i') ?? '-' }}</td>
                                    <td class="py-4 text-gray-600">{{ optional($override->ends_at)?->format('Y-m-d H:i') ?? '-' }}</td>
                                    <td class="py-4"><button type="button" wire:click="startEditingFeatureOverride({{ $override->id }})" class="rounded-full border border-gray-300 px-3 py-1 text-xs font-semibold text-gray-700">Editar</button></td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="py-6 text-center text-gray-500">Sin overrides de feature.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                <div class="flex items-end justify-between gap-4">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-[0.22em] text-blue-700">Limites</p>
                        <h3 class="mt-2 text-2xl font-black text-gray-900">Overrides registrados</h3>
                    </div>
                    <p class="text-sm text-gray-500">{{ $limitOverrides->count() }} registros</p>
                </div>
                <div class="mt-6 overflow-x-auto">
                    <table class="min-w-full divide-y divide-stone-200 text-sm">
                        <thead>
                            <tr class="text-left text-gray-500">
                                <th class="pb-3 font-medium">Clave</th>
                                <th class="pb-3 font-medium">Valor</th>
                                <th class="pb-3 font-medium">Inicio</th>
                                <th class="pb-3 font-medium">Fin</th>
                                <th class="pb-3 font-medium">Accion</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-stone-100">
                            @forelse ($limitOverrides as $override)
                                <tr>
                                    <td class="py-4 font-medium text-gray-900">{{ $override->limit_key }}</td>
                                    <td class="py-4 text-gray-600">{{ $override->limit_value }}</td>
                                    <td class="py-4 text-gray-600">{{ optional($override->starts_at)?->format('Y-m-d H:i') ?? '-' }}</td>
                                    <td class="py-4 text-gray-600">{{ optional($override->ends_at)?->format('Y-m-d H:i') ?? '-' }}</td>
                                    <td class="py-4"><button type="button" wire:click="startEditingLimitOverride({{ $override->id }})" class="rounded-full border border-gray-300 px-3 py-1 text-xs font-semibold text-gray-700">Editar</button></td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="py-6 text-center text-gray-500">Sin overrides de limite.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        </div>
    </div>
</div>
