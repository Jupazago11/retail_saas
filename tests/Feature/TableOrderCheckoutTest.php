<?php

namespace Tests\Feature;

use App\Actions\Companies\CreateCompany;
use App\Actions\Dining\AddDishToDiningOrder;
use App\Enums\RecordStatus;
use App\Livewire\Dining\TableOrderPage;
use App\Models\BusinessType;
use App\Models\Category;
use App\Models\DiningOrderItem;
use App\Models\DiningTable;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Unit;
use App\Models\User;
use App\Services\Tenancy\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithCompanyPlans;
use Tests\TestCase;

class TableOrderCheckoutTest extends TestCase
{
    use InteractsWithCompanyPlans, RefreshDatabase;

    public function test_a_single_payer_can_pay_with_mixed_payment_methods(): void
    {
        [$owner, $company, $table, $burger, $soda] = $this->fixture();

        app(AddDishToDiningOrder::class)->handle($company, $table, [
            'product_id' => $burger->id, 'quantity' => '1', 'unit_price' => '18000',
        ], $owner);
        app(AddDishToDiningOrder::class)->handle($company, $table, [
            'product_id' => $soda->id, 'quantity' => '2', 'unit_price' => '6000',
        ], $owner);

        $this->actingAs($owner);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        Livewire::test(TableOrderPage::class, ['table' => $table->id])
            ->call('openCheckout')
            ->set('payments.0.0.payment_method_code', 'card')
            ->set('payments.0.0.amount', '20000')
            ->call('addPaymentRow', 0)
            ->set('payments.0.1.payment_method_code', 'cash')
            ->set('payments.0.1.amount', '10000')
            ->call('submitCheckout')
            ->assertRedirect();

        $this->assertSame('free', $table->fresh()->occupancy_status);
        $this->assertDatabaseHas('payments', ['payment_method_code' => 'card', 'amount' => '20000.00']);
        $this->assertDatabaseHas('payments', ['payment_method_code' => 'cash', 'amount' => '10000.00']);
    }

    public function test_splitting_the_bill_between_two_payers_creates_two_confirmed_sales(): void
    {
        [$owner, $company, $table, $burger, $soda] = $this->fixture();

        app(AddDishToDiningOrder::class)->handle($company, $table, [
            'product_id' => $burger->id, 'quantity' => '1', 'unit_price' => '18000',
        ], $owner);
        app(AddDishToDiningOrder::class)->handle($company, $table, [
            'product_id' => $soda->id, 'quantity' => '1', 'unit_price' => '6000',
        ], $owner);

        $items = DiningOrderItem::query()->orderBy('id')->get();

        $this->actingAs($owner);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        Livewire::test(TableOrderPage::class, ['table' => $table->id])
            ->call('openCheckout')
            ->call('toggleSplitBill')
            ->call('addPayer')
            ->call('assignItemPayer', $items[0]->id, 0)
            ->call('assignItemPayer', $items[1]->id, 1)
            ->set('payerLabels.0', 'Ana')
            ->set('payerLabels.1', 'Luis')
            ->set('payments.0.0.amount', '18000')
            ->set('payments.1.0.amount', '6000')
            ->call('submitCheckout')
            ->assertSet('completedSaleIds', fn ($ids) => count($ids) === 2);

        $this->assertSame('free', $table->fresh()->occupancy_status);
        $this->assertSame(2, Sale::query()->where('company_id', $company->id)->count());
        $this->assertDatabaseHas('sales', ['payer_label' => 'Ana', 'grand_total' => '18000.00']);
        $this->assertDatabaseHas('sales', ['payer_label' => 'Luis', 'grand_total' => '6000.00']);
    }

    protected function fixture(): array
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Cobro Mesa SAS',
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
