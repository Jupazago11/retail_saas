<?php

namespace Tests\Feature;

use App\Actions\Companies\CreateCompany;
use App\Actions\Inventory\CreateInventoryAdjustment;
use App\Actions\Promotions\CreatePromotion;
use App\Actions\Sales\CreateSale;
use App\Enums\InventoryAdjustmentType;
use App\Enums\PromotionDiscountType;
use App\Enums\PromotionTargetType;
use App\Enums\PromotionType;
use App\Enums\RecordStatus;
use App\Enums\SaleStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\Sale;
use App\Models\Unit;
use App\Models\User;
use App\Services\Settings\CompanySettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\Concerns\InteractsWithCompanyPlans;
use Tests\TestCase;

class PromotionsAndCombosTest extends TestCase
{
    use InteractsWithCompanyPlans;
    use RefreshDatabase;

    public function test_it_applies_percentage_promotion_to_matching_product(): void
    {
        [$owner, $company, $branch, $warehouse, $productA] = $this->fixture();

        app(CreatePromotion::class)->handle($company, [
            'name' => 'Promo arroz 10',
            'promotion_type' => PromotionType::ProductDiscount->value,
            'discount_type' => PromotionDiscountType::Percentage->value,
            'discount_value' => '10',
            'targets' => [
                [
                    'target_type' => PromotionTargetType::Product->value,
                    'target_id' => $productA->id,
                    'min_quantity' => '1',
                ],
            ],
        ]);

        $sale = app(CreateSale::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'user_id' => $owner->id,
            'status' => SaleStatus::Confirmed->value,
            'items' => [
                [
                    'product_id' => $productA->id,
                    'quantity' => '2',
                    'unit_price' => '1800',
                ],
            ],
        ]);

        $item = $sale->items->first();

