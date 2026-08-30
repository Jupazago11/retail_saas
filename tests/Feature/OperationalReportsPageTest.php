<?php

namespace Tests\Feature;

use App\Actions\Cash\CloseCashSession;
use App\Actions\Cash\OpenCashSession;
use App\Actions\Companies\CreateCompany;
use App\Actions\Credit\RegisterCreditPayment;
use App\Actions\Customers\CreateCustomer;
use App\Actions\Inventory\CreateInventoryAdjustment;
use App\Actions\Promotions\CreatePromotion;
use App\Actions\Sales\CreateSale;
use App\Actions\Sales\RegisterSalePayments;
use App\Enums\InventoryAdjustmentType;
use App\Enums\PromotionDiscountType;
use App\Enums\PromotionTargetType;
use App\Enums\PromotionType;
use App\Enums\RecordStatus;
use App\Enums\SaleStatus;
use App\Livewire\Reports\OperationalReportsPage;
use App\Models\Category;
use App\Models\CompanyRole;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use App\Services\Reports\OperationalReportService;
use App\Services\Settings\CompanySettings;
use App\Services\Tenancy\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithCompanyPlans;
use Tests\TestCase;

class OperationalReportsPageTest extends TestCase
{
    use InteractsWithCompanyPlans;
    use RefreshDatabase;

    public function test_reports_page_shows_operational_summary(): void
    {
        [$owner, $company, $branch, $warehouse, $cashRegister, $product] = $this->fixture();
        app(CompanySettings::class)->set($company, 'loyalty', 'loyalty_enabled', true);
        app(CompanySettings::class)->set($company, 'credit', 'credit_enabled', true);
        $customer = app(CreateCustomer::class)->handle($company, [
            'first_name' => 'Paola',
            'loyalty_enabled' => true,
            'credit_enabled' => true,
            'credit_limit' => '10000',
        ]);
        app(CreatePromotion::class)->handle($company, [
            'name' => 'Promo reporte',
            'promotion_type' => PromotionType::ProductDiscount->value,
            'discount_type' => PromotionDiscountType::Percentage->value,
            'discount_value' => '10',
            'targets' => [[
                'target_type' => PromotionTargetType::Product->value,
                'target_id' => $product->id,
                'min_quantity' => '1',
            ]],
        ]);

        $cashSession = app(OpenCashSession::class)->handle($company, [
            'branch_id' => $branch->id,
            'cash_register_id' => $cashRegister->id,
            'opened_by' => $owner->id,
            'opening_amount' => '50000',
        ]);

        $sale = app(CreateSale::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'cash_register_id' => $cashRegister->id,
            'customer_id' => $customer->id,
            'user_id' => $owner->id,
            'status' => SaleStatus::Confirmed->value,
            'items' => [[
                'product_id' => $product->id,
                'quantity' => '2',
                'unit_price' => '1800',
            ]],
        ]);

        app(RegisterSalePayments::class)->handle($company, $sale, [
            'cash_session_id' => $cashSession->id,
            'received_by' => $owner->id,
            'payments' => [[
                'payment_method_code' => 'cash',
                'amount' => (string) $sale->grand_total,
            ]],
        ]);

