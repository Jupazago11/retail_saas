<x-app-layout>
    @php
        $snapshot = app(\App\Services\Plans\CompanyPlanResolver::class)->snapshot($company);
        $plan = $snapshot['plan'];
        $planLabel = $plan?->name ?? ($plan?->code ? \Illuminate\Support\Str::headline($plan->code) : 'Sin plan');
        $planModules = $snapshot['modules'] ?? [];
        $planFeatures = $snapshot['features'] ?? [];

        $canViewMasters = auth()->user()?->hasCurrentCompanyPermission('masters.view') ?? false;
        $canViewProducts = auth()->user()?->hasCurrentCompanyPermission('products.view') ?? false;
        $canViewPurchases = auth()->user()?->hasCurrentCompanyPermission('purchases.view') ?? false;
        $canViewSuppliers = auth()->user()?->hasCurrentCompanyPermission('suppliers.view') ?? false;
        $canViewPayables = auth()->user()?->hasCurrentCompanyPermission('payables.view') ?? false;
        $canViewSales = auth()->user()?->hasCurrentCompanyPermission('sales.view') ?? false;
        $canCreateSales = auth()->user()?->hasCurrentCompanyPermission('sales.create') ?? false;
        $canAccessInventory = (auth()->user()?->hasCurrentCompanyPermission('inventory.view') ?? false)
            || (auth()->user()?->hasCurrentCompanyPermission('inventory.adjust') ?? false)
            || (auth()->user()?->hasCurrentCompanyPermission('inventory.transfer') ?? false);
        $canAccessCash = (auth()->user()?->hasCurrentCompanyPermission('cash.open') ?? false)
            || (auth()->user()?->hasCurrentCompanyPermission('cash.close') ?? false)
            || (auth()->user()?->hasCurrentCompanyPermission('cash.view_difference') ?? false);
        $canAccessCredit = (auth()->user()?->hasCurrentCompanyPermission('credit.view') ?? false)
            || (auth()->user()?->hasCurrentCompanyPermission('credit.manage') ?? false);
        $canViewReports = auth()->user()?->hasCurrentCompanyPermission('reports.view') ?? false;
        $canManagePromotions = auth()->user()?->hasCurrentCompanyPermission('promotions.manage') ?? false;
        $canManageLoyalty = auth()->user()?->hasCurrentCompanyPermission('loyalty.manage') ?? false;
        $canManageSettings = auth()->user()?->hasCurrentCompanyPermission('settings.manage') ?? false;
        $canManageRoles = auth()->user()?->hasCurrentCompanyPermission('roles.manage') ?? false;

        $launcherItems = collect([
            [
                'label' => 'Catalogos',
                'description' => 'Categorias, marcas, unidades y productos.',
                'href' => route('products.index'),
                'visible' => ($planModules['products'] ?? false) && ($canViewMasters || $canViewProducts),
                'color' => 'amber',
                'icon' => 'catalog',
            ],
            [
                'label' => 'Compras',
                'description' => 'Documentos, proveedores y cuentas por pagar.',
                'href' => $canViewPurchases ? route('purchases.index') : ($canViewSuppliers ? route('purchases.suppliers') : route('purchases.payables')),
                'visible' => ($planModules['purchases'] ?? false) && ($canViewPurchases || $canViewSuppliers || $canViewPayables),
                'color' => 'blue',
                'icon' => 'purchases',
            ],
            [
                'label' => 'Ventas',
                'description' => 'POS, historial comercial y tickets.',
                'href' => $canCreateSales ? route('sales.pos') : route('sales.index'),
                'visible' => ($planModules['pos'] ?? false) && ($canCreateSales || $canViewSales),
                'color' => 'emerald',
                'icon' => 'sales',
            ],
            [
                'label' => 'Inventario',
                'description' => 'Existencias, ajustes y alertas de stock minimo.',
                'href' => route('inventory.index'),
                'visible' => ($planModules['inventory'] ?? false) && $canAccessInventory,
                'color' => 'orange',
                'icon' => 'inventory',
            ],
            [
                'label' => 'Caja',
                'description' => 'Aperturas, cierres y control de efectivo.',
                'href' => route('cash.sessions'),
                'visible' => ($planModules['cash'] ?? false) && $canAccessCash,
                'color' => 'violet',
                'icon' => 'cash',
            ],
            [
                'label' => 'Credito',
                'description' => 'Cartera, clientes y abonos pendientes.',
                'href' => route('credit.index'),
                'visible' => ($planModules['credit'] ?? false) && ($planFeatures['credit.enabled'] ?? false) && $canAccessCredit,
                'color' => 'fuchsia',
                'icon' => 'credit',
            ],
            [
                'label' => 'Promociones',
                'description' => 'Descuentos por producto y combos.',
                'href' => route('promotions.index'),
                'visible' => ($planModules['promotions'] ?? false) && $canManagePromotions,
                'color' => 'rose',
                'icon' => 'promotions',
            ],
            [
                'label' => 'Fidelizacion',
                'description' => 'Puntos, saldos y movimientos del cliente.',
                'href' => route('loyalty.index'),
                'visible' => ($planModules['loyalty'] ?? false) && ($planFeatures['loyalty.enabled'] ?? false) && $canManageLoyalty,
                'color' => 'teal',
                'icon' => 'loyalty',
            ],
            [
                'label' => 'Reportes',
                'description' => 'Resumen comercial, recaudo y analitica.',
                'href' => route('reports.index'),
                'visible' => ($planModules['reports'] ?? false) && $canViewReports,
                'color' => 'indigo',
                'icon' => 'reports',
            ],
            [
                'label' => 'Admin',
                'description' => 'Configuracion, estructura, roles y auditoria.',
                'href' => $canManageSettings ? route('admin.settings') : route('admin.roles'),
                'visible' => $canManageSettings || $canManageRoles,
                'color' => 'stone',
                'icon' => 'admin',
            ],
        ])->filter(fn (array $item) => $item['visible'])->values();

        $moduleColorTokens = [
            'amber'   => ['icon' => 'bg-amber-50 text-amber-600'],
            'blue'    => ['icon' => 'bg-blue-50 text-blue-600'],
            'emerald' => ['icon' => 'bg-emerald-50 text-emerald-600'],
            'orange'  => ['icon' => 'bg-orange-50 text-orange-600'],
            'violet'  => ['icon' => 'bg-violet-50 text-violet-600'],
            'fuchsia' => ['icon' => 'bg-fuchsia-50 text-fuchsia-600'],
            'rose'    => ['icon' => 'bg-rose-50 text-rose-600'],
            'teal'    => ['icon' => 'bg-teal-50 text-teal-600'],
            'indigo'  => ['icon' => 'bg-indigo-50 text-indigo-600'],
            'stone'   => ['icon' => 'bg-gray-100 text-gray-600'],
        ];
    @endphp

    <div class="-mt-3 space-y-6">
        <span class="sr-only">{{ $company->display_name }}</span>
        <section>
            <div class="mb-3 flex items-center justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.3em] text-blue-700">Accesos</p>
                    <h2 class="mt-1 text-xl font-black text-gray-900 sm:text-2xl">Modulos operativos</h2>
                </div>

            </div>

            <div class="grid gap-3 grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 2xl:grid-cols-5">
                @foreach ($launcherItems as $item)
                    @php($tokens = $moduleColorTokens[$item['color']])
                    <a href="{{ $item['href'] }}" wire:navigate class="group block cursor-pointer rounded-xl border border-gray-200 bg-white p-4 shadow-sm transition duration-200 hover:border-gray-300 hover:shadow-md">
                        <div class="mb-2 inline-flex h-12 w-12 items-center justify-center rounded-xl {{ $tokens['icon'] }}">
                                    @switch($item['icon'])
    @case('catalog')
        <x-heroicon-o-squares-2x2 class="h-6 w-6" />
        @break
    @case('purchases')
        <x-heroicon-o-document-text class="h-6 w-6" />
        @break
    @case('sales')
        <x-heroicon-o-shopping-cart class="h-6 w-6" />
        @break
    @case('inventory')
        <x-heroicon-o-archive-box class="h-6 w-6" />
        @break
    @case('cash')
        <x-heroicon-o-banknotes class="h-6 w-6" />
        @break
    @case('credit')
        <x-heroicon-o-credit-card class="h-6 w-6" />
        @break
    @case('promotions')
        <x-heroicon-o-gift-top class="h-6 w-6" />
        @break
    @case('loyalty')
        <x-heroicon-o-heart class="h-6 w-6" />
        @break
    @case('reports')
        <x-heroicon-o-chart-bar class="h-6 w-6" />
        @break
    @case('admin')
        <x-heroicon-o-cog-6-tooth class="h-6 w-6" />
        @break
@endswitch
                                </div>

                        <h3 class="text-xl font-black tracking-tight text-gray-900">{{ $item['label'] }}</h3>
                        <p class="mt-1 text-sm leading-6 text-gray-500">{{ $item['description'] }}</p>
                    </a>
                @endforeach
            </div>
        </section>
    </div>
</x-app-layout>













