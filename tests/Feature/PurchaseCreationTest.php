<?php

namespace Tests\Feature;

use App\Actions\Companies\CreateCompany;
use App\Actions\Purchases\CreatePurchase;
use App\Actions\Suppliers\CreateSupplier;
use App\Models\InventoryMovement;
use App\Actions\Purchases\ReturnPurchase;
use App\Enums\PurchaseStatus;
use App\Enums\RecordStatus;
use App\Models\Attribute;
use App\Models\Brand;
use App\Models\Category;
use App\Models\InventoryBalance;
use App\Models\Product;
use App\Models\ProductPresentation;
use App\Models\ProductVariant;
use App\Models\Unit;
use App\Models\User;
use App\Services\Inventory\PostPurchaseToInventory;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_does_not_reuse_the_sequence_number_of_an_archived_purchase(): void
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Compras Archivadas SAS',
        ]);
        $branch = $company->branches()->firstOrFail();
        $warehouse = $company->warehouses()->firstOrFail();

        $createPurchase = app(CreatePurchase::class);

        $first = $createPurchase->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'total' => '10000',
            'status' => PurchaseStatus::Confirmed->value,
        ]);

        $this->assertSame(1, $first->company_sequence);

        $first->delete();

        $second = $createPurchase->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'total' => '20000',
            'status' => PurchaseStatus::Confirmed->value,
        ]);

        $this->assertSame(2, $second->company_sequence);
    }

    public function test_it_creates_purchase_with_calculated_totals_base_quantity_and_variant(): void
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Compras Retail SAS',
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
            'name' => 'Bebidas',
            'code' => 'BEB',
            'status' => RecordStatus::Active->value,
        ]);
        $brand = Brand::query()->create([
            'company_id' => $company->id,
            'name' => 'Monte Azul',
            'status' => RecordStatus::Active->value,
        ]);
        $product = Product::query()->create([
            'company_id' => $company->id,
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'base_unit_id' => $unit->id,
            'name' => 'Gaseosa Cola',
            'sku' => 'GAS-001',
            'cost' => 2000,
            'price_1' => 3000,
            'tracks_inventory' => true,
            'minimum_stock' => 1,
            'status' => RecordStatus::Active->value,
        ]);
        $presentation = ProductPresentation::query()->create([
            'company_id' => $company->id,
            'product_id' => $product->id,
            'unit_id' => $unit->id,
            'name' => 'Caja x 12',
            'conversion_factor' => '12.000000',
            'price_1' => 33000,
            'status' => RecordStatus::Active->value,
        ]);
        $attribute = Attribute::query()->create([
            'company_id' => $company->id,
            'name' => 'Sabor',
            'code' => 'SAB',
            'status' => RecordStatus::Active->value,
        ]);
        $value = $attribute->values()->create([
            'value' => 'Regular',
            'status' => RecordStatus::Active->value,
        ]);
        $variant = ProductVariant::query()->create([
            'company_id' => $company->id,
            'product_id' => $product->id,
            'sku' => 'GAS-001-REG',
            'status' => RecordStatus::Active->value,
        ]);
        $variant->attributeValues()->attach($value->id);

        $purchase = app(CreatePurchase::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'supplier_name' => 'Distribuciones del Norte',
            'invoice_number' => 'FAC-1001',
            'purchase_type' => 'invoice',
            'status' => PurchaseStatus::Confirmed->value,
            'purchased_at' => now(),
            'items' => [
                [
                    'product_id' => $product->id,
                    'product_presentation_id' => $presentation->id,
                    'product_variant_id' => $variant->id,
                    'quantity' => '2',
                    'unit_cost' => '18000',
                    'tax_rate' => '19',
                ],
            ],
        ]);

        $this->assertSame('36000.00', $purchase->subtotal);
        $this->assertSame('6840.00', $purchase->tax_total);
        $this->assertSame('42840.00', $purchase->total);
        $this->assertCount(1, $purchase->items);
        $this->assertSame('24.000000', $purchase->items->first()->base_quantity);
        $this->assertSame('42840.00', $purchase->items->first()->line_total);
        $this->assertNotNull($purchase->posted_to_inventory_at);

        $this->assertDatabaseHas('purchases', [
            'id' => $purchase->id,
            'company_id' => $company->id,
            'supplier_name' => 'Distribuciones del Norte',
            'status' => PurchaseStatus::Confirmed->value,
            'total' => '42840.00',
        ]);

        $this->assertDatabaseHas('inventory_movements', [
            'company_id' => $company->id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'quantity_in' => '24.000000',
            'unit_cost' => '1500.0000',
            'balance_quantity' => '24.000000',
            'balance_cost' => '1500.0000',
        ]);

        $this->assertDatabaseHas('inventory_balances', [
            'company_id' => $company->id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'quantity_on_hand' => '24.000000',
            'average_cost' => '1500.0000',
        ]);
    }

    public function test_posting_purchase_to_inventory_is_idempotent(): void
    {
        [$company, $branch, $warehouse, $product] = $this->minimalPurchaseFixture();

        $purchase = app(CreatePurchase::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'status' => PurchaseStatus::Confirmed->value,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => '3',
                    'unit_cost' => '1000',
                ],
            ],
        ]);

        $this->assertSame(1, InventoryMovement::query()->count());
        $this->assertNotNull($purchase->posted_to_inventory_at);

        app(PostPurchaseToInventory::class)->handle($purchase);

        $this->assertSame(1, InventoryMovement::query()->count());
        $this->assertSame('3.000000', InventoryBalance::query()->firstOrFail()->quantity_on_hand);
    }

    public function test_it_returns_purchase_from_inventory_and_marks_it_as_cancelled(): void
    {
        [$company, $branch, $warehouse, $product] = $this->minimalPurchaseFixture();

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

        $purchase = app(ReturnPurchase::class)->handle($company, $purchase);

        $this->assertSame(PurchaseStatus::Cancelled->value, $purchase->status);
        $this->assertNotNull($purchase->returned_from_inventory_at);

        $this->assertDatabaseHas('inventory_movements', [
            'company_id' => $company->id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'movement_type' => 'purchase_return_out',
            'quantity_out' => '5.000000',
            'unit_cost' => '1000.0000',
            'balance_quantity' => '0.000000',
            'balance_cost' => '0.0000',
        ]);

        $this->assertDatabaseHas('inventory_balances', [
            'company_id' => $company->id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'quantity_on_hand' => '0.000000',
            'average_cost' => '0.0000',
        ]);
    }

    public function test_returning_purchase_from_inventory_is_idempotent(): void
    {
        [$company, $branch, $warehouse, $product] = $this->minimalPurchaseFixture();

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

        $this->assertSame(1, InventoryMovement::query()->count());

        app(ReturnPurchase::class)->handle($company, $purchase);
        app(ReturnPurchase::class)->handle($company, $purchase->fresh());

        $this->assertSame(2, InventoryMovement::query()->count());
        $this->assertSame(PurchaseStatus::Cancelled->value, $purchase->fresh()->status);
        $this->assertSame('0.000000', InventoryBalance::query()->firstOrFail()->quantity_on_hand);
    }

    public function test_it_rejects_purchase_return_when_stock_is_insufficient(): void
    {
        [$company, $branch, $warehouse, $product] = $this->minimalPurchaseFixture();

        $purchase = app(CreatePurchase::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'status' => PurchaseStatus::Confirmed->value,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => '4',
                    'unit_cost' => '1000',
                ],
            ],
        ]);

        InventoryBalance::query()->firstOrFail()->update([
            'quantity_on_hand' => '1.000000',
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La devolucion no tiene stock suficiente para revertir la compra.');

        try {
            app(ReturnPurchase::class)->handle($company, $purchase);
        } finally {
            $this->assertSame(1, InventoryMovement::query()->count());
            $this->assertSame(PurchaseStatus::Confirmed->value, $purchase->fresh()->status);
            $this->assertNull($purchase->fresh()->returned_from_inventory_at);
        }
    }

    public function test_it_rejects_foreign_catalog_records_in_purchase_creation(): void
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Compras Uno SAS',
        ]);
        $otherCompany = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Compras Dos SAS',
        ]);

        $branch = $company->branches()->firstOrFail();
        $warehouse = $company->warehouses()->firstOrFail();
        $otherUnit = Unit::query()->create([
            'company_id' => $otherCompany->id,
            'code' => 'UND',
            'name' => 'Unidad',
            'precision_scale' => 0,
            'status' => RecordStatus::Active->value,
        ]);
        $otherCategory = Category::query()->create([
            'company_id' => $otherCompany->id,
            'name' => 'Aseo',
            'code' => 'ASE',
            'status' => RecordStatus::Active->value,
        ]);
        $foreignProduct = Product::query()->create([
            'company_id' => $otherCompany->id,
            'category_id' => $otherCategory->id,
            'base_unit_id' => $otherUnit->id,
            'name' => 'Producto Ajeno',
            'cost' => 1000,
            'price_1' => 1500,
            'tracks_inventory' => true,
            'minimum_stock' => 1,
            'status' => RecordStatus::Active->value,
        ]);

        $this->expectException(ModelNotFoundException::class);

        app(CreatePurchase::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'items' => [
                [
                    'product_id' => $foreignProduct->id,
                    'quantity' => '1',
                    'unit_cost' => '1000',
                ],
            ],
        ]);
    }

    public function test_it_links_purchase_to_supplier_and_defaults_due_date_from_payment_terms(): void
    {
        [$company, $branch, $warehouse, $product] = $this->minimalPurchaseFixture();
        $supplier = app(CreateSupplier::class)->handle($company, [
            'first_name' => 'Distribuciones',
            'last_name' => 'Andinas',
            'payment_term_days' => 15,
        ]);

        $purchase = app(CreatePurchase::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'supplier_id' => $supplier->id,
            'status' => PurchaseStatus::Confirmed->value,
            'purchased_at' => '2026-06-10 08:00:00',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => '2',
                    'unit_cost' => '1000',
                ],
            ],
        ]);

        $this->assertSame($supplier->id, $purchase->supplier_id);
        $this->assertSame('Distribuciones Andinas', $purchase->supplier_name);
        $this->assertSame('2026-06-25 08:00:00', $purchase->due_at?->format('Y-m-d H:i:s'));
        $this->assertTrue($purchase->supplier()->exists());
    }

    public function test_it_rejects_foreign_supplier_in_purchase_creation(): void
    {
        [$company, $branch, $warehouse, $product] = $this->minimalPurchaseFixture();
        $otherOwner = User::factory()->create();
        $otherCompany = app(CreateCompany::class)->handle($otherOwner, [
            'legal_name' => 'Proveedor Ajeno SAS',
        ]);
        $foreignSupplier = app(CreateSupplier::class)->handle($otherCompany, [
            'first_name' => 'Proveedor',
            'last_name' => 'Externo',
        ]);

        $this->expectException(ModelNotFoundException::class);

        app(CreatePurchase::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'supplier_id' => $foreignSupplier->id,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => '1',
                    'unit_cost' => '1000',
                ],
            ],
        ]);
    }

    protected function minimalPurchaseFixture(): array
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Compras Minimas SAS',
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
