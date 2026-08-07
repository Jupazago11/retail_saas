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
                'tone' => 'bg-[#f6efe3] text-[#6d4421] border-[#e7c9a0]',
                'accent' => 'from-[#f4c98a] to-[#e39c52]',
                'icon' => 'catalog',
            ],
            [
                'label' => 'Compras',
                'description' => 'Documentos, proveedores y cuentas por pagar.',
                'href' => $canViewPurchases ? route('purchases.index') : ($canViewSuppliers ? route('purchases.suppliers') : route('purchases.payables')),
                'visible' => ($planModules['purchases'] ?? false) && ($canViewPurchases || $canViewSuppliers || $canViewPayables),
                'tone' => 'bg-[#dbe8ff] text-[#173d7a] border-[#a7c4ff]',
                'accent' => 'from-[#7db1ff] to-[#3f74d6]',
                'icon' => 'purchases',
            ],
            [
                'label' => 'Ventas',
                'description' => 'POS, historial comercial y tickets.',
                'href' => $canCreateSales ? route('sales.pos') : route('sales.index'),
                'visible' => ($planModules['pos'] ?? false) && ($canCreateSales || $canViewSales),
                'tone' => 'bg-[#dff7ea] text-[#15523c] border-[#97ddba]',
                'accent' => 'from-[#62dba4] to-[#2ca36d]',
                'icon' => 'sales',
            ],
            [
                'label' => 'Inventario',
                'description' => 'Ajustes masivos e ingresos operativos.',
                'href' => route('inventory.imports'),
                'visible' => ($planModules['imports'] ?? false) && ($planFeatures['imports.excel'] ?? false) && $canAccessInventory,
                'tone' => 'bg-[#fce4dc] text-[#8a3625] border-[#f3b39f]',
                'accent' => 'from-[#f7a87b] to-[#de6a42]',
                'icon' => 'inventory',
            ],
            [
                'label' => 'Caja',
                'description' => 'Aperturas, cierres y control de efectivo.',
                'href' => route('cash.sessions'),
                'visible' => ($planModules['cash'] ?? false) && $canAccessCash,
                'tone' => 'bg-[#efe2ff] text-[#5d2f90] border-[#caa7ff]',
                'accent' => 'from-[#b27cf4] to-[#7a4cc5]',
                'icon' => 'cash',
            ],
            [
                'label' => 'Credito',
                'description' => 'Cartera, clientes y abonos pendientes.',
                'href' => route('credit.index'),
                'visible' => ($planModules['credit'] ?? false) && ($planFeatures['credit.enabled'] ?? false) && $canAccessCredit,
                'tone' => 'bg-[#fff1cf] text-[#7b5a08] border-[#f0d57b]',
                'accent' => 'from-[#f4d35d] to-[#d6a91e]',
                'icon' => 'credit',
            ],
            [
                'label' => 'Promociones',
                'description' => 'Descuentos por producto y combos.',
                'href' => route('promotions.index'),
                'visible' => ($planModules['promotions'] ?? false) && $canManagePromotions,
                'tone' => 'bg-[#ffe0e8] text-[#8f274a] border-[#f7a8bf]',
                'accent' => 'from-[#f58cae] to-[#d64f79]',
                'icon' => 'promotions',
            ],
            [
                'label' => 'Fidelizacion',
                'description' => 'Puntos, saldos y movimientos del cliente.',
                'href' => route('loyalty.index'),
                'visible' => ($planModules['loyalty'] ?? false) && ($planFeatures['loyalty.enabled'] ?? false) && $canManageLoyalty,
                'tone' => 'bg-[#ddf6f2] text-[#14645b] border-[#93ddd3]',
                'accent' => 'from-[#6fe1d0] to-[#2ea99b]',
                'icon' => 'loyalty',
            ],
            [
                'label' => 'Reportes',
                'description' => 'Resumen comercial, recaudo y analitica.',
                'href' => route('reports.index'),
                'visible' => ($planModules['reports'] ?? false) && $canViewReports,
                'tone' => 'bg-[#e5ecff] text-[#28428e] border-[#a9baf7]',
                'accent' => 'from-[#8ea6ff] to-[#5670db]',
                'icon' => 'reports',
            ],
            [
                'label' => 'Admin',
                'description' => 'Configuracion, estructura, roles y auditoria.',
                'href' => $canManageSettings ? route('admin.settings') : route('admin.roles'),
                'visible' => $canManageSettings || $canManageRoles,
                'tone' => 'bg-[#ece9e3] text-[#50483a] border-[#c9c0b2]',
                'accent' => 'from-[#9d9589] to-[#6e665a]',
                'icon' => 'admin',
            ],
        ])->filter(fn (array $item) => $item['visible'])->values();
    @endphp

    <div class="space-y-8">
        <span class="sr-only">{{ $company->display_name }}</span>
        <section>
            <div class="mb-5 flex items-center justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.3em] text-amber-700">Accesos</p>
                    <h2 class="mt-1 text-2xl font-black text-stone-900 sm:text-3xl">Modulos operativos</h2>
                </div>

            </div>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                @foreach ($launcherItems as $item)
                    <a href="{{ $item['href'] }}" class="group relative block cursor-pointer overflow-hidden rounded-[30px] border p-5 shadow-sm transition duration-200 hover:-translate-y-1 hover:shadow-[0_20px_40px_rgba(15,23,42,0.12)] {{ $item['tone'] }}">
                        <div class="absolute inset-x-0 top-0 h-2 bg-gradient-to-r {{ $item['accent'] }}"></div>
                        <div class="flex min-h-[220px] flex-col">
                            <div>
                                <div class="mb-5 inline-flex h-14 w-14 items-center justify-center rounded-[22px] bg-white/80 text-current shadow-sm">
                                    @switch($item['icon'])
    @case('catalog')
        <x-heroicon-o-squares-2x2 class="h-7 w-7" />
        @break
    @case('purchases')
        <x-heroicon-o-document-text class="h-7 w-7" />
        @break
    @case('sales')
        <x-heroicon-o-shopping-cart class="h-7 w-7" />
        @break
    @case('inventory')
        <x-heroicon-o-archive-box class="h-7 w-7" />
        @break
    @case('cash')
        <x-heroicon-o-banknotes class="h-7 w-7" />
        @break
    @case('credit')
        <x-heroicon-o-credit-card class="h-7 w-7" />
        @break
    @case('promotions')
        <x-heroicon-o-gift-top class="h-7 w-7" />
        @break
    @case('loyalty')
        <x-heroicon-o-heart class="h-7 w-7" />
        @break
    @case('reports')
        <x-heroicon-o-chart-bar class="h-7 w-7" />
        @break
    @case('admin')
        <x-heroicon-o-cog-6-tooth class="h-7 w-7" />
        @break
@endswitch
                                </div>

                                <h3 class="text-2xl font-black tracking-tight">{{ $item['label'] }}</h3>
                                <p class="mt-3 text-sm leading-6 opacity-90">{{ $item['description'] }}</p>
                            </div>


                        </div>
                    </a>
                @endforeach
            </div>
        </section>
    </div>
</x-app-layout>













