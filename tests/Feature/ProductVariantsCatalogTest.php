<?php

namespace Tests\Feature;

use App\Actions\Companies\CreateCompany;
use App\Enums\RecordStatus;
use App\Livewire\Products\ProductVariantsPage;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Unit;
use App\Models\User;
use App\Services\Tenancy\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithCompanyPlans;
use Tests\TestCase;

class ProductVariantsCatalogTest extends TestCase
{
    use InteractsWithCompanyPlans;
    use RefreshDatabase;

    public function test_product_variants_page_can_create_update_archive_and_restore_variants(): void
    {
        [$user, $company] = $this->actingUserWithCurrentCompany();
        [$product, $redValue, $largeValue] = $this->seedVariantScenario($company);

        $this->actingAs($user);

        Livewire::test(ProductVariantsPage::class)
            ->set('productId', $product->id)
            ->set('sku', 'CAM-RED-L')
            ->set('barcode', '7701000000001')
            ->set('priceOverride', '89000')
            ->set('selectedAttributeValueIds', [(string) $redValue->id, (string) $largeValue->id])
            ->call('saveVariant')
            ->assertHasNoErrors();

        $variant = ProductVariant::query()->where('company_id', $company->id)->firstOrFail();

        $this->assertSame('CAM-RED-L', $variant->sku);
        $this->assertCount(2, $variant->attributeValues);

        Livewire::test(ProductVariantsPage::class)
            ->call('editVariant', $variant->id)
            ->set('priceOverride', '91000')
            ->call('saveVariant')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('product_variants', [
            'id' => $variant->id,
            'price_override' => '91000.00',
        ]);

        Livewire::test(ProductVariantsPage::class)
            ->call('archiveVariant', $variant->id);

        $this->assertDatabaseHas('product_variants', [
            'id' => $variant->id,
            'status' => RecordStatus::Archived->value,
        ]);

        Livewire::test(ProductVariantsPage::class)
            ->call('restoreVariant', $variant->id);

        $this->assertDatabaseHas('product_variants', [
            'id' => $variant->id,
            'status' => RecordStatus::Inactive->value,
        ]);
    }

    public function test_product_variants_page_rejects_duplicate_attribute_combination_for_same_product(): void
    {
        [$user, $company] = $this->actingUserWithCurrentCompany();
        [$product, $redValue, $largeValue] = $this->seedVariantScenario($company);

        ProductVariant::query()->create([
            'company_id' => $company->id,
            'product_id' => $product->id,
            'sku' => 'BASE-1',
            'status' => RecordStatus::Active->value,
        ])->attributeValues()->sync([$redValue->id, $largeValue->id]);

        $this->actingAs($user);

        Livewire::test(ProductVariantsPage::class)
            ->set('productId', $product->id)
            ->set('selectedAttributeValueIds', [(string) $redValue->id, (string) $largeValue->id])
            ->call('saveVariant')
            ->assertHasErrors(['selectedAttributeValueIds']);
    }

    public function test_product_variants_page_only_shows_records_for_current_company(): void
    {
        [$user, $company] = $this->actingUserWithCurrentCompany();
        [$product, $redValue, $largeValue] = $this->seedVariantScenario($company);

        ProductVariant::query()->create([
            'company_id' => $company->id,
            'product_id' => $product->id,
            'sku' => 'LOCAL',
            'status' => RecordStatus::Active->value,
        ])->attributeValues()->sync([$redValue->id, $largeValue->id]);

        $otherCompany = app(CreateCompany::class)->handle($user, [
            'legal_name' => 'Segunda Empresa SAS',
        ]);
        [$otherProduct, $otherValueA, $otherValueB] = $this->seedVariantScenario($otherCompany);

        ProductVariant::query()->create([
            'company_id' => $otherCompany->id,
            'product_id' => $otherProduct->id,
            'sku' => 'AJENO',
            'status' => RecordStatus::Active->value,
        ])->attributeValues()->sync([$otherValueA->id, $otherValueB->id]);

        $response = $this->actingAs($user)->get(route('products.variants'));

        $response->assertOk();
        $response->assertSee('LOCAL');
        $response->assertDontSee('AJENO');
    }

    protected function actingUserWithCurrentCompany(): array
    {
        $user = User::factory()->create();
        $company = app(CreateCompany::class)->handle($user, [
            'legal_name' => 'Variantes Retail SAS',
        ]);
        $this->assignCompanyPlan($company, 'premium');

        session([CurrentCompany::SESSION_KEY => $company->id]);

        return [$user, $company];
    }

    protected function seedVariantScenario($company): array
    {
        $category = Category::query()->create([
            'company_id' => $company->id,
            'name' => 'Ropa '.$company->id,
            'code' => 'ROP'.$company->id,
            'status' => RecordStatus::Active->value,
        ]);
        $unit = Unit::query()->create([
            'company_id' => $company->id,
            'code' => 'UND'.$company->id,
            'name' => 'Unidad '.$company->id,
            'precision_scale' => 0,
            'status' => RecordStatus::Active->value,
        ]);
        $product = Product::query()->create([
            'company_id' => $company->id,
            'category_id' => $category->id,
            'base_unit_id' => $unit->id,
            'name' => 'Camiseta '.$company->id,
            'cost' => 50000,
            'price_1' => 80000,
            'tracks_inventory' => true,
            'minimum_stock' => 1,
            'status' => RecordStatus::Active->value,
        ]);

        $color = Attribute::query()->create([
            'company_id' => $company->id,
            'name' => 'Color '.$company->id,
            'code' => 'COL'.$company->id,
            'status' => RecordStatus::Active->value,
        ]);
        $size = Attribute::query()->create([
            'company_id' => $company->id,
            'name' => 'Talla '.$company->id,
            'code' => 'TAL'.$company->id,
            'status' => RecordStatus::Active->value,
        ]);

        $redValue = AttributeValue::query()->create([
            'attribute_id' => $color->id,
            'value' => 'Rojo',
            'status' => RecordStatus::Active->value,
        ]);
        $largeValue = AttributeValue::query()->create([
            'attribute_id' => $size->id,
            'value' => 'L',
            'status' => RecordStatus::Active->value,
        ]);

        return [$product, $redValue, $largeValue];
    }
}
