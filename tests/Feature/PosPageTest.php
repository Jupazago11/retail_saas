<?php

namespace Tests\Feature;

use App\Actions\Companies\CreateCompany;
use App\Actions\Customers\CreateCustomer;
use App\Actions\Inventory\CreateInventoryAdjustment;
use App\Actions\Cash\OpenCashSession;
use App\Actions\Promotions\CreatePromotion;
use App\Actions\Sales\CreateSale;
use App\Enums\InventoryAdjustmentType;
use App\Enums\PromotionDiscountType;
use App\Enums\PromotionTargetType;
use App\Enums\PromotionType;
use App\Enums\RecordStatus;
use App\Enums\SaleStatus;
use App\Livewire\Sales\PosPage;
use App\Models\Branch;
use App\Models\CashRegister;
use App\Models\Category;
use App\Models\CompanyRole;
use App\Models\Sale;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\LoyaltyMovement;
use App\Services\Settings\CompanySettings;
use App\Services\Tenancy\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithCompanyPlans;
use Tests\TestCase;

class PosPageTest extends TestCase
{
    use InteractsWithCompanyPlans;
    use RefreshDatabase;

    public function test_pos_page_can_create_confirmed_sale_and_expose_last_ticket_link(): void
    {
        [$owner, $company, $branch, $warehouse, $cashRegister, $product] = $this->posFixture();
        app(CompanySettings::class)->set($company, 'pos', 'sale_document_prefix', 'POS-');
        $cashSession = app(OpenCashSession::class)->handle($company, [
            'branch_id' => $branch->id,
            'cash_register_id' => $cashRegister->id,
            'opened_by' => $owner->id,
            'opening_amount' => '50000',
        ]);

        $this->actingAs($owner);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        Livewire::test(PosPage::class)
            ->set('branchId', $branch->id)
            ->set('warehouseId', $warehouse->id)
            ->set('cashRegisterId', $cashRegister->id)
            ->set('cashSessionId', $cashSession->id)
            ->set('saleStatus', SaleStatus::Confirmed->value)
            ->set('items.0.product_id', (string) $product->id)
            ->set('items.0.quantity', '2')
            ->set('items.0.unit_price', '1800')
            ->call('saveSale')
            ->assertHasNoErrors()
            ->assertSee('Abrir ticket de la ultima venta');

        $sale = Sale::query()->where('company_id', $company->id)->latest('id')->firstOrFail();
        $this->assertSame('POS-000001', $sale->document_number);

        $this->assertDatabaseHas('sales', [
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'cash_register_id' => $cashRegister->id,
            'document_number' => 'POS-000001',
            'status' => SaleStatus::Confirmed->value,
            'sale_type' => 'pos',
            'grand_total' => '3600.00',
        ]);
        $this->assertDatabaseHas('payments', [
            'company_id' => $company->id,
            'cash_session_id' => $cashSession->id,
            'payment_method_code' => 'cash',
            'amount' => '3600.00',
            'status' => 'confirmed',
        ]);
    }

    public function test_pos_page_can_register_confirmed_sale_payment_without_session_when_company_allows_it(): void
    {
        [$owner, $company, $branch, $warehouse, $cashRegister, $product] = $this->posFixture();
        app(CompanySettings::class)->set($company, 'pos', 'requires_open_cash_session', false);

        $this->actingAs($owner);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        Livewire::test(PosPage::class)
            ->set('branchId', $branch->id)
            ->set('warehouseId', $warehouse->id)
            ->set('cashRegisterId', $cashRegister->id)
            ->set('cashSessionId', null)
            ->set('saleStatus', SaleStatus::Confirmed->value)
            ->set('items.0.product_id', (string) $product->id)
            ->set('items.0.quantity', '1')
            ->set('items.0.unit_price', '1800')
            ->set('payments.0.amount', '1800')
            ->call('saveSale')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('payments', [
            'company_id' => $company->id,
            'cash_session_id' => null,
            'payment_method_code' => 'cash',
            'amount' => '1800.00',
            'status' => 'confirmed',
        ]);
    }