        $creditSale = app(CreateSale::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'cash_register_id' => $cashRegister->id,
            'customer_id' => $customer->id,
            'user_id' => $owner->id,
            'sale_type' => 'credit',
            'status' => SaleStatus::Confirmed->value,
            'items' => [[
                'product_id' => $product->id,
                'quantity' => '1',
                'unit_price' => '1800',
            ]],
        ]);

        $creditSale->update([
            'credit_due_at' => now()->subDays(10),
        ]);

        app(RegisterCreditPayment::class)->handle($company, $customer->creditAccount, [
            'cash_session_id' => $cashSession->id,
            'received_by' => $owner->id,
            'payment_method_code' => 'transfer',
            'amount' => '800',
            'reference' => 'ABONO-REP-1',
        ]);

        $this->actingAs($owner);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        Livewire::test(OperationalReportsPage::class)
            ->assertSee('Top vendidos')
            ->assertSee('Medios de pago')
            ->assertSee('Aging de cartera')
            ->assertSee('Promo reporte')
            ->assertSee('Arroz Reporte')
            ->assertSee('3.240')
            ->assertSee('Transferencia');

        $aging = app(OperationalReportService::class)->creditAging($company, [
            'date_from' => now()->startOfMonth()->format('Y-m-d'),
            'date_to' => now()->format('Y-m-d'),
            'branch_id' => $branch->id,
        ]);
        $overdueBucket = $aging->firstWhere('bucket_code', '1_30');

        $this->assertNotNull($overdueBucket);
        $this->assertSame(1, $overdueBucket['sales_count']);
        // El abono de RegisterCreditPayment es contra la cuenta en general,
        // no contra esta venta puntual (ver CreditAccountsPage::moraStatus()),
        // asi que el saldo de ESTA venta en el aging queda intacto en 1620.
        $this->assertSame('1.620', $overdueBucket['balance_total']);
    }

    public function test_reports_page_shows_cash_on_hand_trend_from_closed_sessions(): void
    {
        [$owner, $company, $branch, , $cashRegister] = $this->fixture();

        $session = app(OpenCashSession::class)->handle($company, [
            'branch_id' => $branch->id,
            'cash_register_id' => $cashRegister->id,
            'opened_by' => $owner->id,
            'opening_amount' => '100000',
        ]);

        app(CloseCashSession::class)->handle($company, $session, [
            'closed_by' => $owner->id,
            'closing_counted_amount' => '135000',
        ]);

        $this->actingAs($owner);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        $component = Livewire::test(OperationalReportsPage::class)
            ->assertSee('Efectivo en caja por dia');

        $trend = $component->instance()->cashOnHandTrend();
        $this->assertCount(1, $trend);
        $this->assertSame(now()->format('Y-m-d'), $trend->first()['date']);
        $this->assertSame(135000.0, $trend->first()['cash_on_hand_total']);
    }

    public function test_cash_on_hand_trend_excludes_sessions_still_open_and_respects_module_toggle(): void
    {
        [$owner, $company, $branch, , $cashRegister] = $this->fixture();

        app(OpenCashSession::class)->handle($company, [
            'branch_id' => $branch->id,
            'cash_register_id' => $cashRegister->id,
            'opened_by' => $owner->id,
            'opening_amount' => '50000',
        ]);

        $this->actingAs($owner);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        $component = Livewire::test(OperationalReportsPage::class);
        $this->assertCount(0, $component->instance()->cashOnHandTrend());

        app(CompanySettings::class)->set($company, 'cash', 'module_enabled', false);

        $this->assertFalse(Livewire::test(OperationalReportsPage::class)->instance()->cashEnabled());
    }

    public function test_reports_page_shows_purchases_versus_sales_contrast(): void
    {
        [$owner, $company, $branch, $warehouse] = $this->fixture();
        $supplier = app(\App\Actions\Suppliers\CreateSupplier::class)->handle($company, [
            'first_name' => 'Proveedor',
            'last_name' => 'Contraste',
        ]);

        $purchase = app(\App\Actions\Purchases\CreatePurchase::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'supplier_id' => $supplier->id,
            'status' => \App\Enums\PurchaseStatus::Confirmed->value,
            'total' => '9000',
        ]);
        app(\App\Actions\Purchases\RegisterPurchasePayment::class)->handle($company, $purchase, [
            'amount' => '9000',
            'payment_method_code' => 'transfer',
        ]);

        $this->actingAs($owner);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        Livewire::test(OperationalReportsPage::class)
            ->assertSee('Ingresos del periodo')
            ->assertSee('Gastos del periodo')
            ->assertSee('Compras')
            ->assertSee('Compras por dia')
            ->assertSee('De donde salio el dinero')
            ->assertSee('9.000')
            ->assertSee('Transferencia')
            ->assertSee('Proveedores')
            ->assertSee('Proveedor Contraste');
    }

    public function test_reports_route_is_forbidden_without_reports_permission(): void
    {
        [, $company] = $this->fixture();
        $viewer = User::factory()->create();
        $companyRole = CompanyRole::query()->create([
            'company_id' => $company->id,
            'code' => 'sales_only_no_reports',
            'display_name' => 'Sin reportes',
            'status' => RecordStatus::Active->value,
        ]);
        $companyRole->permissions()->attach(
            Permission::query()->where('code', 'sales.view')->value('id')
        );

        $company->users()->attach($viewer->id, [
            'company_role' => $companyRole->code,
            'company_role_id' => $companyRole->id,
            'status' => RecordStatus::Active->value,
            'joined_at' => now(),
        ]);

        $this->actingAs($viewer)
            ->withSession([CurrentCompany::SESSION_KEY => $company->id])
            ->get(route('reports.index'))
            ->assertForbidden();
    }

    protected function fixture(): array
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Reportes UI SAS',
        ]);
        $this->assignCompanyPlan($company, 'pro');
        $branch = $company->branches()->firstOrFail();
        $warehouse = $company->warehouses()->firstOrFail();
        $cashRegister = $company->cashRegisters()->firstOrFail();

        $unit = Unit::query()->create([
            'company_id' => $company->id,
            'code' => 'UND',
            'name' => 'Unidad',
            'precision_scale' => 0,
            'status' => RecordStatus::Active->value,
        ]);
        $category = Category::query()->create([
            'company_id' => $company->id,
            'name' => 'Abarrotes',
            'code' => 'ABA',
            'status' => RecordStatus::Active->value,
        ]);
        $product = Product::query()->create([
            'company_id' => $company->id,
            'category_id' => $category->id,
            'base_unit_id' => $unit->id,
            'name' => 'Arroz Reporte',
            'cost' => 1000,
            'price_1' => 1800,
            'tracks_inventory' => true,
            'minimum_stock' => 1,
            'status' => RecordStatus::Active->value,
        ]);

        app(CreateInventoryAdjustment::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'adjustment_type' => InventoryAdjustmentType::Increase->value,
            'reason' => 'Stock inicial',
            'items' => [[
                'product_id' => $product->id,
                'quantity' => '20',
                'unit_cost' => '1000',
            ]],
        ]);

        return [$owner, $company, $branch, $warehouse, $cashRegister, $product];
    }
}



