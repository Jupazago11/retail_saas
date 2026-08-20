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
            ->set('purchaseStatus', PurchaseStatus::Confirmed->value)
            ->set('totalAmount', '3570')
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
