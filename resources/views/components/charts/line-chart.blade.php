@props([
    'data' => [],
    'labelKey' => 'date',
    'valueKey' => 'value',
    'color' => '#2a78d6',
    'money' => true,
    'ariaLabel' => 'Grafica de linea',
    'height' => 220,
])

@php
    // Values coming from OperationalReportService are sometimes pre-formatted with
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
    $count = max($points->count(), 1);
    $width = 600;
    $stepX = $count > 1 ? $width / ($count - 1) : 0;

    $coords = $points->values()->map(function ($point, $index) use ($valueKey, $max, $stepX, $count) {
        $value = (float) ($point[$valueKey] ?? 0);
        $x = $count > 1 ? $index * $stepX : $stepX;
        $y = 100 - (($value / $max) * 92);

        return ['x' => $x, 'y' => $y, 'value' => $value];
    });

    $linePath = $coords->map(fn ($c, $i) => ($i === 0 ? 'M' : 'L').$c['x'].','.$c['y'])->implode(' ');
    $areaPath = $coords->isNotEmpty()
        ? $linePath.' L'.$coords->last()['x'].',100 L'.$coords->first()['x'].',100 Z'
        : '';
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
            <svg viewBox="0 0 {{ $width }} 100" preserveAspectRatio="none"
                width="100%" height="{{ $height }}" style="display: block;"
                class="overflow-visible" role="img" aria-label="{{ $ariaLabel }}">
                @foreach ([0, 25, 50, 75, 100] as $gridY)
                    <line x1="0" x2="{{ $width }}" y1="{{ $gridY }}" y2="{{ $gridY }}"
                        stroke="var(--grid-line)" stroke-width="0.5" vector-effect="non-scaling-stroke" />
                @endforeach

                @if ($areaPath)
                    <path d="{{ $areaPath }}" fill="{{ $color }}" opacity="0.12" stroke="none" />
                @endif

                <path d="{{ $linePath }}" fill="none" stroke="{{ $color }}" stroke-width="2"
                    stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke" />

                @foreach ($coords as $index => $c)
                    <circle cx="{{ $c['x'] }}" cy="{{ $c['y'] }}" r="2.5" fill="{{ $color }}" />
                    <circle cx="{{ $c['x'] }}" cy="{{ $c['y'] }}" r="9" fill="transparent"
                        x-on:mouseenter="hovered = {{ $index }}" x-on:mouseleave="hovered = null"
                        style="cursor: pointer;" />
                @endforeach

                @foreach ($coords as $index => $c)
                    <line x1="{{ $c['x'] }}" x2="{{ $c['x'] }}" y1="0" y2="100" stroke="var(--axis-line)"
                        stroke-width="0.5" vector-effect="non-scaling-stroke"
                        x-show="hovered === {{ $index }}" x-cloak />
                @endforeach
            </svg>

            @foreach ($points as $index => $point)
                @php
                    $value = (float) ($point[$valueKey] ?? 0);
                    $leftPct = $count > 1 ? ($index * $stepX / $width) * 100 : 50;
                @endphp
                <div
                    x-show="hovered === {{ $index }}"
                    x-cloak
                    class="pointer-events-none absolute -translate-x-1/2 -translate-y-full rounded-xl px-3 py-1.5 text-xs font-semibold shadow-lg ring-1"
                    style="left: {{ $leftPct }}%; top: -6px; background: var(--surface-1); color: var(--text-primary); border-color: var(--axis-line); white-space: nowrap;"
                >
                    <span style="color: var(--text-secondary);">{{ $point[$labelKey] ?? '' }}:</span>
                    {{ $money ? '$'.number_format($value, 0, ',', '.') : number_format($value, 0, ',', '.') }}
                </div>
            @endforeach
        </div>

        <div class="mt-2 flex justify-between text-[11px]" style="color: var(--text-muted);">
            <span>{{ $points->first()[$labelKey] ?? '' }}</span>
            @if ($points->count() > 1)
                <span>{{ $points->last()[$labelKey] ?? '' }}</span>
            @endif
        </div>

        <div class="mt-4 max-h-56 overflow-y-auto rounded-2xl ring-1" style="border-color: var(--axis-line);">
            <table class="min-w-full text-xs">
                <thead class="sticky top-0" style="background: var(--surface-1);">
                    <tr class="text-left" style="color: var(--text-muted);">
                        <th class="px-3 py-2 font-semibold uppercase tracking-wide">Fecha</th>
                        <th class="px-3 py-2 text-right font-semibold uppercase tracking-wide">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($points as $point)
                        <tr class="border-t" style="border-color: var(--grid-line);">
                            <td class="px-3 py-1.5" style="color: var(--text-secondary);">{{ $point[$labelKey] ?? '' }}</td>
                            <td class="px-3 py-1.5 text-right font-medium" style="color: var(--text-primary);">
                                ${{ number_format((float) ($point[$valueKey] ?? 0), 0, ',', '.') }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
