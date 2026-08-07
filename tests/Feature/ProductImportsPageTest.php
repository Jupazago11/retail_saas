<?php

namespace Tests\Feature;

use App\Actions\Companies\CreateCompany;
use App\Enums\RecordStatus;
use App\Livewire\Products\ProductImportsPage;
use App\Models\Brand;
use App\Models\Category;
use App\Models\CompanyLimitOverride;
use App\Models\CompanyRole;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use App\Services\Tenancy\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithCompanyPlans;
use Tests\TestCase;

class ProductImportsPageTest extends TestCase
{
    use InteractsWithCompanyPlans;
    use RefreshDatabase;

    public function test_product_import_page_imports_valid_rows_and_reports_invalid_ones(): void
    {
        [$owner, $company] = $this->fixture();
        $this->actingAs($owner);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        $file = UploadedFile::fake()->createWithContent('products.csv', implode("\n", [
            'name,category_code,unit_code,cost,price_1,brand_name,sku,minimum_stock,tracks_inventory,status',
            'Arroz Importado,ABA,UND,1800,2500,Marca Import,ARR-001,3,true,active',
            'Fila Mala,NOPE,UND,2000,3000,Marca Import,ARR-002,1,true,active',
        ]));

        Livewire::test(ProductImportsPage::class)
            ->set('importFile', $file)
            ->call('import')
            ->assertHasNoErrors()
            ->assertSee('products.csv')
            ->assertSee('Creados')
            ->assertSee('Errores')
            ->assertSee('Fila 3: La categoria indicada no existe o no esta activa en la empresa.');

        $this->assertDatabaseHas('products', [
            'company_id' => $company->id,
            'name' => 'Arroz Importado',
            'sku' => 'ARR-001',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $company->id,
            'action' => 'products.imported',
        ]);
    }

    public function test_product_import_page_respects_product_limit_during_batch(): void
    {
        [$owner, $company] = $this->fixture();

        CompanyLimitOverride::query()->create([
            'company_id' => $company->id,
            'limit_key' => 'max_products',
            'limit_value' => 1,
            'starts_at' => now()->subMinute(),
        ]);

        $this->actingAs($owner);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        $file = UploadedFile::fake()->createWithContent('products-limit.csv', implode("\n", [
            'name,category_code,unit_code,cost,price_1,sku',
            'Producto Uno,ABA,UND,1800,2500,IMP-001',
            'Producto Dos,ABA,UND,2000,2700,IMP-002',
        ]));

        Livewire::test(ProductImportsPage::class)
            ->set('importFile', $file)
            ->call('import')
            ->assertHasNoErrors()
            ->assertSee('Fila 3: El plan actual ya alcanzo el limite de productos activos permitidos.');

        $this->assertSame(1, Product::query()->where('company_id', $company->id)->count());
    }

    public function test_product_import_route_is_forbidden_without_products_create_permission(): void
    {
        [, $company] = $this->fixture();
        $viewer = User::factory()->create();
        $companyRole = CompanyRole::query()->create([
            'company_id' => $company->id,
            'code' => 'products_view_only',
            'display_name' => 'Solo lectura',
            'status' => RecordStatus::Active->value,
        ]);
        $companyRole->permissions()->attach(
            Permission::query()->where('code', 'products.view')->value('id')
        );

        $company->users()->attach($viewer->id, [
            'company_role' => $companyRole->code,
            'company_role_id' => $companyRole->id,
            'status' => RecordStatus::Active->value,
            'joined_at' => now(),
        ]);

        $this->actingAs($viewer)
            ->withSession([CurrentCompany::SESSION_KEY => $company->id])
            ->get(route('products.imports'))
            ->assertForbidden();
    }

    public function test_product_import_route_is_forbidden_when_plan_has_no_import_feature(): void
    {
        [$owner, $company] = $this->fixture('basic');

        $this->actingAs($owner)
            ->withSession([CurrentCompany::SESSION_KEY => $company->id])
            ->get(route('products.imports'))
            ->assertForbidden();
    }

    protected function fixture(string $planCode = 'pro'): array
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Importaciones Productos SAS',
        ]);
        $this->assignCompanyPlan($company, $planCode);

        Category::query()->create([
            'company_id' => $company->id,
            'name' => 'Abarrotes',
            'code' => 'ABA',
            'status' => RecordStatus::Active->value,
        ]);

        Unit::query()->create([
            'company_id' => $company->id,
            'code' => 'UND',
            'name' => 'Unidad',
            'precision_scale' => 0,
            'status' => RecordStatus::Active->value,
        ]);

        Brand::query()->create([
            'company_id' => $company->id,
            'name' => 'Marca Import',
            'status' => RecordStatus::Active->value,
        ]);

        return [$owner, $company];
    }
}
