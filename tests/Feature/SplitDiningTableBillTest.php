<?php

namespace Tests\Feature;

use App\Actions\Companies\CreateCompany;
use App\Actions\Dining\AddDishToDiningOrder;
use App\Actions\Dining\SplitDiningTableBill;
use App\Enums\RecordStatus;
use App\Models\BusinessType;
use App\Models\Category;
use App\Models\DiningOrderItem;
use App\Models\DiningTable;
use App\Models\FrozenSale;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\Concerns\InteractsWithCompanyPlans;
use Tests\TestCase;

class SplitDiningTableBillTest extends TestCase
{
    use InteractsWithCompanyPlans, RefreshDatabase;

    public function test_a_single_group_covering_everything_charges_one_sale_and_frees_the_table(): void
    {
        [$owner, $company, $table, $burger, $soda] = $this->fixture();

        app(AddDishToDiningOrder::class)->handle($company, $table, [
            'product_id' => $burger->id, 'quantity' => '1', 'unit_price' => '18000',
        ], $owner);
        app(AddDishToDiningOrder::class)->handle($company, $table, [
            'product_id' => $soda->id, 'quantity' => '2', 'unit_price' => '6000',
        ], $owner);

        $items = DiningOrderItem::query()->orderBy('id')->pluck('id')->all();

        $sales = app(SplitDiningTableBill::class)->handle($company, $table, [
            [
                'label' => null,
                'item_ids' => $items,
                'payments' => [['payment_method_code' => 'cash', 'amount' => '30000', 'reference' => null]],
            ],
        ], $owner);

        $this->assertCount(1, $sales);
        $this->assertSame('confirmed', $sales[0]->status);
        $this->assertSame('30000.00', (string) $sales[0]->grand_total);
        $this->assertSame('free', $table->fresh()->occupancy_status);
        $this->assertSame('converted', FrozenSale::query()->firstOrFail()->status);
        $this->assertSame($sales[0]->id, FrozenSale::query()->firstOrFail()->converted_sale_id);
    }

    public function test_splitting_by_items_creates_one_sale_per_payer_each_linked_to_the_frozen_sale(): void
    {
        [$owner, $company, $table, $burger, $soda] = $this->fixture();

        app(AddDishToDiningOrder::class)->handle($company, $table, [
            'product_id' => $burger->id, 'quantity' => '1', 'unit_price' => '18000',
        ], $owner);
        app(AddDishToDiningOrder::class)->handle($company, $table, [
            'product_id' => $soda->id, 'quantity' => '1', 'unit_price' => '6000',
        ], $owner);

        $items = DiningOrderItem::query()->orderBy('id')->get();
        $frozenSaleId = $items->first()->frozen_sale_id;

        $sales = app(SplitDiningTableBill::class)->handle($company, $table, [
            [
                'label' => 'Persona 1',
                'item_ids' => [$items[0]->id],
                'payments' => [['payment_method_code' => 'card', 'amount' => '18000', 'reference' => 'AUTH1']],
            ],
            [
                'label' => 'Persona 2',
                'item_ids' => [$items[1]->id],
                'payments' => [['payment_method_code' => 'cash', 'amount' => '6000', 'reference' => null]],
            ],
        ], $owner);

        $this->assertCount(2, $sales);
        $this->assertSame('Persona 1', $sales[0]->payer_label);
        $this->assertSame($frozenSaleId, $sales[0]->frozen_sale_id);
        $this->assertSame('18000.00', (string) $sales[0]->grand_total);
        $this->assertSame('Persona 2', $sales[1]->payer_label);
        $this->assertSame($frozenSaleId, $sales[1]->frozen_sale_id);
        $this->assertSame('6000.00', (string) $sales[1]->grand_total);

        $frozenSale = FrozenSale::query()->firstOrFail();
        $this->assertSame('converted', $frozenSale->status);
        $this->assertNull($frozenSale->converted_sale_id, 'Con mas de una venta no hay un unico converted_sale_id.');
        $this->assertSame('free', $table->fresh()->occupancy_status);
    }

