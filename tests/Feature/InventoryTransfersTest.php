<?php

namespace Tests\Feature;

use App\Actions\Companies\CreateCompany;
use App\Actions\Inventory\CreateInventoryAdjustment;
use App\Actions\Inventory\CreateInventoryTransfer;
use App\Enums\InventoryAdjustmentType;
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
use App\Models\Warehouse;
use App\Services\Inventory\PostInventoryTransferToInventory;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryTransfersTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_transfers_inventory_between_warehouses_and_posts_both_movements(): void
    {
        [$company, $sourceWarehouse, $destinationWarehouse, $product, $variant] = $this->transferFixture();

        app(CreateInventoryAdjustment::class)->handle($company, [
            'branch_id' => $sourceWarehouse->branch_id,
            'warehouse_id' => $sourceWarehouse->id,
            'adjustment_type' => InventoryAdjustmentType::Increase->value,
            'reason' => 'Carga inicial',
            'items' => [
                [
                    'product_id' => $product->id,
                    'product_variant_id' => $variant->id,
                    'quantity' => '8',
                    'unit_cost' => '1100',
                ],
            ],
        ]);

        $transfer = app(CreateInventoryTransfer::class)->handle($company, [
            'source_warehouse_id' => $sourceWarehouse->id,
            'destination_warehouse_id' => $destinationWarehouse->id,
            'reason' => 'Reabastecimiento interno',
            'transferred_at' => now(),
            'items' => [
                [
                    'product_id' => $product->id,
                    'product_variant_id' => $variant->id,
                    'quantity' => '3',
                ],
            ],
        ]);

        $this->assertNotNull($transfer->posted_to_inventory_at);

        $this->assertDatabaseHas('inventory_movements', [
            'company_id' => $company->id,
            'warehouse_id' => $sourceWarehouse->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'movement_type' => 'transfer_out',
            'quantity_out' => '3.000000',
            'unit_cost' => '1100.0000',
            'balance_quantity' => '5.000000',
            'balance_cost' => '1100.0000',
        ]);

        $this->assertDatabaseHas('inventory_movements', [
            'company_id' => $company->id,
            'warehouse_id' => $destinationWarehouse->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'movement_type' => 'transfer_in',
            'quantity_in' => '3.000000',
            'unit_cost' => '1100.0000',
            'balance_quantity' => '3.000000',
            'balance_cost' => '1100.0000',
        ]);

        $this->assertDatabaseHas('inventory_balances', [
            'company_id' => $company->id,
            'warehouse_id' => $sourceWarehouse->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'quantity_on_hand' => '5.000000',
            'average_cost' => '1100.0000',
        ]);

        $this->assertDatabaseHas('inventory_balances', [
            'company_id' => $company->id,
            'warehouse_id' => $destinationWarehouse->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'quantity_on_hand' => '3.000000',
            'average_cost' => '1100.0000',
        ]);
    }

    public function test_transfer_posting_is_idempotent(): void
    {
        [$company, $sourceWarehouse, $destinationWarehouse, $product] = $this->transferFixtureWithoutVariant();

        app(CreateInventoryAdjustment::class)->handle($company, [
            'branch_id' => $sourceWarehouse->branch_id,
            'warehouse_id' => $sourceWarehouse->id,
            'adjustment_type' => InventoryAdjustmentType::Increase->value,
            'reason' => 'Carga inicial',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => '6',
                    'unit_cost' => '900',
                ],
            ],
        ]);

        $transfer = app(CreateInventoryTransfer::class)->handle($company, [
            'source_warehouse_id' => $sourceWarehouse->id,
            'destination_warehouse_id' => $destinationWarehouse->id,
            'reason' => 'Prueba idempotente',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => '2',
                ],
            ],
        ]);

        $this->assertSame(3, InventoryMovement::query()->count());

        app(PostInventoryTransferToInventory::class)->handle($transfer);

        $this->assertSame(3, InventoryMovement::query()->count());
    }

    public function test_it_rejects_transfer_without_enough_stock_in_source_warehouse(): void
    {
        [$company, $sourceWarehouse, $destinationWarehouse, $product] = $this->transferFixtureWithoutVariant();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('El traslado no tiene stock suficiente en la bodega origen.');

        try {
            app(CreateInventoryTransfer::class)->handle($company, [
                'source_warehouse_id' => $sourceWarehouse->id,
                'destination_warehouse_id' => $destinationWarehouse->id,
                'reason' => 'Sin stock',
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

    public function test_it_rejects_transfer_with_same_source_and_destination_warehouse(): void
    {
        [$company, $sourceWarehouse, , $product] = $this->transferFixtureWithoutVariant();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('El traslado requiere bodegas origen y destino distintas.');

        app(CreateInventoryTransfer::class)->handle($company, [
            'source_warehouse_id' => $sourceWarehouse->id,
            'destination_warehouse_id' => $sourceWarehouse->id,
            'reason' => 'Invalido',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => '1',
                ],
            ],
        ]);
    }

    public function test_it_rejects_foreign_variant_in_transfer(): void
    {
        [$company, $sourceWarehouse, $destinationWarehouse, $product] = $this->transferFixtureWithoutVariant();
        [, , , , $foreignVariant] = $this->transferFixture();

        $this->expectException(ModelNotFoundException::class);

        app(CreateInventoryTransfer::class)->handle($company, [
            'source_warehouse_id' => $sourceWarehouse->id,
            'destination_warehouse_id' => $destinationWarehouse->id,
            'reason' => 'Cruce invalido',
            'items' => [
                [
                    'product_id' => $product->id,
                    'product_variant_id' => $foreignVariant->id,
                    'quantity' => '1',
                ],
            ],
        ]);
    }

    protected function transferFixture(): array
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Traslados Retail SAS',
        ]);
        $sourceWarehouse = $company->warehouses()->firstOrFail();
        $destinationWarehouse = Warehouse::query()->create([
            'company_id' => $company->id,
            'branch_id' => $sourceWarehouse->branch_id,
            'name' => 'Bodega Secundaria',
            'code' => 'SEC',
            'status' => RecordStatus::Active->value,
            'is_primary' => false,
        ]);
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
            'name' => 'Sierra',
            'status' => RecordStatus::Active->value,
        ]);
        $product = Product::query()->create([
            'company_id' => $company->id,
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'base_unit_id' => $unit->id,
            'name' => 'Agua Mineral',
            'cost' => 900,
            'price_1' => 1400,
            'tracks_inventory' => true,
            'minimum_stock' => 1,
            'status' => RecordStatus::Active->value,
        ]);
        $attribute = Attribute::query()->create([
            'company_id' => $company->id,
            'name' => 'Tamano',
            'code' => 'TAM',
            'status' => RecordStatus::Active->value,
        ]);
        $value = $attribute->values()->create([
            'value' => '500ml',
            'status' => RecordStatus::Active->value,
        ]);
        $variant = ProductVariant::query()->create([
            'company_id' => $company->id,
            'product_id' => $product->id,
            'sku' => 'AGU-500',
            'status' => RecordStatus::Active->value,
        ]);
        $variant->attributeValues()->attach($value->id);

        return [$company, $sourceWarehouse, $destinationWarehouse, $product, $variant];
    }

    protected function transferFixtureWithoutVariant(): array
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Traslados Base SAS',
        ]);
        $sourceWarehouse = $company->warehouses()->firstOrFail();
        $destinationWarehouse = Warehouse::query()->create([
            'company_id' => $company->id,
            'branch_id' => $sourceWarehouse->branch_id,
            'name' => 'Bodega Auxiliar',
            'code' => 'AUX',
            'status' => RecordStatus::Active->value,
            'is_primary' => false,
        ]);
        $unit = Unit::query()->create([
            'company_id' => $company->id,
            'code' => 'UND',
            'name' => 'Unidad',
            'precision_scale' => 0,
            'status' => RecordStatus::Active->value,
        ]);
        $category = Category::query()->create([
            'company_id' => $company->id,
            'name' => 'Panaderia',
            'code' => 'PAN',
            'status' => RecordStatus::Active->value,
        ]);
        $product = Product::query()->create([
            'company_id' => $company->id,
            'category_id' => $category->id,
            'base_unit_id' => $unit->id,
            'name' => 'Pan Tajado',
            'cost' => 700,
            'price_1' => 1200,
            'tracks_inventory' => true,
            'minimum_stock' => 1,
            'status' => RecordStatus::Active->value,
        ]);

        return [$company, $sourceWarehouse, $destinationWarehouse, $product];
    }
}
