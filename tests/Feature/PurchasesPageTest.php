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

    public function test_purchases_page_can_create_confirmed_purchase(): void
    {
        [$user, $company, $branch, $warehouse, $product] = $this->fixture();
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
            ->set('items.0.product_id', $product->id)
            ->set('items.0.quantity', '3')
            ->set('items.0.unit_cost', '1000')
            ->set('items.0.tax_rate', '19')
            ->call('savePurchase')
            ->assertHasNoErrors();

        $purchase = Purchase::query()->where('company_id', $company->id)->firstOrFail();

        $this->assertSame('FAC-UI-100', $purchase->invoice_number);
        $this->assertSame(PurchaseStatus::Confirmed->value, $purchase->status);
        $this->assertSame($supplier->id, $purchase->supplier_id);
        $this->assertSame('3570.00', (string) $purchase->total);
        $this->assertSame('3570.00', (string) $purchase->balance_due);
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
            ->call('startRegisteringPayment', $purchase->id)
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

    public function test_purchases_page_can_expand_purchase_ledger(): void
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
            ->call('toggleLedger', $purchase->id)
            ->assertSee('Cargo inicial')
            ->assertSee('FAC-LEDGER-1');
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
