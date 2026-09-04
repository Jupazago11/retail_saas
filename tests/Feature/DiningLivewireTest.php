<?php

namespace Tests\Feature;

use App\Actions\Companies\CreateCompany;
use App\Enums\RecordStatus;
use App\Livewire\Dining\DiningTablesPage;
use App\Livewire\Dining\KitchenDisplayPage;
use App\Livewire\Dining\TableOrderPage;
use App\Models\BusinessType;
use App\Models\Category;
use App\Models\DiningTable;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use App\Services\Tenancy\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithCompanyPlans;
use Tests\TestCase;

class DiningLivewireTest extends TestCase
{
    use InteractsWithCompanyPlans, RefreshDatabase;

    public function test_full_dining_flow_through_livewire_screens(): void
    {
        [$owner, $company, $branch, $product] = $this->fixture();

        $this->actingAs($owner);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        Livewire::test(DiningTablesPage::class)
            ->set('branchId', $branch->id)
            ->set('capacity', '4')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('1');

        $table = DiningTable::query()->where('company_id', $company->id)->firstOrFail();
        $this->assertSame('free', $table->occupancy_status);

        Livewire::test(TableOrderPage::class, ['table' => $table->id])
            ->set('productId', (string) $product->id)
            ->set('quantity', '2')
            ->call('addDish')
            ->assertHasNoErrors()
            ->assertSee('Hamburguesa');

        $this->assertSame('occupied', $table->fresh()->occupancy_status);

        Livewire::test(KitchenDisplayPage::class)
            ->assertSee('Hamburguesa')
            ->assertSee('Pendiente');

        $item = $table->fresh()->openFrozenSale()->diningOrderItems()->firstOrFail();

        Livewire::test(KitchenDisplayPage::class)
            ->call('advance', $item->id, 'preparing');

        $this->assertSame('preparing', $item->fresh()->kitchen_status);

        Livewire::test(TableOrderPage::class, ['table' => $table->id])
            ->call('openCheckout')
            ->set('payments.0.0.amount', '36000')
            ->call('submitCheckout')
            ->assertRedirect();

        $this->assertSame('free', $table->fresh()->occupancy_status);
        $this->assertDatabaseHas('sales', ['company_id' => $company->id, 'status' => 'confirmed']);
        $this->assertDatabaseHas('payments', ['amount' => '36000.00', 'payment_method_code' => 'cash']);
    }

    public function test_a_dish_note_added_from_the_table_order_page_shows_up_in_the_kitchen_display(): void
    {
        [$owner, $company, $branch, $product] = $this->fixture();

        $this->actingAs($owner);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        Livewire::test(DiningTablesPage::class)
            ->set('branchId', $branch->id)
            ->set('capacity', '4')
            ->call('save');

        $table = DiningTable::query()->where('company_id', $company->id)->firstOrFail();

        Livewire::test(TableOrderPage::class, ['table' => $table->id])
            ->set('productId', (string) $product->id)
            ->set('quantity', '1')
            ->set('notes', 'sin cebolla')
            ->call('addDish')
            ->assertHasNoErrors()
            ->assertSee('sin cebolla');

        Livewire::test(KitchenDisplayPage::class)
            ->assertSee('Hamburguesa')
            ->assertSee('sin cebolla');
    }

    public function test_kitchen_display_shows_an_elapsed_time_badge_that_gets_more_urgent(): void
    {
        [$owner, $company, $branch, $product] = $this->fixture();

        $this->actingAs($owner);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        Livewire::test(DiningTablesPage::class)
            ->set('branchId', $branch->id)
            ->set('capacity', '4')
            ->call('save');

        $table = DiningTable::query()->where('company_id', $company->id)->firstOrFail();

        $start = now();

        Livewire::test(TableOrderPage::class, ['table' => $table->id])
            ->set('productId', (string) $product->id)
            ->set('quantity', '1')
            ->call('addDish');

        $item = $table->fresh()->openFrozenSale()->diningOrderItems()->firstOrFail();
        $kitchen = Livewire::test(KitchenDisplayPage::class)->instance();

        $this->assertSame(0, $kitchen->elapsedMinutes($item));
        $this->assertSame('<1m', $kitchen->elapsedLabel(0));
        $this->assertStringContainsString('bg-gray-100', $kitchen->urgencyClasses(0));

        $this->travelTo($start->copy()->addMinutes(8));
        $this->assertSame(8, $kitchen->elapsedMinutes($item));
        $this->assertSame('8m', $kitchen->elapsedLabel(8));
        $this->assertStringContainsString('bg-amber-100', $kitchen->urgencyClasses($kitchen->elapsedMinutes($item)));

        $this->travelTo($start->copy()->addMinutes(16));
        $this->assertSame(16, $kitchen->elapsedMinutes($item));
        $this->assertStringContainsString('bg-rose-100', $kitchen->urgencyClasses($kitchen->elapsedMinutes($item)));

        $this->travelBack();
    }

    protected function fixture(): array
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Restaurante Livewire SAS',
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

        return [$owner, $company, $branch, $product];
    }
}
