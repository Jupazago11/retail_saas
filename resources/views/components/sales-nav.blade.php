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

<div class="flex flex-wrap gap-2">
    @if ($canViewSales)
        <a
            href="{{ route('sales.index') }}"
            wire:navigate
            class="{{ request()->routeIs('sales.index') ? 'bg-stone-900 text-white' : 'bg-white text-stone-700 border border-stone-200' }} rounded-full px-4 py-2 text-sm font-semibold transition"
        >
            Ventas
        </a>
    @endif

    @if ($canCreateSales)
        <a
            href="{{ route('sales.pos') }}"
            wire:navigate
            class="{{ request()->routeIs('sales.pos') ? 'bg-stone-900 text-white' : 'bg-white text-stone-700 border border-stone-200' }} rounded-full px-4 py-2 text-sm font-semibold transition"
        >
            POS
        </a>
    @endif

</div>