        $this->assertSame('3600.00', $sale->subtotal);
        $this->assertSame('360.00', $sale->discount_total);
        $this->assertSame('3240.00', $sale->grand_total);
        $this->assertSame('360.00', $item->discount_amount);
        $this->assertSame('product_discount', $item->promotion_snapshot[0]['promotion_type']);
        $this->assertSame('360.00', $item->promotion_snapshot[0]['discount_amount']);
    }

    public function test_it_applies_combo_fixed_price_and_distributes_discount_between_lines(): void
    {
        [$owner, $company, $branch, $warehouse, $productA, $productB] = $this->fixture();

        app(CreatePromotion::class)->handle($company, [
            'name' => 'Combo arroz + frijol',
            'promotion_type' => PromotionType::ComboPrice->value,
            'discount_type' => PromotionDiscountType::FixedPrice->value,
            'discount_value' => '4200',
            'combo_items' => [
                [
                    'product_id' => $productA->id,
                    'required_quantity' => '1',
                ],
                [
                    'product_id' => $productB->id,
                    'required_quantity' => '1',
                ],
            ],
        ]);

        $sale = app(CreateSale::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'user_id' => $owner->id,
            'status' => SaleStatus::Confirmed->value,
            'items' => [
                [
                    'product_id' => $productA->id,
                    'quantity' => '1',
                    'unit_price' => '3000',
                ],
                [
                    'product_id' => $productB->id,
                    'quantity' => '1',
                    'unit_price' => '2000',
                ],
            ],
        ]);

        $this->assertSame('5000.00', $sale->subtotal);
        $this->assertSame('800.00', $sale->discount_total);
        $this->assertSame('4200.00', $sale->grand_total);
        $this->assertSame('480.00', $sale->items[0]->discount_amount);
        $this->assertSame('320.00', $sale->items[1]->discount_amount);
        $this->assertSame('combo_price', $sale->items[0]->promotion_snapshot[0]['promotion_type']);
        $this->assertSame('combo_price', $sale->items[1]->promotion_snapshot[0]['promotion_type']);
    }

    public function test_it_avoids_stacking_product_promotion_on_units_consumed_by_combo_when_disabled(): void
    {
        [$owner, $company, $branch, $warehouse, $productA, $productB] = $this->fixture();

        app(CompanySettings::class)->set($company, 'pos', 'allow_promotion_stacking', false);

        app(CreatePromotion::class)->handle($company, [
            'name' => 'Combo arroz + frijol',
            'promotion_type' => PromotionType::ComboPrice->value,
            'discount_type' => PromotionDiscountType::FixedPrice->value,
            'discount_value' => '4200',
            'priority' => 10,
            'combo_items' => [
                [
                    'product_id' => $productA->id,
                    'required_quantity' => '1',
                ],
                [
                    'product_id' => $productB->id,
                    'required_quantity' => '1',
                ],
            ],
        ]);

        app(CreatePromotion::class)->handle($company, [
            'name' => 'Promo arroz 10',
            'promotion_type' => PromotionType::ProductDiscount->value,
            'discount_type' => PromotionDiscountType::Percentage->value,
            'discount_value' => '10',
            'priority' => 20,
            'targets' => [
                [
                    'target_type' => PromotionTargetType::Product->value,
                    'target_id' => $productA->id,
                    'min_quantity' => '1',
                ],
            ],
        ]);

        $sale = app(CreateSale::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'user_id' => $owner->id,
            'status' => SaleStatus::Confirmed->value,
            'items' => [
                [
                    'product_id' => $productA->id,
                    'quantity' => '1',
                    'unit_price' => '3000',
                ],
                [
                    'product_id' => $productB->id,
                    'quantity' => '1',
                    'unit_price' => '2000',
                ],
            ],
        ]);

        $this->assertSame('800.00', $sale->discount_total);
        $this->assertCount(1, $sale->items[0]->promotion_snapshot);
        $this->assertSame('combo_price', $sale->items[0]->promotion_snapshot[0]['promotion_type']);
    }

    public function test_it_ignores_inactive_or_outdated_promotions(): void
    {
        [$owner, $company, $branch, $warehouse, $productA] = $this->fixture();

        app(CreatePromotion::class)->handle($company, [
            'name' => 'Promo vencida',
            'promotion_type' => PromotionType::ProductDiscount->value,
            'discount_type' => PromotionDiscountType::Percentage->value,
            'discount_value' => '10',
            'starts_at' => now()->subDays(10),
            'ends_at' => now()->subDay(),
            'targets' => [
                [
                    'target_type' => PromotionTargetType::Product->value,
                    'target_id' => $productA->id,
                    'min_quantity' => '1',
                ],
            ],
        ]);

        $sale = app(CreateSale::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'user_id' => $owner->id,
            'status' => SaleStatus::Confirmed->value,
            'items' => [
                [
                    'product_id' => $productA->id,
                    'quantity' => '1',
                    'unit_price' => '1800',
                ],
            ],
        ]);

        $this->assertSame('0.00', $sale->discount_total);
        $this->assertNull($sale->items->first()->promotion_snapshot);
    }

    public function test_it_rejects_promotion_creation_when_promotions_module_is_not_in_plan(): void
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Promos Basic SAS',
        ]);
        $unit = Unit::query()->create([
            'company_id' => $company->id,
            'code' => 'UND-BASIC',
            'name' => 'Unidad',
            'precision_scale' => 0,
            'status' => RecordStatus::Active->value,
        ]);
        $category = Category::query()->create([
            'company_id' => $company->id,
            'name' => 'Abarrotes',
            'code' => 'ABA-BASIC',
            'status' => RecordStatus::Active->value,
        ]);
        $product = Product::query()->create([
            'company_id' => $company->id,
            'category_id' => $category->id,
            'base_unit_id' => $unit->id,
            'name' => 'Arroz basic promo',
            'cost' => 900,
            'price_1' => 1800,
            'tracks_inventory' => true,
            'minimum_stock' => 1,
            'status' => RecordStatus::Active->value,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('El plan actual no tiene habilitado el modulo de promociones.');

        app(CreatePromotion::class)->handle($company, [
            'name' => 'Promo bloqueada',
            'promotion_type' => PromotionType::ProductDiscount->value,
            'discount_type' => PromotionDiscountType::Percentage->value,
            'discount_value' => '10',
            'targets' => [[
                'target_type' => PromotionTargetType::Product->value,
                'target_id' => $product->id,
                'min_quantity' => '1',
            ]],
        ]);
    }

    public function test_it_rejects_combo_promotion_when_combo_feature_is_not_in_plan(): void
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Promos Pro SAS',
        ]);
        $this->assignCompanyPlan($company, 'pro');
        $unit = Unit::query()->create([
            'company_id' => $company->id,
            'code' => 'UND-PRO',
            'name' => 'Unidad',
            'precision_scale' => 0,
            'status' => RecordStatus::Active->value,
        ]);
        $category = Category::query()->create([
            'company_id' => $company->id,
            'name' => 'Abarrotes',
            'code' => 'ABA-PRO',
            'status' => RecordStatus::Active->value,
        ]);
        $productA = Product::query()->create([
            'company_id' => $company->id,
            'category_id' => $category->id,
            'base_unit_id' => $unit->id,
            'name' => 'Arroz pro',
            'cost' => 900,
            'price_1' => 1800,
            'tracks_inventory' => true,
            'minimum_stock' => 1,
            'status' => RecordStatus::Active->value,
        ]);
        $productB = Product::query()->create([
            'company_id' => $company->id,
            'category_id' => $category->id,
            'base_unit_id' => $unit->id,
            'name' => 'Frijol pro',
            'cost' => 800,
            'price_1' => 1700,
            'tracks_inventory' => true,
            'minimum_stock' => 1,
            'status' => RecordStatus::Active->value,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('El plan actual no tiene habilitada la feature de combos.');

        app(CreatePromotion::class)->handle($company, [
            'name' => 'Combo bloqueado',
            'promotion_type' => PromotionType::ComboPrice->value,
            'discount_type' => PromotionDiscountType::FixedPrice->value,
            'discount_value' => '4200',
            'combo_items' => [
                ['product_id' => $productA->id, 'required_quantity' => '1'],
                ['product_id' => $productB->id, 'required_quantity' => '1'],
            ],
        ]);
    }

    protected function fixture(): array
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Promos Retail SAS',
        ]);
        $this->assignCompanyPlan($company, 'premium');
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
        $productA = Product::query()->create([
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
        $productB = Product::query()->create([
            'company_id' => $company->id,
            'category_id' => $category->id,
            'base_unit_id' => $unit->id,
            'name' => 'Frijol',
            'cost' => 800,
            'price_1' => 1700,
            'tracks_inventory' => true,
            'minimum_stock' => 1,
            'status' => RecordStatus::Active->value,
        ]);

        app(CreateInventoryAdjustment::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'adjustment_type' => InventoryAdjustmentType::Increase->value,
            'reason' => 'Stock inicial',
            'items' => [
                [
                    'product_id' => $productA->id,
                    'quantity' => '20',
                    'unit_cost' => '900',
                ],
                [
                    'product_id' => $productB->id,
                    'quantity' => '20',
                    'unit_cost' => '800',
                ],
            ],
        ]);

        return [$owner, $company, $branch, $warehouse, $productA, $productB];
    }
}
