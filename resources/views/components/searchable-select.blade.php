@props([
    'model',
    'options',
    'placeholder' => 'Selecciona',
    'id' => null,
    'allowCreate' => false,
    'live' => false,
])

@php
    // Los filtros de tabla necesitan sincronizar de inmediato (como wire:model.live);
    // los campos de formulario en modales se quedan diferidos hasta el submit.
    $entangleExpression = $live
        ? "\$wire.entangle('{$model}').live"
        : "\$wire.entangle('{$model}')";
@endphp

{{--
    wire:key evita que wire:navigate "reutilice" este nodo al morfear hacia
    otra pagina con una estructura DOM parecida en la misma posicion: sin la
    key, Alpine puede dejar los efectos reactivos (query/filtered/touched)
    de la pagina VIEJA pegados a un nodo que sobrevive el morph, y tiran
    "X is not defined" en la consola al navegar (ej. Productos -> Home).
--}}
<div
    @if ($id) wire:key="{{ $id }}" @endif
    x-data="searchableSelect({
        selected: {{ $entangleExpression }},
        allowCreate: {{ $allowCreate ? 'true' : 'false' }},
    })"
    data-options="{{ json_encode(collect($options)->values()) }}"
    x-on:click.outside="close()"
    class="relative"
>
    <input
        type="text"
        x-model="query"
        x-on:focus="onFocus($event)"
        x-on:input="onInput()"
        x-on:keydown.down.prevent="highlightNext()"
        x-on:keydown.up.prevent="highlightPrev()"
        x-on:keydown.enter.prevent="selectHighlighted()"
        x-on:keydown.escape="close()"
        x-on:blur="close()"
        @if ($id) id="{{ $id }}" @endif
        placeholder="{{ $placeholder }}"
        autocomplete="off"
        {{ $attributes->merge(['class' => 'block w-full rounded-xl border-gray-300 pr-8 shadow-sm focus:border-blue-600 focus:ring-blue-600 text-sm']) }}
    >

    <button
        type="button"
        x-show="query"
        x-cloak
        x-on:click.stop="clear()"
        title="Limpiar"
        class="absolute inset-y-0 right-0 flex w-8 items-center justify-center text-gray-300 hover:text-gray-500"
    >
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
        </svg>
    </button>

    <ul
        x-show="open && filtered.length"
        x-cloak
        class="absolute z-20 mt-1 max-h-56 w-full overflow-auto rounded-xl border border-gray-200 bg-white py-1 text-sm shadow-lg"
    >
        <template x-for="(option, index) in filtered" :key="option.id">
            <li
                x-on:mousedown.prevent="choose(option)"
                x-on:mouseenter="highlighted = index"
                :class="highlighted === index ? 'bg-amber-50 text-gray-900' : 'text-gray-700'"
                class="cursor-pointer px-3 py-1.5"
                x-text="option.label"
            ></li>
        </template>
    </ul>

    <p
        x-show="open && touched && ! filtered.length"
        x-cloak
        class="absolute z-20 mt-1 w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm text-gray-400 shadow-lg"
    >
        Sin resultados
    </p>
</div>
