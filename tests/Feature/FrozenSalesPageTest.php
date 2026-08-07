<?php

namespace Tests\Feature;

use App\Actions\Companies\CreateCompany;
use App\Actions\Inventory\CreateInventoryAdjustment;
use App\Enums\FrozenSaleStatus;
use App\Enums\InventoryAdjustmentType;
use App\Enums\RecordStatus;
use App\Livewire\Sales\FrozenSalesPage;
use App\Models\Attribute;
use App\Models\Brand;
use App\Models\Category;
use App\Models\CompanyRole;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductPresentation;
use App\Models\ProductVariant;
use App\Models\RoleTemplate;
use App\Models\Unit;
use App\Models\User;
use App\Services\Tenancy\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithCompanyPlans;
use Tests\TestCase;

class FrozenSalesPageTest extends TestCase
{
    use InteractsWithCompanyPlans;
    use RefreshDatabase;

    public function test_frozen_sales_page_can_create_resume_and_convert_frozen_sale(): void
    {
        [$company, $seller, $branch, $warehouse, $cashRegister, $product, $presentation, $variant] = $this->frozenFixtureWithTemplateUser('seller');

        app(CreateInventoryAdjustment::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'adjustment_type' => InventoryAdjustmentType::Increase->value,
            'reason' => 'Stock inicial para conversion',
            'items' => [
                [
                    'product_id' => $product->id,
                    'product_variant_id' => $variant->id,
                    'quantity' => '12',
                    'unit_cost' => '1000',
                ],
            ],
        ]);

        $this->actingAs($seller);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        Livewire::test(FrozenSalesPage::class)
            ->set('branchId', $branch->id)
            ->set('warehouseId', $warehouse->id)
            ->set('cashRegisterId', $cashRegister->id)
            ->set('label', 'Mesa 12')
            ->set('items.0.product_id', (string) $product->id)
            ->set('items.0.product_presentation_id', (string) $presentation->id)
            ->set('items.0.product_variant_id', (string) $variant->id)
            ->set('items.0.quantity', '1')
            ->set('items.0.unit_price', '15000')
            ->call('saveFrozenSale')
            ->assertHasNoErrors()
            ->assertSee('Mesa 12');

        $frozenSale = $company->frozenSales()->firstOrFail();
        $this->assertSame(FrozenSaleStatus::Open->value, $frozenSale->status);

        Livewire::test(FrozenSalesPage::class)
            ->call('startResumingFrozenSale', $frozenSale->id)
            ->assertSet('resumingFrozenSaleId', $frozenSale->id)
            ->assertSet('label', 'Mesa 12')
            ->assertSet('items.0.product_id', (string) $product->id);

        Livewire::test(FrozenSalesPage::class)
            ->call('convertFrozenSaleToSale', $frozenSale->id);

        $frozenSale = $frozenSale->fresh();
        $this->assertSame(FrozenSaleStatus::Converted->value, $frozenSale->status);
        $this->assertNotNull($frozenSale->converted_sale_id);
        $this->assertDatabaseHas('sales', [
            'company_id' => $company->id,
            'id' => $frozenSale->converted_sale_id,
            'status' => 'confirmed',
        ]);
    }

    public function test_frozen_sales_page_can_cancel_open_frozen_sale(): void
    {
        [$company, $seller, $branch, $warehouse, $cashRegister, $product] = $this->frozenFixtureWithTemplateUser('seller');

        $this->actingAs($seller);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        Livewire::test(FrozenSalesPage::class)
            ->set('branchId', $branch->id)
            ->set('warehouseId', $warehouse->id)
            ->set('cashRegisterId', $cashRegister->id)
            ->set('label', 'Mesa cancelar')
            ->set('items.0.product_id', (string) $product->id)
            ->set('items.0.quantity', '1')
            ->set('items.0.unit_price', '2000')
            ->call('saveFrozenSale')
            ->assertHasNoErrors();

        $frozenSale = $company->frozenSales()->firstOrFail();

        Livewire::test(FrozenSalesPage::class)
            ->call('cancelFrozenSale', $frozenSale->id);

        $this->assertSame(FrozenSaleStatus::Cancelled->value, $frozenSale->fresh()->status);
    }

    public function test_frozen_sales_page_route_is_forbidden_without_freeze_or_create_permission(): void
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Frozen Route SAS',
        ]);
        $viewer = User::factory()->create();
        $companyRole = CompanyRole::query()->create([
            'company_id' => $company->id,
            'code' => 'sales_view_only',
            'display_name' => 'Solo lectura ventas',
            'status' => RecordStatus::Active->value,
        ]);

        $companyRole->permissions()->attach(
            Permission::query()->where('code', 'sales.view')->value('id')
        );

        $company->users()->attach($viewer->id, [
            'company_role' => 'sales_view_only',
            'company_role_id' => $companyRole->id,
            'status' => RecordStatus::Active->value,
            'joined_at' => now(),
        ]);

        $this->actingAs($viewer)
            ->withSession([CurrentCompany::SESSION_KEY => $company->id])
            ->get(route('sales.frozen'))
            ->assertForbidden();
    }

    protected function frozenFixtureWithTemplateUser(string $templateCode): array
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Frozen UI SAS',
        ]);
        $this->assignCompanyPlan($company, 'pro');
        $user = User::factory()->create();

        $company->users()->attach($user->id, [
            'company_role' => $templateCode,
            'role_template_id' => RoleTemplate::query()->where('code', $templateCode)->value('id'),
            'status' => RecordStatus::Active->value,
            'joined_at' => now(),
        ]);

        $branch = $company->branches()->firstOrFail();
        $warehouse = $company->warehouses()->firstOrFail();
        $cashRegister = $company->cashRegisters()->firstOrFail();
        $unit = Unit::query()->create([
            'company_id' => $company->id,
            'code' => 'UND',
            'name' => 'Unidad',
            'precision_scale' => 0,
            'status' => RecordStatus::Active->value,
        ]);
        $category = Category::query()->create([
            'company_id' => $company->id,
            'name' => 'Bebidas',
            'code' => 'BEB',
            'status' => RecordStatus::Active->value,
        ]);
        $brand = Brand::query()->create([
            'company_id' => $company->id,
            'name' => 'Costa Azul',
            'status' => RecordStatus::Active->value,
        ]);
        $product = Product::query()->create([
            'company_id' => $company->id,
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'base_unit_id' => $unit->id,
            'name' => 'Gaseosa UI',
            'cost' => 1000,
            'price_1' => 2500,
            'tracks_inventory' => true,
            'minimum_stock' => 1,
            'status' => RecordStatus::Active->value,
        ]);
        $presentation = ProductPresentation::query()->create([
            'company_id' => $company->id,
            'product_id' => $product->id,
            'unit_id' => $unit->id,
            'name' => 'Pack x 6',
            'conversion_factor' => '6.000000',
            'price_1' => 15000,
            'status' => RecordStatus::Active->value,
        ]);
        $attribute = Attribute::query()->create([
            'company_id' => $company->id,
            'name' => 'Sabor',
            'code' => 'SAB',
            'status' => RecordStatus::Active->value,
        ]);
        $value = $attribute->values()->create([
            'value' => 'Cola',
            'status' => RecordStatus::Active->value,
        ]);
        $variant = ProductVariant::query()->create([
            'company_id' => $company->id,
            'product_id' => $product->id,
            'sku' => 'GAS-UI-COL',
            'status' => RecordStatus::Active->value,
        ]);
        $variant->attributeValues()->attach($value->id);

        return [$company, $user, $branch, $warehouse, $cashRegister, $product, $presentation, $variant];
    }
}