    public function test_pos_page_rejects_cash_session_from_another_register_context(): void
    {
        [$owner, $company, $branch, $warehouse, $cashRegister, $product] = $this->posFixture();

        $secondaryBranch = Branch::query()->create([
            'company_id' => $company->id,
            'name' => 'Sucursal Norte',
            'code' => 'NORTE',
            'status' => RecordStatus::Active->value,
        ]);
        Warehouse::query()->create([
            'company_id' => $company->id,
            'branch_id' => $secondaryBranch->id,
            'name' => 'Bodega Norte',
            'code' => 'BOD-NORTE',
            'status' => RecordStatus::Active->value,
        ]);
        $secondaryRegister = CashRegister::query()->create([
            'company_id' => $company->id,
            'branch_id' => $secondaryBranch->id,
            'name' => 'Caja Norte',
            'code' => 'CAJA-NORTE',
            'status' => RecordStatus::Active->value,
        ]);
        $foreignSession = app(OpenCashSession::class)->handle($company, [
            'branch_id' => $secondaryBranch->id,
            'cash_register_id' => $secondaryRegister->id,
            'opened_by' => $owner->id,
            'opening_amount' => '25000',
        ]);

        $this->actingAs($owner);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        Livewire::test(PosPage::class)
            ->set('branchId', $branch->id)
            ->set('warehouseId', $warehouse->id)
            ->set('cashRegisterId', $cashRegister->id)
            ->set('cashSessionId', $foreignSession->id)
            ->set('saleStatus', SaleStatus::Confirmed->value)
            ->set('items.0.product_id', (string) $product->id)
            ->set('items.0.quantity', '1')
            ->set('items.0.unit_price', '1800')
            ->call('saveSale')
            ->assertHasErrors(['cashSessionId']);

        $this->assertDatabaseMissing('payments', [
            'company_id' => $company->id,
            'cash_session_id' => $foreignSession->id,
            'status' => 'confirmed',
        ]);
    }

    public function test_pos_page_can_redeem_loyalty_points_on_confirmed_sale(): void
    {
        [$owner, $company, $branch, $warehouse, $cashRegister, $product] = $this->posFixture();
        app(CompanySettings::class)->set($company, 'loyalty', 'loyalty_enabled', true);
        app(CompanySettings::class)->set($company, 'loyalty', 'points_rate', '1');
        $customer = app(CreateCustomer::class)->handle($company, [
            'first_name' => 'Cliente',
            'last_name' => 'Fiel',
            'loyalty_enabled' => true,
        ]);
        $customer->loyaltyAccount()->firstOrFail()->update([
            'points_balance' => '1000.0000',
        ]);
        LoyaltyMovement::query()->create([
            'company_id' => $company->id,
            'loyalty_account_id' => $customer->loyaltyAccount->id,
            'sale_id' => null,
            'movement_type' => 'earn',
            'points' => '1000.0000',
            'cash_equivalent' => '1000.00',
            'balance_after' => '1000.0000',
            'occurred_at' => now()->subDay(),
        ]);

        $cashSession = app(OpenCashSession::class)->handle($company, [
            'branch_id' => $branch->id,
            'cash_register_id' => $cashRegister->id,
            'opened_by' => $owner->id,
            'opening_amount' => '50000',
        ]);

        $this->actingAs($owner);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        Livewire::test(PosPage::class)
            ->set('branchId', $branch->id)
            ->set('warehouseId', $warehouse->id)
            ->set('cashRegisterId', $cashRegister->id)
            ->set('cashSessionId', $cashSession->id)
            ->set('customerId', $customer->id)
            ->set('saleStatus', SaleStatus::Confirmed->value)
            ->set('loyaltyPointsToRedeem', '1000')
            ->set('items.0.product_id', (string) $product->id)
            ->set('items.0.quantity', '1')
            ->set('items.0.unit_price', '1800')
            ->call('saveSale')
            ->assertHasNoErrors()
            ->assertSee('Redencion');

        $this->assertDatabaseHas('sales', [
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'grand_total' => '800.00',
            'discount_total' => '1000.00',
        ]);
        $this->assertDatabaseHas('loyalty_movements', [
            'company_id' => $company->id,
            'loyalty_account_id' => $customer->loyaltyAccount->id,
            'movement_type' => 'redeem',
            'points' => '1000.0000',
            'cash_equivalent' => '1000.00',
        ]);
        $this->assertDatabaseHas('payments', [
            'company_id' => $company->id,
            'cash_session_id' => $cashSession->id,
            'amount' => '800.00',
            'status' => 'confirmed',
        ]);
    }

