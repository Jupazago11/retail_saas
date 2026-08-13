@props(['active' => ''])

@php
    $canViewSales = auth()->user()?->hasCurrentCompanyPermission('sales.view') ?? false;
    $canCreateSales = auth()->user()?->hasCurrentCompanyPermission('sales.create') ?? false;
    $canFreezeSales = auth()->user()?->hasCurrentCompanyPermission('sales.freeze') ?? false;
    $company = app(\App\Services\Tenancy\CurrentCompany::class)->company();
    $planSnapshot = $company
        ? app(\App\Services\Plans\CompanyPlanResolver::class)->snapshot($company)
        : ['modules' => [], 'features' => []];
    $frozenSalesEnabled = (bool) ($planSnapshot['features']['pos.frozen_sales'] ?? false);
@endphp

{{--
    El resaltado del tab activo usa un prop explicito ($active), no
    request()->routeIs(): durante una peticion de Livewire (wire:click,
    wire:model.live, etc.) la ruta "actual" que ve el servidor es el
    endpoint interno de Livewire, no la pagina que el usuario esta viendo,
    asi que routeIs() se apaga apenas se interactua con cualquier filtro.
--}}
<div class="flex flex-wrap gap-2">
    @if ($canViewSales)
        <a
            href="{{ route('sales.index') }}"
            wire:navigate
            class="{{ $active === 'sales.index' ? 'bg-blue-600 text-white' : 'bg-white text-gray-700 border border-gray-200' }} rounded-full px-4 py-2 text-sm font-semibold transition"
        >
            Ventas
        </a>
    @endif

    @if ($canCreateSales)
        <a
            href="{{ route('sales.pos') }}"
            wire:navigate
            class="{{ $active === 'sales.pos' ? 'bg-blue-600 text-white' : 'bg-white text-gray-700 border border-gray-200' }} rounded-full px-4 py-2 text-sm font-semibold transition"
        >
            POS
        </a>
    @endif

</div>
