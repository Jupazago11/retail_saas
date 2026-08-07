<?php

namespace Tests\Feature;

use App\Actions\Companies\CreateCompany;
use App\Enums\RecordStatus;
use App\Livewire\Inventory\InventoryAdjustmentImportsPage;
use App\Models\Category;
use App\Models\CompanyRole;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Unit;
use App\Models\User;
use App\Services\Tenancy\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithCompanyPlans;
use Tests\TestCase;

class InventoryAdjustmentImportsPageTest extends TestCase
{
    use InteractsWithCompanyPlans;
    use RefreshDatabase;

    public function test_inventory_adjustment_import_page_creates_single_adjustment_with_valid_rows(): void
    {
        [$owner, $company, $branch, $warehouse, $product, $variant] = $this->fixture();

        $file = UploadedFile::fake()->createWithContent('inventory-adjustment.csv', implode("\n", [
            'product_sku,variant_sku,quantity,unit_cost',
            'ARR-500,,10,1800',
            'ARR-VAR,ROJ-01,3,1900',
            'MISSING,,2,1500',
        ]));

        $this->actingAs($owner);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        Livewire::test(InventoryAdjustmentImportsPage::class)
            ->set('branchId', $branch->id)
            ->set('warehouseId', $warehouse->id)
            ->set('adjustmentType', 'increase')
            ->set('reason', 'Migracion inicial')
            ->set('importFile', $file)
            ->call('import')
            ->assertHasNoErrors()
            ->assertSee('inventory-adjustment.csv')
            ->assertSee('Fila 4: El producto indicado no existe para la empresa.');

        $this->assertDatabaseHas('inventory_adjustments', [
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'adjustment_type' => 'increase',
            'reason' => 'Migracion inicial',
        ]);

        $this->assertDatabaseHas('inventory_adjustment_items', [
            'product_id' => $product->id,
            'product_variant_id' => null,
            'quantity' => '10.000000',
            'unit_cost' => '1800.0000',
        ]);

        $this->assertDatabaseHas('inventory_adjustment_items', [
            'product_id' => $variant->product_id,
            'product_variant_id' => $variant->id,
            'quantity' => '3.000000',
            'unit_cost' => '1900.0000',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $company->id,
            'action' => 'inventory.adjustment.imported',
        ]);
    }

    public function test_inventory_adjustment_import_route_is_forbidden_without_permission(): void
    {
        [, $company] = $this->fixture();
        $viewer = User::factory()->create();
        $companyRole = CompanyRole::query()->create([
            'company_id' => $company->id,
            'code' => 'inventory_view_only',
            'display_name' => 'Solo vista de inventario',
            'status' => RecordStatus::Active->value,
        ]);
        $companyRole->permissions()->attach(
            Permission::query()->where('code', 'inventory.view')->value('id')
        );

        $company->users()->attach($viewer->id, [
            'company_role' => $companyRole->code,
            'company_role_id' => $companyRole->id,
            'status' => RecordStatus::Active->value,
            'joined_at' => now(),
        ]);

        $this->actingAs($viewer)
            ->withSession([CurrentCompany::SESSION_KEY => $company->id])
            ->get(route('inventory.imports'))
            ->assertForbidden();
    }

    public function test_inventory_adjustment_import_route_is_forbidden_when_plan_has_no_import_feature(): void
    {
        [$owner, $company] = $this->fixture('basic');

        $this->actingAs($owner)
            ->withSession([CurrentCompany::SESSION_KEY => $company->id])
            ->get(route('inventory.imports'))
            ->assertForbidden();
    }

    protected function fixture(string $planCode = 'pro'): array
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Inventario CSV SAS',
        ]);
        $this->assignCompanyPlan($company, $planCode);

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

        $product = Product::query()->create([
            'company_id' => $company->id,
            'category_id' => $category->id,
            'base_unit_id' => $unit->id,
            'name' => 'Arroz 500g',
            'sku' => 'ARR-500',
            'cost' => 1000,
            'price_1' => 1800,
            'tracks_inventory' => true,
            'minimum_stock' => 1,
            'status' => RecordStatus::Active->value,
        ]);

        $variantProduct = Product::query()->create([
            'company_id' => $company->id,
            'category_id' => $category->id,
            'base_unit_id' => $unit->id,
            'name' => 'Arroz color',
            'sku' => 'ARR-VAR',
            'cost' => 1100,
            'price_1' => 1900,
            'tracks_inventory' => true,
            'minimum_stock' => 1,
            'status' => RecordStatus::Active->value,
        ]);

        $variant = ProductVariant::query()->create([
            'company_id' => $company->id,
            'product_id' => $variantProduct->id,
            'sku' => 'ROJ-01',
            'status' => RecordStatus::Active->value,
        ]);

        return [$owner, $company, $branch, $warehouse, $product, $variant];
    }
}
