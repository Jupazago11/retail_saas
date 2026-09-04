<?php

namespace Tests\Feature;

use App\Actions\Companies\CreateCompany;
use App\Actions\Dining\AddDishToDiningOrder;
use App\Actions\Dining\AdvanceDiningOrderItemStatus;
use App\Actions\Dining\CloseDiningTable;
use App\Enums\RecordStatus;
use App\Models\BusinessType;
use App\Models\Category;
use App\Models\DiningOrderItem;
use App\Models\DiningTable;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\Concerns\InteractsWithCompanyPlans;
use Tests\TestCase;

class DiningTablesFlowTest extends TestCase
{
    use InteractsWithCompanyPlans, RefreshDatabase;

    public function test_adding_first_dish_opens_the_table_and_creates_a_frozen_sale(): void
    {
        [$owner, $company, $table, $product] = $this->diningFixture();

        $frozenSale = app(AddDishToDiningOrder::class)->handle($company, $table, [
            'product_id' => $product->id,
            'quantity' => '2',
            'unit_price' => '18000',
        ], $owner);

        $this->assertSame('occupied', $table->fresh()->occupancy_status);
        $this->assertSame($table->id, $frozenSale->dining_table_id);
        $this->assertSame('open', $frozenSale->status);
        $this->assertCount(1, $frozenSale->payload_snapshot['items']);
        $this->assertSame(1, DiningOrderItem::query()->where('frozen_sale_id', $frozenSale->id)->count());
    }

    public function test_a_dish_can_carry_a_free_text_note_for_the_kitchen(): void
    {
        [$owner, $company, $table, $product] = $this->diningFixture();

        app(AddDishToDiningOrder::class)->handle($company, $table, [
            'product_id' => $product->id,
            'quantity' => '1',
            'unit_price' => '18000',
            'notes' => '  sin cebolla  ',
        ], $owner);

        $item = DiningOrderItem::query()->where('frozen_sale_id', $table->fresh()->openFrozenSale()->id)->firstOrFail();
        $this->assertSame('sin cebolla', $item->notes);
    }

    public function test_a_dish_without_a_note_stores_null_instead_of_an_empty_string(): void
    {
        [$owner, $company, $table, $product] = $this->diningFixture();

        app(AddDishToDiningOrder::class)->handle($company, $table, [
            'product_id' => $product->id,
            'quantity' => '1',
            'unit_price' => '18000',
            'notes' => '   ',
        ], $owner);

        $item = DiningOrderItem::query()->where('frozen_sale_id', $table->fresh()->openFrozenSale()->id)->firstOrFail();
        $this->assertNull($item->notes);
    }

    public function test_adding_a_second_dish_appends_to_the_same_open_order(): void
    {
        [$owner, $company, $table, $product] = $this->diningFixture();
        $secondProduct = Product::query()->create([
            'company_id' => $company->id,
            'category_id' => $product->category_id,
            'base_unit_id' => $product->base_unit_id,
            'name' => 'Limonada',
            'cost' => 1000,
            'price_1' => 6000,
            'tracks_inventory' => false,
            'minimum_stock' => 0,
            'status' => RecordStatus::Active->value,
        ]);

        $first = app(AddDishToDiningOrder::class)->handle($company, $table, [
            'product_id' => $product->id,
            'quantity' => '1',
            'unit_price' => '18000',
        ], $owner);

        $second = app(AddDishToDiningOrder::class)->handle($company, $table, [
            'product_id' => $secondProduct->id,
            'quantity' => '2',
            'unit_price' => '6000',
        ], $owner);

        $this->assertSame($first->id, $second->id, 'El segundo plato debe caer sobre la misma comanda abierta.');
        $this->assertCount(2, $second->payload_snapshot['items']);
        $this->assertSame(2, DiningOrderItem::query()->where('frozen_sale_id', $second->id)->count());
        $this->assertSame('30000.00', $second->payload_snapshot['totals']['grand_total']);
    }

