@props(['active' => ''])

@php
    $canViewPurchases = auth()->user()?->hasCurrentCompanyPermission('purchases.view') ?? false;
    $canViewSuppliers = auth()->user()?->hasCurrentCompanyPermission('suppliers.view') ?? false;
    $canViewPayables = auth()->user()?->hasCurrentCompanyPermission('payables.view') ?? false;
    $company = app(\App\Services\Tenancy\CurrentCompany::class)->company();
    $planSnapshot = $company
        ? app(\App\Services\Plans\CompanyPlanResolver::class)->snapshot($company)
        : ['modules' => [], 'features' => []];
    $canUseImports = (bool) ($planSnapshot['modules']['imports'] ?? false)
        && (bool) ($planSnapshot['features']['imports.excel'] ?? false);
@endphp

{{--
    El resaltado del tab activo usa un prop explicito ($active), no
    request()->routeIs(): durante una peticion de Livewire (wire:click,
    wire:model.live, etc.) la ruta "actual" que ve el servidor es el
    endpoint interno de Livewire, no la pagina que el usuario esta viendo,
    asi que routeIs() se apaga apenas se interactua con cualquier filtro.
--}}
<div class="flex flex-wrap gap-2">
    @if ($canViewPurchases)
        <a
            href="{{ route('purchases.index') }}"
            wire:navigate
            class="{{ $active === 'purchases.index' ? 'bg-blue-600 text-white' : 'bg-white text-gray-700 border border-gray-200' }} rounded-full px-4 py-2 text-sm font-semibold transition"
        >
            Compras
        </a>
    @endif

    @if ((auth()->user()?->hasCurrentCompanyPermission('purchases.create') ?? false) && $canUseImports)
        <a
            href="{{ route('purchases.imports') }}"
            wire:navigate
            class="{{ $active === 'purchases.imports' ? 'bg-blue-600 text-white' : 'bg-white text-gray-700 border border-gray-200' }} rounded-full px-4 py-2 text-sm font-semibold transition"
        >
            Importar
        </a>
    @endif

    @if ($canViewSuppliers)
        <a
            href="{{ route('purchases.suppliers') }}"
            wire:navigate
            class="{{ $active === 'purchases.suppliers' ? 'bg-blue-600 text-white' : 'bg-white text-gray-700 border border-gray-200' }} rounded-full px-4 py-2 text-sm font-semibold transition"
        >
            Proveedores
        </a>
    @endif

    @if ((auth()->user()?->hasCurrentCompanyPermission('suppliers.manage') ?? false) && $canUseImports)
        <a
            href="{{ route('purchases.suppliers.imports') }}"
            wire:navigate
            class="{{ $active === 'purchases.suppliers.imports' ? 'bg-blue-600 text-white' : 'bg-white text-gray-700 border border-gray-200' }} rounded-full px-4 py-2 text-sm font-semibold transition"
        >
            Importar proveedores
        </a>
    @endif

    @if ($canViewPayables)
        <a
            href="{{ route('purchases.payables') }}"
            wire:navigate
            class="{{ $active === 'purchases.payables' ? 'bg-blue-600 text-white' : 'bg-white text-gray-700 border border-gray-200' }} rounded-full px-4 py-2 text-sm font-semibold transition"
        >
            Cuentas por pagar
        </a>
    @endif
</div>
