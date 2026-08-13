<?php

use App\Livewire\Actions\Logout;
use App\Services\Plans\CompanyPlanResolver;
use App\Services\Settings\CompanySettings;
use App\Services\Tenancy\CurrentCompany;
use Illuminate\Support\Str;
use Livewire\Volt\Component;

new class extends Component
{
    public ?string $currentCompanyName = null;
    public array $planModules = [];
    public array $planFeatures = [];
    public bool $cashModuleEnabled = true;

    public function mount(CurrentCompany $currentCompany, CompanyPlanResolver $companyPlanResolver, CompanySettings $companySettings): void
    {
        $company = $currentCompany->company();

        $this->currentCompanyName = $company?->display_name;

        if (! $company) {
            return;
        }

        $snapshot = $companyPlanResolver->snapshot($company);

        $this->planModules = $snapshot['modules'];
        $this->planFeatures = $snapshot['features'];
        $this->cashModuleEnabled = (bool) $companySettings->get($company, 'cash', 'module_enabled');
    }

    /**
     * Log the current user out of the application.
     */
    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect('/', navigate: true);
    }
}; ?>

@php
    $canViewMasters = auth()->user()?->hasCurrentCompanyPermission('masters.view') ?? false;
    $canViewProducts = auth()->user()?->hasCurrentCompanyPermission('products.view') ?? false;
    $canViewPurchases = auth()->user()?->hasCurrentCompanyPermission('purchases.view') ?? false;
    $canViewSuppliers = auth()->user()?->hasCurrentCompanyPermission('suppliers.view') ?? false;
    $canViewPayables = auth()->user()?->hasCurrentCompanyPermission('payables.view') ?? false;
    $canViewSales = auth()->user()?->hasCurrentCompanyPermission('sales.view') ?? false;
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
    $hasProductsModule = (bool) ($planModules['products'] ?? false);
    $hasPurchasesModule = (bool) ($planModules['purchases'] ?? false);
    $hasPosModule = (bool) ($planModules['pos'] ?? false);
    $hasCashModule = (bool) ($planModules['cash'] ?? false);
    $hasCreditModule = (bool) ($planModules['credit'] ?? false);
    $hasLoyaltyModule = (bool) ($planModules['loyalty'] ?? false);
    $hasPromotionsModule = (bool) ($planModules['promotions'] ?? false);
    $hasReportsModule = (bool) ($planModules['reports'] ?? false);
    $hasInventoryModule = (bool) ($planModules['inventory'] ?? false);
    $hasImportsModule = (bool) ($planModules['imports'] ?? false);
    $hasCreditFeature = (bool) ($planFeatures['credit.enabled'] ?? false);
    $hasLoyaltyFeature = (bool) ($planFeatures['loyalty.enabled'] ?? false);
    $hasImportsFeature = (bool) ($planFeatures['imports.excel'] ?? false);
    $catalogRoute = route('products.index');
    $purchasesRoute = $canViewPurchases
        ? route('purchases.index')
        : ($canViewSuppliers ? route('purchases.suppliers') : route('purchases.payables'));
    $adminRoute = $canManageSettings
        ? route('admin.settings')
        : ($canManageRoles ? route('admin.roles') : route('admin.audit-logs'));
    $companyInitials = Str::of($currentCompanyName ?: 'RS')
        ->explode(' ')
        ->filter()
        ->take(2)
        ->map(fn (string $segment) => Str::upper(Str::substr($segment, 0, 1)))
        ->implode('');
    $userInitial = Str::upper(Str::substr(auth()->user()?->name ?? 'U', 0, 1));
    $usernameHandle = auth()->user()?->username ? '@'.auth()->user()->username : '@usuario';
    $navigationItems = collect([
        [
            'label' => 'Dashboard',
            'href' => route('dashboard'),
            'active' => request()->routeIs('dashboard'),
            'visible' => true,
            'icon' => 'dashboard',
        ],
        [
            'label' => 'Catalogos',
            'href' => $catalogRoute,
            'active' => request()->routeIs('masters.*') || request()->routeIs('products.*'),
            'visible' => $hasProductsModule && ($canViewMasters || $canViewProducts),
            'icon' => 'catalog',
        ],
        [
            'label' => 'Compras',
            'href' => $purchasesRoute,
            'active' => request()->routeIs('purchases.*'),
            'visible' => $hasPurchasesModule && ($canViewPurchases || $canViewSuppliers || $canViewPayables),
            'icon' => 'purchases',
        ],
        [
            'label' => 'Ventas',
            'href' => route('sales.index'),
            'active' => request()->routeIs('sales.*'),
            'visible' => $hasPosModule && $canViewSales,
            'icon' => 'sales',
        ],
        [
            'label' => 'Inventario',
            'href' => route('inventory.index'),
            'active' => request()->routeIs('inventory.*'),
            'visible' => $hasInventoryModule && $canAccessInventory,
            'icon' => 'inventory',
        ],
        [
            'label' => 'Caja',
            'href' => route('cash.sessions'),
            'active' => request()->routeIs('cash.*'),
            'visible' => $hasCashModule && $canAccessCash && $cashModuleEnabled,
            'icon' => 'cash',
        ],
        [
            'label' => 'Credito',
            'href' => route('credit.index'),
            'active' => request()->routeIs('credit.*'),
            'visible' => $hasCreditModule && $hasCreditFeature && $canAccessCredit,
            'icon' => 'credit',
        ],
        [
            'label' => 'Promociones',
            'href' => route('promotions.index'),
            'active' => request()->routeIs('promotions.*'),
            'visible' => $hasPromotionsModule && $canManagePromotions,
            'icon' => 'promotions',
        ],
        [
            'label' => 'Fidelizacion',
            'href' => route('loyalty.index'),
            'active' => request()->routeIs('loyalty.*'),
            'visible' => $hasLoyaltyModule && $hasLoyaltyFeature && $canManageLoyalty,
            'icon' => 'loyalty',
        ],
        [
            'label' => 'Reportes',
            'href' => route('reports.index'),
            'active' => request()->routeIs('reports.*'),
            'visible' => $hasReportsModule && $canViewReports,
            'icon' => 'reports',
        ],
        [
            'label' => 'Admin',
            'href' => $adminRoute,
            'active' => request()->routeIs('admin.*'),
            'visible' => $canManageSettings || $canManageRoles,
            'icon' => 'admin',
        ],
        [
            'label' => 'Plataforma',
            'href' => route('platform.companies'),
            'active' => request()->routeIs('platform.*'),
            'visible' => auth()->user()?->is_platform_admin ?? false,
            'icon' => 'admin',
        ],
    ])->filter(fn (array $item) => $item['visible'])->values();