    public function test_it_rejects_a_partition_that_leaves_an_item_unassigned(): void
    {
        [$owner, $company, $table, $burger, $soda] = $this->fixture();

        app(AddDishToDiningOrder::class)->handle($company, $table, [
            'product_id' => $burger->id, 'quantity' => '1', 'unit_price' => '18000',
        ], $owner);
        app(AddDishToDiningOrder::class)->handle($company, $table, [
            'product_id' => $soda->id, 'quantity' => '1', 'unit_price' => '6000',
        ], $owner);

        $items = DiningOrderItem::query()->orderBy('id')->pluck('id')->all();

        $this->expectException(InvalidArgumentException::class);
        app(SplitDiningTableBill::class)->handle($company, $table, [
            [
                'label' => null,
                'item_ids' => [$items[0]],
                'payments' => [['payment_method_code' => 'cash', 'amount' => '18000', 'reference' => null]],
            ],
        ], $owner);
    }

    public function test_it_rejects_a_group_whose_payments_do_not_cover_its_subtotal(): void
    {
        [$owner, $company, $table, $burger] = $this->fixture();

        app(AddDishToDiningOrder::class)->handle($company, $table, [
            'product_id' => $burger->id, 'quantity' => '1', 'unit_price' => '18000',
        ], $owner);

        $items = DiningOrderItem::query()->pluck('id')->all();

        $this->expectException(InvalidArgumentException::class);
        app(SplitDiningTableBill::class)->handle($company, $table, [
            [
                'label' => null,
                'item_ids' => $items,
                'payments' => [['payment_method_code' => 'cash', 'amount' => '10000', 'reference' => null]],
            ],
        ], $owner);
    }

    public function test_closing_a_table_without_an_open_order_fails(): void
    {
        [$owner, $company, $table] = $this->fixture();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Esta mesa no tiene una comanda abierta para cobrar.');

        app(SplitDiningTableBill::class)->handle($company, $table, [
            ['label' => null, 'item_ids' => [999], 'payments' => [['payment_method_code' => 'cash', 'amount' => '1', 'reference' => null]]],
        ], $owner);
    }

    protected function fixture(): array
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Division de Cuenta SAS',
        ]);
        $company->update(['business_type_id' => BusinessType::where('code', 'restaurant')->value('id')]);
        $this->assignCompanyPlan($company, 'basic');

        $branch = $company->branches()->firstOrFail();

        $unit = Unit::query()->create([
            'company_id' => $company->id, 'code' => 'UND', 'name' => 'Unidad',
            'precision_scale' => 0, 'status' => RecordStatus::Active->value,
        ]);
        $category = Category::query()->create([
            'company_id' => $company->id, 'name' => 'Platos', 'code' => 'PLA',
            'status' => RecordStatus::Active->value,
        ]);
        $burger = Product::query()->create([
            'company_id' => $company->id, 'category_id' => $category->id, 'base_unit_id' => $unit->id,
            'name' => 'Hamburguesa', 'cost' => 8000, 'price_1' => 18000,
            'tracks_inventory' => false, 'minimum_stock' => 0, 'status' => RecordStatus::Active->value,
        ]);
        $soda = Product::query()->create([
            'company_id' => $company->id, 'category_id' => $category->id, 'base_unit_id' => $unit->id,
            'name' => 'Gaseosa', 'cost' => 2000, 'price_1' => 6000,
            'tracks_inventory' => false, 'minimum_stock' => 0, 'status' => RecordStatus::Active->value,
        ]);

        $table = DiningTable::query()->create([
            'company_id' => $company->id, 'branch_id' => $branch->id, 'name' => 'Mesa 1',
            'capacity' => 4, 'status' => RecordStatus::Active->value, 'occupancy_status' => 'free',
        ]);

        return [$owner, $company, $table, $burger, $soda];
    }
}
