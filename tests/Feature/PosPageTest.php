<?php

namespace Tests\Feature;

use App\Actions\Cash\OpenCashSession;
use App\Actions\Companies\CreateCompany;
use App\Actions\Customers\CreateCustomer;
use App\Actions\Inventory\CreateInventoryAdjustment;
use App\Actions\Promotions\CreatePromotion;
use App\Actions\Sales\CancelSale;
use App\Actions\Sales\CreateSale;
use App\Enums\CreditMovementType;
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
use App\Models\CreditMovement;
use App\Models\Customer;
use App\Models\LoyaltyMovement;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
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
            ->assertSee('Ticket ultima venta');

        $sale = Sale::query()->where('company_id', $company->id)->latest('id')->firstOrFail();
        $this->assertSame('000001', $sale->document_number);

        $this->assertDatabaseHas('sales', [
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'cash_register_id' => $cashRegister->id,
            'document_number' => '000001',
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

    public function test_pos_page_flexible_price_product_charges_typed_total_with_quantity_one_and_no_inventory_movement(): void
    {
        [$owner, $company, $branch, $warehouse, $cashRegister] = $this->posFixture();
        $cashSession = app(OpenCashSession::class)->handle($company, [
            'branch_id' => $branch->id,
            'cash_register_id' => $cashRegister->id,
            'opened_by' => $owner->id,
            'opening_amount' => '50000',
        ]);

        $unit = Unit::query()->where('company_id', $company->id)->firstOrFail();
        $category = Category::query()->where('company_id', $company->id)->firstOrFail();
        $papa = Product::query()->create([
            'company_id' => $company->id,
            'category_id' => $category->id,
            'base_unit_id' => $unit->id,
            'name' => 'Papa',
            'cost' => 0,
            'price_1' => 0,
            'flexible_price' => true,
            'tracks_inventory' => false,
            'minimum_stock' => 0,
            'status' => RecordStatus::Active->value,
        ]);

        $this->actingAs($owner);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        // Aunque llegue una cantidad distinta de 1 (por ejemplo, un residuo
        // de un re-escaneo), precio flexible siempre debe guardarse con
        // cantidad 1 — el total lo define directamente unit_price.
        Livewire::test(PosPage::class)
            ->set('branchId', $branch->id)
            ->set('warehouseId', $warehouse->id)
            ->set('cashRegisterId', $cashRegister->id)
            ->set('cashSessionId', $cashSession->id)
            ->set('saleStatus', SaleStatus::Confirmed->value)
            ->set('items.0.product_id', (string) $papa->id)
            ->set('items.0.quantity', '3')
            ->set('items.0.unit_price', '4500')
            ->call('saveSale')
            ->assertHasNoErrors();

        $sale = Sale::query()->where('company_id', $company->id)->latest('id')->firstOrFail();

        $this->assertSame('4500.00', $sale->grand_total);
        $this->assertDatabaseHas('sale_items', [
            'sale_id' => $sale->id,
            'product_id' => $papa->id,
            'quantity' => '1.000000',
            'unit_price' => '4500.00',
        ]);
        $this->assertDatabaseMissing('inventory_movements', [
            'product_id' => $papa->id,
        ]);
    }

    public function test_pos_page_can_backdate_a_sale_when_checkbox_is_marked(): void
    {
        [$owner, $company, $branch, $warehouse, $cashRegister, $product] = $this->posFixture();

        $this->actingAs($owner);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        $pastDate = now()->subDays(2)->format('Y-m-d');

        Livewire::test(PosPage::class)
            ->set('branchId', $branch->id)
            ->set('warehouseId', $warehouse->id)
            ->set('cashRegisterId', $cashRegister->id)
            ->set('saleStatus', SaleStatus::Confirmed->value)
            ->set('items.0.product_id', (string) $product->id)
            ->set('items.0.quantity', '1')
            ->set('items.0.unit_price', '1800')
            ->set('backdateSale', true)
            ->set('soldAt', $pastDate)
            ->call('saveSale')
            ->assertHasNoErrors();

        $sale = Sale::query()->where('company_id', $company->id)->latest('id')->firstOrFail();
        $this->assertSame($pastDate, $sale->sold_at->format('Y-m-d'));
    }

    public function test_pos_page_hides_backdate_checkbox_and_ignores_it_without_the_permission(): void
    {
        [, $company, $branch, $warehouse, $cashRegister, $product] = $this->posFixture();

        $cashier = $this->createUserWithCompanyPermissions($company, 'sales_only', ['sales.create']);

        $this->actingAs($cashier);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        $pastDate = now()->subDays(2)->format('Y-m-d');

        Livewire::test(PosPage::class)
            ->set('branchId', $branch->id)
            ->set('warehouseId', $warehouse->id)
            ->set('cashRegisterId', $cashRegister->id)
            ->set('items.0.product_id', (string) $product->id)
            ->set('items.0.quantity', '1')
            ->set('items.0.unit_price', '1800')
            ->call('openPaymentModal')
            ->assertDontSee('Es de un dia anterior')
            ->set('backdateSale', true)
            ->assertSet('backdateSale', false)
            ->call('saveSale')
            ->assertHasNoErrors();

        $sale = Sale::query()->where('company_id', $company->id)->latest('id')->firstOrFail();
        $this->assertNotSame($pastDate, $sale->sold_at->format('Y-m-d'));
        $this->assertTrue($sale->sold_at->isToday());
    }

    public function test_pos_page_allows_backdating_with_the_explicit_permission(): void
    {
        [, $company, $branch, $warehouse, $cashRegister, $product] = $this->posFixture();

        $cashier = $this->createUserWithCompanyPermissions($company, 'sales_backdate', ['sales.create', 'sales.change_date']);

        $this->actingAs($cashier);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        $pastDate = now()->subDays(2)->format('Y-m-d');

        Livewire::test(PosPage::class)
            ->set('branchId', $branch->id)
            ->set('warehouseId', $warehouse->id)
            ->set('cashRegisterId', $cashRegister->id)
            ->set('items.0.product_id', (string) $product->id)
            ->set('items.0.quantity', '1')
            ->set('items.0.unit_price', '1800')
            ->call('openPaymentModal')
            ->assertSee('Es de un dia anterior')
            ->set('backdateSale', true)
            ->assertSet('backdateSale', true)
            ->set('soldAt', $pastDate)
            ->call('saveSale')
            ->assertHasNoErrors();

        $sale = Sale::query()->where('company_id', $company->id)->latest('id')->firstOrFail();
        $this->assertSame($pastDate, $sale->sold_at->format('Y-m-d'));
    }

    public function test_pos_page_defaults_sale_date_to_now_without_the_checkbox(): void
    {
        [$owner, $company, $branch, $warehouse, $cashRegister, $product] = $this->posFixture();

        $this->actingAs($owner);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        Livewire::test(PosPage::class)
            ->set('branchId', $branch->id)
            ->set('warehouseId', $warehouse->id)
            ->set('cashRegisterId', $cashRegister->id)
            ->set('saleStatus', SaleStatus::Confirmed->value)
            ->set('items.0.product_id', (string) $product->id)
            ->set('items.0.quantity', '1')
            ->set('items.0.unit_price', '1800')
            ->assertSet('backdateSale', false)
            ->call('saveSale')
            ->assertHasNoErrors();

        $sale = Sale::query()->where('company_id', $company->id)->latest('id')->firstOrFail();
        $this->assertTrue($sale->sold_at->isToday());
    }

    public function test_pos_page_can_register_confirmed_sale_payment_without_any_open_cash_session(): void
    {
        [$owner, $company, $branch, $warehouse, $cashRegister, $product] = $this->posFixture();

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
            ->assertDispatched('toast', function (string $eventName, array $params): bool {
                return ($params['type'] ?? null) === 'error';
            });

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
            ->assertHasNoErrors();

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

        $draftSale = CreateSale::class;
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
            ->assertSee('Ticket ultima venta');

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
            ->assertSet('items.0.quantity', '2')
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

    public function test_pos_page_preloads_original_date_when_modifying_a_backdated_sale(): void
    {
        [$owner, $company, $branch, $warehouse, $cashRegister, $product] = $this->posFixture();

        $originalSale = app(CreateSale::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'cash_register_id' => $cashRegister->id,
            'user_id' => $owner->id,
            'status' => SaleStatus::Confirmed->value,
            'items' => [
                ['product_id' => $product->id, 'quantity' => '1', 'unit_price' => '2000'],
            ],
        ]);
        $originalSale->update(['sold_at' => now()->subDays(3)]);

        $this->actingAs($owner);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        // Antes esto se reseteaba a "hoy" en silencio; ahora debe mantener el
        // dia original de la venta (el dueno de la empresa siempre puede
        // usar retrofecha, ver canBackdateSale()).
        Livewire::test(PosPage::class)
            ->call('loadSaleForModification', $originalSale->id)
            ->assertSet('backdateSale', true)
            ->assertSet('soldAt', now()->subDays(3)->format('Y-m-d'));
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

    public function test_pos_page_hides_credit_payment_method_when_plan_lacks_credit_module(): void
    {
        [$owner, $company, $branch, $warehouse, $cashRegister] = $this->posFixture();
        $this->assignCompanyPlan($company, 'basic');

        $this->actingAs($owner);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        $component = Livewire::test(PosPage::class)
            ->set('branchId', $branch->id)
            ->set('warehouseId', $warehouse->id)
            ->set('cashRegisterId', $cashRegister->id);

        $this->assertArrayNotHasKey('credit', $component->instance()->paymentMethodOptions());
    }

    public function test_pos_page_charges_only_the_credit_portion_of_a_mixed_payment(): void
    {
        [$owner, $company, $branch, $warehouse, $cashRegister, $product] = $this->posFixture();
        app(CompanySettings::class)->set($company, 'credit', 'credit_enabled', true);
        $customer = app(CreateCustomer::class)->handle($company, [
            'first_name' => 'Cliente',
            'last_name' => 'Mixto',
            'credit_enabled' => true,
            'credit_limit' => '100000',
        ]);
        $cashSession = app(OpenCashSession::class)->handle($company, [
            'branch_id' => $branch->id,
            'cash_register_id' => $cashRegister->id,
            'opened_by' => $owner->id,
            'opening_amount' => '50000',
        ]);

        $this->actingAs($owner);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        // Venta de 3600: 1000 en efectivo y el resto (2600) a credito.
        // El cliente se elige desde el buscador del modal (selectPaymentCustomer),
        // el mismo camino que antes dejaba sales.customer_id vacio.
        Livewire::test(PosPage::class)
            ->set('branchId', $branch->id)
            ->set('warehouseId', $warehouse->id)
            ->set('cashRegisterId', $cashRegister->id)
            ->set('cashSessionId', $cashSession->id)
            ->set('saleStatus', SaleStatus::Confirmed->value)
            ->set('items.0.product_id', (string) $product->id)
            ->set('items.0.quantity', '2')
            ->set('items.0.unit_price', '1800')
            ->call('selectPaymentCustomer', $customer->id)
            ->set('payments.0.payment_method_code', 'cash')
            ->set('payments.0.amount', '1000')
            ->call('addPaymentLine')
            ->set('payments.1.payment_method_code', 'credit')
            ->set('payments.1.amount', '2600')
            ->call('saveSale')
            ->assertHasNoErrors();

        $sale = Sale::query()->where('company_id', $company->id)->firstOrFail();
        $account = $customer->creditAccount()->firstOrFail()->fresh();

        $this->assertSame('pos', $sale->sale_type);
        $this->assertSame($customer->id, $sale->customer_id);
        $this->assertSame($account->id, $sale->credit_account_id);
        $this->assertNotNull($sale->credit_due_at);

        $this->assertDatabaseHas('payments', [
            'sale_id' => $sale->id,
            'payment_method_code' => 'cash',
            'amount' => '1000.00',
        ]);
        $this->assertDatabaseHas('payments', [
            'sale_id' => $sale->id,
            'payment_method_code' => 'credit',
            'amount' => '2600.00',
        ]);

        // Solo se carga al cupo la porcion a credito, no el total de la venta.
        $this->assertSame('2600.00', $account->balance_due);
        $this->assertSame('97400.00', $account->available_credit);
        $this->assertDatabaseHas('credit_movements', [
            'credit_account_id' => $account->id,
            'sale_id' => $sale->id,
            'movement_type' => CreditMovementType::SaleCharge->value,
            'amount' => '2600.00',
        ]);
        $this->assertSame(1, CreditMovement::query()->where('sale_id', $sale->id)->count());

        // Anular la venta debe revertir solo la porcion cargada al credito
        // (2600), no el grand_total completo (3600) — antes de este fix,
        // CancelSale reversaba siempre grand_total y esto habria lanzado
        // "El movimiento excede el saldo pendiente de la venta.".
        app(CancelSale::class)->handle($company, $sale, 'Prueba de anulacion mixta');

        $account = $account->fresh();
        $this->assertSame('0.00', $account->balance_due);
        $this->assertSame('100000.00', $account->available_credit);
        $this->assertDatabaseHas('credit_movements', [
            'credit_account_id' => $account->id,
            'sale_id' => $sale->id,
            'movement_type' => CreditMovementType::SaleCancellationAdjustment->value,
            'amount' => '2600.00',
        ]);
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
        $this->assertSame(1, Customer::query()->where('company_id', $company->id)->count());
    }

    public function test_pos_page_hides_freeze_option_and_warns_when_plan_lacks_frozen_sales(): void
    {
        [$owner, $company, $branch, $warehouse, $cashRegister, $product] = $this->posFixture();
        $this->assignCompanyPlan($company, 'basic');

        $this->actingAs($owner);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        $component = Livewire::test(PosPage::class)
            ->set('branchId', $branch->id)
            ->set('warehouseId', $warehouse->id)
            ->set('cashRegisterId', $cashRegister->id)
            ->set('items.0.product_id', (string) $product->id)
            ->set('items.0.quantity', '1')
            ->set('items.0.unit_price', '1800');

        // Antes de este fix, el modal de "salida con carrito sin terminar"
        // siempre ofrecia "congelar y salir" sin importar si el plan/permiso
        // realmente lo permitian: al hacer clic no pasaba nada (ni error ni
        // navegacion) porque freezeCurrentSale() retornaba en silencio.
        $this->assertFalse($component->instance()->canFreezeCurrentSale());
        $component->assertDontSee('Sí, congelar venta y salir');

        $component->call('freezeCurrentSale')
            ->assertDispatched('toast', function (string $eventName, array $params): bool {
                return ($params['type'] ?? null) === 'error';
            });

        $this->assertDatabaseCount('frozen_sales', 0);
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
            ->assertSee('3.240');
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
            ->assertSee('posProductSearch()', false)
            ->assertSee($product->name)
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

    protected function createUserWithCompanyPermissions(\App\Models\Company $company, string $roleCode, array $permissionCodes): User
    {
        $user = User::factory()->create();

        $companyRole = CompanyRole::query()->create([
            'company_id' => $company->id,
            'code' => $roleCode,
            'display_name' => $roleCode,
            'status' => RecordStatus::Active->value,
        ]);

        $companyRole->permissions()->attach(
            Permission::query()->whereIn('code', $permissionCodes)->pluck('id')
        );

        $company->users()->attach($user->id, [
            'company_role' => $roleCode,
            'company_role_id' => $companyRole->id,
            'status' => RecordStatus::Active->value,
            'joined_at' => now(),
        ]);

        return $user;
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
