<?php

namespace Tests\Feature;

use App\Actions\Companies\CreateCompany;
use App\Actions\Purchases\ApplySupplierCreditToPurchase;
use App\Actions\Purchases\CreatePurchase;
use App\Actions\Purchases\RegisterPurchasePayment;
use App\Actions\Purchases\ReturnPurchase;
use App\Actions\Suppliers\CreateSupplier;
use App\Enums\PurchaseStatus;
use App\Enums\RecordStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class PurchasePayablesTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirmed_purchase_creates_initial_payable_charge(): void
    {
        [$company, $branch, $warehouse, $product] = $this->fixture();

        $purchase = app(CreatePurchase::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'status' => PurchaseStatus::Confirmed->value,
            'invoice_number' => 'FAC-2001',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => '3',
                    'unit_cost' => '1000',
                ],
            ],
        ]);

        $this->assertSame('0.00', $purchase->amount_paid);
        $this->assertSame('3000.00', $purchase->balance_due);
        $this->assertNull($purchase->paid_at);
        $this->assertDatabaseHas('payable_movements', [
            'company_id' => $company->id,
            'purchase_id' => $purchase->id,
            'movement_type' => 'purchase_charge',
            'amount' => '3000.00',
            'balance_after' => '3000.00',
            'reference' => 'FAC-2001',
        ]);
    }

    public function test_it_registers_partial_and_final_purchase_payments(): void
    {
        [$company, $branch, $warehouse, $product] = $this->fixture();

        $purchase = app(CreatePurchase::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'status' => PurchaseStatus::Confirmed->value,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => '5',
                    'unit_cost' => '1000',
                ],
            ],
        ]);

        $purchase = app(RegisterPurchasePayment::class)->handle($company, $purchase, [
            'amount' => '1500',
            'reference' => 'TRX-1',
        ]);

        $this->assertSame(PurchaseStatus::PartiallyPaid->value, $purchase->status);
        $this->assertSame('1500.00', $purchase->amount_paid);
        $this->assertSame('3500.00', $purchase->balance_due);

        $purchase = app(RegisterPurchasePayment::class)->handle($company, $purchase, [
            'amount' => '3500',
            'reference' => 'TRX-2',
        ]);

        $this->assertSame(PurchaseStatus::Paid->value, $purchase->status);
        $this->assertSame('5000.00', $purchase->amount_paid);
        $this->assertSame('0.00', $purchase->balance_due);
        $this->assertNotNull($purchase->paid_at);

        $this->assertDatabaseHas('payable_movements', [
            'purchase_id' => $purchase->id,
            'movement_type' => 'payment',
            'amount' => '1500.00',
            'balance_after' => '3500.00',
            'reference' => 'TRX-1',
        ]);
        $this->assertDatabaseHas('payable_movements', [
            'purchase_id' => $purchase->id,
            'movement_type' => 'payment',
            'amount' => '3500.00',
            'balance_after' => '0.00',
            'reference' => 'TRX-2',
        ]);
    }

    public function test_returned_purchase_adjusts_payable_when_it_has_no_payments(): void
    {
        [$company, $branch, $warehouse, $product] = $this->fixture();

        $purchase = app(CreatePurchase::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'status' => PurchaseStatus::Confirmed->value,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => '2',
                    'unit_cost' => '1000',
                ],
            ],
        ]);

        $purchase = app(ReturnPurchase::class)->handle($company, $purchase);

        $this->assertSame(PurchaseStatus::Returned->value, $purchase->status);
        $this->assertSame('0.00', $purchase->balance_due);
        $this->assertSame('0.00', $purchase->amount_paid);
        $this->assertDatabaseHas('payable_movements', [
            'purchase_id' => $purchase->id,
            'movement_type' => 'purchase_return_adjustment',
            'amount' => '2000.00',
            'balance_after' => '0.00',
        ]);
    }

    public function test_it_generates_supplier_credit_when_returning_purchase_after_payment(): void
    {
        [$company, $branch, $warehouse, $product] = $this->fixture();
        $supplier = app(CreateSupplier::class)->handle($company, [
            'first_name' => 'Proveedor',
            'last_name' => 'Pagado',
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

        $purchase = app(RegisterPurchasePayment::class)->handle($company, $purchase, [
            'amount' => '500',
        ]);

        $purchase = app(ReturnPurchase::class)->handle($company, $purchase);

        $this->assertSame(PurchaseStatus::Returned->value, $purchase->status);
        $this->assertSame('0.00', $purchase->balance_due);
        $this->assertSame('500.00', $purchase->amount_paid);
        $this->assertSame('500.00', $purchase->supplier->fresh()->credit_balance);
        $this->assertDatabaseHas('payable_movements', [
            'purchase_id' => $purchase->id,
            'supplier_id' => $supplier->id,
            'movement_type' => 'purchase_return_adjustment',
            'amount' => '1500.00',
            'balance_after' => '0.00',
        ]);
        $this->assertDatabaseHas('payable_movements', [
            'purchase_id' => $purchase->id,
            'supplier_id' => $supplier->id,
            'movement_type' => 'supplier_credit_generated',
            'amount' => '500.00',
            'supplier_credit_after' => '500.00',
        ]);
    }

    public function test_it_rejects_returning_paid_purchase_without_formal_supplier(): void
    {
        [$company, $branch, $warehouse, $product] = $this->fixture();

        $purchase = app(CreatePurchase::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'status' => PurchaseStatus::Confirmed->value,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => '2',
                    'unit_cost' => '1000',
                ],
            ],
        ]);

        $purchase = app(RegisterPurchasePayment::class)->handle($company, $purchase, [
            'amount' => '500',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No se puede devolver una compra pagada sin proveedor formal enlazado.');

        app(ReturnPurchase::class)->handle($company, $purchase);
    }

    public function test_it_applies_supplier_credit_to_pending_purchase(): void
    {
        [$company, $branch, $warehouse, $product] = $this->fixture();
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
            'reference' => 'PAGO-RET-1',
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

        $openPurchase = app(ApplySupplierCreditToPurchase::class)->handle($company, $openPurchase, [
            'amount' => '500',
            'reference' => 'NC-APLICADA-1',
        ]);

        $this->assertSame(PurchaseStatus::PartiallyPaid->value, $openPurchase->status);
        $this->assertSame('500.00', $openPurchase->amount_paid);
        $this->assertSame('2500.00', $openPurchase->balance_due);
        $this->assertSame('0.00', $supplier->fresh()->credit_balance);
        $this->assertDatabaseHas('payable_movements', [
            'purchase_id' => $openPurchase->id,
            'supplier_id' => $supplier->id,
            'movement_type' => 'supplier_credit_applied',
            'amount' => '500.00',
            'balance_after' => '2500.00',
            'supplier_credit_after' => '0.00',
            'reference' => 'NC-APLICADA-1',
        ]);
    }

    public function test_it_requires_reference_to_apply_supplier_credit(): void
    {
        [$company, $branch, $warehouse, $product] = $this->fixture();
        $supplier = app(CreateSupplier::class)->handle($company, [
            'first_name' => 'Proveedor',
            'last_name' => 'SinRef',
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
            'reference' => 'PAGO-BASE',
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

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Debes indicar una referencia para aplicar saldo a favor.');

        app(ApplySupplierCreditToPurchase::class)->handle($company, $openPurchase, [
            'amount' => '500',
            'reference' => '',
        ]);
    }

    public function test_paid_purchase_requires_initial_paid_amount_matching_total(): void
    {
        [$company, $branch, $warehouse, $product] = $this->fixture();

        $purchase = app(CreatePurchase::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'status' => PurchaseStatus::Paid->value,
            'paid_amount' => '3000',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => '3',
                    'unit_cost' => '1000',
                ],
            ],
        ]);

        $this->assertSame(PurchaseStatus::Paid->value, $purchase->status);
        $this->assertSame('3000.00', $purchase->amount_paid);
        $this->assertSame('0.00', $purchase->balance_due);
    }

    protected function fixture(): array
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'CxP Retail SAS',
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

        return [$company, $branch, $warehouse, $product];
    }
}
