@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'border-gray-300 focus:border-blue-600 focus:ring-blue-600 rounded-lg shadow-sm']) }}>
