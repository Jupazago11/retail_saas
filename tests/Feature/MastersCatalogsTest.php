<?php

namespace Tests\Feature;

use App\Actions\Companies\CreateCompany;
use App\Enums\RecordStatus;
use App\Livewire\Masters\BrandsPage;
use App\Livewire\Masters\CategoriesPage;
use App\Livewire\Masters\UnitsPage;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Unit;
use App\Models\User;
use App\Services\Tenancy\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MastersCatalogsTest extends TestCase
{
    use RefreshDatabase;

    public function test_categories_page_can_create_update_archive_and_restore_categories(): void
    {
        [$user, $company] = $this->actingUserWithCurrentCompany();

        $this->actingAs($user);

        Livewire::test(CategoriesPage::class)
            ->set('name', 'Bebidas')
            ->set('code', 'beb')
            ->call('saveCategory')
            ->assertHasNoErrors();

        $category = Category::query()->where('company_id', $company->id)->firstOrFail();

        $this->assertSame('BEB', $category->code);
        $this->assertSame(RecordStatus::Active->value, $category->status);

        Livewire::test(CategoriesPage::class)
            ->call('editCategory', $category->id)
            ->set('name', 'Bebidas Frias')
            ->set('code', 'BFR')
            ->call('saveCategory')
            ->assertHasNoErrors();

        $category->refresh();

        $this->assertSame('Bebidas Frias', $category->name);
        $this->assertSame('BFR', $category->code);

        Livewire::test(CategoriesPage::class)
            ->call('archiveCategory', $category->id);

        $this->assertSoftDeleted('categories', [
            'id' => $category->id,
        ]);

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'status' => RecordStatus::Archived->value,
        ]);

        Livewire::test(CategoriesPage::class)
            ->call('restoreCategory', $category->id);

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'status' => RecordStatus::Inactive->value,
            'deleted_at' => null,
        ]);
    }

    public function test_brands_page_can_create_update_archive_and_restore_brands(): void
    {
        [$user, $company] = $this->actingUserWithCurrentCompany();

        $this->actingAs($user);

        Livewire::test(BrandsPage::class)
            ->set('name', 'Acme')
            ->call('saveBrand')
            ->assertHasNoErrors();

        $brand = Brand::query()->where('company_id', $company->id)->firstOrFail();

        Livewire::test(BrandsPage::class)
            ->call('editBrand', $brand->id)
            ->set('name', 'Acme Plus')
            ->call('saveBrand')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('brands', [
            'id' => $brand->id,
            'name' => 'Acme Plus',
        ]);

        Livewire::test(BrandsPage::class)
            ->call('archiveBrand', $brand->id);

        $this->assertSoftDeleted('brands', [
            'id' => $brand->id,
        ]);

        Livewire::test(BrandsPage::class)
            ->call('restoreBrand', $brand->id);

        $this->assertDatabaseHas('brands', [
            'id' => $brand->id,
            'status' => RecordStatus::Inactive->value,
            'deleted_at' => null,
        ]);
    }

    public function test_units_page_can_create_update_archive_and_restore_units(): void
    {
        [$user, $company] = $this->actingUserWithCurrentCompany();

        $this->actingAs($user);

        Livewire::test(UnitsPage::class)
            ->set('code', 'UND')
            ->set('name', 'Unidad')
            ->set('precisionScale', 0)
            ->call('saveUnit')
            ->assertHasNoErrors();

        $unit = Unit::query()->where('company_id', $company->id)->firstOrFail();

        $this->assertSame(RecordStatus::Active->value, $unit->status);

        Livewire::test(UnitsPage::class)
            ->call('editUnit', $unit->id)
            ->set('name', 'Unidad Entera')
            ->set('precisionScale', 2)
            ->call('saveUnit')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('units', [
            'id' => $unit->id,
            'name' => 'Unidad Entera',
            'precision_scale' => 2,
        ]);

        Livewire::test(UnitsPage::class)
            ->call('archiveUnit', $unit->id);

        $this->assertDatabaseHas('units', [
            'id' => $unit->id,
            'status' => RecordStatus::Archived->value,
        ]);

        Livewire::test(UnitsPage::class)
            ->call('restoreUnit', $unit->id);

        $this->assertDatabaseHas('units', [
            'id' => $unit->id,
            'status' => RecordStatus::Inactive->value,
        ]);
    }

    public function test_masters_pages_only_show_records_for_current_company(): void
    {
        [$user, $company] = $this->actingUserWithCurrentCompany();
        $otherCompany = app(CreateCompany::class)->handle($user, [
            'legal_name' => 'Segunda Empresa SAS',
        ]);

        Category::query()->create([
            'company_id' => $company->id,
            'name' => 'Local',
            'code' => 'LOC',
            'status' => RecordStatus::Active->value,
        ]);

        Category::query()->create([
            'company_id' => $otherCompany->id,
            'name' => 'Ajena',
            'code' => 'AJE',
            'status' => RecordStatus::Active->value,
        ]);

        $response = $this->actingAs($user)->get(route('masters.categories'));

        $response->assertOk();
        $response->assertSee('Local');
        $response->assertDontSee('Ajena');
    }

    protected function actingUserWithCurrentCompany(): array
    {
        $user = User::factory()->create();
        $company = app(CreateCompany::class)->handle($user, [
            'legal_name' => 'Catalogos Retail SAS',
        ]);

        session([CurrentCompany::SESSION_KEY => $company->id]);

        return [$user, $company];
    }
}
