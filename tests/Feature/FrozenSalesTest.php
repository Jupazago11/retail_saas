<?php

namespace Tests\Feature;

use App\Actions\Companies\CreateCompany;
use App\Actions\Inventory\CreateInventoryAdjustment;
use App\Actions\Sales\CancelFrozenSale;
use App\Actions\Sales\ConvertFrozenSaleToSale;
use App\Actions\Sales\CreateFrozenSale;
use App\Enums\FrozenSaleStatus;
use App\Enums\InventoryAdjustmentType;
use App\Enums\RecordStatus;
use App\Models\Attribute;
use App\Models\FrozenSale;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductPresentation;
use App\Models\ProductVariant;
use App\Models\Unit;
use App\Models\User;
use App\Services\Settings\CompanySettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\Concerns\InteractsWithCompanyPlans;
use Tests\TestCase;

class FrozenSalesTest extends TestCase
{
    use InteractsWithCompanyPlans;
    use RefreshDatabase;

    public function test_it_creates_frozen_sale_with_expiration_and_without_inventory_impact(): void
    {
        [$owner, $company, $branch, $warehouse, $cashRegister, $product, $presentation, $variant] = $this->frozenSaleFixture();
        app(CompanySettings::class)->set($company, 'pos', 'frozen_sales_expiration_minutes', 45);
        app(CompanySettings::class)->set($company, 'pos', 'allow_manual_discounts', true);

        $frozenSale = app(CreateFrozenSale::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'cash_register_id' => $cashRegister->id,
            'created_by' => $owner->id,
            'label' => 'Mesa 4',
            'items' => [
                [
                    'product_id' => $product->id,
                    'product_presentation_id' => $presentation->id,
                    'product_variant_id' => $variant->id,
                    'quantity' => '1',
                    'unit_price' => '15000',
                    'discount_amount' => '500',
                    'tax_rate' => '19',
                ],
            ],
        ]);

