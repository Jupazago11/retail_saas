@php
    $canManageSettings = auth()->user()?->hasCurrentCompanyPermission('settings.manage') ?? false;
    $canManageRoles = auth()->user()?->hasCurrentCompanyPermission('roles.manage') ?? false;
    $canViewReports = auth()->user()?->hasCurrentCompanyPermission('reports.view') ?? false;
    $company = app(\App\Services\Tenancy\CurrentCompany::class)->company();
    $reportsEnabled = $company
        ? app(\App\Services\Plans\CompanyPlanResolver::class)->hasModule($company, 'reports')
        : false;
@endphp

<div class="flex flex-wrap gap-2">
    @if ($reportsEnabled && $canViewReports)
        <a href="{{ route('reports.index') }}" wire:navigate @class([
            'rounded-full px-4 py-2 text-sm font-semibold transition',
            'bg-stone-900 text-white' => request()->routeIs('reports.*'),
            'border border-stone-300 text-stone-700 hover:border-stone-400' => ! request()->routeIs('reports.*'),
        ])>
            Reportes
        </a>
    @endif

    @if ($canManageSettings)
        <a href="{{ route('admin.subscription') }}" wire:navigate @class([
            'rounded-full px-4 py-2 text-sm font-semibold transition',
            'bg-stone-900 text-white' => request()->routeIs('admin.subscription'),
            'border border-stone-300 text-stone-700 hover:border-stone-400' => ! request()->routeIs('admin.subscription'),
        ])>
            Suscripcion
        </a>
    @endif

    @if ($canManageSettings)
        <a href="{{ route('admin.bundles') }}" wire:navigate @class([
            'rounded-full px-4 py-2 text-sm font-semibold transition',
            'bg-stone-900 text-white' => request()->routeIs('admin.bundles'),
            'border border-stone-300 text-stone-700 hover:border-stone-400' => ! request()->routeIs('admin.bundles'),
        ])>
            Bundles
        </a>
    @endif

    @if ($canManageSettings)
        <a href="{{ route('admin.coupons') }}" wire:navigate @class([
            'rounded-full px-4 py-2 text-sm font-semibold transition',
            'bg-stone-900 text-white' => request()->routeIs('admin.coupons'),
            'border border-stone-300 text-stone-700 hover:border-stone-400' => ! request()->routeIs('admin.coupons'),
        ])>
            Cupones
        </a>
    @endif

    @if ($canManageSettings)
        <a href="{{ route('admin.overrides') }}" wire:navigate @class([
            'rounded-full px-4 py-2 text-sm font-semibold transition',
            'bg-stone-900 text-white' => request()->routeIs('admin.overrides'),
            'border border-stone-300 text-stone-700 hover:border-stone-400' => ! request()->routeIs('admin.overrides'),
        ])>
            Overrides
        </a>
    @endif

    @if ($canManageSettings)
        <a href="{{ route('admin.structure') }}" wire:navigate @class([
            'rounded-full px-4 py-2 text-sm font-semibold transition',
            'bg-stone-900 text-white' => request()->routeIs('admin.structure'),
            'border border-stone-300 text-stone-700 hover:border-stone-400' => ! request()->routeIs('admin.structure'),
        ])>
            Estructura
        </a>
    @endif

    @if ($canManageSettings)
        <a href="{{ route('admin.settings') }}" wire:navigate @class([
            'rounded-full px-4 py-2 text-sm font-semibold transition',
            'bg-stone-900 text-white' => request()->routeIs('admin.settings'),
            'border border-stone-300 text-stone-700 hover:border-stone-400' => ! request()->routeIs('admin.settings'),
        ])>
            Configuracion
        </a>
    @endif

    @if ($canManageSettings)
        <a href="{{ route('admin.audit-logs') }}" wire:navigate @class([
            'rounded-full px-4 py-2 text-sm font-semibold transition',
            'bg-stone-900 text-white' => request()->routeIs('admin.audit-logs'),
            'border border-stone-300 text-stone-700 hover:border-stone-400' => ! request()->routeIs('admin.audit-logs'),
        ])>
            Auditoria
        </a>
    @endif

    @if ($canManageRoles)
        <a href="{{ route('admin.roles') }}" wire:navigate @class([
            'rounded-full px-4 py-2 text-sm font-semibold transition',
            'bg-stone-900 text-white' => request()->routeIs('admin.roles'),
            'border border-stone-300 text-stone-700 hover:border-stone-400' => ! request()->routeIs('admin.roles'),
        ])>
            Roles
        </a>
    @endif
</div>
