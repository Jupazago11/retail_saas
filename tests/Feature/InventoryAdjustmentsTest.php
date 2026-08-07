<?php

namespace Tests\Feature;

use App\Actions\Companies\CreateCompany;
use App\Actions\Inventory\CreateInventoryAdjustment;
use App\Actions\Purchases\CreatePurchase;
use App\Enums\InventoryAdjustmentType;
use App\Enums\PurchaseStatus;
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
use App\Services\Inventory\PostInventoryAdjustmentToInventory;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryAdjustmentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_inventory_increase_adjustment_and_posts_it(): void
    {
        [$company, $branch, $warehouse, $product, $variant] = $this->inventoryFixture();

        $adjustment = app(CreateInventoryAdjustment::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'adjustment_type' => InventoryAdjustmentType::Increase->value,
            'reason' => 'Conteo inicial',
            'adjusted_at' => now(),
            'items' => [
                [
                    'product_id' => $product->id,
                    'product_variant_id' => $variant->id,
                    'quantity' => '10',
                    'unit_cost' => '1200',
                ],
            ],
        ]);

        $this->assertNotNull($adjustment->posted_to_inventory_at);
        $this->assertSame(InventoryAdjustmentType::Increase->value, $adjustment->adjustment_type);

        $this->assertDatabaseHas('inventory_movements', [
            'company_id' => $company->id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'movement_type' => 'adjustment_in',
            'quantity_in' => '10.000000',
            'unit_cost' => '1200.0000',
            'balance_quantity' => '10.000000',
            'balance_cost' => '1200.0000',
        ]);

        $this->assertDatabaseHas('inventory_balances', [
            'company_id' => $company->id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'quantity_on_hand' => '10.000000',
            'average_cost' => '1200.0000',
        ]);
    }

    public function test_it_creates_inventory_decrease_adjustment_using_current_average_cost(): void
    {
        [$company, $branch, $warehouse, $product] = $this->inventoryFixtureWithoutVariant();

        app(CreatePurchase::class)->handle($company, [
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

        $adjustment = app(CreateInventoryAdjustment::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'adjustment_type' => InventoryAdjustmentType::Decrease->value,
            'reason' => 'Merma operativa',
            'adjusted_at' => now(),
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => '2',
                ],
            ],
        ]);

        $this->assertNotNull($adjustment->posted_to_inventory_at);

        $this->assertDatabaseHas('inventory_movements', [
            'company_id' => $company->id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'product_variant_id' => null,
            'movement_type' => 'adjustment_out',
            'quantity_out' => '2.000000',
            'unit_cost' => '1000.0000',
            'balance_quantity' => '3.000000',
            'balance_cost' => '1000.0000',
        ]);

        $this->assertDatabaseHas('inventory_balances', [
            'company_id' => $company->id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'product_variant_id' => null,
            'quantity_on_hand' => '3.000000',
            'average_cost' => '1000.0000',
        ]);
    }

    public function test_posting_inventory_adjustment_is_idempotent(): void
    {
        [$company, $branch, $warehouse, $product] = $this->inventoryFixtureWithoutVariant();

        $adjustment = app(CreateInventoryAdjustment::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'adjustment_type' => InventoryAdjustmentType::Increase->value,
            'reason' => 'Ajuste de ingreso',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => '4',
                    'unit_cost' => '900',
                ],
            ],
        ]);

        $this->assertSame(1, InventoryMovement::query()->count());

        app(PostInventoryAdjustmentToInventory::class)->handle($adjustment);

        $this->assertSame(1, InventoryMovement::query()->count());
        $this->assertSame('4.000000', InventoryBalance::query()->firstOrFail()->quantity_on_hand);
    }

    public function test_it_rejects_inventory_decrease_adjustment_without_enough_stock(): void
    {
        [$company, $branch, $warehouse, $product] = $this->inventoryFixtureWithoutVariant();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('El ajuste no tiene stock suficiente para descontar la cantidad indicada.');

        try {
            app(CreateInventoryAdjustment::class)->handle($company, [
                'branch_id' => $branch->id,
                'warehouse_id' => $warehouse->id,
                'adjustment_type' => InventoryAdjustmentType::Decrease->value,
                'reason' => 'Merma',
                'items' => [
                    [
                        'product_id' => $product->id,
                        'quantity' => '1',
                    ],
                ],
            ]);
        } finally {
            $this->assertSame(0, InventoryMovement::query()->count());
            $this->assertSame(0, InventoryBalance::query()->count());
        }
    }

    public function test_it_rejects_foreign_variant_in_inventory_adjustment(): void
    {
        [$company, $branch, $warehouse, $product] = $this->inventoryFixtureWithoutVariant();
        [, , , , $foreignVariant] = $this->inventoryFixture();

        $this->expectException(ModelNotFoundException::class);

        app(CreateInventoryAdjustment::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'adjustment_type' => InventoryAdjustmentType::Increase->value,
            'reason' => 'Cruce invalido',
            'items' => [
                [
                    'product_id' => $product->id,
                    'product_variant_id' => $foreignVariant->id,
                    'quantity' => '1',
                    'unit_cost' => '1000',
                ],
            ],
        ]);
    }

    protected function inventoryFixture(): array
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Ajustes Retail SAS',
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
            'name' => 'Confiteria',
            'code' => 'CNF',
            'status' => RecordStatus::Active->value,
        ]);
        $brand = Brand::query()->create([
            'company_id' => $company->id,
            'name' => 'Dulce Norte',
            'status' => RecordStatus::Active->value,
        ]);
        $product = Product::query()->create([
            'company_id' => $company->id,
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'base_unit_id' => $unit->id,
            'name' => 'Caramelo',
            'cost' => 800,
            'price_1' => 1400,
            'tracks_inventory' => true,
            'minimum_stock' => 1,
            'status' => RecordStatus::Active->value,
        ]);
        $attribute = Attribute::query()->create([
            'company_id' => $company->id,
            'name' => 'Color',
            'code' => 'COL',
            'status' => RecordStatus::Active->value,
        ]);
        $value = $attribute->values()->create([
            'value' => 'Rojo',
            'status' => RecordStatus::Active->value,
        ]);
        $variant = ProductVariant::query()->create([
            'company_id' => $company->id,
            'product_id' => $product->id,
            'sku' => 'CAR-ROJ',
            'status' => RecordStatus::Active->value,
        ]);
        $variant->attributeValues()->attach($value->id);

        return [$company, $branch, $warehouse, $product, $variant];
    }

    protected function inventoryFixtureWithoutVariant(): array
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Ajustes Base SAS',
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
            'name' => 'Lacteos',
            'code' => 'LAC',
            'status' => RecordStatus::Active->value,
        ]);
        $product = Product::query()->create([
            'company_id' => $company->id,
            'category_id' => $category->id,
            'base_unit_id' => $unit->id,
            'name' => 'Leche Entera',
            'cost' => 1000,
            'price_1' => 1600,
            'tracks_inventory' => true,
            'minimum_stock' => 1,
            'status' => RecordStatus::Active->value,
        ]);

        return [$company, $branch, $warehouse, $product];
    }
}
