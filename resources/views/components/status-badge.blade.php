@props(['color' => 'stone'])

@php
    $palette = [
        'emerald' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
        'rose'    => 'bg-rose-50 text-rose-700 ring-rose-600/20',
        'amber'   => 'bg-amber-50 text-amber-700 ring-amber-600/20',
        'sky'     => 'bg-sky-50 text-sky-700 ring-sky-600/20',
        'violet'  => 'bg-violet-50 text-violet-700 ring-violet-600/20',
        'blue'    => 'bg-blue-50 text-blue-700 ring-blue-600/20',
        'indigo'  => 'bg-indigo-50 text-indigo-700 ring-indigo-600/20',
        'stone'   => 'bg-gray-100 text-gray-600 ring-stone-400/30',
    ][$color] ?? 'bg-gray-100 text-gray-600 ring-stone-400/30';
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center justify-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold ring-1 ring-inset $palette"]) }}>
    {{ $slot }}
</span>
