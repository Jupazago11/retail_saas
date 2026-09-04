<?php

namespace Tests\Feature;

use App\Actions\Companies\CreateCompany;
use App\Actions\Inventory\CreateInventoryAdjustment;
use App\Actions\Sales\CreateSale;
use App\Actions\Sales\ReturnSale;
use App\Enums\InventoryAdjustmentType;
use App\Enums\RecordStatus;
use App\Enums\SaleStatus;
use App\Models\Category;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Recipe;
use App\Models\Unit;
use App\Models\User;
use App\Services\Inventory\RecipeCostCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class RecipeInventoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_recipe_cost_calculator_sums_ingredient_costs_over_yield(): void
    {
        [, $company, , , $dish, $harina, $queso] = $this->recipeFixture();

        $recipe = Recipe::query()->create([
            'company_id' => $company->id,
            'product_id' => $dish->id,
            'yield_quantity' => '2',
        ]);

        $recipe->items()->create([
            'ingredient_product_id' => $harina->id,
            'quantity' => '400',
        ]);
        $recipe->items()->create([
            'ingredient_product_id' => $queso->id,
            'quantity' => '200',
        ]);

        // (400 * 20) + (200 * 30) = 8000 + 6000 = 14000, entre 2 porciones = 7000
        $cost = app(RecipeCostCalculator::class)->recalculate($recipe);

        $this->assertSame('7000.0000', $cost);
        $this->assertSame('7000.00', $dish->fresh()->cost);
    }

    public function test_selling_a_recipe_product_discounts_ingredients_not_the_dish(): void
    {
        [$owner, $company, $branch, $warehouse, $dish, $harina, $queso] = $this->recipeFixture();

        app(CreateInventoryAdjustment::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'adjustment_type' => InventoryAdjustmentType::Increase->value,
            'reason' => 'Stock inicial de insumos',
            'items' => [
                ['product_id' => $harina->id, 'quantity' => '5000', 'unit_cost' => '20'],
                ['product_id' => $queso->id, 'quantity' => '3000', 'unit_cost' => '30'],
            ],
        ]);

        $recipe = Recipe::query()->create([
            'company_id' => $company->id,
            'product_id' => $dish->id,
            'yield_quantity' => '1',
        ]);
        $recipe->items()->create(['ingredient_product_id' => $harina->id, 'quantity' => '200']);
        $recipe->items()->create(['ingredient_product_id' => $queso->id, 'quantity' => '100']);

        $sale = app(CreateSale::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'user_id' => $owner->id,
            'status' => SaleStatus::Confirmed->value,
            'items' => [
                ['product_id' => $dish->id, 'quantity' => '3', 'unit_price' => '25000'],
            ],
        ]);

        $this->assertNotNull($sale->posted_to_inventory_at);

        // 3 pizzas x 200g harina = 600g; x 100g queso = 300g.
        $this->assertDatabaseHas('inventory_movements', [
            'company_id' => $company->id,
            'product_id' => $harina->id,
            'movement_type' => 'sale_out',
            'quantity_out' => '600.000000',
            'balance_quantity' => '4400.000000',
        ]);
        $this->assertDatabaseHas('inventory_movements', [
            'company_id' => $company->id,
            'product_id' => $queso->id,
            'movement_type' => 'sale_out',
            'quantity_out' => '300.000000',
            'balance_quantity' => '2700.000000',
        ]);

        $this->assertSame(
            0,
            InventoryMovement::query()->where('product_id', $dish->id)->count(),
            'El plato no debe generar movimiento de kardex propio.'
        );

        // Costo realizado del plato: (200*20 + 100*30) = 7000 por unidad.
        $this->assertSame('7000.0000', $sale->items->first()->cost_snapshot);
    }

    public function test_recipe_sale_fails_when_ingredient_stock_is_insufficient(): void
    {
        [$owner, $company, $branch, $warehouse, $dish, $harina, $queso] = $this->recipeFixture();

        $recipe = Recipe::query()->create([
            'company_id' => $company->id,
            'product_id' => $dish->id,
            'yield_quantity' => '1',
        ]);
        $recipe->items()->create(['ingredient_product_id' => $harina->id, 'quantity' => '200']);
        $recipe->items()->create(['ingredient_product_id' => $queso->id, 'quantity' => '100']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('La venta no tiene stock suficiente para completar la salida.');

        app(CreateSale::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'user_id' => $owner->id,
            'status' => SaleStatus::Confirmed->value,
            'items' => [
                ['product_id' => $dish->id, 'quantity' => '1', 'unit_price' => '25000'],
            ],
        ]);
    }

    public function test_returning_a_recipe_sale_restores_ingredient_stock(): void
    {
        [$owner, $company, $branch, $warehouse, $dish, $harina, $queso] = $this->recipeFixture();

        app(CreateInventoryAdjustment::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'adjustment_type' => InventoryAdjustmentType::Increase->value,
            'reason' => 'Stock inicial de insumos',
            'items' => [
                ['product_id' => $harina->id, 'quantity' => '5000', 'unit_cost' => '20'],
                ['product_id' => $queso->id, 'quantity' => '3000', 'unit_cost' => '30'],
            ],
        ]);

        $recipe = Recipe::query()->create([
            'company_id' => $company->id,
            'product_id' => $dish->id,
            'yield_quantity' => '1',
        ]);
        $recipe->items()->create(['ingredient_product_id' => $harina->id, 'quantity' => '200']);
        $recipe->items()->create(['ingredient_product_id' => $queso->id, 'quantity' => '100']);

        $sale = app(CreateSale::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'user_id' => $owner->id,
            'status' => SaleStatus::Confirmed->value,
            'items' => [
                ['product_id' => $dish->id, 'quantity' => '2', 'unit_price' => '25000'],
            ],
        ]);

        app(ReturnSale::class)->handle($company, $sale, [
            ['sale_item_id' => $sale->items->first()->id, 'quantity' => '1'],
        ], 'Cliente no lo quiso');

        // Stock inicial 5000/3000, se vendieron 2 (400g harina, 200g queso),
        // se devuelve 1 (200g harina, 100g queso) -> queda 4800/2900.
        $this->assertDatabaseHas('inventory_balances', [
            'company_id' => $company->id,
            'product_id' => $harina->id,
            'quantity_on_hand' => '4800.000000',
        ]);
        $this->assertDatabaseHas('inventory_balances', [
            'company_id' => $company->id,
            'product_id' => $queso->id,
            'quantity_on_hand' => '2900.000000',
        ]);
        $this->assertDatabaseHas('inventory_movements', [
            'company_id' => $company->id,
            'product_id' => $harina->id,
            'movement_type' => 'sale_return_in',
            'quantity_in' => '200.000000',
        ]);
    }

    protected function recipeFixture(): array
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Cocina Recetas SAS',
        ]);
        $branch = $company->branches()->firstOrFail();
        $warehouse = $company->warehouses()->firstOrFail();

        $unit = Unit::query()->create([
            'company_id' => $company->id,
            'code' => 'GR',
            'name' => 'Gramo',
            'precision_scale' => 0,
            'status' => RecordStatus::Active->value,
        ]);
        $category = Category::query()->create([
            'company_id' => $company->id,
            'name' => 'Cocina',
            'code' => 'COC',
            'status' => RecordStatus::Active->value,
        ]);

        $harina = Product::query()->create([
            'company_id' => $company->id,
            'category_id' => $category->id,
            'base_unit_id' => $unit->id,
            'name' => 'Harina',
            'cost' => 20,
            'price_1' => 0,
            'tracks_inventory' => true,
            'minimum_stock' => 0,
            'status' => RecordStatus::Active->value,
        ]);

        $queso = Product::query()->create([
            'company_id' => $company->id,
            'category_id' => $category->id,
            'base_unit_id' => $unit->id,
            'name' => 'Queso',
            'cost' => 30,
            'price_1' => 0,
            'tracks_inventory' => true,
            'minimum_stock' => 0,
            'status' => RecordStatus::Active->value,
        ]);

        $dish = Product::query()->create([
            'company_id' => $company->id,
            'category_id' => $category->id,
            'base_unit_id' => $unit->id,
            'name' => 'Pizza Margarita',
            'cost' => 0,
            'price_1' => 25000,
            'tracks_inventory' => false,
            'is_recipe' => true,
            'minimum_stock' => 0,
            'status' => RecordStatus::Active->value,
        ]);

        return [$owner, $company, $branch, $warehouse, $dish, $harina, $queso];
    }
}