@endphp

<div>
    <div
        x-show="sidebarOpen"
        x-transition.opacity
        class="fixed inset-0 z-30 bg-stone-950/40 lg:hidden"
        @click="sidebarOpen = false"
    ></div>

    <aside
        class="fixed inset-y-0 left-0 z-40 flex w-72 flex-col border-r border-gray-200 bg-white shadow-xl transition-all duration-300 lg:static lg:shrink-0 lg:translate-x-0 lg:shadow-none"
        :class="(sidebarOpen ? 'translate-x-0 ' : '-translate-x-full lg:translate-x-0 ') + (sidebarCollapsed ? 'lg:w-24' : 'lg:w-72')"
    >
        <div class="flex items-center justify-between border-b border-gray-200 px-5 py-5" :class="sidebarCollapsed ? 'lg:px-4' : ''">
            <a href="{{ route('dashboard') }}" class="flex min-w-0 items-center gap-3">
                <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-stone-950 text-white">
                    <x-application-logo class="h-7 w-auto fill-current" />
                </div>

                <div class="min-w-0" :class="sidebarCollapsed ? 'lg:hidden' : ''">
                    <p class="truncate text-sm font-semibold uppercase tracking-[0.28em] text-blue-700">{{ \App\Models\PlatformSetting::appName() }}</p>
                    <p class="truncate text-sm text-gray-500">Operacion central</p>
                </div>
            </a>

            <button
                type="button"
                class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-gray-200 text-gray-500 transition hover:border-blue-300 hover:text-blue-700 lg:hidden"
                @click="sidebarOpen = false"
            >
                <span class="sr-only">Cerrar menu</span>
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6L6 18" />
                </svg>
            </button>
        </div>

        <div class="border-b border-gray-200 px-5 py-5" :class="sidebarCollapsed ? 'lg:px-4' : ''">
            <a
                href="{{ route('companies.select') }}"
               
                class="flex items-center gap-3 rounded-[24px] border border-amber-200 bg-amber-50 px-4 py-4 text-gray-900 transition hover:border-blue-300 hover:bg-amber-100/70"
                @click="sidebarOpen = false"
                title="{{ $currentCompanyName ?: 'Empresa activa' }}"
            >
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-amber-500 text-sm font-semibold text-white">
                    {{ $companyInitials }}
                </div>

                <div class="min-w-0" :class="sidebarCollapsed ? 'lg:hidden' : ''">
                    <p class="text-xs font-semibold uppercase tracking-[0.28em] text-blue-700">Empresa activa</p>
                    <p class="truncate text-sm font-medium text-gray-800">{{ $currentCompanyName ?: 'Sin empresa' }}</p>
                </div>
            </a>
        </div>

        <nav class="flex-1 space-y-2 overflow-y-auto px-4 py-5">
            @foreach ($navigationItems as $item)
                <a
                    href="{{ $item['href'] }}"
                   
                    class="{{ $item['active'] ? 'bg-stone-950 text-white shadow-lg shadow-stone-950/10' : 'text-gray-600 hover:bg-gray-100 hover:text-stone-950' }} flex items-center gap-3 rounded-lg px-4 py-3 transition"
                    :class="sidebarCollapsed ? 'lg:justify-center lg:px-3' : ''"
                    @click="sidebarOpen = false"
                    title="{{ $item['label'] }}"
                >
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg {{ $item['active'] ? 'bg-white/10 text-white' : 'bg-stone-200/70 text-gray-700' }}">
                        @switch($item['icon'])
                            @case('dashboard')
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 13h7V4H4zm9 7h7v-9h-7zm0-16v5h7V4zM4 20h7v-5H4z" /></svg>
                                @break
                            @case('catalog')
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6.5A2.5 2.5 0 016.5 4H20v14H6.5A2.5 2.5 0 014 15.5z" /><path stroke-linecap="round" stroke-linejoin="round" d="M8 4v14" /></svg>
                                @break
                            @case('purchases')
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5h2l2.7 10.2A2 2 0 009.63 17H18a2 2 0 001.93-1.48L21 8H7" /><circle cx="10" cy="20" r="1" /><circle cx="18" cy="20" r="1" /></svg>
                                @break
                            @case('sales')
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M7 7h10M7 12h10M7 17h6" /><rect x="4" y="3" width="16" height="18" rx="2" /></svg>
                                @break
                            @case('inventory')
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7l9-4 9 4-9 4z" /><path stroke-linecap="round" stroke-linejoin="round" d="M3 7v10l9 4 9-4V7" /><path stroke-linecap="round" stroke-linejoin="round" d="M12 11v10" /></svg>
                                @break
                            @case('cash')
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="6" width="18" height="12" rx="2" /><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M8 14h.01M12 14h4" /></svg>
                                @break
                            @case('credit')
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 2v20M17 5H9.5a3.5 3.5 0 000 7H14.5a3.5 3.5 0 010 7H6" /></svg>
                                @break
                            @case('promotions')
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M20 12V7a2 2 0 00-2-2h-5l-2-2-2 2H6a2 2 0 00-2 2v5l-2 2 2 2v1a2 2 0 002 2h5l2 2 2-2h3a2 2 0 002-2v-1l2-2z" /></svg>
                                @break
                            @case('loyalty')
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21s-6.5-4.35-9-8.18C.3 8.96 2.1 4 6.66 4c2.02 0 3.29 1.16 4.02 2.3C11.4 5.16 12.67 4 14.69 4 19.25 4 21.05 8.96 21 12.82 18.5 16.65 12 21 12 21z" /></svg>
                                @break
                            @case('reports')
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M6 20V10M12 20V4M18 20v-7" /></svg>
                                @break
                            @case('admin')
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8a4 4 0 100 8 4 4 0 000-8zm8.94 4a7.94 7.94 0 00-.14-1.5l2.03-1.58-2-3.46-2.42.98a8.22 8.22 0 00-2.6-1.5L15.5 2h-4l-.31 2.94a8.22 8.22 0 00-2.6 1.5l-2.42-.98-2 3.46 2.03 1.58A8.58 8.58 0 003.06 12c0 .51.05 1 .14 1.5l-2.03 1.58 2 3.46 2.42-.98a8.22 8.22 0 002.6 1.5L11.5 22h4l.31-2.94a8.22 8.22 0 002.6-1.5l2.42.98 2-3.46-2.03-1.58c.09-.5.14-.99.14-1.5z" /></svg>
                                @break
                        @endswitch
                    </span>

                    <span class="truncate text-sm font-medium" :class="sidebarCollapsed ? 'lg:hidden' : ''">{{ $item['label'] }}</span>
                </a>
            @endforeach
        </nav>

        <div class="border-t border-gray-200 p-4">
            <div class="rounded-[26px] border border-gray-200 bg-gray-50 p-4" :class="sidebarCollapsed ? 'lg:px-3' : ''">
                <div class="flex items-center gap-3">
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-stone-950 text-sm font-semibold text-white">
                        {{ $userInitial }}
                    </div>

                    <div class="min-w-0" :class="sidebarCollapsed ? 'lg:hidden' : ''">
                        <div class="truncate text-sm font-semibold text-gray-900" x-data="{{ json_encode(['name' => auth()->user()->name]) }}" x-text="name" x-on:profile-updated.window="name = $event.detail.name"></div>
                        <div class="truncate text-xs text-gray-500">{{ $usernameHandle }}</div>
                    </div>
                </div>

                <div class="mt-4 space-y-2" :class="sidebarCollapsed ? 'lg:hidden' : ''">
                    <a href="{{ route('profile') }}" class="flex items-center justify-between rounded-lg px-3 py-2 text-sm text-gray-600 transition hover:bg-white hover:text-stone-950" @click="sidebarOpen = false">
                        <span>Perfil</span>
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 6l6 6-6 6" /></svg>
                    </a>

                    <a href="{{ route('companies.select') }}" class="flex items-center justify-between rounded-lg px-3 py-2 text-sm text-gray-600 transition hover:bg-white hover:text-stone-950" @click="sidebarOpen = false">
                        <span>Cambiar empresa</span>
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 6l6 6-6 6" /></svg>
                    </a>

                    <button wire:click="logout" type="button" class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-sm text-rose-600 transition hover:bg-rose-50">
                        <span>Cerrar sesion</span>
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17l5-5-5-5M20 12H9" /><path stroke-linecap="round" stroke-linejoin="round" d="M11 19H6a2 2 0 01-2-2V7a2 2 0 012-2h5" /></svg>
                    </button>
                </div>

                <div class="mt-4 hidden lg:block" x-show="sidebarCollapsed">
                    <div class="flex flex-col items-center gap-2">
                        <a href="{{ route('profile') }}" class="inline-flex h-11 w-11 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-600 transition hover:border-blue-300 hover:text-blue-700" title="Perfil">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M20 21a8 8 0 00-16 0" /><circle cx="12" cy="7" r="4" /></svg>
                        </a>

                        <a href="{{ route('companies.select') }}" class="inline-flex h-11 w-11 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-600 transition hover:border-blue-300 hover:text-blue-700" title="Cambiar empresa">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7l8-4 8 4-8 4z" /><path stroke-linecap="round" stroke-linejoin="round" d="M4 12l8 4 8-4" /><path stroke-linecap="round" stroke-linejoin="round" d="M4 17l8 4 8-4" /></svg>
                        </a>

                        <button wire:click="logout" type="button" class="inline-flex h-11 w-11 items-center justify-center rounded-lg border border-rose-200 bg-rose-50 text-rose-600 transition hover:border-rose-300 hover:bg-rose-100" title="Cerrar sesion">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17l5-5-5-5M20 12H9" /><path stroke-linecap="round" stroke-linejoin="round" d="M11 19H6a2 2 0 01-2-2V7a2 2 0 012-2h5" /></svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </aside>
</div>
