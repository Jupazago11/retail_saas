{{--
    Ventas y POS son dos componentes Livewire completos (SalesPage/PosPage,
    sin tocar su logica interna) montados AMBOS aqui a la vez; cambiar de
    pestaña es un toggle de Alpine puro (x-show), sin ninguna peticion al
    servidor — asi el carrito del POS no se pierde al ir a revisar el
    historial y volver. Ver [[project_sales_pos_unified_workspace]].

    x-on:switch-sales-tab: disparado por los botones "Ir al POS"/"Historial"
    dentro de cada componente hijo (puramente client-side, sin wire:click).
    x-on:edit-draft-requested / modify-sale-requested: los MISMOS eventos que
    PosPage escucha por Livewire (#[On(...)]) para cargar la venta indicada
    tambien cambian la pestaña visible aqui.
--}}
<div
    x-data="{ tab: @js($activeTab) }"
    x-on:switch-sales-tab.window="tab = $event.detail.tab"
    x-on:edit-draft-requested.window="tab = 'pos'"
    x-on:modify-sale-requested.window="tab = 'pos'"
    x-effect="window.history.replaceState(null, '', tab === 'pos' ? @js(route('sales.pos')) : @js(route('sales.index')))"
>
    <div class="mx-auto max-w-7xl px-4 pt-6 pb-4 sm:px-6 lg:px-8">
        <div class="flex flex-wrap gap-2">
            @if ($this->canViewSales())
                <button type="button" x-on:click="tab = 'history'"
                    :class="tab === 'history' ? 'bg-blue-600 text-white' : 'bg-white text-gray-700 border border-gray-200'"
                    class="rounded-full px-4 py-2 text-sm font-semibold transition">
                    Ventas
                </button>
            @endif

            @if ($this->canCreateSales())
                <button type="button" x-on:click="tab = 'pos'"
                    :class="tab === 'pos' ? 'bg-blue-600 text-white' : 'bg-white text-gray-700 border border-gray-200'"
                    class="rounded-full px-4 py-2 text-sm font-semibold transition">
                    POS
                </button>
            @endif
        </div>
    </div>

    @if ($this->canViewSales())
        <div
            x-show="tab === 'history'"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-y-1"
            x-transition:enter-end="opacity-100 translate-y-0"
        >
            @livewire('sales.sales-page', key('sales-workspace-history'))
        </div>
    @endif

    @if ($this->canCreateSales())
        <div
            x-show="tab === 'pos'"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-y-1"
            x-transition:enter-end="opacity-100 translate-y-0"
        >
            @livewire('sales.pos-page', key('sales-workspace-pos'))
        </div>
    @endif
</div>
