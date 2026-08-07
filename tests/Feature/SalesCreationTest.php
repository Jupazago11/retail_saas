<?php

namespace Tests\Feature;

use App\Actions\Companies\CreateCompany;
use App\Actions\Inventory\CreateInventoryAdjustment;
use App\Actions\Sales\CreateSale;
use App\Enums\InventoryAdjustmentType;
use App\Enums\RecordStatus;
use App\Enums\SaleStatus;
use App\Models\Attribute;
use App\Models\Brand;
use App\Models\Category;
use App\Models\CompanyLimitOverride;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductPresentation;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\Unit;
use App\Models\User;
use App\Services\Inventory\PostSaleToInventory;
use App\Services\Settings\CompanySettings;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\Concerns\InteractsWithCompanyPlans;
use Tests\TestCase;

class SalesCreationTest extends TestCase
{
    use InteractsWithCompanyPlans, RefreshDatabase;

    public function test_it_creates_confirmed_sale_with_totals_cost_snapshot_and_inventory_posting(): void
    {
        [$owner, $company, $branch, $warehouse, $product, $presentation, $variant] = $this->saleFixture();
        $this->assignCompanyPlan($company, 'pro');
        app(CompanySettings::class)->set($company, 'pos', 'allow_manual_discounts', true);

        app(CreateInventoryAdjustment::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'adjustment_type' => InventoryAdjustmentType::Increase->value,
            'reason' => 'Stock inicial',
            'items' => [
                [
                    'product_id' => $product->id,
                    'product_variant_id' => $variant->id,
                    'quantity' => '24',
                    'unit_cost' => '1000',
                ],
            ],
        ]);

