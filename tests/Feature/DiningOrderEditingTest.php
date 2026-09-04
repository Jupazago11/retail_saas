<?php

namespace Tests\Feature;

use App\Actions\Companies\CreateCompany;
use App\Actions\Dining\AddDishToDiningOrder;
use App\Actions\Dining\AdvanceDiningOrderItemStatus;
use App\Actions\Dining\RemoveDiningOrderItem;
use App\Actions\Dining\UpdateDiningOrderItemQuantity;
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

class DiningOrderEditingTest extends TestCase
{
    use InteractsWithCompanyPlans, RefreshDatabase;

    public function test_updating_quantity_recalculates_payload_and_flags_as_modified(): void
    {
        [$waiter, $company, $table, $product] = $this->fixture();

        $frozenSale = app(AddDishToDiningOrder::class)->handle($company, $table, [
            'product_id' => $product->id,
            'quantity' => '2',
            'unit_price' => '10000',
        ], $waiter);

        $item = DiningOrderItem::query()->firstOrFail();
        $this->assertFalse($item->is_modified);

        $editor = User::factory()->create();
        $updated = app(UpdateDiningOrderItemQuantity::class)->handle($item, '5', $editor);

        $this->assertSame('5.000000', $updated->quantity);
        $this->assertTrue($updated->is_modified);
        $this->assertSame($editor->id, $updated->modified_by);

        $payloadLine = collect($frozenSale->fresh()->payload_snapshot['items'])->firstWhere('dining_order_item_id', $item->id);
        $this->assertSame('5.000000', $payloadLine['quantity']);
        $this->assertSame('50000.00', $frozenSale->fresh()->payload_snapshot['totals']['subtotal']);
    }

    public function test_removing_a_pending_item_deletes_it_without_a_trace(): void
    {
        [$waiter, $company, $table, $product] = $this->fixture();

        app(AddDishToDiningOrder::class)->handle($company, $table, [
            'product_id' => $product->id,
            'quantity' => '1',
            'unit_price' => '10000',
        ], $waiter);
        $frozenSale = app(AddDishToDiningOrder::class)->handle($company, $table, [
            'product_id' => $product->id,
            'quantity' => '2',
            'unit_price' => '10000',
        ], $waiter);

        $items = DiningOrderItem::query()->orderBy('id')->get();
        $this->assertCount(2, $items);

        app(RemoveDiningOrderItem::class)->handle($items->first(), $waiter);

        $this->assertSame(1, DiningOrderItem::query()->count());
        $this->assertCount(1, $frozenSale->fresh()->payload_snapshot['items']);
    }

    public function test_removing_an_item_already_in_progress_marks_it_cancelled_instead_of_deleting(): void
    {
        [$waiter, $company, $table, $product] = $this->fixture();

        app(AddDishToDiningOrder::class)->handle($company, $table, [
            'product_id' => $product->id,
            'quantity' => '1',
            'unit_price' => '10000',
        ], $waiter);
        app(AddDishToDiningOrder::class)->handle($company, $table, [
            'product_id' => $product->id,
            'quantity' => '2',
            'unit_price' => '10000',
        ], $waiter);

        $items = DiningOrderItem::query()->orderBy('id')->get();
        $inProgress = app(AdvanceDiningOrderItemStatus::class)->handle($items->first(), 'preparing');

        app(RemoveDiningOrderItem::class)->handle($inProgress, $waiter);

        $inProgress->refresh();
        $this->assertSame('cancelled', $inProgress->kitchen_status);
        $this->assertTrue($inProgress->is_modified);
        $this->assertSame(2, DiningOrderItem::query()->count());
    }

    public function test_removing_the_last_item_is_rejected(): void
    {
        [$waiter, $company, $table, $product] = $this->fixture();

        app(AddDishToDiningOrder::class)->handle($company, $table, [
            'product_id' => $product->id,
            'quantity' => '1',
            'unit_price' => '10000',
        ], $waiter);

        $item = DiningOrderItem::query()->firstOrFail();

        $this->expectException(InvalidArgumentException::class);
        app(RemoveDiningOrderItem::class)->handle($item, $waiter);
    }

    protected function fixture(): array
    {
        $waiter = User::factory()->create();
        $company = app(CreateCompany::class)->handle($waiter, [
            'legal_name' => 'Edicion Comandas SAS',
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

        return [$waiter, $company, $table, $product];
    }
}