    public function test_closing_a_table_converts_the_order_to_a_sale_and_frees_the_table(): void
    {
        [$owner, $company, $table, $product] = $this->diningFixture();

        app(AddDishToDiningOrder::class)->handle($company, $table, [
            'product_id' => $product->id,
            'quantity' => '1',
            'unit_price' => '18000',
        ], $owner);

        $sale = app(CloseDiningTable::class)->handle($company, $table->fresh());

        $this->assertSame('confirmed', $sale->status);
        $this->assertSame('free', $table->fresh()->occupancy_status);
        $this->assertNull($table->fresh()->openFrozenSale());
    }

    public function test_closing_a_table_without_an_open_order_fails(): void
    {
        [, $company, $table] = $this->diningFixture();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Esta mesa no tiene una comanda abierta para cobrar.');

        app(CloseDiningTable::class)->handle($company, $table);
    }

    public function test_kitchen_item_status_advances_in_order_with_an_optional_hold(): void
    {
        [$owner, $company, $table, $product] = $this->diningFixture();

        app(AddDishToDiningOrder::class)->handle($company, $table, [
            'product_id' => $product->id,
            'quantity' => '1',
            'unit_price' => '18000',
        ], $owner);

        $item = DiningOrderItem::query()->firstOrFail();
        $this->assertSame('pending', $item->kitchen_status);

        $item = app(AdvanceDiningOrderItemStatus::class)->handle($item, 'preparing');
        $this->assertSame('preparing', $item->kitchen_status);

        // Cocina pausa el plato (falta un insumo, esperar otro de la misma
        // mesa, etc.) y despues lo reanuda.
        $item = app(AdvanceDiningOrderItemStatus::class)->handle($item, 'on_hold');
        $this->assertSame('on_hold', $item->kitchen_status);

        $item = app(AdvanceDiningOrderItemStatus::class)->handle($item, 'preparing');
        $this->assertSame('preparing', $item->kitchen_status);

        $item = app(AdvanceDiningOrderItemStatus::class)->handle($item, 'ready');
        $this->assertSame('ready', $item->kitchen_status);

        $item = app(AdvanceDiningOrderItemStatus::class)->handle($item, 'served');
        $this->assertSame('served', $item->kitchen_status);

        $this->expectException(InvalidArgumentException::class);
        app(AdvanceDiningOrderItemStatus::class)->handle($item, 'preparing');
    }

    public function test_kitchen_item_status_rejects_skipping_a_step(): void
    {
        [$owner, $company, $table, $product] = $this->diningFixture();

        app(AddDishToDiningOrder::class)->handle($company, $table, [
            'product_id' => $product->id,
            'quantity' => '1',
            'unit_price' => '18000',
        ], $owner);

        $item = DiningOrderItem::query()->firstOrFail();

        $this->expectException(InvalidArgumentException::class);
        app(AdvanceDiningOrderItemStatus::class)->handle($item, 'ready');
    }

    protected function diningFixture(): array
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Restaurante Comandas SAS',
        ]);
        $company->update(['business_type_id' => BusinessType::where('code', 'restaurant')->value('id')]);
        $this->assignCompanyPlan($company, 'basic');

        $branch = $company->branches()->firstOrFail();

        $unit = Unit::query()->create([
            'company_id' => $company->id,
            'code' => 'UND',
            'name' => 'Unidad',
            'precision_scale' => 0,
            'status' => RecordStatus::Active->value,
        ]);
        $category = Category::query()->create([
            'company_id' => $company->id,
            'name' => 'Platos',
            'code' => 'PLA',
            'status' => RecordStatus::Active->value,
        ]);
        $product = Product::query()->create([
            'company_id' => $company->id,
            'category_id' => $category->id,
            'base_unit_id' => $unit->id,
            'name' => 'Hamburguesa',
            'cost' => 8000,
            'price_1' => 18000,
            'tracks_inventory' => false,
            'minimum_stock' => 0,
            'status' => RecordStatus::Active->value,
        ]);

        $table = DiningTable::query()->create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'name' => 'Mesa 1',
            'capacity' => 4,
            'status' => RecordStatus::Active->value,
            'occupancy_status' => 'free',
        ]);

        return [$owner, $company, $table, $product];
    }
}
