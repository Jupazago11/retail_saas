<div>
    {{-- Livewire needs a single root element. "space-y-*" only wraps the normal
         content below so it never adds margin-top to the modal (a sibling of
         this div, not a child of it), which used to push the "fixed inset-0"
         overlay down from the real top of the viewport. --}}
    <div class="space-y-6">

        {{-- Header --}}
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.22em] text-blue-600">Plataforma</p>
            <h1 class="mt-1 text-2xl font-black text-gray-900">Planes</h1>
        </div>

        {{-- Grid de planes --}}
        <div class="grid gap-5 lg:grid-cols-3">
            @foreach ($plans as $plan)
                <div class="flex flex-col rounded-xl bg-white p-6 ring-1 ring-gray-200">
                    {{-- Cabecera del plan --}}
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-blue-600">{{ $plan->billing_period }}</p>
                            <h2 class="mt-0.5 text-xl font-black text-gray-900">{{ $plan->name }}</h2>
                            <p class="mt-1 text-2xl font-black text-gray-900">
                                {{ number_format($plan->base_price, 0, ',', '.') }}
                                <span class="text-sm font-normal text-gray-400">COP</span>
                            </p>
                        </div>
                        @if ($plan->status === 'active')
                            <x-status-badge color="emerald">activo</x-status-badge>
                        @else
                            <x-status-badge color="stone">inactivo</x-status-badge>
                        @endif
                    </div>

                    {{-- Módulos --}}
                    <div class="mt-5">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Módulos incluidos</p>
                        <div class="mt-2 flex flex-wrap gap-1.5">
                            @foreach ($plan->modules->where('pivot.enabled', true) as $module)
                                <span class="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-600">{{ $module->name }}</span>
                            @endforeach
                        </div>
                    </div>

                    {{-- Límites --}}
                    @if ($plan->limits->isNotEmpty())
                        <div class="mt-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Límites</p>
                            <ul class="mt-2 space-y-1">
                                @foreach ($plan->limits->take(5) as $limit)
                                    <li class="flex justify-between text-xs text-gray-600">
                                        <span class="text-gray-400">{{ str_replace('_', ' ', $limit->limit_key) }}</span>
                                        <span class="font-semibold">{{ number_format($limit->limit_value) }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    {{-- Acción --}}
                    <div class="mt-auto pt-5">
                        <button wire:click="startEdit({{ $plan->id }})"
                            class="w-full rounded-full border border-gray-300 py-2 text-sm font-semibold text-gray-700 transition hover:border-blue-400 hover:text-blue-700">
                            Editar plan
                        </button>
                    </div>
                </div>
            @endforeach
        </div>

    </div>

    {{-- Panel de edición completa del plan (drawer lateral: el formulario es
         demasiado grande para el modal chico centrado que usa el resto de la
         plataforma; ver AGENTS.md "Modal para formularios pequeños y drawer
         para medianos o grandes"). --}}
    @if ($showModal)
        <div class="fixed inset-0 z-50 bg-black/40" wire:click="closeModal"></div>

        <div class="fixed inset-y-0 right-0 z-50 flex w-full max-w-2xl flex-col bg-white shadow-2xl">
            <div class="flex items-center justify-between border-b border-stone-100 px-6 py-5">
                <h3 class="text-lg font-black text-gray-900">Editar plan</h3>
                <button wire:click="closeModal" class="rounded-full p-1.5 text-gray-400 transition hover:bg-gray-100 hover:text-gray-700">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <div class="flex-1 overflow-y-auto px-6 py-6 space-y-8">

                {{-- Datos básicos --}}
                <div class="space-y-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Datos básicos</p>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Nombre <span class="text-rose-600">*</span></label>
                        <input wire:model="editName" type="text"
                            class="mt-1 w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-600 focus:ring-blue-600">
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Precio base (COP) <span class="text-rose-600">*</span></label>
                            <input wire:model="editPrice" type="number" min="0" step="1000"
                                class="mt-1 w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-600 focus:ring-blue-600">
                        </div>

                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Período de facturación <span class="text-rose-600">*</span></label>
                            <select wire:model="editPeriod"
                                class="mt-1 w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-600 focus:ring-blue-600">
                                <option value="monthly">Mensual</option>
                                <option value="yearly">Anual</option>
                                <option value="one_time">Único</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Estado <span class="text-rose-600">*</span></label>
                        <select wire:model="editStatus"
                            class="mt-1 w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-600 focus:ring-blue-600">
                            <option value="active">Activo</option>
                            <option value="inactive">Inactivo</option>
                        </select>
                    </div>
                </div>

                {{-- Módulos y features --}}
                <div class="space-y-3 border-t border-stone-100 pt-6">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Módulos y funcionalidades incluidas</p>

                    <div class="space-y-2">
                        @foreach ($modules as $module)
                            @php($moduleEnabled = in_array($module->id, $editModuleIds, true))
                            <div class="rounded-lg ring-1 ring-gray-200 {{ $moduleEnabled ? 'bg-white' : 'bg-gray-50' }}">
                                <label class="flex cursor-pointer items-center gap-3 px-4 py-3">
                                    <input type="checkbox" wire:click="toggleModule({{ $module->id }})" @checked($moduleEnabled)
                                        class="rounded border-gray-300 text-blue-600 focus:ring-blue-600">
                                    <span class="text-sm font-semibold text-gray-800">{{ $module->name }}</span>
                                </label>

                                @if ($module->features->isNotEmpty())
                                    <div class="grid grid-cols-1 gap-1.5 border-t border-stone-100 px-4 py-3 pl-11 sm:grid-cols-2">
                                        @foreach ($module->features as $feature)
                                            @php($featureEnabled = in_array($feature->id, $editFeatureIds, true))
                                            <label class="flex items-center gap-2 text-xs {{ $moduleEnabled ? 'text-gray-600' : 'text-stone-300' }}">
                                                <input type="checkbox" wire:click="toggleFeature({{ $feature->id }})"
                                                    @checked($featureEnabled) @disabled(! $moduleEnabled)
                                                    class="rounded border-gray-300 text-blue-600 focus:ring-blue-600 disabled:opacity-50">
                                                {{ $feature->name }}
                                            </label>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Límites --}}
                <div class="space-y-3 border-t border-stone-100 pt-6">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Límites</p>

                    <div class="grid grid-cols-2 gap-4">
                        @foreach ($limitDefinitions as $key => $label)
                            <div>
                                <label class="block text-xs font-semibold text-gray-500">{{ $label }} <span class="text-rose-600">*</span></label>
                                <input wire:model="editLimits.{{ $key }}" type="number" min="0" step="1"
                                    class="mt-1 w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-600 focus:ring-blue-600">
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-3 border-t border-stone-100 px-6 py-4">
                <button wire:click="closeModal"
                    class="rounded-full border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-600 transition hover:bg-gray-50">
                    Cancelar
                </button>
                <button wire:click="saveEdit"
                    class="rounded-full bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-700">
                    Guardar
                </button>
            </div>
        </div>
    @endif
</div>
