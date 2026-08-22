<?php

namespace Tests\Feature;

use App\Actions\Cash\OpenCashSession;
use App\Actions\Companies\CreateCompany;
use App\Actions\Customers\CreateCustomer;
use App\Actions\Inventory\CreateInventoryAdjustment;
use App\Actions\Sales\CreateSale;
use App\Enums\InventoryAdjustmentType;
use App\Enums\RecordStatus;
use App\Enums\SaleStatus;
use App\Livewire\Credit\CreditAccountsPage;
use App\Models\Category;
use App\Models\CompanyRole;
use App\Models\Customer;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use App\Services\Settings\CompanySettings;
use App\Services\Tenancy\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithCompanyPlans;
use Tests\TestCase;

class CreditAccountsPageTest extends TestCase
{
    use InteractsWithCompanyPlans;
    use RefreshDatabase;

    public function test_credit_page_lists_accounts_and_registers_payment(): void
    {
        [$owner, $company, $sale, $customer, $cashSession] = $this->creditUiFixture();

        $this->actingAs($owner);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        Livewire::test(CreditAccountsPage::class)
            ->assertSee('Cuentas activas')
            ->assertSee($customer->person->first_name)
            ->call('selectCustomer', $customer->id)
            ->assertSee($sale->document_number)
            ->call('startRegisteringPayment')
            ->set('cashSessionId', $cashSession->id)
            ->set('paymentAmount', '1000')
            ->set('paymentMethodCode', 'cash')
            ->set('paymentReference', 'AB-UI-1')
            ->call('registerPayment')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('payments', [
            'company_id' => $company->id,
            'sale_id' => null,
            'credit_account_id' => $customer->creditAccount->id,
            'cash_session_id' => $cashSession->id,
            'amount' => '1000.00',
            'reference' => 'AB-UI-1',
        ]);
        $this->assertSame('2600.00', $customer->creditAccount()->firstOrFail()->fresh()->balance_due);
    }

    public function test_credit_page_filters_accounts_by_mora_status_pill(): void
    {
        [$owner, $company, $sale, $customer] = $this->creditUiFixture();

        $sale->update(['credit_due_at' => now()->subDays(10)]);

        $this->actingAs($owner);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        Livewire::test(CreditAccountsPage::class)
            ->assertSee($customer->person->first_name)
            ->assertSee('En mora')
            ->call('setStatusFilter', 'current')
            ->assertDontSee($customer->person->first_name)
            ->call('setStatusFilter', 'overdue')
            ->assertSee($customer->person->first_name)
            ->call('setStatusFilter', 'all')
            ->assertSee($customer->person->first_name);
    }

    public function test_credit_page_can_enable_credit_for_a_customer_without_an_account(): void
    {
        [$owner, $company] = $this->creditUiFixture();

        $newCustomer = app(CreateCustomer::class)->handle($company, [
            'first_name' => 'Nato',
        ]);

        $this->actingAs($owner);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        $component = Livewire::test(CreditAccountsPage::class)
            ->call('openAddCustomerModal')
            ->assertSet('showAddCustomerModal', true);

        $options = collect($component->instance()->customersWithoutCreditOptions());
        $this->assertTrue($options->contains('id', $newCustomer->id));

        $component
            ->call('selectCustomerForCredit', $newCustomer->id)
            ->assertSet('addCustomerSelectedId', $newCustomer->id)
            ->set('addCustomerCreditLimit', '200000')
            ->call('enableCredit')
            ->assertHasNoErrors()
            ->assertSet('showAddCustomerModal', false);

        $newCustomer->refresh();
        $this->assertTrue($newCustomer->credit_enabled);
        $this->assertNotNull($newCustomer->creditAccount);
        $this->assertSame('200000.00', $newCustomer->creditAccount->credit_limit);

        Livewire::test(CreditAccountsPage::class)
            ->assertSee('Nato');
    }

    public function test_credit_page_can_create_a_brand_new_customer_and_enable_credit_in_one_flow(): void
    {
        [$owner, $company] = $this->creditUiFixture();

        $this->actingAs($owner);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        Livewire::test(CreditAccountsPage::class)
            ->call('openAddCustomerModal')
            ->call('startCreatingCustomerForCredit')
            ->assertSet('addCustomerCreatingNew', true)
            ->set('newCustomerFirstName', 'Camila')
            ->set('newCustomerLastName', 'Rios')
            ->set('newCustomerDocumentType', 'CC')
            ->set('newCustomerDocumentNumber', '1099887766')
            ->set('newCustomerPhone', '3011234567')
            ->set('newCustomerEmail', 'camila@example.test')
            ->set('addCustomerCreditLimit', '300000')
            ->call('createCustomerForCredit')
            ->assertHasNoErrors()
            ->assertSet('showAddCustomerModal', false);

        $customer = Customer::query()
            ->where('company_id', $company->id)
            ->whereHas('person', fn ($query) => $query->where('document_number', '1099887766'))
            ->with(['person', 'creditAccount'])
            ->firstOrFail();

        $this->assertSame('Camila', $customer->person->first_name);
        $this->assertSame('Rios', $customer->person->last_name);
        $this->assertSame('3011234567', $customer->person->phone);
        $this->assertTrue($customer->credit_enabled);
        $this->assertNotNull($customer->creditAccount);
        $this->assertSame('300000.00', $customer->creditAccount->credit_limit);

        Livewire::test(CreditAccountsPage::class)
            ->assertSee('Camila');
    }