    public function test_pos_page_can_edit_draft_sale_and_confirm_it(): void
    {
        [$owner, $company, $branch, $warehouse, $cashRegister, $product] = $this->posFixture();
        $cashSession = app(OpenCashSession::class)->handle($company, [
            'branch_id' => $branch->id,
            'cash_register_id' => $cashRegister->id,
            'opened_by' => $owner->id,
            'opening_amount' => '50000',
        ]);

        $draftSale = \App\Actions\Sales\CreateSale::class;
        $draftSale = app($draftSale)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'cash_register_id' => $cashRegister->id,
            'user_id' => $owner->id,
            'status' => SaleStatus::Draft->value,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => '1',
                    'unit_price' => '1800',
                ],
            ],
        ]);

        $this->actingAs($owner);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        Livewire::test(PosPage::class)
            ->call('loadDraftSaleForEditing', $draftSale->id)
            ->assertSet('editingSaleId', $draftSale->id)
            ->set('cashSessionId', $cashSession->id)
            ->set('saleStatus', SaleStatus::Confirmed->value)
            ->set('items.0.quantity', '3')
            ->set('items.0.unit_price', '1900')
            ->call('saveSale')
            ->assertHasNoErrors()
            ->assertSee('Abrir ticket de la ultima venta');

        $draftSale->refresh();

        $this->assertSame(SaleStatus::Confirmed->value, $draftSale->status);
        $this->assertSame('5700.00', (string) $draftSale->grand_total);
        $this->assertNotNull($draftSale->posted_to_inventory_at);
        $this->assertDatabaseHas('payments', [
            'sale_id' => $draftSale->id,
            'cash_session_id' => $cashSession->id,
            'amount' => '5700.00',
            'status' => 'confirmed',
        ]);
    }

    public function test_pos_page_can_load_confirmed_sale_for_modification_and_confirm_replacement(): void
    {
        [$owner, $company, $branch, $warehouse, $cashRegister, $product] = $this->posFixture();
        $cashSession = app(OpenCashSession::class)->handle($company, [
            'branch_id' => $branch->id,
            'cash_register_id' => $cashRegister->id,
            'opened_by' => $owner->id,
            'opening_amount' => '50000',
        ]);

        $originalSale = app(CreateSale::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'cash_register_id' => $cashRegister->id,
            'user_id' => $owner->id,
            'status' => SaleStatus::Confirmed->value,
            'items' => [
                ['product_id' => $product->id, 'quantity' => '2', 'unit_price' => '1800'],
            ],
        ]);

        $this->actingAs($owner);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        Livewire::test(PosPage::class)
            ->call('loadSaleForModification', $originalSale->id)
            ->assertSet('modifyingSaleId', $originalSale->id)
            ->assertSet('items.0.quantity', '2.000000')
            ->set('cashSessionId', $cashSession->id)
            ->set('items.0.quantity', '3')
            ->call('saveSale')
            ->assertHasNoErrors();

        $originalSale->refresh();
        $this->assertSame(SaleStatus::Cancelled->value, $originalSale->status);

        $newSale = Sale::query()->where('replaces_sale_id', $originalSale->id)->first();
        $this->assertNotNull($newSale);
        $this->assertSame(SaleStatus::Confirmed->value, $newSale->status);
        $this->assertSame('3.000000', $newSale->items->first()->quantity);
    }

    public function test_pos_page_rejects_loading_draft_sale_for_modification(): void
    {
        [$owner, $company, $branch, $warehouse, $cashRegister, $product] = $this->posFixture();

        $draftSale = app(CreateSale::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'cash_register_id' => $cashRegister->id,
            'user_id' => $owner->id,
            'status' => SaleStatus::Draft->value,
            'items' => [
                ['product_id' => $product->id, 'quantity' => '1', 'unit_price' => '1800'],
            ],
        ]);

        $this->actingAs($owner);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        Livewire::test(PosPage::class)
            ->call('loadSaleForModification', $draftSale->id)
            ->assertSet('modifyingSaleId', null);
    }

    public function test_pos_page_rejects_loading_credit_sale_for_modification(): void
    {
        [$owner, $company, $branch, $warehouse, $cashRegister, $product] = $this->posFixture();
        app(CompanySettings::class)->set($company, 'credit', 'credit_enabled', true);
        $customer = app(CreateCustomer::class)->handle($company, [
            'first_name' => 'Cliente',
            'last_name' => 'Credito',
            'credit_enabled' => true,
            'credit_limit' => '100000',
        ]);

        $creditSale = app(CreateSale::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'customer_id' => $customer->id,
            'user_id' => $owner->id,
            'sale_type' => 'credit',
            'status' => SaleStatus::Confirmed->value,
            'items' => [
                ['product_id' => $product->id, 'quantity' => '1', 'unit_price' => '1800'],
            ],
        ]);

        $this->actingAs($owner);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        Livewire::test(PosPage::class)
            ->call('loadSaleForModification', $creditSale->id)
            ->assertSet('modifyingSaleId', null);
    }

    public function test_pos_page_resolves_new_customer_from_nickname_without_polluting_document_number(): void
    {
        [$owner, $company, $branch, $warehouse, $cashRegister, $product] = $this->posFixture();
        $cashSession = app(OpenCashSession::class)->handle($company, [
            'branch_id' => $branch->id,
            'cash_register_id' => $cashRegister->id,
            'opened_by' => $owner->id,
            'opening_amount' => '50000',
        ]);

        $this->actingAs($owner);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        Livewire::test(PosPage::class)
            ->set('branchId', $branch->id)
            ->set('warehouseId', $warehouse->id)
            ->set('cashRegisterId', $cashRegister->id)
            ->set('cashSessionId', $cashSession->id)
            ->set('saleStatus', SaleStatus::Confirmed->value)
            ->set('items.0.product_id', (string) $product->id)
            ->set('items.0.quantity', '1')
            ->set('items.0.unit_price', '1800')
            ->set('paymentCustomerDocument', 'Ñato')
            ->call('saveSale')
            ->assertHasNoErrors();

        $sale = Sale::query()->where('company_id', $company->id)->firstOrFail();
        $person = $sale->customer->person;

        $this->assertSame('Ñato', $person->first_name);
        $this->assertNull($person->document_number);
        $this->assertSame('Ñato', $person->full_name);
    }

    public function test_pos_page_reuses_same_customer_for_repeated_nickname(): void
    {
        [$owner, $company, $branch, $warehouse, $cashRegister, $product] = $this->posFixture();
        $cashSession = app(OpenCashSession::class)->handle($company, [
            'branch_id' => $branch->id,
            'cash_register_id' => $cashRegister->id,
            'opened_by' => $owner->id,
            'opening_amount' => '50000',
        ]);

        $this->actingAs($owner);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        foreach ([1, 2] as $attempt) {
            Livewire::test(PosPage::class)
                ->set('branchId', $branch->id)
                ->set('warehouseId', $warehouse->id)
                ->set('cashRegisterId', $cashRegister->id)
                ->set('cashSessionId', $cashSession->id)
                ->set('saleStatus', SaleStatus::Confirmed->value)
                ->set('items.0.product_id', (string) $product->id)
                ->set('items.0.quantity', '1')
                ->set('items.0.unit_price', '1800')
                ->set('paymentCustomerDocument', 'Ñato')
                ->call('saveSale')
                ->assertHasNoErrors();
        }

        $sales = Sale::query()->where('company_id', $company->id)->get();
        $this->assertCount(2, $sales);
        $this->assertSame($sales[0]->customer_id, $sales[1]->customer_id);
        $this->assertSame(1, \App\Models\Customer::query()->where('company_id', $company->id)->count());
    }

    public function test_pos_page_shows_real_preview_with_promotions_before_confirming(): void
    {
        [$owner, $company, $branch, $warehouse, $cashRegister, $product] = $this->posFixture();

        app(CreatePromotion::class)->handle($company, [
            'name' => 'Promo preview',
            'promotion_type' => PromotionType::ProductDiscount->value,
            'discount_type' => PromotionDiscountType::Percentage->value,
            'discount_value' => '10',
            'targets' => [
                [
                    'target_type' => PromotionTargetType::Product->value,
                    'target_id' => $product->id,
                    'min_quantity' => '1',
                ],
            ],
        ]);

        $this->actingAs($owner);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        Livewire::test(PosPage::class)
            ->set('branchId', $branch->id)
            ->set('warehouseId', $warehouse->id)
            ->set('cashRegisterId', $cashRegister->id)
            ->set('items.0.product_id', (string) $product->id)
            ->set('items.0.quantity', '2')
            ->set('items.0.unit_price', '1800')
            ->assertSee('Promo preview')
            ->assertSee('3,240.00');
    }

    public function test_pos_page_can_add_product_from_lookup_input_using_barcode_and_enter(): void
    {
        [$owner, $company, $branch, $warehouse, $cashRegister, $product] = $this->posFixture();
        $product->update([
            'barcode' => '7701234567890',
        ]);

        $this->actingAs($owner);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        Livewire::test(PosPage::class)
            ->set('branchId', $branch->id)
            ->set('warehouseId', $warehouse->id)
            ->set('cashRegisterId', $cashRegister->id)
            ->call('submitProductLookup', '7701234567890')
            ->assertSet('productLookup', '')
            ->assertSet('items.0.product_id', (string) $product->id);
    }

    public function test_pos_page_selects_first_product_matching_partial_name_on_enter(): void
    {
        [$owner, $company, $branch, $warehouse, $cashRegister, $product] = $this->posFixture();

        $this->actingAs($owner);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        Livewire::test(PosPage::class)
            ->set('branchId', $branch->id)
            ->set('warehouseId', $warehouse->id)
            ->set('cashRegisterId', $cashRegister->id)
            ->call('submitProductLookup', 'arroz')
            ->assertSet('productLookup', '')
            ->assertSet('items.0.product_id', (string) $product->id);
    }

    public function test_pos_page_displays_product_options_while_typing_a_name(): void
    {
        [$owner, $company, $branch, $warehouse, $cashRegister, $product] = $this->posFixture();

        $this->actingAs($owner);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        Livewire::test(PosPage::class)
            ->set('branchId', $branch->id)
            ->set('warehouseId', $warehouse->id)
            ->set('cashRegisterId', $cashRegister->id)
            ->assertSee('pos-product-options', false)
            ->assertSee($product->name)
            ->assertSee('list="pos-product-options"', false)
            ->assertSee('data-pos-product-input', false)
            ->assertDontSee('wire:change="submitProductLookup(', false);
    }

    public function test_pos_page_route_is_forbidden_without_sales_create_permission(): void
    {
        [, $company] = $this->posFixture();
        $viewer = User::factory()->create();
        $companyRole = CompanyRole::query()->create([
            'company_id' => $company->id,
            'code' => 'sales_view_only',
            'display_name' => 'Solo consulta ventas',
            'status' => RecordStatus::Active->value,
        ]);

        $companyRole->permissions()->attach(
            Permission::query()->where('code', 'sales.view')->value('id')
        );

        $company->users()->attach($viewer->id, [
            'company_role' => 'sales_view_only',
            'company_role_id' => $companyRole->id,
            'status' => RecordStatus::Active->value,
            'joined_at' => now(),
        ]);

        $this->actingAs($viewer)
            ->withSession([CurrentCompany::SESSION_KEY => $company->id])
            ->get(route('sales.pos'))
            ->assertForbidden();
    }

    protected function posFixture(): array
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'POS Inicial SAS',
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
            'name' => 'Arroz POS',
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
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => '10',
                    'unit_cost' => '1000',
                ],
            ],
        ]);

        return [$owner, $company, $branch, $warehouse, $cashRegister, $product];
    }
}
