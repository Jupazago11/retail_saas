<?php

namespace Tests\Feature;

use App\Actions\Companies\CreateCompany;
use App\Actions\Dining\AddDishToDiningOrder;
use App\Enums\RecordStatus;
use App\Models\BusinessType;
use App\Models\Category;
use App\Models\DiningTable;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use App\Services\Tenancy\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithCompanyPlans;
use Tests\TestCase;

class DiningOrderPreviewTicketTest extends TestCase
{
    use InteractsWithCompanyPlans, RefreshDatabase;

    public function test_it_renders_an_unpaid_preview_with_the_current_order(): void
    {
        [$owner, $company, $table, $product] = $this->fixture();

        app(AddDishToDiningOrder::class)->handle($company, $table, [
            'product_id' => $product->id, 'quantity' => '2', 'unit_price' => '18000',
        ], $owner);

        $this->actingAs($owner);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        $response = $this->get(route('dining.tables.preview-ticket', $table));

        $response->assertOk();
        $response->assertSee('Cuenta sin pagar');
        $response->assertSee('Hamburguesa');
        $response->assertSee('Mesa: '.$table->name, false);
    }

    public function test_it_is_not_found_when_the_table_has_no_open_order(): void
    {
        [$owner, $company, $table] = $this->fixture();

        $this->actingAs($owner);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        $this->get(route('dining.tables.preview-ticket', $table))->assertNotFound();
    }

    public function test_it_is_forbidden_without_dining_permission(): void
    {
        [, $company, $table] = $this->fixture();

        $outsider = User::factory()->create();
        $company->users()->attach($outsider->id, [
            'company_role' => 'custom',
            'company_role_id' => \App\Models\CompanyRole::query()->create([
                'company_id' => $company->id, 'code' => 'sin_permisos', 'display_name' => 'Sin permisos',
                'status' => RecordStatus::Active->value,
            ])->id,
            'status' => RecordStatus::Active->value,
            'joined_at' => now(),
        ]);

        $this->actingAs($outsider);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        $this->get(route('dining.tables.preview-ticket', $table))->assertForbidden();
    }

    protected function fixture(): array
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Cuenta Preliminar SAS',
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
        $product = Product::query()->create([
            'company_id' => $company->id, 'category_id' => $category->id, 'base_unit_id' => $unit->id,
            'name' => 'Hamburguesa', 'cost' => 8000, 'price_1' => 18000,
            'tracks_inventory' => false, 'minimum_stock' => 0, 'status' => RecordStatus::Active->value,
        ]);

        $table = DiningTable::query()->create([
            'company_id' => $company->id, 'branch_id' => $branch->id, 'name' => 'Mesa 1',
            'capacity' => 4, 'status' => RecordStatus::Active->value, 'occupancy_status' => 'free',
        ]);

        return [$owner, $company, $table, $product];
    }
}
