<?php

namespace Tests\Feature;

use App\Actions\Companies\CreateCompany;
use App\Actions\Purchases\ApplySupplierCreditToPurchase;
use App\Actions\Purchases\CreatePurchase;
use App\Actions\Purchases\ListSupplierPayableMovements;
use App\Actions\Purchases\RegisterPurchasePayment;
use App\Actions\Purchases\ReturnPurchase;
use App\Actions\Suppliers\CreateSupplier;
use App\Enums\PayableMovementType;
use App\Enums\PurchaseStatus;
use App\Enums\RecordStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierPayableMovementsQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_supplier_ledger_movements_with_operational_filters(): void
    {
        [$company, $branch, $warehouse, $product] = $this->fixture();
        $supplierA = app(CreateSupplier::class)->handle($company, [
            'first_name' => 'Proveedor',
            'last_name' => 'Alpha',
        ]);
        $supplierB = app(CreateSupplier::class)->handle($company, [
            'first_name' => 'Proveedor',
            'last_name' => 'Beta',
        ]);

        $this->travelTo(now()->startOfDay()->subDay());
        $returnedPurchase = app(CreatePurchase::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'supplier_id' => $supplierA->id,
            'invoice_number' => 'FAC-A-RET',
            'status' => PurchaseStatus::Confirmed->value,
            'items' => [[
                'product_id' => $product->id,
                'quantity' => '2',
                'unit_cost' => '1000',
            ]],
        ]);
        app(RegisterPurchasePayment::class)->handle($company, $returnedPurchase, [
            'amount' => '500',
            'reference' => 'PAY-A-1',
        ]);
        app(ReturnPurchase::class)->handle($company, $returnedPurchase);

        $this->travelTo(now()->addDay());
        $creditAppliedPurchase = app(CreatePurchase::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'supplier_id' => $supplierA->id,
            'invoice_number' => 'FAC-A-OPEN',
            'status' => PurchaseStatus::Confirmed->value,
            'items' => [[
                'product_id' => $product->id,
                'quantity' => '3',
                'unit_cost' => '1000',
            ]],
        ]);
        app(ApplySupplierCreditToPurchase::class)->handle($company, $creditAppliedPurchase, [
            'amount' => '500',
            'reference' => 'NC-A-1',
        ]);

        app(CreatePurchase::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'supplier_id' => $supplierB->id,
            'invoice_number' => 'FAC-B-1',
            'status' => PurchaseStatus::Confirmed->value,
            'items' => [[
                'product_id' => $product->id,
                'quantity' => '1',
                'unit_cost' => '1000',
            ]],
        ]);

        $query = app(ListSupplierPayableMovements::class);

        $supplierRows = $query->handle($company, [
            'supplier_id' => $supplierA->id,
        ]);

        $this->assertCount(6, $supplierRows);
        $this->assertSame($supplierA->id, $supplierRows->first()->supplier_id);
        $this->assertSame([
            PayableMovementType::SupplierCreditApplied->value,
            PayableMovementType::PurchaseCharge->value,
            PayableMovementType::SupplierCreditGenerated->value,
            PayableMovementType::PurchaseReturnAdjustment->value,
            PayableMovementType::Payment->value,
            PayableMovementType::PurchaseCharge->value,
        ], $supplierRows->pluck('movement_type')->all());

        $purchaseRows = $query->handle($company, [
            'purchase_id' => $creditAppliedPurchase->id,
        ]);

        $this->assertCount(2, $purchaseRows);
        $this->assertSame([
            PayableMovementType::SupplierCreditApplied->value,
            PayableMovementType::PurchaseCharge->value,
        ], $purchaseRows->pluck('movement_type')->all());

        $typeRows = $query->handle($company, [
            'movement_type' => PayableMovementType::SupplierCreditGenerated->value,
        ]);

        $this->assertCount(1, $typeRows);
        $this->assertSame($returnedPurchase->id, $typeRows->first()->purchase_id);

        $nameRows = $query->handle($company, [
            'supplier_name' => 'Alpha',
        ]);

        $this->assertCount(6, $nameRows);
        $this->assertTrue($nameRows->every(fn ($row) => $row->supplier_id === $supplierA->id));

        $dateRows = $query->handle($company, [
            'supplier_id' => $supplierA->id,
            'date_from' => now()->format('Y-m-d'),
            'date_to' => now()->format('Y-m-d'),
        ]);

        $this->assertCount(2, $dateRows);
        $this->assertTrue($dateRows->every(fn ($row) => $row->purchase_id === $creditAppliedPurchase->id));
    }

    public function test_it_rejects_invalid_date_filters_for_supplier_ledger_query(): void
    {
        [$company] = $this->fixture();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Fecha invalida.');

        app(ListSupplierPayableMovements::class)->handle($company, [
            'date_from' => '16/06/2026',
        ]);
    }

    protected function fixture(): array
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Ledger Proveedores SAS',
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
