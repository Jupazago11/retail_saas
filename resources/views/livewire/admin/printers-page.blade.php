<div class="pb-10">
    <div class="mx-auto max-w-5xl space-y-6 px-4 sm:px-6 lg:px-8">
        <x-admin-nav active="admin.printers" />

        @forelse ($guides as $guide)
            <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                <h3 class="text-lg font-black text-gray-900">{{ $guide->title }}</h3>
                <p class="mt-3 whitespace-pre-line text-sm text-gray-700">{{ $guide->instructions }}</p>

                @if ($guide->path)
                    @php($guideUrl = $guide->temporaryUrl())
                    <a href="{{ $guideUrl }}" target="_blank" rel="noopener"
                        class="mt-4 inline-flex items-center rounded-full border border-blue-300 px-4 py-2 text-sm font-semibold text-blue-700 transition hover:border-blue-400 hover:bg-blue-50">
                        Descargar archivo
                    </a>
                @endif
            </div>
        @empty
            <div class="rounded-xl bg-white p-10 text-center shadow-sm ring-1 ring-gray-200">
                <p class="text-sm text-gray-400">No hay guias disponibles por el momento.</p>
            </div>
        @endforelse
    </div>
</div>
