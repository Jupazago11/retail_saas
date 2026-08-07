<?php

namespace Tests\Feature;

use App\Actions\Companies\CreateCompany;
use App\Enums\RecordStatus;
use App\Livewire\Products\ProductPresentationsPage;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductPresentation;
use App\Models\Unit;
use App\Models\User;
use App\Services\Tenancy\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithCompanyPlans;
use Tests\TestCase;

class ProductPresentationsCatalogTest extends TestCase
{
    use InteractsWithCompanyPlans;
    use RefreshDatabase;

    public function test_product_presentations_page_can_create_update_archive_and_restore_presentations(): void
    {
        [$user, $company] = $this->actingUserWithCurrentCompany();
        [$product, $baseUnit] = $this->seedBaseProduct($company);
        $presentationUnit = Unit::query()->create([
            'company_id' => $company->id,
            'code' => 'CJ',
            'name' => 'Caja',
            'precision_scale' => 0,
            'status' => RecordStatus::Active->value,
        ]);

        $this->actingAs($user);

        Livewire::test(ProductPresentationsPage::class)
            ->set('productId', $product->id)
            ->set('unitId', $presentationUnit->id)
            ->set('name', 'Caja x 12')
            ->set('barcode', '7701234500001')
            ->set('conversionFactor', '12')
            ->set('price1', '54000')
            ->set('price2', '52000')
            ->call('savePresentation')
            ->assertHasNoErrors();

        $presentation = ProductPresentation::query()->where('company_id', $company->id)->firstOrFail();

        $this->assertSame('12.000000', $presentation->conversion_factor);
        $this->assertSame('Caja x 12', $presentation->name);

        Livewire::test(ProductPresentationsPage::class)
            ->call('editPresentation', $presentation->id)
            ->set('name', 'Caja x 12 unidades')
            ->set('conversionFactor', '12.5')
            ->call('savePresentation')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('product_presentations', [
            'id' => $presentation->id,
            'name' => 'Caja x 12 unidades',
        ]);

        Livewire::test(ProductPresentationsPage::class)
            ->call('archivePresentation', $presentation->id);

        $this->assertSoftDeleted('product_presentations', [
            'id' => $presentation->id,
        ]);

        Livewire::test(ProductPresentationsPage::class)
            ->call('restorePresentation', $presentation->id);

        $this->assertDatabaseHas('product_presentations', [
            'id' => $presentation->id,
            'status' => RecordStatus::Inactive->value,
            'deleted_at' => null,
        ]);
    }

    public function test_product_presentations_page_rejects_foreign_product_from_other_company(): void
    {
        [$user, $company] = $this->actingUserWithCurrentCompany();
        $unit = Unit::query()->create([
            'company_id' => $company->id,
            'code' => 'CJ',
            'name' => 'Caja',
            'precision_scale' => 0,
            'status' => RecordStatus::Active->value,
        ]);

        $otherCompany = app(CreateCompany::class)->handle($user, [
            'legal_name' => 'Retail Ajeno SAS',
        ]);
        [$foreignProduct] = $this->seedBaseProduct($otherCompany);

        $this->actingAs($user);

        Livewire::test(ProductPresentationsPage::class)
            ->set('productId', $foreignProduct->id)
            ->set('unitId', $unit->id)
            ->set('name', 'Paquete invalido')
            ->set('conversionFactor', '6')
            ->set('price1', '10000')
            ->call('savePresentation')
            ->assertHasErrors(['productId']);
    }

    public function test_product_presentations_page_only_shows_records_for_current_company(): void
    {
        [$user, $company] = $this->actingUserWithCurrentCompany();
        [$product] = $this->seedBaseProduct($company);
        $unit = Unit::query()->create([
            'company_id' => $company->id,
            'code' => 'CJ',
            'name' => 'Caja',
            'precision_scale' => 0,
            'status' => RecordStatus::Active->value,
        ]);

        ProductPresentation::query()->create([
            'company_id' => $company->id,
            'product_id' => $product->id,
            'unit_id' => $unit->id,
            'name' => 'Local',
            'conversion_factor' => '6.000000',
            'price_1' => 9000,
            'status' => RecordStatus::Active->value,
        ]);

        $otherCompany = app(CreateCompany::class)->handle($user, [
            'legal_name' => 'Segunda Empresa SAS',
        ]);
        [$otherProduct] = $this->seedBaseProduct($otherCompany);
        $otherUnit = Unit::query()->create([
            'company_id' => $otherCompany->id,
            'code' => 'PK',
            'name' => 'Paquete',
            'precision_scale' => 0,
            'status' => RecordStatus::Active->value,
        ]);

        ProductPresentation::query()->create([
            'company_id' => $otherCompany->id,
            'product_id' => $otherProduct->id,
            'unit_id' => $otherUnit->id,
            'name' => 'Ajena',
            'conversion_factor' => '8.000000',
            'price_1' => 12000,
            'status' => RecordStatus::Active->value,
        ]);

        $response = $this->actingAs($user)->get(route('products.presentations'));

        $response->assertOk();
        $response->assertSee('Local');
        $response->assertDontSee('Ajena');
    }

    protected function actingUserWithCurrentCompany(): array
    {
        $user = User::factory()->create();
        $company = app(CreateCompany::class)->handle($user, [
            'legal_name' => 'Presentaciones Retail SAS',
        ]);
        $this->assignCompanyPlan($company, 'premium');

        session([CurrentCompany::SESSION_KEY => $company->id]);

        return [$user, $company];
    }

    protected function seedBaseProduct($company): array
    {
        $category = Category::query()->create([
            'company_id' => $company->id,
            'name' => 'Abarrotes '.$company->id,
            'code' => 'ABA'.$company->id,
            'status' => RecordStatus::Active->value,
        ]);
        $baseUnit = Unit::query()->create([
            'company_id' => $company->id,
            'code' => 'UND'.$company->id,
            'name' => 'Unidad '.$company->id,
            'precision_scale' => 0,
            'status' => RecordStatus::Active->value,
        ]);
        $product = Product::query()->create([
            'company_id' => $company->id,
            'category_id' => $category->id,
            'base_unit_id' => $baseUnit->id,
            'name' => 'Producto '.$company->id,
            'cost' => 1000,
            'price_1' => 1500,
            'tracks_inventory' => true,
            'minimum_stock' => 1,
            'status' => RecordStatus::Active->value,
        ]);

        return [$product, $baseUnit];
    }
}
