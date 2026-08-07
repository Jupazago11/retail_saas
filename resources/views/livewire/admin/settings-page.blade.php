<div class="pb-10">
    <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
        <x-admin-nav />

        <div
            x-data="{ active: '{{ array_key_first($groups) }}' }"
            class="max-w-3xl rounded-3xl bg-white shadow-sm ring-1 ring-stone-200 overflow-hidden">

            {{-- Header --}}
            <div class="flex items-center justify-between border-b border-stone-100 px-6 py-5">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.22em] text-amber-700">Configuracion</p>
                    <h3 class="mt-1 text-2xl font-black text-stone-900">Empresa activa</h3>
                </div>
                <button wire:click="saveSettings"
                    class="rounded-full bg-stone-900 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-amber-700">
                    Guardar cambios
                </button>
            </div>

            @error('settings')
                <div class="mx-6 mt-4 rounded-2xl bg-rose-50 px-4 py-3 text-sm text-rose-700 ring-1 ring-rose-200">
                    {{ $message }}
                </div>
            @enderror

            {{-- Step tabs --}}
            <div class="flex flex-wrap gap-1.5 border-b border-stone-100 px-6 py-4">
                @foreach ($groups as $group => $definitions)
                    <button type="button"
                        @click="active = '{{ $group }}'"
                        :class="active === '{{ $group }}'
                            ? 'bg-stone-900 text-white shadow-sm'
                            : 'bg-stone-100 text-stone-600 hover:bg-stone-200'"
                        class="rounded-full px-4 py-2 text-sm font-semibold transition">
                        {{ $this->groupLabel($group) }}
                    </button>
                @endforeach
            </div>

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
                                    <label class="col-span-2 flex cursor-pointer items-center justify-between rounded-2xl border border-stone-200 bg-stone-50 px-4 py-3 transition hover:border-amber-300 hover:bg-amber-50/40">
                                        <span class="text-sm font-medium text-stone-700">{{ $label }}</span>
                                        <div class="relative ml-4 flex-shrink-0">
                                            <input wire:model="{{ $path }}" type="checkbox"
                                                @checked((bool) $current)
                                                class="peer sr-only">
                                            <div class="h-6 w-11 rounded-full bg-stone-300 transition peer-checked:bg-amber-600 peer-focus:ring-2 peer-focus:ring-amber-400 peer-focus:ring-offset-1"></div>
                                            <div class="absolute left-0.5 top-0.5 h-5 w-5 rounded-full bg-white shadow transition peer-checked:translate-x-5"></div>
                                        </div>
                                    </label>

                                @elseif ($options !== [])
                                    <div>
                                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-stone-500">{{ $label }}</label>
                                        <select wire:model="{{ $path }}"
                                            class="block w-full rounded-2xl border-stone-300 shadow-sm focus:border-amber-500 focus:ring-amber-500 text-sm">
                                            @foreach ($options as $value => $optLabel)
                                                <option value="{{ $value }}">{{ $optLabel }}</option>
                                            @endforeach
                                        </select>
                                    </div>

                                @elseif ($definition['type'] === 'integer')
                                    <div>
                                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-stone-500">{{ $label }}</label>
                                        <input wire:model="{{ $path }}" type="number" step="1"
                                            class="block w-full rounded-2xl border-stone-300 shadow-sm focus:border-amber-500 focus:ring-amber-500 text-sm">
                                    </div>

                                @elseif ($definition['type'] === 'decimal')
                                    <div>
                                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-stone-500">{{ $label }}</label>
                                        <input wire:model="{{ $path }}" type="number" step="0.0001"
                                            class="block w-full rounded-2xl border-stone-300 shadow-sm focus:border-amber-500 focus:ring-amber-500 text-sm">
                                    </div>

                                @else
                                    <div>
                                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-stone-500">{{ $label }}</label>
                                        <input wire:model="{{ $path }}" type="text"
                                            class="block w-full rounded-2xl border-stone-300 shadow-sm focus:border-amber-500 focus:ring-amber-500 text-sm">
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
