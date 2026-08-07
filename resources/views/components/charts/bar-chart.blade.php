@props([
    'data' => [],
    'labelKey' => 'label',
    'valueKey' => 'value',
    'color' => '#2a78d6',
    'colors' => null,
    'money' => false,
    'ariaLabel' => 'Grafica de barras',
    'height' => 220,
])

@php
    // Values coming from OperationalReportService are often pre-formatted with
    // '.' as a thousands separator (e.g. "153.028"); a naive (float) cast would
    // misread that dot as a decimal point. Strip formatting before casting.
    $normalize = function ($raw) {
        if (is_string($raw)) {
            return (float) \App\Support\Money::normalizeInput($raw);
        }

        return (float) $raw;
    };

    $points = collect($data)->values()->map(function ($point) use ($normalize, $valueKey) {
        $point = (array) $point;
        $point[$valueKey] = $normalize($point[$valueKey] ?? 0);

        return $point;
    });
    $max = (float) $points->max($valueKey);
    $max = $max > 0 ? $max : 1;
    $barCount = max($points->count(), 1);
@endphp

<div
    class="viz-root"
    style="
        --surface-1: #fcfcfb; --text-primary: #0b0b0b; --text-secondary: #52514e;
        --text-muted: #898781; --grid-line: #e1e0d9; --axis-line: #c3c2b7;
    "
    x-data="{ hovered: null }"
>
    <style>
        .viz-root { color-scheme: light; }
        @media (prefers-color-scheme: dark) {
            :root:where(:not([data-theme="light"])) .viz-root {
                --surface-1: #1a1a19; --text-primary: #ffffff; --text-secondary: #c3c2b7;
                --text-muted: #898781; --grid-line: #2c2c2a; --axis-line: #383835;
                color-scheme: dark;
            }
        }
        :root[data-theme="dark"] .viz-root {
            --surface-1: #1a1a19; --text-primary: #ffffff; --text-secondary: #c3c2b7;
            --text-muted: #898781; --grid-line: #2c2c2a; --axis-line: #383835;
            color-scheme: dark;
        }
    </style>

    @if ($points->isEmpty())
        <p class="text-sm text-stone-400">Sin datos para el filtro actual.</p>
    @else
        <div class="relative" style="height: {{ $height }}px;">
            <svg viewBox="0 0 {{ $barCount * 60 }} 100" preserveAspectRatio="none"
                width="100%" height="{{ $height }}" style="display: block;"
                class="overflow-visible" role="img" aria-label="{{ $ariaLabel }}">
                {{-- Gridlines --}}
                @foreach ([0, 25, 50, 75, 100] as $gridY)
                    <line x1="0" x2="{{ $barCount * 60 }}" y1="{{ $gridY }}" y2="{{ $gridY }}"
                        stroke="var(--grid-line)" stroke-width="0.5" vector-effect="non-scaling-stroke" />
                @endforeach

                @foreach ($points as $index => $point)
                    @php
                        $value = (float) ($point[$valueKey] ?? 0);
                        $barHeight = max(($value / $max) * 96, $value > 0 ? 2 : 0);
                        $barColor = $colors[$index % max(count($colors ?: []), 1)] ?? $color;
                        $x = $index * 60 + 8;
                    @endphp
                    <rect
                        x="{{ $x }}" y="{{ 100 - $barHeight }}" width="44" height="{{ $barHeight }}"
                        rx="4" ry="4" fill="{{ $colors ? $barColor : $color }}"
                        opacity="1"
                        x-on:mouseenter="hovered = {{ $index }}"
                        x-on:mouseleave="hovered = null"
                        style="cursor: pointer;"
                    >
                        <title>{{ $point[$labelKey] ?? '' }}: {{ $money ? '$'.number_format($value, 0, ',', '.') : number_format($value, 0, ',', '.') }}</title>
                    </rect>
                @endforeach
            </svg>

            {{-- Tooltip --}}
            @foreach ($points as $index => $point)
                @php
                    $value = (float) ($point[$valueKey] ?? 0);
                    $leftPct = (($index * 60 + 30) / ($barCount * 60)) * 100;
                @endphp
                <div
                    x-show="hovered === {{ $index }}"
                    x-cloak
                    class="pointer-events-none absolute -translate-x-1/2 -translate-y-full rounded-xl px-3 py-1.5 text-xs font-semibold shadow-lg ring-1"
                    style="left: {{ $leftPct }}%; top: -6px; background: var(--surface-1); color: var(--text-primary); border-color: var(--axis-line);"
                >
                    <span style="color: var(--text-secondary);">{{ $point[$labelKey] ?? '' }}:</span>
                    {{ $money ? '$'.number_format($value, 0, ',', '.') : number_format($value, 0, ',', '.') }}
                </div>
            @endforeach
        </div>

        {{-- Labels --}}
        <div class="mt-2 grid gap-1 text-center text-[11px]" style="color: var(--text-muted); grid-template-columns: repeat({{ $barCount }}, minmax(0, 1fr));">
            @foreach ($points as $point)
                <span class="truncate">{{ $point[$labelKey] ?? '' }}</span>
            @endforeach
        </div>
    @endif
</div>
