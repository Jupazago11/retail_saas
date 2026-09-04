@props(['icon', 'class' => 'h-5 w-5'])

@if ($icon === 'store')
    <svg class="{{ $class }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M3 9l1.5-5h15L21 9M3 9v10a1 1 0 001 1h16a1 1 0 001-1V9M3 9h18M9 20v-6h6v6" />
    </svg>
@elseif ($icon === 'utensils')
    <svg class="{{ $class }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M5 2v7M7 2v7M9 2v7M7 9v13M15 2c-1.7 0-3 2-3 4v4a2 2 0 002 2h1M15 2v20" />
    </svg>
@endif
