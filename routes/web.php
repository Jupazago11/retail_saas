<?php

use App\Http\Controllers\Sales\SaleTicketController;
use App\Http\Controllers\Admin\ExportAuditLogsController;
use App\Http\Controllers\Reports\ExportOperationalReportController;
use App\Livewire\Admin\AuditLogsPage;
use App\Livewire\Admin\BundlesPage;
use App\Livewire\Admin\CompanyStructurePage;
use App\Livewire\Admin\CouponsPage;
use App\Livewire\Admin\PlanOverridesPage;
use App\Livewire\Admin\RolesPage;
use App\Livewire\Admin\SettingsPage;
use App\Livewire\Admin\SubscriptionPage;
use App\Livewire\Platform\CompaniesPage as PlatformCompaniesPage;
use App\Livewire\Platform\DashboardPage as PlatformDashboardPage;
use App\Livewire\Platform\SubscriptionsPage as PlatformSubscriptionsPage;
use App\Livewire\Platform\PlansPage as PlatformPlansPage;
use App\Livewire\Platform\PlatformCouponsPage;
use App\Livewire\Platform\UsersPage as PlatformUsersPage;
use App\Livewire\Platform\PlatformSettingsPage;
use App\Livewire\Cash\CashSessionsPage;
use App\Livewire\Credit\CreditAccountsPage;
use App\Livewire\Credit\CustomerImportsPage;
use App\Livewire\Loyalty\LoyaltyAccountsPage;
use App\Livewire\Inventory\InventoryAdjustmentImportsPage;
use App\Livewire\Inventory\InventoryAdjustmentPage;
use App\Livewire\Company\SelectCompanyPage;
use App\Livewire\Customers\CustomersPage;
use App\Livewire\Masters\BrandsPage;
use App\Livewire\Masters\CategoriesPage;
use App\Livewire\Masters\UnitsPage;
use App\Livewire\Promotions\PromotionsPage;
use App\Livewire\Purchases\PayablesPage;
use App\Livewire\Purchases\PurchasesPage;
use App\Livewire\Purchases\PurchaseImportsPage;
use App\Livewire\Purchases\SupplierImportsPage;
use App\Livewire\Purchases\SuppliersPage;
use App\Livewire\Sales\PosPage;
use App\Livewire\Sales\SalesPage;
use App\Livewire\Products\ProductsPage;
use App\Livewire\Products\ProductImportsPage;
use App\Livewire\Products\AttributesPage;
use App\Livewire\Products\ProductPresentationsPage;
use App\Livewire\Products\ProductVariantsPage;
use App\Livewire\Reports\OperationalReportsPage;
use App\Services\Tenancy\CurrentCompany;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');

