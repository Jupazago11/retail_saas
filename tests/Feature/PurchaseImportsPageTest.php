<?php

namespace Tests\Feature;

use App\Actions\Companies\CreateCompany;
use App\Actions\Suppliers\CreateSupplier;
use App\Enums\PurchaseStatus;
use App\Enums\RecordStatus;
use App\Livewire\Purchases\PurchaseImportsPage;
use App\Models\Category;
use App\Models\CompanyRole;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Services\Tenancy\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithCompanyPlans;
use Tests\TestCase;

class PurchaseImportsPageTest extends TestCase
{
    use InteractsWithCompanyPlans;
    use RefreshDatabase;

    public function test_purchase_import_page_creates_purchase_with_valid_rows_and_partial_errors(): void
    {
        [$owner, $company, $branch, $warehouse, $supplier, $product] = $this->fixture();

        $file = UploadedFile::fake()->createWithContent('purchase-import.csv', implode("\n", [
            'product_sku,quantity,unit_cost,tax_rate',
            'ARR-500,4,1800,19',
            'MISSING,2,900,0',
        ]));

        $this->actingAs($owner);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        Livewire::test(PurchaseImportsPage::class)
            ->set('branchId', $branch->id)
            ->set('warehouseId', $warehouse->id)
            ->set('supplierId', $supplier->id)
            ->set('invoiceNumber', 'FAC-IMP-001')
            ->set('purchaseStatus', PurchaseStatus::Confirmed->value)
            ->set('importFile', $file)
            ->call('import')
            ->assertHasNoErrors()
            ->assertSee('purchase-import.csv')
            ->assertSee('Fila 3: El producto indicado no existe para la empresa.');

        $this->assertDatabaseHas('purchases', [
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'supplier_id' => $supplier->id,
            'invoice_number' => 'FAC-IMP-001',
            'status' => PurchaseStatus::Confirmed->value,
        ]);

        $this->assertDatabaseHas('purchase_items', [
            'product_id' => $product->id,
            'quantity' => '4.000000',
            'unit_cost' => '1800.0000',
            'tax_rate' => '19.00',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $company->id,
            'action' => 'purchase.imported',
        ]);
    }

    public function test_purchase_import_route_is_forbidden_without_permission(): void
    {
        [, $company] = $this->fixture();
        $viewer = User::factory()->create();
        $companyRole = CompanyRole::query()->create([
            'company_id' => $company->id,
            'code' => 'purchase_view_only',
            'display_name' => 'Solo vista compras',
            'status' => RecordStatus::Active->value,
        ]);
        $companyRole->permissions()->attach(
            Permission::query()->where('code', 'purchases.view')->value('id')
        );

        $company->users()->attach($viewer->id, [
            'company_role' => $companyRole->code,
            'company_role_id' => $companyRole->id,
            'status' => RecordStatus::Active->value,
            'joined_at' => now(),
        ]);

        $this->actingAs($viewer)
            ->withSession([CurrentCompany::SESSION_KEY => $company->id])
            ->get(route('purchases.imports'))
            ->assertForbidden();
    }

    public function test_purchase_import_route_is_forbidden_when_plan_has_no_import_feature(): void
    {
        [$owner, $company] = $this->fixture('basic');

        $this->actingAs($owner)
            ->withSession([CurrentCompany::SESSION_KEY => $company->id])
            ->get(route('purchases.imports'))
            ->assertForbidden();
    }

    protected function fixture(string $planCode = 'pro'): array
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Compras CSV SAS',
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
            'name' => 'Arroz',
            'sku' => 'ARR-500',
            'cost' => 900,
            'price_1' => 1300,
            'tracks_inventory' => true,
            'minimum_stock' => 1,
            'status' => RecordStatus::Active->value,
        ]);

        $supplier = app(CreateSupplier::class)->handle($company, [
            'first_name' => 'Proveedor',
            'last_name' => 'Importado',
        ]);

        return [$owner, $company, $branch, $warehouse, $supplier, $product];
    }
}
