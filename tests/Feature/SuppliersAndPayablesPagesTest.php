<?php

namespace Tests\Feature;

use App\Actions\Companies\CreateCompany;
use App\Actions\Purchases\CreatePurchase;
use App\Actions\Purchases\RegisterPurchasePayment;
use App\Actions\Purchases\ReturnPurchase;
use App\Actions\Suppliers\CreateSupplier;
use App\Enums\PurchaseStatus;
use App\Enums\RecordStatus;
use App\Livewire\Purchases\PayablesPage;
use App\Livewire\Purchases\SuppliersPage;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Services\Tenancy\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithCompanyPlans;
use Tests\TestCase;

class SuppliersAndPayablesPagesTest extends TestCase
{
    use InteractsWithCompanyPlans, RefreshDatabase;

    public function test_suppliers_page_can_create_update_toggle_and_scope_suppliers(): void
    {
        [$user, $company] = $this->actingUserWithCurrentCompany();
        $otherOwner = User::factory()->create();
        $otherCompany = app(CreateCompany::class)->handle($otherOwner, [
            'legal_name' => 'Otra Empresa SAS',
        ]);

        app(CreateSupplier::class)->handle($otherCompany, [
            'first_name' => 'Proveedor',
            'last_name' => 'Ajeno',
        ]);

        $this->actingAs($user);

        Livewire::test(SuppliersPage::class)
            ->set('documentType', 'NIT')
            ->set('documentNumber', '900123123')
            ->set('firstName', 'Distribuidora')
            ->set('lastName', 'Andina')
            ->set('phone', '3001234567')
            ->set('email', 'compras@andina.test')
            ->set('paymentTermDays', '30')
            ->set('notes', 'Mayorista principal')
            ->call('saveSupplier')
            ->assertHasNoErrors();

        $supplier = Supplier::query()
            ->where('company_id', $company->id)
            ->with('person')
            ->firstOrFail();

        $this->assertSame('Distribuidora', $supplier->person->first_name);
        $this->assertSame('30', (string) $supplier->payment_term_days);

        Livewire::test(SuppliersPage::class)
            ->call('editSupplier', $supplier->id)
            ->set('lastName', 'Andina SAS')
            ->set('status', RecordStatus::Inactive->value)
            ->call('saveSupplier')
            ->assertHasNoErrors();

        $supplier->refresh();
        $supplier->load('person');

        $this->assertSame('Andina SAS', $supplier->person->last_name);
        $this->assertSame(RecordStatus::Inactive->value, $supplier->status);

        Livewire::test(SuppliersPage::class)
            ->call('toggleSupplierStatus', $supplier->id);

        $this->assertDatabaseHas('suppliers', [
            'id' => $supplier->id,
            'status' => RecordStatus::Active->value,
        ]);

        $response = $this->actingAs($user)->get(route('purchases.suppliers'));

        $response->assertOk();
        $response->assertSee('Distribuidora');
        $response->assertDontSee('Proveedor Ajeno');
    }