Route::middleware('auth')->group(function () {
    Route::get('companies/select', SelectCompanyPage::class)
        ->name('companies.select');

    Route::view('subscription/pending', 'livewire.pages.subscription-pending')
        ->name('subscription.pending');

    Route::middleware('verified')->prefix('platform')->name('platform.')->group(function () {
        Route::get('/',            PlatformDashboardPage::class)  ->name('dashboard');
        Route::get('companies',    PlatformCompaniesPage::class)  ->name('companies');
        Route::get('subscriptions',PlatformSubscriptionsPage::class)->name('subscriptions');
        Route::get('plans',        PlatformPlansPage::class)      ->name('plans');
        Route::get('coupons',      PlatformCouponsPage::class)    ->name('coupons');
        Route::get('users',        PlatformUsersPage::class)      ->name('users');
        Route::get('settings',     PlatformSettingsPage::class)   ->name('settings');
    });

    Route::get('dashboard', function (CurrentCompany $currentCompany) {
        return view('dashboard', [
            'company' => $currentCompany->company(),
        ]);
    })
        ->middleware(['verified', 'company.context'])
        ->name('dashboard');

    Route::middleware(['verified', 'company.context', 'subscription.active'])->group(function () {
        Route::get('masters/categories', CategoriesPage::class)
            ->middleware('company.permission:masters.view')
            ->name('masters.categories');

        Route::get('masters/brands', BrandsPage::class)
            ->middleware('company.permission:masters.view')
            ->name('masters.brands');

        Route::get('masters/units', UnitsPage::class)
            ->middleware('company.permission:masters.view')
            ->name('masters.units');

        Route::get('products', ProductsPage::class)
            ->middleware('company.permission:products.view')
            ->name('products.index');

        Route::get('products/imports', ProductImportsPage::class)
            ->middleware('company.permission:products.create')
            ->name('products.imports');

        Route::get('inventory', InventoryAdjustmentPage::class)
            ->middleware('company.permission:inventory.adjust')
            ->name('inventory.index');

        Route::get('inventory/imports', InventoryAdjustmentImportsPage::class)
            ->middleware('company.permission:inventory.adjust')
            ->name('inventory.imports');

        Route::get('products/presentations', ProductPresentationsPage::class)
            ->middleware('company.permission:products.view')
            ->name('products.presentations');

        Route::get('products/attributes', AttributesPage::class)
            ->middleware('company.permission:products.view')
            ->name('products.attributes');

        Route::get('products/variants', ProductVariantsPage::class)
            ->middleware('company.permission:products.view')
            ->name('products.variants');

        Route::get('purchases', PurchasesPage::class)
            ->middleware('company.permission:purchases.view')
            ->name('purchases.index');

        Route::get('purchases/imports', PurchaseImportsPage::class)
            ->middleware('company.permission:purchases.create')
            ->name('purchases.imports');

        Route::get('purchases/suppliers', SuppliersPage::class)
            ->middleware('company.permission:suppliers.view')
            ->name('purchases.suppliers');

        Route::get('purchases/suppliers/imports', SupplierImportsPage::class)
            ->middleware('company.permission:suppliers.manage')
            ->name('purchases.suppliers.imports');

        Route::get('purchases/payables', PayablesPage::class)
            ->middleware('company.permission:payables.view')
            ->name('purchases.payables');

        Route::get('sales', SalesPage::class)
            ->middleware('company.permission:sales.view')
            ->name('sales.index');

        Route::get('sales/pos', PosPage::class)
            ->middleware('company.permission:sales.create')
            ->name('sales.pos');

Route::get('cash/sessions', CashSessionsPage::class)
            ->name('cash.sessions');

        Route::get('customers', CustomersPage::class)
            ->middleware('company.permission:customers.view')
            ->name('customers.index');

        Route::get('credit', CreditAccountsPage::class)
            ->name('credit.index');

        Route::get('credit/customers/imports', CustomerImportsPage::class)
            ->middleware('company.permission:credit.manage')
            ->name('credit.customers.imports');

        Route::get('promotions', PromotionsPage::class)
            ->middleware('company.permission:promotions.manage')
            ->name('promotions.index');

        Route::get('loyalty', LoyaltyAccountsPage::class)
            ->middleware('company.permission:loyalty.manage')
            ->name('loyalty.index');

        Route::get('sales/{sale}/ticket', SaleTicketController::class)
            ->middleware('company.permission:sales.view')
            ->name('sales.ticket');

        Route::get('reports', OperationalReportsPage::class)
            ->middleware('company.permission:reports.view')
            ->name('reports.index');

        Route::get('reports/export', ExportOperationalReportController::class)
            ->middleware('company.permission:reports.view')
            ->name('reports.export');

        Route::get('admin/audit-logs', AuditLogsPage::class)
            ->middleware('company.permission:settings.manage')
            ->name('admin.audit-logs');

        Route::get('admin/audit-logs/export', ExportAuditLogsController::class)
            ->middleware('company.permission:settings.manage')
            ->name('admin.audit-logs.export');

        Route::get('admin/settings', SettingsPage::class)
            ->middleware('company.permission:settings.manage')
            ->name('admin.settings');

        Route::get('admin/subscription', SubscriptionPage::class)
            ->middleware('company.permission:settings.manage')
            ->name('admin.subscription');

        Route::get('admin/bundles', BundlesPage::class)
            ->middleware('company.permission:settings.manage')
            ->name('admin.bundles');

        Route::get('admin/coupons', CouponsPage::class)
            ->middleware('company.permission:settings.manage')
            ->name('admin.coupons');

        Route::get('admin/overrides', PlanOverridesPage::class)
            ->middleware('company.permission:settings.manage')
            ->name('admin.overrides');

        Route::get('admin/structure', CompanyStructurePage::class)
            ->middleware('company.permission:settings.manage')
            ->name('admin.structure');

        Route::get('admin/roles', RolesPage::class)
            ->middleware('company.permission:roles.manage')
            ->name('admin.roles');
    });

    Route::view('profile', 'profile')
        ->name('profile');
});

require __DIR__.'/auth.php';
