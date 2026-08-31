<?php

namespace Tests\Feature;

use App\Actions\Companies\CreateCompany;
use App\Actions\Purchases\CreatePurchase;
use App\Actions\Suppliers\CreateSupplier;
use App\Enums\PurchaseStatus;
use App\Enums\RecordStatus;
use App\Livewire\Purchases\PurchasesPage;
use App\Models\Category;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Unit;
use App\Models\User;
use App\Services\Tenancy\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PurchasesPageTest extends TestCase
{
    use RefreshDatabase;

    // La UI de Compras deliberadamente no pide productos/cantidades: es un
    // registro de deuda con el proveedor (monto total) y sus abonos, sin
    // tocar inventario. Quien si necesita productos con costo/IVA por linea
    // y postear a kardex es CreatePurchase::handle() llamado directamente
    // (ver seedPurchases() del seeder demo), no este formulario.
    public function test_purchases_page_can_create_confirmed_purchase(): void
    {
        [$user, $company, $branch, $warehouse] = $this->fixture();
        $supplier = app(CreateSupplier::class)->handle($company, [
            'first_name' => 'Proveedor',
            'last_name' => 'Principal',
            'payment_term_days' => 15,
        ]);

        $this->actingAs($user);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        Livewire::test(PurchasesPage::class)
            ->set('branchId', $branch->id)
            ->set('warehouseId', $warehouse->id)
            ->set('supplierId', $supplier->id)
            ->set('invoiceNumber', 'FAC-UI-100')
            ->set('totalAmount', '3570')
            ->set('paidImmediately', false)
            ->call('savePurchase')
            ->assertHasNoErrors();

        $purchase = Purchase::query()->where('company_id', $company->id)->firstOrFail();

        $this->assertSame('FAC-UI-100', $purchase->invoice_number);
        $this->assertSame(PurchaseStatus::Confirmed->value, $purchase->status);
        $this->assertSame($supplier->id, $purchase->supplier_id);
        $this->assertSame('3570.00', (string) $purchase->total);
        $this->assertSame('3570.00', (string) $purchase->balance_due);
        $this->assertSame(0, $purchase->items()->count());
        $this->assertNull($purchase->posted_to_inventory_at);
    }

    // Igual que en POS con clientes: si el usuario teclea un nombre y no lo
    // selecciona de la lista desplegable, se crea un proveedor formal de una
    // vez (no queda como texto suelto sin registro), para que luego se le
    // puedan agregar documento/telefono/plazo desde Proveedores.
    public function test_purchases_page_auto_creates_a_supplier_from_typed_name(): void
    {
        [$user, $company, $branch, $warehouse] = $this->fixture();

        $this->actingAs($user);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        Livewire::test(PurchasesPage::class)
            ->set('branchId', $branch->id)
            ->set('warehouseId', $warehouse->id)
            ->set('supplierName', 'Distribuidora Nueva SAS')
            ->set('totalAmount', '5000')
            ->set('paidImmediately', false)
            ->call('savePurchase')
            ->assertHasNoErrors();

        $supplier = \App\Models\Supplier::query()->where('company_id', $company->id)->with('person')->firstOrFail();
        $this->assertSame('Distribuidora Nueva SAS', $supplier->person->first_name);

        $purchase = Purchase::query()->where('company_id', $company->id)->firstOrFail();
        $this->assertSame($supplier->id, $purchase->supplier_id);
    }

    public function test_purchases_page_reuses_existing_supplier_with_same_typed_name(): void
    {
        [$user, $company, $branch, $warehouse] = $this->fixture();
        $existing = app(CreateSupplier::class)->handle($company, [
            'first_name' => 'Distribuidora Existente',
        ]);

        $this->actingAs($user);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        Livewire::test(PurchasesPage::class)
            ->set('branchId', $branch->id)
            ->set('warehouseId', $warehouse->id)
            ->set('supplierName', 'distribuidora existente')
            ->set('totalAmount', '1000')
            ->set('paidImmediately', false)
            ->call('savePurchase')
            ->assertHasNoErrors();

        $this->assertSame(1, \App\Models\Supplier::query()->where('company_id', $company->id)->count());

        $purchase = Purchase::query()->where('company_id', $company->id)->firstOrFail();
        $this->assertSame($existing->id, $purchase->supplier_id);
    }

    public function test_purchases_page_requires_answering_whether_it_was_already_paid(): void
    {
        [$user, $company, $branch, $warehouse] = $this->fixture();
        $supplier = app(CreateSupplier::class)->handle($company, ['first_name' => 'Proveedor', 'last_name' => 'SinRespuesta']);

        $this->actingAs($user);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        // Sin tocar "Esta compra ya se pago" (sigue en null): el resto del
        // formulario ni siquiera se habria mostrado en la UI real, y el
        // guardado debe rechazarse en vez de asumir un valor por defecto.
        Livewire::test(PurchasesPage::class)
            ->set('branchId', $branch->id)
            ->set('warehouseId', $warehouse->id)
            ->set('supplierId', $supplier->id)
            ->set('totalAmount', '2000')
            ->call('savePurchase')
            ->assertHasErrors(['paidImmediately']);

        $this->assertSame(0, Purchase::query()->where('company_id', $company->id)->count());
    }

    public function test_purchases_page_defaults_purchase_type_to_users_last_used_type(): void
    {
        [$user, $company, $branch, $warehouse] = $this->fixture();
        $supplier = app(CreateSupplier::class)->handle($company, ['first_name' => 'Proveedor', 'last_name' => 'TipoRecordado']);

        $this->actingAs($user);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        Livewire::test(PurchasesPage::class)
            ->set('branchId', $branch->id)
            ->set('warehouseId', $warehouse->id)
            ->set('supplierId', $supplier->id)
            ->set('purchaseType', 'expense')
            ->set('totalAmount', '1500')
            ->set('paidImmediately', false)
            ->call('savePurchase')
            ->assertHasNoErrors();

        $this->assertSame('expense', $user->fresh()->last_purchase_type);

        // Un componente nuevo (como al abrir el modal de nuevo) arranca con
        // el tipo que este usuario guardo la ultima vez, no con "Factura".
        Livewire::test(PurchasesPage::class)
            ->assertSet('purchaseType', 'expense');
    }

    public function test_purchases_page_can_create_purchase_paid_immediately_in_full_with_single_method(): void
    {
        [$user, $company, $branch, $warehouse] = $this->fixture();
        $supplier = app(CreateSupplier::class)->handle($company, [
            'first_name' => 'Proveedor',
            'last_name' => 'Contado',
            'payment_term_days' => 30,
        ]);

        $this->actingAs($user);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        Livewire::test(PurchasesPage::class)
            ->set('branchId', $branch->id)
            ->set('warehouseId', $warehouse->id)
            ->set('supplierId', $supplier->id)
            ->set('totalAmount', '4000')
            ->set('paidImmediately', true)
            ->set('paymentCompletionMode', 'full')
            ->set('paymentMethodMode', 'transfer')
            ->call('savePurchase')
            ->assertHasNoErrors();

        $purchase = Purchase::query()->where('company_id', $company->id)->firstOrFail();

        $this->assertSame(PurchaseStatus::Paid->value, $purchase->status);
        $this->assertSame('4000.00', (string) $purchase->amount_paid);
        $this->assertSame('0.00', (string) $purchase->balance_due);
        // Pago inmediato ignora el plazo del proveedor: no queda "Vence".
        $this->assertNull($purchase->due_at);
        $this->assertDatabaseHas('payable_movements', [
            'purchase_id' => $purchase->id,
            'movement_type' => 'payment',
            'amount' => '4000.00',
            'payment_method_code' => 'transfer',
        ]);
    }

    public function test_purchases_page_backdated_purchase_paid_immediately_uses_purchased_at_for_the_payment(): void
    {
        [$user, $company, $branch, $warehouse] = $this->fixture();
        $supplier = app(CreateSupplier::class)->handle($company, [
            'first_name' => 'Fruver',
            'last_name' => 'Ayer',
            'payment_term_days' => 30,
        ]);

        $this->actingAs($user);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        $yesterday = now()->subDay()->format('Y-m-d');

        Livewire::test(PurchasesPage::class)
            ->set('branchId', $branch->id)
            ->set('warehouseId', $warehouse->id)
            ->set('supplierId', $supplier->id)
            ->set('purchasedAt', $yesterday)
            ->set('totalAmount', '10800')
            ->set('paidImmediately', true)
            ->set('paymentCompletionMode', 'full')
            ->set('paymentMethodMode', 'cash')
            ->call('savePurchase')
            ->assertHasNoErrors();

        $purchase = Purchase::query()->where('company_id', $company->id)->firstOrFail();
        $movement = \App\Models\PayableMovement::query()
            ->where('purchase_id', $purchase->id)
            ->where('movement_type', 'payment')
            ->firstOrFail();

        $this->assertSame($yesterday, $purchase->purchased_at->toDateString());
        $this->assertSame($yesterday, $movement->occurred_at->toDateString());
    }

    public function test_purchases_page_can_create_purchase_with_partial_mixed_payment(): void
    {
        [$user, $company, $branch, $warehouse] = $this->fixture();
        $supplier = app(CreateSupplier::class)->handle($company, [
            'first_name' => 'Proveedor',
            'last_name' => 'Mixto',
        ]);

        $this->actingAs($user);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        Livewire::test(PurchasesPage::class)
            ->set('branchId', $branch->id)
            ->set('warehouseId', $warehouse->id)
            ->set('supplierId', $supplier->id)
            ->set('totalAmount', '10000')
            ->set('paidImmediately', true)
            ->set('paymentCompletionMode', 'partial')
            ->set('initialPaidAmount', '6000')
            ->set('paymentMethodMode', 'mixed')
            ->set('mixedCashAmount', '2000')
            ->set('mixedTransferAmount', '4000')
            ->call('savePurchase')
            ->assertHasNoErrors();

        $purchase = Purchase::query()->where('company_id', $company->id)->firstOrFail();

        $this->assertSame(PurchaseStatus::PartiallyPaid->value, $purchase->status);
        $this->assertSame('6000.00', (string) $purchase->amount_paid);
        $this->assertSame('4000.00', (string) $purchase->balance_due);
        $this->assertNull($purchase->due_at);
        $this->assertDatabaseHas('payable_movements', [
            'purchase_id' => $purchase->id,
            'movement_type' => 'payment',
            'amount' => '2000.00',
            'payment_method_code' => 'cash',
        ]);
        $this->assertDatabaseHas('payable_movements', [
            'purchase_id' => $purchase->id,
            'movement_type' => 'payment',
            'amount' => '4000.00',
            'payment_method_code' => 'transfer',
        ]);
    }

    public function test_purchases_page_rejects_mixed_payment_that_does_not_add_up(): void
    {
        [$user, $company, $branch, $warehouse] = $this->fixture();
        $supplier = app(CreateSupplier::class)->handle($company, ['first_name' => 'Proveedor', 'last_name' => 'Descuadre']);

        $this->actingAs($user);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        Livewire::test(PurchasesPage::class)
            ->set('branchId', $branch->id)
            ->set('warehouseId', $warehouse->id)
            ->set('supplierId', $supplier->id)
            ->set('totalAmount', '10000')
            ->set('paidImmediately', true)
            ->set('paymentCompletionMode', 'full')
            ->set('paymentMethodMode', 'mixed')
            ->set('mixedCashAmount', '2000')
            ->set('mixedTransferAmount', '4000')
            ->call('savePurchase')
            ->assertHasErrors(['mixedCashAmount']);

        $this->assertSame(0, Purchase::query()->where('company_id', $company->id)->count());
    }

    public function test_purchases_page_keeps_form_data_after_closing_modal_by_accident(): void
    {
        [$user, $company, $branch, $warehouse] = $this->fixture();

        $this->actingAs($user);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        Livewire::test(PurchasesPage::class)
            ->call('openModal')
            ->set('branchId', $branch->id)
            ->set('warehouseId', $warehouse->id)
            ->set('supplierName', 'Proveedor Sin Guardar')
            ->set('invoiceNumber', 'FAC-DRAFT-1')
            ->set('totalAmount', '7500')
            // Cerrar (clic afuera, X o "Cancelar" llaman a closeModal) ya no
            // debe borrar lo que se llevaba escrito.
            ->call('closeModal')
            ->assertSet('supplierName', 'Proveedor Sin Guardar')
            ->assertSet('invoiceNumber', 'FAC-DRAFT-1')
            ->assertSet('totalAmount', '7500')
            ->call('openModal')
            ->assertSet('supplierName', 'Proveedor Sin Guardar')
            ->assertSet('invoiceNumber', 'FAC-DRAFT-1')
            ->assertSet('totalAmount', '7500');
    }

    public function test_purchases_page_can_register_payment_and_return_purchase(): void
    {
        [$user, $company, $branch, $warehouse, $product] = $this->fixture();
        $supplier = app(CreateSupplier::class)->handle($company, [
            'first_name' => 'Proveedor',
            'last_name' => 'Pagos',
        ]);

        $purchase = app(CreatePurchase::class)->handle($company, [
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

        $this->actingAs($user);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        Livewire::test(PurchasesPage::class)
            ->call('openLedger', $purchase->id)
            ->set('paymentAmount', '500')
            ->set('paymentReference', 'TRX-UI-1')
            ->call('registerPayment')
            ->assertHasNoErrors();

        $purchase->refresh();

        $this->assertSame(PurchaseStatus::PartiallyPaid->value, $purchase->status);
        $this->assertSame('500.00', (string) $purchase->amount_paid);
        $this->assertSame('1500.00', (string) $purchase->balance_due);

        Livewire::test(PurchasesPage::class)
            ->call('cancelPurchase', $purchase->id)
            ->assertHasNoErrors();

        $purchase->refresh();
        $supplier->refresh();

        $this->assertSame(PurchaseStatus::Cancelled->value, $purchase->status);
        $this->assertSame('0.00', (string) $purchase->balance_due);
        $this->assertSame('500.00', (string) $supplier->credit_balance);
    }

    public function test_purchases_page_can_cancel_a_pending_purchase_without_items(): void
    {
        [$user, $company, $branch, $warehouse] = $this->fixture();
        $supplier = app(CreateSupplier::class)->handle($company, [
            'first_name' => 'Proveedor',
            'last_name' => 'Informativo',
        ]);

        $purchase = app(CreatePurchase::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'supplier_id' => $supplier->id,
            'invoice_number' => 'FAC-INFO-1',
            'status' => PurchaseStatus::Confirmed->value,
            'total' => '50000',
        ]);

        $this->assertSame(0, $purchase->items()->count());
        $this->assertSame('50000.00', (string) $purchase->balance_due);

        $this->actingAs($user);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        Livewire::test(PurchasesPage::class)
            ->call('cancelPurchase', $purchase->id);

        $purchase->refresh();

        $this->assertSame(PurchaseStatus::Cancelled->value, $purchase->status);
        $this->assertSame('0.00', (string) $purchase->balance_due);
    }

    public function test_purchases_page_can_open_purchase_ledger_modal(): void
    {
        [$user, $company, $branch, $warehouse, $product] = $this->fixture();
        $supplier = app(CreateSupplier::class)->handle($company, [
            'first_name' => 'Proveedor',
            'last_name' => 'Ledger',
        ]);

        $purchase = app(CreatePurchase::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'supplier_id' => $supplier->id,
            'invoice_number' => 'FAC-LEDGER-1',
            'status' => PurchaseStatus::Confirmed->value,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => '2',
                    'unit_cost' => '1000',
                ],
            ],
        ]);

        $this->actingAs($user);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        Livewire::test(PurchasesPage::class)
            ->call('openLedger', $purchase->id)
            ->assertSee('Factura')
            ->assertSee('FAC-LEDGER-1');
    }

    public function test_purchases_page_ledger_shows_payment_timeliness(): void
    {
        [$user, $company, $branch, $warehouse, $product] = $this->fixture();
        $supplier = app(CreateSupplier::class)->handle($company, [
            'first_name' => 'Proveedor',
            'last_name' => 'Puntualidad',
        ]);

        // due_at quedo hace 2 dias: el pago (occurred_at = now()) sale con
        // 2 dias de retraso.
        $purchase = app(CreatePurchase::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'supplier_id' => $supplier->id,
            'invoice_number' => 'FAC-TIME-1',
            'status' => PurchaseStatus::Confirmed->value,
            'due_at' => now()->subDays(2)->toDateString(),
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => '2',
                    'unit_cost' => '1000',
                ],
            ],
        ]);

        $this->actingAs($user);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        Livewire::test(PurchasesPage::class)
            ->call('openLedger', $purchase->id)
            ->set('paymentAmount', (string) (int) round((float) $purchase->balance_due))
            ->call('registerPayment')
            ->assertHasNoErrors()
            ->assertSee('2 dias de retraso');
    }

    public function test_purchases_page_records_payment_method_on_registered_payment(): void
    {
        [$user, $company, $branch, $warehouse, $product] = $this->fixture();
        $supplier = app(CreateSupplier::class)->handle($company, [
            'first_name' => 'Proveedor',
            'last_name' => 'Transferencia',
        ]);

        $purchase = app(CreatePurchase::class)->handle($company, [
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

        $this->actingAs($user);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        Livewire::test(PurchasesPage::class)
            ->call('openLedger', $purchase->id)
            ->set('paymentAmount', '2000')
            ->set('paymentMethodCode', 'transfer')
            ->call('registerPayment')
            ->assertHasNoErrors()
            ->assertSee('Transferencia');

        $this->assertDatabaseHas('payable_movements', [
            'purchase_id' => $purchase->id,
            'movement_type' => 'payment',
            'payment_method_code' => 'transfer',
        ]);
    }

    public function test_purchases_page_rejects_payment_greater_than_balance_due(): void
    {
        [$user, $company, $branch, $warehouse, $product] = $this->fixture();
        $supplier = app(CreateSupplier::class)->handle($company, [
            'first_name' => 'Proveedor',
            'last_name' => 'Sobrepago',
        ]);

        $purchase = app(CreatePurchase::class)->handle($company, [
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

        $this->actingAs($user);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        Livewire::test(PurchasesPage::class)
            ->call('openLedger', $purchase->id)
            ->set('paymentAmount', (string) ((int) round((float) $purchase->balance_due) + 500))
            ->call('registerPayment')
            ->assertHasErrors(['paymentAmount']);

        $purchase->refresh();

        $this->assertSame(PurchaseStatus::Confirmed->value, $purchase->status);
        $this->assertSame('2000.00', (string) $purchase->balance_due);
        $this->assertSame('0.00', (string) $purchase->amount_paid);
    }

    public function test_due_status_shows_a_traffic_light_relative_to_the_due_date(): void
    {
        [$user, $company, $branch, $warehouse] = $this->fixture();
        $supplier = app(CreateSupplier::class)->handle($company, [
            'first_name' => 'Proveedor',
            'last_name' => 'Semaforo',
        ]);

        $overdue = app(CreatePurchase::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'supplier_id' => $supplier->id,
            'status' => PurchaseStatus::Confirmed->value,
            'total' => '10000',
            'due_at' => now()->subDays(3)->toDateString(),
        ]);
        $approaching = app(CreatePurchase::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'supplier_id' => $supplier->id,
            'status' => PurchaseStatus::Confirmed->value,
            'total' => '10000',
            'due_at' => now()->addDays(5)->toDateString(),
        ]);
        $farAway = app(CreatePurchase::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'supplier_id' => $supplier->id,
            'status' => PurchaseStatus::Confirmed->value,
            'total' => '10000',
            'due_at' => now()->addDays(20)->toDateString(),
        ]);
        $paidButOverdue = app(CreatePurchase::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'supplier_id' => $supplier->id,
            'status' => PurchaseStatus::Confirmed->value,
            'total' => '10000',
            'due_at' => now()->subDays(3)->toDateString(),
        ]);
        $paidButOverdue->update(['status' => PurchaseStatus::Paid->value, 'balance_due' => '0.00']);

        $this->actingAs($user);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        $page = Livewire::test(PurchasesPage::class)->instance();

        $overdueStatus = $page->dueStatus($overdue->fresh());
        $this->assertSame('rose', $overdueStatus['color']);
        $this->assertStringContainsString('Vencida hace 3', $overdueStatus['label']);

        $approachingStatus = $page->dueStatus($approaching->fresh());
        $this->assertSame('amber', $approachingStatus['color']);
        $this->assertStringContainsString('Vence en 5', $approachingStatus['label']);

        $farAwayStatus = $page->dueStatus($farAway->fresh());
        $this->assertSame('emerald', $farAwayStatus['color']);

        $this->assertNull($page->dueStatus($paidButOverdue->fresh()));
    }

    protected function fixture(): array
    {
        $user = User::factory()->create();
        $company = app(CreateCompany::class)->handle($user, [
            'legal_name' => 'Compras UI SAS',
        ]);
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
