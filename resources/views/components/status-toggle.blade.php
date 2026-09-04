{{--
    Toggle de estado binario (activo/inactivo): el badge de estado ES el
    boton, sin confirmacion. Patron por defecto para cualquier CRUD con un
    campo de 2 estados; no usar para estados con mas de 2 valores o donde el
    verbo de la accion cambia segun la transicion (ver docs/decisiones-tecnicas.md).
--}}
@props([
    'active',
    'action',
    'activeLabel' => 'activo',
    'inactiveLabel' => 'inactivo',
])

<button wire:click="{{ $action }}"
    {{ $attributes->class([
        'inline-flex w-20 justify-center rounded-full px-3 py-1 text-xs font-semibold transition',
        'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-600/20 hover:bg-emerald-100' => $active,
        'bg-stone-200 text-gray-600 hover:bg-stone-300' => ! $active,
    ]) }}>
    {{ $active ? $activeLabel : $inactiveLabel }}
</button>
