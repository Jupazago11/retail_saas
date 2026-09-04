<?php

namespace Tests\Feature;

use App\Actions\Companies\CreateCompany;
use App\Actions\Companies\ProvisionDefaultRestaurantRoles;
use App\Actions\Dining\AddDishToDiningOrder;
use App\Enums\RecordStatus;
use App\Livewire\Dining\WaiterOrdersPage;
use App\Models\BusinessType;
use App\Models\Category;
use App\Models\CompanyRole;
use App\Models\DiningFloorPlan;
use App\Models\DiningOrderItem;
use App\Models\DiningTable;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use App\Services\Tenancy\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithCompanyPlans;
use Tests\TestCase;

class WaiterOrdersPageTest extends TestCase
{
    use InteractsWithCompanyPlans, RefreshDatabase;

    public function test_waiter_can_take_an_order_through_the_simple_view(): void
    {
        [, $company, $table, $product, $waiter] = $this->fixture();

        $this->actingAs($waiter);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        Livewire::test(WaiterOrdersPage::class)
            ->call('selectTable', $table->id)
            ->set('productId', (string) $product->id)
            ->set('quantity', '2')
            ->call('addDish')
            ->assertHasNoErrors()
            ->assertSee('Hamburguesa');

        $this->assertSame(1, DiningOrderItem::query()->count());
        $this->assertSame('occupied', $table->fresh()->occupancy_status);
    }

    public function test_selecting_a_table_shows_its_order_and_supports_editing_and_removing_items(): void
    {
        [$owner, $company, $table, $product, $waiter] = $this->fixture();

        app(AddDishToDiningOrder::class)->handle($company, $table, [
            'product_id' => $product->id,
            'quantity' => '1',
            'unit_price' => '18000',
        ], $owner);
        app(AddDishToDiningOrder::class)->handle($company, $table, [
            'product_id' => $product->id,
            'quantity' => '1',
            'unit_price' => '18000',
        ], $owner);

        $items = DiningOrderItem::query()->orderBy('id')->get();
        $item = $items->first();

        $this->actingAs($waiter);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        Livewire::test(WaiterOrdersPage::class)
            ->call('selectTable', $table->id)
            ->assertSee('Hamburguesa')
            ->call('updateItemQuantity', $item->id, '3')
            ->assertHasNoErrors();

        $this->assertSame('3.000000', $item->fresh()->quantity);
        $this->assertTrue($item->fresh()->is_modified);

        Livewire::test(WaiterOrdersPage::class)
            ->call('selectTable', $table->id)
            ->call('removeItem', $item->id);

        $this->assertSame(1, DiningOrderItem::query()->count());
    }

    public function test_a_user_without_dining_permission_is_forbidden(): void
    {
        [, $company, , , ] = $this->fixture();

        $outsider = User::factory()->create();
        $company->users()->attach($outsider->id, [
            'company_role' => 'custom',
            'company_role_id' => CompanyRole::query()->create([
                'company_id' => $company->id,
                'code' => 'sin_permisos',
                'display_name' => 'Sin permisos',
                'status' => RecordStatus::Active->value,
            ])->id,
            'status' => RecordStatus::Active->value,
            'joined_at' => now(),
        ]);

        $this->actingAs($outsider);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        Livewire::test(WaiterOrdersPage::class)->assertForbidden();
    }

    public function test_map_view_receives_the_floor_plan_and_table_positions(): void
    {
        [, $company, $table, , $waiter] = $this->fixture();

        DiningFloorPlan::query()->create([
            'company_id' => $company->id,
            'branch_id' => $table->branch_id,
            'outline_points' => [
                ['x' => 10, 'y' => 10],
                ['x' => 90, 'y' => 10],
                ['x' => 90, 'y' => 90],
            ],
        ]);
        $table->update(['pos_x' => 25, 'pos_y' => 40, 'shape' => 'round', 'size' => 10]);

        $this->actingAs($waiter);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        Livewire::test(WaiterOrdersPage::class)
            ->assertSet('branchId', $table->branch_id)
            ->assertViewHas('floorPlan', fn ($floorPlan) => $floorPlan !== null && count($floorPlan->outline_points) === 3)
            ->assertViewHas('tables', fn ($tables) => $tables->firstWhere('id', $table->id)?->shape === 'round');
    }

    protected function fixture(): array
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Pedidos Mesero SAS',
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

        app(ProvisionDefaultRestaurantRoles::class)->handle($company);
        $meseroRole = CompanyRole::query()->where('company_id', $company->id)->where('code', 'MESERO')->firstOrFail();

        $waiter = User::factory()->create();
        $company->users()->attach($waiter->id, [
            'company_role' => 'custom',
            'company_role_id' => $meseroRole->id,
            'status' => RecordStatus::Active->value,
            'joined_at' => now(),
        ]);

        return [$owner, $company, $table, $product, $waiter];
    }
}