        $sale = app(CreateSale::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'user_id' => $owner->id,
            'status' => SaleStatus::Confirmed->value,
            'sold_at' => now(),
            'items' => [
                [
                    'product_id' => $product->id,
                    'product_presentation_id' => $presentation->id,
                    'product_variant_id' => $variant->id,
                    'quantity' => '2',
                    'unit_price' => '2500',
                    'discount_amount' => '500',
                    'tax_rate' => '19',
                ],
            ],
        ]);

        $this->assertSame('5000.00', $sale->subtotal);
        $this->assertSame('500.00', $sale->discount_total);
        $this->assertSame('855.00', $sale->tax_total);
        $this->assertSame('5355.00', $sale->grand_total);
        $this->assertSame(1, $sale->document_sequence);
        $this->assertSame('VTA-000001', $sale->document_number);
        $this->assertNotNull($sale->posted_to_inventory_at);
        $this->assertCount(1, $sale->items);
        $this->assertSame('12.000000', $sale->items->first()->base_quantity);
        $this->assertSame('1000.0000', $sale->items->first()->cost_snapshot);

        $this->assertDatabaseHas('inventory_movements', [
            'company_id' => $company->id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'movement_type' => 'sale_out',
            'quantity_out' => '12.000000',
            'unit_cost' => '1000.0000',
            'balance_quantity' => '12.000000',
            'balance_cost' => '1000.0000',
        ]);

        $this->assertDatabaseHas('inventory_balances', [
            'company_id' => $company->id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'quantity_on_hand' => '12.000000',
            'average_cost' => '1000.0000',
        ]);
    }

    public function test_draft_sale_does_not_post_inventory_until_explicitly_processed(): void
    {
        [, $company, $branch, $warehouse, $product] = $this->saleFixtureWithoutVariant();

        $sale = app(CreateSale::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'status' => SaleStatus::Draft->value,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => '1',
                    'unit_price' => '2000',
                ],
            ],
        ]);

        $this->assertNull($sale->posted_to_inventory_at);
        $this->assertSame('VTA-000001', $sale->document_number);
        $this->assertSame(0, InventoryMovement::query()->count());
        $this->assertSame(0, InventoryBalance::query()->count());
    }

    public function test_it_assigns_consecutive_internal_document_numbers_per_company(): void
    {
        [$owner, $company, $branch, $warehouse, $product] = $this->saleFixtureWithoutVariant();
        app(CompanySettings::class)->set($company, 'pos', 'allow_negative_stock', true);

        $firstSale = app(CreateSale::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'user_id' => $owner->id,
            'status' => SaleStatus::Draft->value,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => '1',
                    'unit_price' => '1800',
                ],
            ],
        ]);

        $secondSale = app(CreateSale::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'user_id' => $owner->id,
            'status' => SaleStatus::Confirmed->value,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => '1',
                    'unit_price' => '1800',
                ],
            ],
        ]);

        $this->assertSame('VTA-000001', $firstSale->document_number);
        $this->assertSame('VTA-000002', $secondSale->document_number);
    }

    public function test_it_can_use_company_settings_for_internal_document_prefix_and_starting_sequence(): void
    {
        [$owner, $company, $branch, $warehouse, $product] = $this->saleFixtureWithoutVariant();
        app(CompanySettings::class)->set($company, 'pos', 'sale_document_prefix', 'POS-');
        app(CompanySettings::class)->set($company, 'pos', 'sale_document_starting_sequence', 1001);

        $sale = app(CreateSale::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'user_id' => $owner->id,
            'status' => SaleStatus::Draft->value,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => '1',
                    'unit_price' => '1800',
                ],
            ],
        ]);

        $this->assertSame(1001, $sale->document_sequence);
        $this->assertSame('POS-001001', $sale->document_number);
    }

    public function test_it_does_not_roll_back_internal_sequence_if_setting_is_lower_than_existing_history(): void
    {
        [$owner, $company, $branch, $warehouse, $product] = $this->saleFixtureWithoutVariant();

        $firstSale = app(CreateSale::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'user_id' => $owner->id,
            'status' => SaleStatus::Draft->value,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => '1',
                    'unit_price' => '1800',
                ],
            ],
        ]);

        app(CompanySettings::class)->set($company, 'pos', 'sale_document_starting_sequence', 1);
        app(CompanySettings::class)->set($company, 'pos', 'sale_document_prefix', 'POS-');

        $secondSale = app(CreateSale::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'user_id' => $owner->id,
            'status' => SaleStatus::Draft->value,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => '1',
                    'unit_price' => '1800',
                ],
            ],
        ]);

        $this->assertSame('VTA-000001', $firstSale->document_number);
        $this->assertSame(2, $secondSale->document_sequence);
        $this->assertSame('POS-000002', $secondSale->document_number);
    }

    public function test_posting_sale_to_inventory_is_idempotent(): void
    {
        [, $company, $branch, $warehouse, $product] = $this->saleFixtureWithoutVariant();

        app(CreateInventoryAdjustment::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'adjustment_type' => InventoryAdjustmentType::Increase->value,
            'reason' => 'Stock inicial',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => '5',
                    'unit_cost' => '900',
                ],
            ],
        ]);

        $sale = app(CreateSale::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'status' => SaleStatus::Confirmed->value,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => '2',
                    'unit_price' => '1800',
                ],
            ],
        ]);

        $this->assertSame(2, InventoryMovement::query()->count());

        app(PostSaleToInventory::class)->handle($sale);

        $this->assertSame(2, InventoryMovement::query()->count());
        $this->assertSame('3.000000', InventoryBalance::query()->firstOrFail()->quantity_on_hand);
    }

    public function test_it_rejects_confirmed_sale_when_stock_is_insufficient(): void
    {
        [, $company, $branch, $warehouse, $product] = $this->saleFixtureWithoutVariant();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La venta no tiene stock suficiente para completar la salida.');

        try {
            app(CreateSale::class)->handle($company, [
                'branch_id' => $branch->id,
                'warehouse_id' => $warehouse->id,
                'status' => SaleStatus::Confirmed->value,
                'items' => [
                    [
                        'product_id' => $product->id,
                        'quantity' => '1',
                        'unit_price' => '1800',
                    ],
                ],
            ]);
        } finally {
            $this->assertSame(0, Sale::query()->count());
            $this->assertSame(0, InventoryMovement::query()->count());
        }
    }

    public function test_it_rejects_manual_discounts_when_company_disallows_them(): void
    {
        [, $company, $branch, $warehouse, $product] = $this->saleFixtureWithoutVariant();
        $this->assignCompanyPlan($company, 'pro');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('La empresa no permite descuentos manuales en la venta.');

        app(CreateSale::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'status' => SaleStatus::Draft->value,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => '1',
                    'unit_price' => '1800',
                    'discount_amount' => '100',
                ],
            ],
        ]);
    }

    public function test_it_allows_confirmed_sale_to_leave_negative_stock_when_company_enables_it(): void
    {
        [, $company, $branch, $warehouse, $product] = $this->saleFixtureWithoutVariant();
        app(CompanySettings::class)->set($company, 'pos', 'allow_negative_stock', true);

        $sale = app(CreateSale::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'status' => SaleStatus::Confirmed->value,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => '1',
                    'unit_price' => '1800',
                ],
            ],
        ]);

        $this->assertNotNull($sale->posted_to_inventory_at);
        $this->assertSame('900.0000', $sale->items()->firstOrFail()->cost_snapshot);
        $this->assertDatabaseHas('inventory_movements', [
            'company_id' => $company->id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'movement_type' => 'sale_out',
            'quantity_out' => '1.000000',
            'unit_cost' => '900.0000',
            'balance_quantity' => '-1.000000',
            'balance_cost' => '900.0000',
        ]);
        $this->assertDatabaseHas('inventory_balances', [
            'company_id' => $company->id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'quantity_on_hand' => '-1.000000',
            'average_cost' => '900.0000',
        ]);
    }

    public function test_it_rejects_foreign_catalog_records_in_sale_creation(): void
    {
        [, $company, $branch, $warehouse, $product] = $this->saleFixtureWithoutVariant();
        [, , , , $foreignProduct, , $foreignVariant] = $this->saleFixture();

        $this->expectException(ModelNotFoundException::class);

        app(CreateSale::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'items' => [
                [
                    'product_id' => $foreignProduct->id,
                    'product_variant_id' => $foreignVariant->id,
                    'quantity' => '1',
                    'unit_price' => '1000',
                ],
            ],
        ]);
    }

    public function test_it_blocks_confirmed_sales_when_company_reaches_monthly_limit(): void
    {
        [$owner, $company, $branch, $warehouse, $product] = $this->saleFixtureWithoutVariant();
        $this->assignCompanyPlan($company, 'basic');
        app(CompanySettings::class)->set($company, 'pos', 'allow_negative_stock', true);

        CompanyLimitOverride::query()->create([
            'company_id' => $company->id,
            'limit_key' => 'max_monthly_sales',
            'limit_value' => 1,
            'starts_at' => now()->subMinute(),
        ]);

        app(CreateSale::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'user_id' => $owner->id,
            'status' => SaleStatus::Confirmed->value,
            'sold_at' => now()->startOfMonth()->addDays(2),
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => '1',
                    'unit_price' => '1800',
                ],
            ],
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('El plan actual ya alcanzo el limite de ventas confirmadas del mes.');

        app(CreateSale::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'user_id' => $owner->id,
            'status' => SaleStatus::Confirmed->value,
            'sold_at' => now()->startOfMonth()->addDays(3),
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => '1',
                    'unit_price' => '1800',
                ],
            ],
        ]);
    }

    public function test_it_allows_draft_sales_even_when_monthly_confirmed_limit_is_reached(): void
    {
        [$owner, $company, $branch, $warehouse, $product] = $this->saleFixtureWithoutVariant();
        $this->assignCompanyPlan($company, 'basic');

        CompanyLimitOverride::query()->create([
            'company_id' => $company->id,
            'limit_key' => 'max_monthly_sales',
            'limit_value' => 1,
            'starts_at' => now()->subMinute(),
        ]);

        Sale::query()->create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'user_id' => $owner->id,
            'sale_type' => 'pos',
            'status' => SaleStatus::Confirmed->value,
            'sold_at' => now()->startOfMonth()->addDay(),
            'subtotal' => '1000.00',
            'discount_total' => '0.00',
            'tax_total' => '0.00',
            'grand_total' => '1000.00',
        ]);

        $sale = app(CreateSale::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'user_id' => $owner->id,
            'status' => SaleStatus::Draft->value,
            'sold_at' => now()->startOfMonth()->addDays(4),
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => '1',
                    'unit_price' => '1800',
                ],
            ],
        ]);

        $this->assertSame(SaleStatus::Draft->value, $sale->status);
    }

    protected function saleFixture(): array
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Ventas Retail SAS',
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
            'name' => 'Pico Alto',
            'status' => RecordStatus::Active->value,
        ]);
        $product = Product::query()->create([
            'company_id' => $company->id,
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'base_unit_id' => $unit->id,
            'name' => 'Jugo Naranja',
            'sku' => 'JUG-001',
            'cost' => 1000,
            'price_1' => 2500,
            'tracks_inventory' => true,
            'minimum_stock' => 1,
            'status' => RecordStatus::Active->value,
        ]);
        $presentation = ProductPresentation::query()->create([
            'company_id' => $company->id,
            'product_id' => $product->id,
            'unit_id' => $unit->id,
            'name' => 'Pack x 6',
            'conversion_factor' => '6.000000',
            'price_1' => 15000,
            'status' => RecordStatus::Active->value,
        ]);
        $attribute = Attribute::query()->create([
            'company_id' => $company->id,
            'name' => 'Pulpa',
            'code' => 'PUL',
            'status' => RecordStatus::Active->value,
        ]);
        $value = $attribute->values()->create([
            'value' => 'Con Pulpa',
            'status' => RecordStatus::Active->value,
        ]);
        $variant = ProductVariant::query()->create([
            'company_id' => $company->id,
            'product_id' => $product->id,
            'sku' => 'JUG-001-P',
            'status' => RecordStatus::Active->value,
        ]);
        $variant->attributeValues()->attach($value->id);

        return [$owner, $company, $branch, $warehouse, $product, $presentation, $variant];
    }

    protected function saleFixtureWithoutVariant(): array
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Ventas Base SAS',
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
            'price_1' => 1800,
            'tracks_inventory' => true,
            'minimum_stock' => 1,
            'status' => RecordStatus::Active->value,
        ]);

        return [$owner, $company, $branch, $warehouse, $product];
    }
}
