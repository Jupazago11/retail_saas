<?php

namespace Tests\Feature;

use App\Actions\Companies\CreateCompany;
use App\Enums\RecordStatus;
use App\Livewire\Products\ProductsPage;
use App\Models\Brand;
use App\Models\Category;
use App\Models\CompanyLimitOverride;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use App\Services\Tenancy\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithCompanyPlans;
use Tests\TestCase;

class ProductsCatalogTest extends TestCase
{
    use InteractsWithCompanyPlans, RefreshDatabase;

    public function test_products_page_can_create_update_archive_and_restore_products(): void
    {
        [$user, $company] = $this->actingUserWithCurrentCompany();
        $category = Category::query()->create([
            'company_id' => $company->id,
            'name' => 'Lacteos',
            'code' => 'LAC',
            'status' => RecordStatus::Active->value,
        ]);
        $brand = Brand::query()->create([
            'company_id' => $company->id,
            'name' => 'Campo Vivo',
            'status' => RecordStatus::Active->value,
        ]);
        $unit = Unit::query()->create([
            'company_id' => $company->id,
            'code' => 'UND',
            'name' => 'Unidad',
            'precision_scale' => 0,
            'status' => RecordStatus::Active->value,
        ]);

        $this->actingAs($user);

        Livewire::test(ProductsPage::class)
            ->set('categoryId', $category->id)
            ->set('brandId', $brand->id)
            ->set('baseUnitId', $unit->id)
            ->set('name', 'Leche Entera 1L')
            ->set('sku', 'LEC-001')
            ->set('barcode', '7701234567890')
            ->set('cost', '3200')
            ->set('price1', '4500')
            ->set('price2', '4300')
            ->set('minimumStock', '6')
            ->call('saveProduct')
            ->assertHasNoErrors();

        $product = Product::query()->where('company_id', $company->id)->firstOrFail();

        $this->assertSame('LEC-001', $product->sku);
        $this->assertSame('40.63', $product->margin_1);
        $this->assertSame(RecordStatus::Active->value, $product->status);

        Livewire::test(ProductsPage::class)
            ->call('editProduct', $product->id)
            ->set('name', 'Leche Entera 1 Litro')
            ->set('price1', '4700')
            ->call('saveProduct')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'Leche Entera 1 Litro',
        ]);

        Livewire::test(ProductsPage::class)
            ->call('archiveProduct', $product->id);

        $this->assertSoftDeleted('products', [
            'id' => $product->id,
        ]);

        Livewire::test(ProductsPage::class)
            ->call('restoreProduct', $product->id);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'status' => RecordStatus::Inactive->value,
            'deleted_at' => null,
        ]);
    }

    public function test_products_page_rejects_foreign_masters_from_other_company(): void
    {
        [$user, $company] = $this->actingUserWithCurrentCompany();
        $this->assignCompanyPlan($company, 'premium');
        $unit = Unit::query()->create([
            'company_id' => $company->id,
            'code' => 'UND',
            'name' => 'Unidad',
            'precision_scale' => 0,
            'status' => RecordStatus::Active->value,
        ]);

        $otherCompany = app(CreateCompany::class)->handle($user, [
            'legal_name' => 'Otra Retail SAS',
        ]);

        $foreignCategory = Category::query()->create([
            'company_id' => $otherCompany->id,
            'name' => 'Ajena',
            'code' => 'AJE',
            'status' => RecordStatus::Active->value,
        ]);

        $this->actingAs($user);

        Livewire::test(ProductsPage::class)
            ->set('categoryId', $foreignCategory->id)
            ->set('baseUnitId', $unit->id)
            ->set('name', 'Producto Invalido')
            ->set('cost', '1000')
            ->set('price1', '1500')
            ->set('minimumStock', '1')
            ->call('saveProduct')
            ->assertHasErrors(['categoryId']);
    }

    public function test_products_page_only_shows_records_for_current_company(): void
    {
        [$user, $company] = $this->actingUserWithCurrentCompany();
        $this->assignCompanyPlan($company, 'premium');
        $category = Category::query()->create([
            'company_id' => $company->id,
            'name' => 'Bebidas',
            'code' => 'BEB',
            'status' => RecordStatus::Active->value,
        ]);
        $unit = Unit::query()->create([
            'company_id' => $company->id,
            'code' => 'UND',
            'name' => 'Unidad',
            'precision_scale' => 0,
            'status' => RecordStatus::Active->value,
        ]);

        Product::query()->create([
            'company_id' => $company->id,
            'category_id' => $category->id,
            'base_unit_id' => $unit->id,
            'name' => 'Producto Local',
            'cost' => 1000,
            'price_1' => 1500,
            'tracks_inventory' => true,
            'minimum_stock' => 1,
            'status' => RecordStatus::Active->value,
        ]);

        $otherCompany = app(CreateCompany::class)->handle($user, [
            'legal_name' => 'Segunda Empresa SAS',
        ]);
        $otherCategory = Category::query()->create([
            'company_id' => $otherCompany->id,
            'name' => 'Aseo',
            'code' => 'ASE',
            'status' => RecordStatus::Active->value,
        ]);
        $otherUnit = Unit::query()->create([
            'company_id' => $otherCompany->id,
            'code' => 'CJ',
            'name' => 'Caja',
            'precision_scale' => 0,
            'status' => RecordStatus::Active->value,
        ]);

        Product::query()->create([
            'company_id' => $otherCompany->id,
            'category_id' => $otherCategory->id,
            'base_unit_id' => $otherUnit->id,
            'name' => 'Producto Ajeno',
            'cost' => 2000,
            'price_1' => 3000,
            'tracks_inventory' => true,
            'minimum_stock' => 2,
            'status' => RecordStatus::Active->value,
        ]);

        $response = $this->actingAs($user)->get(route('products.index'));

        $response->assertOk();
        $response->assertSee('Producto Local');
        $response->assertDontSee('Producto Ajeno');
    }

    public function test_products_page_blocks_creation_when_company_reaches_plan_product_limit(): void
    {
        [$user, $company] = $this->actingUserWithCurrentCompany();
        $this->assignCompanyPlan($company, 'basic');

        CompanyLimitOverride::query()->create([
            'company_id' => $company->id,
            'limit_key' => 'max_products',
            'limit_value' => 1,
            'starts_at' => now()->subMinute(),
        ]);

        $category = Category::query()->create([
            'company_id' => $company->id,
            'name' => 'Despensa',
            'code' => 'DES',
            'status' => RecordStatus::Active->value,
        ]);
        $unit = Unit::query()->create([
            'company_id' => $company->id,
            'code' => 'UND',
            'name' => 'Unidad',
            'precision_scale' => 0,
            'status' => RecordStatus::Active->value,
        ]);

        Product::query()->create([
            'company_id' => $company->id,
            'category_id' => $category->id,
            'base_unit_id' => $unit->id,
            'name' => 'Producto Base',
            'cost' => 1000,
            'price_1' => 1500,
            'tracks_inventory' => true,
            'minimum_stock' => 1,
            'status' => RecordStatus::Active->value,
        ]);

        $this->actingAs($user);

        Livewire::test(ProductsPage::class)
            ->set('categoryId', $category->id)
            ->set('baseUnitId', $unit->id)
            ->set('name', 'Producto Excedido')
            ->set('cost', '1200')
            ->set('price1', '1800')
            ->set('minimumStock', '1')
            ->call('saveProduct')
            ->assertHasErrors(['name']);

        $this->assertDatabaseMissing('products', [
            'company_id' => $company->id,
            'name' => 'Producto Excedido',
        ]);
    }

    public function test_products_page_creates_category_and_brand_from_typed_name_on_save(): void
    {
        [$user, $company] = $this->actingUserWithCurrentCompany();
        $unit = Unit::query()->create([
            'company_id' => $company->id,
            'code' => 'UND',
            'name' => 'Unidad',
            'precision_scale' => 0,
            'status' => RecordStatus::Active->value,
        ]);
        // canCreateProducts() exige al menos una categoria existente; la
        // nueva ("Bebidas") se crea al vuelo desde el nombre tipeado.
        Category::query()->create([
            'company_id' => $company->id,
            'name' => 'Otra',
            'code' => 'OTRA',
            'status' => RecordStatus::Active->value,
        ]);

        $this->actingAs($user);

        Livewire::test(ProductsPage::class)
            ->set('categoryId', 'Bebidas')
            ->set('brandId', 'Postobon')
            ->set('baseUnitId', $unit->id)
            ->set('name', 'Gaseosa 1.5L')
            ->set('cost', '2000')
            ->set('price1', '3000')
            ->set('minimumStock', '1')
            ->call('saveProduct')
            ->assertHasNoErrors();

        $category = Category::query()->where('company_id', $company->id)->where('name', 'Bebidas')->first();
        $brand = Brand::query()->where('company_id', $company->id)->where('name', 'Postobon')->first();

        $this->assertNotNull($category);
        $this->assertNotNull($brand);
        $this->assertDatabaseHas('products', [
            'company_id' => $company->id,
            'name' => 'Gaseosa 1.5L',
            'category_id' => $category->id,
            'brand_id' => $brand->id,
        ]);

        // Escribir el mismo nombre con otra capitalizacion reutiliza la
        // categoria/marca existente en vez de duplicarla.
        Livewire::test(ProductsPage::class)
            ->set('categoryId', 'bebidas')
            ->set('brandId', 'postobon')
            ->set('baseUnitId', $unit->id)
            ->set('name', 'Gaseosa 2L')
            ->set('cost', '2200')
            ->set('price1', '3300')
            ->set('minimumStock', '1')
            ->call('saveProduct')
            ->assertHasNoErrors();

        $this->assertSame(1, Category::query()->where('company_id', $company->id)->whereRaw('upper(name) = ?', ['BEBIDAS'])->count());
        $this->assertSame(1, Brand::query()->where('company_id', $company->id)->whereRaw('upper(name) = ?', ['POSTOBON'])->count());
        $this->assertDatabaseHas('products', [
            'company_id' => $company->id,
            'name' => 'Gaseosa 2L',
            'category_id' => $category->id,
            'brand_id' => $brand->id,
        ]);
    }

    public function test_products_page_tax_rate_defaults_to_zero_and_rejects_negative(): void
    {
        [$user, $company] = $this->actingUserWithCurrentCompany();
        $category = Category::query()->create([
            'company_id' => $company->id,
            'name' => 'Aseo',
            'code' => 'ASE',
            'status' => RecordStatus::Active->value,
        ]);
        $unit = Unit::query()->create([
            'company_id' => $company->id,
            'code' => 'UND',
            'name' => 'Unidad',
            'precision_scale' => 0,
            'status' => RecordStatus::Active->value,
        ]);

        $this->actingAs($user);

        Livewire::test(ProductsPage::class)
            ->set('categoryId', $category->id)
            ->set('baseUnitId', $unit->id)
            ->set('name', 'Producto Sin IVA')
            ->set('cost', '1000')
            ->set('price1', '1500')
            ->set('minimumStock', '1')
            ->set('taxRate', '')
            ->call('saveProduct')
            ->assertHasNoErrors();

        $product = Product::query()->where('company_id', $company->id)->where('name', 'Producto Sin IVA')->firstOrFail();
        $this->assertSame('0.00', $product->tax_rate);

        Livewire::test(ProductsPage::class)
            ->set('categoryId', $category->id)
            ->set('baseUnitId', $unit->id)
            ->set('name', 'Producto IVA Invalido')
            ->set('cost', '1000')
            ->set('price1', '1500')
            ->set('minimumStock', '1')
            ->set('taxRate', '-5')
            ->call('saveProduct')
            ->assertHasErrors(['taxRate']);

        // El input acepta coma o punto decimal (el usuario puede escribir
        // cualquiera de los dos, se normaliza antes de guardar).
        Livewire::test(ProductsPage::class)
            ->set('categoryId', $category->id)
            ->set('baseUnitId', $unit->id)
            ->set('name', 'Producto IVA Con Coma')
            ->set('cost', '1000')
            ->set('price1', '1500')
            ->set('minimumStock', '1')
            ->set('taxRate', '5,5')
            ->call('saveProduct')
            ->assertHasNoErrors();

        $productWithComma = Product::query()->where('company_id', $company->id)->where('name', 'Producto IVA Con Coma')->firstOrFail();
        $this->assertSame('5.50', $productWithComma->tax_rate);
    }

    public function test_products_page_preselects_base_unit_when_company_has_only_one(): void
    {
        [$user, $company] = $this->actingUserWithCurrentCompany();
        Category::query()->create([
            'company_id' => $company->id,
            'name' => 'Aseo',
            'code' => 'ASE',
            'status' => RecordStatus::Active->value,
        ]);
        $unit = Unit::query()->create([
            'company_id' => $company->id,
            'code' => 'UND',
            'name' => 'Unidad',
            'precision_scale' => 0,
            'status' => RecordStatus::Active->value,
        ]);

        $this->actingAs($user);

        Livewire::test(ProductsPage::class)
            ->call('openModal')
            ->assertSet('baseUnitId', $unit->id);

        Unit::query()->create([
            'company_id' => $company->id,
            'code' => 'KG',
            'name' => 'Kilogramo',
            'precision_scale' => 3,
            'status' => RecordStatus::Active->value,
        ]);

        Livewire::test(ProductsPage::class)
            ->call('openModal')
            ->assertSet('baseUnitId', null);
    }

    protected function actingUserWithCurrentCompany(): array
    {
        $user = User::factory()->create();
        $company = app(CreateCompany::class)->handle($user, [
            'legal_name' => 'Productos Retail SAS',
        ]);

        session([CurrentCompany::SESSION_KEY => $company->id]);

        return [$user, $company];
    }
}
