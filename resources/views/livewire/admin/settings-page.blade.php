<div class="pb-10">
    <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
        <x-admin-nav active="admin.settings" />

        <div
            x-data="{ active: '{{ array_key_first($groups) }}' }"
            class="max-w-3xl rounded-xl bg-white shadow-sm ring-1 ring-gray-200 overflow-hidden">

            {{-- Header --}}
            <div class="flex items-center justify-between border-b border-stone-100 px-6 py-5">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.22em] text-blue-700">Configuracion</p>
                    <h3 class="mt-1 text-2xl font-black text-gray-900">Empresa activa</h3>
                </div>
                <button wire:click="saveSettings"
                    class="rounded-full bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700">
                    Guardar cambios
                </button>
            </div>

            {{-- Step tabs: solo tiene sentido elegir cuando hay mas de un grupo disponible --}}
            @if (count($groups) > 1)
                <div class="flex flex-wrap gap-1.5 border-b border-stone-100 px-6 py-4">
                    @foreach ($groups as $group => $definitions)
                        <button type="button"
                            @click="active = '{{ $group }}'"
                            :class="active === '{{ $group }}'
                                ? 'bg-blue-600 text-white shadow-sm'
                                : 'bg-gray-100 text-gray-600 hover:bg-stone-200'"
                            class="rounded-full px-4 py-2 text-sm font-semibold transition">
                            {{ $this->groupLabel($group) }}
                        </button>
                    @endforeach
                </div>
            @endif

            {{-- Step content --}}
            <div class="px-6 py-6">
                @foreach ($groups as $group => $definitions)
                    <div x-show="active === '{{ $group }}'" x-cloak>
                        <div class="grid gap-5 sm:grid-cols-2">
                            @foreach ($definitions as $definition)
                                @php
                                    $path    = "settings.{$group}.{$definition['key']}";
                                    $options = $this->selectOptions($group, $definition['key']);
                                    $label   = $this->fieldLabel($definition['key']);
                                    $current = $settings[$group][$definition['key']] ?? $definition['default'];
                                @endphp

                                @if ($definition['type'] === 'boolean')
                                    {{-- Toggle row — full width --}}
                                    <label class="col-span-2 flex cursor-pointer items-center justify-between rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 transition hover:border-blue-300 hover:bg-blue-50/40">
                                        <span class="text-sm font-medium text-gray-700">{{ $label }}</span>
                                        <div class="relative ml-4 flex-shrink-0">
                                            <input wire:model.live="{{ $path }}" type="checkbox"
                                                @checked((bool) $current)
                                                class="peer sr-only">
                                            <div class="h-6 w-11 rounded-full bg-stone-300 transition peer-checked:bg-amber-600 peer-focus:ring-2 peer-focus:ring-amber-400 peer-focus:ring-offset-1"></div>
                                            <div class="absolute left-0.5 top-0.5 h-5 w-5 rounded-full bg-white shadow transition peer-checked:translate-x-5"></div>
                                        </div>
                                    </label>

                                @elseif ($options !== [])
                                    <div>
                                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $label }}</label>
                                        <select wire:model="{{ $path }}"
                                            class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600 text-sm">
                                            @foreach ($options as $value => $optLabel)
                                                <option value="{{ $value }}">{{ $optLabel }}</option>
                                            @endforeach
                                        </select>
                                    </div>

                                @elseif ($definition['type'] === 'integer')
                                    <div>
                                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $label }}</label>
                                        <input wire:model="{{ $path }}" type="number" step="1"
                                            class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600 text-sm">
                                    </div>

                                @elseif ($definition['type'] === 'decimal')
                                    <div>
                                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $label }}</label>
                                        <input wire:model="{{ $path }}" type="number" step="0.0001"
                                            class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600 text-sm">
                                    </div>

                                @else
                                    <div>
                                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $label }}</label>
                                        <input wire:model="{{ $path }}" type="text"
                                            class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600 text-sm">
                                    </div>
                                @endif

                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>

        </div>
    </div>
</div>