        $this->assertSame(FrozenSaleStatus::Open->value, $frozenSale->status);
        $this->assertNotNull($frozenSale->expires_at);
        $this->assertSame('15000.00', $frozenSale->payload_snapshot['totals']['subtotal']);
        $this->assertSame('500.00', $frozenSale->payload_snapshot['totals']['discount_total']);
        $this->assertSame(0, InventoryMovement::query()->count());
    }

    public function test_it_converts_frozen_sale_into_confirmed_sale_and_marks_it_as_converted(): void
    {
        [$owner, $company, $branch, $warehouse, $cashRegister, $product, $presentation, $variant] = $this->frozenSaleFixture();

        app(CreateInventoryAdjustment::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'adjustment_type' => InventoryAdjustmentType::Increase->value,
            'reason' => 'Stock inicial',
            'items' => [
                [
                    'product_id' => $product->id,
                    'product_variant_id' => $variant->id,
                    'quantity' => '12',
                    'unit_cost' => '1000',
                ],
            ],
        ]);

        $frozenSale = app(CreateFrozenSale::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'cash_register_id' => $cashRegister->id,
            'created_by' => $owner->id,
            'label' => 'Pedido rapido',
            'items' => [
                [
                    'product_id' => $product->id,
                    'product_presentation_id' => $presentation->id,
                    'product_variant_id' => $variant->id,
                    'quantity' => '1',
                    'unit_price' => '15000',
                ],
            ],
        ]);

        $sale = app(ConvertFrozenSaleToSale::class)->handle($company, $frozenSale, [
            'sold_at' => now(),
        ]);

        $this->assertSame('confirmed', $sale->status);
        $this->assertSame(FrozenSaleStatus::Converted->value, $frozenSale->fresh()->status);
        $this->assertSame($sale->id, $frozenSale->fresh()->converted_sale_id);
        $this->assertSame(2, InventoryMovement::query()->count());
    }

    public function test_it_rejects_frozen_sale_creation_when_feature_is_disabled(): void
    {
        [$owner, $company, $branch, $warehouse, $cashRegister, $product] = $this->frozenSaleFixtureWithoutVariant();
        app(CompanySettings::class)->set($company, 'pos', 'frozen_sales_enabled', false);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Las ventas congeladas estan deshabilitadas para esta empresa.');

        app(CreateFrozenSale::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'cash_register_id' => $cashRegister->id,
            'created_by' => $owner->id,
            'label' => 'Bloqueada',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => '1',
                    'unit_price' => '2000',
                ],
            ],
        ]);
    }

    public function test_it_rejects_frozen_sale_creation_when_plan_does_not_include_feature(): void
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Frozen Basic SAS',
        ]);
        app(CompanySettings::class)->set($company, 'pos', 'frozen_sales_enabled', true);
        $branch = $company->branches()->firstOrFail();
        $warehouse = $company->warehouses()->firstOrFail();
        $cashRegister = $company->cashRegisters()->firstOrFail();
        $unit = Unit::query()->create([
            'company_id' => $company->id,
            'code' => 'UND-BASIC',
            'name' => 'Unidad',
            'precision_scale' => 0,
            'status' => RecordStatus::Active->value,
        ]);
        $category = \App\Models\Category::query()->create([
            'company_id' => $company->id,
            'name' => 'Basicos',
            'code' => 'BAS',
            'status' => RecordStatus::Active->value,
        ]);
        $product = Product::query()->create([
            'company_id' => $company->id,
            'category_id' => $category->id,
            'base_unit_id' => $unit->id,
            'name' => 'Producto basic frozen',
            'cost' => 1000,
            'price_1' => 2000,
            'tracks_inventory' => true,
            'minimum_stock' => 1,
            'status' => RecordStatus::Active->value,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('El plan actual no tiene habilitada la feature de ventas congeladas.');

        app(CreateFrozenSale::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'cash_register_id' => $cashRegister->id,
            'created_by' => $owner->id,
            'label' => 'Bloqueada por plan',
            'items' => [[
                'product_id' => $product->id,
                'quantity' => '1',
                'unit_price' => '2000',
            ]],
        ]);
    }

    public function test_it_rejects_manual_discounts_when_company_disallows_them_in_frozen_sales(): void
    {
        [$owner, $company, $branch, $warehouse, $cashRegister, $product] = $this->frozenSaleFixtureWithoutVariant();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('La empresa no permite descuentos manuales en ventas congeladas.');

        app(CreateFrozenSale::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'cash_register_id' => $cashRegister->id,
            'created_by' => $owner->id,
            'label' => 'Con descuento',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => '1',
                    'unit_price' => '2000',
                    'discount_amount' => '100',
                ],
            ],
        ]);
    }

    public function test_it_marks_frozen_sale_as_expired_when_conversion_is_attempted_after_expiration(): void
    {
        [$owner, $company, $branch, $warehouse, $cashRegister, $product] = $this->frozenSaleFixtureWithoutVariant();

        $frozenSale = app(CreateFrozenSale::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'cash_register_id' => $cashRegister->id,
            'created_by' => $owner->id,
            'label' => 'Expirada',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => '1',
                    'unit_price' => '2000',
                ],
            ],
        ]);

        $frozenSale->update([
            'expires_at' => now()->subMinute(),
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('La venta congelada ya expiro.');

        try {
            app(ConvertFrozenSaleToSale::class)->handle($company, $frozenSale);
        } finally {
            $this->assertSame(FrozenSaleStatus::Expired->value, $frozenSale->fresh()->status);
        }
    }

    public function test_it_cancels_open_frozen_sale_without_inventory_impact(): void
    {
        [$owner, $company, $branch, $warehouse, $cashRegister, $product] = $this->frozenSaleFixtureWithoutVariant();

        $frozenSale = app(CreateFrozenSale::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'cash_register_id' => $cashRegister->id,
            'created_by' => $owner->id,
            'label' => 'Cancelar',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => '1',
                    'unit_price' => '2000',
                ],
            ],
        ]);

        $cancelled = app(CancelFrozenSale::class)->handle($company, $frozenSale);

        $this->assertSame(FrozenSaleStatus::Cancelled->value, $cancelled->status);
        $this->assertSame(0, InventoryMovement::query()->count());
    }

    protected function frozenSaleFixture(): array
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Frozen Sales SAS',
        ]);
        $this->assignCompanyPlan($company, 'pro');
        $branch = $company->branches()->firstOrFail();
        $warehouse = $company->warehouses()->firstOrFail();
        $cashRegister = $company->cashRegisters()->firstOrFail();
        $unit = Unit::query()->create([
            'company_id' => $company->id,
            'code' => 'UND',
            'name' => 'Unidad',
            'precision_scale' => 0,
            'status' => RecordStatus::Active->value,
        ]);
        $category = \App\Models\Category::query()->create([
            'company_id' => $company->id,
            'name' => 'Bebidas',
            'code' => 'BEB',
            'status' => RecordStatus::Active->value,
        ]);
        $brand = \App\Models\Brand::query()->create([
            'company_id' => $company->id,
            'name' => 'Costa Azul',
            'status' => RecordStatus::Active->value,
        ]);
        $product = Product::query()->create([
            'company_id' => $company->id,
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'base_unit_id' => $unit->id,
            'name' => 'Gaseosa',
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
            'name' => 'Sabor',
            'code' => 'SAB',
            'status' => RecordStatus::Active->value,
        ]);
        $value = $attribute->values()->create([
            'value' => 'Cola',
            'status' => RecordStatus::Active->value,
        ]);
        $variant = ProductVariant::query()->create([
            'company_id' => $company->id,
            'product_id' => $product->id,
            'sku' => 'GAS-COL',
            'status' => RecordStatus::Active->value,
        ]);
        $variant->attributeValues()->attach($value->id);

        return [$owner, $company, $branch, $warehouse, $cashRegister, $product, $presentation, $variant];
    }

    protected function frozenSaleFixtureWithoutVariant(): array
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Frozen Sales Base SAS',
        ]);
        $this->assignCompanyPlan($company, 'pro');
        $branch = $company->branches()->firstOrFail();
        $warehouse = $company->warehouses()->firstOrFail();
        $cashRegister = $company->cashRegisters()->firstOrFail();
        $unit = Unit::query()->create([
            'company_id' => $company->id,
            'code' => 'UND',
            'name' => 'Unidad',
            'precision_scale' => 0,
            'status' => RecordStatus::Active->value,
        ]);
        $category = \App\Models\Category::query()->create([
            'company_id' => $company->id,
            'name' => 'Snacks',
            'code' => 'SNK',
            'status' => RecordStatus::Active->value,
        ]);
        $product = Product::query()->create([
            'company_id' => $company->id,
            'category_id' => $category->id,
            'base_unit_id' => $unit->id,
            'name' => 'Papas',
            'cost' => 900,
            'price_1' => 2000,
            'tracks_inventory' => true,
            'minimum_stock' => 1,
            'status' => RecordStatus::Active->value,
        ]);

        return [$owner, $company, $branch, $warehouse, $cashRegister, $product];
    }
}
