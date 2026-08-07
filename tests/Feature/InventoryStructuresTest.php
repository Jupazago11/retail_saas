<?php

namespace Tests\Feature;

use App\Actions\Companies\CreateCompany;
use App\Enums\InventoryMovementType;
use App\Enums\RecordStatus;
use App\Models\Attribute;
use App\Models\Brand;
use App\Models\Category;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryStructuresTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_can_persist_inventory_movement_and_balance(): void
    {
        [$company, $warehouse, $product, $variant] = $this->inventoryFixture();

        $movement = InventoryMovement::query()->create([
            'company_id' => $company->id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'movement_type' => InventoryMovementType::PurchaseIn->value,
            'reference_type' => 'purchase_item',
            'reference_id' => 1,
            'quantity_in' => '24.000000',
            'quantity_out' => '0.000000',
            'unit_cost' => '1800.5000',
            'balance_quantity' => '24.000000',
            'balance_cost' => '1800.5000',
            'occurred_at' => now(),
        ]);

        $balance = InventoryBalance::query()->create([
            'company_id' => $company->id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'quantity_on_hand' => '24.000000',
            'average_cost' => '1800.5000',
        ]);

        $this->assertSame($company->id, $movement->company->id);
        $this->assertSame($warehouse->id, $movement->warehouse->id);
        $this->assertSame($variant->id, $balance->variant->id);
        $this->assertDatabaseHas('inventory_movements', [
            'id' => $movement->id,
            'movement_type' => InventoryMovementType::PurchaseIn->value,
        ]);
        $this->assertDatabaseHas('inventory_balances', [
            'id' => $balance->id,
            'quantity_on_hand' => '24.000000',
        ]);
    }

    public function test_it_rejects_duplicate_balance_without_variant_for_same_company_warehouse_and_product(): void
    {
        [$company, $warehouse, $product] = $this->inventoryFixture();

        InventoryBalance::query()->create([
            'company_id' => $company->id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'product_variant_id' => null,
            'quantity_on_hand' => '5.000000',
            'average_cost' => '1000.0000',
        ]);

        $this->expectException(QueryException::class);

        InventoryBalance::query()->create([
            'company_id' => $company->id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'product_variant_id' => null,
            'quantity_on_hand' => '8.000000',
            'average_cost' => '1200.0000',
        ]);
    }

    protected function inventoryFixture(): array
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Inventario Retail SAS',
        ]);
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
            'name' => 'Snacks',
            'code' => 'SNK',
            'status' => RecordStatus::Active->value,
        ]);
        $brand = Brand::query()->create([
            'company_id' => $company->id,
            'name' => 'Roca',
            'status' => RecordStatus::Active->value,
        ]);
        $product = Product::query()->create([
            'company_id' => $company->id,
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'base_unit_id' => $unit->id,
            'name' => 'Papas Fritas',
            'cost' => 1000,
            'price_1' => 1800,
            'tracks_inventory' => true,
            'minimum_stock' => 1,
            'status' => RecordStatus::Active->value,
        ]);
        $attribute = Attribute::query()->create([
            'company_id' => $company->id,
            'name' => 'Sabor',
            'code' => 'SAB',
            'status' => RecordStatus::Active->value,
        ]);
        $value = $attribute->values()->create([
            'value' => 'Limon',
            'status' => RecordStatus::Active->value,
        ]);
        $variant = ProductVariant::query()->create([
            'company_id' => $company->id,
            'product_id' => $product->id,
            'sku' => 'PAP-LIM',
            'status' => RecordStatus::Active->value,
        ]);
        $variant->attributeValues()->attach($value->id);

        return [$company, $warehouse, $product, $variant];
    }
}