    public function test_credit_page_customers_without_credit_options_excludes_customers_with_an_account(): void
    {
        [$owner, $company, , $customerWithCredit] = $this->creditUiFixture();

        $newCustomer = app(CreateCustomer::class)->handle($company, [
            'first_name' => 'SinCredito',
        ]);

        $this->actingAs($owner);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        $options = collect(
            Livewire::test(CreditAccountsPage::class)->instance()->customersWithoutCreditOptions()
        );

        $this->assertTrue($options->contains('id', $newCustomer->id));
        $this->assertFalse($options->contains('id', $customerWithCredit->id));
    }

    public function test_credit_page_route_is_forbidden_without_credit_permissions(): void
    {
        [, $company] = $this->creditUiFixture();
        $viewer = User::factory()->create();
        $companyRole = CompanyRole::query()->create([
            'company_id' => $company->id,
            'code' => 'sales_view_only_credit',
            'display_name' => 'Solo ventas lectura',
            'status' => RecordStatus::Active->value,
        ]);

        $companyRole->permissions()->attach(
            Permission::query()->where('code', 'sales.view')->value('id')
        );

        $company->users()->attach($viewer->id, [
            'company_role' => 'sales_view_only_credit',
            'company_role_id' => $companyRole->id,
            'status' => RecordStatus::Active->value,
            'joined_at' => now(),
        ]);

        $this->actingAs($viewer)
            ->withSession([CurrentCompany::SESSION_KEY => $company->id])
            ->get(route('credit.index'))
            ->assertForbidden();
    }

    public function test_credit_payment_action_is_forbidden_without_credit_manage_permission(): void
    {
        [, $company, $sale] = $this->creditUiFixture();
        $assistant = User::factory()->create();

        $company->users()->attach($assistant->id, [
            'company_role' => 'custom',
            'company_role_id' => $this->companyRolePreset($company, 'accounting_assistant')->id,
            'status' => RecordStatus::Active->value,
            'joined_at' => now(),
        ]);

        $this->actingAs($assistant);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        Livewire::test(CreditAccountsPage::class)
            ->call('startRegisteringPayment')
            ->assertForbidden();
    }

    public function test_credit_page_accepts_cash_session_from_any_company_register(): void
    {
        [$owner, $company, , $customer] = $this->creditUiFixture();
        $branch = $company->branches()->firstOrFail();

        $foreignRegister = $company->cashRegisters()->create([
            'branch_id' => $branch->id,
            'name' => 'Caja UI alterna',
            'code' => 'CAJA-UI-ALT',
            'status' => RecordStatus::Active->value,
        ]);

        $foreignSession = app(OpenCashSession::class)->handle($company, [
            'branch_id' => $branch->id,
            'cash_register_id' => $foreignRegister->id,
            'opened_by' => $owner->id,
            'opening_amount' => '30000',
        ]);

        $this->actingAs($owner);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        // Los abonos ya no se enlazan a una venta puntual, asi que una
        // sesion de caja de cualquier caja de la empresa es valida.
        Livewire::test(CreditAccountsPage::class)
            ->assertSee($customer->person->first_name)
            ->call('selectCustomer', $customer->id)
            ->call('startRegisteringPayment')
            ->set('cashSessionId', $foreignSession->id)
            ->set('paymentAmount', '1000')
            ->set('paymentMethodCode', 'cash')
            ->call('registerPayment')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('payments', [
            'company_id' => $company->id,
            'cash_session_id' => $foreignSession->id,
            'amount' => '1000.00',
        ]);
    }

    public function test_credit_page_can_set_a_custom_payment_term_for_one_customer(): void
    {
        [$owner, $company, , $customer] = $this->creditUiFixture();

        $this->actingAs($owner);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        Livewire::test(CreditAccountsPage::class)
            ->call('editCustomer', $customer->id)
            ->assertSet('editPaymentTermDays', '')
            ->set('editPaymentTermDays', '15')
            ->call('saveCustomerEdit')
            ->assertHasNoErrors();

        $this->assertSame(15, $customer->creditAccount()->firstOrFail()->fresh()->payment_term_days);
    }

    public function test_new_credit_sale_uses_the_customers_custom_payment_term(): void
    {
        [$owner, $company, , $customer] = $this->creditUiFixture();
        $branch = $company->branches()->firstOrFail();
        $warehouse = $company->warehouses()->firstOrFail();
        $product = Product::query()->where('company_id', $company->id)->firstOrFail();

        // 15 dias en vez del plazo general de la empresa (30, default del
        // catalogo de settings) para este cliente puntual.
        $customer->creditAccount()->firstOrFail()->update(['payment_term_days' => 15]);

        $sale = app(CreateSale::class)->handle($company, [
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

        $this->assertSame(
            now()->addDays(15)->toDateString(),
            $sale->credit_due_at->toDateString(),
        );
    }

    protected function creditUiFixture(): array
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Credito UI SAS',
        ]);
        $this->assignCompanyPlan($company, 'pro');
        app(CompanySettings::class)->set($company, 'credit', 'credit_enabled', true);
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
            'name' => 'Arroz credito UI',
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

        $customer = app(CreateCustomer::class)->handle($company, [
            'first_name' => 'Maria',
            'last_name' => 'Credito',
            'credit_enabled' => true,
            'credit_limit' => '10000',
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
            'sale_type' => 'credit',
            'status' => SaleStatus::Confirmed->value,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => '2',
                    'unit_price' => '1800',
                ],
            ],
        ]);

        return [$owner, $company, $sale, $customer->fresh(['person', 'creditAccount']), $cashSession];
    }
}
