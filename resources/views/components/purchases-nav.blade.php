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

<div class="flex flex-wrap gap-2">
    @if ($canViewPurchases)
        <a
            href="{{ route('purchases.index') }}"
            wire:navigate
            class="{{ request()->routeIs('purchases.index') ? 'bg-stone-900 text-white' : 'bg-white text-stone-700 border border-stone-200' }} rounded-full px-4 py-2 text-sm font-semibold transition"
        >
            Compras
        </a>
    @endif

    @if ((auth()->user()?->hasCurrentCompanyPermission('purchases.create') ?? false) && $canUseImports)
        <a
            href="{{ route('purchases.imports') }}"
            wire:navigate
            class="{{ request()->routeIs('purchases.imports') ? 'bg-stone-900 text-white' : 'bg-white text-stone-700 border border-stone-200' }} rounded-full px-4 py-2 text-sm font-semibold transition"
        >
            Importar
        </a>
    @endif

    @if ($canViewSuppliers)
        <a
            href="{{ route('purchases.suppliers') }}"
            wire:navigate
            class="{{ request()->routeIs('purchases.suppliers') ? 'bg-stone-900 text-white' : 'bg-white text-stone-700 border border-stone-200' }} rounded-full px-4 py-2 text-sm font-semibold transition"
        >
            Proveedores
        </a>
    @endif

    @if ((auth()->user()?->hasCurrentCompanyPermission('suppliers.manage') ?? false) && $canUseImports)
        <a
            href="{{ route('purchases.suppliers.imports') }}"
            wire:navigate
            class="{{ request()->routeIs('purchases.suppliers.imports') ? 'bg-stone-900 text-white' : 'bg-white text-stone-700 border border-stone-200' }} rounded-full px-4 py-2 text-sm font-semibold transition"
        >
            Importar proveedores
        </a>
    @endif

    @if ($canViewPayables)
        <a
            href="{{ route('purchases.payables') }}"
            wire:navigate
            class="{{ request()->routeIs('purchases.payables') ? 'bg-stone-900 text-white' : 'bg-white text-stone-700 border border-stone-200' }} rounded-full px-4 py-2 text-sm font-semibold transition"
        >
            Cuentas por pagar
        </a>
    @endif
</div>
