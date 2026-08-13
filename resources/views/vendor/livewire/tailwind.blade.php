@php
if (! isset($scrollTo)) {
    $scrollTo = 'body';
}

$scrollIntoViewJsSnippet = ($scrollTo !== false)
    ? <<<JS
       (\$el.closest('{$scrollTo}') || document.querySelector('{$scrollTo}')).scrollIntoView()
    JS
    : '';
@endphp

<div>
    @if ($paginator->hasPages())
        <nav role="navigation" aria-label="Paginacion" class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">

            <p class="text-sm text-gray-500 text-center sm:text-left">
                Mostrando
                <span class="font-medium text-gray-700">{{ $paginator->firstItem() }}</span>
                a
                <span class="font-medium text-gray-700">{{ $paginator->lastItem() }}</span>
                de
                <span class="font-medium text-gray-700">{{ $paginator->total() }}</span>
                resultados
            </p>

            {{-- Paginador compacto: siempre son los mismos elementos
            (Anterior / pagina actual / Siguiente), sin importar cuantas
            paginas haya en total, para que nunca se desborde en pantallas
            pequenas. --}}
            <div class="flex items-center justify-center gap-2">
                @if ($paginator->onFirstPage())
                    <span aria-disabled="true" aria-label="Anterior" class="inline-flex items-center justify-center h-8 w-8 rounded-lg border border-gray-200 text-gray-300 cursor-not-allowed">
                        <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                        </svg>
                    </span>
                @else
                    <button type="button" wire:click="previousPage('{{ $paginator->getPageName() }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}" wire:loading.attr="disabled" aria-label="Anterior"
                        class="inline-flex items-center justify-center h-8 w-8 rounded-lg border border-gray-200 text-gray-500 hover:bg-blue-50 hover:text-blue-700 transition">
                        <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                        </svg>
                    </button>
                @endif

                <span class="inline-flex items-center justify-center rounded-lg bg-gradient-to-br from-blue-600 to-purple-600 px-4 py-1.5 text-sm font-semibold text-white shadow-sm whitespace-nowrap">
                    Pagina {{ $paginator->currentPage() }} de {{ $paginator->lastPage() }}
                </span>

                @if ($paginator->hasMorePages())
                    <button type="button" wire:click="nextPage('{{ $paginator->getPageName() }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}" wire:loading.attr="disabled" aria-label="Siguiente"
                        class="inline-flex items-center justify-center h-8 w-8 rounded-lg border border-gray-200 text-gray-500 hover:bg-blue-50 hover:text-blue-700 transition">
                        <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                        </svg>
                    </button>
                @else
                    <span aria-disabled="true" aria-label="Siguiente" class="inline-flex items-center justify-center h-8 w-8 rounded-lg border border-gray-200 text-gray-300 cursor-not-allowed">
                        <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                        </svg>
                    </span>
                @endif
            </div>
        </nav>
    @endif
</div>
