<div>
    <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        {{ $title }}
    </h2>

    @isset($description)
        <p class="mt-1 text-sm text-stone-500">
            {{ $description }}
        </p>
    @endisset
</div>
