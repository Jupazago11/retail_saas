@props([
    'model',
    'options',
    'placeholder' => 'Selecciona',
    'id' => null,
    'allowCreate' => false,
])

<div
    x-data="searchableSelect({
        selected: $wire.entangle('{{ $model }}'),
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
        @if ($id) id="{{ $id }}" @endif
        placeholder="{{ $placeholder }}"
        autocomplete="off"
        {{ $attributes->merge(['class' => 'block w-full rounded-xl border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600 text-sm']) }}
    >

    <ul
        x-show="open && filtered.length"
        x-cloak
        class="absolute z-20 mt-1 max-h-56 w-full overflow-auto rounded-xl border border-gray-200 bg-white py-1 text-sm shadow-lg"
    >
        <template x-for="(option, index) in filtered" :key="option.id">
            <li
                x-on:click="choose(option)"
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