    public function test_payables_page_can_apply_supplier_credit_to_pending_purchase(): void
    {
        [$user, $company, $branch, $warehouse, $product] = $this->purchaseFixture();
        $this->actingAs($user);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        $supplier = app(CreateSupplier::class)->handle($company, [
            'first_name' => 'Proveedor',
            'last_name' => 'Credito',
        ]);

        $returnedPurchase = app(CreatePurchase::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'supplier_id' => $supplier->id,
            'status' => PurchaseStatus::Confirmed->value,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => '2',
                    'unit_cost' => '1000',
                ],
            ],
        ]);

        $returnedPurchase = app(RegisterPurchasePayment::class)->handle($company, $returnedPurchase, [
            'amount' => '500',
        ]);
        app(ReturnPurchase::class)->handle($company, $returnedPurchase);

        $openPurchase = app(CreatePurchase::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'supplier_id' => $supplier->id,
            'status' => PurchaseStatus::Confirmed->value,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => '3',
                    'unit_cost' => '1000',
                ],
            ],
        ]);

        Livewire::test(PayablesPage::class)
            ->call('startApplyingCredit', $openPurchase->id)
            ->set('creditReference', 'NC-UI-1')
            ->call('applySupplierCredit')
            ->assertHasNoErrors();

        $openPurchase->refresh();
        $supplier->refresh();

        $this->assertSame(PurchaseStatus::PartiallyPaid->value, $openPurchase->status);
        $this->assertSame('500.00', (string) $openPurchase->amount_paid);
        $this->assertSame('2500.00', (string) $openPurchase->balance_due);
        $this->assertSame('0.00', (string) $supplier->credit_balance);
        $this->assertDatabaseHas('payable_movements', [
            'purchase_id' => $openPurchase->id,
            'supplier_id' => $supplier->id,
            'movement_type' => 'supplier_credit_applied',
            'reference' => 'NC-UI-1',
        ]);
    }

    public function test_payables_page_requires_reference_to_apply_supplier_credit(): void
    {
        [$user, $company, $branch, $warehouse, $product] = $this->purchaseFixture();
        $this->actingAs($user);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        $supplier = app(CreateSupplier::class)->handle($company, [
            'first_name' => 'Proveedor',
            'last_name' => 'Ref obligatoria',
        ]);

        $returnedPurchase = app(CreatePurchase::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'supplier_id' => $supplier->id,
            'status' => PurchaseStatus::Confirmed->value,
            'items' => [[
                'product_id' => $product->id,
                'quantity' => '2',
                'unit_cost' => '1000',
            ]],
        ]);

        $returnedPurchase = app(RegisterPurchasePayment::class)->handle($company, $returnedPurchase, [
            'amount' => '500',
            'reference' => 'PAGO-UI-BASE',
        ]);
        app(ReturnPurchase::class)->handle($company, $returnedPurchase);

        $openPurchase = app(CreatePurchase::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'supplier_id' => $supplier->id,
            'status' => PurchaseStatus::Confirmed->value,
            'items' => [[
                'product_id' => $product->id,
                'quantity' => '3',
                'unit_cost' => '1000',
            ]],
        ]);

        Livewire::test(PayablesPage::class)
            ->call('startApplyingCredit', $openPurchase->id)
            ->set('creditReference', '')
            ->call('applySupplierCredit')
            ->assertHasErrors(['creditReference']);
    }

    public function test_payables_page_can_expand_purchase_ledger(): void
    {
        [$user, $company, $branch, $warehouse, $product] = $this->purchaseFixture();
        $this->actingAs($user);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        $supplier = app(CreateSupplier::class)->handle($company, [
            'first_name' => 'Proveedor',
            'last_name' => 'Ledger',
        ]);

        $purchase = app(CreatePurchase::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'supplier_id' => $supplier->id,
            'invoice_number' => 'FAC-PXP-1',
            'status' => PurchaseStatus::Confirmed->value,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => '2',
                    'unit_cost' => '1000',
                ],
            ],
        ]);

        Livewire::test(PayablesPage::class)
            ->call('toggleLedger', $purchase->id)
            ->assertSee('Factura')
            ->assertSee('FAC-PXP-1');
    }

    public function test_payables_page_displays_supplier_summary_and_filters_credit_rows(): void
    {
        [$user, $company, $branch, $warehouse, $product] = $this->purchaseFixture();
        $this->actingAs($user);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        $alpha = app(CreateSupplier::class)->handle($company, [
            'first_name' => 'Proveedor',
            'last_name' => 'Alpha',
        ]);
        $beta = app(CreateSupplier::class)->handle($company, [
            'first_name' => 'Proveedor',
            'last_name' => 'Beta',
        ]);

        app(CreatePurchase::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'supplier_id' => $alpha->id,
            'invoice_number' => 'FAC-ALPHA-AGE',
            'status' => PurchaseStatus::Confirmed->value,
            'due_at' => now()->subDays(45)->format('Y-m-d H:i:s'),
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => '2',
                    'unit_cost' => '1000',
                ],
            ],
        ]);

        $betaPurchase = app(CreatePurchase::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'supplier_id' => $beta->id,
            'invoice_number' => 'FAC-BETA-CREDIT',
            'status' => PurchaseStatus::Confirmed->value,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => '1',
                    'unit_cost' => '1000',
                ],
            ],
        ]);
        $betaPurchase = app(RegisterPurchasePayment::class)->handle($company, $betaPurchase, [
            'amount' => '1000',
        ]);
        app(ReturnPurchase::class)->handle($company, $betaPurchase);

        app(CreatePurchase::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'supplier_id' => $beta->id,
            'invoice_number' => 'FAC-BETA-AGE',
            'status' => PurchaseStatus::Confirmed->value,
            'due_at' => now()->subDays(10)->format('Y-m-d H:i:s'),
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => '2',
                    'unit_cost' => '1000',
                ],
            ],
        ]);

        Livewire::test(PayablesPage::class)
            ->assertSee('Proveedores filtrados')
            ->assertSee('31-60')
            ->assertSee('Proveedor Alpha')
            ->assertSee('Proveedor Beta')
            ->set('hasCreditOnly', true)
            ->assertSee('Proveedor Beta')
            ->set('agingBucket', '0_30')
            ->assertSee('FAC-BETA-AGE')
            ->assertDontSee('FAC-ALPHA-AGE');
    }

    public function test_payables_page_status_cards_adapt_to_open_all_and_paid_tabs(): void
    {
        [$user, $company, $branch, $warehouse, $product] = $this->purchaseFixture();
        $this->actingAs($user);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        $supplier = app(CreateSupplier::class)->handle($company, [
            'first_name' => 'Proveedor',
            'last_name' => 'Mixto',
        ]);

        // Pendiente, aun no vence.
        app(CreatePurchase::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'supplier_id' => $supplier->id,
            'status' => PurchaseStatus::Confirmed->value,
            'due_at' => now()->addDays(5)->format('Y-m-d H:i:s'),
            'items' => [['product_id' => $product->id, 'quantity' => '1', 'unit_cost' => '1000']],
        ]);

        // Vencida hace 12 dias (cae en el tramo 0-30).
        app(CreatePurchase::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'supplier_id' => $supplier->id,
            'status' => PurchaseStatus::Confirmed->value,
            'due_at' => now()->subDays(12)->format('Y-m-d H:i:s'),
            'items' => [['product_id' => $product->id, 'quantity' => '2', 'unit_cost' => '1000']],
        ]);

        // Pagada a tiempo: vence en el futuro, se paga hoy.
        $onTime = app(CreatePurchase::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'supplier_id' => $supplier->id,
            'status' => PurchaseStatus::Confirmed->value,
            'due_at' => now()->addDays(10)->format('Y-m-d H:i:s'),
            'items' => [['product_id' => $product->id, 'quantity' => '1', 'unit_cost' => '2000']],
        ]);
        app(RegisterPurchasePayment::class)->handle($company, $onTime, ['amount' => '2000']);

        // Pagada con retraso: ya habia vencido cuando se pago (hoy).
        $late = app(CreatePurchase::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'supplier_id' => $supplier->id,
            'status' => PurchaseStatus::Confirmed->value,
            'due_at' => now()->subDays(5)->format('Y-m-d H:i:s'),
            'items' => [['product_id' => $product->id, 'quantity' => '1', 'unit_cost' => '3000']],
        ]);
        app(RegisterPurchasePayment::class)->handle($company, $late, ['amount' => '3000']);

        // Pendientes: solo las 2 compras con saldo (1000 + 2000), de las
        // cuales 2000 estan vencidos.
        $open = Livewire::test(PayablesPage::class)
            ->assertSee('Debes en total')
            ->assertSee('A tu favor')
            ->assertDontSee('Total pagado');
        $openCards = $open->viewData('statusCards');
        $this->assertSame('open', $openCards['mode']);
        $this->assertSame(3000.0, $openCards['pending_amount']);
        $this->assertSame(2000.0, $openCards['overdue_amount']);
        $this->assertSame(1, $openCards['overdue_count']);
        $this->assertSame(2000.0, $openCards['aging']['0_30']);

        // Todas: las 4 compras cuentan para el total, sin importar su estado.
        $all = Livewire::test(PayablesPage::class)
            ->call('setStatus', '')
            ->assertSee('Total en compras');
        $allCards = $all->viewData('statusCards');
        $this->assertSame('all', $allCards['mode']);
        $this->assertSame(4, $allCards['purchases_count']);
        $this->assertSame(8000.0, $allCards['total_amount']);
        $this->assertSame(5000.0, $allCards['paid_amount']);
        $this->assertSame(3000.0, $allCards['pending_amount']);

        // Pagadas: el saldo pendiente/vencido ya no aplica (por eso antes se
        // veia $0 y parecia roto); lo que importa es la puntualidad del pago.
        $paid = Livewire::test(PayablesPage::class)
            ->call('setStatus', 'paid')
            ->assertSee('Total pagado')
            ->assertSee('Pagadas a tiempo')
            ->assertSee('Con retraso')
            ->assertDontSee('Debes en total');
        $paidCards = $paid->viewData('statusCards');
        $this->assertSame('paid', $paidCards['mode']);
        $this->assertSame(2, $paidCards['paid_purchases_count']);
        $this->assertSame(1, $paidCards['paid_on_time_count']);
        $this->assertSame(1, $paidCards['paid_late_count']);
        $this->assertSame(5000.0, $paidCards['paid_amount']);
    }

    public function test_payables_page_shows_payment_method_breakdown_scoped_to_current_filters(): void
    {
        [$user, $company, $branch, $warehouse, $product] = $this->purchaseFixture();
        $this->actingAs($user);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        $supplier = app(CreateSupplier::class)->handle($company, [
            'first_name' => 'Proveedor',
            'last_name' => 'Medios',
        ]);

        $purchaseA = app(CreatePurchase::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'supplier_id' => $supplier->id,
            'status' => PurchaseStatus::Confirmed->value,
            'items' => [['product_id' => $product->id, 'quantity' => '3', 'unit_cost' => '1000']],
        ]);
        app(RegisterPurchasePayment::class)->handle($company, $purchaseA, ['amount' => '1000', 'payment_method_code' => 'cash']);

        $purchaseB = app(CreatePurchase::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'supplier_id' => $supplier->id,
            'status' => PurchaseStatus::Confirmed->value,
            'items' => [['product_id' => $product->id, 'quantity' => '2', 'unit_cost' => '1000']],
        ]);
        app(RegisterPurchasePayment::class)->handle($company, $purchaseB, ['amount' => '2000', 'payment_method_code' => 'transfer']);

        $page = Livewire::test(PayablesPage::class)
            ->call('setStatus', '')
            ->assertSee('De donde salio el dinero')
            ->assertSee('Efectivo')
            ->assertSee('Transferencia');

        $breakdown = collect($page->viewData('paymentMethodBreakdown'))->keyBy('payment_method_code');
        $this->assertSame('1.000', $breakdown->get('cash')['payments_total']);
        $this->assertSame('2.000', $breakdown->get('transfer')['payments_total']);
    }

    protected function actingUserWithCurrentCompany(): array
    {
        $user = User::factory()->create();
        $company = app(CreateCompany::class)->handle($user, [
            'legal_name' => 'Compras Retail SAS',
        ]);
        $this->assignCompanyPlan($company, 'basic');

        session([CurrentCompany::SESSION_KEY => $company->id]);

        return [$user, $company];
    }

    protected function purchaseFixture(): array
    {
        [$user, $company] = $this->actingUserWithCurrentCompany();
        $branch = $company->branches()->firstOrFail();
        $warehouse = $company->warehouses()->firstOrFail();
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
            'name' => 'Arroz',
            'cost' => 900,
            'price_1' => 1300,
            'tracks_inventory' => true,
            'minimum_stock' => 1,
            'status' => RecordStatus::Active->value,
        ]);

        return [$user, $company, $branch, $warehouse, $product];
    }
}
